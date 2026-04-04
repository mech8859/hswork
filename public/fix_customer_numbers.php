<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

echo '<h2>客戶編號系統升級</h2>';

try {
    $db = Database::getInstance();
    
    // 1. 新增 legacy_customer_no 欄位（存放 Ragic 原資料編號）
    echo '<h3>1. 新增 legacy_customer_no 欄位</h3>';
    try {
        $db->exec("ALTER TABLE customers ADD COLUMN legacy_customer_no VARCHAR(50) DEFAULT NULL AFTER customer_no");
        echo '<p style="color:green">✓ 已新增 legacy_customer_no 欄位</p>';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo '<p style="color:orange">⚠ 欄位已存在，跳過</p>';
        } else {
            throw $e;
        }
    }
    
    // 2. 將現有 customer_no 複製到 legacy_customer_no
    echo '<h3>2. 複製現有編號到原資料客戶編號</h3>';
    $affected = $db->exec("UPDATE customers SET legacy_customer_no = customer_no WHERE legacy_customer_no IS NULL AND customer_no IS NOT NULL AND customer_no != ''");
    echo "<p style='color:green'>✓ 已複製 {$affected} 筆原資料客戶編號</p>";
    
    // 3. 用新格式重新產生 customer_no
    echo '<h3>3. 新增客戶到自動編號設定</h3>';
    try {
        $db->exec("INSERT INTO number_sequences (module, module_label, prefix, date_format, `separator`, seq_digits) VALUES ('customers', '客戶編號', 'CU', 'Ym', '-', 3)");
        echo '<p style="color:green">✓ 已新增客戶自動編號設定</p>';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo '<p style="color:orange">⚠ 客戶編號設定已存在，跳過</p>';
        } else {
            throw $e;
        }
    }
    
    // 4. 將「案件」改名為「進件編號」
    echo '<h3>4. 案件改名為進件編號</h3>';
    $affected = $db->exec("UPDATE number_sequences SET module_label = '進件編號' WHERE module = 'cases'");
    echo "<p style='color:green'>✓ 已更新 {$affected} 筆</p>";
    
    // 5. 顯示結果
    echo '<h3>5. 目前自動編號設定</h3>';
    $rows = $db->query("SELECT module, module_label, prefix, date_format, `separator`, seq_digits FROM number_sequences ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo '<table border="1" cellpadding="5"><tr><th>Module</th><th>名稱</th><th>前綴</th><th>日期</th><th>分隔</th><th>位數</th></tr>';
    foreach ($rows as $r) {
        echo '<tr><td>'.$r['module'].'</td><td>'.$r['module_label'].'</td><td>'.$r['prefix'].'</td><td>'.$r['date_format'].'</td><td>'.$r['separator'].'</td><td>'.$r['seq_digits'].'</td></tr>';
    }
    echo '</table>';
    
    // 6. 顯示客戶資料範例
    echo '<h3>6. 客戶資料範例（前10筆）</h3>';
    $rows = $db->query("SELECT id, customer_no, legacy_customer_no, name FROM customers ORDER BY id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo '<table border="1" cellpadding="5"><tr><th>ID</th><th>客戶編號(新)</th><th>原資料客戶編號</th><th>名稱</th></tr>';
    foreach ($rows as $r) {
        echo '<tr><td>'.$r['id'].'</td><td>'.($r['customer_no'] ?: '-').'</td><td>'.($r['legacy_customer_no'] ?: '-').'</td><td>'.htmlspecialchars($r['name']).'</td></tr>';
    }
    echo '</table>';
    
    echo '<br><a href="/customers.php">返回客戶管理</a>';
    
} catch (Exception $e) {
    echo '<p style="color:red">✗ 錯誤: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
