<?php
/**
 * 員林分公司零用金 — 從 Ragic API 全量重新同步
 * 流程：刪除員林分公司既有零用金 → 從 Ragic 全量抓取 → 全部 INSERT
 *
 * 用法:
 *   ?dry=1     ← 預覽模式（預設，不寫入）
 *   ?execute=1 ← 執行模式（清空 + 重新同步）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') die('需要管理員權限');

header('Content-Type: text/html; charset=utf-8');
set_time_limit(600);
ini_set('memory_limit', '512M');

$db = Database::getInstance();
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';

echo '<pre style="font-family:monospace">';
echo "=== 員林分公司零用金 重新同步 ===\n";
echo "模式: " . ($execute ? '執行（清空+寫入）' : '預覽（不寫入）') . "\n\n";

// 1. 抓 Ragic 資料
$API_KEY = 'dGhmNTRyZk9uMlRUS2c3MjhhQytMZjlZdCtQc1lUMVJHYzVCNlA0dFFVZm1tREk0MFVxU0JibnRmNGV3TElEMA==';
$url = 'https://ap15.ragic.com/hstcc/yuanlin-case-tracking-sheet/21?api';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    echo "Ragic API 失敗 (HTTP $httpCode)\n";
    echo substr($resp, 0, 500);
    exit;
}

$ragicData = json_decode($resp, true);
if (!is_array($ragicData)) {
    echo "JSON 解析失敗\n";
    exit;
}

echo "Ragic 取得: " . count($ragicData) . " 筆\n";

// 2. 取得員林分公司 ID
$branchStmt = $db->prepare("SELECT id, name FROM branches WHERE name LIKE '%員林%'");
$branchStmt->execute();
$branchRow = $branchStmt->fetch(PDO::FETCH_ASSOC);
if (!$branchRow) {
    echo "找不到員林分公司\n";
    exit;
}
$tanziBranchId = $branchRow['id'];
echo "員林分公司 ID: $tanziBranchId ({$branchRow['name']})\n";

// 3. 統計現有員林零用金筆數
$cntStmt = $db->prepare("SELECT COUNT(*) FROM petty_cash WHERE branch_id = ?");
$cntStmt->execute(array($tanziBranchId));
$existingCount = (int)$cntStmt->fetchColumn();
echo "現有員林零用金: $existingCount 筆\n\n";

// 工具函式
function cleanDate($d) {
    if (!$d) return null;
    $d = str_replace('/', '-', trim($d));
    if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}/', $d, $m)) {
        $parts = explode('-', $m[0]);
        return sprintf('%04d-%02d-%02d', $parts[0], $parts[1], $parts[2]);
    }
    return null;
}
function cleanAmount($v) {
    return (int)str_replace(array(',', ' '), '', $v ?: '0');
}
function nullIfEmpty($v) {
    $v = trim((string)$v);
    return $v === '' ? null : $v;
}

// 4. 預覽前 5 筆
echo "=== 預覽前 5 筆 ===\n";
$sample = 0;
foreach ($ragicData as $rid => $row) {
    if ($sample >= 5) break;
    echo "  [$rid] {$row['零用金編號']} | 登記:{$row['登記日期']} | 款項:{$row['款項日期']} | {$row['收支別']} | 出:{$row['支出總金額']} 入:{$row['收入金額']} | {$row['用途說明']}\n";
    $sample++;
}
echo "\n";

if (!$execute) {
    echo "=== 預覽完成 ===\n";
    echo "確認無誤後加 ?execute=1 執行清空+重新同步\n";
    echo '</pre>';
    echo '<a href="?execute=1" onclick="return confirm(\'確定清空員林全部 ' . $existingCount . ' 筆零用金，並從 Ragic 重新匯入 ' . count($ragicData) . ' 筆？\')" style="display:inline-block;padding:10px 20px;background:#e53935;color:#fff;border-radius:6px;text-decoration:none;font-weight:600">執行清空+重新同步</a>';
    exit;
}

// 5. 執行模式：開始同步
$db->beginTransaction();
try {
    // 5-1. 清空員林分公司零用金
    $delStmt = $db->prepare("DELETE FROM petty_cash WHERE branch_id = ?");
    $delStmt->execute(array($tanziBranchId));
    $deletedCount = $delStmt->rowCount();
    echo "已刪除員林零用金: $deletedCount 筆\n\n";

    // 5-2. 從 Ragic 全量寫入
    $added = 0;
    $errors = array();
    $insertStmt = $db->prepare("
        INSERT INTO petty_cash (
            entry_number, entry_date, expense_date, branch_id, type,
            has_invoice, invoice_info, expense_untaxed, expense_tax, expense_amount,
            income_amount, description, registrar, user_name, approval_status,
            approval_date, upload_number, ragic_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($ragicData as $rid => $row) {
        try {
            $type = (isset($row['收支別']) && $row['收支別'] === '收入') ? '收入' : '支出';
            $insertStmt->execute(array(
                nullIfEmpty($row['零用金編號']),
                cleanDate(isset($row['登記日期']) ? $row['登記日期'] : null),
                cleanDate(isset($row['款項日期']) ? $row['款項日期'] : null),
                $tanziBranchId,
                $type,
                nullIfEmpty(isset($row['有無發票']) ? $row['有無發票'] : '無發票'),
                nullIfEmpty(isset($row['發票資訊']) ? $row['發票資訊'] : ''),
                cleanAmount(isset($row['支出未稅金額']) ? $row['支出未稅金額'] : 0),
                cleanAmount(isset($row['支出稅額']) ? $row['支出稅額'] : 0),
                cleanAmount(isset($row['支出總金額']) ? $row['支出總金額'] : 0),
                cleanAmount(isset($row['收入金額']) ? $row['收入金額'] : 0),
                nullIfEmpty(isset($row['用途說明']) ? $row['用途說明'] : ''),
                nullIfEmpty(isset($row['登記人']) ? $row['登記人'] : ''),
                nullIfEmpty(isset($row['使用者']) ? $row['使用者'] : ''),
                nullIfEmpty(isset($row['簽核狀態']) ? $row['簽核狀態'] : ''),
                cleanDate(isset($row['簽核日期']) ? $row['簽核日期'] : null),
                nullIfEmpty(isset($row['上傳編號']) ? $row['上傳編號'] : ''),
                (int)$rid,
            ));
            $added++;
        } catch (Exception $e) {
            $errors[] = "RID $rid: " . $e->getMessage();
            if (count($errors) > 20) break;
        }
    }

    $db->commit();
    echo "✅ 已新增: $added 筆\n";
    if (!empty($errors)) {
        echo "\n=== 錯誤 (前 20) ===\n";
        foreach ($errors as $e) echo "  $e\n";
    }
} catch (Exception $e) {
    $db->rollBack();
    echo "❌ 失敗，已 rollback: " . $e->getMessage() . "\n";
}

echo "\n=== 完成 ===\n";
echo '</pre>';
