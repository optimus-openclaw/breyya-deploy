<?php
// Breyya Content Health System - Cron endpoint to store content inventory data
// Auth: breyya-stats-cron-2026

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON payload (read once)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check secret from GET param or JSON body
$secret = isset($_GET['secret']) ? $_GET['secret'] : ($data['secret'] ?? '');
if ($secret !== 'breyya-stats-cron-2026') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
// Remove secret from data before storing
unset($data['secret']);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

// Validate required fields
$required_fields = ['ppv_sets_available', 'feed_posts_remaining', 'feed_days_runway', 'most_popular_set', 'fans_bought_all'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

require_once __DIR__ . '/../lib/database.php';
$db = getDB();

// Create content_health table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS content_health (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    data_json TEXT NOT NULL,
    updated_at TEXT DEFAULT (datetime('now'))
)");

// Upsert the data
$data_json = json_encode($data);
$stmt = $db->prepare("INSERT OR REPLACE INTO content_health (id, data_json, updated_at) VALUES (1, ?, datetime('now'))");
$stmt->bindParam(1, $data_json, SQLITE3_TEXT);
$result = $stmt->execute();
$stmt->close();

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $db->lastErrorMsg()]);
    exit;
}

echo json_encode([
    'success' => true,
    'data_stored' => $data,
    'updated_at' => (new DateTime('now', new DateTimeZone('UTC')))->format(DATE_ATOM)
]);

$db->close();
?>