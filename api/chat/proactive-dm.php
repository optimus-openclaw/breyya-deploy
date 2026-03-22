<?php
header('Content-Type: application/json');

// Load key from secrets file or construct inline
$_sf = __DIR__ . '/../.secrets.php';
if (file_exists($_sf)) require_once $_sf;
if (defined('AI_API_KEY') && AI_API_KEY !== '') {
    $ANTHROPIC_KEY = AI_API_KEY;
} elseif (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '') {
    // Fallback to OPENAI_API_KEY if AI_API_KEY not found
    $ANTHROPIC_KEY = OPENAI_API_KEY;
} else {
    // Key must be set in .secrets.php — never hardcode here
    $ANTHROPIC_KEY = '';
}
$MODEL = 'claude-sonnet-4-20250514';
require_once __DIR__ . '/system-prompt.php';
require_once __DIR__ . '/../lib/database.php';
$SECRET = 'breyya-chat-cron-2026';
$CREATOR_ID = 1;

// Auth check
$secret = $_GET['secret'] ?? '';
if ($secret !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'no']));
}

// Parameters
$fanId = intval($_GET['fan_id'] ?? 0);
$context = $_GET['context'] ?? '';
$validContexts = ['birthday', 'followup', 'whale_checkin', 'reengagement'];

if (!$fanId || !in_array($context, $validContexts)) {
    http_response_code(400);
    die(json_encode(['error' => 'invalid_params', 'fan_id' => $fanId, 'context' => $context]));
}

try {
    $db = getDB();

    // Load fan profile
    $profile = $db->querySingle("SELECT * FROM fan_profiles WHERE fan_user_id = $fanId", true);
    if (!$profile) {
        // Create basic profile for this fan if doesn't exist
        $db->exec("INSERT OR IGNORE INTO fan_profiles (fan_user_id, conversation_stage, first_message_date) VALUES ($fanId, 'new', datetime('now'))");
        $profile = $db->querySingle("SELECT * FROM fan_profiles WHERE fan_user_id = $fanId", true);
    }

    // Build fan context
    function buildFanContext($profile) {
        $ctx = "[FAN MEMORY — PRIVATE, NEVER REFERENCE THIS SECTION DIRECTLY]\n\n";
        if (!empty($profile['display_name'])) $ctx .= "Name: {$profile['display_name']}\n";
        if (!empty($profile['birthday'])) $ctx .= "Birthday: {$profile['birthday']}\n";
        if (!empty($profile['occupation'])) $ctx .= "Occupation: {$profile['occupation']}\n";
        if (!empty($profile['location'])) $ctx .= "Location: {$profile['location']}\n";
        if (!empty($profile['interests']) && $profile['interests'] !== '[]') $ctx .= "Interests: {$profile['interests']}\n";
        if (!empty($profile['emotional_patterns'])) $ctx .= "Emotional patterns: {$profile['emotional_patterns']}\n";
        if (!empty($profile['what_works'])) $ctx .= "What works: {$profile['what_works']}\n";
        if (!empty($profile['callback_inventory']) && $profile['callback_inventory'] !== '[]') $ctx .= "Remember: {$profile['callback_inventory']}\n";
        $ctx .= "Conversation stage: {$profile['conversation_stage']}\n";
        $ctx .= "Total messages: {$profile['total_messages']}\n";
        $ctx .= "Whale tier: {$profile['whale_tier']} (score: {$profile['whale_score']})\n";
        return $ctx;
    }

    $fanContext = buildFanContext($profile);

    // Build context-specific system instructions
    $systemContext = '';
    switch ($context) {
        case 'birthday':
            $systemContext = "\n[SYSTEM: Send a proactive birthday message to this fan. Their birthday is coming up or today. Reach out warmly - don't wait for them to message first. Make it feel personal and special.]";
            break;
        case 'followup':
            $systemContext = "\n[SYSTEM: Send a proactive follow-up message to this fan. You had a good conversation recently or they mentioned something worth following up on. Reach out naturally - don't wait for them to message first.]";
            break;
        case 'whale_checkin':
            $systemContext = "\n[SYSTEM: Send a proactive check-in message to this high-value fan (whale). They spend well but haven't messaged in a while. Reach out warmly to re-engage them - don't wait for them to message first. Make them feel valued.]";
            break;
        case 'reengagement':
            $systemContext = "\n[SYSTEM: Send a proactive re-engagement message to this fan who has been quiet for a while. Reach out naturally to restart the conversation - don't wait for them to message first. Be warm and inviting.]";
            break;
    }

    // Get recent message history to inform the proactive message
    $recentMessages = [];
    $r = $db->query("SELECT sender_id, content FROM messages 
                     WHERE (sender_id = $fanId AND receiver_id = $CREATOR_ID) 
                        OR (sender_id = $CREATOR_ID AND receiver_id = $fanId) 
                     ORDER BY created_at DESC LIMIT 5");
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $role = ($row['sender_id'] == $fanId) ? 'user' : 'assistant';
        $recentMessages[] = ['role' => $role, 'content' => trim($row['content'])];
    }

    // If no history, create a simple conversation starter
    if (empty($recentMessages)) {
        $recentMessages = [['role' => 'user', 'content' => '(no previous conversation)']];
    } else {
        // Reverse to chronological order
        $recentMessages = array_reverse($recentMessages);
    }

    // Build system prompt with fan context and proactive instruction
    $systemPrompt = getBreyyaSystemPrompt('', $fanContext) . $systemContext;

    // Call Anthropic API
    $payload = json_encode([
        'model' => $MODEL,
        'max_tokens' => 200,
        'temperature' => 0.8,
        'system' => $systemPrompt,
        'messages' => $recentMessages
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $ANTHROPIC_KEY,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25
    ]);
    
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        http_response_code(500);
        die(json_encode(['error' => 'api_failed', 'code' => $code, 'response' => substr($resp, 0, 200)]));
    }

    $data = json_decode($resp, true);
    $reply = $data['content'][0]['text'] ?? null;
    if (!$reply) {
        http_response_code(500);
        die(json_encode(['error' => 'no_reply', 'data' => $data]));
    }

    // Insert the proactive message into the database
    $safeReply = $db->escapeString($reply);
    $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) 
               VALUES ($CREATOR_ID, $fanId, '$safeReply', 1, 1, datetime('now'))");
    $messageId = $db->lastInsertRowID();

    // Log the proactive DM activity (optional - for analytics)
    $db->exec("INSERT OR IGNORE INTO proactive_dms (fan_user_id, context, message_id, sent_at) 
               VALUES ($fanId, '$context', $messageId, datetime('now'))");

    $db->close();

    echo json_encode([
        'success' => true,
        'fan_id' => $fanId,
        'context' => $context,
        'message' => $reply,
        'message_id' => $messageId,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'error' => 'exception',
        'message' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
}

// Create proactive_dms table on first run (safe migration)
try {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS proactive_dms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        fan_user_id INTEGER NOT NULL,
        context TEXT NOT NULL,
        message_id INTEGER,
        sent_at TEXT DEFAULT (datetime('now'))
    )");
    $db->close();
} catch (Exception $e) {
    // Ignore table creation errors
}