<?php
require_once __DIR__ . '/lib/database.php';
header('Content-Type: application/json');
if (($_GET['secret'] ?? '') !== 'breyya-chat-cron-2026') { http_response_code(403); die('{}'); }
$db = getDB();
// Remove [auto] from all captions and update time
$db->exec("UPDATE posts SET caption = REPLACE(caption, ' [auto]', '') WHERE caption LIKE '%[auto]%'");
$db->exec("UPDATE posts SET created_at = datetime('now') WHERE id = 14");
$db->close();
echo json_encode(['ok' => true, 'message' => 'Fixed captions and post time']);
