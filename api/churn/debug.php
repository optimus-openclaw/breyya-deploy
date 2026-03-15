<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
$DB_PATH = __DIR__ . '/../../data/breyya.db';
echo json_encode([
    'dir' => __DIR__,
    'db_path' => $DB_PATH,
    'db_exists' => file_exists($DB_PATH),
    'db_writable' => is_writable($DB_PATH),
    'parent_writable' => is_writable(dirname($DB_PATH)),
]);
