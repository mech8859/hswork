<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo '<h2>客戶文件檢查</h2>';

// 1. customer_files 表是否存在
try {
    $count = $db->query("SELECT COUNT(*) FROM customer_files")->fetchColumn();
    echo "<p>customer_files 表存在，共 {$count} 筆</p>";
} catch (Exception $e) {
    echo '<p style="color:red">customer_files 表不存在: ' . $e->getMessage() . '</p>';
    echo '<p>需要建立表...</p>';
    
    $db->exec("CREATE TABLE IF NOT EXISTS customer_files (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NOT NULL,
        file_type VARCHAR(30) DEFAULT 'other',
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT DEFAULT 0,
        uploaded_by INT UNSIGNED,
        note VARCHAR(200),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo '<p style="color:green">✓ 已建立 customer_files 表</p>';
}

// 2. 檢查客戶181
$stmt = $db->prepare("SELECT id, customer_no, legacy_customer_no, name FROM customers WHERE legacy_customer_no LIKE '%181%'");
$stmt->execute();
$custs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo '<h3>編號含181的客戶</h3>';
foreach ($custs as $c) {
    echo "<p>ID={$c['id']} | {$c['customer_no']} | {$c['legacy_customer_no']} | {$c['name']}</p>";
    
    $fs = $db->prepare("SELECT * FROM customer_files WHERE customer_id = ?");
    $fs->execute(array($c['id']));
    $files = $fs->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>此客戶文件數: " . count($files) . "</p>";
}

// 3. 檢查伺服器上是否有 JPG
$importDir = __DIR__ . '/uploads/customer_import';
if (is_dir($importDir)) {
    $jpgs = glob($importDir . '/*.jpg');
    echo '<h3>暫存 JPG 檔案</h3>';
    echo '<p>customer_import 目錄有 ' . count($jpgs) . ' 個 JPG</p>';
    if (count($jpgs) > 0) {
        echo '<p>前5個: ';
        foreach (array_slice($jpgs, 0, 5) as $j) echo basename($j) . ' | ';
        echo '</p>';
    }
} else {
    echo '<p style="color:red">customer_import 目錄不存在</p>';
}

// 4. 181相關的JPG
$files181 = glob($importDir . '/181*');
echo '<h3>181開頭的JPG</h3>';
echo '<p>' . count($files181) . ' 個: ';
foreach ($files181 as $f) echo basename($f) . ' | ';
echo '</p>';

echo '<br><a href="/import_customer_jpgs.php">執行JPG匯入</a>';
echo ' | <a href="/customers.php">返回客戶管理</a>';
