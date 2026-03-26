<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';

if (function_exists('setCorsHeaders')) setCorsHeaders();
requireCreator();

$db = getDB();
// Ensure table exists
$db->exec("CREATE TABLE IF NOT EXISTS traffic_sources (id INTEGER PRIMARY KEY AUTOINCREMENT, platform TEXT NOT NULL DEFAULT 'Direct', referrer_url TEXT DEFAULT '', user_agent TEXT DEFAULT '', ip_hash TEXT DEFAULT '', user_id INTEGER DEFAULT NULL, converted_signup INTEGER DEFAULT 0, converted_purchase INTEGER DEFAULT 0, landed_at TEXT DEFAULT (datetime('now')))");

$period = $_GET['period'] ?? 'today';
$dateFilter = match($period) {
    'week' => "datetime('now', '-7 days')",
    'month' => "datetime('now', '-30 days')",
    default => "datetime('now', 'start of day')",
};

// Visits by platform
$result = $db->query("SELECT platform, COUNT(*) as visits, COUNT(DISTINCT ip_hash) as unique_visitors FROM traffic_sources WHERE landed_at >= $dateFilter GROUP BY platform ORDER BY visits DESC");
$platforms = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $platforms[] = $row;
}

// Total visits
$total = $db->querySingle("SELECT COUNT(*) FROM traffic_sources WHERE landed_at >= $dateFilter");
$unique = $db->querySingle("SELECT COUNT(DISTINCT ip_hash) FROM traffic_sources WHERE landed_at >= $dateFilter");

// Recent visits (last 20)
$recent = [];
$result = $db->query("SELECT platform, referrer_url, landed_at FROM traffic_sources ORDER BY landed_at DESC LIMIT 20");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $recent[] = $row;
}

$db->close();

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
}

jsonResponse([
    'period' => $period,
    'total_visits' => $total,
    'unique_visitors' => $unique,
    'by_platform' => $platforms,
    'recent' => $recent,
]);
