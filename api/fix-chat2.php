<?php
if (($_GET['k'] ?? '') !== 'fix-chat2-2026') { http_response_code(403); die('no'); }
require_once __DIR__ . '/lib/database.php';
$db = getDB();
// Delete all AI messages from the last hour that are repetitive
$stmt = $db->query("SELECT id, content FROM messages WHERE is_ai = 1 AND created_at > datetime('now', '-2 hours') ORDER BY created_at DESC");
$seen = [];
$deleted = 0;
while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) {
    // Keep first unique-ish response, delete duplicates
    $key = substr(preg_replace('/[^a-z]/', '', strtolower($row['content'])), 0, 30);
    if (isset($seen[$key])) {
        $db->exec("DELETE FROM messages WHERE id = " . $row['id']);
        $deleted++;
    } else {
        $seen[$key] = true;
    }
}
$db->close();
echo json_encode(['ok' => true, 'deleted' => $deleted]);
unlink(__FILE__);
