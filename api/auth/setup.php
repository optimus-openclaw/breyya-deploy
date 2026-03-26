<?php
/**
 * POST /api/auth/setup
 * One-time setup: create the creator/admin account
 * Protected by setup key — only works if no admin exists yet
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body = getRequestBody();
$setupKey = $body['setup_key'] ?? '';

// Setup key — change this or remove after first use
if ($setupKey !== 'breyya-setup-2026-x9k') {
    jsonResponse(['error' => 'Invalid setup key'], 403);
}

$email = trim($body['email'] ?? '');
$password = $body['password'] ?? '';
$displayName = trim($body['display_name'] ?? 'Breyya');

if (!$email || !$password) {
    jsonResponse(['error' => 'Email and password required'], 400);
}

$db = getDB();

// Check if admin already exists
$result = $db->query("SELECT id FROM users WHERE role = 'creator' OR role = 'admin' LIMIT 1");
if ($result->fetchArray()) {
    $db->close();
    jsonResponse(['error' => 'Admin account already exists'], 409);
}

// Create admin/creator account
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $db->prepare("INSERT INTO users (email, password_hash, display_name, role, avatar_url) VALUES (:email, :hash, :name, 'creator', '/images/hero.jpg')");
$stmt->bindValue(':email', strtolower($email), SQLITE3_TEXT);
$stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
$stmt->bindValue(':name', $displayName, SQLITE3_TEXT);
$stmt->execute();

$userId = $db->lastInsertRowID();
$db->close();

$token = createToken($userId, 'creator');
setAuthCookie($token);

jsonResponse([
    'ok' => true,
    'message' => 'Creator account created successfully',
    'user' => [
        'id' => $userId,
        'email' => strtolower($email),
        'display_name' => $displayName,
        'role' => 'creator',
    ],
    'token' => $token,
], 201);
