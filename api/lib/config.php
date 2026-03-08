<?php
/**
 * Breyya.com — Configuration
 * API keys loaded from environment or local secrets file
 */

// Load secrets from a .gitignored file if it exists
$secretsFile = __DIR__ . '/../../.secrets.php';
if (file_exists($secretsFile)) {
    require_once $secretsFile;
}

// OpenAI
if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
}
define('OPENAI_MODEL', 'gpt-4.1-mini');
define('OPENAI_MAX_TOKENS', 150);
define('OPENAI_TEMPERATURE', 0.8);

// Chat processing secret (protect cron endpoint)
if (!defined('CHAT_CRON_SECRET')) {
    define('CHAT_CRON_SECRET', getenv('CHAT_CRON_SECRET') ?: 'breyya-cron-2026-q7z');
}

// Creator user ID (convention: first user created is the creator)
define('CREATOR_USER_ID', 1);
