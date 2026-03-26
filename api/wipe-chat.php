<?php
if (($_GET['k'] ?? '') !== 'wipe-chat-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/database.php';
$db = getDB();

$msgCount = $db->querySingle("SELECT COUNT(*) FROM messages");
$queueCount = $db->querySingle("SELECT COUNT(*) FROM chat_queue");

$db->exec("DELETE FROM messages");
$db->exec("DELETE FROM chat_queue");

$db->close();
echo json_encode(['ok' => true, 'deleted_messages' => $msgCount, 'deleted_queue' => $queueCount]);
unlink(__FILE__);
