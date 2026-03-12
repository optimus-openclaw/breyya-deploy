<?php
/**
 * POST /api/posts/create
 * Create a new post — creator only
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireCreator();
$body = getRequestBody();

$type = $body['type'] ?? 'photo';
$caption = trim($body['caption'] ?? '');
$mediaUrl = trim($body['media_url'] ?? '');
$mediaThumbnail = trim($body['media_thumbnail'] ?? '');
$isFree = intval($body['is_free'] ?? 0);
$scheduledAt = $body['scheduled_at'] ?? null;

if (!in_array($type, ['photo', 'video', 'text'])) {
    jsonResponse(['error' => 'Invalid post type'], 400);
}

if ($type !== 'text' && !$mediaUrl) {
    jsonResponse(['error' => 'Media URL required for photo/video posts'], 400);
}

$db = getDB();
$stmt = $db->prepare('INSERT INTO posts (creator_id, type, caption, media_url, media_thumbnail, is_free, scheduled_at) VALUES (:cid, :type, :caption, :media, :thumb, :free, :sched)');
$stmt->bindValue(':cid', $user['id'], SQLITE3_INTEGER);
$stmt->bindValue(':type', $type, SQLITE3_TEXT);
$stmt->bindValue(':caption', $caption, SQLITE3_TEXT);
$stmt->bindValue(':media', $mediaUrl, SQLITE3_TEXT);
$stmt->bindValue(':thumb', $mediaThumbnail, SQLITE3_TEXT);
$stmt->bindValue(':free', $isFree, SQLITE3_INTEGER);
$stmt->bindValue(':sched', $scheduledAt, SQLITE3_TEXT);
$stmt->execute();

$postId = $db->lastInsertRowID();
$db->close();

jsonResponse([
    'ok' => true,
    'post' => [
        'id' => $postId,
        'type' => $type,
        'caption' => $caption,
        'is_free' => $isFree,
    ],
], 201);
