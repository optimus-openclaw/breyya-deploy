<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';
setCorsHeaders();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireCreator(); // will exit with JSON error if not authorized
$db = getDB();

// Validate inputs
if (!isset($_FILES['media'])) {
    jsonResponse(['error' => 'Media file is required'], 400);
}

$caption = trim($_POST['caption'] ?? '');
if ($caption === '') {
    jsonResponse(['error' => 'Caption is required'], 400);
}

$like_count = isset($_POST['like_count']) ? intval($_POST['like_count']) : 0;
$is_free = isset($_POST['is_free']) ? (int)$_POST['is_free'] : 1;
$created_at = isset($_POST['created_at']) && trim($_POST['created_at']) !== '' ? $_POST['created_at'] : date('Y-m-d H:i:s');

// Ensure uploads dir exists
$uploadsDir = __DIR__ . '/../../data/uploads';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

$f = $_FILES['media'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'Upload failed', 'code' => $f['error']], 400);
}

$originalName = basename($f['name']);
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedImage = ['jpg','jpeg','png','gif','webp'];
$allowedVideo = ['mp4','mov','webm','m4v'];

$type = 'photo';
if (in_array($ext, $allowedVideo)) $type = 'video';
elseif (!in_array($ext, $allowedImage)) {
    jsonResponse(['error' => 'Unsupported media type'], 400);
}

// Generate safe filename
$filename = uniqid('post_', true) . '.' . $ext;
$destPath = $uploadsDir . '/' . $filename;

if (!move_uploaded_file($f['tmp_name'], $destPath)) {
    jsonResponse(['error' => 'Failed to save uploaded file'], 500);
}

// If image, try to strip EXIF using GD
$mediaUrl = '/data/uploads/' . $filename;
if ($type === 'photo') {
    if (function_exists('gd_info')) {
        try {
            $img = null;
            if (in_array($ext, ['jpg','jpeg'])) $img = imagecreatefromjpeg($destPath);
            if ($ext === 'png') $img = imagecreatefrompng($destPath);
            if ($ext === 'gif') $img = imagecreatefromgif($destPath);
            if ($ext === 'webp' && function_exists('imagecreatefromwebp')) $img = imagecreatefromwebp($destPath);

            if ($img) {
                // Re-save to strip metadata
                if (in_array($ext, ['jpg','jpeg'])) imagejpeg($img, $destPath, 90);
                if ($ext === 'png') imagepng($img, $destPath);
                if ($ext === 'gif') imagegif($img, $destPath);
                if ($ext === 'webp' && function_exists('imagewebp')) imagewebp($img, $destPath);
                imagedestroy($img);
            }
        } catch (Exception $e) {
            // ignore, not critical
        }
    }
}

// Insert into DB
$stmt = $db->prepare("INSERT INTO posts (creator_id, type, caption, media_url, is_free, like_count, created_at) VALUES (:creator_id, :type, :caption, :media_url, :is_free, :like_count, :created_at)");
$stmt->bindValue(':creator_id', $user['id'], SQLITE3_INTEGER);
$stmt->bindValue(':type', $type, SQLITE3_TEXT);
$stmt->bindValue(':caption', $caption, SQLITE3_TEXT);
$stmt->bindValue(':media_url', $mediaUrl, SQLITE3_TEXT);
$stmt->bindValue(':is_free', $is_free, SQLITE3_INTEGER);
$stmt->bindValue(':like_count', $like_count, SQLITE3_INTEGER);
$stmt->bindValue(':created_at', $created_at, SQLITE3_TEXT);
$res = $stmt->execute();

if (!$res) {
    jsonResponse(['error' => 'Database insert failed'], 500);
}

$postId = $db->lastInsertRowID();

// Fetch creator info
$creatorStmt = $db->prepare('SELECT id, display_name, avatar_url FROM users WHERE id = :id');
$creatorStmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
$creatorRes = $creatorStmt->execute();
$creator = $creatorRes->fetchArray(SQLITE3_ASSOC);

$db->close();

$post = [
    'id' => $postId,
    'creator' => [
        'id' => $creator['id'],
        'display_name' => $creator['display_name'],
        'avatar_url' => $creator['avatar_url']
    ],
    'type' => $type,
    'caption' => $caption,
    'media_url' => $mediaUrl,
    'is_free' => $is_free,
    'like_count' => $like_count,
    'created_at' => $created_at
];

jsonResponse(['post' => $post], 201);
