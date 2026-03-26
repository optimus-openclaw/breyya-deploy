<?php
if (($_GET['k'] ?? '') !== 'fix-times2-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/database.php';
$db = getDB();

// Fix the two most recent uploads to correct PT times
$db->exec("UPDATE posts SET created_at = '2026-03-20 08:39:00' WHERE id = 7");
$db->exec("UPDATE posts SET created_at = '2026-03-20 08:35:00' WHERE id = 5");

// Fix post 4 - was uploaded around 4:13 AM PT during session
$db->exec("UPDATE posts SET created_at = '2026-03-20 04:13:00' WHERE id = 4");

// Posts 1,2,3 were uploaded during the 2-3 AM session - those are roughly correct
// But let's bump them slightly to be after midnight PT
$db->exec("UPDATE posts SET created_at = '2026-03-20 02:26:00' WHERE id = 1");
$db->exec("UPDATE posts SET created_at = '2026-03-20 02:28:00' WHERE id = 2");
$db->exec("UPDATE posts SET created_at = '2026-03-20 03:26:00' WHERE id = 3");

$r = $db->query("SELECT id, caption, created_at FROM posts ORDER BY created_at DESC");
$posts = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) { $posts[] = $row; }
$db->close();
echo json_encode(['ok' => true, 'posts' => $posts]);
unlink(__FILE__);
