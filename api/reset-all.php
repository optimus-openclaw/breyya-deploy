<?php
if (($_GET['k'] ?? '') !== 'reset-all-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/database.php';
$db = getDB();

// Reset all stats to zero
$db->exec("DELETE FROM messages");
$db->exec("DELETE FROM chat_queue");
$db->exec("DELETE FROM transactions");
$db->exec("DELETE FROM tips");
$db->exec("DELETE FROM post_likes");
$db->exec("DELETE FROM mass_messages");
$db->exec("DELETE FROM drip_schedule");

// Reset post like counts to zero
$db->exec("UPDATE posts SET like_count = 0");

// Reset fan profiles
$db->exec("DELETE FROM fan_profiles");

// Recreate fan profiles for existing test users with zero stats
$r = $db->query("SELECT id, display_name FROM users WHERE role = 'fan'");
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $uid = $row['id'];
    $name = $db->escapeString($row['display_name']);
    $offset = rand(0, 180);
    $db->exec("INSERT OR IGNORE INTO fan_profiles (user_id, display_name, attention_offset_minutes) VALUES ($uid, '$name', $offset)");
}

$db->close();
echo json_encode(['ok' => true, 'message' => 'All stats reset to zero']);
unlink(__FILE__);
