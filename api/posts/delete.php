<?php
/**
 * POST /api/posts/delete.php
 * Delete a post — admin/creator only
 */
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireAuth();
if ($user['role'] !== 'creator' && $user['role'] !== 'admin') {
    jsonResponse(['error' => 'Admin access required'], 403);
}

$body = getRequestBody();
$postId = intval($body['post_id'] ?? 0);

if (!$postId) {
    jsonResponse(['error' => 'post_id required'], 400);
}

$db = getDB();

// Get the post to find media file
$stmt = $db->prepare('SELECT media_url FROM posts WHERE id = :id');
$stmt->bindValue(':id', $postId, SQLITE3_INTEGER);
$result = $stmt->execute();
$post = $result->fetchArray(SQLITE3_ASSOC);

if (!$post) {
    $db->close();
    jsonResponse(['error' => 'Post not found'], 404);
}

// Delete from database
$stmt = $db->prepare('DELETE FROM posts WHERE id = :id');
$stmt->bindValue(':id', $postId, SQLITE3_INTEGER);
$stmt->execute();

// Delete associated likes
$stmt = $db->prepare('DELETE FROM post_likes WHERE post_id = :id');
$stmt->bindValue(':id', $postId, SQLITE3_INTEGER);
$stmt->execute();

$db->close();

// Try to delete the media file from disk
if ($post['media_url']) {
    $filePath = __DIR__ . '/../../' . ltrim($post['media_url'], '/');
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

jsonResponse(['ok' => true, 'deleted_post_id' => $postId]);
