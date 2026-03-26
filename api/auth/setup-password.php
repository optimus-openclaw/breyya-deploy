<?php
require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body = getRequestBody();
$email = strtolower(trim($body['email'] ?? ''));
$password = $body['password'] ?? '';

if (!$email || !$password) {
    jsonResponse(['error' => 'Email and password required'], 400);
}

if (strlen($password) < 8) {
    jsonResponse(['error' => 'Password must be at least 8 characters'], 400);
}

try {
    $db = getDB();

    // Ensure password_set column exists
    try {
        $db->exec("ALTER TABLE users ADD COLUMN password_set INTEGER DEFAULT 0");
    } catch (Exception $e) {
        // Column already exists, ignore
    }

    // Find user by email (case-insensitive) with active subscription and password not set
    $stmt = $db->prepare('
        SELECT u.id, u.email, u.display_name, u.role, u.avatar_url
        FROM users u
        LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = "active" AND s.expires_at > datetime("now")
        WHERE LOWER(u.email) = :email
        AND (u.password_set = 0 OR u.password_set IS NULL)
        LIMIT 1
    ');
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        // Try without subscription check — maybe webhook didn't create subscription
        $stmt = $db->prepare('
            SELECT id, email, display_name, role, avatar_url
            FROM users
            WHERE LOWER(email) = :email
            AND (password_set = 0 OR password_set IS NULL)
            LIMIT 1
        ');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
    }

    if (!$user) {
        jsonResponse(['error' => 'No new account found for this email. Please check your email or contact support@breyya.com'], 400);
    }

    // Set the password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('UPDATE users SET password_hash = :hash, password_set = 1 WHERE id = :id');
    $stmt->bindValue(':hash', $passwordHash, SQLITE3_TEXT);
    $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
    $stmt->execute();

    $db->close();

    // Create JWT and set cookie
    $token = createToken($user['id'], $user['role']);
    setAuthCookie($token);

    jsonResponse([
        'ok' => true,
        'message' => 'Password set successfully! You are now logged in.',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
            'avatar_url' => $user['avatar_url'] ?? null,
        ],
        'token' => $token,
    ]);
} catch (Exception $e) {
    error_log("setup-password error: " . $e->getMessage());
    jsonResponse(['error' => 'Something went wrong. Please try again.'], 500);
}
