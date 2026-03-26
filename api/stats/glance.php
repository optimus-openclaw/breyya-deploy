<?php
// Breyya Stats Glance API - Phase 2
// Stats start date filter - only count data from this date forward
define('STATS_START_DATE', '2026-03-24');

// Hardcoded key (32 chars)
$STATS_KEY = 'b3f9a7c2d8e14f6b9a0c3d5e7f8a1b2c'; // generated

header('Content-Type: application/json');

$key = isset($_GET['key']) ? $_GET['key'] : '';
if ($key !== $STATS_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../lib/database.php';
$db = getDB();

function iso_now() {
    $dt = new DateTime('now', new DateTimeZone('UTC'));
    return $dt->format(DATE_ATOM);
}

// Helpers for date ranges
$today_start = (new DateTime('today'))->format('Y-m-d 00:00:00');
$today_end = (new DateTime('today'))->format('Y-m-d 23:59:59');
$week_start = (new DateTime('monday this week'))->format('Y-m-d 00:00:00');
$month_start = (new DateTime('first day of this month'))->format('Y-m-d 00:00:00');

// Today's revenue: subscriptions, tips, and PPV (all from transactions table with STATS_START_DATE filter)
$today_subs = $db->querySingle("SELECT IFNULL(SUM(amount_cents),0) FROM transactions WHERE type='subscription' AND created_at BETWEEN '$today_start' AND '$today_end' AND created_at >= '" . STATS_START_DATE . "'");
$today_tips = $db->querySingle("SELECT IFNULL(SUM(amount_cents),0) FROM transactions WHERE type='tip' AND created_at BETWEEN '$today_start' AND '$today_end' AND created_at >= '" . STATS_START_DATE . "'");
$today_ppv = $db->querySingle("SELECT IFNULL(SUM(amount_cents),0) FROM transactions WHERE type='ppv' AND created_at BETWEEN '$today_start' AND '$today_end' AND created_at >= '" . STATS_START_DATE . "'");

// Week totals (with STATS_START_DATE filter)
$week_end = date('Y-m-d 23:59:59', strtotime($week_start . ' +6 days'));
$week_total = $db->querySingle("SELECT IFNULL(SUM(amount_cents),0) FROM transactions WHERE created_at BETWEEN '$week_start' AND '$week_end' AND created_at >= '" . STATS_START_DATE . "'");
$week_tips = $db->querySingle("SELECT IFNULL(SUM(amount_cents),0) FROM transactions WHERE type='tip' AND created_at BETWEEN '$week_start' AND '$week_end' AND created_at >= '" . STATS_START_DATE . "'");
$week_ppv = $db->querySingle("SELECT IFNULL(SUM(amount_cents),0) FROM transactions WHERE type='ppv' AND created_at BETWEEN '$week_start' AND '$week_end' AND created_at >= '" . STATS_START_DATE . "'");

// Month totals (with STATS_START_DATE filter)
$month_end = date('Y-m-d 23:59:59', strtotime($month_start . ' +1 month -1 day'));
$month_total = $db->querySingle("SELECT IFNULL(SUM(amount_cents),0) FROM transactions WHERE created_at BETWEEN '$month_start' AND '$month_end' AND created_at >= '" . STATS_START_DATE . "'");
$month_tips = $db->querySingle("SELECT IFNULL(SUM(amount_cents),0) FROM transactions WHERE type='tip' AND created_at BETWEEN '$month_start' AND '$month_end' AND created_at >= '" . STATS_START_DATE . "'");
$month_ppv = $db->querySingle("SELECT IFNULL(SUM(amount_cents),0) FROM transactions WHERE type='ppv' AND created_at BETWEEN '$month_start' AND '$month_end' AND created_at >= '" . STATS_START_DATE . "'");

// New subscribers counts (with STATS_START_DATE filter)
$today_new_subs = $db->querySingle("SELECT COUNT(DISTINCT user_id) FROM transactions WHERE type='subscription' AND created_at BETWEEN '$today_start' AND '$today_end' AND created_at >= '" . STATS_START_DATE . "'");
$week_new_subs = $db->querySingle("SELECT COUNT(DISTINCT user_id) FROM transactions WHERE type='subscription' AND created_at BETWEEN '$week_start' AND '$week_end' AND created_at >= '" . STATS_START_DATE . "'");
$month_new_subs = $db->querySingle("SELECT COUNT(DISTINCT user_id) FROM transactions WHERE type='subscription' AND created_at BETWEEN '$month_start' AND '$month_end' AND created_at >= '" . STATS_START_DATE . "'");

// Subscribers active (use is_active from users table, with STATS_START_DATE filter)
$active_subs = $db->querySingle("SELECT COUNT(*) FROM users WHERE is_active = 1 AND created_at >= '" . STATS_START_DATE . "'");

// New subscribers new_today/new_this_week (from users.created_at, with STATS_START_DATE filter)
$new_today = $db->querySingle("SELECT COUNT(*) FROM users WHERE created_at BETWEEN '$today_start' AND '$today_end' AND created_at >= '" . STATS_START_DATE . "'");
$new_week = $db->querySingle("SELECT COUNT(*) FROM users WHERE created_at BETWEEN '$week_start' AND '$week_end' AND created_at >= '" . STATS_START_DATE . "'");

// Chat activity: active chatters (distinct senders in messages in last 24h), messages_received, replies_sent (use is_ai=1 as replies)
$last24 = (new DateTime('-24 hours'))->format('Y-m-d H:i:s');
$active_chatters = $db->querySingle("SELECT COUNT(DISTINCT sender_id) FROM messages WHERE created_at >= '$last24' AND created_at >= '" . STATS_START_DATE . "'");
$messages_received = $db->querySingle("SELECT COUNT(*) FROM messages WHERE created_at >= '$last24' AND is_ai = 0 AND created_at >= '" . STATS_START_DATE . "'");
$replies_sent = $db->querySingle("SELECT COUNT(*) FROM messages WHERE created_at >= '$last24' AND is_ai = 1 AND created_at >= '" . STATS_START_DATE . "'");

// Top fans today: combine tips and PPV from transactions table (with STATS_START_DATE filter)
$top_fans = [];
$top_sql = "SELECT u.display_name as username, IFNULL(SUM(t.amount_cents),0) as spent FROM users u
LEFT JOIN transactions t ON t.user_id = u.id 
WHERE t.type IN ('tip', 'ppv') 
AND t.created_at BETWEEN '$today_start' AND '$today_end' 
AND t.created_at >= '" . STATS_START_DATE . "'
GROUP BY u.id
HAVING spent > 0
ORDER BY spent DESC
LIMIT 3";
$rows = $db->query($top_sql);
if ($rows) {
    while ($r = $rows->fetchArray(SQLITE3_ASSOC)) {
        $top_fans[] = ['username' => $r['username'], 'spent_today' => floatval($r['spent']) / 100]; // Convert cents to dollars
    }
}

// Whale data
$whales_70plus = $db->querySingle("SELECT COUNT(*) FROM whale_scores WHERE score >= 70") ?: 0;
$whales_active_today = $db->querySingle("SELECT COUNT(*) FROM whale_scores ws JOIN (SELECT DISTINCT sender_id as user_id FROM messages WHERE created_at >= '$today_start' UNION SELECT DISTINCT user_id FROM transactions WHERE created_at >= '$today_start') today ON ws.fan_user_id = today.user_id WHERE ws.score >= 70") ?: 0;
$highest_whale = $db->querySingle("SELECT u.display_name as username, ws.score FROM whale_scores ws JOIN users u ON ws.fan_user_id = u.id ORDER BY ws.score DESC LIMIT 1", true);
if (!$highest_whale) $highest_whale = ['username' => '', 'score' => 0];
$seven_days_ago = (new DateTime('-7 days'))->format('Y-m-d H:i:s');
$rising_stars = $db->querySingle("SELECT COUNT(*) FROM whale_scores WHERE score >= 40 AND updated_at >= '$seven_days_ago'") ?: 0;

// Content health
$content_health = $db->querySingle("SELECT data_json, updated_at FROM content_health WHERE id = 1", true);
if ($content_health) {
    $content_data = json_decode($content_health['data_json'], true);
    $content_data['last_updated'] = $content_health['updated_at'];
} else {
    $content_data = [
        'ppv_sets_available' => 0,
        'feed_posts_remaining' => 0, 
        'feed_days_runway' => 0,
        'most_popular_set' => ['name' => '', 'sales' => 0],
        'fans_bought_all' => 0,
        'last_updated' => ''
    ];
}

// AI costs
$ai_costs = $db->querySingle("SELECT data_json, updated_at FROM ai_costs WHERE id = 1", true);
if ($ai_costs) {
    $ai_data = json_decode($ai_costs['data_json'], true);
    $ai_data['last_updated'] = $ai_costs['updated_at'];
} else {
    $ai_data = [
        'today' => [
            'sonnet' => ['runs' => 0, 'cost' => 0.00],
            'mini' => ['runs' => 0, 'cost' => 0.00],
            'opus' => ['runs' => 0, 'cost' => 0.00],
            'total' => 0.00
        ],
        'month_total' => 0.00,
        'last_updated' => ''
    ];
}

// Alerts
$alerts = [];
$alert_rows = $db->query("SELECT level, message FROM alerts ORDER BY created_at DESC");
if ($alert_rows) {
    while ($alert = $alert_rows->fetchArray(SQLITE3_ASSOC)) {
        $alerts[] = $alert;
    }
}

$response = [
    'updated' => iso_now(),
    'today' => [
        'revenue_subscriptions' => floatval($today_subs) / 100,
        'revenue_tips' => floatval($today_tips) / 100,
        'revenue_ppv' => floatval($today_ppv) / 100,
        'revenue_total' => (floatval($today_subs) + floatval($today_tips) + floatval($today_ppv)) / 100,
        'new_subscribers' => intval($today_new_subs)
    ],
    'week' => [
        'revenue_total' => floatval($week_total) / 100,
        'new_subscribers' => intval($week_new_subs),
        'tips_total' => floatval($week_tips) / 100,
        'ppv_total' => floatval($week_ppv) / 100
    ],
    'month' => [
        'revenue_total' => floatval($month_total) / 100,
        'new_subscribers' => intval($month_new_subs),
        'tips_total' => floatval($month_tips) / 100,
        'ppv_total' => floatval($month_ppv) / 100
    ],
    'subscribers' => [
        'active' => intval($active_subs),
        'new_today' => intval($new_today),
        'new_this_week' => intval($new_week)
    ],
    'chat' => [
        'active_chatters' => intval($active_chatters),
        'messages_received' => intval($messages_received),
        'replies_sent' => intval($replies_sent)
    ],
    'top_fans_today' => $top_fans,
    'whales' => [
        'total_70plus' => intval($whales_70plus),
        'active_today' => intval($whales_active_today),
        'highest' => [
            'username' => $highest_whale['username'] ?? '',
            'score' => floatval($highest_whale['score'] ?? 0)
        ],
        'rising_stars' => intval($rising_stars)
    ],
    'content' => $content_data,
    'ai_costs' => $ai_data,
    'alerts' => $alerts
];

echo json_encode($response);

?>