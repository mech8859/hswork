<?php
header('Content-Type: text/plain');
echo "PHP OK\n";
echo "curl: " . (function_exists('curl_init') ? 'yes' : 'no') . "\n";
echo "allow_url_fopen: " . ini_get('allow_url_fopen') . "\n";
echo "phpversion: " . phpversion() . "\n";
