<?php
/**
 * GET /api/messages/list
 * Get conversation messages between fan and creator
 * Query params: ?page=1&limit=50
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

$user = requireAuth();

// Fans can only message the creator (user id 1 by convention)
$creatorId = 1;
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

$db = getDB();

// Get messages in this conversation
$stmt = $db->prepare("
    SELECT m.*, 
           s.display_name as sender_name, s.avatar_url as sender_avatar,
           r.display_name as receiver_name
    FROM messages m
    JOIN users s ON m.sender_id = s.id
    JOIN users r ON m.receiver_id = r.id
    WHERE (m.sender_id = :uid AND m.receiver_id = :cid)
       OR (m.sender_id = :cid2 AND m.receiver_id = :uid2)
    ORDER BY m.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
$stmt->bindValue(':uid2', $user['id'], SQLITE3_INTEGER);
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
$readStmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = :uid AND sender_id = :cid AND is_read = 0");
$readStmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
$readStmt->bindValue(':cid', $creatorId, SQLITE3_INTEGER);
$readStmt->execute();

$db->close();

// Reverse so oldest first (for chat display)
$messages = array_reverse($messages);

jsonResponse([
    'ok' => true,
    'messages' => $messages,
]);
