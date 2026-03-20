<?php
header('Content-Type: application/json');

// Test the chat processor parts that might be failing
$SECRET = 'breyya-chat-cron-2026';
$secret = $_GET['secret'] ?? '';

if ($secret !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'auth failed']));
}

try {
    // Test secrets loading
    $_sf = __DIR__ . '/../.secrets.php';
    echo json_encode(['step' => 1, 'secrets_file' => $_sf, 'exists' => file_exists($_sf)]);
    
    // Also test the contents
    if (file_exists($_sf)) {
        $contents = file_get_contents($_sf);
        echo json_encode(['step' => '1b', 'file_size' => strlen($contents), 'has_define' => strpos($contents, 'define') !== false]);
        
        // Test if there's a PHP syntax error by evaluating it
        $evalResult = null;
        ob_start();
        $error = error_get_last();
        try {
            eval('?>' . $contents);
            $evalResult = 'ok';
        } catch (ParseError $e) {
            $evalResult = 'parse_error: ' . $e->getMessage();
        } catch (Throwable $e) {
            $evalResult = 'error: ' . $e->getMessage();
        }
        ob_end_clean();
        echo json_encode(['step' => '1c', 'eval_result' => $evalResult]);
    }
    
    if (file_exists($_sf)) {
        require_once $_sf;
        echo json_encode(['step' => '2a', 'require_ok' => true]);
    }
    
    echo json_encode(['step' => '2b', 'defined' => defined('AI_API_KEY')]);
    
    if (defined('AI_API_KEY')) {
        $keyValue = AI_API_KEY;
        echo json_encode(['step' => '2c', 'key_empty' => ($keyValue === ''), 'key_length' => strlen($keyValue)]);
        if ($keyValue !== '') {
            $ANTHROPIC_KEY = $keyValue;
            echo json_encode(['step' => 2, 'key_loaded' => substr($ANTHROPIC_KEY, 0, 10) . '...']);
        } else {
            echo json_encode(['step' => 2, 'key_loaded' => false, 'reason' => 'empty']);
        }
    } else {
        echo json_encode(['step' => 2, 'key_loaded' => false, 'reason' => 'not_defined']);
    }
    
    // Test inventory paths
    $INVENTORY_PATHS = [
        '/Users/optimus/.openclaw/workspace/skills/breyya-site/r2-inventory.json',
        __DIR__ . '/../../data/r2-inventory.json',
    ];
    
    $inventoryStatus = [];
    foreach ($INVENTORY_PATHS as $p) {
        $inventoryStatus[] = ['path' => $p, 'exists' => file_exists($p)];
    }
    echo json_encode(['step' => 3, 'inventory_paths' => $inventoryStatus]);
    
    // Test timezone operations
    $pt = new DateTimeZone('America/Los_Angeles');
    $now = new DateTime('now', $pt);
    $hour = (int)$now->format('G');
    echo json_encode(['step' => 4, 'timezone_test' => 'OK', 'current_hour_pt' => $hour]);
    
    // Test database operations
    $DB_PATH = __DIR__ . '/../data/breyya.db';
    $db = new SQLite3($DB_PATH);
    $db->busyTimeout(5000);
    echo json_encode(['step' => 5, 'database' => 'OK']);
    
    // Test the main query from chat processor
    $CREATOR_ID = 1;
    $r = $db->query("SELECT m.id, m.sender_id, m.content, m.created_at FROM messages m WHERE m.receiver_id = $CREATOR_ID AND m.sender_id != $CREATOR_ID AND m.is_ai = 0 AND m.id NOT IN (SELECT fan_message_id FROM chat_queue) ORDER BY m.created_at ASC LIMIT 20");
    $count = 0;
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $count++;
    }
    echo json_encode(['step' => 6, 'query_test' => 'OK', 'new_messages' => $count]);
    
    $db->close();
    echo json_encode(['step' => 7, 'msg' => 'All chat processor components tested successfully']);
    
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
}
?>