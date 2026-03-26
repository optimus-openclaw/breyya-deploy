<?php
/**
 * GET /api/auth/check-status.php
 * Check user authentication and subscription status
 * Used by frontend to show/hide JOIN button
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

// Get current user (no auth required - returns null if not authenticated)
$user = getCurrentUser();

if (!$user) {
    // Not logged in
    jsonResponse([
        'logged_in' => false,
        'has_subscription' => false,
        'display_name' => null,
        'subscription_expires' => null
    ]);
    exit;
}

$db = getDB();

try {
    // Check if user has active subscription
    $stmt = $db->prepare("SELECT expires_at FROM subscriptions WHERE user_id = :uid AND status = 'active' AND expires_at > datetime('now') ORDER BY expires_at DESC LIMIT 1");
    $stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $subscription = $result->fetchArray(SQLITE3_ASSOC);

    $hasSubscription = $subscription !== false;
    $expiresAt = $hasSubscription ? $subscription['expires_at'] : null;

    jsonResponse([
        'logged_in' => true,
        'has_subscription' => $hasSubscription,
        'display_name' => $user['display_name'] ?: $user['email'],
        'subscription_expires' => $expiresAt
    ]);

} catch (Exception $e) {
    error_log("Check status error: " . $e->getMessage());
    jsonResponse(['error' => 'Failed to check status'], 500);
} finally {
    $db->close();
}
?>