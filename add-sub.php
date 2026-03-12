<?php
// Add to deploy repo and run once
require_once __DIR__ . '/api/lib/database.php';
$db = getDB();
$db->exec("INSERT OR REPLACE INTO subscriptions (user_id, status, plan, price_cents, started_at, expires_at) VALUES (3, 'active', 'monthly', 2000, datetime('now'), datetime('now', '+1 year'))");
echo json_encode(['ok' => true, 'message' => 'Subscription added for user 3']);
$db->close();
