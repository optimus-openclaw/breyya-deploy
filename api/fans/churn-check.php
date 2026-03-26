<?php
// churn-check.php?k=secret_token - GET lists fans with total_messages>=5 and last_active>14 days
header('Content-Type: application/json');
if (!isset($_GET['k']) || $_GET['k'] !== 'churn-2026-secret') { http_response_code(403); echo json_encode(["error"=>"missing_or_invalid_key"]); exit; }
$db_path = '/tmp/breyya-deploy-live/api/lib/database.php';
if (file_exists($db_path)) { require_once $db_path; $db = get_database_connection(); }
else { $db = new PDO('sqlite:' . __DIR__ . '/../../data/db.sqlite'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

$threshold_ts = date('Y-m-d H:i:s', time() - (14*86400));
$stmt = $db->prepare('SELECT user_id, display_name, total_messages, last_active_at FROM fan_profiles WHERE total_messages >= 5 AND (last_active_at IS NULL OR last_active_at = "" OR last_active_at < :thr)');
$stmt->execute([':thr' => $threshold_ts]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["ok"=>true, "count"=>count($rows), "fans"=>$rows]);

?>