<?php
/**
 * POST /api/messages/upload-media
 * Upload an image/video for chat messages
 * Returns the media URL to use in send.php
 */
require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireAuth();

if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'No file uploaded or upload error'], 400);
}

$file = $_FILES['media'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/quicktime'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    jsonResponse(['error' => 'Invalid file type. Allowed: JPEG, PNG, WebP, GIF, MP4'], 400);
}

// Max 20MB
if ($file['size'] > 20 * 1024 * 1024) {
    jsonResponse(['error' => 'File too large (max 20MB)'], 400);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$filename = 'chat_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);

$uploadDir = __DIR__ . '/../../data/uploads/chat/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destPath = $uploadDir . $filename;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(['error' => 'Failed to save file'], 500);
}

// Strip EXIF from images
if (in_array($mimeType, ['image/jpeg', 'image/png'])) {
    if ($mimeType === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        $img = @imagecreatefromjpeg($destPath);
        if ($img) { imagejpeg($img, $destPath, 90); imagedestroy($img); }
    } elseif ($mimeType === 'image/png' && function_exists('imagecreatefrompng')) {
        $img = @imagecreatefrompng($destPath);
        if ($img) { imagepng($img, $destPath, 6); imagedestroy($img); }
    }
}

$mediaUrl = '/data/uploads/chat/' . $filename;
jsonResponse(['ok' => true, 'media_url' => $mediaUrl, 'mime_type' => $mimeType]);
