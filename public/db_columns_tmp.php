<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$cols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';
foreach ($cols as $c) {
    echo $c['Field'] . ' (' . $c['Type'] . ') ' . ($c['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}
echo '</pre>';
