<?php
if (($_GET['secret'] ?? '') !== 'wyc-deploy-2026') { http_response_code(403); die('no'); }
$html = file_get_contents('php://input');
if (strlen($html) < 100) die('empty');
$target = '/home/u708448327/domains/whyyoucame.com/public_html/index.html';
if (file_put_contents($target, $html)) {
    echo 'OK: written ' . strlen($html) . ' bytes';
    unlink(__FILE__); // self destruct
} else {
    echo 'FAIL: could not write';
}
