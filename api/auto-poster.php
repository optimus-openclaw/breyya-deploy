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
    "SELECT COUNT(*) FROM posts WHERE date(created_at) = '$today' AND creator_id = $CREATOR_ID AND is_auto = 1"
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

// Strategy 2: Check R2 Feed content if no local uploads available
if (empty($availableFiles)) {
    $r2BaseUrl = 'https://pub-24f8d05ca30745b496a897793321ddf1.r2.dev';
    
    // Query R2 for Feed images via rclone (runs on the OpenClaw host, not the web server)
    // Instead, use a pre-synced list or check known R2 feed paths
    // For now, maintain a list in the DB of R2 feed content
    $r2Result = $db->query("SELECT r2_url FROM content_inventory WHERE status = 'feed' AND r2_url NOT IN (SELECT media_url FROM posts WHERE media_url IS NOT NULL)");
    if ($r2Result) {
        while ($row = $r2Result->fetchArray(SQLITE3_ASSOC)) {
            $url = $row['r2_url'];
            // Only images, not videos
            $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'])) {
                $availableFiles[] = $url;
            }
        }
    }
    
    // Strategy 3: If still no content from DB, check R2 directly via known public URLs
    if (empty($availableFiles)) {
        // Hardcoded R2 Feed URLs as fallback (from content sorter output)
        $knownR2Feed = [
            $r2BaseUrl . '/2020-09-14%20sorted/Feed/!-01-jpeg/images/IMG_0352.jpeg',
            $r2BaseUrl . '/2020-09-14%20sorted/Feed/!-03-jpeg/images/IMG_0374.jpeg',
            $r2BaseUrl . '/2020-09-14%20sorted/Feed/!-05-jpeg/images/IMG_0057.jpeg',
            $r2BaseUrl . '/2020-09-14%20sorted/Feed/!-08-jpeg/images/IMG_0075.jpeg',
            $r2BaseUrl . '/2020-09-14%20sorted/Feed/!-10-jpeg/images/IMG_0223.jpeg',
            $r2BaseUrl . '/2020-09-28%20sorted/Feed/!-03-jpeg/images/IMG_0296.jpeg',
            $r2BaseUrl . '/2020-09-28%20sorted/Feed/!-05-jpeg/images/IMG_0073.jpeg',
            $r2BaseUrl . '/2020-09-28%20sorted/Feed/!-06-jpeg/images/IMG_0622.jpeg',
            $r2BaseUrl . '/2020-09-28%20sorted/Feed/!-09-jpeg/images/IMG_0299.jpeg',
            $r2BaseUrl . '/2021-01-23%20sorted/Feed/white-tank-light-denim-shorts/images/IMG_9474.jpg',
        ];
        
        foreach ($knownR2Feed as $url) {
            if (!in_array($url, $usedMedia)) {
                $availableFiles[] = $url;
            }
        }
    }
}

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
$caption = $captions[array_rand($captions)];

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
