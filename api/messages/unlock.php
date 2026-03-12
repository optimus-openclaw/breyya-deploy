<?php
/**
 * POST /api/messages/unlock
 * Pay to unlock a PPV message
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireAuth();
$body = getRequestBody();
$messageId = intval($body['message_id'] ?? 0);

if (!$messageId) {
    jsonResponse(['error' => 'message_id required'], 400);
}

$db = getDB();

// Get the message
$stmt = $db->prepare('SELECT * FROM messages WHERE id = :mid AND receiver_id = :uid AND is_ppv = 1');
$stmt->bindValue(':mid', $messageId, SQLITE3_INTEGER);
$stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$message = $result->fetchArray(SQLITE3_ASSOC);

if (!$message) {
    $db->close();
    jsonResponse(['error' => 'PPV message not found'], 404);
}

if ($message['is_unlocked']) {
    $db->close();
    jsonResponse(['error' => 'Already unlocked'], 400);
}

// TODO: Charge via CCBill here
// For now, mark as unlocked and record transaction

// Mark message as unlocked
$stmt = $db->prepare('UPDATE messages SET is_unlocked = 1 WHERE id = :mid');
$stmt->bindValue(':mid', $messageId, SQLITE3_INTEGER);
$stmt->execute();

// Record transaction
$stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount_cents, description, reference_id, status) VALUES (:uid, 'ppv', :amount, :desc, :ref, 'completed')");
$stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
$stmt->bindValue(':amount', $message['ppv_price_cents'], SQLITE3_INTEGER);
$stmt->bindValue(':desc', 'PPV message unlock', SQLITE3_TEXT);
$stmt->bindValue(':ref', "msg_$messageId", SQLITE3_TEXT);
$stmt->execute();

$db->close();

jsonResponse([
    'ok' => true,
    'message_id' => $messageId,
    'amount_cents' => $message['ppv_price_cents'],
    'media_url' => $message['media_url'],
]);
