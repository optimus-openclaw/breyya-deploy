<?php
require_once __DIR__ . '/lib/database.php';
header('Content-Type: application/json');
if (($_GET['secret'] ?? '') !== 'breyya-chat-cron-2026') { http_response_code(403); die('{}'); }
$db = getDB();
$action = $_GET['action'] ?? 'fix';
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $db->exec("DELETE FROM posts WHERE id = $id");
        $db->close();
        echo json_encode(['ok' => true, 'deleted' => $id]);
        exit;
    }
}
$db->exec("UPDATE posts SET caption = REPLACE(caption, ' [auto]', '') WHERE caption LIKE '%[auto]%'");
$db->close();
echo json_encode(['ok' => true]);
