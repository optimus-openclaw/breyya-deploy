<?php
/**
 * POST /api/auth/forgot-password
 * Request password reset email
 * 
 * Always returns success to prevent email enumeration
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body = getRequestBody();
$email = trim($body['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Valid email required'], 400);
}

$db = getDB();

// Find user by email
$stmt = $db->prepare('SELECT id, email, display_name FROM users WHERE email = :email AND is_active = 1');
$stmt->bindValue(':email', strtolower($email), SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if ($user) {
    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Store reset token
    $stmt = $db->prepare('INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (:uid, :token, :expires)');
    $stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->bindValue(':expires', $expiresAt, SQLITE3_TEXT);
    $stmt->execute();
    
    // Send email
    $resetLink = "https://breyya.com/reset-password/?token=" . urlencode($token);
    $name = $user['display_name'] ?: explode('@', $user['email'])[0];
    
    $subject = "Reset your Breyya password";
    $message = "Hi $name,\n\n";
    $message .= "Click here to reset your password: $resetLink\n\n";
    $message .= "This link expires in 1 hour.\n\n";
    $message .= "If you didn't request this, you can safely ignore this email.\n\n";
    $message .= "- Team Breyya";
    
    $headers = "From: support@breyya.com\r\n";
    $headers .= "Reply-To: support@breyya.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Attempt to send email
    $emailSent = mail($user['email'], $subject, $message, $headers);
    
    // Log email attempt
    error_log("Password reset email " . ($emailSent ? "sent" : "failed") . " for user: " . $user['email']);
}

$db->close();

// Always return success to prevent email enumeration
jsonResponse([
    'ok' => true,
    'message' => 'If an account with that email exists, we sent you a password reset link.'
]);