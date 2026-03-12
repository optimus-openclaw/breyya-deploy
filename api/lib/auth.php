<?php
/**
 * Breyya.com — Authentication Layer
 * JWT-based auth with httpOnly cookies
 */

require_once __DIR__ . '/database.php';

define('JWT_SECRET', getenv('BREYYA_JWT_SECRET') ?: 'breyya_dev_secret_change_in_production_2026');
define('JWT_EXPIRY', 86400 * 30); // 30 days
define('COOKIE_NAME', 'breyya_token');

/**
 * Create a JWT token
 */
function createToken(int $userId, string $role): string {
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload = base64url_encode(json_encode([
        'sub' => $userId,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRY,
    ]));
    $signature = base64url_encode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );
    return "$header.$payload.$signature";
}

/**
 * Verify and decode a JWT token
 */
function verifyToken(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $payload, $signature] = $parts;

    $expectedSig = base64url_encode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );

    if (!hash_equals($expectedSig, $signature)) return null;

    $data = json_decode(base64url_decode($payload), true);
    if (!$data || !isset($data['exp']) || $data['exp'] < time()) return null;

    return $data;
}

/**
 * Get current authenticated user from cookie or Authorization header
 */
function getCurrentUser(): ?array {
    $token = $_COOKIE[COOKIE_NAME] ?? null;

    // Also check Authorization header
    if (!$token) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        }
    }

    if (!$token) return null;

    $decoded = verifyToken($token);
    if (!$decoded) return null;

    $db = getDB();
    $stmt = $db->prepare('SELECT id, email, display_name, role, avatar_url, is_active, created_at FROM users WHERE id = :id AND is_active = 1');
    $stmt->bindValue(':id', $decoded['sub'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();

    return $user ?: null;
}

/**
 * Require authentication — returns user or sends 401
 */
function requireAuth(): array {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    return $user;
}

/**
 * Require creator role
 */
function requireCreator(): array {
    $user = requireAuth();
    if ($user['role'] !== 'creator' && $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Creator access required']);
        exit;
    }
    return $user;
}

/**
 * Check if user has active subscription
 */
function hasActiveSubscription(int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM subscriptions WHERE user_id = :uid AND status = 'active' AND expires_at > datetime('now') LIMIT 1");
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $sub = $result->fetchArray();
    $db->close();
    return $sub !== false;
}

/**
 * Set auth cookie
 */
function setAuthCookie(string $token): void {
    setcookie(COOKIE_NAME, $token, [
        'expires' => time() + JWT_EXPIRY,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Clear auth cookie
 */
function clearAuthCookie(): void {
    setcookie(COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Base64url encode/decode helpers
 */
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get JSON request body
 */
function getRequestBody(): array {
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?: [];
}

/**
 * CORS headers
 */
function setCorsHeaders(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = ['https://breyya.com', 'https://www.breyya.com', 'http://localhost:3333'];

    if (in_array($origin, $allowed)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
