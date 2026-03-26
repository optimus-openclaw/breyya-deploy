<?php
// Breyya AI Costs System - Cron endpoint to store AI usage/cost data
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
unset($data['secret']);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

// Validate required fields
$required_fields = ['today', 'month_total'];
if (!isset($data['today']) || !isset($data['month_total'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: today, month_total']);
    exit;
}

// Validate today structure
$today_required = ['sonnet', 'mini', 'opus', 'total'];
foreach ($today_required as $field) {
    if (!isset($data['today'][$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field in today: $field"]);
        exit;
    }
}

require_once __DIR__ . '/../lib/database.php';
$db = getDB();

// Create ai_costs table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS ai_costs (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    data_json TEXT NOT NULL,
    updated_at TEXT DEFAULT (datetime('now'))
)");

// Upsert the data
$data_json = json_encode($data);
$stmt = $db->prepare("INSERT OR REPLACE INTO ai_costs (id, data_json, updated_at) VALUES (1, ?, datetime('now'))");
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