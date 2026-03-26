<?php
// calculate-whale.php - POST user_id -> recalc whale score and update profile
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(["error"=>"method_not_allowed"]); exit; }
$db_path = '/tmp/breyya-deploy-live/api/lib/database.php';
if (file_exists($db_path)) { require_once $db_path; $db = get_database_connection(); }
else { $db = new PDO('sqlite:' . __DIR__ . '/../../data/db.sqlite'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (empty($body['user_id'])) { http_response_code(400); echo json_encode(["error"=>"missing_user_id"]); exit; }
$uid = intval($body['user_id']);

$stmt = $db->prepare('SELECT total_spent_cents, total_messages, last_active_at, daily_message_count FROM fan_profiles WHERE user_id = :uid LIMIT 1');
$stmt->execute([':uid'=>$uid]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$profile) { http_response_code(404); echo json_encode(["error"=>"not_found"]); exit; }

$ppv_points = min(($profile['total_spent_cents'] / 100) * 0.4, 40);
$msg_points = min($profile['total_messages'], 100) * 0.3; if ($msg_points > 30) $msg_points = 30;
$engage_points = min($profile['daily_message_count'], 10);

$recency_points = 0;
if (!empty($profile['last_active_at'])) {
    $last = strtotime($profile['last_active_at']);
    $diff_days = (time() - $last) / 86400;
    if ($diff_days <= 7) $recency_points = 20;
    else if ($diff_days <= 14) $recency_points = 10;
}

$score = round($ppv_points + $msg_points + $recency_points + $engage_points);
if ($score > 100) $score = 100;
$tier = 'casual';
if ($score >= 70) $tier = 'whale';
else if ($score >= 40) $tier = 'active';

$upd = $db->prepare('UPDATE fan_profiles SET whale_score = :score, whale_tier = :tier, updated_at = datetime("now") WHERE user_id = :uid');
$upd->execute([':score'=>$score, ':tier'=>$tier, ':uid'=>$uid]);

echo json_encode(["ok"=>true, "user_id"=>$uid, "whale_score"=>$score, "whale_tier"=>$tier]);

?>