<?php
header('Content-Type: application/json');

$SECRET = 'breyya-chat-cron-2026';
$CREATOR_ID = 1;
$DB_PATH = __DIR__ . '/../../data/breyya.db';

// Auth check
$secret = $_GET['secret'] ?? '';
if ($secret !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'no']));
}

$fanCount = intval($_GET['fans'] ?? 50);
$cleanup = ($_GET['cleanup'] ?? 'yes') === 'yes';

if ($fanCount < 1 || $fanCount > 100) {
    http_response_code(400);
    die(json_encode(['error' => 'invalid_fan_count', 'fans' => $fanCount]));
}

try {
    $db = new SQLite3($DB_PATH);
    $db->busyTimeout(5000);
    
    $startTime = microtime(true);
    $testFanIds = [];
    $insertedMessages = 0;
    
    // Sample test messages
    $messages = [
        "hey babe! thinking about you 💕",
        "what are you up to today gorgeous? 😘",
        "can't stop thinking about you 🥺",
        "hey beautiful, how was your day?",
        "missing you babe 😍",
        "you're so perfect 💖",
        "what's your favorite thing to do?",
        "you always make me smile 😊",
        "tell me about your day babe 💕",
        "you're absolutely stunning 🔥"
    ];
    
    // Insert test messages from fake fan IDs
    $insertStart = microtime(true);
    for ($i = 0; $i < $fanCount; $i++) {
        $fanId = 9001 + $i;
        $testFanIds[] = $fanId;
        $message = $messages[array_rand($messages)];
        $safeMessage = $db->escapeString($message);
        
        $result = $db->exec("INSERT INTO messages (sender_id, receiver_id, content, is_ai, is_unlocked, created_at) VALUES ($fanId, $CREATOR_ID, '$safeMessage', 0, 1, datetime('now'))");
        if ($result) {
            $insertedMessages++;
        }
    }
    $insertEnd = microtime(true);
    
    // Trigger go.php processor multiple times
    $processingStart = microtime(true);
    $processingResults = [];
    $totalProcessed = 0;
    $totalErrors = 0;
    
    for ($run = 1; $run <= 10; $run++) {
        $goUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/api/chat/go.php?secret=' . $SECRET;
        $ch = curl_init($goUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $runStart = microtime(true);
        $resp = curl_exec($ch);
        $runEnd = microtime(true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $runTime = ($runEnd - $runStart) * 1000;
        
        if ($httpCode === 200) {
            $data = json_decode($resp, true);
            if ($data && isset($data['processed'])) {
                $processed = intval($data['processed']);
                $errors = intval($data['errors'] ?? 0);
                $totalProcessed += $processed;
                $totalErrors += $errors;
                
                $processingResults[] = [
                    'run' => $run,
                    'processed' => $processed,
                    'errors' => $errors,
                    'time_ms' => round($runTime, 2),
                    'queued' => $data['queued'] ?? 0
                ];
                
                if ($processed === 0) break; // No more to process
            } else {
                $processingResults[] = [
                    'run' => $run,
                    'error' => 'invalid_response',
                    'time_ms' => round($runTime, 2)
                ];
                break;
            }
        } else {
            $processingResults[] = [
                'run' => $run,
                'error' => 'http_error',
                'code' => $httpCode,
                'time_ms' => round($runTime, 2)
            ];
            break;
        }
        
        sleep(1); // 1 second between runs
    }
    
    $processingEnd = microtime(true);
    
    // Check results
    $responseCount = $db->querySingle("SELECT COUNT(*) FROM messages WHERE sender_id = $CREATOR_ID AND receiver_id IN (" . implode(',', $testFanIds) . ") AND created_at >= datetime('now', '-10 minutes')");
    $queueBacklog = $db->querySingle("SELECT COUNT(*) FROM chat_queue WHERE status = 'scheduled'");
    
    // Cleanup if requested
    if ($cleanup && !empty($testFanIds)) {
        $fanIdList = implode(',', $testFanIds);
        $db->exec("DELETE FROM messages WHERE sender_id IN ($fanIdList) OR receiver_id IN ($fanIdList)");
        $db->exec("DELETE FROM chat_queue WHERE fan_message_id IN (SELECT id FROM messages WHERE sender_id IN ($fanIdList))");
    }
    
    $db->close();
    
    // Calculate metrics
    $totalTime = ($processingEnd - $startTime) * 1000;
    $insertTime = ($insertEnd - $insertStart) * 1000;
    $processingTime = ($processingEnd - $processingStart) * 1000;
    $avgResponseTime = $totalProcessed > 0 ? ($processingTime / $totalProcessed) : 0;
    
    $report = [
        'success' => true,
        'test_config' => [
            'fan_count' => $fanCount,
            'cleanup' => $cleanup,
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'timing' => [
            'total_time_ms' => round($totalTime, 2),
            'insert_time_ms' => round($insertTime, 2),
            'processing_time_ms' => round($processingTime, 2),
            'avg_response_time_ms' => round($avgResponseTime, 2)
        ],
        'results' => [
            'messages_inserted' => $insertedMessages,
            'total_processed' => $totalProcessed,
            'total_errors' => $totalErrors,
            'response_count' => intval($responseCount),
            'queue_backlog' => intval($queueBacklog),
            'success_rate' => $totalProcessed > 0 ? round(($totalProcessed - $totalErrors) / $totalProcessed * 100, 2) : 0
        ],
        'processing_runs' => $processingResults,
        'queue_analysis' => [
            'current_limit' => 20, // Updated from 5 to 20
            'messages_per_run' => $totalProcessed > 0 ? round($totalProcessed / count($processingResults), 2) : 0,
            'capacity_5min' => $totalProcessed > 0 ? round($totalProcessed * (300 / ($processingTime / 1000)), 0) : 0,
            'meets_200_msg_requirement' => ($totalProcessed * (300 / ($processingTime / 1000))) >= 200
        ]
    ];
    
    echo json_encode($report, JSON_PRETTY_PRINT);
    
} catch (Throwable $e) {
    echo json_encode([
        'error' => 'exception',
        'message' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
}
?>