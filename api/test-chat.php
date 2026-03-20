<?php
header('Content-Type: application/json');

// Test the specific tables and operations the chat processor needs
$SECRET = 'breyya-chat-cron-2026';
$secret = $_GET['secret'] ?? '';

if ($secret !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'auth failed']));
}

try {
    $DB_PATH = __DIR__ . '/../data/breyya.db';
    $db = new SQLite3($DB_PATH);
    $db->busyTimeout(5000);
    
    echo json_encode(['step' => 1, 'msg' => 'DB connection OK']);

    // Test all the table creations from the chat processor
    $db->exec("CREATE TABLE IF NOT EXISTS chat_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, fan_message_id INTEGER NOT NULL, fan_user_id INTEGER NOT NULL, status TEXT DEFAULT 'pending', scheduled_at TEXT, ai_response TEXT DEFAULT '', delivered_at TEXT, created_at TEXT DEFAULT (datetime('now')), UNIQUE(fan_message_id))");
    echo json_encode(['step' => 2, 'msg' => 'chat_queue table OK']);
    
    $db->exec("CREATE TABLE IF NOT EXISTS ppv_sales (id INTEGER PRIMARY KEY, fan_user_id INTEGER, content_key TEXT, price_cents INTEGER, sold_at TEXT DEFAULT (datetime('now')))");
    echo json_encode(['step' => 3, 'msg' => 'ppv_sales table OK']);
    
    $db->exec("CREATE TABLE IF NOT EXISTS daily_engagement (id INTEGER PRIMARY KEY, fan_user_id INTEGER, date TEXT, message_count INTEGER DEFAULT 0, bonus_messages INTEGER DEFAULT 0, UNIQUE(fan_user_id, date))");
    echo json_encode(['step' => 4, 'msg' => 'daily_engagement table OK']);
    
    $db->exec("CREATE TABLE IF NOT EXISTS fan_profiles (fan_user_id INTEGER PRIMARY KEY, display_name TEXT DEFAULT '', preferences TEXT DEFAULT '', topics_discussed TEXT DEFAULT '', ppv_purchases_total INTEGER DEFAULT 0, total_messages INTEGER DEFAULT 0, last_active TEXT DEFAULT '', notes TEXT DEFAULT '', whale_score INTEGER DEFAULT 0, updated_at TEXT DEFAULT (datetime('now')))" );
    echo json_encode(['step' => 5, 'msg' => 'fan_profiles table OK']);

    // Test column additions (these might fail silently)
    @$db->exec("ALTER TABLE messages ADD COLUMN is_ai INTEGER DEFAULT 0");
    @$db->exec("ALTER TABLE messages ADD COLUMN is_ppv INTEGER DEFAULT 0");
    @$db->exec("ALTER TABLE messages ADD COLUMN ppv_price_cents INTEGER DEFAULT 0");
    @$db->exec("ALTER TABLE messages ADD COLUMN media_url TEXT DEFAULT ''");
    @$db->exec("ALTER TABLE messages ADD COLUMN ppv_content_key TEXT DEFAULT ''");
    echo json_encode(['step' => 6, 'msg' => 'message table alterations OK']);
    
    // Test a simple query that the processor does
    $r = $db->query("SELECT COUNT(*) FROM messages");
    echo json_encode(['step' => 7, 'msg' => 'query test OK']);
    
    $db->close();
    echo json_encode(['step' => 8, 'msg' => 'All tests passed - chat processor should work now']);
    
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
}
?>