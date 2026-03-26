<?php
// update-profile.php - POST append/update fan profile fields
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(["error"=>"method_not_allowed"]); exit; }
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

$uid = get_current_user_id();
if (!$uid) { http_response_code(401); echo json_encode(["error"=>"unauthorized"]); exit; }

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$known_name = isset($body['known_name']) ? trim($body['known_name']) : null;
$interests = isset($body['interests']) ? trim($body['interests']) : null;
topics = isset($body['topics']) ? trim($body['topics']) : null;

// fetch existing
$stmt = $db->prepare('SELECT * FROM fan_profiles WHERE user_id = :uid LIMIT 1');
$stmt->execute([':uid'=>$uid]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$profile) {
    $db->prepare('INSERT INTO fan_profiles (user_id, created_at, updated_at, attention_offset_minutes) VALUES (:uid, datetime("now"), datetime("now"), :offset)')
       ->execute([':uid'=>$uid, ':offset'=>rand(0,180)]);
    $stmt->execute([':uid'=>$uid]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
}

$update_fields = [];
$params = [':uid'=>$uid];
if ($known_name !== null && $known_name !== '') {
    $update_fields[] = 'known_name = :known_name';
    $params[':known_name'] = $known_name;
}
if ($interests !== null && $interests !== '') {
    // append to interests JSON/text
    $new = trim(($profile['interests'] ?? '') . ' ' . $interests);
    $update_fields[] = 'interests = :interests';
    $params[':interests'] = $new;
}
if ($topics !== null && $topics !== '') {
    $newt = trim(($profile['topics_discussed'] ?? '') . ' ' . $topics);
    $update_fields[] = 'topics_discussed = :topics';
    $params[':topics'] = $newt;
}

if (count($update_fields) === 0) {
    echo json_encode(["ok"=>true, "message"=>"nothing_to_update"]);
    exit;
}
$update_fields[] = 'updated_at = datetime("now")';
$sql = 'UPDATE fan_profiles SET ' . implode(', ', $update_fields) . ' WHERE user_id = :uid';
$upd = $db->prepare($sql);
$upd->execute($params);

echo json_encode(["ok"=>true]);

?>