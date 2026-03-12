<?php
/**
 * POST /api/messages/send
 * Send a message (fan → creator, or creator → fan)
 * 
 * Creator can pass test_as_fan: true to simulate a fan message for testing.
 * This creates a test fan user (id 9999) and sends as them.
 * 
 * Creator/AI can send PPV messages with:
 *   is_ppv: 1, ppv_price_cents: 1500, media_url: "/images/...", ppv_preview_url: "/images/..."
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireAuth();
$body = getRequestBody();

$content = trim($body['content'] ?? '');
$receiverId = intval($body['receiver_id'] ?? 0);
$mediaUrl = trim($body['media_url'] ?? '');
$isPpv = intval($body['is_ppv'] ?? 0);
$ppvPriceCents = intval($body['ppv_price_cents'] ?? 0);
$ppvPreviewUrl = trim($body['ppv_preview_url'] ?? '');
$testAsFan = !empty($body['test_as_fan']);

if (!$content && !$mediaUrl) {
    jsonResponse(['error' => 'Message content or media required'], 400);
}

// Test as fan mode: only available to creator/admin
$senderId = $user['id'];
if ($testAsFan && ($user['role'] === 'creator' || $user['role'] === 'admin')) {
    // Ensure test fan user exists
    $db = getDB();
    $checkStmt = $db->prepare("SELECT id FROM users WHERE id = 9999");
    $checkResult = $checkStmt->execute();
    $testFan = $checkResult->fetchArray(SQLITE3_ASSOC);
    
    if (!$testFan) {
        $db->exec("INSERT INTO users (id, email, display_name, role, is_active, created_at) VALUES (9999, 'testfan@breyya.com', 'Test Fan', 'fan', 1, datetime('now'))");
        // Also create a subscription so the fan can message
        $db->exec("INSERT INTO subscriptions (user_id, status, expires_at, created_at) VALUES (9999, 'active', datetime('now', '+1 year'), datetime('now'))");
    }
    $db->close();
    
    $senderId = 9999;
    $receiverId = 1; // Send to creator
    $isPpv = 0;
    $ppvPriceCents = 0;
    $ppvPreviewUrl = '';
}

// Fans can only message creator (id 1)
if ($user['role'] === 'fan') {
    $receiverId = 1;

    // Check subscription
    if (!hasActiveSubscription($user['id'])) {
        jsonResponse(['error' => 'Active subscription required to send messages'], 403);
    }

    // Fans can't send PPV
    $isPpv = 0;
    $ppvPriceCents = 0;
    $ppvPreviewUrl = '';
}

if (!$receiverId) {
    jsonResponse(['error' => 'receiver_id required'], 400);
}

$db = getDB();

// Ensure ppv_preview_url column exists (safe migration)
try {
    $db->exec("ALTER TABLE messages ADD COLUMN ppv_preview_url TEXT DEFAULT ''");
} catch (Exception $e) {
    // Column already exists — ignore
}

$stmt = $db->prepare('INSERT INTO messages (sender_id, receiver_id, content, media_url, is_ppv, ppv_price_cents, ppv_preview_url, is_unlocked) VALUES (:sid, :rid, :content, :media, :ppv, :price, :preview, :unlocked)');
$stmt->bindValue(':sid', $senderId, SQLITE3_INTEGER);
$stmt->bindValue(':rid', $receiverId, SQLITE3_INTEGER);
$stmt->bindValue(':content', $content, SQLITE3_TEXT);
$stmt->bindValue(':media', $mediaUrl, SQLITE3_TEXT);
$stmt->bindValue(':ppv', $isPpv, SQLITE3_INTEGER);
$stmt->bindValue(':price', $ppvPriceCents, SQLITE3_INTEGER);
$stmt->bindValue(':preview', $ppvPreviewUrl, SQLITE3_TEXT);
$stmt->bindValue(':unlocked', $isPpv ? 0 : 1, SQLITE3_INTEGER);

$stmt->execute();
$messageId = $db->lastInsertRowID();
$db->close();

jsonResponse([
    'ok' => true,
    'message' => [
        'id' => $messageId,
        'sender_id' => $senderId,
        'receiver_id' => $receiverId,
        'content' => $content,
        'is_ppv' => $isPpv,
        'ppv_price_cents' => $ppvPriceCents,
        'ppv_preview_url' => $ppvPreviewUrl,
    ],
    'test_fan_mode' => $testAsFan,
], 201);
