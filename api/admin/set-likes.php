<?php
require_once __DIR__ . '/../lib/database.php';
header('Content-Type: application/json');
if (($_GET['secret'] ?? '') !== 'breyya-chat-cron-2026') { http_response_code(403); die('{}'); }

$db = getDB();

// Distribute 259 likes across posts naturally
// Newer posts get more, older get fewer, with randomness
$posts = [];
$r = $db->query("SELECT id FROM posts ORDER BY created_at DESC");
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $posts[] = $row['id'];
}

$totalTarget = 259;
$count = count($posts);
if ($count === 0) { echo json_encode(['error' => 'no posts']); exit; }

// Weight: newest gets most, decay as we go older
$likes = [];
$remaining = $totalTarget;
for ($i = 0; $i < $count; $i++) {
    if ($i === $count - 1) {
        $amount = $remaining; // Last post gets whatever's left
    } else {
        // Random amount weighted toward newer posts
        $weight = max(1, $count - $i);
        $maxForThis = min($remaining, intval($totalTarget / $count * $weight * 0.8) + rand(1, 8));
        $amount = max(1, min($remaining, rand(intval($maxForThis * 0.5), $maxForThis)));
    }
    $likes[$posts[$i]] = $amount;
    $remaining -= $amount;
    if ($remaining <= 0) {
        // Distribute remaining 0s or 1s
        for ($j = $i + 1; $j < $count; $j++) {
            $likes[$posts[$j]] = rand(1, 5);
        }
        break;
    }
}

// Update DB
foreach ($likes as $postId => $likeCount) {
    $db->exec("UPDATE posts SET like_count = $likeCount WHERE id = $postId");
}

$db->close();
echo json_encode(['ok' => true, 'distribution' => $likes, 'total' => array_sum($likes)]);
