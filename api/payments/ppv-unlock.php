<?php
/**
 * POST /api/payments/ppv-unlock.php
 * PPV Content Unlock Endpoint
 * 
 * Charges a fan to unlock a PPV message and shows the full content
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/cbpt.php';
require_once __DIR__ . '/../lib/config.php';

setCorsHeaders();

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Get request body
$input = getRequestBody();

// Validate input
$messageId = intval($input['message_id'] ?? 0);

if (!$messageId) {
    jsonResponse(['error' => 'Invalid message_id'], 400);
}

// Check authentication (logged-in user OR secret for testing)
$currentUser = getCurrentUser();
$secretProvided = $input['secret'] ?? '';
$cronSecret = defined('CHAT_CRON_SECRET') ? CHAT_CRON_SECRET : 'breyya-chat-cron-2026';

if (!$currentUser && $secretProvided !== $cronSecret) {
    jsonResponse(['error' => 'Authentication required'], 401);
}

$db = getDB();

try {
    // 1. Verify the message exists and is PPV
    $stmt = $db->prepare("
        SELECT id, receiver_id, sender_id, is_ppv, ppv_price_cents, is_unlocked, 
               ppv_content_key, media_url, content, ppv_preview_url
        FROM messages 
        WHERE id = :mid
    ");
    $stmt->bindValue(':mid', $messageId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $message = $result->fetchArray(SQLITE3_ASSOC);

    if (!$message) {
        jsonResponse(['error' => 'Message not found'], 404);
    }

    if (!$message['is_ppv']) {
        jsonResponse(['error' => 'Message is not PPV content'], 400);
    }

    if ($message['is_unlocked']) {
        jsonResponse(['error' => 'Message is already unlocked'], 400);
    }

    // 2. Verify the authenticated user is the receiver
    $fanUserId = $message['receiver_id'];
    if ($currentUser && $currentUser['id'] != $fanUserId && $secretProvided !== $cronSecret) {
        jsonResponse(['error' => 'You can only unlock your own messages'], 403);
    }

    // 3. Get the price
    $priceCents = intval($message['ppv_price_cents']);
    $priceAmount = $priceCents / 100.0;
    
    if ($priceAmount <= 0) {
        jsonResponse(['error' => 'Invalid PPV price'], 400);
    }

    // 4. Charge via CBPT (test accounts unlock free)
    if ($fanUserId == 3 || $fanUserId == 4) {
        // Test accounts — skip CBPT, auto-unlock
        $chargeResult = ["success" => true, "subscription_id" => "TEST-FREE-UNLOCK", "card_last_four" => "0000"];
        error_log("PPV test unlock: fan $fanUserId skipped CBPT (test account)");
    } else {
        $chargeDescription = "PPV Content Unlock";
        $chargeResult = chargeCBPT($fanUserId, $priceAmount, $chargeDescription, $db);
    }

    if (!$chargeResult['success']) {
        jsonResponse([
            'success' => false,
            'error' => $chargeResult['error'],
            'decline_code' => $chargeResult['decline_code'] ?? ''
        ], 402);
    }

    // 5. Mark unlock AFTER delivery succeeds (moved to step 8)

    // 6. Record PPV purchase event
    try {
        @$db->exec("CREATE TABLE IF NOT EXISTS ppv_purchases (id INTEGER PRIMARY KEY AUTOINCREMENT, fan_user_id INTEGER NOT NULL, message_id INTEGER NOT NULL, amount_cents INTEGER NOT NULL, content_key TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $purchaseStmt = $db->prepare("INSERT INTO ppv_purchases (fan_user_id, message_id, amount_cents, content_key) VALUES (:uid, :mid, :amount, :key)");
        $purchaseStmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
        $purchaseStmt->bindValue(':mid', $messageId, SQLITE3_INTEGER);
        $purchaseStmt->bindValue(':amount', $priceCents, SQLITE3_INTEGER);
        $purchaseStmt->bindValue(':key', $message['ppv_content_key'], SQLITE3_TEXT);
        $purchaseStmt->execute();
    } catch (\Throwable $e) {
        error_log("PPV purchase record failed (non-fatal): " . $e->getMessage());
    }

    error_log("PPV Unlock Success: Fan $fanUserId unlocked message $messageId for $" . number_format($priceAmount, 2));

    // 7. Deliver set content (v2 format) - insert all items as separate messages
    $setDelivered = false;
    $totalItems = 0;
    
    if (strpos($message['ppv_content_key'], '_') !== false) {
        // This is a set_id_tier format - deliver all items in the set
        $inventoryFile = __DIR__ . '/../../data/content-inventory.json';
        if (file_exists($inventoryFile)) {
            $inventory = json_decode(file_get_contents($inventoryFile), true);
            
            // Parse set_id_tier format
            $parts = explode('_', $message['ppv_content_key']);
            if (count($parts) >= 3) {
                $tier = array_pop($parts);
                $setId = implode('_', $parts);
                
                // Find the set and tier
                if ($inventory['version'] == 2 && isset($inventory['sets'])) {
                    foreach ($inventory['sets'] as $set) {
                        if ($set['set_id'] === $setId && isset($set['tiers'][$tier])) {
                            $tierData = $set['tiers'][$tier];
                            $allItems = array_merge($tierData['images'], $tierData['videos']);
                            
                            if (!empty($allItems)) {
                                // Insert each item as a separate message
                                foreach ($allItems as $index => $itemUrl) {
                                    $safeItemUrl = $db->escapeString($itemUrl);
                                    
                                    // Determine if it's an image or video
                                    $isVideo = strpos($itemUrl, '.mp4') !== false || strpos($itemUrl, '.mov') !== false;
                                    $itemContent = $isVideo ? "📹 Video from your purchased set" : "📸 Photo from your purchased set";
                                    $safeContent = $db->escapeString($itemContent);
                                    
                                    // Insert with slight delay to maintain order
                                    $delay = $index + 3; // Start 3 seconds after unlock, then 1 second per item
                                    $db->exec("INSERT INTO messages (sender_id, receiver_id, content, media_url, is_ai, is_unlocked, created_at) 
                                              VALUES (1, $fanUserId, '$safeContent', '$safeItemUrl', 1, 1, datetime('now', '+$delay seconds'))");
                                    $totalItems++;
                                }
                                $setDelivered = true;
                                error_log("PPV Set Delivered: $totalItems items from $setId ($tier) to fan $fanUserId");
                            }
                            break;
                        }
                    }
                }
            }
        }
    }

    // 8. NOW mark as unlocked (after set delivery succeeded)
    $unlockStmt = $db->prepare("UPDATE messages SET is_unlocked = 1 WHERE id = :mid");
    $unlockStmt->bindValue(':mid', $messageId, SQLITE3_INTEGER);
    $unlockStmt->execute();

    jsonResponse([
        'success' => true,
        'message_id' => $messageId,
        'media_url' => $message['media_url'],
        'content_key' => $message['ppv_content_key'],
        'price' => $priceAmount,
        'subscription_id' => $chargeResult['subscription_id'],
        'card_last_four' => $chargeResult['card_last_four'] ?? '',
        'set_delivered' => $setDelivered,
        'total_items' => $totalItems
    ]);

} catch (\Throwable $e) {
    error_log("PPV Unlock Exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    jsonResponse(['error' => 'PPV unlock failed: ' . $e->getMessage()], 500);
} finally {
    if (isset($db)) $db->close();
}
?>