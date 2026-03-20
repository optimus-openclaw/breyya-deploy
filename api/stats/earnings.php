<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';
setCorsHeaders();
$user = requireCreator();
$db = getDB();
$subRev = $db->querySingle("SELECT COALESCE(SUM(amount_cents),0) FROM transactions WHERE type='subscription' AND status='completed'") ?: 0;
$tipRev = $db->querySingle("SELECT COALESCE(SUM(amount_cents),0) FROM transactions WHERE type='tip' AND status='completed'") ?: 0;
$ppvRev = $db->querySingle("SELECT COALESCE(SUM(amount_cents),0) FROM transactions WHERE type='ppv' AND status='completed'") ?: 0;
$refunds = $db->querySingle("SELECT COALESCE(SUM(amount_cents),0) FROM transactions WHERE type='refund'") ?: 0;
$recent = [];
$stmt = $db->query("SELECT t.*, u.display_name, u.email FROM transactions t LEFT JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 20");
while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) { $recent[] = $row; }
$db->close();
jsonResponse(['ok'=>true,'subscriptions'=>$subRev,'tips'=>$tipRev,'ppv'=>$ppvRev,'refunds'=>$refunds,'total'=>$subRev+$tipRev+$ppvRev-$refunds,'recent'=>$recent]);
