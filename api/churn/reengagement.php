<?php
header('Content-Type: application/json');

$SECRET = 'breyya-chat-cron-2026';
$CREATOR_ID = 1;
$DB_PATH = __DIR__ . '/../../data/breyya.db';

if (($_GET['secret'] ?? '') !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'no']));
}

$db = new SQLite3($DB_PATH);
$db->busyTimeout(5000);
$db->exec("CREATE TABLE IF NOT EXISTS churn_reengagement (id INTEGER PRIMARY KEY AUTOINCREMENT, fan_user_id INTEGER NOT NULL, sent_at TEXT DEFAULT (datetime('now')), message_text TEXT DEFAULT '')");

// Re-engagement message templates — warm, personal, not spammy
$templates = [
    "hey stranger 👀 been thinking about you lately… hope you're doing ok 🥺",
    "omg i was just looking through my photos and thought of you 😊 how have you been??",
    "heyy it's been a while!! i've been posting some new stuff lately… missed chatting with you 💕",
    "hi you 🥺 haven't heard from you in a bit… everything good? i was just thinking about you",
    "hey!! i know it's been a minute but i literally just thought of you 😂 how's life?",
    "hiiii been a while!! i've been up to so much lately… you should catch up with me 😏",
];

// Find churned fans: 5+ messages, last active 14-60 days ago, no re-engagement this month
$churned = $db->query(" 
    SELECT fp.fan_user_id FROM fan_profiles fp
    WHERE fp.total_messages >= 5
      AND fp.last_active IS NOT NULL
      AND fp.last_active != ''
      AND julianday('now') - julianday(fp.last_active) BETWEEN 14 AND 60
      AND fp.fan_user_id NOT IN (
          SELECT fan_user_id FROM churn_reengagement WHERE strftime('%Y-%m', sent_at) = strftime('%Y-%m', 'now')
      )
    ORDER BY fp.whale_score DESC, fp.total_messages DESC
    LIMIT 5
");

$sent = 0;
$errors = 0;

while ($fan = $churned->fetchArray(SQLITE3_ASSOC)) {
    $fid = intval($fan['fan_user_id']);

    // Pick a template based on fan_user_id for variety
    $msg = $templates[$fid % count($templates)];
    $safeMsgForQueue = SQLite3::escapeString($msg);
    $safeMsgForLog = SQLite3::escapeString($msg);

    // Check if there's already a pending message for this fan
    $pending = $db->querySingle("SELECT COUNT(*) FROM chat_queue WHERE fan_user_id = $fid AND status = 'pending'");
    if ($pending > 0) continue;

    // Queue the re-engagement message as if it came from the creator
    // We insert directly into messages table as an outbound message
    $inserted = $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES ($CREATOR_ID, $fid, '$safeMsgForQueue', 1, 1, datetime('now'))");
    if ($inserted) {
        // Log to churn table
        $db->exec("INSERT OR IGNORE INTO churn_reengagement (fan_user_id, message_text, sent_at) VALUES ($fid, '$safeMsgForLog', datetime('now'))");
        $sent++;
    } else {
        $errors++;
    }
}

$db->close();
echo json_encode([
    'ok' => true,
    'churned_fans_messaged' => $sent,
    'errors' => $errors,
    'ts' => date('Y-m-d H:i:s')
]);
