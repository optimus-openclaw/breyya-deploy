<?php
if (($_GET['secret'] ?? '') !== 'fix-admin-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/auth.php';
$db = getDb();
$newPassword = 'IWasBornIn2026!';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $db->prepare("UPDATE users SET email = :email, password_hash = :hash WHERE id = 1");
$stmt->bindValue(':email', 'BreyyaX@gmail.com', SQLITE3_TEXT);
$stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
$result = $stmt->execute();
// Verify
$check = $db->querySingle("SELECT email, password_hash FROM users WHERE id = 1", true);
$verified = password_verify($newPassword, $check['password_hash']);
echo json_encode(['ok' => true, 'email' => $check['email'], 'verified' => $verified]);
unlink(__FILE__);
