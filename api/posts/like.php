<?php
/**
 * POST /api/posts/like.php
 * Toggle like on a post. Returns new like state + count.
 */
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';
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
$existing = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

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
    $stmt = $db->prepare('INSERT OR IGNORE INTO post_likes (post_id, user_id) VALUES (:pid, :uid)');
    $stmt->bindValue(':pid', $postId, SQLITE3_INTEGER);
    $stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
    $stmt->execute();

    $db->exec("UPDATE posts SET like_count = like_count + 1 WHERE id = $postId");
    $liked = true;
}

// Get new count
$newCount = $db->querySingle("SELECT like_count FROM posts WHERE id = $postId");
$db->close();

jsonResponse(['ok' => true, 'liked' => $liked, 'like_count' => $newCount]);
