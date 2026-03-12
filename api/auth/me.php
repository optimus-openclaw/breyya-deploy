<?php
/**
 * GET /api/auth/me
 * Get current authenticated user + subscription status
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

$user = requireAuth();

// Get subscription status
$hasSubscription = hasActiveSubscription($user['id']);

// Get subscription details if active
$subDetails = null;
if ($hasSubscription) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE user_id = :uid AND status = 'active' AND expires_at > datetime('now') ORDER BY expires_at DESC LIMIT 1");
    $stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $subDetails = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
}

jsonResponse([
    'ok' => true,
    'user' => $user,
    'subscription' => [
        'active' => $hasSubscription,
        'expires_at' => $subDetails['expires_at'] ?? null,
        'plan' => $subDetails['plan'] ?? null,
    ],
]);
