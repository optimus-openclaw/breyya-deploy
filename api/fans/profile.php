<?php
// profile.php - GET fan profile for authenticated user or by user_id for admin
header('Content-Type: application/json');
// simple auth include
$auth_path = '/tmp/breyya-deploy-live/api/lib/auth.php';
if (file_exists($auth_path)) require_once $auth_path;
$db_path = '/tmp/breyya-deploy-live/api/lib/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
    $db = get_database_connection();
} else {
    $db = new PDO('sqlite:' . __DIR__ . '/../../data/db.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// auth: if Authorization header contains Bearer <token>, assume get_user_from_token
function get_current_user_id() {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = $m[1];
            if (function_exists('get_user_from_token')) {
                $u = get_user_from_token($token);
                if ($u && isset($u['id'])) return $u['id'];
            }
        }
    }
    return null;
}

$admin_mode = false;
if (isset($_GET['admin']) && $_GET['admin'] === '1') {
    // naive admin check via header token role
    if (!empty($_SERVER['HTTP_AUTHORIZATION']) && function_exists('get_user_from_token')) {
        $u = get_user_from_token(preg_replace('/^Bearer\s+/','', $_SERVER['HTTP_AUTHORIZATION']));
        if ($u && !empty($u['is_admin'])) $admin_mode = true;
    }
}

$user_id = null;
if ($admin_mode && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
} else {
    $user_id = get_current_user_id();
}

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["error" => "unauthorized"]);
    exit;
}

$stmt = $db->prepare('SELECT * FROM fan_profiles WHERE user_id = :uid LIMIT 1');
$stmt->execute([':uid' => $user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$profile) {
    // create a default profile row
    $ins = $db->prepare('INSERT INTO fan_profiles (user_id, display_name, created_at, updated_at, attention_offset_minutes) VALUES (:uid, :display, datetime("now"), datetime("now"), :offset)');
    $ins->execute([':uid' => $user_id, ':display' => '', ':offset' => rand(0,180)]);
    $stmt->execute([':uid' => $user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Return profile JSON
echo json_encode(["ok" => true, "profile" => $profile]);

?>