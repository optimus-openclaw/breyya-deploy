<?php
/**
 * GET /api/chat/status.php
 * Returns chat status for the current user:
 * - whether Breyya has a pending (scheduled but not yet delivered) response
 * - fan classification
 * - used by frontend for "typing" indicator
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/fan_profiles.php';

setCorsHeaders();

$user = requireAuth();

$db = getDB();

// Ensure chat_queue table exists
$db->exec("CREATE TABLE IF NOT EXISTS chat_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fan_message_id INTEGER NOT NULL,
    fan_user_id INTEGER NOT NULL,
    status TEXT DEFAULT 'pending',
    scheduled_at TEXT DEFAULT NULL,
    ai_response TEXT DEFAULT '',
    ai_response_2 TEXT DEFAULT '',
    delivered_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    UNIQUE(fan_message_id)
)");

// Check for pending/scheduled responses for this user
$stmt = $db->prepare("
    SELECT COUNT(*) as pending_count
    FROM chat_queue 
    WHERE fan_user_id = :uid 
      AND status = 'scheduled'
");
$stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$pendingCount = intval($row['pending_count'] ?? 0);

// Check if any scheduled response is "almost ready" (within 2 minutes)
$stmt = $db->prepare("
    SELECT COUNT(*) as typing_count
    FROM chat_queue 
    WHERE fan_user_id = :uid 
      AND status = 'scheduled'
      AND scheduled_at <= datetime('now', '+2 minutes')
");
$stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$typingCount = intval($row['typing_count'] ?? 0);

$db->close();

$classification = classifyFan($user['id']);

jsonResponse([
    'ok' => true,
    'has_pending' => $pendingCount > 0,
    'is_typing' => $typingCount > 0,
    'fan_classification' => $classification,
]);
