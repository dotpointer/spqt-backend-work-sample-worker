<?php
$code = isset($_REQUEST['code']) && is_numeric($_REQUEST['code']) ? intval($_REQUEST['code']) : 200;
http_response_code($code);
header('Content-Type: text/plain');
echo 'Response code: '.$code."\n\n".'Usage: request with ?code=<http code>'."\n";
?>
