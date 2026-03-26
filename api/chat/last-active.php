<?php
/**
 * GET /api/chat/last-active.php
 * Returns when Breyya last sent a message (for dynamic status)
 * No auth required — status is public info
 */
require_once __DIR__ . '/../lib/database.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db = getDB();
$result = $db->querySingle(
    "SELECT created_at FROM messages WHERE sender_id = 1 ORDER BY created_at DESC LIMIT 1",
    true
);
$db->close();

$lastActive = $result ? $result['created_at'] : null;
echo json_encode(['ok' => true, 'last_active' => $lastActive]);
