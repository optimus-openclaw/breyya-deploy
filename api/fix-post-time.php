<?php
require_once __DIR__ . '/lib/database.php';
header('Content-Type: application/json');
if (($_GET['secret'] ?? '') !== 'breyya-chat-cron-2026') { http_response_code(403); die('{}'); }
$db = getDB();
$db->exec("UPDATE posts SET created_at = datetime('now') WHERE id = 14");
$db->close();
echo json_encode(['ok' => true, 'message' => 'Post 14 time updated to now']);
