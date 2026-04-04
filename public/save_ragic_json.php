<?php
// Save Ragic JSON data to file
if ($_GET['token'] !== 'hswork2026img') die('invalid token');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$input = file_get_contents('php://input');
if (empty($input)) $input = $_POST['data'] ?? '';
if (empty($input)) die('no data');

$filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $_GET['file'] ?? 'ragic_staff_all.json');
if (empty($filename)) $filename = 'ragic_staff_all.json';
$path = __DIR__ . '/../' . $filename;
file_put_contents($path, $input);
echo 'saved ' . strlen($input) . ' bytes to ' . $filename;
