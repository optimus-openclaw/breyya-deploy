<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';
setCorsHeaders();
$user = requireCreator();
$db = getDB();
$fans = [];
$stmt = $db->query("SELECT u.id, u.email, u.display_name, u.created_at, (SELECT COUNT(*) FROM messages WHERE sender_id=u.id) as msg_count, (SELECT COUNT(*) FROM post_likes WHERE user_id=u.id) as like_count, (SELECT COALESCE(SUM(amount_cents),0) FROM transactions WHERE user_id=u.id AND status='completed') as total_spent, (SELECT status FROM subscriptions WHERE user_id=u.id ORDER BY created_at DESC LIMIT 1) as sub_status FROM users u WHERE u.role='fan' ORDER BY u.created_at DESC");
while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) { $fans[] = $row; }
$db->close();
jsonResponse(['ok'=>true,'fans'=>$fans]);
