<?php
if (($_GET['k'] ?? '') !== 'purge-2026') { http_response_code(403); die('no'); }
// Touch the contact page to bust LiteSpeed cache
$file = __DIR__ . '/../contact/index.html';
touch($file);
clearstatcache();
echo json_encode(['ok' => true, 'time' => date('Y-m-d H:i:s'), 'file_mtime' => filemtime($file)]);
unlink(__FILE__);
