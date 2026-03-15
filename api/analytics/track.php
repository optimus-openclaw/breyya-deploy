<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../lib/database.php';

// Auto-create table
$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS traffic_sources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    platform TEXT NOT NULL DEFAULT 'Direct',
    referrer_url TEXT DEFAULT '',
    user_agent TEXT DEFAULT '',
    ip_hash TEXT DEFAULT '',
    user_id INTEGER DEFAULT NULL,
    converted_signup INTEGER DEFAULT 0,
    converted_purchase INTEGER DEFAULT 0,
    landed_at TEXT DEFAULT (datetime('now'))
)");

// Detect platform from referrer
$ref = $_SERVER['HTTP_REFERER'] ?? '';
$platform = 'Direct';
if (strpos($ref, 'tiktok.com') !== false) $platform = 'TikTok';
elseif (strpos($ref, 'twitter.com') !== false || strpos($ref, 'x.com') !== false) $platform = 'Twitter/X';
elseif (strpos($ref, 'instagram.com') !== false) $platform = 'Instagram';
elseif (strpos($ref, 'reddit.com') !== false) $platform = 'Reddit';
elseif (strpos($ref, 'youtube.com') !== false || strpos($ref, 'youtu.be') !== false) $platform = 'YouTube';
elseif (strpos($ref, 'google.com') !== false) $platform = 'Google';
elseif (!empty($ref)) $platform = 'Organic/Other';

$ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);
$refSafe = substr($ref, 0, 500);

$stmt = $db->prepare("INSERT INTO traffic_sources (platform, referrer_url, user_agent, ip_hash, landed_at) VALUES (:p, :r, :u, :i, datetime('now'))");
$stmt->bindValue(':p', $platform);
$stmt->bindValue(':r', $refSafe);
$stmt->bindValue(':u', $ua);
$stmt->bindValue(':i', $ipHash);
$stmt->execute();
$db->close();

echo json_encode(['ok' => true, 'platform' => $platform]);
