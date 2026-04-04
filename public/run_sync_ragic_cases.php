<?php
/**
 * Ragic → hswork 案件同步腳本
 * 規則：
 *   1. Ragic 有、系統沒有 → 新增
 *   2. Ragic 有、系統有 → 比對更新（不覆蓋系統專有欄位）
 *   3. Ragic 沒有、系統有 → 刪除（但保留檔案/照片）
 *   4. 系統專有欄位（difficulty, estimated_hours, max_engineers, work_time, urgency 等排工欄位）不覆蓋
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) {
    die('需要管理員權限');
}

set_time_limit(600);
ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 案件同步</h2><pre>';
ob_flush(); flush();

$db = Database::getInstance();
$dryRun = !isset($_GET['execute']); // 預設預覽模式

if ($dryRun) {
    echo "【預覽模式】不會實際寫入資料庫。加 ?execute=1 執行同步。\n\n";
} else {
    echo "【執行模式】同步中...\n\n";
}

// ===== 1. 載入 Ragic JSON =====
$jsonFile = __DIR__ . '/../database/ragic_cases_20260405.json';
if (!file_exists($jsonFile)) {
    die('找不到 Ragic JSON 檔案: ' . $jsonFile);
}
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic 資料: " . count($ragicData) . " 筆\n";

// ===== 2. 載入系統現有案件 =====
$existingCases = array();
$stmt = $db->query('SELECT id, case_number, ragic_id, updated_at FROM cases ORDER BY id');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingCases[$row['case_number']] = $row;
}
echo "系統現有案件: " . count($existingCases) . " 筆\n\n";

// ===== 3. 建立對照表 =====
$branchMap = array();
$bStmt = $db->query('SELECT id, name FROM branches');
while ($b = $bStmt->fetch(PDO::FETCH_ASSOC)) {
    $branchMap[$b['name']] = $b['id'];
    // 也支援不完整名稱
    $branchMap[str_replace('分公司', '', $b['name'])] = $b['id'];
}

$salesMap = array();
$sStmt = $db->query('SELECT id, real_name FROM users WHERE is_active = 1');
while ($s = $sStmt->fetch(PDO::FETCH_ASSOC)) {
    $salesMap[$s['real_name']] = $s['id'];
}

// 案別對照
$caseTypeMap = array(
    '新案' => 'new_install',
    '老客戶追加' => 'addition',
    '舊客戶維修案' => 'old_repair',
    '新客戶維修案' => 'new_repair',
    '維護保養' => 'maintenance',
);

// 進度對照
$statusMap = array(
    '待追蹤' => 'tracking',
    '未完工' => 'incomplete',
    '完工未收款' => 'unpaid',
    '已完工待簽核' => 'completed_pending',
    '已完工結案' => 'closed',
    '未成交' => 'lost',
    '已排工' => 'scheduled',
    '已排工/已排行事曆' => 'scheduled',
    '已排行事曆' => 'scheduled',
    '待安排派工查修' => 'awaiting_dispatch',
    '保養案件' => 'maintenance_case',
    '毀約' => 'breach',
    '客戶取消' => 'cancelled',
    '已進場/需再安排' => 'revisit',
);

// ===== 4. 欄位對照函數 =====
function ragicToHswork($r) {
    global $branchMap, $salesMap, $caseTypeMap, $statusMap;

    $caseNumber = trim($r['進件編號'] ?? '');
    if (!$caseNumber) return null;

    // 分公司
    $branchName = trim($r['所屬分公司'] ?? '');
    $branchId = isset($branchMap[$branchName]) ? $branchMap[$branchName] : (isset($branchMap[str_replace('分公司', '', $branchName)]) ? $branchMap[str_replace('分公司', '', $branchName)] : 1);

    // 業務
    $salesName = trim($r['承辦業務'] ?? '');
    $salesId = isset($salesMap[$salesName]) ? $salesMap[$salesName] : null;

    // 案別
    $caseTypeRaw = trim($r['案別'] ?? '新案');
    $caseType = isset($caseTypeMap[$caseTypeRaw]) ? $caseTypeMap[$caseTypeRaw] : 'new_install';

    // 進度
    $statusRaw = trim($r['案件進度'] ?? '待追蹤');
    $status = isset($statusMap[$statusRaw]) ? $statusMap[$statusRaw] : 'tracking';

    // 狀態（子狀態）
    $subStatus = trim($r['狀態'] ?? '');

    // 客戶名稱
    $customerName = trim($r['客戶名稱(新建)'] ?? '');
    if (!$customerName) $customerName = trim($r['客戶名稱(現有客戶)'] ?? '');
    $title = $customerName ?: $caseNumber;

    // 日期轉換
    $parseDate = function($v) {
        if (!$v || !trim($v)) return null;
        $v = trim($v);
        $v = str_replace('/', '-', $v);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) return substr($v, 0, 10);
        return null;
    };

    // 金額
    $parseNum = function($v) {
        if (!$v || !trim($v)) return null;
        $v = str_replace(',', '', trim($v));
        return is_numeric($v) ? (int)round((float)$v) : null;
    };

    // 系統別
    $systemType = trim($r['系統別'] ?? '');

    // 客戶需求 (array → comma string)
    $needs = $r['客戶需求'] ?? '';
    if (is_array($needs)) $needs = implode(',', $needs);

    // 是否含稅
    $taxIncluded = trim($r['是否含稅'] ?? '');

    return array(
        'case_number'    => $caseNumber,
        'branch_id'      => $branchId,
        'title'          => $title,
        'case_type'      => $caseType,
        'status'         => $status,
        'sub_status'     => $subStatus ?: null,
        'address'        => trim($r['地址'] ?? '') ?: null,
        'description'    => trim($r['備註'] ?? '') ?: null,
        'system_type'    => $systemType ?: null,
        'ragic_id'       => isset($r['_ragicId']) ? (string)$r['_ragicId'] : null,
        'sales_id'       => $salesId,
        'customer_name'  => $customerName ?: null,
        'customer_category' => trim($r['客戶分類'] ?? '') ?: null,
        'customer_phone' => trim($r['家用/公司電話'] ?? '') ?: null,
        'customer_mobile'=> trim($r['行動電話'] ?? '') ?: null,
        'contact_person' => trim($r['聯絡人'] ?? '') ?: null,
        'customer_email' => trim($r['mail'] ?? '') ?: null,
        'deal_date'      => $parseDate($r['成交日期'] ?? ''),
        'deal_amount'    => $parseNum($r['成交金額(未稅)'] ?? ''),
        'is_tax_included'=> $taxIncluded ?: null,
        'tax_amount'     => $parseNum($r['稅金'] ?? ''),
        'total_amount'   => $parseNum($r['含稅金額'] ?? ''),
        'deposit_amount' => $parseNum($r['訂金金額'] ?? ''),
        'deposit_method' => trim($r['訂金支付方式'] ?? '') ?: null,
        'deposit_payment_date' => $parseDate($r['訂金付款日'] ?? ''),
        'balance_amount' => $parseNum($r['尾款'] ?? ''),
        'total_collected'=> $parseNum($r['總收款金額'] ?? ''),
        'quote_amount'   => $parseNum($r['報價金額'] ?? ''),
        'planned_start_date' => $parseDate($r['預計施作日期'] ?? ''),
        'planned_end_date'   => $parseDate($r['預計完工日期'] ?? ''),
        'completion_date'    => $parseDate($r['完工日期'] ?? ''),
        'billing_title'  => trim($r['發票抬頭'] ?? '') ?: null,
        'billing_tax_id' => trim($r['統一編號'] ?? '') ?: null,
        'billing_address'=> trim($r['發票寄送地址'] ?? '') ?: null,
        'billing_email'  => trim($r['發票寄送mail'] ?? '') ?: null,
        'registrar'      => trim($r['登記人員'] ?? '') ?: null,
        'case_source'    => trim($r['案件來源'] ?? '') ?: null,
        'company'        => trim($r['進件公司'] ?? '') ?: null,
        'created_at_ragic' => $parseDate($r['進件日期'] ?? ''),
        // 維修相關
        'repair_report_date'  => $parseDate($r['維修申告日期'] ?? ''),
        'repair_fault_reason' => trim($r['客戶申告故障原因'] ?? '') ?: null,
        'repair_equipment'    => trim($r['維修器材'] ?? '') ?: null,
        'repair_result'       => trim($r['維修結果'] ?? '') ?: null,
        'repair_description'  => trim($r['維修完成說明'] ?? '') ?: null,
        'repair_original_case'=> trim($r['原案件客戶編號'] ?? '') ?: null,
        'repair_original_complete_date' => $parseDate($r['原案件完工日期'] ?? ''),
        'repair_original_warranty_date' => $parseDate($r['原案件保固日期'] ?? ''),
        'repair_is_charged'   => trim($r['有無收費'] ?? '') ?: null,
        'repair_no_charge_reason' => trim($r['不收費原因'] ?? '') ?: null,
        'notes'          => trim($r['業務備註'] ?? '') ?: null,
        // 客戶編號 (for reference)
        '_customer_no'   => trim($r['客戶編號'] ?? ''),
        '_visit_date'    => $parseDate($r['拜訪日期'] ?? ''),
        '_visit_method'  => trim($r['拜訪方式'] ?? ''),
        '_max_engineers'  => $parseNum($r['預計施工人數'] ?? ''),
    );
}

// ===== 5. 同步邏輯 =====
$insertCount = 0;
$updateCount = 0;
$skipCount = 0;
$deleteCount = 0;
$errorCount = 0;
$ragicCaseNumbers = array();

// 不覆蓋的系統專有欄位
$systemOnlyFields = array('difficulty', 'estimated_hours', 'max_engineers', 'work_time_start', 'work_time_end',
    'has_time_restriction', 'customer_break_time', 'allow_night_work', 'urgency', 'is_large_project',
    'is_flexible', 'current_visit', 'total_visits', 'customer_id', 'created_by', 'updated_by',
    'system_difficulty');

// 可同步的欄位
$syncFields = array(
    'branch_id', 'title', 'case_type', 'status', 'sub_status', 'address', 'description',
    'system_type', 'ragic_id', 'sales_id', 'customer_name', 'customer_category',
    'customer_phone', 'customer_mobile', 'contact_person', 'customer_email',
    'deal_date', 'deal_amount', 'is_tax_included', 'tax_amount', 'total_amount',
    'deposit_amount', 'deposit_method', 'deposit_payment_date', 'balance_amount',
    'total_collected', 'quote_amount', 'planned_start_date', 'planned_end_date',
    'billing_title', 'billing_tax_id', 'billing_address', 'billing_email',
    'registrar', 'notes',
    'repair_report_date', 'repair_fault_reason', 'repair_equipment',
    'repair_result', 'repair_description', 'repair_original_case',
    'repair_original_complete_date', 'repair_original_warranty_date',
    'repair_is_charged', 'repair_no_charge_reason',
);

foreach ($ragicData as $ragicId => $ragicRow) {
    $mapped = ragicToHswork($ragicRow);
    if (!$mapped) continue;

    $cn = $mapped['case_number'];
    $ragicCaseNumbers[$cn] = true;

    if (isset($existingCases[$cn])) {
        // === 更新 ===
        $existId = $existingCases[$cn]['id'];

        if (!$dryRun) {
            // 取得現有資料比對
            $curStmt = $db->prepare('SELECT * FROM cases WHERE id = ?');
            $curStmt->execute(array($existId));
            $current = $curStmt->fetch(PDO::FETCH_ASSOC);

            $updates = array();
            $params = array();
            foreach ($syncFields as $field) {
                if (!array_key_exists($field, $mapped)) continue;
                $newVal = $mapped[$field];
                $curVal = $current[$field] ?? null;

                // Ragic 為準，空值也覆蓋（文字資料以 Ragic 為準）
                // 值不同才更新
                if ((string)$newVal !== (string)$curVal) {
                    $updates[] = "`{$field}` = ?";
                    $params[] = $newVal;
                }
            }

            if (!empty($updates)) {
                $params[] = $existId;
                $sql = "UPDATE cases SET " . implode(', ', $updates) . " WHERE id = ?";
                try {
                    $db->prepare($sql)->execute($params);
                    $updateCount++;
                    echo "更新 {$cn} (ID:{$existId}) - " . count($updates) . " 個欄位\n";
                } catch (Exception $e) {
                    $errorCount++;
                    echo "❌ 更新失敗 {$cn}: " . $e->getMessage() . "\n";
                }
            } else {
                $skipCount++;
            }
        } else {
            $updateCount++;
        }
    } else {
        // === 新增 ===
        if (!$dryRun) {
            $cols = array('case_number');
            $vals = array($cn);
            foreach ($syncFields as $field) {
                if (!array_key_exists($field, $mapped)) continue;
                if ($mapped[$field] !== null) {
                    $cols[] = $field;
                    $vals[] = $mapped[$field];
                }
            }
            // 補上 max_engineers
            if ($mapped['_max_engineers']) {
                $cols[] = 'max_engineers';
                $vals[] = $mapped['_max_engineers'];
            }
            $cols[] = 'created_by';
            $vals[] = Auth::id();

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $colStr = implode(',', array_map(function($c) { return "`{$c}`"; }, $cols));

            try {
                $db->prepare("INSERT INTO cases ({$colStr}) VALUES ({$placeholders})")->execute($vals);
                $insertCount++;
                echo "新增 {$cn} - {$mapped['title']}\n";
            } catch (Exception $e) {
                $errorCount++;
                echo "❌ 新增失敗 {$cn}: " . $e->getMessage() . "\n";
            }
        } else {
            $insertCount++;
            echo "將新增 {$cn} - {$mapped['title']}\n";
        }
    }
}

// === 刪除（Ragic 沒有但系統有的）===
foreach ($existingCases as $cn => $caseRow) {
    if (!isset($ragicCaseNumbers[$cn])) {
        $deleteCount++;
        if ($dryRun) {
            echo "將刪除 {$cn} (ID:{$caseRow['id']})\n";
        } else {
            // 刪除案件但保留檔案
            try {
                // 只刪除 DB 記錄，不刪除檔案
                $db->prepare('DELETE FROM case_contacts WHERE case_id = ?')->execute(array($caseRow['id']));
                $db->prepare('DELETE FROM case_readiness WHERE case_id = ?')->execute(array($caseRow['id']));
                $db->prepare('DELETE FROM case_site_conditions WHERE case_id = ?')->execute(array($caseRow['id']));
                $db->prepare('DELETE FROM case_required_skills WHERE case_id = ?')->execute(array($caseRow['id']));
                // 附件和施工照片的 DB 記錄刪除，但實體檔案保留
                $db->prepare('DELETE FROM case_attachments WHERE case_id = ?')->execute(array($caseRow['id']));
                $db->prepare('DELETE FROM case_work_logs WHERE case_id = ?')->execute(array($caseRow['id']));
                $db->prepare('DELETE FROM case_payments WHERE case_id = ?')->execute(array($caseRow['id']));
                $db->prepare('DELETE FROM cases WHERE id = ?')->execute(array($caseRow['id']));
                echo "已刪除 {$cn} (ID:{$caseRow['id']})\n";
            } catch (Exception $e) {
                $errorCount++;
                echo "❌ 刪除失敗 {$cn}: " . $e->getMessage() . "\n";
            }
        }
    }
}

echo "\n===== 同步結果 =====\n";
echo "新增: {$insertCount} 筆\n";
echo "更新: {$updateCount} 筆\n";
echo "無變更: {$skipCount} 筆\n";
echo "刪除: {$deleteCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo "Ragic 總筆數: " . count($ragicData) . "\n";
echo "系統原有: " . count($existingCases) . "\n";

if ($dryRun) {
    echo "\n<a href='?execute=1' style='font-size:1.2em;color:red'>⚠ 點此執行同步（不可復原）</a>\n";
}
echo '</pre>';
