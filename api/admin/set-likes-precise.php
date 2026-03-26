<?php
require_once __DIR__ . '/../lib/database.php';
header('Content-Type: application/json');
if (($_GET['secret'] ?? '') !== 'breyya-chat-cron-2026') { http_response_code(403); die('{}'); }

$db = getDB();

$likes = [18=>42, 17=>35, 16=>28, 14=>24, 13=>22, 12=>19, 11=>17, 10=>15, 9=>14, 7=>12, 5=>11, 4=>8, 3=>6, 2=>4, 1=>2];
foreach ($likes as $postId => $count) {
    $db->exec("UPDATE posts SET like_count = $count WHERE id = $postId");
}
$db->close();
echo json_encode(['ok' => true, 'total' => array_sum($likes), 'distribution' => $likes]);
