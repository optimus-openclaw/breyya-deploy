<?php
require_once __DIR__ . '/../lib/database.php';
header('Content-Type: application/json');
if (($_GET['secret'] ?? '') !== 'breyya-chat-cron-2026') { http_response_code(403); die('{}'); }

$fanId = intval($_GET['fan_id'] ?? 3);
$msg = $_GET['msg'] ?? '';
if (empty($msg)) { echo json_encode(['error' => 'msg required']); exit; }

$db = getDB();
$safe = SQLite3::escapeString($msg);
$db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES ($fanId, 1, '$safe', 0, 1, datetime('now'))");
$id = $db->lastInsertRowID();
$db->close();

echo json_encode(['ok' => true, 'message_id' => $id, 'content' => $msg]);
