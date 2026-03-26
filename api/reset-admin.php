<?php
if (($_GET['secret'] ?? '') !== 'reset-admin-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/auth.php';
$db = getDb();
$newHash = password_hash('IWasBornIn2026!', PASSWORD_DEFAULT);
$db->exec("UPDATE users SET email='BreyyaX@gmail.com', password_hash='" . $newHash . "' WHERE id=1");
echo json_encode(['ok' => true, 'message' => 'Admin password reset']);
unlink(__FILE__);
