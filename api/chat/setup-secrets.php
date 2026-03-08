<?php
/**
 * ONE-TIME setup endpoint to create .secrets.php on the server
 * DELETE THIS FILE after use!
 * 
 * POST /api/chat/setup-secrets.php
 * Body: {"setup_key": "breyya-setup-2026-x9k", "openai_key": "sk-..."}
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$setupKey = $body['setup_key'] ?? '';

// Same setup key used for admin account creation
if ($setupKey !== 'breyya-setup-2026-x9k') {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid setup key']);
    exit;
}

$openaiKey = trim($body['openai_key'] ?? '');
$cronSecret = trim($body['cron_secret'] ?? 'breyya-cron-2026-q7z');

if (!$openaiKey) {
    http_response_code(400);
    echo json_encode(['error' => 'openai_key required']);
    exit;
}

$secretsPath = __DIR__ . '/../../.secrets.php';
$content = "<?php\n// Breyya.com Secrets — auto-generated\ndefine('OPENAI_API_KEY', '$openaiKey');\ndefine('CHAT_CRON_SECRET', '$cronSecret');\n";

$written = file_put_contents($secretsPath, $content);
if ($written === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write secrets file']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Secrets file created. DELETE this endpoint now!']);
