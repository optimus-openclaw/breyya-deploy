<?php
// One-time secrets bootstrap — POST the key to this endpoint
// Usage: curl -X POST https://breyya.com/api/setup-secrets.php -d "token=SETUP2026&key=YOUR_KEY"
$SETUP_TOKEN = 'SETUP2026-breyya-safe';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['token'] ?? '') !== $SETUP_TOKEN) {
    http_response_code(403);
    die('Forbidden');
}
$key = trim($_POST['key'] ?? '');
if (strlen($key) < 50) { die('Invalid key'); }
$secrets = "<?php\ndefine('AI_API_KEY', '" . addslashes($key) . "');\n";
$target = __DIR__ . '/.secrets.php';
file_put_contents($target, $secrets);
echo "OK: .secrets.php written\n";
unlink(__FILE__);
echo "Setup script deleted.\n";
