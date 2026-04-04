<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

$step = isset($_GET['step']) ? (int)$_GET['step'] : -1;
$batch = 500;
$jsonFile = __DIR__ . '/customers_import.json';

if (!file_exists($jsonFile)) {
    echo "ERROR: customers_import.json not found. Upload it first.";
    exit;
}

// Delete action - must check BEFORE preview
if (isset($_GET['action']) && $_GET['action'] === 'delete_imported') {
    echo "<h3>刪除匯入資料</h3>";
    $before = $db->query("SELECT COUNT(*) FROM customers WHERE import_source LIKE 'erp_%'")->fetchColumn();
    echo "準備刪除 {$before} 筆...<br>";
    ob_flush(); flush();
    $deleted = 0;
    while (true) {
        $del = $db->exec("DELETE FROM customers WHERE import_source LIKE 'erp_%' LIMIT 2000");
        if ($del == 0) break;
        $deleted += $del;
        echo "已刪除 {$deleted} 筆...<br>";
        ob_flush(); flush();
    }
    echo "<br><b>完成！共刪除 {$deleted} 筆匯入資料</b><br><br>";
    echo "<a href='?' style='background:#2196F3;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none'>返回預覽</a>";
    exit;
}

$all = json_decode(file_get_contents($jsonFile), true);
$total = count($all);
$totalSteps = ceil($total / $batch);

echo "<h3>客戶資料匯入</h3>";
echo "總筆數: {$total} | 每批: {$batch} | 總步數: {$totalSteps}<br><br>";

if ($step < 0) {
    // Show preview
    echo "<b>預覽模式</b> - 不會寫入資料<br><br>";
    
    // Count by source
    $sources = array();
    foreach ($all as $r) {
        $src = $r['import_source'];
        if (!isset($sources[$src])) $sources[$src] = 0;
        $sources[$src]++;
    }
    foreach ($sources as $s => $c) {
        echo "{$s}: {$c} 筆<br>";
    }
    
    // Check existing
    $existing = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    echo "<br>目前資料庫客戶數: {$existing}<br>";
    
    $imported = $db->query("SELECT COUNT(*) FROM customers WHERE import_source IS NOT NULL")->fetchColumn();
    echo "已匯入(有import_source): {$imported}<br><br>";
    
    echo "<a href='?step=0' style='background:#4CAF50;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:1.1em'>開始匯入 (Step 0)</a><br><br>";
    echo "<a href='?action=delete_imported' style='background:#f44336;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none'>清除所有匯入資料</a>";
    exit;
}

// Import batch
$start = $step * $batch;
$subset = array_slice($all, $start, $batch);

if (empty($subset)) {
    echo "ALL DONE! 所有步驟完成。<br>";
    $total_imported = $db->query("SELECT COUNT(*) FROM customers WHERE import_source IS NOT NULL")->fetchColumn();
    echo "匯入總數: {$total_imported}<br>";
    echo "<a href='/customers.php'>客戶管理</a>";
    exit;
}

echo "Step {$step}/{$totalSteps} (筆 " . ($start+1) . " ~ " . min($start+$batch, $total) . ")<br>";

$inserted = 0;
$updated = 0;
$skipped = 0;

// Prepare statements
$findByNo = $db->prepare("SELECT id FROM customers WHERE customer_no = ? LIMIT 1");
$insert = $db->prepare("INSERT INTO customers (customer_no, name, category, contact_person, phone, mobile, fax, site_city, site_district, site_address, payment_method, line_id, note, import_source, tax_id, is_active, created_at, updated_at) VALUES (?, ?, 'residential', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
$update = $db->prepare("UPDATE customers SET name=?, contact_person=?, phone=?, mobile=?, fax=?, site_city=?, site_district=?, site_address=?, payment_method=?, line_id=?, note=?, import_source=?, tax_id=?, updated_at=NOW() WHERE id=?");

foreach ($subset as $r) {
    $cno = $r['customer_no'];
    if (empty($cno)) { $skipped++; continue; }
    
    $findByNo->execute(array($cno));
    $exists = $findByNo->fetch(PDO::FETCH_ASSOC);
    
    if ($exists) {
        // Update existing
        $update->execute(array(
            $r['name'], $r['contact_person'], $r['phone'], $r['mobile'], $r['fax'],
            $r['site_city'], $r['site_district'], $r['site_address'],
            $r['payment_method'], $r['line_id'], $r['note'], $r['import_source'], $r['tax_id'],
            $exists['id']
        ));
        $updated++;
    } else {
        // Insert new
        try {
            $insert->execute(array(
                $cno, $r['name'], $r['contact_person'], $r['phone'], $r['mobile'], $r['fax'],
                $r['site_city'], $r['site_district'], $r['site_address'],
                $r['payment_method'], $r['line_id'], $r['note'], $r['import_source'], $r['tax_id']
            ));
            $inserted++;
        } catch (Exception $e) {
            $skipped++;
        }
    }
}

echo "新增: {$inserted} | 更新: {$updated} | 跳過: {$skipped}<br><br>";

$nextStep = $step + 1;
if ($nextStep < $totalSteps) {
    echo "<a href='?step={$nextStep}' style='background:#2196F3;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none'>下一步 Step {$nextStep} →</a>";
    // Auto redirect after 1 second
    echo "<script>setTimeout(function(){ window.location='?step={$nextStep}'; }, 1000);</script>";
} else {
    echo "<b>ALL DONE!</b><br>";
    $total_imported = $db->query("SELECT COUNT(*) FROM customers WHERE import_source IS NOT NULL")->fetchColumn();
    echo "匯入總數: {$total_imported}<br>";
    echo "<a href='/customers.php'>客戶管理</a>";
}
