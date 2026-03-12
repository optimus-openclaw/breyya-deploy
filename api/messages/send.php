<?php
/**
 * POST /api/messages/send
 * Send a message (fan → creator, or creator → fan)
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

if (!$content && !$mediaUrl) {
    jsonResponse(['error' => 'Message content or media required'], 400);
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
}

if (!$receiverId) {
    jsonResponse(['error' => 'receiver_id required'], 400);
}

$db = getDB();

$stmt = $db->prepare('INSERT INTO messages (sender_id, receiver_id, content, media_url, is_ppv, ppv_price_cents, is_unlocked) VALUES (:sid, :rid, :content, :media, :ppv, :price, :unlocked)');
$stmt->bindValue(':sid', $user['id'], SQLITE3_INTEGER);
$stmt->bindValue(':rid', $receiverId, SQLITE3_INTEGER);
$stmt->bindValue(':content', $content, SQLITE3_TEXT);
$stmt->bindValue(':media', $mediaUrl, SQLITE3_TEXT);
$stmt->bindValue(':ppv', $isPpv, SQLITE3_INTEGER);
$stmt->bindValue(':price', $ppvPriceCents, SQLITE3_INTEGER);
$stmt->bindValue(':unlocked', $isPpv ? 0 : 1, SQLITE3_INTEGER); // Non-PPV messages are auto-unlocked

$stmt->execute();
$messageId = $db->lastInsertRowID();
$db->close();

jsonResponse([
    'ok' => true,
    'message' => [
        'id' => $messageId,
        'sender_id' => $user['id'],
        'receiver_id' => $receiverId,
        'content' => $content,
        'is_ppv' => $isPpv,
        'ppv_price_cents' => $ppvPriceCents,
    ],
], 201);
