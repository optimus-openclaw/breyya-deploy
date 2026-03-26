<?php
if (($_GET['k'] ?? '') !== 'fix-chat3-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/database.php';
$db = getDB();

// Count current AI messages per fan
$r = $db->query("SELECT sender_id, receiver_id, COUNT(*) as cnt FROM messages WHERE is_ai = 1 GROUP BY receiver_id");
$stats = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) { $stats[] = $row; }

// For BigTipper (user_id 3), keep only the last 2 AI responses, delete the rest
$db->exec("DELETE FROM messages WHERE is_ai = 1 AND receiver_id = 3 AND id NOT IN (
    SELECT id FROM messages WHERE is_ai = 1 AND receiver_id = 3 ORDER BY created_at DESC LIMIT 2
)");
$deleted3 = $db->changes();

// For test fan 9999, keep only last 2
$db->exec("DELETE FROM messages WHERE is_ai = 1 AND receiver_id = 9999 AND id NOT IN (
    SELECT id FROM messages WHERE is_ai = 1 AND receiver_id = 9999 ORDER BY created_at DESC LIMIT 2
)");
$deleted9 = $db->changes();

// Clear completed queue entries
$db->exec("DELETE FROM chat_queue WHERE status IN ('delivered', 'failed', '(combined)')");
$clearedQueue = $db->changes();

$db->close();
echo json_encode(['ok' => true, 'deleted_bigtipper' => $deleted3, 'deleted_testfan' => $deleted9, 'cleared_queue' => $clearedQueue, 'stats' => $stats]);
unlink(__FILE__);
