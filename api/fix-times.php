<?php
if (($_GET['k'] ?? '') !== 'fix-times-2026') { http_response_code(403); die('no'); }
date_default_timezone_set('America/Los_Angeles');
require_once __DIR__ . '/lib/database.php';
$db = getDB();

// Update all post timestamps from UTC to Pacific (-7 hours for PDT)
$db->exec("UPDATE posts SET created_at = datetime(created_at, '-7 hours') WHERE created_at > '2026-03-20 04:00:00'");

$r = $db->query("SELECT id, caption, created_at FROM posts ORDER BY created_at DESC");
$posts = [];
while ($row = $r->fetchArray(SQLITE3_ASSOC)) { $posts[] = $row; }
$db->close();
echo json_encode(['ok' => true, 'posts' => $posts]);
unlink(__FILE__);
