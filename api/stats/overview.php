<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';
setCorsHeaders();
$user = requireCreator();
$db = getDB();
$totalSubs = $db->querySingle("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'") ?: 0;
$totalPosts = $db->querySingle("SELECT COUNT(*) FROM posts") ?: 0;
$totalMessages = $db->querySingle("SELECT COUNT(*) FROM messages") ?: 0;
$totalLikes = $db->querySingle("SELECT COALESCE(SUM(like_count),0) FROM posts") ?: 0;
$totalEarnings = $db->querySingle("SELECT COALESCE(SUM(amount_cents),0) FROM transactions WHERE status='completed'") ?: 0;
$totalTips = $db->querySingle("SELECT COALESCE(SUM(amount_cents),0) FROM tips") ?: 0;
$totalUsers = $db->querySingle("SELECT COUNT(*) FROM users WHERE role='fan'") ?: 0;
$recentMessages = $db->querySingle("SELECT COUNT(*) FROM messages WHERE created_at > datetime('now','-24 hours')") ?: 0;
$db->close();
jsonResponse(['ok'=>true,'subscribers'=>$totalSubs,'registered_fans'=>$totalUsers,'posts'=>$totalPosts,'messages'=>$totalMessages,'messages_24h'=>$recentMessages,'likes'=>$totalLikes,'earnings_cents'=>$totalEarnings,'tips_cents'=>$totalTips]);
