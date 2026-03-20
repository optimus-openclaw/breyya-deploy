<?php
header('Content-Type: application/json');

// Simple test to isolate what's failing in the chat processor
$SECRET = 'breyya-chat-cron-2026';
$secret = $_GET['secret'] ?? '';

if ($secret !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'auth failed']));
}

try {
    echo json_encode(['step' => 1, 'msg' => 'Auth OK']);

    // Test database connection
    $DB_PATH = __DIR__ . '/../data/breyya.db';
    echo json_encode(['step' => 2, 'db_path' => $DB_PATH, 'exists' => file_exists($DB_PATH)]);
    
    $db = new SQLite3($DB_PATH);
    echo json_encode(['step' => 3, 'msg' => 'DB connection OK']);
    
    // Test table creation
    $db->exec("CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY, name TEXT)");
    echo json_encode(['step' => 4, 'msg' => 'Table creation OK']);
    
    // Test the complex functions one by one
    echo json_encode(['step' => 5, 'msg' => 'Basic tests passed']);
    
    $db->close();
    
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
}
?>