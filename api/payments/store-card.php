<?php
/**
 * POST /api/payments/store-card.php
 * Store fan's card details after first successful payment for future CBPT charges
 * 
 * Note: This is automatically handled by webhook.php on NewSaleSuccess events.
 * This endpoint exists for manual card storage if needed.
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';

setCorsHeaders();

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Require admin authentication for manual card storage
$currentUser = getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'admin') {
    jsonResponse(['error' => 'Admin access required'], 403);
}

// Get request body
$input = getRequestBody();

$fanUserId = intval($input['fan_user_id'] ?? 0);
$subscriptionId = $input['ccbill_subscription_id'] ?? '';
$cardLastFour = $input['card_last_four'] ?? '';
$cardType = $input['card_type'] ?? '';

if (!$fanUserId || !$subscriptionId) {
    jsonResponse(['error' => 'fan_user_id and ccbill_subscription_id are required'], 400);
}

$db = getDB();

try {
    // Verify the fan user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE id = :uid AND role = 'fan'");
    $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $fan = $result->fetchArray(SQLITE3_ASSOC);

    if (!$fan) {
        jsonResponse(['error' => 'Fan user not found'], 404);
    }

    // Insert or update payment method
    $stmt = $db->prepare("INSERT OR REPLACE INTO fan_payment_methods (fan_user_id, ccbill_subscription_id, card_last_four, card_type, updated_at) VALUES (:uid, :subid, :last4, :type, datetime('now'))");
    $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
    $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
    $stmt->bindValue(':last4', $cardLastFour, SQLITE3_TEXT);
    $stmt->bindValue(':type', $cardType, SQLITE3_TEXT);
    $stmt->execute();

    jsonResponse([
        'success' => true,
        'message' => 'Payment method stored successfully',
        'fan_user_id' => $fanUserId,
        'card_last_four' => $cardLastFour
    ]);

} catch (Exception $e) {
    error_log("Store card error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to store payment method'], 500);
} finally {
    $db->close();
}
?>