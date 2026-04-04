<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$importDir = __DIR__ . '/uploads/customer_import';
$customerUploadBase = __DIR__ . '/uploads/customers';

echo '<h2>客戶 JPG 批次匯入（修正版）</h2>';

// 先清除上次錯誤匯入的資料
$db->exec("DELETE FROM customer_files WHERE note = '從ERP客戶資料匯入'");
echo '<p>已清除上次匯入資料，重新匹配</p>';

// 取所有客戶，只匹配「客戶XXX」格式的 legacy_customer_no
$stmt = $db->query("SELECT id, customer_no, legacy_customer_no, name FROM customers WHERE legacy_customer_no LIKE '客戶%' ORDER BY id");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$custByNum = array();
foreach ($customers as $c) {
    // 從「客戶181」提取「181」，從「客戶6208.業#3254」提取「6208」
    if (preg_match('/客戶(\d+)/', $c['legacy_customer_no'], $m)) {
        $num = ltrim($m[1], '0') ?: '0';
        // 只取第一個匹配的（避免重複）
        if (!isset($custByNum[$num])) {
            $custByNum[$num] = $c;
        }
    }
}

echo '<p>可匹配客戶數: ' . count($custByNum) . '</p>';

// 掃描 JPG
$files = glob($importDir . '/*.jpg');
if (empty($files)) {
    // 嘗試大寫
    $files = glob($importDir . '/*.JPG');
}
echo '<p>待匯入 JPG: ' . count($files) . ' 個</p>';

$matched = 0;
$notMatched = 0;
$errors = array();
$matchDetails = array();

$insertStmt = $db->prepare("INSERT INTO customer_files (customer_id, file_type, file_name, file_path, file_size, uploaded_by, note) VALUES (?, ?, ?, ?, ?, ?, ?)");

foreach ($files as $filepath) {
    $filename = basename($filepath);
    
    // 提取開頭編號：「181-xxx.jpg」→「181」，「003-1xxx.jpg」→「3」
    if (!preg_match('/^0*(\d+)/', $filename, $m)) {
        $errors[] = $filename . ' (無法提取編號)';
        $notMatched++;
        continue;
    }
    
    $num = $m[1];
    
    if (!isset($custByNum[$num])) {
        $errors[] = $filename . " (編號{$num}無對應「客戶{$num}」)";
        $notMatched++;
        continue;
    }
    
    $customer = $custByNum[$num];
    $custId = $customer['id'];
    
    // 建目錄
    $custDir = $customerUploadBase . '/' . $custId;
    if (!is_dir($custDir)) mkdir($custDir, 0755, true);
    
    // 複製檔案
    $newPath = $custDir . '/' . $filename;
    if (copy($filepath, $newPath)) {
        $relPath = 'uploads/customers/' . $custId . '/' . $filename;
        $fileSize = filesize($newPath);
        
        try {
            $insertStmt->execute(array(
                $custId,
                'quotation',  // 報價單類型
                $filename,
                $relPath,
                $fileSize,
                Auth::id(),
                '從ERP客戶資料匯入'
            ));
            $matched++;
            $matchDetails[] = array('file' => $filename, 'customer' => $customer['name'], 'legacy' => $customer['legacy_customer_no']);
            unlink($filepath);
        } catch (Exception $e) {
            $errors[] = $filename . ' (DB: ' . $e->getMessage() . ')';
        }
    } else {
        $errors[] = $filename . ' (複製失敗)';
    }
}

echo '<div style="background:#e8f5e9;padding:16px;border-radius:8px;margin:16px 0">';
echo '<h3 style="color:#2e7d32">✓ 成功匯入: ' . $matched . ' 個 JPG（報價單類型）</h3>';
echo '</div>';

if ($notMatched > 0) {
    echo '<div style="background:#fff3e0;padding:16px;border-radius:8px;margin:16px 0">';
    echo '<h3 style="color:#e65100">⚠ 未匹配: ' . $notMatched . ' 個</h3>';
    echo '<details><summary>點擊查看</summary><ul style="font-size:.8rem;max-height:300px;overflow:auto">';
    foreach ($errors as $err) echo '<li>' . htmlspecialchars($err) . '</li>';
    echo '</ul></details></div>';
}

echo '<h3>匹配結果（前30筆）</h3>';
echo '<table border="1" cellpadding="4" style="font-size:.85rem"><tr><th>檔案</th><th>客戶名稱</th><th>原編號</th></tr>';
foreach (array_slice($matchDetails, 0, 30) as $d) {
    echo '<tr><td>' . htmlspecialchars($d['file']) . '</td><td>' . htmlspecialchars($d['customer']) . '</td><td>' . htmlspecialchars($d['legacy']) . '</td></tr>';
}
echo '</table>';

echo '<br><a href="/customers.php">返回客戶管理</a>';
