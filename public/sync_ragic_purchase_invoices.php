<?php
/**
 * 從 Ragic 同步進項發票到 purchase_invoices
 * 用發票字軌（invoice_number）做唯一鍵避免重複
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';

$API_KEY = 'dGhmNTRyZk9uMlRUS2c3MjhhQytMZjlZdCtQc1lUMVJHYzVCNlA0dFFVZm1tREk0MFVxU0JibnRmNGV3TElEMA==';
$RAGIC_URL = 'https://ap15.ragic.com/hstcc/-4/3';

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===" : "=== 預覽模式 === (加 ?execute=1 執行)";
echo "\n\n";

// 1. 抓 Ragic 資料
echo "[1] 從 Ragic 抓取進項發票...\n";
$allRecords = array();
$offset = 0;
$limit = 100;
while (true) {
    $url = $RAGIC_URL . '?api&limit=' . $limit . '&offset=' . $offset;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) { echo "API 錯誤: HTTP {$httpCode}\n"; break; }
    $batch = json_decode($response, true);
    if (!$batch || empty($batch)) break;
    $allRecords = $allRecords + $batch;
    echo "  已抓取 " . count($allRecords) . " 筆 (offset={$offset})\n";
    ob_flush(); flush();
    if (count($batch) < $limit) break;
    $offset += $limit;
}
echo "共 " . count($allRecords) . " 筆\n\n";

$db = Database::getInstance();

// 2. 取得已存在的發票字軌（避免重複）
$existingStmt = $db->query("SELECT invoice_number FROM purchase_invoices WHERE invoice_number IS NOT NULL AND invoice_number != ''");
$existingMap = array();
foreach ($existingStmt->fetchAll(PDO::FETCH_COLUMN) as $num) {
    $existingMap[$num] = true;
}
echo "系統已有 " . count($existingMap) . " 筆進項發票\n\n";

// 3. 廠商對照（用統編找 vendor_id）
$vendorMap = array();
$vStmt = $db->query("SELECT id, tax_id FROM vendors WHERE tax_id IS NOT NULL AND tax_id != ''");
foreach ($vStmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
    $vendorMap[$v['tax_id']] = (int)$v['id'];
}

// 4. 同步
echo "[2] 同步...\n\n";
$stats = array('new' => 0, 'skip' => 0, 'skip_empty' => 0, 'skip_void' => 0, 'error' => 0);

// Ragic 欄位 → 系統欄位
foreach ($allRecords as $ragicId => $rec) {
    $invoiceNumber = isset($rec['發票字軌']) ? trim($rec['發票字軌']) : '';
    $invoiceDate = isset($rec['發票日期']) ? trim($rec['發票日期']) : '';
    $vendorName = isset($rec['廠商名稱']) ? trim($rec['廠商名稱']) : (isset($rec['抬頭1']) ? trim($rec['抬頭1']) : '');
    $vendorTaxId = isset($rec['統編1']) ? trim($rec['統編1']) : '';
    $invoiceType = isset($rec['發票種類']) ? trim($rec['發票種類']) : '';
    $amountUntaxed = isset($rec['未稅金額']) ? (float)$rec['未稅金額'] : 0;
    $taxAmount = isset($rec['稅額']) ? (float)$rec['稅額'] : 0;
    $totalAmount = isset($rec['小計']) ? (float)$rec['小計'] : 0;
    $docType = isset($rec['單據類型']) ? trim($rec['單據類型']) : '';
    $docStatus = isset($rec['單據狀態']) ? trim($rec['單據狀態']) : '';
    $attribute = isset($rec['屬性']) ? trim($rec['屬性']) : '';

    // 跳過作廢
    if ($docStatus === '作廢') { $stats['skip_void']++; continue; }

    // 跳過無發票字軌
    if (!$invoiceNumber) { $stats['skip_empty']++; continue; }

    // 跳過已存在
    if (isset($existingMap[$invoiceNumber])) { $stats['skip']++; continue; }

    // 日期格式轉換
    $invoiceDate = str_replace('/', '-', $invoiceDate);

    // 發票格式對照
    $invoiceFormat = '';
    if ($invoiceType === '電子發票') $invoiceFormat = 'electronic';
    elseif ($invoiceType === '三聯式') $invoiceFormat = 'triplicate';
    elseif ($invoiceType === '二聯式') $invoiceFormat = 'duplicate';
    elseif ($invoiceType === '收據') $invoiceFormat = 'receipt';

    // 狀態對照
    $status = 'confirmed';
    if ($docStatus === '草稿') $status = 'pending';

    // 扣抵類型
    $deductionType = 'deductible';

    // vendor_id
    $vendorId = isset($vendorMap[$vendorTaxId]) ? $vendorMap[$vendorTaxId] : null;

    // 計算期間
    $period = '';
    if ($invoiceDate) {
        $m = (int)date('m', strtotime($invoiceDate));
        $y = date('Y', strtotime($invoiceDate));
        $biMonth = (int)ceil($m / 2);
        $period = $y . '-' . str_pad($biMonth * 2 - 1, 2, '0', STR_PAD_LEFT) . '/' . str_pad($biMonth * 2, 2, '0', STR_PAD_LEFT);
    }

    // 如果沒有稅額但有總額，自動計算
    if ($totalAmount > 0 && $taxAmount == 0 && $amountUntaxed == 0) {
        $amountUntaxed = round($totalAmount / 1.05);
        $taxAmount = $totalAmount - $amountUntaxed;
    }
    if ($totalAmount == 0 && $amountUntaxed > 0) {
        $totalAmount = $amountUntaxed + $taxAmount;
    }

    echo "[NEW] {$invoiceNumber} | {$invoiceDate} | {$vendorName} | \${$totalAmount}\n";

    if ($execute) {
        try {
            $db->prepare("
                INSERT INTO purchase_invoices
                (invoice_number, invoice_date, vendor_id, vendor_name, vendor_tax_id,
                 invoice_type, amount_untaxed, tax_amount, total_amount, tax_rate,
                 deduction_type, period, status, note,
                 invoice_format, deduction_category,
                 created_by, created_at)
                VALUES (?,?,?,?,?, ?,?,?,?,?, ?,?,?,?, ?,?, ?, NOW())
            ")->execute(array(
                $invoiceNumber,
                $invoiceDate ?: date('Y-m-d'),
                $vendorId,
                $vendorName ?: null,
                $vendorTaxId ?: null,
                '應稅',
                $amountUntaxed,
                $taxAmount,
                $totalAmount,
                5,
                $deductionType,
                $period ?: null,
                $status,
                $attribute ? "屬性:{$attribute}" : null,
                $invoiceFormat ?: null,
                null,
                Auth::id(),
            ));
            $stats['new']++;
        } catch (Exception $e) {
            echo "  [ERROR] " . $e->getMessage() . "\n";
            $stats['error']++;
        }
    } else {
        $stats['new']++;
    }
}

echo "\n=== 完成 ===\n";
echo "新增: {$stats['new']}\n";
echo "已存在跳過: {$stats['skip']}\n";
echo "無字軌跳過: {$stats['skip_empty']}\n";
echo "作廢跳過: {$stats['skip_void']}\n";
echo "錯誤: {$stats['error']}\n";

if (!$execute) echo "\n→ 確認後加 ?execute=1 執行\n";
echo '</pre>';
