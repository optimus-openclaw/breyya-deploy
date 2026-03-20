<?php
// fans.php - admin-only fan leaderboard
header('Content-Type: application/json');
$auth_path = '/tmp/breyya-deploy-live/api/lib/auth.php';
$db_path = '/tmp/breyya-deploy-live/api/lib/database.php';
$is_admin = false;
if (file_exists($auth_path)) { require_once $auth_path; if (function_exists('get_user_from_token') && !empty($_SERVER['HTTP_AUTHORIZATION'])) { $u = get_user_from_token(preg_replace('/^Bearer\s+/','', $_SERVER['HTTP_AUTHORIZATION'])); if ($u && !empty($u['is_admin'])) $is_admin = true; } }
if (!$is_admin) { http_response_code(401); echo json_encode(["error"=>"unauthorized"]); exit; }

if (file_exists($db_path)) { require_once $db_path; $db = get_database_connection(); }
else { $db = new PDO('sqlite:' . __DIR__ . '/../../data/db.sqlite'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

$stmt = $db->query('SELECT user_id, display_name, whale_score, whale_tier, total_spent_cents, total_messages, last_active_at FROM fan_profiles ORDER BY whale_score DESC LIMIT 100');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["ok"=>true, "count"=>count($rows), "fans"=>$rows]);

?>