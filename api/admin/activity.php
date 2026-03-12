<?php
/**
 * GET /api/admin/activity
 * Returns recent activity feed (messages, tips, PPV unlocks, subscriptions)
 * Requires admin/creator auth
 */

require_once __DIR__ . '/../lib/auth.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = requireCreator();

$limit = min((int)($_GET['limit'] ?? 50), 100);

$db = getDB();

// Union of recent activity types, each with a human-readable action
$query = "
    SELECT * FROM (
        -- Messages sent by fans
        SELECT
            m.created_at AS timestamp,
            u.display_name AS username,
            'sent message' AS action,
            NULL AS amount_cents
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE u.role = 'fan' AND m.is_ai = 0
        ORDER BY m.created_at DESC
        LIMIT :lim
    )

    UNION ALL

    SELECT * FROM (
        -- Tips
        SELECT
            t.created_at AS timestamp,
            u.display_name AS username,
            'tipped' AS action,
            t.amount_cents
        FROM tips t
        JOIN users u ON u.id = t.user_id
        ORDER BY t.created_at DESC
        LIMIT :lim
    )

    UNION ALL

    SELECT * FROM (
        -- PPV unlocks
        SELECT
            tr.created_at AS timestamp,
            u.display_name AS username,
            'unlocked PPV' AS action,
            tr.amount_cents
        FROM transactions tr
        JOIN users u ON u.id = tr.user_id
        WHERE tr.type = 'ppv' AND tr.status = 'completed'
        ORDER BY tr.created_at DESC
        LIMIT :lim
    )

    UNION ALL

    SELECT * FROM (
        -- New subscriptions
        SELECT
            s.started_at AS timestamp,
            u.display_name AS username,
            'subscribed' AS action,
            s.price_cents AS amount_cents
        FROM subscriptions s
        JOIN users u ON u.id = s.user_id
        WHERE u.role = 'fan'
        ORDER BY s.started_at DESC
        LIMIT :lim
    )

    ORDER BY timestamp DESC
    LIMIT :lim
";

$stmt = $db->prepare($query);
$stmt->bindValue(':lim', $limit, SQLITE3_INTEGER);
$result = $stmt->execute();

$activity = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $activity[] = $row;
}

$db->close();

jsonResponse([
    'activity' => $activity,
]);
