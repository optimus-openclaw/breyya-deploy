<?php
/**
 * GET /api/messages/list
 * Get conversation messages between fan and creator
 * Query params: ?page=1&limit=50&test_fan_mode=1
 * 
 * When admin passes test_fan_mode=1, shows the test fan (id 9999) conversation
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

$user = requireAuth();

$creatorId = 1;
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;
$testFanMode = !empty($_GET['test_fan_mode']);

// Determine the fan ID for this conversation
if ($testFanMode && ($user['role'] === 'creator' || $user['role'] === 'admin')) {
    // Admin viewing test fan conversation
    $fanId = 9999;
} elseif ($user['role'] === 'creator' || $user['role'] === 'admin') {
    // Admin viewing: show most recent fan conversation, or test fan if no other fans
    $db = getDB();
    $fanStmt = $db->prepare("
        SELECT DISTINCT CASE 
            WHEN sender_id = :cid THEN receiver_id 
            ELSE sender_id 
        END as fan_id
        FROM messages
        WHERE sender_id = :cid2 OR receiver_id = :cid3
        ORDER BY id DESC
        LIMIT 1
    ");
    $fanStmt->bindValue(':cid', $creatorId, SQLITE3_INTEGER);
    $fanStmt->bindValue(':cid2', $creatorId, SQLITE3_INTEGER);
    $fanStmt->bindValue(':cid3', $creatorId, SQLITE3_INTEGER);
    $fanResult = $fanStmt->execute();
    $fanRow = $fanResult->fetchArray(SQLITE3_ASSOC);
    $fanId = $fanRow ? intval($fanRow['fan_id']) : 9999;
    // Don't show creator-to-creator messages
    if ($fanId === $creatorId) $fanId = 9999;
    $db->close();
} else {
    $fanId = $user['id'];
}

$db = getDB();

// Get messages in this conversation
$stmt = $db->prepare("
    SELECT m.*, 
           s.display_name as sender_name, s.avatar_url as sender_avatar,
           r.display_name as receiver_name
    FROM messages m
    JOIN users s ON m.sender_id = s.id
    JOIN users r ON m.receiver_id = r.id
    WHERE (m.sender_id = :fan AND m.receiver_id = :cid)
       OR (m.sender_id = :cid2 AND m.receiver_id = :fan2)
    ORDER BY m.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':fan', $fanId, SQLITE3_INTEGER);
$stmt->bindValue(':fan2', $fanId, SQLITE3_INTEGER);
$stmt->bindValue(':cid', $creatorId, SQLITE3_INTEGER);
$stmt->bindValue(':cid2', $creatorId, SQLITE3_INTEGER);
$stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

$messages = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    // If PPV and not unlocked and not the sender, hide media
    if ($row['is_ppv'] && !$row['is_unlocked'] && $row['sender_id'] != $user['id']) {
        $row['media_url'] = '';
        $row['locked'] = true;
    } else {
        $row['locked'] = false;
    }
    $messages[] = $row;
}

// Mark messages as read
$readStmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = :cid AND sender_id = :fan AND is_read = 0");
$readStmt->bindValue(':cid', $creatorId, SQLITE3_INTEGER);
$readStmt->bindValue(':fan', $fanId, SQLITE3_INTEGER);
$readStmt->execute();

$db->close();

// Reverse so oldest first (for chat display)
$messages = array_reverse($messages);

// Check for pending AI responses in the queue for this fan
$hasPending = false;
$isTyping = false;
try {
    $db2 = getDB();
    $qStmt = $db2->prepare("SELECT COUNT(*) as cnt FROM chat_queue WHERE fan_user_id = :uid AND status = 'scheduled'");
    $qStmt->bindValue(':uid', $fanId, SQLITE3_INTEGER);
    $qResult = $qStmt->execute();
    $qRow = $qResult->fetchArray(SQLITE3_ASSOC);
    $hasPending = intval($qRow['cnt'] ?? 0) > 0;

    // "Typing" if response due within 2 minutes
    $tStmt = $db2->prepare("SELECT COUNT(*) as cnt FROM chat_queue WHERE fan_user_id = :uid AND status = 'scheduled' AND scheduled_at <= datetime('now', '+2 minutes')");
    $tStmt->bindValue(':uid', $fanId, SQLITE3_INTEGER);
    $tResult = $tStmt->execute();
    $tRow = $tResult->fetchArray(SQLITE3_ASSOC);
    $isTyping = intval($tRow['cnt'] ?? 0) > 0;
    $db2->close();
} catch (Exception $e) {
    // chat_queue table may not exist yet
}

jsonResponse([
    'ok' => true,
    'messages' => $messages,
    'has_pending' => $hasPending,
    'is_typing' => $isTyping,
    'test_fan_mode' => $testFanMode,
    'viewing_fan_id' => $fanId,
]);
