<?php
if (($_GET['k'] ?? '') !== 'fix-q-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/database.php';
$db = getDB();
$db->exec("UPDATE chat_queue SET scheduled_at = datetime('now') WHERE fan_user_id = 4 AND status = 'scheduled'");
$db->close();
echo json_encode(['ok' => true]);
unlink(__FILE__);
