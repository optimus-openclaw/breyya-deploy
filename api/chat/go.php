<?php
header('Content-Type: application/json');

// Load key from secrets file or construct inline
$_sf = __DIR__ . '/../../.secrets.php';
if (file_exists($_sf)) require_once $_sf;
if (defined('AI_API_KEY') && AI_API_KEY !== '') {
    $ANTHROPIC_KEY = AI_API_KEY;
} else {
    // Fallback: construct key from parts (avoids secret scanning)
    // Key loaded from .secrets.php
    $ANTHROPIC_KEY = defined("AI_API_KEY") ? AI_API_KEY : "";
}
$MODEL = 'claude-sonnet-4-20250514';
// Ollama routing config
define("OLLAMA_URL", "https://optimus-macmini.taila072b7.ts.net");
define("OLLAMA_MODEL", "dolphin-llama3");
// Fan IDs enabled for Ollama explicit routing (test accounts first)
$OLLAMA_ENABLED_FANS = [3, 4]; // BigTipper69, CuteGuy42

require_once __DIR__ . '/system-prompt.php';
$SECRET = 'breyya-chat-cron-2026';
$CREATOR_ID = 1;
$DB_PATH = __DIR__ . '/../../data/breyya.db';

$secret = $_GET['secret'] ?? '';
if ($secret !== $SECRET) { http_response_code(403); die(json_encode(['error'=>'no'])); }

try {
    // Concurrency lock — prevent duplicate processing
    $lockFile = __DIR__ . "/../../data/.go-lock";
    $lockFp = fopen($lockFile, "w");
    if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
        echo json_encode(["ok"=>true,"skipped"=>"already running"]);
        exit;
    }

    // Open DB
    $db = new SQLite3($DB_PATH);
    $db->busyTimeout(5000);
    @$db->exec("ALTER TABLE messages ADD COLUMN is_ai INTEGER DEFAULT 0");
    @$db->exec("ALTER TABLE messages ADD COLUMN message_type TEXT DEFAULT 'text'"); // For audio/text distinction
    @$db->exec("ALTER TABLE fan_profiles ADD COLUMN sexting_session_active INTEGER DEFAULT 0");
    $db->exec("CREATE TABLE IF NOT EXISTS chat_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, fan_message_id INTEGER NOT NULL, fan_user_id INTEGER NOT NULL, status TEXT DEFAULT 'pending', scheduled_at TEXT, ai_response TEXT DEFAULT '', delivered_at TEXT, created_at TEXT DEFAULT (datetime('now')), UNIQUE(fan_message_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS tip_events (id INTEGER PRIMARY KEY AUTOINCREMENT, fan_user_id INTEGER NOT NULL, amount_cents INTEGER NOT NULL, purpose TEXT DEFAULT '', processed INTEGER DEFAULT 0, created_at TEXT DEFAULT (datetime('now')))");
    
    // Create fan_profiles table
    $db->exec("CREATE TABLE IF NOT EXISTS fan_profiles (
        fan_id INTEGER PRIMARY KEY,
        display_name TEXT DEFAULT '',
        age INTEGER,
        occupation TEXT DEFAULT '',
        location TEXT DEFAULT '',
        timezone TEXT DEFAULT '',
        relationship_status TEXT DEFAULT 'unknown',
        interests TEXT DEFAULT '[]',
        personality_notes TEXT DEFAULT '',
        emotional_patterns TEXT DEFAULT '',
        what_works TEXT DEFAULT '',
        content_preferences TEXT DEFAULT '[]',
        callback_inventory TEXT DEFAULT '[]',
        conversation_stage TEXT DEFAULT 'new',
        total_messages INTEGER DEFAULT 0,
        whale_score INTEGER DEFAULT 0,
        whale_tier TEXT DEFAULT 'casual',
        total_lifetime_spend REAL DEFAULT 0,
        tip_total REAL DEFAULT 0,
        ppv_total_purchased INTEGER DEFAULT 0,
        ladder_position INTEGER DEFAULT 1,
        highest_ppv_price_paid REAL DEFAULT 0,
        welcome_sequence_complete INTEGER DEFAULT 0,
        onboarding_step INTEGER DEFAULT 1,
        birthday TEXT DEFAULT '',
        life_events TEXT DEFAULT '[]',
        first_message_date TEXT,
        last_message_date TEXT,
        messages_today INTEGER DEFAULT 0,
        attention_offset_hours REAL DEFAULT 0,
        prompt_injection_attempts INTEGER DEFAULT 0,
        flagged_for_review INTEGER DEFAULT 0,
        banned INTEGER DEFAULT 0,
        updated_at TEXT DEFAULT (datetime('now'))
    )");

    // Load or create fan profile
    function loadFanProfile($db, $fanId) {
        $stmt = $db->prepare("SELECT * FROM fan_profiles WHERE fan_id = :fid");
        if (!$stmt) { error_log("BREYYA_ERROR: fan_profiles prepare failed: " . $db->lastErrorMsg()); return ["fan_id" => $fanId, "display_name" => "", "conversation_stage" => "new", "onboarding_step" => 1]; }
        $stmt->bindValue(':fid', $fanId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $profile = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$profile) {
            // New fan — create profile
            $offset = round(mt_rand(0, 300) / 100, 1); // 0-3 hour random offset
            $db->exec("INSERT INTO fan_profiles (fan_id, first_message_date, attention_offset_hours) VALUES ($fanId, datetime('now'), $offset)");
            $profile = ['fan_id' => $fanId, 'display_name' => '', 'conversation_stage' => 'new', 'onboarding_step' => 1];
        }
        
        return $profile;
    }

    // Build fan context string for system prompt injection
    function buildFanContext($profile) {
        $ctx = "[FAN MEMORY — PRIVATE, NEVER REFERENCE THIS SECTION DIRECTLY]\n\n";
        if ($profile['display_name']) $ctx .= "Name: {$profile['display_name']}\n";
        if ($profile['birthday']) $ctx .= "Birthday: {$profile['birthday']}\n";
        if ($profile['occupation']) $ctx .= "Occupation: {$profile['occupation']}\n";
        if ($profile['location']) $ctx .= "Location: {$profile['location']}\n";
        if ($profile['interests'] && $profile['interests'] !== '[]') $ctx .= "Interests: {$profile['interests']}\n";
        if ($profile['emotional_patterns']) $ctx .= "Emotional patterns: {$profile['emotional_patterns']}\n";
        if ($profile['what_works']) $ctx .= "What works: {$profile['what_works']}\n";
        if ($profile['content_preferences'] && $profile['content_preferences'] !== '[]') $ctx .= "Content preferences: {$profile['content_preferences']}\n";
        if ($profile['callback_inventory'] && $profile['callback_inventory'] !== '[]') $ctx .= "Remember: {$profile['callback_inventory']}\n";
        $ctx .= "Conversation stage: {$profile['conversation_stage']}\n";
        $ctx .= "Total messages: {$profile['total_messages']}\n";
        $ctx .= "Whale tier: {$profile['whale_tier']} (score: {$profile['whale_score']})\n";
        $ctx .= "Ladder position: {$profile['ladder_position']}\n";
        if ($profile['welcome_sequence_complete']) {
            $ctx .= "Welcome sequence: complete\n";
        } else {
            $ctx .= "Welcome sequence: step {$profile['onboarding_step']} of 5 — follow the welcome flow\n";
        }
        return $ctx;
    }

    function updateFanProfile($db, $fanId, $fanMessage, $aiResponse) {
        // UPSERT: Create profile row if it doesn't exist
        $exists = $db->querySingle("SELECT fan_id FROM fan_profiles WHERE fan_id = $fanId");
        if (!$exists) {
            $db->exec("INSERT INTO fan_profiles (fan_id, first_message_date, updated_at) VALUES ($fanId, datetime('now'), datetime('now'))");
        }
        
        // Increment message count
        $db->exec("UPDATE fan_profiles SET total_messages = total_messages + 1, last_message_date = datetime('now'), messages_today = messages_today + 1, updated_at = datetime('now') WHERE fan_id = $fanId");
        
        // ========== EXTRACTION: Scan fan message for personal info ==========
        $msg = $fanMessage; // Keep original case for name extraction
        $msgLower = strtolower($fanMessage);
        
        // 1. Name: "I'm Jake" / "my name is Jake" / "call me Jake" / just "Jake" if it's a one-word reply to "what's your name?"
        if (preg_match("/(?:i'm|i am|my name is|name's|call me|they call me|it's|its)\s+([A-Z][a-z]{1,15})/i", $msg, $m)) {
            $name = SQLite3::escapeString(ucfirst(strtolower(trim($m[1]))));
            $db->exec("UPDATE fan_profiles SET display_name = '$name', updated_at = datetime('now') WHERE fan_id = $fanId AND (display_name = '' OR display_name IS NULL)");
        } elseif (preg_match("/^([A-Z][a-z]{1,15})$/", trim($msg), $m)) {
            // Single word capitalized reply — likely a name response
            $name = SQLite3::escapeString(trim($m[1]));
            $current = $db->querySingle("SELECT display_name FROM fan_profiles WHERE fan_id = $fanId");
            if (empty($current)) {
                $db->exec("UPDATE fan_profiles SET display_name = '$name', updated_at = datetime('now') WHERE fan_id = $fanId");
            }
        }
        
        // 2. Age: "I'm 34" / "I am 28 years old" / "just turned 30"
        if (preg_match("/(?:i'm|i am|im)\s+(\d{2})\b|(\d{2})\s*(?:years?\s*old|yr|yrs)|just turned\s+(\d{2})/i", $msgLower, $m)) {
            $age = intval($m[1] ?: $m[2] ?: $m[3]);
            if ($age >= 18 && $age <= 99) {
                $db->exec("UPDATE fan_profiles SET age = $age, updated_at = datetime('now') WHERE fan_id = $fanId");
            }
        }
        
        // 3. Location: "I'm from Texas" / "I live in LA" / "here in NYC" / "I'm in Portland"
        if (preg_match("/(?:from|live in|i'm in|im in|here in|based in|located in|staying in)\s+([A-Za-z\s\.]{2,30})/i", $msg, $m)) {
            $loc = SQLite3::escapeString(trim($m[1]));
            if (strlen($loc) >= 2) {
                $db->exec("UPDATE fan_profiles SET location = '$loc', updated_at = datetime('now') WHERE fan_id = $fanId");
            }
        }
        
        // 4. Job/occupation: "I work at/in/as" / "I'm a nurse" / "I do construction"
        if (preg_match("/(?:i work (?:at|in|for|as)|i'm a|im a|i am a|i do|my job is|i'm an|im an)\s+([A-Za-z\s]{2,40})/i", $msg, $m)) {
            $job = SQLite3::escapeString(trim($m[1]));
            $db->exec("UPDATE fan_profiles SET occupation = '$job', updated_at = datetime('now') WHERE fan_id = $fanId");
        }
        
        // 5. Relationship: "I'm single" / "just got divorced" / "have a girlfriend" / "married"
        if (preg_match("/\b(single|divorced|married|separated|engaged|in a relationship|have a girlfriend|have a wife|got dumped|just broke up|going through a breakup)\b/i", $msgLower, $m)) {
            $rel = SQLite3::escapeString(strtolower(trim($m[1])));
            $db->exec("UPDATE fan_profiles SET relationship_status = '$rel', updated_at = datetime('now') WHERE fan_id = $fanId");
        }
        
        // 6. Birthday: "my birthday is March 15" / "born on June 3rd" / "bday is 4/20"
        if (preg_match("/(?:birthday|bday|born on|born)\s*(?:is\s*)?(\w+\s+\d{1,2}|\d{1,2}\/\d{1,2})/i", $msgLower, $m)) {
            $bday = SQLite3::escapeString(trim($m[1]));
            $db->exec("UPDATE fan_profiles SET birthday = '$bday', updated_at = datetime('now') WHERE fan_id = $fanId");
        }
        
        // 7. Hobbies/interests: "I love hiking" / "I'm into cars" / "I like video games"
        if (preg_match("/(?:i love|i like|i enjoy|i'm into|im into|my hobby is|i do a lot of|really into|big fan of)\s+([A-Za-z\s,&]{2,60})/i", $msg, $m)) {
            $hobby = SQLite3::escapeString(trim($m[1]));
            // Append to interests JSON array
            $current = $db->querySingle("SELECT interests FROM fan_profiles WHERE fan_id = $fanId");
            $interests = json_decode($current ?: '[]', true) ?: [];
            if (!in_array(strtolower($hobby), array_map('strtolower', $interests))) {
                $interests[] = $hobby;
                $interestsJson = SQLite3::escapeString(json_encode($interests));
                $db->exec("UPDATE fan_profiles SET interests = '$interestsJson', updated_at = datetime('now') WHERE fan_id = $fanId");
            }
        }
        
        // 8. Pets: "I have a dog" / "my cat's name is Whiskers" / "got a puppy"
        if (preg_match("/(?:i have a|my|got a|i own a)\s*(dog|cat|puppy|kitten|bird|fish|snake|hamster|rabbit|parrot)(?:\s*named?\s*([A-Z][a-z]+))?/i", $msg, $m)) {
            $pet = trim($m[1]);
            $petName = isset($m[2]) ? trim($m[2]) : '';
            $petInfo = $petName ? "$pet named $petName" : $pet;
            // Append to callback_inventory
            $current = $db->querySingle("SELECT callback_inventory FROM fan_profiles WHERE fan_id = $fanId");
            $inventory = json_decode($current ?: '[]', true) ?: [];
            $petEntry = "Has a $petInfo";
            if (!in_array($petEntry, $inventory)) {
                $inventory[] = $petEntry;
                $invJson = SQLite3::escapeString(json_encode($inventory));
                $db->exec("UPDATE fan_profiles SET callback_inventory = '$invJson', updated_at = datetime('now') WHERE fan_id = $fanId");
            }
        }
        
        // 9. Life events: "just got promoted" / "going through a breakup" / "moving to a new city" / "starting a new job"
        if (preg_match("/(?:just got|just|going through|starting|finished|graduated|got fired|lost my|bought a|moving to)\s+([A-Za-z\s]{3,50})/i", $msg, $m)) {
            $event = SQLite3::escapeString(trim($m[1]));
            $current = $db->querySingle("SELECT life_events FROM fan_profiles WHERE fan_id = $fanId");
            $events = json_decode($current ?: '[]', true) ?: [];
            $eventEntry = date('Y-m-d') . ": " . $event;
            $events[] = $eventEntry;
            // Keep last 10 events
            $events = array_slice($events, -10);
            $eventsJson = SQLite3::escapeString(json_encode($events));
            $db->exec("UPDATE fan_profiles SET life_events = '$eventsJson', updated_at = datetime('now') WHERE fan_id = $fanId");
        }
        
        // 10. Referral source: "found you on TikTok" / "saw you on Reddit" / "friend told me"
        if (preg_match("/(?:found you|saw you|heard about you|came from|discovered you)\s*(?:on|from|via|through)?\s*(tiktok|reddit|twitter|instagram|ig|x|google|youtube|friend|a friend)/i", $msgLower, $m)) {
            $source = SQLite3::escapeString(trim($m[1]));
            $db->exec("UPDATE fan_profiles SET personality_notes = personality_notes || ' Referral: $source', updated_at = datetime('now') WHERE fan_id = $fanId");
        }
        
        // ========== UPDATE CONVERSATION STAGE ==========
        $count = $db->querySingle("SELECT total_messages FROM fan_profiles WHERE fan_id = $fanId");
        if ($count <= 5) {
            $stage = 'new';
            $db->exec("UPDATE fan_profiles SET onboarding_step = $count, welcome_sequence_complete = CASE WHEN $count >= 5 THEN 1 ELSE 0 END WHERE fan_id = $fanId");
        } elseif ($count <= 20) {
            $stage = 'warming_up';
        } else {
            $stage = 'hooked';
        }

    function callOllama($systemPrompt, $messages, $fanContext = "") {
        // Convert Anthropic message format to Ollama format
        $ollamaMessages = [];
        $ollamaMessages[] = ["role" => "system", "content" => $systemPrompt];
        foreach ($messages as $msg) {
            $ollamaMessages[] = ["role" => $msg["role"], "content" => is_string($msg["content"]) ? $msg["content"] : json_encode($msg["content"])];
        }
        
        $payload = json_encode([
            "model" => OLLAMA_MODEL,
            "messages" => $ollamaMessages,
            "stream" => false,
            "options" => ["temperature" => 0.9, "num_predict" => 300]
        ]);
        
        $ch = curl_init(OLLAMA_URL . "/api/chat");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45  // Ollama can be slow on cold start
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($code === 200) {
            $data = json_decode($resp, true);
            return $data["message"]["content"] ?? null;
        }
        error_log("Ollama call failed: code=$code err=$err");
        return null;
    }

        $db->exec("UPDATE fan_profiles SET conversation_stage = '$stage', updated_at = datetime('now') WHERE fan_id = $fanId");
    }

    $processed = 0; $queued = 0; $errors = 0; $debug = [];

    // Queue new messages
    $r = $db->query("SELECT m.id, m.sender_id, m.content, m.created_at FROM messages m WHERE m.receiver_id = $CREATOR_ID AND m.sender_id != $CREATOR_ID AND m.is_ai = 0 AND m.id NOT IN (SELECT fan_message_id FROM chat_queue) ORDER BY m.created_at ASC LIMIT 20");
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $delay = ($row["sender_id"] == 3) ? 5 : 1800;
        $sched = date('Y-m-d H:i:s', strtotime($row['created_at']) + $delay);
        $db->exec("INSERT OR IGNORE INTO chat_queue (fan_message_id, fan_user_id, status, scheduled_at) VALUES ({$row['id']}, {$row['sender_id']}, 'scheduled', '$sched')");
        $queued++;
    }


    // ========== PHASE 1: DELIVER typing items whose typing_until has passed ==========
    $r1 = $db->query("SELECT cq.id as qid, cq.fan_user_id, cq.ai_response, cq.fan_message_id FROM chat_queue cq WHERE cq.status = 'typing' AND cq.typing_until <= datetime('now') ORDER BY cq.created_at ASC");
    $deliverItems = [];
    while ($drow = $r1->fetchArray(SQLITE3_ASSOC)) { $deliverItems[] = $drow; }
    
    foreach ($deliverItems as $ditem) {
        $dfid = intval($ditem['fan_user_id']);
        $dqid = intval($ditem['qid']);
        $dreply = $ditem['ai_response'];
        
        if (!empty($dreply) && $dreply !== '(pending)') {
            $voiceData = @json_decode($dreply, true);
            if ($voiceData && isset($voiceData['_type']) && $voiceData['_type'] === 'voice') {
                // Voice message — stored as JSON during Phase 2
                $sv = $db->escapeString($voiceData['voice_text'] ?? '');
                $su = $db->escapeString($voiceData['voice_url'] ?? '');
                $db->exec("INSERT INTO messages (sender_id, receiver_id, content, media_url, message_type, is_ai, is_unlocked, created_at) VALUES ($CREATOR_ID, $dfid, '$sv', '$su', 'audio', 1, 1, datetime('now'))");
                if (!empty($voiceData['text_reply'])) {
                    $st = $db->escapeString($voiceData['text_reply']);
                    $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES ($CREATOR_ID, $dfid, '$st', 1, 1, datetime('now', '+2 seconds'))");
                }
            } else {
                // Regular text
                $safe = $db->escapeString($dreply);
                $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES ($CREATOR_ID, $dfid, '$safe', 1, 1, datetime('now'))");
            }
            $db->exec("UPDATE chat_queue SET status='delivered', delivered_at=datetime('now') WHERE id=$dqid");
            $origMsg = $db->querySingle("SELECT content FROM messages WHERE id = " . intval($ditem['fan_message_id']), false);
            updateFanProfile($db, $dfid, $origMsg ?? '', $dreply);
            $processed++;
            $debug[] = "phase1_deliver:fan=$dfid";
        }
    }

    // ========== PHASE 2: GENERATE replies for scheduled items ==========
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
        // Set typing state so frontend can detect it
        $qid = intval($item['qid']);
        $typingSecs = max(3, min(15, intval(strlen($item['fan_msg']) / 5)));
        $db->exec("UPDATE chat_queue SET status='typing', typing_until=datetime('now', '+" . $typingSecs . " seconds') WHERE id=$qid");
        $fid = intval($item['fan_id']);
        
        // Load fan profile and build context
        $fanProfile = loadFanProfile($db, $fid);
        $fanContext = buildFanContext($fanProfile);
        $whaleScore = intval($fanProfile['whale_score'] ?? 0);
        
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

        // === OLLAMA ROUTING: Check if this fan should use uncensored model ===
        $useOllama = false;
        global $OLLAMA_ENABLED_FANS;

        // Check if fan is enabled for Ollama AND has an active sexting session
        if (in_array($fanUserId, $OLLAMA_ENABLED_FANS)) {
            // Load sexting session status from fan_profiles
            $sStmt = $db->prepare("SELECT sexting_session_active FROM fan_profiles WHERE fan_id = :fid");
            $sStmt->bindValue(":fid", $fanUserId, SQLITE3_INTEGER);
            $sResult = @$sStmt->execute();
            $sRow = $sResult ? $sResult->fetchArray(SQLITE3_ASSOC) : null;
            if ($sRow && $sRow["sexting_session_active"] == 1) {
                $useOllama = true;
                $debug[] = "route=ollama";
            }
        }

        if ($useOllama) {
            // Use Ollama with uncensored prompt
            require_once __DIR__ . "/ollama-prompt.php";
            $ollamaPrompt = getOllamaSystemPrompt($fanContext);
            $reply = callOllama($ollamaPrompt, $msgs, $fanContext);
            
            if (!$reply) {
                // Ollama failed — DONT fall back to Sonnet for explicit content
                $debug[] = "ollama_failed";
                $fallbackMessages = [
                    "sorry babe my phone died 😩 whatd I miss?",
                    "omg sorry I disappeared 😂 my wifi was being weird",
                    "hey sorry about that 😩 Im back now"
                ];
                $reply = $fallbackMessages[array_rand($fallbackMessages)];
                error_log("OLLAMA FAILED for fan $fanUserId — used canned fallback, NOT Sonnet");
            }
        } else {

        // Call Anthropic with fallback logic
        $reply = null;
        $apiError = null;
        
        // Primary model attempt
        $payload = json_encode(['model'=>$MODEL,'max_tokens'=>300,'temperature'=>0.9,'system'=>getBreyyaSystemPrompt('', $fanContext, $whaleScore),'messages'=>$msgs]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$ANTHROPIC_KEY,'anthropic-version: 2023-06-01'], CURLOPT_POSTFIELDS=>$payload, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>25]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        $debug[] = "api=$code";
        if ($code === 200) {
            $data = json_decode($resp, true);
            $reply = $data['content'][0]['text'] ?? null;
        }

        // Fallback to claude-haiku if primary failed
        if (!$reply && $MODEL !== 'claude-haiku-3-20240307') {
            $fallbackModel = 'claude-haiku-3-20240307';
            $payload = json_encode(['model'=>$fallbackModel,'max_tokens'=>300,'temperature'=>0.9,'system'=>getBreyyaSystemPrompt('', $fanContext, $whaleScore),'messages'=>$msgs]);
            
            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$ANTHROPIC_KEY,'anthropic-version: 2023-06-01'], CURLOPT_POSTFIELDS=>$payload, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>25]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($code === 200) {
                $data = json_decode($resp, true);
                $reply = $data['content'][0]['text'] ?? null;
                $debug[] = "fallback_success:haiku";
            } else {
                $apiError = "Primary and fallback APIs failed. Code: $code, Error: $cerr";
            }
        } elseif (!$reply) {
            $apiError = "Primary API failed. Code: $code, Error: $cerr";
        }

        // If both API calls failed, use canned fallback response
        if (!$reply) {
            $fallbackMessages = [
                "sorry babe my phone died 😩 what'd I miss?",
                "omg sorry I disappeared 😂 my wifi was being weird",
                "hey sorry about that 😩 I'm back now",
                "ugh sorry babe, my phone was acting up 😂"
            ];
            $reply = $fallbackMessages[array_rand($fallbackMessages)];
            
            // Log the error
            error_log("BREYYA_API_FALLBACK: fan_id=$fid, error=$apiError, timestamp=" . date('c'));
            
            // Mark queue item with fallback status
            $db->exec("UPDATE chat_queue SET status='fallback_sent' WHERE id=" . intval($item['qid']));
            $debug[] = "fallback_used";
        }
        }  // End of Ollama routing else block

        // ========== POST-PROCESSING: Enforce rules server-side ==========
        
        // 0. Strip any stray voice closing tags
        $reply = str_replace('[/VOICE]', '', $reply);
        
        // 1. Strip ALL emoji not in the approved set
        // Approved: 😘 🥰 😏 🔥 💕 😂 👀 😩 🫶 💋 ❤️
        // Strategy: strip known bad ones explicitly
        $bannedEmoji = ['😊', '😄', '🙂', '😉', '🤗', '💪', '🎶', '✨', '💫', '🌟', '⭐', '🤔', '😌', '🙈', '💖', '🌸', '🥺', '😍', '🤤', '😜', '😝', '🤭', '🥵', '💦', '🙏', '💗', '😎', '🤩', '💜', '💙', '🧡', '💛', '🖤', '🤍', '♥️', '😈', '🔒', '🎂', '🎉', '🎊', '🥳', '🤑'];
        $reply = str_replace($bannedEmoji, '', $reply);
        
        // 2. Smart character limit: max 280 chars, always break at sentence end
        // Never cut mid-sentence. If no sentence break found, extend to find one.
        if (!preg_match('/\[VOICE/', $reply) && !preg_match('/\[PPV/', $reply)) {
            if (mb_strlen($reply) > 280) {
                // Try to find sentence end before 280 chars
                $truncated = mb_substr($reply, 0, 280);
                $lastPeriod = max(strrpos($truncated, '.'), strrpos($truncated, '!'), strrpos($truncated, '?'));
                if ($lastPeriod && $lastPeriod > 60) {
                    $reply = mb_substr($reply, 0, $lastPeriod + 1);
                } else {
                    // No sentence break before 280 — look ahead up to 350 for one
                    $extended = mb_substr($reply, 0, 350);
                    $nextEnd = null;
                    foreach (['.', '!', '?'] as $p) {
                        $pos = strpos($extended, $p, 200);
                        if ($pos !== false && ($nextEnd === null || $pos < $nextEnd)) $nextEnd = $pos;
                    }
                    if ($nextEnd) {
                        $reply = mb_substr($reply, 0, $nextEnd + 1);
                    } else {
                        // Last resort: break at last space before 280
                        $lastSpace = strrpos($truncated, ' ');
                        $reply = $lastSpace ? mb_substr($reply, 0, $lastSpace) : $truncated;
                    }
                }
                $debug[] = "smart_truncated";
            }
        }
        
        // 3. Enforce max 1 question mark per message
        $questionCount = substr_count($reply, '?');
        if ($questionCount > 1) {
            // Keep only the LAST question mark, remove earlier ones
            $pos = strpos($reply, '?');
            while ($questionCount > 1) {
                $reply = substr_replace($reply, '', $pos, 1);
                $questionCount--;
                $pos = strpos($reply, '?');
            }
            $debug[] = "stripped_extra_questions";
        }
        
        // 4. Anti-repeat opener: check last Breyya message, strip matching opener
        $lastBMsg = $db->querySingle("SELECT content FROM messages WHERE sender_id = $CREATOR_ID AND receiver_id = $fid ORDER BY id DESC LIMIT 1");
        if ($lastBMsg) {
            $lastWord = strtolower(explode(' ', trim($lastBMsg))[0]);
            $thisWord = strtolower(explode(' ', trim($reply))[0]);
            // If same opener word, strip it
            if ($lastWord === $thisWord && strlen($lastWord) > 1) {
                $reply = preg_replace('/^\S+\s*/', '', $reply);
                $reply = ltrim($reply, ', ');
                // Capitalize first letter
                $reply = mb_strtoupper(mb_substr($reply, 0, 1)) . mb_substr($reply, 1);
                // But if it was "mmm" or similar filler, just lowercase is fine
                if (preg_match('/^[A-Z][a-z]/', $reply) && mb_strlen($reply) > 2) {
                    $reply = mb_strtolower(mb_substr($reply, 0, 1)) . mb_substr($reply, 1);
                }
                $debug[] = "repeat_opener_stripped:$lastWord";
            }
        }
        
        // 5. Clean up double spaces, leading/trailing whitespace
        $reply = preg_replace('/\s{2,}/', ' ', trim($reply));
        
        // ========== END POST-PROCESSING ==========

        // Check for voice note processing
        $isVoiceMessage = false;
        $voiceText = null;
        $audioUrl = null;
        
        // Match ALL voice tag formats the AI might use:
        // [VOICE:text here] or [VOICE]text[/VOICE] or [VOICE] text
        // First: strip any [/VOICE] closing tags
        $reply = str_replace('[/VOICE]', '', $reply);
        
        if (preg_match('/\[VOICE[:\s]([^\]]+)\]/', $reply, $voiceMatch) || preg_match('/\[VOICE\]\s*(.+?)$/s', $reply, $voiceMatch)) {
            $isVoiceMessage = true;
            $rawVoiceText = trim($voiceMatch[1]);
            
            // Voice = one short ASMR thought (3-7 words). If model sent more, take first phrase only.
            $voiceWords = explode(' ', $rawVoiceText);
            if (count($voiceWords) > 8) {
                // Find first natural break: comma, period, emoji, or exclamation
                $firstPhrase = $rawVoiceText;
                if (preg_match('/^(.{10,60}?)[,\.!?😏🔥😂💕😘]/', $rawVoiceText, $phraseMatch)) {
                    $firstPhrase = trim($phraseMatch[1]);
                } else {
                    // No natural break — just take first 7 words
                    $firstPhrase = implode(' ', array_slice($voiceWords, 0, 7));
                }
                $voiceText = $firstPhrase;
                $debug[] = "voice_shortened:" . count($voiceWords) . "->" . str_word_count($voiceText);
            } else {
                $voiceText = $rawVoiceText;
            }
            
            // Strip ALL voice tags from reply, keep remaining text as separate message
            $remainingText = trim(preg_replace('/\[VOICE[:\s][^\]]*\]|\[VOICE\][^\n]*/', '', $reply));
            if ($remainingText && $remainingText !== $rawVoiceText) {
                $reply = $remainingText;
            } else {
                $reply = $voiceText;
            }
            
            // Generate voice note
            require_once __DIR__ . '/../lib/voice.php';
            createVoiceNotesTable(); // Ensure table exists
            
            $audioUrl = generateVoiceNote($voiceText, $fid);
            
            if ($audioUrl) {
                logVoiceNote($fid, $voiceText, $audioUrl);
                $debug[] = "voice_generated:fan=$fid";
            } else {
                // Voice generation failed, fall back to text
                $reply = $voiceText;
                $debug[] = "voice_failed:fan=$fid";
            }
        }

        // PPV Content Detection & Processing
        $ppvDetected = false;
        
        // RATE LIMIT: Max 1 PPV per fan per 10 messages. Check if we already sent one recently.
        $recentPpv = $db->querySingle("SELECT COUNT(*) FROM messages WHERE sender_id = $CREATOR_ID AND receiver_id = $fid AND is_ppv = 1 AND id > (SELECT COALESCE(MAX(id),0) - 10 FROM messages WHERE (sender_id = $fid OR receiver_id = $fid))");
        $ppvBlocked = ($recentPpv > 0);
        if ($ppvBlocked) {
            // Strip the PPV tag — she already sent one recently
            $reply = preg_replace('/\[PPV:[^\]]+\]/', '', $reply);
            $reply = trim($reply);
            $debug[] = "ppv_rate_limited:fan=$fid,recent=$recentPpv";
        }
        
        if (!$ppvBlocked && preg_match('/\[PPV:([^:]+):([0-9.]+)\]/', $reply, $ppvMatches)) {
            $ppvItemId = trim($ppvMatches[1]);
            $ppvPrice = floatval($ppvMatches[2]);
            
            // Load content inventory to get item details
            $inventoryFile = __DIR__ . '/../../data/content-inventory.json';
            if (file_exists($inventoryFile)) {
                $inventory = json_decode(file_get_contents($inventoryFile), true);
                $ppvItem = null;
                
                // Find the requested item
                foreach ($inventory['items'] as $item) {
                    if ($item['id'] === $ppvItemId) {
                        $ppvItem = $item;
                        break;
                    }
                }
                
                if ($ppvItem && $ppvPrice > 0) {
                    // Generate blurred preview if possible
                    $previewUrl = '';
                    if ($ppvItem['type'] === 'photo') {
                        // Try to create blurred preview
                        $previewDir = __DIR__ . '/../../data/ppv-previews/';
                        if (!is_dir($previewDir)) {
                            mkdir($previewDir, 0755, true);
                        }
                        
                        $previewFile = $previewDir . $ppvItemId . '_preview.jpg';
                        if (function_exists('imagecreatefromjpeg') && !file_exists($previewFile)) {
                            // Create blurred preview using GD
                            $sourceUrl = $ppvItem['public_url'];
                            $imageData = @file_get_contents($sourceUrl);
                            if ($imageData) {
                                $sourceImage = @imagecreatefromstring($imageData);
                                if ($sourceImage) {
                                    // Apply heavy blur
                                    for ($i = 0; $i < 30; $i++) {
                                        imagefilter($sourceImage, IMG_FILTER_GAUSSIAN_BLUR);
                                    }
                                    imagejpeg($sourceImage, $previewFile, 60);
                                    imagedestroy($sourceImage);
                                    $previewUrl = '/data/ppv-previews/' . $ppvItemId . '_preview.jpg';
                                }
                            }
                        }
                        
                        // Fallback: use original URL but mark for CSS blur
                        if (!$previewUrl) {
                            $previewUrl = $ppvItem['public_url'];
                        }
                    } else {
                        // For videos, use a placeholder or first frame
                        $previewUrl = $ppvItem['public_url'];
                    }
                    
                    // Strip the [PPV:...] tag from the reply text
                    $cleanReply = preg_replace('/\[PPV:[^\]]+\]/', '', $reply);
                    $cleanReply = trim($cleanReply);
                    
                    // Insert the text message first (if there's text content)
                    if (!empty($cleanReply)) {
                        $safeTextMsg = $db->escapeString($cleanReply);
                        $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES ($CREATOR_ID, $fid, '$safeTextMsg', 1, 1, datetime('now'))");
                    }
                    
                    // Insert the PPV message
                    $ppvPriceCents = intval($ppvPrice * 100);
                    $safePpvContentKey = $db->escapeString($ppvItem['key']);
                    $safePpvPreviewUrl = $db->escapeString($previewUrl);
                    $safePpvMediaUrl = $db->escapeString($ppvItem['public_url']);
                    
                    $db->exec("INSERT INTO messages (sender_id, receiver_id, content, media_url, media_thumbnail, is_ppv, ppv_price_cents, is_unlocked, ppv_preview_url, ppv_content_key, is_ai, created_at) 
                              VALUES ($CREATOR_ID, $fid, 'Exclusive content - unlock to view 🔒', '$safePpvMediaUrl', '$safePpvPreviewUrl', 1, $ppvPriceCents, 0, '$safePpvPreviewUrl', '$safePpvContentKey', 1, datetime('now', '+1 second'))");
                    
                    $ppvDetected = true;
                    $debug[] = "ppv_sent:item=$ppvItemId,price=$ppvPrice,fan=$fid";
                    
                    // Update reply for logging purposes
                    $reply = $cleanReply . " [PPV sent: $ppvItemId for $$ppvPrice]";
                } else {
                    $debug[] = "ppv_error:item_not_found=$ppvItemId";
                }
            } else {
                $debug[] = "ppv_error:no_inventory";
            }
        }        // Double-texting logic: ~15% chance of splitting into 2 messages (not for voice messages)
        $doubleTexted = false;
        if (!$isVoiceMessage && mt_rand(1, 100) <= 15 && strlen($reply) > 20) {
            // Split the reply into 2 messages
            // Strategy: Split at sentence boundary, or split at emoji
            $parts = preg_split('/(?<=[.!?😂🔥😏💕😘🥰👀😩])\s+/', $reply, 2);
            if (count($parts) == 2 && strlen($parts[0]) > 5 && strlen($parts[1]) > 5) {
                // Insert first message
                $safe1 = $db->escapeString($parts[0]);
                $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) 
                          VALUES ($CREATOR_ID, $fid, '$safe1', 1, 1, datetime('now'))");
                
                // Insert second message 2-5 seconds later
                $delay = mt_rand(2, 5);
                $safe2 = $db->escapeString($parts[1]);
                $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) 
                          VALUES ($CREATOR_ID, $fid, '$safe2', 1, 1, datetime('now', '+$delay seconds'))");
                
                // Skip normal insert
                $doubleTexted = true;
                $debug[] = "double_text:fan=$fid,delay={$delay}s";
            }
        }

        // Insert reply (only if not double-texted and no PPV)
        // DEDUP: prevent duplicate messages within 10 seconds
        // ===== PHASE 2 SAVE: Store reply in queue, set typing state =====
        $dedupSafe = $db->escapeString($reply);
        $dedupCheck = $db->querySingle("SELECT COUNT(*) FROM messages WHERE sender_id = $CREATOR_ID AND receiver_id = $fid AND content = '$dedupSafe' AND created_at >= datetime('now', '-10 seconds')");
        if ($dedupCheck > 0) { $debug[] = "dedup_skipped:fan=$fid"; $doubleTexted = true; }
        if (!$doubleTexted && !$ppvDetected) {
            // Calculate typing duration based on reply length (3-20 seconds)
            $typingDuration = max(3, min(20, intval(mb_strlen($reply) / 15)));
            
            if ($isVoiceMessage && $audioUrl) {
                // Store voice data as JSON for Phase 1 to deliver
                $voicePayload = json_encode([
                    '_type' => 'voice',
                    'voice_text' => $voiceText,
                    'voice_url' => $audioUrl,
                    'text_reply' => ($reply !== $voiceText && !empty($reply) && strlen($reply) > 2) ? $reply : ''
                ]);
                $safePayload = $db->escapeString($voicePayload);
                $db->exec("UPDATE chat_queue SET status='typing', ai_response='$safePayload', typing_until=datetime('now', '+" . $typingDuration . " seconds') WHERE id=" . intval($item['qid']));
                $debug[] = "phase2_voice_typing:fan=$fid,secs=$typingDuration";
            } else {
                // Store text reply for Phase 1 to deliver
                $safe = $db->escapeString($reply);
                $db->exec("UPDATE chat_queue SET status='typing', ai_response='$safe', typing_until=datetime('now', '+" . $typingDuration . " seconds') WHERE id=" . intval($item['qid']));
                $debug[] = "phase2_text_typing:fan=$fid,secs=$typingDuration";
            }
        }
        
        $processed++;
    }

    $db->close();
    echo json_encode(['ok'=>true,'queued'=>$queued,'processed'=>$processed,'errors'=>$errors,'debug'=>$debug,'key'=>substr($ANTHROPIC_KEY,0,10),'model'=>$MODEL,'ts'=>date('Y-m-d H:i:s')]);

} catch (Throwable $e) {
    echo json_encode(['fatal'=>$e->getMessage(),'line'=>$e->getLine()]);
}
