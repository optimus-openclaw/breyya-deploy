<?php
require_once __DIR__ . '/lib/database.php';
header('Content-Type: application/json');
if (($_GET['secret'] ?? '') !== 'breyya-chat-cron-2026') { http_response_code(403); die('{}'); }

$db = getDB();

// Ensure PPV columns exist
$db->exec("ALTER TABLE posts ADD COLUMN is_ppv INTEGER DEFAULT 0");
$db->exec("ALTER TABLE posts ADD COLUMN ppv_price_cents INTEGER DEFAULT 0");

$caption = $_GET['caption'] ?? 'something special for you 🔥🔒';
$mediaUrl = $_GET['media_url'] ?? '';
$price = intval($_GET['price'] ?? 1500);
$type = $_GET['type'] ?? 'video';

$stmt = $db->prepare(
    "INSERT INTO posts (creator_id, type, caption, media_url, is_free, is_ppv, ppv_price_cents, like_count, created_at) 
     VALUES (1, :type, :caption, :media, 0, 1, :price, 0, datetime('now'))"
);
$stmt->bindValue(':type', $type, SQLITE3_TEXT);
$stmt->bindValue(':caption', $caption, SQLITE3_TEXT);
$stmt->bindValue(':media', $mediaUrl, SQLITE3_TEXT);
$stmt->bindValue(':price', $price, SQLITE3_INTEGER);
$stmt->execute();

$postId = $db->lastInsertRowID();
$db->close();

echo json_encode(['ok' => true, 'post_id' => $postId, 'caption' => $caption, 'price_cents' => $price, 'media' => $mediaUrl]);
