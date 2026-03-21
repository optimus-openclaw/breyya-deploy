<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/database.php';

header('Content-Type: application/json');

$secret = $_GET['secret'] ?? '';
if ($secret !== CHAT_CRON_SECRET) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$fanId = intval($_GET['fan_id'] ?? 0);
if (!$fanId) {
    echo json_encode(['error' => 'fan_id required']);
    exit;
}

$db = getDB();
$CREATOR_ID = 1;

// Delete all messages between this fan and creator
$db->exec("DELETE FROM messages WHERE (sender_id = $fanId AND receiver_id = $CREATOR_ID) OR (sender_id = $CREATOR_ID AND receiver_id = $fanId)");

// Delete queue entries for this fan
$db->exec("DELETE FROM chat_queue WHERE fan_user_id = $fanId");

// Reset daily engagement
$db->exec("DELETE FROM daily_engagement WHERE fan_user_id = $fanId");

$db->close();

echo json_encode(['ok' => true, 'message' => "Chat history cleared for fan $fanId"]);
