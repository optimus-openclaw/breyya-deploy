<?php
/**
 * POST /api/payments/check-payment-method.php
 * Check if a fan has a stored payment method for CBPT charges
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

// Require authentication
$currentUser = getCurrentUser();
if (!$currentUser) {
    jsonResponse(['error' => 'Authentication required'], 401);
}

// Get request body
$input = getRequestBody();
$fanUserId = intval($input['fan_user_id'] ?? 0);

if (!$fanUserId) {
    jsonResponse(['error' => 'Invalid fan_user_id'], 400);
}

$db = getDB();

try {
    // Check if fan has a stored payment method
    $stmt = $db->prepare("SELECT ccbill_subscription_id, card_last_four, card_type FROM fan_payment_methods WHERE fan_user_id = :uid");
    $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $paymentMethod = $result->fetchArray(SQLITE3_ASSOC);

    if ($paymentMethod && $paymentMethod['ccbill_subscription_id']) {
        jsonResponse([
            'success' => true,
            'payment_method' => [
                'card_last_four' => $paymentMethod['card_last_four'],
                'card_type' => $paymentMethod['card_type']
            ]
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'No stored payment method found'
        ]);
    }

} catch (Exception $e) {
    error_log("Check payment method error: " . $e->getMessage());
    jsonResponse(['error' => 'Database error'], 500);
} finally {
    $db->close();
}
?>