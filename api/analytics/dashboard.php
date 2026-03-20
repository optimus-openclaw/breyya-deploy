<?php
// dashboard.php - admin only placeholder traffic sources
header('Content-Type: application/json');
// naive admin check via Authorization header token
$auth_path = '/tmp/breyya-deploy-live/api/lib/auth.php';
$is_admin = false;
if (file_exists($auth_path)) { require_once $auth_path; if (function_exists('get_user_from_token') && !empty($_SERVER['HTTP_AUTHORIZATION'])) { $u = get_user_from_token(preg_replace('/^Bearer\s+/','', $_SERVER['HTTP_AUTHORIZATION'])); if ($u && !empty($u['is_admin'])) $is_admin = true; } }
if (!$is_admin) { http_response_code(401); echo json_encode(["error"=>"unauthorized"]); exit; }

echo json_encode(["ok"=>true, "period"=>"today", "sources"=>["tiktok"=>0,"instagram"=>0,"reddit"=>0,"youtube"=>0,"twitter"=>0,"google"=>0,"direct"=>0]]);

?>