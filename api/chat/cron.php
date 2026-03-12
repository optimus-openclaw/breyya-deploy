<?php
/**
 * CLI cron wrapper for chat queue processor
 * Called by Hostinger cron: /usr/bin/php /home/u708448327/domains/breyya.com/public_html/api/chat/cron.php
 * 
 * Sets up the cron secret and calls process.php via internal include
 */

// Simulate the GET parameter for the processor
$_GET['secret'] = 'breyya-cron-2026-q7z';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Load and execute the processor
require_once __DIR__ . '/process.php';
