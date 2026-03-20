<?php
if (($_GET['k'] ?? '') !== 'fix-avatar-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/database.php';
$db = getDB();
$db->exec("UPDATE users SET avatar_url = '/images/hero2.jpg' WHERE id = 1");
$db->close();
echo json_encode(['ok' => true]);
unlink(__FILE__);
