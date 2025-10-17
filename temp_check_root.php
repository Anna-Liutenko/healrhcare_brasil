<?php
$opts = array('http' => array('method' => 'GET', 'header' => "Connection: close\r\n"));
$ctx = stream_context_create($opts);
$res = @file_get_contents('http://127.0.0.1:8089/', false, $ctx);
if ($res === false) {
    echo "NOROOT\n";
} else {
    echo substr($res, 0, 500);
}
