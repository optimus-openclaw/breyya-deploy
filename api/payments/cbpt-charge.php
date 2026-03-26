<?php
/**
 * POST /api/payments/cbpt-charge.php
 * CCBill Charge By Previous Transaction ID API
 * 
 * Charges a fan's stored payment method using their previous subscription ID
 * No FlexForm needed for repeat purchases
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/cbpt.php';
require_once __DIR__ . '/../lib/config.php';

setCorsHeaders();

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Get request body
$input = getRequestBody();

// Validate input
$fanUserId = intval($input['fan_user_id'] ?? 0);
$amount = floatval($input['amount'] ?? 0);
$description = $input['description'] ?? 'One-time charge';

// Check authentication (logged-in user OR secret for testing)
$currentUser = getCurrentUser();
$secretProvided = $input['secret'] ?? '';
$cronSecret = defined('CHAT_CRON_SECRET') ? CHAT_CRON_SECRET : 'breyya-chat-cron-2026';

if (!$currentUser && $secretProvided !== $cronSecret) {
    jsonResponse(['error' => 'Authentication required'], 401);
}

$db = getDB();

try {
    // Ensure fan_payment_methods table exists
    $db->exec("CREATE TABLE IF NOT EXISTS fan_payment_methods (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        fan_user_id INTEGER NOT NULL,
        ccbill_subscription_id TEXT,
        card_last_four TEXT,
        card_type TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(fan_user_id)
    )");

    // Use the shared CBPT function
    $result = chargeCBPT($fanUserId, $amount, $description, $db);

    if ($result['success']) {
        // Additional tip-specific logic
        $amountCents = intval($amount * 100);
        
        // Ensure tip_events table exists
        $db->exec("CREATE TABLE IF NOT EXISTS tip_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fan_user_id INTEGER NOT NULL,
            amount_cents INTEGER NOT NULL,
            purpose TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $db->prepare("INSERT INTO tip_events (fan_user_id, amount_cents, purpose) VALUES (:uid, :amount, :msg)");
        $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
        $stmt->bindValue(':amount', $amountCents, SQLITE3_INTEGER);
        $stmt->bindValue(':msg', $description, SQLITE3_TEXT);
        $stmt->execute();

        jsonResponse([
            'success' => true,
            'amount' => $result['amount'],
            'description' => $result['description'],
            'subscription_id' => $result['subscription_id'],
            'card_last_four' => $result['card_last_four']
        ]);

    } else {
        jsonResponse([
            'success' => false,
            'error' => $result['error'],
            'decline_code' => $result['decline_code'] ?? ''
        ], 402);
    }

} catch (\Throwable $e) {
    error_log("CBPT Charge Exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    jsonResponse(['error' => 'Payment processing failed: ' . $e->getMessage()], 500);
} finally {
    if (isset($db)) $db->close();
}
?>