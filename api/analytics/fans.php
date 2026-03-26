<?php
/**
 * Breyya Admin — Fan Leaderboard
 * Shows top spenders, whale scores, engagement stats
 * Requires creator/admin login
 */
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';

$user = getCurrentUser();
if (!$user || !in_array($user['role'], ['creator', 'admin'])) {
    http_response_code(403);
    echo '<h1 style="font-family:sans-serif;color:#e94560;padding:20px">Access denied</h1>';
    exit;
}

$db = getDB();

// Ensure tables exist
$db->exec("CREATE TABLE IF NOT EXISTS fan_profiles (fan_user_id INTEGER PRIMARY KEY, display_name TEXT DEFAULT '', preferences TEXT DEFAULT '', topics_discussed TEXT DEFAULT '', ppv_purchases_total INTEGER DEFAULT 0, total_messages INTEGER DEFAULT 0, last_active TEXT DEFAULT '', notes TEXT DEFAULT '', whale_score INTEGER DEFAULT 0, updated_at TEXT DEFAULT (datetime('now')))");
$db->exec("ALTER TABLE fan_profiles ADD COLUMN whale_score INTEGER DEFAULT 0" . ""); // safe, @ suppressed by try
@$db->exec("ALTER TABLE fan_profiles ADD COLUMN whale_score INTEGER DEFAULT 0");

// Get all fans with spend data
$result = $db->query("
    SELECT 
        fp.fan_user_id,
        fp.display_name,
        fp.whale_score,
        fp.ppv_purchases_total,
        fp.total_messages,
        fp.last_active,
        COALESCE(SUM(ps.price_cents), 0) as total_spent_cents,
        COUNT(DISTINCT ps.id) as ppv_count
    FROM fan_profiles fp
    LEFT JOIN ppv_sales ps ON ps.fan_user_id = fp.fan_user_id
    GROUP BY fp.fan_user_id
    ORDER BY total_spent_cents DESC, fp.whale_score DESC, fp.total_messages DESC
    LIMIT 100
");

$fans = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $fans[] = $row;
}

// Summary stats
$totalFans    = $db->querySingle("SELECT COUNT(*) FROM fan_profiles");
$totalWhales  = $db->querySingle("SELECT COUNT(*) FROM fan_profiles WHERE whale_score >= 70");
$totalActive  = $db->querySingle("SELECT COUNT(*) FROM fan_profiles WHERE whale_score >= 40 AND whale_score < 70");
$totalRevenue = $db->querySingle("SELECT COALESCE(SUM(price_cents),0) FROM ppv_sales");
$db->close();

$now = date('M j, Y g:i A');

function tierBadge($score) {
    if ($score >= 70) return '<span style="color:#e74c3c">🐳 VIP</span>';
    if ($score >= 40) return '<span style="color:#f39c12">🐬 Active</span>';
    return '<span style="color:#888">🐟 Casual</span>';
}

function timeAgo($dt) {
    if (empty($dt)) return 'Never';
    $diff = time() - strtotime($dt);
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return floor($diff/604800) . 'w ago';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fan Leaderboard — Breyya Admin</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#0f0f1a;color:#eee;padding:20px;min-height:100vh}
h1{color:#e94560;margin-bottom:4px;font-size:22px}
.subtitle{color:#888;font-size:12px;margin-bottom:20px}
.stats-row{display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap}
.stat-card{background:#16213e;border:1px solid #0f3460;border-radius:10px;padding:14px 18px;flex:1;min-width:120px}
.stat-card .label{font-size:11px;color:#888;margin-bottom:4px}
.stat-card .value{font-size:22px;font-weight:bold;color:#e94560}
.stat-card .value.green{color:#2ecc71}
.stat-card .value.orange{color:#f39c12}
table{width:100%;border-collapse:collapse}
thead tr{background:#16213e}
th{text-align:left;padding:10px 12px;color:#e94560;font-size:12px;font-weight:600;border-bottom:2px solid #0f3460;white-space:nowrap}
td{padding:10px 12px;border-bottom:1px solid #1a1a3e;font-size:13px;vertical-align:middle}
tr:hover td{background:#16213e55}
.rank{color:#888;font-size:12px;font-weight:bold;width:30px}
.rank.gold{color:#f39c12}
.rank.silver{color:#aaa}
.rank.bronze{color:#cd7f32}
.fan-name{font-weight:600}
.fan-id{color:#888;font-size:11px}
.score{font-weight:bold}
.no-data{text-align:center;padding:40px;color:#888}
.back-link{display:inline-block;margin-bottom:16px;color:#888;font-size:13px;text-decoration:none}
.back-link:hover{color:#e94560}
</style>
</head>
<body>

<a href="/api/analytics/dashboard.php" class="back-link">← Back to Analytics</a>
<h1>👑 Fan Leaderboard</h1>
<p class="subtitle">Updated: <?= $now ?></p>

<div class="stats-row">
    <div class="stat-card">
        <div class="label">Total Fans</div>
        <div class="value"><?= number_format($totalFans) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">🐳 VIP Whales</div>
        <div class="value" style="color:#e74c3c"><?= $totalWhales ?></div>
    </div>
    <div class="stat-card">
        <div class="label">🐬 Active Fans</div>
        <div class="value orange"><?= $totalActive ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Total Revenue</div>
        <div class="value green">$<?= number_format($totalRevenue / 100, 2) ?></div>
    </div>
</div>

<?php if (empty($fans)): ?>
<div class="no-data">
    <p style="font-size:32px;margin-bottom:12px">👋</p>
    <p>No fan data yet — profiles will appear once fans start chatting.</p>
</div>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Fan</th>
            <th>Tier</th>
            <th>Score</th>
            <th>PPV Purchases</th>
            <th>Spent</th>
            <th>Messages</th>
            <th>Last Active</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($fans as $i => $fan):
        $rank = $i + 1;
        $rankClass = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
        $rankIcon = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : $rank));
        $name = !empty($fan['display_name']) ? htmlspecialchars($fan['display_name']) : 'Fan #' . $fan['fan_user_id'];
        $spent = '$' . number_format($fan['total_spent_cents'] / 100, 2);
    ?>
        <tr>
            <td class="rank <?= $rankClass ?>"><?= $rankIcon ?></td>
            <td>
                <div class="fan-name"><?= $name ?></div>
                <div class="fan-id">ID: <?= $fan['fan_user_id'] ?></div>
            </td>
            <td><?= tierBadge($fan['whale_score']) ?></td>
            <td class="score" style="color:<?= $fan['whale_score'] >= 70 ? '#e74c3c' : ($fan['whale_score'] >= 40 ? '#f39c12' : '#888') ?>">
                <?= $fan['whale_score'] ?>/100
            </td>
            <td><?= $fan['ppv_count'] ?></td>
            <td style="color:#2ecc71;font-weight:600"><?= $spent ?></td>
            <td><?= number_format($fan['total_messages']) ?></td>
            <td style="color:#888"><?= timeAgo($fan['last_active']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

</body>
</html>
