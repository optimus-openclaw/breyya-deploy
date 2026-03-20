<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';
setCorsHeaders();

$db = getDB();
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
if ($limit <= 0 || $limit > 500) $limit = 100;

$stmt = $db->prepare("SELECT p.id, p.caption, p.media_url, p.like_count, p.created_at, p.type, u.display_name, u.avatar_url FROM posts p JOIN users u ON p.creator_id = u.id ORDER BY p.created_at DESC LIMIT :limit");
$stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
$res = $stmt->execute();

$posts = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $posts[] = [
        'id' => $row['id'],
        'caption' => $row['caption'],
        'media_url' => $row['media_url'],
        'like_count' => intval($row['like_count']),
        'created_at' => $row['created_at'],
        'type' => $row['type'],
        'creator' => [
            'display_name' => $row['display_name'],
            'avatar_url' => $row['avatar_url']
        ]
    ];
}

$db->close();

jsonResponse(['posts' => $posts]);
