<?php
if (($_GET['k'] ?? '') !== 'fix-2026-pw') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/database.php';
$db = getDB();
$hash = password_hash('IWasBornIn2026!', PASSWORD_DEFAULT);
$stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE email = 'breyyax@gmail.com'");
$stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
$stmt->execute();

// Verify it works
$stmt2 = $db->prepare("SELECT password_hash FROM users WHERE email = 'breyyax@gmail.com'");
$row = $stmt2->execute()->fetchArray(SQLITE3_ASSOC);
$ok = password_verify('IWasBornIn2026!', $row['password_hash']);
$db->close();
echo json_encode(['ok' => $ok, 'message' => $ok ? 'Password reset successful' : 'FAILED']);
// Self destruct
unlink(__FILE__);
