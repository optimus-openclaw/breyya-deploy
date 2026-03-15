<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';

$user = getCurrentUser();
if (!$user || !in_array($user['role'], ['creator', 'admin'])) {
    http_response_code(403);
    echo '<h1>Access denied</h1>';
    exit;
}

$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS traffic_sources (id INTEGER PRIMARY KEY AUTOINCREMENT, platform TEXT NOT NULL DEFAULT 'Direct', referrer_url TEXT DEFAULT '', user_agent TEXT DEFAULT '', ip_hash TEXT DEFAULT '', user_id INTEGER DEFAULT NULL, converted_signup INTEGER DEFAULT 0, converted_purchase INTEGER DEFAULT 0, landed_at TEXT DEFAULT (datetime('now')))");

function getPlatformData($db, $dateFilter) {
    $result = $db->query("SELECT platform, COUNT(*) as visits, COUNT(DISTINCT ip_hash) as unique_v FROM traffic_sources WHERE landed_at >= $dateFilter GROUP BY platform ORDER BY visits DESC");
    $rows = [];
    while ($r = $result->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
    return $rows;
}

$today   = getPlatformData($db, "datetime('now', 'start of day')");
$week    = getPlatformData($db, "datetime('now', '-7 days')");
$month   = getPlatformData($db, "datetime('now', '-30 days')");

$totalToday = $db->querySingle("SELECT COUNT(*) FROM traffic_sources WHERE landed_at >= datetime('now','start of day')");
$totalWeek  = $db->querySingle("SELECT COUNT(*) FROM traffic_sources WHERE landed_at >= datetime('now','-7 days')");
$totalMonth = $db->querySingle("SELECT COUNT(*) FROM traffic_sources WHERE landed_at >= datetime('now','-30 days')");
$db->close();

$now = date('M j, Y g:i A');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Analytics — Breyya Admin</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,sans-serif;background:#0f0f1a;color:#eee;padding:20px}
h1{color:#e94560;margin-bottom:20px;font-size:22px}
.tabs{display:flex;gap:10px;margin-bottom:20px}
.tab{padding:8px 18px;border:1px solid #0f3460;border-radius:8px;cursor:pointer;background:#16213e;color:#aaa;font-size:14px}
.tab.active{background:#e94560;color:#fff;border-color:#e94560}
.section{display:none}.section.active{display:block}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
th{text-align:left;padding:10px;background:#16213e;color:#e94560;font-size:13px;border-bottom:2px solid #0f3460}
td{padding:10px;border-bottom:1px solid #1a1a3e;font-size:14px}
.total{font-size:13px;color:#888;margin-bottom:16px}
.platform-icon{margin-right:6px}
</style>
</head>
<body>
<h1>📊 Traffic Analytics</h1>
<p style="color:#888;font-size:12px;margin-bottom:20px">Updated: <?= $now ?></p>

<div class="tabs">
  <div class="tab active" onclick="show('today',this)">Today</div>
  <div class="tab" onclick="show('week',this)">This Week</div>
  <div class="tab" onclick="show('month',this)">This Month</div>
</div>

<div id="today" class="section active">
<p class="total">Total visits today: <b><?= $totalToday ?></b></p>
<?php renderTable($today); ?>
</div>
<div id="week" class="section">
<p class="total">Total visits this week: <b><?= $totalWeek ?></b></p>
<?php renderTable($week); ?>
</div>
<div id="month" class="section">
<p class="total">Total visits this month: <b><?= $totalMonth ?></b></p>
<?php renderTable($month); ?>
</div>

<?php function renderTable($rows) {
    if (empty($rows)) { echo '<p style="color:#888">No data yet</p>'; return; }
    echo '<table><tr><th>Platform</th><th>Visits</th><th>Unique Visitors</th></tr>';
    foreach ($rows as $r) {
        $icons = ['TikTok'=>'🎵','Twitter/X'=>'🐦','Instagram'=>'📸','Reddit'=>'🤖','YouTube'=>'▶️','Google'=>'🔍','Direct'=>'🔗','Organic/Other'=>'🌐'];
        $icon = $icons[$r['platform']] ?? '🌐';
        echo "<tr><td>{$icon} {$r['platform']}</td><td>{$r['visits']}</td><td>{$r['unique_v']}</td></tr>";
    }
    echo '</table>';
} ?>

<script>
function show(id,el){
  document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  el.classList.add('active');
}
</script>
</body>
</html>
