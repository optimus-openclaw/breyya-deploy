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

$db = getDB();
// Delete all queue entries so they get re-queued with new (instant) delay
$db->exec("DELETE FROM chat_queue");
// Also delete the bad message 1 (sender=receiver=1, that was the admin talking to himself)
$db->exec("DELETE FROM messages WHERE id = 1");
$db->close();

echo json_encode(['ok' => true, 'message' => 'Queue and bad message cleared. Run process.php to re-queue with instant delays.']);
