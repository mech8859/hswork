<?php
/**
 * Ragic 案件全量同步（主表+子表格）
 * - Ragic 有、系統沒有 → 新增
 * - Ragic 有、系統也有 → 以 Ragic 覆蓋更新
 * - 系統有、Ragic 沒有 → 刪除
 * - 附件/圖片 → 只記錄 fileKey（pending），不下載
 *
 * 用法：
 *   ?case=2026-1763          ← 同步單筆
 *   ?branch=潭子&limit=20    ← 同步指定分公司
 *   ?limit=3000              ← 全量同步
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');

set_time_limit(1200);
ini_set('memory_limit', '512M');

$db = Database::getInstance();
$API_KEY = 'dGhmNTRyZk9uMlRUS2c3MjhhQytMZjlZdCtQc1lUMVJHYzVCNlA0dFFVZm1tREk0MFVxU0JibnRmNGV3TElEMA==';
$RAGIC_BASE = 'https://ap15.ragic.com/hstcc/new-case-registration/16';

$targetCase = isset($_GET['case']) ? trim($_GET['case']) : '';
$targetBranch = isset($_GET['branch']) ? trim($_GET['branch']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Ragic 同步</title><style>body{font-family:sans-serif;padding:16px;max-width:900px;margin:0 auto} .ok{color:green} .skip{color:orange} .err{color:red} .info{color:#1976d2} summary{cursor:pointer;font-weight:600;padding:4px 0} table{border-collapse:collapse;width:100%} th,td{border:1px solid #ddd;padding:4px 8px;text-align:left} th{background:#f5f5f5}</style></head><body>';
echo '<h2>Ragic 案件全量同步</h2>';
echo '<p class="info">附件下載: 已停用（file.jsp 無法使用），僅記錄 fileKey</p>';

// ============ 對照表 ============
$branchMap = array();
foreach ($db->query("SELECT id, name FROM branches")->fetchAll() as $b) { $branchMap[$b['name']] = $b['id']; }
$branchAlias = array('潭子分公司'=>'潭子分公司','員林分公司'=>'員林分公司','清水分公司'=>'清水分公司','東區電子鎖'=>'東區電子鎖專賣店','清水電子鎖'=>'清水電子鎖專賣店','中區專案部'=>'中區專案部');
$userMap = array();
foreach ($db->query("SELECT id, real_name FROM users")->fetchAll() as $u) { $userMap[$u['real_name']] = $u['id']; }
$caseTypeMap = array('新案'=>'new_install','老客戶追加'=>'addition','舊客戶維修案'=>'old_repair','新客戶維修案'=>'new_repair','維護保養'=>'maintenance');
$statusMap = array('已成交'=>'incomplete','未指派'=>'tracking','待聯絡'=>'tracking','已聯絡安排場勘'=>'tracking','已聯絡待場勘'=>'tracking','待場勘'=>'tracking','已會勘未報價'=>'tracking','已報價待追蹤'=>'tracking','現簽'=>'incomplete','已聯絡電話報價'=>'tracking','已報價無意願'=>'lost','跨月成交'=>'incomplete','電話報價成交'=>'incomplete','電話不通或未接'=>'tracking','規劃或預算案'=>'tracking','無效'=>'lost','客戶毀約'=>'breach');
$progressMap = array('未完工'=>'incomplete','待追蹤'=>'tracking','待安排派工查修'=>'awaiting_dispatch','完工未收款'=>'unpaid','已完工結案'=>'closed','已完工未收款'=>'unpaid','客戶取消'=>'customer_cancel','未成交'=>'lost','保養案件'=>'maint_case','毀約'=>'breach');
$fileTypeMap = array('報價單'=>'quotation','報價單(檔案)'=>'quotation','圖面'=>'blueprint','保固書'=>'warranty','檔案'=>'other','檔案2'=>'other','相片'=>'site_photo');
$customerCategoryMap = array('住家'=>'個人 / 住戶','公司行號'=>'一般公司 / 企業','工廠'=>'製造 / 工廠','社區'=>'社區 / 管委會','機關'=>'機關 / 政府','學校'=>'教育');

// ============ Ragic API ============
function ragicGet($url) {
    global $API_KEY;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) return null;
    return json_decode($resp, true);
}

// Ragic 日期格式轉換 2026/03/27 → 2026-03-27
function ragicDate($val) {
    if (!$val || $val === '-') return null;
    return str_replace('/', '-', trim($val));
}

// 處理客戶需求（可能是 JSON 陣列）
function parseCustomerNeed($val) {
    if (!$val) return null;
    if (is_array($val)) return implode('、', $val);
    if (substr($val, 0, 1) === '[') {
        $decoded = json_decode($val, true);
        if (is_array($decoded)) return implode('、', $decoded);
    }
    return $val;
}

// 解析 Ragic 欄位成案件資料
function parseRagicCase($rdata, $customerName) {
    global $branchMap, $branchAlias, $userMap, $caseTypeMap, $statusMap, $progressMap, $customerCategoryMap;

    $branchName = isset($rdata['所屬分公司']) ? $rdata['所屬分公司'] : '';
    if (isset($branchAlias[$branchName])) $branchName = $branchAlias[$branchName];
    $branchId = isset($branchMap[$branchName]) ? $branchMap[$branchName] : 1;

    $caseType = isset($caseTypeMap[$rdata['案別']]) ? $caseTypeMap[$rdata['案別']] : 'new_install';
    $progress = isset($rdata['案件進度']) ? $rdata['案件進度'] : '';
    $ragicStatus = isset($rdata['狀態']) ? $rdata['狀態'] : '';
    // status 用對照表轉系統值
    if (isset($progressMap[$progress])) { $status = $progressMap[$progress]; }
    elseif (isset($statusMap[$ragicStatus])) { $status = $statusMap[$ragicStatus]; }
    else { $status = 'tracking'; }

    $salesName = isset($rdata['承辦業務']) ? $rdata['承辦業務'] : '';
    $salesId = isset($userMap[$salesName]) ? $userMap[$salesName] : null;

    $isTaxStr = isset($rdata['是否含稅']) ? $rdata['是否含稅'] : '';
    $customerNeed = parseCustomerNeed(isset($rdata['客戶需求']) ? $rdata['客戶需求'] : null);

    // 客戶分類對照
    $ragicCategory = isset($rdata['客戶分類']) ? $rdata['客戶分類'] : null;
    $customerCategory = isset($customerCategoryMap[$ragicCategory]) ? $customerCategoryMap[$ragicCategory] : $ragicCategory;

    return array(
        'branch_id' => $branchId,
        'case_type' => $caseType,
        'status' => $status,
        'address' => isset($rdata['地址']) ? $rdata['地址'] : null,
        'description' => $customerNeed,
        'sales_id' => $salesId,
        'customer_name' => $customerName ?: null,
        'customer_phone' => isset($rdata['家用/公司電話']) ? $rdata['家用/公司電話'] : null,
        'customer_mobile' => isset($rdata['行動電話']) ? $rdata['行動電話'] : null,
        'customer_email' => isset($rdata['mail']) ? $rdata['mail'] : null,
        'contact_person' => isset($rdata['聯絡人']) ? $rdata['聯絡人'] : null,
        'city' => isset($rdata['縣市及鄉鎮地區']) ? $rdata['縣市及鄉鎮地區'] : null,
        'case_source' => isset($rdata['案件來源']) ? $rdata['案件來源'] : null,
        'sub_status' => $ragicStatus ?: $progress,
        'site_progress' => isset($rdata['現場進度']) ? $rdata['現場進度'] : null,
        'is_completed' => (isset($rdata['是否已完工']) && strpos($rdata['是否已完工'], '已完工') !== false) ? 1 : 0,
        'completed_date' => ragicDate(isset($rdata['完工日期']) ? $rdata['完工日期'] : null),
        'sales_note' => isset($rdata['業務備註']) ? $rdata['業務備註'] : null,
        'deal_date' => ragicDate(isset($rdata['成交日期']) ? $rdata['成交日期'] : null),
        'company' => isset($rdata['進件公司']) ? $rdata['進件公司'] : null,
        'deal_amount' => isset($rdata['成交金額(未稅)']) && $rdata['成交金額(未稅)'] !== '' ? (float)$rdata['成交金額(未稅)'] : null,
        'tax_included' => (strpos($isTaxStr, '含稅') !== false) ? 1 : 0,
        'is_tax_included' => $isTaxStr ?: null,
        'tax_amount' => isset($rdata['稅金']) && $rdata['稅金'] !== '' ? (float)$rdata['稅金'] : null,
        'total_amount' => isset($rdata['含稅金額']) && $rdata['含稅金額'] !== '' ? (float)$rdata['含稅金額'] : null,
        'deposit_amount' => isset($rdata['訂金金額']) && $rdata['訂金金額'] !== '' ? (float)$rdata['訂金金額'] : null,
        'deposit_date' => ragicDate(isset($rdata['訂金付款日']) ? $rdata['訂金付款日'] : null),
        'deposit_method' => isset($rdata['訂金支付方式']) ? $rdata['訂金支付方式'] : null,
        'balance_amount' => isset($rdata['尾款']) && $rdata['尾款'] !== '' ? (float)$rdata['尾款'] : null,
        'invoice_title' => isset($rdata['發票抬頭']) ? $rdata['發票抬頭'] : null,
        'tax_id_number' => isset($rdata['統一編號']) ? $rdata['統一編號'] : null,
        'billing_title' => isset($rdata['發票抬頭']) ? $rdata['發票抬頭'] : null,
        'billing_tax_id' => isset($rdata['統一編號']) ? $rdata['統一編號'] : null,
        'billing_contact' => isset($rdata['聯絡人']) ? $rdata['聯絡人'] : null,
        'billing_phone' => isset($rdata['家用/公司電話']) ? $rdata['家用/公司電話'] : null,
        'billing_mobile' => isset($rdata['行動電話']) ? $rdata['行動電話'] : null,
        'billing_address' => isset($rdata['發票寄送地址']) ? $rdata['發票寄送地址'] : null,
        'billing_email' => isset($rdata['發票寄送mail']) ? $rdata['發票寄送mail'] : null,
        'quote_amount' => isset($rdata['報價金額']) && $rdata['報價金額'] !== '' ? (float)$rdata['報價金額'] : null,
        'completion_amount' => isset($rdata['完工金額(含稅)']) && $rdata['完工金額(含稅)'] !== '' ? (float)$rdata['完工金額(含稅)'] : null,
        'total_collected' => isset($rdata['總收款金額']) && $rdata['總收款金額'] !== '' ? (float)$rdata['總收款金額'] : null,
        'est_start_date' => ragicDate(isset($rdata['預計施作日期']) ? $rdata['預計施作日期'] : null),
        'est_end_date' => ragicDate(isset($rdata['預計完工日期']) ? $rdata['預計完工日期'] : null),
        'planned_start_date' => ragicDate(isset($rdata['預計施作日期']) ? $rdata['預計施作日期'] : null),
        'planned_end_date' => ragicDate(isset($rdata['預計完工日期']) ? $rdata['預計完工日期'] : null),
        'est_workers' => isset($rdata['預計施工人數']) && $rdata['預計施工人數'] !== '' ? (int)$rdata['預計施工人數'] : null,
        'est_days' => isset($rdata['預計工期']) && $rdata['預計工期'] !== '' ? (int)$rdata['預計工期'] : null,
        'system_type' => isset($rdata['系統別']) ? $rdata['系統別'] : null,
        'registrar' => isset($rdata['登記人員']) ? $rdata['登記人員'] : null,
        'customer_category' => $customerCategory,
        'notes' => isset($rdata['備註']) ? $rdata['備註'] : null,
        'settlement_confirmed' => (isset($rdata['帳款是否已結清']) && $rdata['帳款是否已結清'] === '已結清') ? 1 : 0,
        'settlement_date' => ragicDate(isset($rdata['帳款結清日期']) ? $rdata['帳款結清日期'] : null),
        'settlement_method' => isset($rdata['帳款結清方式']) ? $rdata['帳款結清方式'] : null,
        'repair_report_date' => ragicDate(isset($rdata['維修申告日期']) ? $rdata['維修申告日期'] : null),
        'repair_fault_reason' => isset($rdata['客戶申告故障原因']) ? $rdata['客戶申告故障原因'] : null,
        'repair_by_sales' => (isset($rdata['是否由業務報價']) && strpos($rdata['是否由業務報價'], '業務') !== false) ? 1 : null,
        'repair_equipment' => isset($rdata['維修器材']) ? $rdata['維修器材'] : null,
        'repair_staff' => isset($rdata['維修人員']) ? (is_array($rdata['維修人員']) ? implode('、', $rdata['維修人員']) : $rdata['維修人員']) : null,
        'repair_helper' => isset($rdata['點工人員']) ? (is_array($rdata['點工人員']) ? implode('、', $rdata['點工人員']) : $rdata['點工人員']) : null,
        'repair_result' => isset($rdata['維修結果']) ? $rdata['維修結果'] : null,
        'repair_description' => isset($rdata['維修完成說明']) ? $rdata['維修完成說明'] : null,
        'repair_original_case' => isset($rdata['原案件客戶編號']) ? $rdata['原案件客戶編號'] : null,
        'repair_original_complete_date' => ragicDate(isset($rdata['原案件完工日期']) ? $rdata['原案件完工日期'] : null),
        'repair_original_warranty_date' => ragicDate(isset($rdata['原案件保固日期']) ? $rdata['原案件保固日期'] : null),
        'repair_is_charged' => isset($rdata['有無收費']) ? $rdata['有無收費'] : null,
        'repair_no_charge_reason' => isset($rdata['不收費原因']) ? $rdata['不收費原因'] : null,
    );
}

// ============ Step 1: 取得 Ragic 資料 ============
if ($targetCase) {
    echo '<p class="info">同步單筆案件: ' . htmlspecialchars($targetCase) . '</p>';
    $listData = ragicGet($RAGIC_BASE . '?api&limit=100&where=1000000,eq,' . urlencode($targetCase));
    if (!$listData || empty($listData)) {
        echo '<p class="info">精確搜尋無結果，改用全量比對...</p>';
        flush();
        $listData = ragicGet($RAGIC_BASE . '?api&limit=3000');
        if ($listData) {
            $filtered = array();
            foreach ($listData as $rid => $rdata) {
                if (isset($rdata['進件編號']) && trim($rdata['進件編號']) === $targetCase) {
                    $filtered[$rid] = $rdata;
                }
            }
            $listData = $filtered;
        }
    }
} else {
    if (!$limit || $limit < 100) $limit = 3000;
    echo '<p class="info">同步範圍: ' . ($targetBranch ? htmlspecialchars($targetBranch) : '全部') . '，上限 ' . $limit . ' 筆</p>';
    echo '<p class="info">批次模式：僅同步主表，子表格請用 ?case=編號 逐案同步</p>';
    $listData = ragicGet($RAGIC_BASE . '?api&limit=' . $limit);
}
$isBatchMode = empty($targetCase); // 批次模式不打子表格 API

if (!$listData) {
    echo '<p class="err">Ragic API 連線失敗</p></body></html>';
    exit;
}

echo '<p>Ragic 取得 ' . count($listData) . ' 筆案件</p>';
flush();

$totalNew = 0; $totalUpd = 0; $totalDel = 0;
$totalAtt = 0; $totalPay = 0; $totalWl = 0;
$ragicCaseNumbers = array(); // 記錄所有 Ragic 案件編號（用於刪除比對）
$casesWithFiles = array();   // 記錄有圖片/附件的案件

// ============ Step 2: 逐筆同步 ============
foreach ($listData as $ragicRid => $rdata) {
    $caseNumber = isset($rdata['進件編號']) ? trim($rdata['進件編號']) : '';
    if (!$caseNumber) continue;

    // 分公司篩選
    if ($targetBranch) {
        $rBranch = isset($rdata['所屬分公司']) ? $rdata['所屬分公司'] : '';
        if (strpos($rBranch, $targetBranch) === false) continue;
    }

    $ragicCaseNumbers[] = $caseNumber;

    $customerName = isset($rdata['客戶名稱(現有客戶)']) && $rdata['客戶名稱(現有客戶)']
        ? $rdata['客戶名稱(現有客戶)']
        : (isset($rdata['客戶名稱(新建)']) ? $rdata['客戶名稱(新建)'] : '');

    echo '<details><summary>' . htmlspecialchars($caseNumber) . ' - ' . htmlspecialchars($customerName) . '</summary>';

    $d = parseRagicCase($rdata, $customerName);

    // 檢查系統是否已有此案件
    $chk = $db->prepare("SELECT id FROM cases WHERE case_number = ?");
    $chk->execute(array($caseNumber));
    $existing = $chk->fetch(PDO::FETCH_ASSOC);
    $caseId = null;

    if ($existing) {
        // ---- 更新案件（以 Ragic 為準覆蓋）----
        $caseId = $existing['id'];
        try {
            $upd = $db->prepare("UPDATE cases SET
                branch_id = ?, case_type = ?, status = ?, address = ?, description = ?,
                sales_id = ?, customer_name = ?, customer_phone = ?, customer_mobile = ?, customer_email = ?,
                contact_person = ?, city = ?, case_source = ?, sub_status = ?,
                completed_date = ?, sales_note = ?, deal_date = ?, company = ?,
                deal_amount = ?, tax_included = ?, is_tax_included = ?, tax_amount = ?, total_amount = ?,
                deposit_amount = ?, deposit_date = ?, deposit_method = ?, balance_amount = ?,
                invoice_title = ?, tax_id_number = ?,
                billing_title = ?, billing_tax_id = ?, billing_contact = ?, billing_phone = ?, billing_mobile = ?, billing_address = ?, billing_email = ?,
                quote_amount = ?, completion_amount = ?, total_collected = ?,
                est_start_date = ?, est_end_date = ?, planned_start_date = ?, planned_end_date = ?, est_workers = ?, est_days = ?,
                site_progress = ?, is_completed = ?,
                system_type = ?, registrar = ?, customer_category = ?, notes = ?,
                settlement_confirmed = ?, settlement_date = ?, settlement_method = ?,
                repair_report_date = ?, repair_fault_reason = ?, repair_by_sales = ?, repair_equipment = ?,
                repair_staff = ?, repair_helper = ?, repair_result = ?, repair_description = ?,
                repair_original_case = ?, repair_original_complete_date = ?, repair_original_warranty_date = ?,
                repair_is_charged = ?, repair_no_charge_reason = ?
            WHERE id = ?");
            $upd->execute(array(
                $d['branch_id'], $d['case_type'], $d['status'], $d['address'], $d['description'],
                $d['sales_id'], $d['customer_name'], $d['customer_phone'], $d['customer_mobile'], $d['customer_email'],
                $d['contact_person'], $d['city'], $d['case_source'], $d['sub_status'],
                $d['completed_date'], $d['sales_note'], $d['deal_date'], $d['company'],
                $d['deal_amount'], $d['tax_included'], $d['is_tax_included'], $d['tax_amount'], $d['total_amount'],
                $d['deposit_amount'], $d['deposit_date'], $d['deposit_method'], $d['balance_amount'],
                $d['invoice_title'], $d['tax_id_number'],
                $d['billing_title'], $d['billing_tax_id'], $d['billing_contact'], $d['billing_phone'], $d['billing_mobile'], $d['billing_address'], $d['billing_email'],
                $d['quote_amount'], $d['completion_amount'], $d['total_collected'],
                $d['est_start_date'], $d['est_end_date'], $d['planned_start_date'], $d['planned_end_date'], $d['est_workers'], $d['est_days'],
                $d['site_progress'], $d['is_completed'],
                $d['system_type'], $d['registrar'], $d['customer_category'], $d['notes'],
                $d['settlement_confirmed'], $d['settlement_date'], $d['settlement_method'],
                $d['repair_report_date'], $d['repair_fault_reason'], $d['repair_by_sales'], $d['repair_equipment'],
                $d['repair_staff'], $d['repair_helper'], $d['repair_result'], $d['repair_description'],
                $d['repair_original_case'], $d['repair_original_complete_date'], $d['repair_original_warranty_date'],
                $d['repair_is_charged'], $d['repair_no_charge_reason'],
                $caseId
            ));
            $totalUpd++;
            echo '<p class="ok">已更新 (ID: ' . $caseId . ')</p>';
        } catch (Exception $e) {
            echo '<p class="err">更新失敗: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    } else {
        // ---- 新增案件 ----
        $createdAt = isset($rdata['進件日期']) ? $rdata['進件日期'] : date('Y-m-d');
        try {
            $ins = $db->prepare("INSERT INTO cases (
                branch_id, case_number, title, case_type, status, address, description,
                ragic_id, sales_id, customer_name, customer_phone, customer_mobile, customer_email,
                contact_person, city, case_source, sub_status,
                completed_date, sales_note, deal_date, company,
                deal_amount, tax_included, is_tax_included, tax_amount, total_amount,
                deposit_amount, deposit_date, deposit_method, balance_amount,
                invoice_title, tax_id_number,
                billing_title, billing_tax_id, billing_contact, billing_phone, billing_mobile, billing_address, billing_email,
                quote_amount, completion_amount, total_collected,
                est_start_date, est_end_date, planned_start_date, planned_end_date, est_workers, est_days,
                site_progress, is_completed,
                system_type, registrar, customer_category, notes,
                settlement_confirmed, settlement_date, settlement_method,
                repair_report_date, repair_fault_reason, repair_by_sales, repair_equipment,
                repair_staff, repair_helper, repair_result, repair_description,
                repair_original_case, repair_original_complete_date, repair_original_warranty_date,
                repair_is_charged, repair_no_charge_reason, created_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute(array(
                $d['branch_id'], $caseNumber, mb_substr($customerName ?: $caseNumber, 0, 200),
                $d['case_type'], $d['status'], $d['address'], $d['description'],
                $caseNumber, $d['sales_id'], $d['customer_name'], $d['customer_phone'], $d['customer_mobile'], $d['customer_email'],
                $d['contact_person'], $d['city'], $d['case_source'], $d['sub_status'],
                $d['completed_date'], $d['sales_note'], $d['deal_date'], $d['company'],
                $d['deal_amount'], $d['tax_included'], $d['is_tax_included'], $d['tax_amount'], $d['total_amount'],
                $d['deposit_amount'], $d['deposit_date'], $d['deposit_method'], $d['balance_amount'],
                $d['invoice_title'], $d['tax_id_number'],
                $d['billing_title'], $d['billing_tax_id'], $d['billing_contact'], $d['billing_phone'], $d['billing_mobile'], $d['billing_address'], $d['billing_email'],
                $d['quote_amount'], $d['completion_amount'], $d['total_collected'],
                $d['est_start_date'], $d['est_end_date'], $d['planned_start_date'], $d['planned_end_date'], $d['est_workers'], $d['est_days'],
                $d['site_progress'], $d['is_completed'],
                $d['system_type'], $d['registrar'], $d['customer_category'], $d['notes'],
                $d['settlement_confirmed'], $d['settlement_date'], $d['settlement_method'],
                $d['repair_report_date'], $d['repair_fault_reason'], $d['repair_by_sales'], $d['repair_equipment'],
                $d['repair_staff'], $d['repair_helper'], $d['repair_result'], $d['repair_description'],
                $d['repair_original_case'], $d['repair_original_complete_date'], $d['repair_original_warranty_date'],
                $d['repair_is_charged'], $d['repair_no_charge_reason'],
                $createdAt ?: date('Y-m-d'),
            ));
            $caseId = (int)$db->lastInsertId();
            $totalNew++;
            echo '<p class="ok">新增 (ID: ' . $caseId . ')</p>';
        } catch (Exception $e) {
            echo '<p class="err">新增失敗: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</details>';
            continue;
        }
    }

    // ---- 取子表格（僅單筆模式）----
    if ($isBatchMode) {
        echo '</details>';
        flush();
        continue;
    }
    $detail = ragicGet($RAGIC_BASE . '/' . $ragicRid . '?api&subtables=true');
    if (!$detail || !isset($detail[$ragicRid])) {
        echo '<p class="err">無法取得子表格</p></details>';
        continue;
    }
    $record = $detail[$ragicRid];

    // ---- 同步帳款交易 ----
    $payments = isset($record['_subtable_1007228']) ? $record['_subtable_1007228'] : array();
    $payNew = 0;
    foreach ($payments as $rid => $row) {
        $date = isset($row['帳款交易日期']) ? str_replace('/', '-', $row['帳款交易日期']) : '';
        if (empty($date)) continue;
        $chk2 = $db->prepare("SELECT id FROM case_payments WHERE case_id = ? AND ragic_id = ?");
        $chk2->execute(array($caseId, 'st1007228_' . $rid));
        if ($chk2->fetch()) continue;

        // 記錄有圖片的帳款
        $payImgNote = null;
        if (!empty($row['圖片'])) {
            $imgParts = explode('@', $row['圖片'], 2);
            if (count($imgParts) == 2) {
                $payImgNote = 'ragic_pending:' . $imgParts[0];
                $casesWithFiles[] = array('case' => $caseNumber, 'type' => '帳款圖片', 'file' => $imgParts[1], 'key' => $imgParts[0]);
            }
        }

        try {
            $db->prepare("INSERT INTO case_payments (case_id, payment_date, payment_type, transaction_type, amount, note, ragic_id, created_by) VALUES (?,?,?,?,?,?,?,?)")
               ->execute(array($caseId, $date, isset($row['帳款類別']) ? $row['帳款類別'] : null, isset($row['交易內容']) ? $row['交易內容'] : null, (int)(isset($row['金額']) ? $row['金額'] : 0), isset($row['備註']) ? $row['備註'] : null, 'st1007228_' . $rid, Auth::id()));
            $payNew++; $totalPay++;
        } catch (Exception $e) {
            echo '<p class="err">帳款寫入失敗: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
    if ($payNew) echo '<p class="ok">帳款: +' . $payNew . '</p>';

    // ---- 同步附件（只記錄，不下載）----
    $attachments = isset($record['_subtable_1004158']) ? $record['_subtable_1004158'] : array();
    $attNew = 0;
    foreach ($attachments as $rid => $row) {
        foreach ($fileTypeMap as $ragicField => $hsType) {
            $fileInfo = isset($row[$ragicField]) ? $row[$ragicField] : '';
            if (empty($fileInfo) || $fileInfo === '-') continue;
            $parts = explode('@', $fileInfo, 2);
            if (count($parts) != 2) continue;
            $fileName = $parts[1];

            $chk2 = $db->prepare("SELECT id FROM case_attachments WHERE case_id = ? AND file_name = ?");
            $chk2->execute(array($caseId, $fileName));
            if ($chk2->fetch()) continue;

            try {
                $db->prepare("INSERT INTO case_attachments (case_id, file_type, file_name, file_path, file_size, uploaded_by, note) VALUES (?,?,?,?,?,?,?)")
                   ->execute(array($caseId, $hsType, $fileName, '', 0, Auth::id(), 'ragic_pending:' . $parts[0]));
                $attNew++; $totalAtt++;
                $casesWithFiles[] = array('case' => $caseNumber, 'type' => $ragicField, 'file' => $fileName, 'key' => $parts[0]);
            } catch (Exception $e) {
                echo '<p class="err">附件寫入失敗: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        }
    }
    if ($attNew) echo '<p class="ok">附件: +' . $attNew . ' (待下載)</p>';

    // ---- 同步施工紀錄 ----
    $worklogs = isset($record['_subtable_1007229']) ? $record['_subtable_1007229'] : array();
    $wlNew = 0;
    foreach ($worklogs as $rid => $row) {
        $content = isset($row['施工內容']) ? $row['施工內容'] : '';
        if (empty($content)) continue;
        $chk2 = $db->prepare("SELECT id FROM case_work_logs WHERE case_id = ? AND ragic_id = ?");
        $chk2->execute(array($caseId, 'st1007229_' . $rid));
        if ($chk2->fetch()) continue;

        $workDate = !empty($row['施工日期']) ? str_replace('/', '-', $row['施工日期']) : null;
        try {
            $db->prepare("INSERT INTO case_work_logs (case_id, work_date, work_content, equipment_used, cable_used, ragic_id, created_by) VALUES (?,?,?,?,?,?,?)")
               ->execute(array($caseId, $workDate, $content, isset($row['使用器材']) ? $row['使用器材'] : null, isset($row['使用線材']) ? $row['使用線材'] : null, 'st1007229_' . $rid, Auth::id()));
            $wlNew++; $totalWl++;
        } catch (Exception $e) {
            echo '<p class="err">施工紀錄寫入失敗: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
    if ($wlNew) echo '<p class="ok">施工紀錄: +' . $wlNew . '</p>';

    if (!$payNew && !$attNew && !$wlNew && $existing) echo '<p class="skip">子表格無新資料</p>';
    echo '</details>';
    flush();
}

// ============ Step 3: 刪除 Ragic 不存在的案件 ============
if (!$targetCase && !$targetBranch && count($ragicCaseNumbers) > 100) {
    // 只有全量同步時才執行刪除
    echo '<h3>檢查需刪除的案件...</h3>';
    $placeholders = implode(',', array_fill(0, count($ragicCaseNumbers), '?'));
    $delStmt = $db->prepare("SELECT id, case_number, title FROM cases WHERE case_number NOT IN ($placeholders)");
    $delStmt->execute($ragicCaseNumbers);
    $toDelete = $delStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($toDelete)) {
        echo '<p class="ok">無需刪除的案件</p>';
    } else {
        echo '<p class="err">找到 ' . count($toDelete) . ' 筆需刪除（Ragic 已無此案件）：</p><ul>';
        foreach ($toDelete as $del) {
            echo '<li>' . htmlspecialchars($del['case_number']) . ' - ' . htmlspecialchars($del['title']) . ' (ID: ' . $del['id'] . ')</li>';
            // 刪除子表格
            $db->prepare("DELETE FROM case_payments WHERE case_id = ?")->execute(array($del['id']));
            $db->prepare("DELETE FROM case_attachments WHERE case_id = ?")->execute(array($del['id']));
            $db->prepare("DELETE FROM case_work_logs WHERE case_id = ?")->execute(array($del['id']));
            // 刪除主表
            $db->prepare("DELETE FROM cases WHERE id = ?")->execute(array($del['id']));
            $totalDel++;
        }
        echo '</ul>';
    }
}

// ============ 結果摘要 ============
echo '<hr><h3>同步完成</h3><ul>';
echo '<li>新增案件: ' . $totalNew . ' 筆</li>';
echo '<li>更新案件: ' . $totalUpd . ' 筆</li>';
echo '<li>刪除案件: ' . $totalDel . ' 筆</li>';
echo '<li>新增帳款: ' . $totalPay . ' 筆</li>';
echo '<li>新增附件: ' . $totalAtt . ' 個（待下載）</li>';
echo '<li>新增施工紀錄: ' . $totalWl . ' 筆</li>';
echo '</ul>';

// ============ 有圖片/附件的案件清單 ============
if (!empty($casesWithFiles)) {
    echo '<h3>待下載圖片/附件 (' . count($casesWithFiles) . ' 個)</h3>';
    echo '<table><tr><th>案件編號</th><th>類型</th><th>檔名</th><th>fileKey</th></tr>';
    foreach ($casesWithFiles as $f) {
        echo '<tr><td>' . htmlspecialchars($f['case']) . '</td><td>' . htmlspecialchars($f['type']) . '</td><td>' . htmlspecialchars($f['file']) . '</td><td>' . htmlspecialchars($f['key']) . '</td></tr>';
    }
    echo '</table>';
}

// 統計已有 pending 附件的案件總數
$pendingCount = $db->query("SELECT COUNT(*) FROM case_attachments WHERE note LIKE 'ragic_pending:%'")->fetchColumn();
$pendingCases = $db->query("SELECT COUNT(DISTINCT case_id) FROM case_attachments WHERE note LIKE 'ragic_pending:%'")->fetchColumn();
echo '<p class="info">系統中共有 ' . $pendingCount . ' 個待下載附件，分布在 ' . $pendingCases . ' 個案件中</p>';

echo '<p><a href="/cases.php">返回案件管理</a></p></body></html>';
