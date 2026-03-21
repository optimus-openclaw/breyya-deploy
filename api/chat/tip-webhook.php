<?php
header('Content-Type: application/json');

$SECRET = 'breyya-chat-cron-2026';
$DB_PATH = __DIR__ . '/../../data/breyya.db';

// Accept tip event via POST from CCBill webhook OR GET with secret for testing
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // CCBill webhook POST
    $input = json_decode(file_get_contents('php://input'), true);
    $fanId = intval($input['fan_id'] ?? 0);
    $amountDollars = floatval($input['amount'] ?? 0);
    $purpose = trim($input['purpose'] ?? '');
} elseif ($method === 'GET') {
    // Manual testing via GET with secret
    $secret = $_GET['secret'] ?? '';
    if ($secret !== $SECRET) {
        http_response_code(403);
        die(json_encode(['error' => 'unauthorized']));
    }
    
    $fanId = intval($_GET['fan_id'] ?? 0);
    $amountDollars = floatval($_GET['amount'] ?? 0);
    $purpose = trim($_GET['purpose'] ?? '');
} else {
    http_response_code(405);
    die(json_encode(['error' => 'method not allowed']));
}

// Validate required params
if ($fanId <= 0 || $amountDollars <= 0) {
    http_response_code(400);
    die(json_encode(['error' => 'invalid fan_id or amount']));
}

// Convert dollars to cents
$amountCents = intval($amountDollars * 100);

try {
    $db = new SQLite3($DB_PATH);
    $db->busyTimeout(5000);
    
    // Ensure tip_events table exists
    $db->exec("CREATE TABLE IF NOT EXISTS tip_events (id INTEGER PRIMARY KEY AUTOINCREMENT, fan_user_id INTEGER NOT NULL, amount_cents INTEGER NOT NULL, purpose TEXT DEFAULT '', processed INTEGER DEFAULT 0, created_at TEXT DEFAULT (datetime('now')))");
    
    // Insert tip event
    $safePurpose = $db->escapeString($purpose);
    $db->exec("INSERT INTO tip_events (fan_user_id, amount_cents, purpose, processed) VALUES ($fanId, $amountCents, '$safePurpose', 0)");
    
    $tipId = $db->lastInsertRowID();
    $db->close();
    
    echo json_encode([
        'ok' => true,
        'tip_id' => $tipId,
        'fan_id' => $fanId,
        'amount_cents' => $amountCents,
        'purpose' => $purpose,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'database_error', 'message' => $e->getMessage()]);
}