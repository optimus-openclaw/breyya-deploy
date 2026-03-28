<?php
/**
 * POST /api/posts/upload.php
 * Upload media file for a new post — creator only
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireCreator();

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'No file uploaded or upload error'], 400);
}

$file = $_FILES['file'];
$type = $_POST['type'] ?? 'photo';

// Validate file type
$allowedImage = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedVideo = ['video/mp4', 'video/quicktime', 'video/webm'];
$allowed = $type === 'video' ? array_merge($allowedImage, $allowedVideo) : $allowedImage;

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowed)) {
    jsonResponse(['error' => 'Invalid file type: ' . $mimeType], 400);
}

// Generate filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$filename = 'post_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

// Save to uploads directory
$uploadDir = __DIR__ . '/../../data/uploads/posts/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destPath = $uploadDir . $filename;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(['error' => 'Failed to save file'], 500);
}

// Return the public URL
$publicUrl = '/data/uploads/posts/' . $filename;

jsonResponse([
    'ok' => true,
    'url' => $publicUrl,
    'filename' => $filename,
    'type' => $type,
    'size' => $file['size']
]);
