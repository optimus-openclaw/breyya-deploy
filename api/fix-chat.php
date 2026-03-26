<?php
if (($_GET['k'] ?? '') !== 'fix-chat-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/database.php';
$db = getDB();

// Delete all AI responses that contain "testing my memory"
$stmt = $db->prepare("DELETE FROM messages WHERE is_ai = 1 AND content LIKE '%testing my memory%'");
$stmt->execute();
$deleted = $db->changes();

// Also clean up the chat queue for these
$db->exec("DELETE FROM chat_queue WHERE ai_response LIKE '%testing my memory%'");

$db->close();
echo json_encode(['ok' => true, 'deleted_repetitive' => $deleted]);
unlink(__FILE__);
