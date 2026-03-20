<?php
/**
 * POST /api/posts/create
 * Create a new post — creator/admin only
 * Accepts both JSON (with media_url) and multipart/form-data (with file upload)
 */

date_default_timezone_set('America/Los_Angeles');
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireCreator();

// Handle both JSON and multipart form data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'multipart/form-data') !== false) {
    $caption = trim($_POST['caption'] ?? '');
    $type = $_POST['type'] ?? 'photo';
    $isFree = intval($_POST['is_free'] ?? 1);
    $likeCount = intval($_POST['like_count'] ?? 0);
    $createdAt = $_POST['created_at'] ?? null;
    $mediaUrl = '';

    // Handle file upload
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['media'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/quicktime'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            jsonResponse(['error' => 'Invalid file type'], 400);
        }

        if ($file['size'] > 50 * 1024 * 1024) {
            jsonResponse(['error' => 'File too large (max 50MB)'], 400);
        }

        // Determine type from mime
        if (strpos($mimeType, 'video') !== false) {
            $type = 'video';
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'post_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        $uploadDir = __DIR__ . '/../../data/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $destPath = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            jsonResponse(['error' => 'Failed to save file'], 500);
        }

        // Strip EXIF from images
        if (in_array($mimeType, ['image/jpeg']) && function_exists('imagecreatefromjpeg')) {
            $img = @imagecreatefromjpeg($destPath);
            if ($img) { imagejpeg($img, $destPath, 90); imagedestroy($img); }
        }

        $mediaUrl = '/data/uploads/' . $filename;
    }
} else {
    $body = getRequestBody();
    $caption = trim($body['caption'] ?? '');
    $type = $body['type'] ?? 'photo';
    $mediaUrl = trim($body['media_url'] ?? '');
    $isFree = intval($body['is_free'] ?? 0);
    $likeCount = intval($body['like_count'] ?? 0);
    $createdAt = $body['created_at'] ?? null;
}

if (!in_array($type, ['photo', 'video', 'text'])) {
    jsonResponse(['error' => 'Invalid post type'], 400);
}

if ($type !== 'text' && !$mediaUrl) {
    jsonResponse(['error' => 'Media URL required for photo/video posts'], 400);
}

$db = getDB();
$stmt = $db->prepare('INSERT INTO posts (creator_id, type, caption, media_url, is_free, like_count, created_at) VALUES (:cid, :type, :caption, :media, :free, :likes, :created)');
$stmt->bindValue(':cid', $user['id'], SQLITE3_INTEGER);
$stmt->bindValue(':type', $type, SQLITE3_TEXT);
$stmt->bindValue(':caption', $caption, SQLITE3_TEXT);
$stmt->bindValue(':media', $mediaUrl, SQLITE3_TEXT);
$stmt->bindValue(':free', $isFree, SQLITE3_INTEGER);
$stmt->bindValue(':likes', $likeCount, SQLITE3_INTEGER);
$stmt->bindValue(':created', $createdAt ?: date('Y-m-d H:i:s'), SQLITE3_TEXT);
$stmt->execute();

$postId = $db->lastInsertRowID();
$db->close();

jsonResponse([
    'ok' => true,
    'post' => [
        'id' => $postId,
        'type' => $type,
        'caption' => $caption,
        'media_url' => $mediaUrl,
        'is_free' => $isFree,
        'like_count' => $likeCount,
    ]
]);
