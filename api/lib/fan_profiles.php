<?php
/**
 * Breyya.com — Fan Profile Management
 * Tracks fan data, classification, and vibe types
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

/**
 * Get or create a fan profile
 */
function getFanProfile(int $userId): array {
    $db = getDB();

    // Ensure fan_profiles table exists (migration)
    $db->exec("CREATE TABLE IF NOT EXISTS fan_profiles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER UNIQUE NOT NULL,
        name TEXT DEFAULT '',
        birthday TEXT DEFAULT '',
        location TEXT DEFAULT '',
        job TEXT DEFAULT '',
        hobbies TEXT DEFAULT '',
        pets TEXT DEFAULT '',
        relationship_status TEXT DEFAULT '',
        favorite_teams TEXT DEFAULT '',
        has_kids INTEGER DEFAULT 0,
        vibe_type TEXT DEFAULT 'unknown',
        notes TEXT DEFAULT '',
        last_topic TEXT DEFAULT '',
        message_count INTEGER DEFAULT 0,
        last_active TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    $stmt = $db->prepare('SELECT * FROM fan_profiles WHERE user_id = :uid');
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $profile = $result->fetchArray(SQLITE3_ASSOC);

    if (!$profile) {
        // Create new profile
        $stmt = $db->prepare("INSERT INTO fan_profiles (user_id) VALUES (:uid)");
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->execute();

        $stmt = $db->prepare('SELECT * FROM fan_profiles WHERE user_id = :uid');
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $profile = $result->fetchArray(SQLITE3_ASSOC);
    }

    $db->close();
    return $profile ?: [];
}

/**
 * Update fan profile fields
 */
function updateFanProfile(int $userId, array $fields): void {
    $db = getDB();

    // Ensure profile exists
    getFanProfile($userId);

    $allowed = ['name', 'birthday', 'location', 'job', 'hobbies', 'pets',
                'relationship_status', 'favorite_teams', 'has_kids',
                'vibe_type', 'notes', 'last_topic', 'message_count', 'last_active'];

    $sets = ["updated_at = datetime('now')"];
    $params = [];
    foreach ($fields as $key => $value) {
        if (in_array($key, $allowed)) {
            $sets[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }

    if (count($sets) <= 1) {
        $db->close();
        return;
    }

    $sql = "UPDATE fan_profiles SET " . implode(', ', $sets) . " WHERE user_id = :uid";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }
    $stmt->execute();
    $db->close();
}

/**
 * Increment message count and update last_active
 */
function incrementFanMessageCount(int $userId): void {
    $db = getDB();

    // Ensure fan_profiles table + row exist
    getFanProfile($userId);

    $stmt = $db->prepare("UPDATE fan_profiles SET message_count = message_count + 1, last_active = datetime('now'), updated_at = datetime('now') WHERE user_id = :uid");
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $stmt->execute();
    $db->close();
}

/**
 * Classify fan based on message count and tip total
 * Returns: 'new', 'regular', 'engaged', 'whale'
 */
function classifyFan(int $userId): string {
    $db = getDB();

    // Get message count
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM messages WHERE sender_id = :uid");
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $msgCount = intval($result->fetchArray(SQLITE3_ASSOC)['cnt'] ?? 0);

    // Get tip total
    $tipTotal = 0;
    // Check if tips table uses user_id or from_user_id
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_cents), 0) as total FROM tips WHERE user_id = :uid");
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $tipTotal = intval($row['total'] ?? 0);

    // PPV unlocks count
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM transactions WHERE user_id = :uid AND type = 'ppv' AND status = 'completed'");
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $ppvCount = intval($result->fetchArray(SQLITE3_ASSOC)['cnt'] ?? 0);

    $db->close();

    // Classification logic
    if ($tipTotal >= 5000 || $ppvCount >= 3) {
        return 'whale';  // $50+ in tips OR 3+ PPVs unlocked
    }
    if ($msgCount >= 15 || $tipTotal > 0) {
        return 'engaged'; // 15+ messages OR has tipped
    }
    if ($msgCount >= 4) {
        return 'regular'; // 4-14 messages
    }
    return 'new'; // 0-3 messages
}

/**
 * Get conversation count (number of distinct "sessions" — gaps > 4 hours)
 */
function getConversationCount(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT created_at FROM messages WHERE sender_id = :uid ORDER BY created_at ASC");
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $convos = 0;
    $lastTime = null;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $time = strtotime($row['created_at']);
        if ($lastTime === null || ($time - $lastTime) > 14400) { // 4 hour gap = new conversation
            $convos++;
        }
        $lastTime = $time;
    }
    $db->close();
    return max(1, $convos);
}
