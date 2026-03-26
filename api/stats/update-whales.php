<?php
// Breyya Whale Watch System - Cron endpoint to recalculate whale scores
// Auth: breyya-stats-cron-2026

header('Content-Type: application/json');

$secret = isset($_GET['secret']) ? $_GET['secret'] : '';
if ($secret !== 'breyya-stats-cron-2026') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../lib/database.php';
$db = getDB();

// Create whale_scores table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS whale_scores (
    fan_user_id INTEGER PRIMARY KEY,
    score REAL NOT NULL DEFAULT 0,
    total_spent REAL NOT NULL DEFAULT 0,
    last_activity TEXT,
    updated_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (fan_user_id) REFERENCES users(id)
)");

// Calculate whale scores for all users
// Formula: total_spent (dollars) + (total_messages * 0.5) + (days_active * 1) + (ppv_purchased_count * 5)

$whale_sql = "
INSERT OR REPLACE INTO whale_scores (fan_user_id, score, total_spent, last_activity, updated_at)
SELECT 
    u.id as fan_user_id,
    (
        COALESCE(spent.total_dollars, 0) + 
        (COALESCE(msgs.total_messages, 0) * 0.5) + 
        COALESCE(activity.days_active, 0) + 
        (COALESCE(ppv.ppv_count, 0) * 5)
    ) as score,
    COALESCE(spent.total_dollars, 0) as total_spent,
    COALESCE(activity.last_activity, u.created_at) as last_activity,
    datetime('now') as updated_at
FROM users u
LEFT JOIN (
    SELECT user_id, SUM(amount_cents)/100.0 as total_dollars
    FROM transactions 
    WHERE type IN ('tip', 'ppv', 'subscription')
    GROUP BY user_id
) spent ON spent.user_id = u.id
LEFT JOIN (
    SELECT sender_id as user_id, COUNT(*) as total_messages
    FROM messages
    GROUP BY sender_id
) msgs ON msgs.user_id = u.id
LEFT JOIN (
    SELECT user_id, 
           COUNT(DISTINCT DATE(created_at)) as days_active,
           MAX(created_at) as last_activity
    FROM (
        SELECT sender_id as user_id, created_at FROM messages
        UNION ALL
        SELECT user_id, created_at FROM transactions
    )
    GROUP BY user_id
) activity ON activity.user_id = u.id
LEFT JOIN (
    SELECT user_id, COUNT(*) as ppv_count
    FROM transactions
    WHERE type = 'ppv'
    GROUP BY user_id
) ppv ON ppv.user_id = u.id
WHERE u.role = 'fan'
";

$result = $db->exec($whale_sql);
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $db->lastErrorMsg()]);
    exit;
}

$affected_rows = $db->changes();
$total_whales = $db->querySingle("SELECT COUNT(*) FROM whale_scores");
$whales_70plus = $db->querySingle("SELECT COUNT(*) FROM whale_scores WHERE score >= 70");

echo json_encode([
    'success' => true,
    'updated_scores' => $affected_rows,
    'total_whales' => intval($total_whales),
    'whales_70plus' => intval($whales_70plus),
    'updated_at' => (new DateTime('now', new DateTimeZone('UTC')))->format(DATE_ATOM)
]);

$db->close();
?>