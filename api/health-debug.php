<?php
/**
 * GET /api/health - DEBUG VERSION
 * Simple health check endpoint - returns public status
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/lib/database.php';
    echo "Step 1: database.php loaded<br>\n";
    
    setCorsHeaders();
    echo "Step 2: CORS headers set<br>\n";

    // Test database connection
    echo "Step 3: About to test database<br>\n";
    $db = getDB();
    echo "Step 4: Database connection obtained<br>\n";
    $db->close();
    echo "Step 5: Database closed<br>\n";
    
    jsonResponse([
        'ok' => true,
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => '1.0',
        'services' => [
            'database' => 'connected',
            'api' => 'active'
        ]
    ]);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>\n";
    echo "STACK: " . $e->getTraceAsString() . "<br>\n";
    die();
}

function setCorsHeaders(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}