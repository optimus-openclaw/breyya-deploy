<?php
header('Content-Type: application/json');

$SECRET = 'breyya-chat-cron-2026';
$DB_PATH = __DIR__ . '/../../data/breyya.db';

// Simple auth check via secret
$secret = $_GET['secret'] ?? '';
if ($secret !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'unauthorized']));
}

$fanId = intval($_GET['fan_id'] ?? 0);
if ($fanId <= 0) {
    http_response_code(400);
    die(json_encode(['error' => 'invalid fan_id']));
}

try {
    $db = new SQLite3($DB_PATH);
    $db->busyTimeout(5000);
    
    // Get recent tips (last 30 days)
    $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
    $recentTips = [];
    
    $r = $db->query("SELECT amount_cents, purpose, created_at FROM tip_events WHERE fan_user_id = $fanId AND created_at >= '$thirtyDaysAgo' ORDER BY created_at DESC");
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $recentTips[] = [
            'amount' => $row['amount_cents'] / 100, // Convert to dollars
            'purpose' => $row['purpose'],
            'date' => $row['created_at']
        ];
    }
    
    // Get total tipped (all time)
    $totalTipped = ($db->querySingle("SELECT SUM(amount_cents) FROM tip_events WHERE fan_user_id = $fanId") ?? 0) / 100;
    
    // Check if fan is eligible for ratings (tipped >= $20 in last hour)
    $hourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $recentTipAmount = ($db->querySingle("SELECT SUM(amount_cents) FROM tip_events WHERE fan_user_id = $fanId AND created_at >= '$hourAgo'") ?? 0) / 100;
    $ratingEligible = $recentTipAmount >= 20;
    
    $db->close();
    
    echo json_encode([
        'ok' => true,
        'fan_id' => $fanId,
        'recent_tips' => $recentTips,
        'total_tipped' => $totalTipped,
        'rating_eligible' => $ratingEligible,
        'recent_tip_amount' => $recentTipAmount,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'database_error', 'message' => $e->getMessage()]);
}