<?php
/**
 * Fan profile and classification utilities
 */

require_once __DIR__ . '/database.php';

/**
 * Get fan profile data
 * @param int $fanId Fan user ID
 * @return array Profile data
 */
function getFanProfile(int $fanId): array {
    $db = getDB();
    
    // Get basic user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindValue(':id', $fanId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$user) {
        $db->close();
        return ['id' => $fanId, 'username' => 'Unknown', 'email' => '', 'created_at' => date('Y-m-d H:i:s')];
    }
    
    // Get spend data if payments table exists
    $spend = 0;
    try {
        $spendStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE user_id = :id AND status = 'completed'");
        $spendStmt->bindValue(':id', $fanId, SQLITE3_INTEGER);
        $spendResult = $spendStmt->execute();
        $spendData = $spendResult->fetchArray(SQLITE3_ASSOC);
        $spend = $spendData['total'] ?? 0;
    } catch (Exception $e) {
        // Payments table might not exist yet
        $spend = 0;
    }
    
    $db->close();
    
    return [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'created_at' => $user['created_at'],
        'total_spend' => $spend,
    ];
}

/**
 * Classify fan based on behavior and spending
 * @param int $fanId Fan user ID
 * @return string Classification: new, regular, whale, inactive
 */
function classifyFan(int $fanId): string {
    $profile = getFanProfile($fanId);
    $messageCount = getConversationCount($fanId);
    $spend = $profile['total_spend'] ?? 0;
    
    // Account age in days
    $accountAge = (time() - strtotime($profile['created_at'])) / 86400;
    
    // Whale: high spend
    if ($spend >= 100) {
        return 'whale';
    }
    
    // Regular: consistent interaction
    if ($messageCount >= 10 && $spend >= 20) {
        return 'regular';
    }
    
    // New: recent account or low interaction
    if ($accountAge <= 7 || $messageCount <= 3) {
        return 'new';
    }
    
    // Inactive: old account, low interaction, no spend
    if ($accountAge > 30 && $messageCount <= 5 && $spend == 0) {
        return 'inactive';
    }
    
    return 'regular'; // Default
}

/**
 * Get total conversation count for a fan
 * @param int $fanId Fan user ID
 * @return int Message count
 */
function getConversationCount(int $fanId): int {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM messages 
        WHERE (sender_id = :fan AND receiver_id = :creator) 
           OR (sender_id = :creator2 AND receiver_id = :fan2)
    ");
    $stmt->bindValue(':fan', $fanId, SQLITE3_INTEGER);
    $stmt->bindValue(':fan2', $fanId, SQLITE3_INTEGER);
    $stmt->bindValue(':creator', CREATOR_USER_ID, SQLITE3_INTEGER);
    $stmt->bindValue(':creator2', CREATOR_USER_ID, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    
    return $row['count'] ?? 0;
}

/**
 * Increment fan message count (for tracking engagement)
 * @param int $fanId Fan user ID
 */
function incrementFanMessageCount(int $fanId): void {
    $db = getDB();
    
    // Create fan_stats table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS fan_stats (
        fan_id INTEGER PRIMARY KEY,
        message_count INTEGER DEFAULT 0,
        last_message_at TEXT DEFAULT (datetime('now')),
        total_spend REAL DEFAULT 0.0,
        created_at TEXT DEFAULT (datetime('now'))
    )");
    
    // Insert or update
    $stmt = $db->prepare("
        INSERT INTO fan_stats (fan_id, message_count, last_message_at) 
        VALUES (:fan_id, 1, datetime('now'))
        ON CONFLICT(fan_id) DO UPDATE SET 
            message_count = message_count + 1,
            last_message_at = datetime('now')
    ");
    $stmt->bindValue(':fan_id', $fanId, SQLITE3_INTEGER);
    $stmt->execute();
    
    $db->close();
}