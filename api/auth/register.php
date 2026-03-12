<?php
/**
 * POST /api/auth/register
 * Create a new fan account
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body = getRequestBody();
$email = trim($body['email'] ?? '');
$password = $body['password'] ?? '';
$displayName = trim($body['display_name'] ?? '');

// Validation
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Valid email required'], 400);
}
if (strlen($password) < 8) {
    jsonResponse(['error' => 'Password must be at least 8 characters'], 400);
}
if (!$displayName) {
    $displayName = explode('@', $email)[0];
}

$db = getDB();

// Check if email exists
$stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
$stmt->bindValue(':email', strtolower($email), SQLITE3_TEXT);
$result = $stmt->execute();
if ($result->fetchArray()) {
    $db->close();
    jsonResponse(['error' => 'Email already registered'], 409);
}

// Create user
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $db->prepare('INSERT INTO users (email, password_hash, display_name, role) VALUES (:email, :hash, :name, :role)');
$stmt->bindValue(':email', strtolower($email), SQLITE3_TEXT);
$stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
$stmt->bindValue(':name', $displayName, SQLITE3_TEXT);
$stmt->bindValue(':role', 'fan', SQLITE3_TEXT);
$stmt->execute();

$userId = $db->lastInsertRowID();
$db->close();

// Create token and set cookie
$token = createToken($userId, 'fan');
setAuthCookie($token);

jsonResponse([
    'ok' => true,
    'user' => [
        'id' => $userId,
        'email' => strtolower($email),
        'display_name' => $displayName,
        'role' => 'fan',
    ],
    'token' => $token,
], 201);
