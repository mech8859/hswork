<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
$sqls = array(
    "ALTER TABLE cases ADD COLUMN construction_area VARCHAR(30) DEFAULT NULL COMMENT '施工區域(縣市鄉鎮區)' AFTER contact_address",
);
echo "<pre>";
foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: " . substr($sql, 0, 80) . "\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP: exists\n";
        } else {
            echo "ERR: " . $e->getMessage() . "\n";
        }
    }
}
echo "Done.\n</pre>";
