<?php
/**
 * POST /api/auth/login
 * Authenticate and return token
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body = getRequestBody();
$email = trim($body['email'] ?? '');
$password = $body['password'] ?? '';

if (!$email || !$password) {
    jsonResponse(['error' => 'Email and password required'], 400);
}

$db = getDB();
$stmt = $db->prepare('SELECT id, email, password_hash, display_name, role, avatar_url, is_active FROM users WHERE email = :email');
$stmt->bindValue(':email', strtolower($email), SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);
$db->close();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse(['error' => 'Invalid email or password'], 401);
}

if (!$user['is_active']) {
    jsonResponse(['error' => 'Account is deactivated'], 403);
}

$token = createToken($user['id'], $user['role']);
setAuthCookie($token);

jsonResponse([
    'ok' => true,
    'user' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'display_name' => $user['display_name'],
        'role' => $user['role'],
        'avatar_url' => $user['avatar_url'],
    ],
    'token' => $token,
]);
