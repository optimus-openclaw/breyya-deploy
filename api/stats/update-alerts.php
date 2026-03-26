<?php
// Breyya Alerts Engine - Cron endpoint to evaluate alert rules
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

// Create tables if not exist
$db->exec("CREATE TABLE IF NOT EXISTS fan_flags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fan_user_id INTEGER NOT NULL,
    reason TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (fan_user_id) REFERENCES users(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    level TEXT NOT NULL CHECK(level IN ('red', 'yellow', 'green')),
    message TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now'))
)");

// Clear old alerts
$db->exec("DELETE FROM alerts");

$alerts = [];

// RED ALERT: Fans who haven't messaged in 14+ days (but were active before)
$fourteen_days_ago = (new DateTime('-14 days'))->format('Y-m-d H:i:s');
$silent_fans = $db->querySingle("
    SELECT COUNT(DISTINCT u.id) 
    FROM users u
    JOIN messages m ON m.sender_id = u.id
    WHERE u.role = 'fan'
    AND u.id NOT IN (
        SELECT DISTINCT sender_id 
        FROM messages 
        WHERE created_at >= '$fourteen_days_ago'
    )
    AND EXISTS (
        SELECT 1 FROM messages 
        WHERE sender_id = u.id 
        AND created_at < '$fourteen_days_ago'
    )
");

if ($silent_fans > 0) {
    $alerts[] = ['level' => 'red', 'message' => "$silent_fans fans silent 14+ days"];
}

// RED ALERT: Any fan flagged in fan_flags table
$flagged_fans = $db->query("
    SELECT ff.reason, COUNT(*) as count 
    FROM fan_flags ff 
    JOIN users u ON ff.fan_user_id = u.id 
    GROUP BY ff.reason
");

if ($flagged_fans) {
    while ($flag = $flagged_fans->fetchArray(SQLITE3_ASSOC)) {
        $alerts[] = ['level' => 'red', 'message' => "{$flag['count']} fans flagged: {$flag['reason']}"];
    }
}

// YELLOW ALERT: Content health ppv_sets_available < 5
$content_health = $db->querySingle("SELECT data_json FROM content_health WHERE id = 1");
if ($content_health) {
    $content_data = json_decode($content_health, true);
    if ($content_data && isset($content_data['ppv_sets_available']) && $content_data['ppv_sets_available'] < 5) {
        $alerts[] = ['level' => 'yellow', 'message' => 'Content inventory below 5 PPV sets'];
    }
}

// YELLOW ALERT: Any chargebacks this week
$week_start = (new DateTime('monday this week'))->format('Y-m-d 00:00:00');
$week_end = date('Y-m-d 23:59:59', strtotime($week_start . ' +6 days'));
$chargebacks = $db->querySingle("
    SELECT COUNT(*) 
    FROM transactions 
    WHERE type = 'chargeback' 
    AND created_at BETWEEN '$week_start' AND '$week_end'
");

if ($chargebacks > 0) {
    $alerts[] = ['level' => 'yellow', 'message' => "$chargebacks chargebacks this week"];
}

// Insert all alerts into database
foreach ($alerts as $alert) {
    $stmt = $db->prepare("INSERT INTO alerts (level, message) VALUES (?, ?)");
    $stmt->bindParam(1, $alert['level'], SQLITE3_TEXT);
    $stmt->bindParam(2, $alert['message'], SQLITE3_TEXT);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'alerts_generated' => count($alerts),
    'alerts' => $alerts,
    'updated_at' => (new DateTime('now', new DateTimeZone('UTC')))->format(DATE_ATOM)
]);

$db->close();
?>