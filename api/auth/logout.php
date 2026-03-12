<?php
/**
 * POST /api/auth/logout
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

clearAuthCookie();
jsonResponse(['ok' => true]);
