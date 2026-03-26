<?php
/**
 * Breyya.com — Chat Queue Processor (FIXED VERSION)
 * 
 * FIXES:
 * 1. Added proper error checking for prepare() calls
 * 2. Enabled PDO error reporting
 * 3. Added logging for SQL errors
 * 4. Graceful degradation on database errors
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/openai.php';
require_once __DIR__ . '/../lib/fan_profiles.php';

header('Content-Type: application/json');

// Auth check
$secret = $_GET['secret'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
if ($secret !== CHAT_CRON_SECRET) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Helper function to safely prepare statements with error checking
function safePrepare($db, $sql, $context = 'unknown') {
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        $errorInfo = $db->errorInfo();
        $error = "SQL prepare failed in context '$context': " . $errorInfo[2] . " (Code: " . $errorInfo[1] . ")";
        error_log($error);
        return ['error' => $error, 'stmt' => null];
    }
    return ['error' => null, 'stmt' => $stmt];
}

// Function to get database connection with error checking
function getDBSafely() {
    try {
        $db = getDB();
        // Enable error mode
        if (method_exists($db, 'setAttribute')) {
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return ['error' => null, 'db' => $db];
    } catch (Exception $e) {
        $error = "Database connection failed: " . $e->getMessage();
        error_log($error);
        return ['error' => $error, 'db' => null];
    }
}

// Initialize with error checking
$dbResult = getDBSafely();
if ($dbResult['error']) {
    echo json_encode(['fatal' => $dbResult['error'], 'line' => __LINE__]);
    exit;
}
$db = $dbResult['db'];

// Ensure is_ai column exists in messages table (migration)
try {
    @$db->exec("ALTER TABLE messages ADD COLUMN is_ai INTEGER DEFAULT 0"); // Silently ignore if column already exists
} catch (Exception $e) {
    error_log("Migration error: " . $e->getMessage());
}
$db = null;

// Ensure queue table exists
$dbResult = getDBSafely();
if ($dbResult['error']) {
    echo json_encode(['fatal' => $dbResult['error'], 'line' => __LINE__]);
    exit;
}
$db = $dbResult['db'];

try {
    $db->exec("CREATE TABLE IF NOT EXISTS chat_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        fan_message_id INTEGER NOT NULL,
        fan_user_id INTEGER NOT NULL,
        status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'scheduled', 'delivered', 'failed')),
        scheduled_at TEXT DEFAULT NULL,
        ai_response TEXT DEFAULT '',
        ai_response_2 TEXT DEFAULT '',
        delivered_at TEXT DEFAULT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        UNIQUE(fan_message_id)
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_chat_queue_status ON chat_queue(status)");
} catch (Exception $e) {
    $error = "Table creation failed: " . $e->getMessage();
    error_log($error);
    echo json_encode(['fatal' => $error, 'line' => __LINE__]);
    exit;
}
$db = null;

$processed = 0;
$scheduled = 0;
$errors = 0;
$debug_info = [];

// Step 1: Find new fan messages that aren't in the queue yet
$dbResult = getDBSafely();
if ($dbResult['error']) {
    echo json_encode(['fatal' => $dbResult['error'], 'line' => __LINE__]);
    exit;
}
$db = $dbResult['db'];

$prepResult = safePrepare($db, "
    SELECT m.id, m.sender_id, m.content, m.created_at
    FROM messages m
    WHERE m.receiver_id = :creator_id
      AND m.sender_id != :creator_id
      AND m.is_ai = 0
      AND m.id NOT IN (SELECT fan_message_id FROM chat_queue)
    ORDER BY m.created_at ASC
    LIMIT 20
", "select_new_messages");

if ($prepResult['error']) {
    $db = null;
    echo json_encode(['fatal' => $prepResult['error'], 'line' => __LINE__]);
    exit;
}

$stmt = $prepResult['stmt'];

try {
    $stmt->bindValue(':creator_id', CREATOR_USER_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $newMessages = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $newMessages[] = $row;
    }
    $debug_info[] = "Found " . count($newMessages) . " new messages";
} catch (Exception $e) {
    $error = "Execute failed on select_new_messages: " . $e->getMessage();
    error_log($error);
    $db = null;
    echo json_encode(['fatal' => $error, 'line' => __LINE__]);
    exit;
}

// Add new messages to queue with scheduled delivery times
foreach ($newMessages as $msg) {
    try {
        // Test fan (id 9999) gets instant responses for testing
        $delay = ($msg['sender_id'] == 9999) ? mt_rand(5, 15) : calculateDelay();
        $scheduledAt = date('Y-m-d H:i:s', strtotime($msg['created_at']) + $delay);

        $prepResult = safePrepare($db, "INSERT OR IGNORE INTO chat_queue (fan_message_id, fan_user_id, status, scheduled_at) VALUES (:mid, :uid, 'scheduled', :sched)", "insert_queue");
        
        if ($prepResult['error']) {
            $errors++;
            $debug_info[] = "Insert prepare failed for message " . $msg['id'];
            continue;
        }

        $ins = $prepResult['stmt'];
        $ins->bindValue(':mid', $msg['id'], SQLITE3_INTEGER);
        $ins->bindValue(':uid', $msg['sender_id'], SQLITE3_INTEGER);
        $ins->bindValue(':sched', $scheduledAt, SQLITE3_TEXT);
        $ins->execute();
        $scheduled++;

        // Update fan profile
        incrementFanMessageCount($msg['sender_id']);
    } catch (Exception $e) {
        $errors++;
        $debug_info[] = "Failed to queue message " . $msg['id'] . ": " . $e->getMessage();
        error_log("Queue insert error: " . $e->getMessage());
    }
}

// Step 2: Process scheduled items whose delivery time has arrived
$prepResult = safePrepare($db, "
    SELECT cq.*, m.content as fan_message, m.sender_id as fan_id
    FROM chat_queue cq
    JOIN messages m ON cq.fan_message_id = m.id
    WHERE cq.status = 'scheduled'
      AND cq.scheduled_at <= datetime('now')
    ORDER BY cq.scheduled_at ASC
    LIMIT 10
", "select_ready_items");

if ($prepResult['error']) {
    $db = null;
    echo json_encode(['fatal' => $prepResult['error'], 'line' => __LINE__]);
    exit;
}

$stmt = $prepResult['stmt'];

try {
    $result = $stmt->execute();
    $readyItems = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $readyItems[] = $row;
    }
    $debug_info[] = "Found " . count($readyItems) . " ready items";
} catch (Exception $e) {
    $error = "Execute failed on select_ready_items: " . $e->getMessage();
    error_log($error);
    $db = null;
    echo json_encode(['fatal' => $error, 'line' => __LINE__]);
    exit;
}

$db = null;

foreach ($readyItems as $item) {
    $fanId = $item['fan_id'];

    try {
        // Get fan profile and classification
        $profile = getFanProfile($fanId);
        $classification = classifyFan($fanId);
        $convoCount = getConversationCount($fanId);

        // Load conversation history (last 50 messages)
        $dbResult = getDBSafely();
        if ($dbResult['error']) {
            $errors++;
            $debug_info[] = "DB connection failed for fan $fanId";
            continue;
        }
        $db = $dbResult['db'];

        $prepResult = safePrepare($db, "
            SELECT sender_id, content, created_at
            FROM messages
            WHERE (sender_id = :fan AND receiver_id = :creator)
               OR (sender_id = :creator2 AND receiver_id = :fan2)
            ORDER BY created_at DESC
            LIMIT 50
        ", "select_history");

        if ($prepResult['error']) {
            $errors++;
            $debug_info[] = "History prepare failed for fan $fanId";
            $db = null;
            continue;
        }

        $histStmt = $prepResult['stmt'];
        $histStmt->bindValue(':fan', $fanId, SQLITE3_INTEGER);
        $histStmt->bindValue(':fan2', $fanId, SQLITE3_INTEGER);
        $histStmt->bindValue(':creator', CREATOR_USER_ID, SQLITE3_INTEGER);
        $histStmt->bindValue(':creator2', CREATOR_USER_ID, SQLITE3_INTEGER);
        $histResult = $histStmt->execute();

        $history = [];
        while ($row = $histResult->fetchArray(SQLITE3_ASSOC)) {
            $history[] = $row;
        }

        // Reverse to chronological order
        $history = array_reverse($history);

        // Build OpenAI messages array
        $messageCount = 0;
        $openaiMessages = [];
        foreach ($history as $h) {
            $role = ($h['sender_id'] == $fanId) ? 'user' : 'assistant';
            if ($role === 'user') $messageCount++;
            if (trim($h['content'])) {
                $openaiMessages[] = ['role' => $role, 'content' => $h['content']];
            }
        }

        // Build system prompt
        $systemPrompt = buildSystemPrompt($profile, $classification, $messageCount, $convoCount);

        // Call OpenAI
        $reply = callOpenAI($systemPrompt, $openaiMessages);

        if ($reply === null) {
            // Mark as failed
            $prepResult = safePrepare($db, "UPDATE chat_queue SET status = 'failed' WHERE id = :id", "update_failed");
            if (!$prepResult['error']) {
                $upd = $prepResult['stmt'];
                $upd->bindValue(':id', $item['id'], SQLITE3_INTEGER);
                $upd->execute();
            }
            $db = null;
            $errors++;
            continue;
        }

        // 85% single message, 15% double (split into two messages)
        $reply2 = '';
        if (mt_rand(1, 100) <= 15) {
            // Split: try to find a natural break point, or ask for a second message
            $secondReply = callOpenAI(
                $systemPrompt . "\n\nYou just sent: \"$reply\"\nNow send a SHORT follow-up message (1 sentence, like a second text in a row). Different thought or a continuation. Keep it natural like rapid-fire texting.",
                $openaiMessages
            );
            if ($secondReply) {
                $reply2 = $secondReply;
            }
        }

        // Insert AI response as message from creator
        $prepResult = safePrepare($db, "INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES (:sid, :rid, :content, 1, 1, :created)", "insert_message");
        if ($prepResult['error']) {
            $errors++;
            $debug_info[] = "Message insert prepare failed for fan $fanId";
            $db = null;
            continue;
        }

        $ins = $prepResult['stmt'];
        $ins->bindValue(':sid', CREATOR_USER_ID, SQLITE3_INTEGER);
        $ins->bindValue(':rid', $fanId, SQLITE3_INTEGER);
        $ins->bindValue(':content', $reply, SQLITE3_TEXT);
        $ins->bindValue(':created', $item['scheduled_at'], SQLITE3_TEXT);
        $ins->execute();

        // Insert second message if double
        if ($reply2) {
            $secondTime = date('Y-m-d H:i:s', strtotime($item['scheduled_at']) + mt_rand(3, 15));
            $prepResult = safePrepare($db, "INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES (:sid, :rid, :content, 1, 1, :created)", "insert_message_2");
            if (!$prepResult['error']) {
                $ins2 = $prepResult['stmt'];
                $ins2->bindValue(':sid', CREATOR_USER_ID, SQLITE3_INTEGER);
                $ins2->bindValue(':rid', $fanId, SQLITE3_INTEGER);
                $ins2->bindValue(':content', $reply2, SQLITE3_TEXT);
                $ins2->bindValue(':created', $secondTime, SQLITE3_TEXT);
                $ins2->execute();
            }
        }

        // Update queue status
        $prepResult = safePrepare($db, "UPDATE chat_queue SET status = 'delivered', ai_response = :resp, ai_response_2 = :resp2, delivered_at = datetime('now') WHERE id = :id", "update_delivered");
        if (!$prepResult['error']) {
            $upd = $prepResult['stmt'];
            $upd->bindValue(':resp', $reply, SQLITE3_TEXT);
            $upd->bindValue(':resp2', $reply2, SQLITE3_TEXT);
            $upd->bindValue(':id', $item['id'], SQLITE3_INTEGER);
            $upd->execute();
        }

        $db = null;
        $processed++;

    } catch (Exception $e) {
        $errors++;
        $debug_info[] = "Processing failed for fan $fanId: " . $e->getMessage();
        error_log("Processing error for fan $fanId: " . $e->getMessage());
        if (isset($db)) $db = null;
    }
}

echo json_encode([
    'ok' => true,
    'new_queued' => $scheduled,
    'processed' => $processed,
    'errors' => $errors,
    'debug_info' => $debug_info,
    'timestamp' => date('Y-m-d H:i:s'),
    'version' => 'fixed_v1'
]);

/**
 * Calculate realistic response delay in seconds
 * 15% instant (60-300s), 40% normal (900-2700s), 30% slow (7200-28800s), 15% next day (43200-86400s)
 */
function calculateDelay(): int {
    $roll = mt_rand(1, 100);

    if ($roll <= 15) {
        // Instant: 1-5 minutes
        return mt_rand(60, 300);
    } elseif ($roll <= 55) {
        // Normal: 15-45 minutes
        return mt_rand(900, 2700);
    } elseif ($roll <= 85) {
        // Slow: 2-8 hours
        return mt_rand(7200, 28800);
    } else {
        // Next day: 12-24 hours
        return mt_rand(43200, 86400);
    }
}
?>