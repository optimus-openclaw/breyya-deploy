<?php
header('Content-Type: application/json');

$SECRET = 'breyya-chat-cron-2026';
$DB_PATH = __DIR__ . '/../../data/breyya.db';
$BACKUP_DIR = __DIR__ . '/../../data/backups';

// Check secret
$secret = $_GET['secret'] ?? '';
if ($secret !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'unauthorized']));
}

try {
    // Ensure backup directory exists
    if (!is_dir($BACKUP_DIR)) {
        if (!mkdir($BACKUP_DIR, 0755, true)) {
            throw new Exception('Failed to create backup directory');
        }
    }

    // Check if database exists
    if (!file_exists($DB_PATH)) {
        throw new Exception('Database file not found: ' . $DB_PATH);
    }

    $today = date('Y-m-d');
    $backupFile = $BACKUP_DIR . "/breyya-{$today}.db";
    $jsonExportFile = $BACKUP_DIR . "/breyya-{$today}.json";

    // Copy database file
    if (!copy($DB_PATH, $backupFile)) {
        throw new Exception('Failed to copy database file');
    }

    // Open database for JSON export
    $db = new SQLite3($DB_PATH);
    $db->busyTimeout(5000);

    // Export key tables to JSON
    $export = [
        'timestamp' => date('c'),
        'backup_date' => $today,
        'tables' => []
    ];

    $tables = ['fan_profiles', 'messages', 'tip_events', 'posts', 'subscriptions'];
    foreach ($tables as $table) {
        // Check if table exists
        $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if (!$tableExists) {
            $export['tables'][$table] = ['status' => 'table_not_exists', 'count' => 0, 'data' => []];
            continue;
        }

        $export['tables'][$table] = ['data' => []];
        
        // Get row count
        $count = $db->querySingle("SELECT COUNT(*) FROM $table");
        $export['tables'][$table]['count'] = intval($count);

        // Export data (limit to 1000 rows per table for large tables)
        $limit = in_array($table, ['messages']) ? 1000 : 5000;
        $result = $db->query("SELECT * FROM $table ORDER BY rowid DESC LIMIT $limit");
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $export['tables'][$table]['data'][] = $row;
        }
    }

    $db->close();

    // Save JSON export
    file_put_contents($jsonExportFile, json_encode($export, JSON_PRETTY_PRINT));

    // Clean up old backups (keep last 7 days)
    $files = glob($BACKUP_DIR . '/breyya-*.db');
    if (count($files) > 7) {
        // Sort files by modification time, oldest first
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Remove oldest files, keep newest 7
        $filesToDelete = array_slice($files, 0, count($files) - 7);
        foreach ($filesToDelete as $file) {
            @unlink($file);
            // Also remove corresponding JSON file
            $jsonFile = str_replace('.db', '.json', $file);
            @unlink($jsonFile);
        }
    }

    // Clean up old JSON exports too
    $jsonFiles = glob($BACKUP_DIR . '/breyya-*.json');
    if (count($jsonFiles) > 7) {
        usort($jsonFiles, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        $jsonFilesToDelete = array_slice($jsonFiles, 0, count($jsonFiles) - 7);
        foreach ($jsonFilesToDelete as $file) {
            @unlink($file);
        }
    }

    echo json_encode([
        'success' => true,
        'backup_file' => basename($backupFile),
        'json_export' => basename($jsonExportFile),
        'db_size' => filesize($backupFile),
        'json_size' => filesize($jsonExportFile),
        'tables_exported' => array_keys($export['tables']),
        'download_url' => '/data/backups/' . basename($backupFile),
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>