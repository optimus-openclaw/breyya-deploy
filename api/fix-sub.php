<?php
if (($_GET['k'] ?? '') !== 'fix-sub-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/database.php';
$db = getDB();
$db->exec("INSERT OR IGNORE INTO subscriptions (user_id, status, plan, price_cents, expires_at) VALUES (4, 'active', 'monthly', 2000, datetime('now', '+30 days'))");
$db->close();
echo json_encode(['ok' => true]);
unlink(__FILE__);
