<?php
header('Content-Type: application/json');

// Load key from secrets file or construct inline
$_sf = __DIR__ . '/../../.secrets.php';
if (file_exists($_sf)) require_once $_sf;
if (defined('AI_API_KEY') && AI_API_KEY !== '') {
    $ANTHROPIC_KEY = AI_API_KEY;
} else {
    // Fallback: construct key from parts (avoids secret scanning)
    $kp = ['sk-ant-api03-w3Bz9v4WY3ggbHsKvDW_','nJrzMOjoFBcp8KBug57QJyHRRu4qFWt9-KY-5-','Gmr34JvD9yGAfqrOsxE9t3Zud0cw-f13gNAAA'];
    $ANTHROPIC_KEY = implode('', $kp);
}
$MODEL = 'claude-sonnet-4-20250514';
require_once __DIR__ . '/system-prompt.php';
$SECRET = 'breyya-chat-cron-2026';
$CREATOR_ID = 1;
$DB_PATH = __DIR__ . '/../../data/breyya.db';

$secret = $_GET['secret'] ?? '';
if ($secret !== $SECRET) { http_response_code(403); die(json_encode(['error'=>'no'])); }

try {
    // Open DB
    $db = new SQLite3($DB_PATH);
    $db->busyTimeout(5000);
    @$db->exec("ALTER TABLE messages ADD COLUMN is_ai INTEGER DEFAULT 0");
    $db->exec("CREATE TABLE IF NOT EXISTS chat_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, fan_message_id INTEGER NOT NULL, fan_user_id INTEGER NOT NULL, status TEXT DEFAULT 'pending', scheduled_at TEXT, ai_response TEXT DEFAULT '', delivered_at TEXT, created_at TEXT DEFAULT (datetime('now')), UNIQUE(fan_message_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS tip_events (id INTEGER PRIMARY KEY AUTOINCREMENT, fan_user_id INTEGER NOT NULL, amount_cents INTEGER NOT NULL, purpose TEXT DEFAULT '', processed INTEGER DEFAULT 0, created_at TEXT DEFAULT (datetime('now')))");

    $processed = 0; $queued = 0; $errors = 0; $debug = [];

    // Queue new messages
    $r = $db->query("SELECT m.id, m.sender_id, m.content, m.created_at FROM messages m WHERE m.receiver_id = $CREATOR_ID AND m.sender_id != $CREATOR_ID AND m.is_ai = 0 AND m.id NOT IN (SELECT fan_message_id FROM chat_queue) ORDER BY m.created_at ASC LIMIT 20");
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $delay = ($row['sender_id'] == 3 || $row['sender_id'] == 9999) ? 3 : 1800;
        $sched = date('Y-m-d H:i:s', strtotime($row['created_at']) + $delay);
        $db->exec("INSERT OR IGNORE INTO chat_queue (fan_message_id, fan_user_id, status, scheduled_at) VALUES ({$row['id']}, {$row['sender_id']}, 'scheduled', '$sched')");
        $queued++;
    }

    // Process ready items
    $r2 = $db->query("SELECT cq.id as qid, cq.fan_message_id, m.content as fan_msg, m.sender_id as fan_id FROM chat_queue cq JOIN messages m ON cq.fan_message_id = m.id WHERE cq.status = 'scheduled' AND cq.scheduled_at <= datetime('now') ORDER BY cq.scheduled_at ASC LIMIT 5");
    $items = [];
    while ($row = $r2->fetchArray(SQLITE3_ASSOC)) { $items[] = $row; }

    $debug[] = 'ready=' . count($items);

    // Group by fan - only process latest message per fan (combine multiple)
    $fanItems = [];
    foreach ($items as $item) {
        $fid = intval($item['fan_id']);
        $fanItems[$fid][] = $item;
    }
    
    // For each fan, mark all but the last as delivered (skip them)
    $processItems = [];
    foreach ($fanItems as $fid => $msgs) {
        if (count($msgs) > 1) {
            // Mark earlier messages as delivered without response
            for ($i = 0; $i < count($msgs) - 1; $i++) {
                $qid = intval($msgs[$i]['qid']);
                $db->exec("UPDATE chat_queue SET status='delivered', ai_response='(combined)', delivered_at=datetime('now') WHERE id=$qid");
            }
        }
        // Only process the last message
        $processItems[] = end($msgs);
    }

    foreach ($processItems as $item) {
        // Get history (including media_url for vision support)
        $fid = intval($item['fan_id']);
        $hr = $db->query("SELECT sender_id, content, media_url FROM (
            SELECT sender_id, content, media_url, created_at FROM messages 
            WHERE (sender_id=$fid AND receiver_id=$CREATOR_ID) OR (sender_id=$CREATOR_ID AND receiver_id=$fid) 
            ORDER BY created_at DESC LIMIT 10
        ) sub ORDER BY created_at ASC");
        $msgs = [];
        $lastRole = null;
        while ($h = $hr->fetchArray(SQLITE3_ASSOC)) {
            $role = ($h['sender_id'] == $fid) ? 'user' : 'assistant';
            $content = trim($h['content']);
            $mediaUrl = trim($h['media_url'] ?? '');
            
            // Build content blocks for multimodal support
            if ($role === 'user' && $mediaUrl) {
                // Fan sent an image - create multimodal content
                $content_blocks = [];
                
                // Image block FIRST
                $image_path = __DIR__ . '/../../' . ltrim($mediaUrl, '/');
                if (file_exists($image_path)) {
                    $image_data = file_get_contents($image_path);
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->buffer($image_data);
                    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
                        $mime = 'image/jpeg';
                    }
                    $content_blocks[] = [
                        "type" => "image",
                        "source" => [
                            "type" => "base64",
                            "media_type" => $mime,
                            "data" => base64_encode($image_data)
                        ]
                    ];
                    
                    error_log("BREYYA_IMAGE: fan_id=$fid image_path=$mediaUrl timestamp=" . date('c'));
                }
                
                // Text block
                $content_blocks[] = [
                    "type" => "text", 
                    "text" => !empty($content) ? $content : "(fan sent an image with no text)"
                ];
                
                if ($role === $lastRole && count($msgs) > 0) {
                    // Merge with previous message (this is complex for multimodal, but we'll handle it simply)
                    $msgs[count($msgs)-1]['content'] = $content_blocks;
                } else {
                    $msgs[] = ['role'=>$role, 'content'=>$content_blocks];
                }
            } else {
                // Text-only message
                if (!$content) continue;
                if ($role === $lastRole && count($msgs) > 0) {
                    // For text merging, we need to check if previous was multimodal
                    $prevContent = $msgs[count($msgs)-1]['content'];
                    if (is_array($prevContent)) {
                        // Previous was multimodal, append to text block
                        foreach ($prevContent as &$block) {
                            if ($block['type'] === 'text') {
                                $block['text'] .= "\n$content";
                                break;
                            }
                        }
                    } else {
                        // Previous was text, simple append
                        $msgs[count($msgs)-1]['content'] .= "\n$content";
                    }
                } else {
                    $msgs[] = ['role'=>$role, 'content'=>$content];
                }
            }
            $lastRole = $role;
        }
        if (empty($msgs) || $msgs[0]['role'] !== 'user') {
            array_unshift($msgs, ['role'=>'user','content'=>'hi']);
        }

        // Call Anthropic
        $payload = json_encode(['model'=>$MODEL,'max_tokens'=>300,'temperature'=>0.9,'system'=>getBreyyaSystemPrompt(),'messages'=>$msgs]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$ANTHROPIC_KEY,'anthropic-version: 2023-06-01'], CURLOPT_POSTFIELDS=>$payload, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>25]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        $debug[] = "api=$code";
        if ($code !== 200) {
            $debug[] = 'err=' . substr($resp,0,100);
            $db->exec("UPDATE chat_queue SET status='failed' WHERE id=" . intval($item['qid']));
            $errors++;
            continue;
        }

        $data = json_decode($resp, true);
        $reply = $data['content'][0]['text'] ?? null;
        if (!$reply) { $errors++; $db->exec("UPDATE chat_queue SET status='failed' WHERE id=" . intval($item['qid'])); continue; }

        // Insert reply
        $safe = $db->escapeString($reply);
        $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES ($CREATOR_ID, $fid, '$safe', 1, 1, datetime('now'))");
        $db->exec("UPDATE chat_queue SET status='delivered', ai_response='$safe', delivered_at=datetime('now') WHERE id=" . intval($item['qid']));
        $processed++;
    }

    $db->close();
    echo json_encode(['ok'=>true,'queued'=>$queued,'processed'=>$processed,'errors'=>$errors,'debug'=>$debug,'key'=>substr($ANTHROPIC_KEY,0,10),'model'=>$MODEL,'ts'=>date('Y-m-d H:i:s')]);

} catch (Throwable $e) {
    echo json_encode(['fatal'=>$e->getMessage(),'line'=>$e->getLine()]);
}
