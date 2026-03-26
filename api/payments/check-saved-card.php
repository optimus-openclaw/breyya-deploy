<?php
/**
 * GET /api/payments/check-saved-card.php
 * Check if the logged-in fan has a saved payment method
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';

setCorsHeaders();

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Require authentication
$user = requireAuth();

if ($user['role'] !== 'fan') {
    jsonResponse(['error' => 'Only fans can check payment methods'], 403);
}

$db = getDB();

try {
    $stmt = $db->prepare("SELECT ccbill_subscription_id, card_last_four, card_type FROM fan_payment_methods WHERE fan_user_id = :uid LIMIT 1");
    $stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $paymentMethod = $result->fetchArray(SQLITE3_ASSOC);

    if ($paymentMethod && $paymentMethod['ccbill_subscription_id']) {
        jsonResponse([
            'has_saved_card' => true,
            'card_type' => $paymentMethod['card_type'] ?? 'Card',
            'card_last_four' => $paymentMethod['card_last_four'] ?? '****'
        ]);
    } else {
        jsonResponse([
            'has_saved_card' => false,
            'card_type' => null,
            'card_last_four' => null
        ]);
    }

} catch (Exception $e) {
    error_log("Check saved card error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to check payment methods'], 500);
} finally {
    $db->close();
}
?>