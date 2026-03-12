<?php
/**
 * Breyya.com — Database Layer
 * SQLite database with all schema management
 */

define('DB_PATH', __DIR__ . '/../../data/breyya.db');

function getDB(): SQLite3 {
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $db = new SQLite3(DB_PATH);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');

    return $db;
}

function initDB(): void {
    $db = getDB();

    // Users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        display_name TEXT DEFAULT '',
        role TEXT DEFAULT 'fan' CHECK(role IN ('fan', 'creator', 'admin')),
        avatar_url TEXT DEFAULT '',
        is_active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    )");

    // Subscriptions table
    $db->exec("CREATE TABLE IF NOT EXISTS subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        status TEXT DEFAULT 'active' CHECK(status IN ('active', 'cancelled', 'expired', 'past_due')),
        plan TEXT DEFAULT 'monthly',
        price_cents INTEGER DEFAULT 2000,
        started_at TEXT DEFAULT (datetime('now')),
        expires_at TEXT NOT NULL,
        ccbill_subscription_id TEXT DEFAULT '',
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Posts / feed content
    $db->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        creator_id INTEGER NOT NULL,
        type TEXT DEFAULT 'photo' CHECK(type IN ('photo', 'video', 'text')),
        caption TEXT DEFAULT '',
        media_url TEXT DEFAULT '',
        media_thumbnail TEXT DEFAULT '',
        is_free INTEGER DEFAULT 0,
        like_count INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        scheduled_at TEXT DEFAULT NULL,
        FOREIGN KEY (creator_id) REFERENCES users(id)
    )");

    // Post likes
    $db->exec("CREATE TABLE IF NOT EXISTS post_likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        UNIQUE(post_id, user_id),
        FOREIGN KEY (post_id) REFERENCES posts(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Messages / DMs
    $db->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id INTEGER NOT NULL,
        receiver_id INTEGER NOT NULL,
        content TEXT DEFAULT '',
        media_url TEXT DEFAULT '',
        media_thumbnail TEXT DEFAULT '',
        is_ppv INTEGER DEFAULT 0,
        ppv_price_cents INTEGER DEFAULT 0,
        is_unlocked INTEGER DEFAULT 0,
        is_read INTEGER DEFAULT 0,
        is_ai INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (sender_id) REFERENCES users(id),
        FOREIGN KEY (receiver_id) REFERENCES users(id)
    )");

    // Transactions / payments
    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type TEXT NOT NULL CHECK(type IN ('subscription', 'ppv', 'tip', 'refund')),
        amount_cents INTEGER NOT NULL,
        description TEXT DEFAULT '',
        reference_id TEXT DEFAULT '',
        status TEXT DEFAULT 'completed' CHECK(status IN ('pending', 'completed', 'failed', 'refunded')),
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Tips
    $db->exec("CREATE TABLE IF NOT EXISTS tips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        creator_id INTEGER NOT NULL,
        amount_cents INTEGER NOT NULL,
        message TEXT DEFAULT '',
        post_id INTEGER DEFAULT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (creator_id) REFERENCES users(id)
    )");

    // Mass messages (PPV blasts)
    $db->exec("CREATE TABLE IF NOT EXISTS mass_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        creator_id INTEGER NOT NULL,
        content TEXT DEFAULT '',
        media_url TEXT DEFAULT '',
        ppv_price_cents INTEGER DEFAULT 0,
        sent_count INTEGER DEFAULT 0,
        unlocked_count INTEGER DEFAULT 0,
        revenue_cents INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (creator_id) REFERENCES users(id)
    )");

    // Content drip schedule
    $db->exec("CREATE TABLE IF NOT EXISTS drip_schedule (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        publish_at TEXT NOT NULL,
        is_published INTEGER DEFAULT 0,
        FOREIGN KEY (post_id) REFERENCES posts(id)
    )");

    // Indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_subscriptions_user ON subscriptions(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_posts_creator ON posts(creator_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_receiver ON messages(receiver_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_sender ON messages(sender_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions(user_id)");

    $db->close();
}

// Auto-init on first load
initDB();
