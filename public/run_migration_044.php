<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');

// Quick fixes
if (isset($_GET['fix'])) {
    $db = Database::getInstance();
    $f = $_GET['fix'];
    if ($f === '1') {
        $affected = $db->exec("UPDATE customers SET legacy_customer_no = original_customer_no WHERE original_customer_no IS NOT NULL AND original_customer_no != ''");
        echo "<pre>已更新 legacy_customer_no: {$affected} 筆</pre>";
    } elseif ($f === '2') {
        $affected = $db->exec("UPDATE customers SET category = 'commercial' WHERE category = 'vendor'");
        echo "<pre>已將叫貨廠商改為商辦: {$affected} 筆</pre>";
    } elseif ($f === 'cases') {
        $stmts = array(
            "ALTER TABLE cases ADD COLUMN IF NOT EXISTS updated_by INT UNSIGNED DEFAULT NULL COMMENT '最後修改人'",
            "ALTER TABLE cases ADD COLUMN IF NOT EXISTS registrar VARCHAR(50) DEFAULT NULL COMMENT '登記人'",
        );
        foreach ($stmts as $s) {
            try { $db->exec($s); echo "<pre>OK: " . mb_substr($s, 0, 80) . "</pre>"; }
            catch (PDOException $e) { echo "<pre>WARN: " . $e->getMessage() . "</pre>"; }
        }
        exit;
    } elseif ($f === '3') {
        echo "<pre>=== 更新客戶分類 ===\n";
        $sqlFile = __DIR__ . '/../database/customer_fix_categories.sql';
        if (!file_exists($sqlFile)) { echo "SQL 檔案不存在\n"; exit; }
        $sql = file_get_contents($sqlFile);
        $statements = array_filter(array_map('trim', explode(";\n", $sql)));
        $success = 0; $errors = 0;
        foreach ($statements as $stmt) {
            $stmt = rtrim(trim($stmt), ';');
            if (empty($stmt) || strpos($stmt, '--') === 0) continue;
            try { $db->exec($stmt); $success++; }
            catch (PDOException $e) { echo "[錯誤] " . $e->getMessage() . "\n"; $errors++; }
        }
        echo "完成: {$success} 成功, {$errors} 錯誤\n";
        $stmt = $db->query("SELECT category, COUNT(*) as cnt FROM customers WHERE is_active = 1 AND category IS NOT NULL GROUP BY category ORDER BY cnt DESC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            echo "  {$row['category']}: {$row['cnt']}\n";
        }
        echo "</pre>";
    }
    exit;
}
echo '<h2>Migration 044: 客戶資料強化</h2><pre>';

$db = Database::getInstance();
$sqlFile = __DIR__ . '/../database/migration_044_customer_enhance.sql';
if (!file_exists($sqlFile)) { die('SQL 檔案不存在'); }

$sql = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', explode(";\n", $sql)));

$success = 0; $errors = 0;
foreach ($statements as $stmt) {
    $stmt = rtrim(trim($stmt), ';');
    if (empty($stmt) || strpos($stmt, '--') === 0) continue;
    try {
        $db->exec($stmt);
        echo "OK: " . mb_substr($stmt, 0, 80) . "\n";
        $success++;
    } catch (PDOException $e) {
        echo "WARN: " . mb_substr($stmt, 0, 80) . "\n   " . $e->getMessage() . "\n";
        $errors++;
    }
}

// 建立 customer_groups 表
try {
    $db->exec("CREATE TABLE IF NOT EXISTS customer_groups (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(100) NOT NULL,
        tax_id VARCHAR(20) DEFAULT NULL,
        note VARCHAR(200) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tax_id (tax_id),
        INDEX idx_group_name (group_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK: CREATE TABLE customer_groups\n";
    $success++;
} catch (PDOException $e) {
    echo "WARN: customer_groups: " . $e->getMessage() . "\n";
    $errors++;
}

// 額外修正
$extras = array(
    "ALTER TABLE customers ADD COLUMN IF NOT EXISTS case_number VARCHAR(20) DEFAULT NULL COMMENT '進件編號'",
    "ALTER TABLE customers ADD INDEX IF NOT EXISTS idx_case_number (case_number)",
    "ALTER TABLE cases ADD COLUMN IF NOT EXISTS customer_no VARCHAR(20) DEFAULT NULL COMMENT '客戶編號' AFTER customer_id",
    "ALTER TABLE cases ADD INDEX IF NOT EXISTS idx_customer_no (customer_no)",
);
foreach ($extras as $stmt) {
    try {
        $db->exec($stmt);
        echo "OK: " . mb_substr($stmt, 0, 80) . "\n";
        $success++;
    } catch (PDOException $e) {
        echo "WARN: " . mb_substr($stmt, 0, 80) . "\n   " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n完成: {$success} 成功, {$errors} 錯誤\n";
echo '</pre>';
