<?php
/**
 * Breyya.com Configuration
 */

// Environment
define('ENV', 'production'); // 'development' or 'production'

// Database
define('DATABASE_PATH', '/home/u726473634/domains/breyya.com/site_database.db');

// OpenAI
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'sk-PLACEHOLDER');
define('OPENAI_MODEL', 'gpt-4o-mini');

// Cron secrets
define('CHAT_CRON_SECRET', 'breyya-chat-cron-2026');

// User IDs
define('CREATOR_USER_ID', 1); // Breyya's user ID

// Auth
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'breyya-jwt-secret-2026');
define('SESSION_TIMEOUT', 3600 * 24 * 30); // 30 days

// CCBill
define('CCBILL_ACCOUNT', '951821');
define('CCBILL_SUBACCOUNT', '0000');
define('CCBILL_FLEXFORMS_ID', '08c1b7e7-4885-4b46-8f30-5e0aa0df0b83');
define('CCBILL_SALT_KEY', getenv('CCBILL_SALT_KEY') ?: 'PLACEHOLDER');

// Site settings
define('SITE_NAME', 'Breyya');
define('SITE_URL', 'https://breyya.com');
define('SUPPORT_EMAIL', 'support@breyya.com');
define('SUBSCRIPTION_PRICE', 20.00);