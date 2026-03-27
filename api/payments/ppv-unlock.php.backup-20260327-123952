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

    // 5. Success! Unlock the message
    $unlockStmt = $db->prepare("UPDATE messages SET is_unlocked = 1 WHERE id = :mid");
    $unlockStmt->bindValue(':mid', $messageId, SQLITE3_INTEGER);
    $unlockStmt->execute();

    // 6. Record PPV purchase event
    $db->exec("CREATE TABLE IF NOT EXISTS ppv_purchases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        fan_user_id INTEGER NOT NULL,
        message_id INTEGER NOT NULL,
        amount_cents INTEGER NOT NULL,
        content_key TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $purchaseStmt = $db->prepare("INSERT INTO ppv_purchases (fan_user_id, message_id, amount_cents, content_key) VALUES (:uid, :mid, :amount, :key)");
    $purchaseStmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
    $purchaseStmt->bindValue(':mid', $messageId, SQLITE3_INTEGER);
    $purchaseStmt->bindValue(':amount', $priceCents, SQLITE3_INTEGER);
    $purchaseStmt->bindValue(':key', $message['ppv_content_key'], SQLITE3_TEXT);
    $purchaseStmt->execute();

    error_log("PPV Unlock Success: Fan $fanUserId unlocked message $messageId for $" . number_format($priceAmount, 2));

    jsonResponse([
        'success' => true,
        'message_id' => $messageId,
        'media_url' => $message['media_url'],
        'content_key' => $message['ppv_content_key'],
        'price' => $priceAmount,
        'subscription_id' => $chargeResult['subscription_id'],
        'card_last_four' => $chargeResult['card_last_four'] ?? ''
    ]);

} catch (\Throwable $e) {
    error_log("PPV Unlock Exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    jsonResponse(['error' => 'PPV unlock failed: ' . $e->getMessage()], 500);
} finally {
    if (isset($db)) $db->close();
}
?>