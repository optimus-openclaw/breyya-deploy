<?php
/**
 * GET /api/chat/seed-ppv.php?secret=...
 * Seed demo PPV messages from Breyya (sender_id 1) to the test fan (id 9999)
 * Run once to populate demo data. Safe to re-run (deletes old PPV seeds first).
 */

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/database.php';

header('Content-Type: application/json');

$secret = $_GET['secret'] ?? '';
if ($secret !== CHAT_CRON_SECRET) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDB();

// Ensure ppv_preview_url column exists
try {
    $db->exec("ALTER TABLE messages ADD COLUMN ppv_preview_url TEXT DEFAULT ''");
} catch (Exception $e) {
    // Already exists
}

// Ensure test fan user exists
$checkStmt = $db->prepare("SELECT id FROM users WHERE id = 9999");
$checkResult = $checkStmt->execute();
$testFan = $checkResult->fetchArray(SQLITE3_ASSOC);

if (!$testFan) {
    $db->exec("INSERT INTO users (id, email, password_hash, display_name, role, is_active, created_at) VALUES (9999, 'testfan@breyya.com', '', 'Test Fan', 'fan', 1, datetime('now'))");
    $db->exec("INSERT INTO subscriptions (user_id, status, expires_at, created_at) VALUES (9999, 'active', datetime('now', '+1 year'), datetime('now'))");
}

// Remove any old seeded PPV demo messages (content-based match)
$db->exec("DELETE FROM messages WHERE sender_id = 1 AND receiver_id = 9999 AND is_ppv = 1 AND content LIKE '%just took these%'");
$db->exec("DELETE FROM messages WHERE sender_id = 1 AND receiver_id = 9999 AND is_ppv = 1 AND content LIKE '%something special%'");

// Seed PPV message 1
$stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, content, media_url, ppv_preview_url, is_ppv, ppv_price_cents, is_unlocked, is_read, created_at) VALUES (:sid, :rid, :content, :media, :preview, :ppv, :price, 0, 0, datetime('now', '-5 minutes'))");
$stmt->bindValue(':sid', 1, SQLITE3_INTEGER);
$stmt->bindValue(':rid', 9999, SQLITE3_INTEGER);
$stmt->bindValue(':content', 'omg I just took these and I can\'t stop looking at them 🫣🔥', SQLITE3_TEXT);
$stmt->bindValue(':media', '/images/gallery/IMG_1457.jpg', SQLITE3_TEXT);
$stmt->bindValue(':preview', '/images/gallery/IMG_1457.jpg', SQLITE3_TEXT);
$stmt->bindValue(':ppv', 1, SQLITE3_INTEGER);
$stmt->bindValue(':price', 1500, SQLITE3_INTEGER);
$stmt->execute();
$id1 = $db->lastInsertRowID();

// Seed PPV message 2
$stmt2 = $db->prepare("INSERT INTO messages (sender_id, receiver_id, content, media_url, ppv_preview_url, is_ppv, ppv_price_cents, is_unlocked, is_read, created_at) VALUES (:sid, :rid, :content, :media, :preview, :ppv, :price, 0, 0, datetime('now', '-2 minutes'))");
$stmt2->bindValue(':sid', 1, SQLITE3_INTEGER);
$stmt2->bindValue(':rid', 9999, SQLITE3_INTEGER);
$stmt2->bindValue(':content', 'made something special just for you 😏💋', SQLITE3_TEXT);
$stmt2->bindValue(':media', '/images/gallery/IMG_1457.jpg', SQLITE3_TEXT);
$stmt2->bindValue(':preview', '/images/gallery/IMG_1457.jpg', SQLITE3_TEXT);
$stmt2->bindValue(':ppv', 1, SQLITE3_INTEGER);
$stmt2->bindValue(':price', 2500, SQLITE3_INTEGER);
$stmt2->execute();
$id2 = $db->lastInsertRowID();

$db->close();

echo json_encode([
    'ok' => true,
    'message' => 'Seeded 2 demo PPV messages from Breyya to test fan',
    'message_ids' => [$id1, $id2],
]);
