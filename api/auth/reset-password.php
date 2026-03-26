<?php
/**
 * POST /api/auth/reset-password
 * Reset password using token
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body = getRequestBody();
$token = trim($body['token'] ?? '');
$newPassword = $body['new_password'] ?? '';

if (!$token || !$newPassword) {
    jsonResponse(['error' => 'Token and new password required'], 400);
}

if (strlen($newPassword) < 8) {
    jsonResponse(['error' => 'Password must be at least 8 characters'], 400);
}

$db = getDB();

// Find valid, unused token
$stmt = $db->prepare('
    SELECT rt.id as token_id, rt.user_id, u.email, u.display_name, u.role, u.avatar_url
    FROM password_reset_tokens rt
    JOIN users u ON rt.user_id = u.id
    WHERE rt.token = :token 
    AND rt.used = 0 
    AND rt.expires_at > datetime("now")
    AND u.is_active = 1
    LIMIT 1
');
$stmt->bindValue(':token', $token, SQLITE3_TEXT);
$result = $stmt->execute();
$tokenData = $result->fetchArray(SQLITE3_ASSOC);

if (!$tokenData) {
    jsonResponse(['error' => 'Invalid or expired reset token'], 400);
}

// Update password
$passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
$stmt = $db->prepare('UPDATE users SET password_hash = :hash, password_set = 1, updated_at = datetime("now") WHERE id = :id');
$stmt->bindValue(':hash', $passwordHash, SQLITE3_TEXT);
$stmt->bindValue(':id', $tokenData['user_id'], SQLITE3_INTEGER);
$stmt->execute();

// Mark token as used
$stmt = $db->prepare('UPDATE password_reset_tokens SET used = 1 WHERE id = :id');
$stmt->bindValue(':id', $tokenData['token_id'], SQLITE3_INTEGER);
$stmt->execute();

$db->close();

// Create JWT token and set cookie
$jwtToken = createToken($tokenData['user_id'], $tokenData['role']);
setAuthCookie($jwtToken);

jsonResponse([
    'ok' => true,
    'message' => 'Password reset successfully! You are now logged in.',
    'user' => [
        'id' => $tokenData['user_id'],
        'email' => $tokenData['email'],
        'display_name' => $tokenData['display_name'],
        'role' => $tokenData['role'],
        'avatar_url' => $tokenData['avatar_url'],
    ],
    'token' => $jwtToken,
]);