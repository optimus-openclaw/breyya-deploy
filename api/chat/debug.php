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

// All messages
$msgs = [];
$r = $db->query("SELECT id, sender_id, receiver_id, content, is_ai, created_at FROM messages ORDER BY id ASC LIMIT 50");
while ($row = $r->fetchArray(SQLITE3_ASSOC)) { $msgs[] = $row; }

// All queue items
$queue = [];
$r2 = $db->query("SELECT * FROM chat_queue ORDER BY id ASC LIMIT 50");
while ($row = $r2->fetchArray(SQLITE3_ASSOC)) { $queue[] = $row; }

// Server time
$serverTime = date('Y-m-d H:i:s');

$db->close();

// Config info for debugging
$configInfo = [
    'model' => defined('AI_MODEL') ? AI_MODEL : (defined('OPENAI_MODEL') ? OPENAI_MODEL : 'NOT SET'),
    'key_prefix' => defined('AI_API_KEY') ? substr(AI_API_KEY, 0, 12) . '...' : (defined('OPENAI_API_KEY') ? substr(OPENAI_API_KEY, 0, 12) . '...' : 'NOT SET'),
    'secrets_exists' => file_exists(__DIR__ . '/../../.secrets.php'),
];

echo json_encode([
    'server_time' => $serverTime,
    'config' => $configInfo,
    'messages' => $msgs,
    'queue' => $queue,
    'creator_id' => CREATOR_USER_ID,
], JSON_PRETTY_PRINT);

// cache-bust: 1773300234
