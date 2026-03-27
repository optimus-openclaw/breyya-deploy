<?php
header('Content-Type: application/json');

// Load key from secrets file or construct inline  
$_sf = __DIR__ . '/../.secrets.php';
if (file_exists($_sf)) require_once $_sf;
if (defined('AI_API_KEY') && AI_API_KEY !== '') {
    $ANTHROPIC_KEY = AI_API_KEY;
} elseif (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '') {
    $ANTHROPIC_KEY = OPENAI_API_KEY;
} else {
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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['error' => 'invalid_json']));
}

$intent = $input['intent'] ?? '';
$maxFans = intval($input['max_fans'] ?? 50);
$validIntents = ['good_morning', 'content_tease', 'late_night', 'flash_sale', 'reengagement'];

if (!in_array($intent, $validIntents) || $maxFans < 1 || $maxFans > 100) {
    http_response_code(400);
    die(json_encode(['error' => 'invalid_params', 'intent' => $intent, 'max_fans' => $maxFans]));
}

try {
    $db = getDB();

    // Get list of active fans (have subscription, not banned)
    // Prioritize fans who haven't received broadcasts recently
    $fans = [];
    $r = $db->query("SELECT DISTINCT u.id, u.display_name, fp.display_name as fan_name
                     FROM users u
                     JOIN subscriptions s ON u.id = s.user_id 
                     LEFT JOIN fan_profiles fp ON u.id = fp.fan_user_id
                     WHERE u.role = 'fan' 
                       AND s.status = 'active' 
                       AND s.expires_at > datetime('now')
                       AND (fp.banned IS NULL OR fp.banned = 0)
                       AND u.id NOT IN (
                           SELECT fan_user_id FROM broadcast_history 
                           WHERE sent_at > datetime('now', '-1 day')
                           GROUP BY fan_user_id 
                           HAVING COUNT(*) >= 5
                       )
                     ORDER BY RANDOM()
                     LIMIT $maxFans");
    
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $fans[] = [
            'id' => intval($row['id']),
            'name' => $row['fan_name'] ?: $row['display_name'] ?: ''
        ];
    }

    if (empty($fans)) {
        echo json_encode([
            'success' => true,
            'sent_count' => 0,
            'message' => 'No eligible fans found',
            'intent' => $intent
        ]);
        exit;
    }

    // Generate 5-10 message variants for this intent upfront (cost efficiency)
    $variantCount = min(10, max(5, ceil(count($fans) / 5))); // 5-10 variants based on fan count
    
    // Build intent-specific system prompt
    $intentPrompts = [
        'good_morning' => "Generate $variantCount different good morning message variants. Each should be 1-2 sentences, flirty and warm, using Breyya's voice. Mix up the wording completely - fans shouldn't see identical messages. Include morning activities like coffee, waking up, breakfast, etc.",
        'content_tease' => "Generate $variantCount different content tease message variants. Each should hint at new photos/content without being explicit. Be playful and mysterious. Mix up the wording completely so no two are alike.",
        'late_night' => "Generate $variantCount different late night message variants. Each should capture late night vibes - can't sleep, thinking about fans, cozy in bed. Mix up the wording completely.",
        'flash_sale' => "Generate $variantCount different flash sale/special offer message variants. Each should create urgency about limited time content or deals. Mix up the wording completely.",
        'reengagement' => "Generate $variantCount different re-engagement message variants. Each should warmly invite fans back into conversation after being quiet. Mix up the wording completely."
    ];

    $systemPrompt = getBreyyaSystemPrompt() . "\n\nTASK: " . $intentPrompts[$intent] . "\n\nFormat your response as a numbered list (1. 2. 3. etc.) with each variant on its own line.";

    // Generate variants with Anthropic
    $payload = json_encode([
        'model' => $MODEL,
        'max_tokens' => 400,
        'temperature' => 0.9,
        'system' => $systemPrompt,
        'messages' => [
            ['role' => 'user', 'content' => "Generate the message variants for $intent"]
        ]
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
        CURLOPT_TIMEOUT => 30
    ]);
    
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        http_response_code(500);
        die(json_encode(['error' => 'api_failed', 'code' => $code]));
    }

    $data = json_decode($resp, true);
    $variantText = $data['content'][0]['text'] ?? '';
    if (!$variantText) {
        http_response_code(500);
        die(json_encode(['error' => 'no_variants']));
    }

    // Parse variants from the response
    $variants = [];
    $lines = explode("\n", $variantText);
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^\d+\.\s*(.+)$/', $line, $matches)) {
            $variants[] = trim($matches[1]);
        }
    }

    // Fallback if parsing failed
    if (empty($variants)) {
        $variants = array_filter(array_map('trim', $lines));
    }

    if (empty($variants)) {
        http_response_code(500);
        die(json_encode(['error' => 'failed_to_parse_variants', 'raw' => $variantText]));
    }

    // Ensure we have enough variants (duplicate if needed)
    while (count($variants) < count($fans)) {
        $variants = array_merge($variants, $variants);
    }

    // Send personalized messages to each fan
    $sentCount = 0;
    $errors = [];

    foreach ($fans as $i => $fan) {
        try {
            // Pick a variant (rotate through them)
            $variantIndex = $i % count($variants);
            $message = $variants[$variantIndex];
            
            // Personalize with fan name if available
            if (!empty($fan['name'])) {
                // Add name naturally to some messages (not all)
                if (rand(1, 3) === 1) { // 33% chance
                    $nameInsertions = [
                        " babe",
                        " " . strtolower($fan['name']),
                        " hun"
                    ];
                    $message .= $nameInsertions[array_rand($nameInsertions)];
                }
            }

            // Insert message into database
            $safeMessage = $db->escapeString($message);
            $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) 
                       VALUES ($CREATOR_ID, {$fan['id']}, '$safeMessage', 1, 1, datetime('now'))");
            
            // Track broadcast history
            $db->exec("INSERT INTO broadcast_history (fan_user_id, intent, message_content, sent_at) 
                       VALUES ({$fan['id']}, '$intent', '$safeMessage', datetime('now'))");
            
            $sentCount++;
            
        } catch (Exception $e) {
            $errors[] = "Fan {$fan['id']}: " . $e->getMessage();
        }
    }

    $db->close();

    echo json_encode([
        'success' => true,
        'intent' => $intent,
        'sent_count' => $sentCount,
        'total_fans' => count($fans),
        'variants_generated' => count($variants),
        'errors' => $errors,
        'sample_variants' => array_slice($variants, 0, 3), // Show first 3 variants for debugging
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'error' => 'exception',
        'message' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
}

// Create broadcast_history table on first run (safe migration)
try {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS broadcast_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        fan_user_id INTEGER NOT NULL,
        intent TEXT NOT NULL,
        message_content TEXT,
        sent_at TEXT DEFAULT (datetime('now'))
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_broadcast_history_fan_date ON broadcast_history(fan_user_id, sent_at)");
    $db->close();
} catch (Exception $e) {
    // Ignore table creation errors
}