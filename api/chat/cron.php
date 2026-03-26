<?php
/**
 * CLI cron wrapper for chat queue processor
 * Called by Hostinger cron: /usr/bin/php /home/u708448327/domains/breyya.com/public_html/api/chat/cron.php
 * 
 * Calls go.php which is the primary chat processor with Sonnet, PPV, voice notes, etc.
 */

// Simulate the GET parameter for go.php
$_GET['secret'] = 'breyya-chat-cron-2026';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Load and execute the primary processor
require_once __DIR__ . '/go.php';
