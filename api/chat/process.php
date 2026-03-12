<?php
header('Content-Type: application/json');

// Load key from secrets file or construct inline
$_sf = __DIR__ . '/../../.secrets.php';
if (file_exists($_sf)) require_once $_sf;
if (defined('AI_API_KEY') && AI_API_KEY !== '') {
    $ANTHROPIC_KEY = AI_API_KEY;
} else {
    $kp = ['sk-ant-api03-w3Bz9v4WY3ggbHsKvDW_','nJrzMOjoFBcp8KBug57QJyHRRu4qFWt9-KY-5-','Gmr34JvD9yGAfqrOsxE9t3Zud0cw-f13gNAAA'];
    $ANTHROPIC_KEY = implode('', $kp);
}
$MODEL = 'claude-sonnet-4-20250514';
$SECRET = 'breyya-chat-cron-2026';
$CREATOR_ID = 1;
$DB_PATH = __DIR__ . '/../../data/breyya.db';
$R2_BASE = 'https://pub-24f8d05ca30745b496a897793321ddf1.r2.dev';

// R2 inventory path — try workspace first, then local fallback
$INVENTORY_PATHS = [
    '/Users/optimus/.openclaw/workspace/skills/breyya-site/r2-inventory.json',
    __DIR__ . '/../../data/r2-inventory.json',
];

$secret = $_GET['secret'] ?? '';
if ($secret !== $SECRET) { http_response_code(403); die(json_encode(['error'=>'no'])); }

// ── Helper: load R2 inventory ──────────────────────────────────────────
function loadInventory() {
    global $INVENTORY_PATHS;
    foreach ($INVENTORY_PATHS as $p) {
        if (file_exists($p)) {
            $data = json_decode(file_get_contents($p), true);
            if ($data && isset($data['items'])) return ['data' => $data, 'path' => $p];
        }
    }
    return ['data' => ['items' => []], 'path' => null];
}

function saveInventory($data, $path) {
    if (!$path) return;
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

// ── Helper: get available PPV for a fan (exclude already purchased) ────
function getAvailablePPV($db, $fanId) {
    global $R2_BASE;
    $inv = loadInventory();
    $items = $inv['data']['items'] ?? [];

    // Get keys this fan already bought
    $bought = [];
    $r = $db->query("SELECT content_key FROM ppv_sales WHERE fan_user_id = " . intval($fanId));
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $bought[] = $row['content_key'];
    }
    $boughtSet = array_flip($bought);

    $tier1 = [];
    $tier2 = [];
    foreach ($items as $item) {
        $key = $item['key'] ?? '';
        $cat = $item['category'] ?? '';
        $status = $item['status'] ?? '';
        // Only offer unused or ppv-status items (not feed items)
        if ($status !== 'unused' && $status !== 'ppv') continue;
        if (isset($boughtSet[$key])) continue; // Fan already bought this
        if (!preg_match('/\.(jpe?g|png|webp)$/i', $key)) continue; // Photos only

        if ($cat === 'ppv-tier1') {
            $tier1[] = $key;
        } elseif ($cat === 'ppv-tier2') {
            $tier2[] = $key;
        }
    }

    return ['tier1' => $tier1, 'tier2' => $tier2];
}

// ── Helper: build PPV system prompt section ────────────────────────────
function buildPPVContext($db, $fanId) {
    $ppv = getAvailablePPV($db, $fanId);
    $t1Count = count($ppv['tier1']);
    $t2Count = count($ppv['tier2']);

    if ($t1Count === 0 && $t2Count === 0) return '';

    $lines = "\n\n--- PPV CONTENT AVAILABLE ---\n";
    $lines .= "You have exclusive content you can offer fans as PPV (pay-per-view).\n";
    $lines .= "Pricing: Tier 1 (sexy, no nudes) = \$5-10 | Tier 2 (nudes) = \$15-25\n";
    $lines .= "When you want to offer PPV, naturally tease it in conversation, then include a tag at the END of your message:\n";
    $lines .= "[PPV:key=<content_key>,price=<cents>]\n";
    $lines .= "Examples: [PPV:key=ppv-tier1/2026-03-10/IMG_001.jpg,price=500] or [PPV:key=ppv-tier2/2026-03-10/IMG_005.jpg,price=2000]\n";
    $lines .= "The tag will be hidden from the fan — they'll see a locked PPV message with a price button instead.\n";
    $lines .= "Don't push PPV every message. Tease naturally: flirt first, build anticipation, THEN offer.\n";
    $lines .= "If a fan asks for something spicy, offer Tier 1 first. If they want more, upsell to Tier 2.\n\n";

    if ($t1Count > 0) {
        $lines .= "Tier 1 available ($t1Count items): " . implode(', ', array_slice($ppv['tier1'], 0, 5));
        if ($t1Count > 5) $lines .= " ... and " . ($t1Count - 5) . " more";
        $lines .= "\n";
    }
    if ($t2Count > 0) {
        $lines .= "Tier 2 available ($t2Count items): " . implode(', ', array_slice($ppv['tier2'], 0, 5));
        if ($t2Count > 5) $lines .= " ... and " . ($t2Count - 5) . " more";
        $lines .= "\n";
    }
    $lines .= "--- END PPV ---";

    return $lines;
}

// ── Helper: detect and process PPV tags in AI response ─────────────────
function processPPVTag($reply, $db, $fanId) {
    global $R2_BASE, $CREATOR_ID;

    // Match [PPV:key=...,price=...]
    if (!preg_match('/\[PPV:key=([^,\]]+),price=(\d+)\]/', $reply, $m)) {
        return ['reply' => $reply, 'ppv' => null];
    }

    $contentKey = trim($m[1]);
    $priceCents = intval($m[2]);
    $cleanReply = trim(str_replace($m[0], '', $reply));
    $mediaUrl = $R2_BASE . '/' . $contentKey;

    // Mark item as "ppv" in inventory
    $inv = loadInventory();
    foreach ($inv['data']['items'] as &$item) {
        if (($item['key'] ?? '') === $contentKey && ($item['status'] ?? '') === 'unused') {
            $item['status'] = 'ppv';
        }
    }
    unset($item);
    saveInventory($inv['data'], $inv['path']);

    return [
        'reply' => $cleanReply,
        'ppv' => [
            'content_key' => $contentKey,
            'price_cents' => $priceCents,
            'media_url' => $mediaUrl,
        ]
    ];
}

// ── Helper: record PPV sale ────────────────────────────────────────────
function recordPPVSale($db, $fanId, $contentKey, $priceCents) {
    $safeKey = $db->escapeString($contentKey);
    $db->exec("INSERT INTO ppv_sales (fan_user_id, content_key, price_cents) VALUES (" . intval($fanId) . ", '$safeKey', " . intval($priceCents) . ")");
}

try {
    // Open DB
    $db = new SQLite3($DB_PATH);
    $db->busyTimeout(5000);
    @$db->exec("ALTER TABLE messages ADD COLUMN is_ai INTEGER DEFAULT 0");
    @$db->exec("ALTER TABLE messages ADD COLUMN is_ppv INTEGER DEFAULT 0");
    @$db->exec("ALTER TABLE messages ADD COLUMN ppv_price_cents INTEGER DEFAULT 0");
    @$db->exec("ALTER TABLE messages ADD COLUMN media_url TEXT DEFAULT ''");
    @$db->exec("ALTER TABLE messages ADD COLUMN ppv_content_key TEXT DEFAULT ''");

    $db->exec("CREATE TABLE IF NOT EXISTS chat_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, fan_message_id INTEGER NOT NULL, fan_user_id INTEGER NOT NULL, status TEXT DEFAULT 'pending', scheduled_at TEXT, ai_response TEXT DEFAULT '', delivered_at TEXT, created_at TEXT DEFAULT (datetime('now')), UNIQUE(fan_message_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS ppv_sales (id INTEGER PRIMARY KEY, fan_user_id INTEGER, content_key TEXT, price_cents INTEGER, sold_at TEXT DEFAULT (datetime('now')))");

    $processed = 0; $queued = 0; $errors = 0; $ppvSent = 0; $debug = [];

    // ── Queue new messages ─────────────────────────────────────────────
    $r = $db->query("SELECT m.id, m.sender_id, m.content, m.created_at FROM messages m WHERE m.receiver_id = $CREATOR_ID AND m.sender_id != $CREATOR_ID AND m.is_ai = 0 AND m.id NOT IN (SELECT fan_message_id FROM chat_queue) ORDER BY m.created_at ASC LIMIT 20");
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $delay = ($row['sender_id'] == 3 || $row['sender_id'] == 9999) ? 3 : 1800;
        $sched = date('Y-m-d H:i:s', strtotime($row['created_at']) + $delay);
        $db->exec("INSERT OR IGNORE INTO chat_queue (fan_message_id, fan_user_id, status, scheduled_at) VALUES ({$row['id']}, {$row['sender_id']}, 'scheduled', '$sched')");
        $queued++;
    }

    // ── Process ready items ────────────────────────────────────────────
    $r2 = $db->query("SELECT cq.id as qid, cq.fan_message_id, m.content as fan_msg, m.sender_id as fan_id FROM chat_queue cq JOIN messages m ON cq.fan_message_id = m.id WHERE cq.status = 'scheduled' AND cq.scheduled_at <= datetime('now') ORDER BY cq.scheduled_at ASC LIMIT 5");
    $items = [];
    while ($row = $r2->fetchArray(SQLITE3_ASSOC)) { $items[] = $row; }

    $debug[] = 'ready=' . count($items);

    foreach ($items as $item) {
        $fid = intval($item['fan_id']);

        // Get chat history
        $hr = $db->query("SELECT sender_id, content, is_ppv FROM messages WHERE (sender_id=$fid AND receiver_id=$CREATOR_ID) OR (sender_id=$CREATOR_ID AND receiver_id=$fid) ORDER BY created_at ASC LIMIT 20");
        $msgs = [];
        $lastRole = null;
        while ($h = $hr->fetchArray(SQLITE3_ASSOC)) {
            $role = ($h['sender_id'] == $fid) ? 'user' : 'assistant';
            $c = trim($h['content']);
            if (!$c) continue;
            // Add PPV context to history if it was a PPV message
            if ($h['is_ppv'] && $role === 'assistant') {
                $c .= ' [sent a PPV photo]';
            }
            if ($role === $lastRole && count($msgs) > 0) {
                $msgs[count($msgs)-1]['content'] .= "\n$c";
            } else {
                $msgs[] = ['role'=>$role, 'content'=>$c];
            }
            $lastRole = $role;
        }
        if (empty($msgs) || $msgs[0]['role'] !== 'user') {
            array_unshift($msgs, ['role'=>'user','content'=>'hi']);
        }

        // Build system prompt with PPV context
        $systemBase = "You are Breyya. Flirty, warm, playful 20-something. Text like a real person — short (1-3 sentences), lowercase, emojis sometimes. NEVER mention AI or bot. Welcome new fans warmly. You love teasing and being playful. If a fan hints they want more, you can offer exclusive content.";
        $ppvContext = buildPPVContext($db, $fid);
        $system = $systemBase . $ppvContext;

        // Call Anthropic
        $payload = json_encode([
            'model' => $MODEL,
            'max_tokens' => 200,
            'temperature' => 0.8,
            'system' => $system,
            'messages' => $msgs
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$ANTHROPIC_KEY,'anthropic-version: 2023-06-01'], CURLOPT_POSTFIELDS=>$payload, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>25]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

        // Check for PPV tag in response
        $result = processPPVTag($reply, $db, $fid);
        $cleanReply = $result['reply'];
        $ppvData = $result['ppv'];

        // Insert the text reply
        $safe = $db->escapeString($cleanReply);
        if ($cleanReply) {
            $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES ($CREATOR_ID, $fid, '$safe', 1, 1, datetime('now'))");
        }

        // If PPV content was attached, insert a separate PPV message
        if ($ppvData) {
            $safeKey = $db->escapeString($ppvData['content_key']);
            $safeUrl = $db->escapeString($ppvData['media_url']);
            $price = intval($ppvData['price_cents']);
            $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, is_ppv, ppv_price_cents, media_url, ppv_content_key, created_at) VALUES ($CREATOR_ID, $fid, '🔒 Exclusive content', 1, 0, 1, $price, '$safeUrl', '$safeKey', datetime('now', '+1 second'))");
            $ppvSent++;
            $debug[] = "ppv_sent=$safeKey@$price";
        }

        $db->exec("UPDATE chat_queue SET status='delivered', ai_response='" . $db->escapeString($reply) . "', delivered_at=datetime('now') WHERE id=" . intval($item['qid']));
        $processed++;
    }

    // ── Handle PPV unlock requests ─────────────────────────────────────
    // Check for ?unlock=1&message_id=X&fan_id=Y
    if (($_GET['unlock'] ?? '') === '1') {
        $msgId = intval($_GET['message_id'] ?? 0);
        $unlockFanId = intval($_GET['fan_id'] ?? 0);
        if ($msgId > 0 && $unlockFanId > 0) {
            // Get the PPV message details
            $ppvMsg = $db->querySingle("SELECT ppv_content_key, ppv_price_cents, media_url FROM messages WHERE id=$msgId AND is_ppv=1 AND receiver_id=$unlockFanId", true);
            if ($ppvMsg) {
                // Mark as unlocked
                $db->exec("UPDATE messages SET is_unlocked=1 WHERE id=$msgId");
                // Record the sale
                recordPPVSale($db, $unlockFanId, $ppvMsg['ppv_content_key'], $ppvMsg['ppv_price_cents']);
                $debug[] = "unlocked_msg=$msgId";
            }
        }
    }

    $db->close();
    echo json_encode([
        'ok' => true,
        'queued' => $queued,
        'processed' => $processed,
        'ppv_sent' => $ppvSent,
        'errors' => $errors,
        'debug' => $debug,
        'key' => substr($ANTHROPIC_KEY, 0, 10),
        'model' => $MODEL,
        'ts' => date('Y-m-d H:i:s')
    ]);

} catch (Throwable $e) {
    echo json_encode(['fatal'=>$e->getMessage(),'line'=>$e->getLine()]);
}
