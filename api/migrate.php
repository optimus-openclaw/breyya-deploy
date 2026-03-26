<?php
// migrate.php?k=migrate-2026
// Migration runner for fan_profiles table and bootstrap from users table
if (!isset($_GET['k']) || $_GET['k'] !== 'migrate-2026') {
    http_response_code(403);
    echo json_encode(["error" => "missing_or_invalid_key"]);
    exit;
}
header('Content-Type: application/json');

// include existing database helper if available
$db_path = '/tmp/breyya-deploy-live/api/lib/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
    $db = get_database_connection(); // assume function exists
} else {
    // fallback to sqlite in app/out/data/db.sqlite
    $dir = __DIR__ . '/../../data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $db_file = $dir . '/db.sqlite';
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

try {
    $db->beginTransaction();
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS fan_profiles (
    user_id INTEGER PRIMARY KEY,
    display_name TEXT DEFAULT '',
    known_name TEXT DEFAULT '',
    interests TEXT DEFAULT '',
    topics_discussed TEXT DEFAULT '',
    ppv_purchased TEXT DEFAULT '[]',
    whale_score INTEGER DEFAULT 0,
    whale_tier TEXT DEFAULT 'casual',
    attention_offset_minutes INTEGER DEFAULT 0,
    daily_message_count INTEGER DEFAULT 0,
    daily_message_date TEXT DEFAULT '',
    daily_ppv_bought INTEGER DEFAULT 0,
    last_active_at TEXT DEFAULT '',
    total_messages INTEGER DEFAULT 0,
    total_spent_cents INTEGER DEFAULT 0,
    first_message_at TEXT DEFAULT '',
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);
SQL
    );

    // Try to find existing users table and insert missing profiles
    $users_exists = false;
    $res = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users';");
    if ($res && $res->fetch()) $users_exists = true;

    $inserted = 0;
    if ($users_exists) {
        $stmtUsers = $db->query('SELECT id, display_name FROM users');
        $stmtCheck = $db->prepare('SELECT 1 FROM fan_profiles WHERE user_id = :uid LIMIT 1');
        $stmtInsert = $db->prepare('INSERT OR IGNORE INTO fan_profiles (user_id, display_name, attention_offset_minutes, created_at, updated_at) VALUES (:uid, :display, :offset, datetime("now"), datetime("now"))');
        while ($row = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
            $uid = $row['id'];
            $stmtCheck->execute([':uid' => $uid]);
            if (!$stmtCheck->fetch()) {
                $offset = rand(0, 180);
                $stmtInsert->execute([':uid' => $uid, ':display' => $row['display_name'] ?? '', ':offset' => $offset]);
                $inserted++;
            }
        }
    }

    $db->commit();

    // Self-destruct: rename this file so it can't be re-run accidentally
    $self = __FILE__;
    $ran_marker = __DIR__ . '/.migrate_ran';
    file_put_contents($ran_marker, json_encode(["ran_at" => date(DATE_ATOM), "inserted" => $inserted]));
    @rename($self, $self . '.done');

    echo json_encode(["ok" => true, "inserted_profiles" => $inserted]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

?>