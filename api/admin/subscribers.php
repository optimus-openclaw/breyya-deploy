<?php
/**
 * GET /api/admin/subscribers
 * Returns subscriber list + aggregate stats
 * Requires admin/creator auth
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireCreator();

$db = getDB();

// --- Aggregate stats ---
$totalSubs = $db->querySingle("SELECT COUNT(DISTINCT s.user_id) FROM subscriptions s JOIN users u ON u.id = s.user_id WHERE u.role = 'fan' AND s.status = 'active' AND s.expires_at > datetime('now')") ?: 0;

$totalRevenue = $db->querySingle("SELECT COALESCE(SUM(amount_cents), 0) FROM transactions WHERE status = 'completed'") ?: 0;

$avgSpend = $totalSubs > 0 ? round($totalRevenue / $totalSubs) : 0;

// Churn: expired or cancelled in last 30 days vs active 30 days ago
$churnedCount = $db->querySingle("SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status IN ('cancelled','expired') AND expires_at >= datetime('now','-30 days') AND expires_at <= datetime('now')") ?: 0;
$activeThirtyDaysAgo = $db->querySingle("SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE started_at <= datetime('now','-30 days') AND (expires_at > datetime('now','-30 days') OR status = 'active')") ?: 0;
$churnRate = $activeThirtyDaysAgo > 0 ? round(($churnedCount / $activeThirtyDaysAgo) * 100, 1) : 0;

// --- Sorting ---
$sortBy = $_GET['sort'] ?? 'join_date';
$sortDir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$orderClause = match ($sortBy) {
    'spend'       => "total_spend $sortDir",
    'last_active' => "last_active $sortDir",
    default       => "join_date $sortDir",
};

// --- Subscriber list ---
$query = "
    SELECT
        u.id,
        u.display_name,
        u.email,
        u.created_at AS join_date,
        s.status AS subscription_status,
        s.expires_at,
        COALESCE(t.total_spend, 0) AS total_spend,
        COALESCE(
            (SELECT MAX(m.created_at) FROM messages m WHERE m.sender_id = u.id),
            u.created_at
        ) AS last_active
    FROM users u
    JOIN subscriptions s ON s.user_id = u.id
        AND s.id = (SELECT MAX(s2.id) FROM subscriptions s2 WHERE s2.user_id = u.id)
    LEFT JOIN (
        SELECT user_id, SUM(amount_cents) AS total_spend
        FROM transactions
        WHERE status = 'completed'
        GROUP BY user_id
    ) t ON t.user_id = u.id
    WHERE u.role = 'fan'
    ORDER BY $orderClause
    LIMIT 200
";

$result = $db->query($query);
$subscribers = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscribers[] = $row;
}

$db->close();

jsonResponse([
    'stats' => [
        'total_subscribers' => $totalSubs,
        'total_revenue_cents' => $totalRevenue,
        'avg_spend_cents' => $avgSpend,
        'churn_rate' => $churnRate,
    ],
    'subscribers' => $subscribers,
]);
