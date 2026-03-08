<?php
/**
 * Breyya.com — Chat Queue Processor
 * 
 * Called by cron every 1-5 minutes.
 * Picks up fan messages that need AI responses, generates them via OpenAI,
 * and schedules delivery with realistic delays.
 * 
 * GET /api/chat/process.php?secret=<CHAT_CRON_SECRET>
 * 
 * Flow:
 * 1. Find fan messages to creator with no AI response yet
 * 2. For each, check if scheduled delivery time has passed
 * 3. If ready: generate response via OpenAI, insert as message from creator
 * 4. If not ready yet: skip (will be processed next cron run)
 */

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

// Ensure is_ai column exists in messages table (migration)
$db = getDB();
@$db->exec("ALTER TABLE messages ADD COLUMN is_ai INTEGER DEFAULT 0"); // Silently ignore if column already exists
$db->close();

// Ensure queue table exists
$db = getDB();
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
$db->close();

$processed = 0;
$scheduled = 0;
$errors = 0;

// Step 1: Find new fan messages that aren't in the queue yet
$db = getDB();
$stmt = $db->prepare("
    SELECT m.id, m.sender_id, m.content, m.created_at
    FROM messages m
    WHERE m.receiver_id = :creator_id
      AND m.sender_id != :creator_id
      AND m.is_ai = 0
      AND m.id NOT IN (SELECT fan_message_id FROM chat_queue)
    ORDER BY m.created_at ASC
    LIMIT 20
");
$stmt->bindValue(':creator_id', CREATOR_USER_ID, SQLITE3_INTEGER);
$result = $stmt->execute();

$newMessages = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $newMessages[] = $row;
}

// Add new messages to queue with scheduled delivery times
foreach ($newMessages as $msg) {
    $delay = calculateDelay();
    $scheduledAt = date('Y-m-d H:i:s', strtotime($msg['created_at']) + $delay);

    $ins = $db->prepare("INSERT OR IGNORE INTO chat_queue (fan_message_id, fan_user_id, status, scheduled_at) VALUES (:mid, :uid, 'scheduled', :sched)");
    $ins->bindValue(':mid', $msg['id'], SQLITE3_INTEGER);
    $ins->bindValue(':uid', $msg['sender_id'], SQLITE3_INTEGER);
    $ins->bindValue(':sched', $scheduledAt, SQLITE3_TEXT);
    $ins->execute();
    $scheduled++;

    // Update fan profile
    incrementFanMessageCount($msg['sender_id']);
}

// Step 2: Process scheduled items whose delivery time has arrived
$stmt = $db->prepare("
    SELECT cq.*, m.content as fan_message, m.sender_id as fan_id
    FROM chat_queue cq
    JOIN messages m ON cq.fan_message_id = m.id
    WHERE cq.status = 'scheduled'
      AND cq.scheduled_at <= datetime('now')
    ORDER BY cq.scheduled_at ASC
    LIMIT 10
");
$result = $stmt->execute();

$readyItems = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $readyItems[] = $row;
}
$db->close();

foreach ($readyItems as $item) {
    $fanId = $item['fan_id'];

    // Get fan profile and classification
    $profile = getFanProfile($fanId);
    $classification = classifyFan($fanId);
    $convoCount = getConversationCount($fanId);

    // Load conversation history (last 50 messages)
    $db = getDB();
    $histStmt = $db->prepare("
        SELECT sender_id, content, created_at
        FROM messages
        WHERE (sender_id = :fan AND receiver_id = :creator)
           OR (sender_id = :creator2 AND receiver_id = :fan2)
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $histStmt->bindValue(':fan', $fanId, SQLITE3_INTEGER);
    $histStmt->bindValue(':fan2', $fanId, SQLITE3_INTEGER);
    $histStmt->bindValue(':creator', CREATOR_USER_ID, SQLITE3_INTEGER);
    $histStmt->bindValue(':creator2', CREATOR_USER_ID, SQLITE3_INTEGER);
    $histResult = $histStmt->execute();

    $history = [];
    while ($row = $histResult->fetchArray(SQLITE3_ASSOC)) {
        $history[] = $row;
    }
    $db->close();

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
        $db = getDB();
        $upd = $db->prepare("UPDATE chat_queue SET status = 'failed' WHERE id = :id");
        $upd->bindValue(':id', $item['id'], SQLITE3_INTEGER);
        $upd->execute();
        $db->close();
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
    $db = getDB();

    $ins = $db->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES (:sid, :rid, :content, 1, 1, :created)");
    $ins->bindValue(':sid', CREATOR_USER_ID, SQLITE3_INTEGER);
    $ins->bindValue(':rid', $fanId, SQLITE3_INTEGER);
    $ins->bindValue(':content', $reply, SQLITE3_TEXT);
    $ins->bindValue(':created', $item['scheduled_at'], SQLITE3_TEXT);
    $ins->execute();

    // Insert second message if double
    if ($reply2) {
        $secondTime = date('Y-m-d H:i:s', strtotime($item['scheduled_at']) + mt_rand(3, 15));
        $ins2 = $db->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES (:sid, :rid, :content, 1, 1, :created)");
        $ins2->bindValue(':sid', CREATOR_USER_ID, SQLITE3_INTEGER);
        $ins2->bindValue(':rid', $fanId, SQLITE3_INTEGER);
        $ins2->bindValue(':content', $reply2, SQLITE3_TEXT);
        $ins2->bindValue(':created', $secondTime, SQLITE3_TEXT);
        $ins2->execute();
    }

    // Update queue status
    $upd = $db->prepare("UPDATE chat_queue SET status = 'delivered', ai_response = :resp, ai_response_2 = :resp2, delivered_at = datetime('now') WHERE id = :id");
    $upd->bindValue(':resp', $reply, SQLITE3_TEXT);
    $upd->bindValue(':resp2', $reply2, SQLITE3_TEXT);
    $upd->bindValue(':id', $item['id'], SQLITE3_INTEGER);
    $upd->execute();

    $db->close();
    $processed++;
}

echo json_encode([
    'ok' => true,
    'new_queued' => $scheduled,
    'processed' => $processed,
    'errors' => $errors,
    'timestamp' => date('Y-m-d H:i:s'),
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
