<?php
/**
 * Breyya Auto-Poster
 * Runs once daily via cron. Picks 1 unused gallery photo from the posts database
 * or uploads directory and schedules it to post at a random time during active hours.
 * 
 * Called by cron: curl -s "https://breyya.com/api/auto-poster.php?secret=breyya-autoposter-2026"
 */

header('Content-Type: application/json');

$SECRET = 'breyya-autoposter-2026';
$CREATOR_ID = 1;

if (($_GET['secret'] ?? '') !== $SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'unauthorized']));
}

require_once __DIR__ . '/lib/database.php';

$db = getDB();

// Check if we already posted today
$today = date('Y-m-d');
$alreadyPosted = $db->querySingle(
    "SELECT COUNT(*) FROM posts WHERE date(created_at) = '$today' AND creator_id = $CREATOR_ID AND caption LIKE '%[auto]%'"
);

if ($alreadyPosted > 0) {
    $db->close();
    echo json_encode(['ok' => true, 'action' => 'already_posted_today', 'date' => $today]);
    exit;
}

// Pick a random time between 10 AM and 9 PM PT for the post
// The cron runs early but the post gets a scheduled_at time
$hour = rand(10, 20); // 10 AM to 8 PM (so post appears by 9 PM latest)
$minute = rand(0, 59);
$scheduledAt = $today . ' ' . sprintf('%02d:%02d:00', $hour, $minute);

// Find unused content to post
// Strategy 1: Check data/uploads/ for images not yet in posts table
$uploadDir = __DIR__ . '/../data/uploads/';
$usedMedia = [];
$r = $db->query("SELECT media_url FROM posts WHERE media_url IS NOT NULL AND media_url != ''");
while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
    $usedMedia[] = $row['media_url'];
}

$availableFiles = [];
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'])) {
            $mediaUrl = '/data/uploads/' . $file;
            if (!in_array($mediaUrl, $usedMedia)) {
                $availableFiles[] = $mediaUrl;
            }
        }
    }
}

// Strategy 2: Check R2 content if no local uploads available
// (Future: query R2 inventory for unused gallery content)

if (empty($availableFiles)) {
    $db->close();
    echo json_encode(['ok' => true, 'action' => 'no_unused_content', 'date' => $today]);
    exit;
}

// Pick one random unused photo
$mediaUrl = $availableFiles[array_rand($availableFiles)];

// Generate a flirty caption (rotate through options)
$captions = [
    "just felt cute 💕",
    "good morning babe 😘",
    "new pic for you 🥰",
    "thinking about you 😏",
    "do you like? 👀",
    "feeling myself today 🔥",
    "just because 💋",
    "hi babe 😘🫶",
    "who's online? 😏",
    "miss me? 💕",
    "something about today ✨",
    "💕",
    "🔥🔥🔥",
    "for you babe 😘",
    "late night vibes 🌙",
    "can't sleep so here's a pic 😩💕",
];
$caption = $captions[array_rand($captions)] . ' [auto]';

// Insert the post with the scheduled time
$stmt = $db->prepare(
    "INSERT INTO posts (creator_id, type, caption, media_url, is_free, like_count, created_at) 
     VALUES (:cid, 'photo', :caption, :media, 1, 0, :created)"
);
$stmt->bindValue(':cid', $CREATOR_ID, SQLITE3_INTEGER);
$stmt->bindValue(':caption', $caption, SQLITE3_TEXT);
$stmt->bindValue(':media', $mediaUrl, SQLITE3_TEXT);
$stmt->bindValue(':created', $scheduledAt, SQLITE3_TEXT);
$stmt->execute();

$postId = $db->lastInsertRowID();
$db->close();

echo json_encode([
    'ok' => true,
    'action' => 'posted',
    'post_id' => $postId,
    'media' => $mediaUrl,
    'caption' => $caption,
    'scheduled_at' => $scheduledAt,
    'date' => $today
]);
