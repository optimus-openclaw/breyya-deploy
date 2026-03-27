<?php
/**
 * GET /api/chat/last-active.php
 * Returns Breyya status: last message time + typing/pending state
 * Accepts optional ?fan_id= to check typing for specific fan
 */
require_once __DIR__ . "/../lib/database.php";
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$db = getDB();

// Last message from Breyya
$result = $db->querySingle(
    "SELECT created_at FROM messages WHERE sender_id = 1 ORDER BY created_at DESC LIMIT 1",
    true
);
$lastActive = $result ? $result["created_at"] : null;

// Check if currently typing or processing for any fan (or specific fan)
$fanId = isset($_GET["fan_id"]) ? intval($_GET["fan_id"]) : 0;
$isTyping = false;
$hasPending = false;

if ($fanId > 0) {
    $stmt = $db->prepare("SELECT status, typing_until FROM chat_queue WHERE fan_user_id = :fid AND status IN ('pending','scheduled','typing') ORDER BY created_at DESC LIMIT 1");
    $stmt->bindValue(":fid", $fanId, SQLITE3_INTEGER);
} else {
    $stmt = $db->prepare("SELECT status, typing_until FROM chat_queue WHERE status IN ('pending','scheduled','typing') ORDER BY created_at DESC LIMIT 1");
}
$qResult = @$stmt->execute();
$qRow = $qResult ? $qResult->fetchArray(SQLITE3_ASSOC) : null;

if ($qRow) {
    $hasPending = true;
    if ($qRow["status"] === "typing") {
        $isTyping = true;
    }
}

$db->close();

echo json_encode([
    "ok" => true,
    "last_active" => $lastActive,
    "has_pending" => $hasPending,
    "is_typing" => $isTyping
]);
