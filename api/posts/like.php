<?php
/**
 * POST /api/posts/like
 * Toggle like on a post
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireAuth();
$body = getRequestBody();
$postId = intval($body['post_id'] ?? 0);

if (!$postId) {
    jsonResponse(['error' => 'post_id required'], 400);
}

$db = getDB();

// Check if already liked
$stmt = $db->prepare('SELECT id FROM post_likes WHERE post_id = :pid AND user_id = :uid');
$stmt->bindValue(':pid', $postId, SQLITE3_INTEGER);
$stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$existing = $result->fetchArray();

if ($existing) {
    // Unlike
    $stmt = $db->prepare('DELETE FROM post_likes WHERE post_id = :pid AND user_id = :uid');
    $stmt->bindValue(':pid', $postId, SQLITE3_INTEGER);
    $stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
    $stmt->execute();

    $db->exec("UPDATE posts SET like_count = MAX(0, like_count - 1) WHERE id = $postId");
    $liked = false;
} else {
    // Like
    $stmt = $db->prepare('INSERT INTO post_likes (post_id, user_id) VALUES (:pid, :uid)');
    $stmt->bindValue(':pid', $postId, SQLITE3_INTEGER);
    $stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
    $stmt->execute();

    $db->exec("UPDATE posts SET like_count = like_count + 1 WHERE id = $postId");
    $liked = true;
}

$newCount = $db->querySingle("SELECT like_count FROM posts WHERE id = $postId");
$db->close();

jsonResponse([
    'ok' => true,
    'liked' => $liked,
    'like_count' => $newCount,
]);
