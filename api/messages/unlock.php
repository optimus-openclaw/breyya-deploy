<?php
/**
 * POST /api/messages/unlock
 * Pay to unlock a PPV message — routes to ppv-unlock.php for full set delivery
 */

require_once __DIR__ . "/../lib/auth.php";
setCorsHeaders();

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit(0); }
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(["error" => "Method not allowed"], 405);
}

$user = requireAuth();
$body = getRequestBody();
$messageId = intval($body["message_id"] ?? 0);

if (!$messageId) {
    jsonResponse(["error" => "message_id required"], 400);
}

$db = getDB();

// Get the message
$stmt = $db->prepare("SELECT * FROM messages WHERE id = :mid AND receiver_id = :uid AND is_ppv = 1");
$stmt->bindValue(":mid", $messageId, SQLITE3_INTEGER);
$stmt->bindValue(":uid", $user["id"], SQLITE3_INTEGER);
$result = $stmt->execute();
$message = $result->fetchArray(SQLITE3_ASSOC);

if (!$message) {
    $db->close();
    jsonResponse(["error" => "PPV message not found"], 404);
}

if ($message["is_unlocked"]) {
    $db->close();
    jsonResponse(["ok" => true, "already_unlocked" => true, "media_url" => $message["media_url"]]);
}

$fanUserId = $user["id"];
$priceCents = intval($message["ppv_price_cents"]);
$priceAmount = $priceCents / 100.0;

// --- CHARGE ---
// Test accounts skip CBPT
if ($fanUserId == 3 || $fanUserId == 4) {
    $charged = true;
    error_log("PPV test unlock: fan $fanUserId skipped CBPT (test account)");
} else {
    require_once __DIR__ . "/../lib/cbpt.php";
    $chargeResult = chargeCBPT($fanUserId, $priceAmount, "PPV Content Unlock", $db);
    $charged = !empty($chargeResult["success"]);
    if (!$charged) {
        $db->close();
        jsonResponse(["error" => $chargeResult["error"] ?? "Payment failed"], 402);
    }
}

// --- DELIVER SET (burst all images as individual messages) ---
$totalItems = 0;
$contentKey = $message["ppv_content_key"] ?? "";

if ($contentKey && strpos($contentKey, "_") !== false) {
    $inventoryFile = __DIR__ . "/../../data/content-inventory.json";
    if (file_exists($inventoryFile)) {
        $inventory = json_decode(file_get_contents($inventoryFile), true);
        
        if ($inventory && ($inventory["version"] ?? 0) == 2 && !empty($inventory["sets"])) {
            $parts = explode("_", $contentKey);
            $tier = array_pop($parts);
            $setId = implode("_", $parts);
            
            foreach ($inventory["sets"] as $set) {
                if ($set["set_id"] === $setId && isset($set["tiers"][$tier])) {
                    $files = $set["tiers"][$tier]["files"] ?? [];
                    
                    foreach ($files as $index => $file) {
                        $url = $db->escapeString($file["url"]);
                        $isVideo = ($file["type"] ?? "") === "video";
                        $delay = $index + 2; // stagger 1 per second starting at +2s
                        $msgType = $isVideo ? "video" : "image";
                        
                        $db->exec("INSERT INTO messages (sender_id, receiver_id, content, media_url, message_type, is_ai, is_unlocked, created_at) 
                                  VALUES (1, $fanUserId, , , , 1, 1, datetime(now, + seconds))");
                        $totalItems++;
                    }
                    
                    error_log("PPV Set Delivered: $totalItems items from $setId ($tier) to fan $fanUserId");
                    break;
                }
            }
        }
    }
}

// --- RECORD PURCHASE ---
try {
    @$db->exec("CREATE TABLE IF NOT EXISTS ppv_purchases (id INTEGER PRIMARY KEY AUTOINCREMENT, fan_user_id INTEGER NOT NULL, message_id INTEGER NOT NULL, amount_cents INTEGER NOT NULL, content_key TEXT, items_delivered INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $pStmt = $db->prepare("INSERT INTO ppv_purchases (fan_user_id, message_id, amount_cents, content_key, items_delivered) VALUES (:uid, :mid, :amt, :key, :items)");
    $pStmt->bindValue(":uid", $fanUserId, SQLITE3_INTEGER);
    $pStmt->bindValue(":mid", $messageId, SQLITE3_INTEGER);
    $pStmt->bindValue(":amt", $priceCents, SQLITE3_INTEGER);
    $pStmt->bindValue(":key", $contentKey, SQLITE3_TEXT);
    $pStmt->bindValue(":items", $totalItems, SQLITE3_INTEGER);
    $pStmt->execute();
} catch (\Throwable $e) {
    error_log("PPV purchase record failed (non-fatal): " . $e->getMessage());
}

// --- MARK UNLOCKED ---
$db->exec("UPDATE messages SET is_unlocked = 1 WHERE id = $messageId");

$db->close();

jsonResponse([
    "ok" => true,
    "message_id" => $messageId,
    "amount_cents" => $priceCents,
    "media_url" => $message["media_url"],
    "set_delivered" => $totalItems > 0,
    "total_items" => $totalItems
]);
