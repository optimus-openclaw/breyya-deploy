<?php
if (($_GET['secret'] ?? '') !== 'fix-admin-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/auth.php';
$db = getDb();
$hash = password_hash('IWasBornIn2026!', PASSWORD_DEFAULT);
$stmt = $db->prepare("UPDATE users SET email = :email, password_hash = :hash WHERE id = 1");
$stmt->bindValue(':email', 'breyyax@gmail.com', SQLITE3_TEXT);
$stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
$stmt->execute();
$check = $db->querySingle("SELECT email, password_hash FROM users WHERE id = 1", true);
echo json_encode(['ok' => true, 'email' => $check['email'], 'verified' => password_verify('IWasBornIn2026!', $check['password_hash'])]);
unlink(__FILE__);
