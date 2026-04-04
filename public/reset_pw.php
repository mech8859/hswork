<?php
if (!isset($_GET['token']) || $_GET['token'] !== 'hswork2026fix') die('no');
header('Content-Type: text/plain; charset=utf-8');
$db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$hash = password_hash('Hs888888', PASSWORD_DEFAULT);

// 未登入過 = must_change_password 還是 1 的
$stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE must_change_password = 1");
$stmt->execute(array($hash));
$affected = $stmt->rowCount();
echo "已重設 {$affected} 位人員密碼為 Hs888888\n";
