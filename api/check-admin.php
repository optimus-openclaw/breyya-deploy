<?php
if (($_GET['secret'] ?? '') !== 'check-admin-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/auth.php';
$db = getDb();
$stmt = $db->query("SELECT id, email, role, display_name FROM users WHERE role IN ('creator','admin')");
$users = [];
while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) { $users[] = $row; }
header('Content-Type: application/json');
echo json_encode($users);
unlink(__FILE__);
