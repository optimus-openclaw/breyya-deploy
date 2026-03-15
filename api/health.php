<?php
/**
 * GET /api/health
 * Simple health check endpoint - returns public status
 */

require_once __DIR__ . '/lib/database.php';
setCorsHeaders();

try {
    // Test database connection
    $db = getDB();
    $db->close();
    
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
    http_response_code(503);
    jsonResponse([
        'ok' => false,
        'status' => 'unhealthy',
        'timestamp' => date('c'),
        'error' => 'Service unavailable'
    ], 503);
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