<?php
/**
 * GET /api/posts/list
 * Get feed posts — requires active subscription (unless free posts)
 * Query params: ?page=1&limit=20&free_only=0
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

$user = getCurrentUser();
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$freeOnly = intval($_GET['free_only'] ?? 0);

// If not logged in or no subscription, only show free posts
$showPaid = false;
if ($user) {
    $showPaid = hasActiveSubscription($user['id']) || $user['role'] === 'creator' || $user['role'] === 'admin';
}

$db = getDB();

// Build query
$where = "(scheduled_at IS NULL OR scheduled_at <= datetime('now'))";
// Exclude PPV posts unless explicitly requested (feed handles PPV display, gallery does not)
$includePpv = intval($_GET['include_ppv'] ?? 0);
if (!$includePpv) {
    $where .= " AND (is_ppv IS NULL OR is_ppv = 0)";
}
if ($freeOnly || !$showPaid) {
    $where .= " AND is_free = 1";
}

$stmt = $db->prepare("SELECT p.*, u.display_name as creator_name, u.avatar_url as creator_avatar FROM posts p JOIN users u ON p.creator_id = u.id WHERE $where ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

$posts = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    // Check if current user liked this post
    $liked = false;
    if ($user) {
        $likeStmt = $db->prepare('SELECT id FROM post_likes WHERE post_id = :pid AND user_id = :uid');
        $likeStmt->bindValue(':pid', $row['id'], SQLITE3_INTEGER);
        $likeStmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
        $likeResult = $likeStmt->execute();
        $liked = $likeResult->fetchArray() !== false;
    }

    // If post is paid and user doesn't have subscription, blur the media
    if (!$row['is_free'] && !$showPaid) {
        $row['media_url'] = '';
        $row['is_locked'] = true;
    } else {
        $row['is_locked'] = false;
    }

    $row['liked'] = $liked;
    $posts[] = $row;
}

// Get total count
$countResult = $db->querySingle("SELECT COUNT(*) FROM posts WHERE $where");
$db->close();

jsonResponse([
    'ok' => true,
    'posts' => $posts,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $countResult,
        'has_more' => ($offset + $limit) < $countResult,
    ],
    'has_subscription' => $showPaid,
]);
