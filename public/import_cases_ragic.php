<?php
/**
 * Ragic 案件匯入腳本
 * 步驟：先清空案件相關資料 → 讀取 JSON → 匯入
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Auth::user()['role'], array('boss','manager'))) {
    die('需要管理員權限');
}

set_time_limit(300);
ini_set('memory_limit', '256M');

$db = Database::getInstance();
$step = isset($_GET['step']) ? $_GET['step'] : 'confirm';

// ============ Step 1: 確認頁面 ============
if ($step === 'confirm') {
    $caseCount = $db->query("SELECT COUNT(*) FROM cases")->fetchColumn();
    echo '<h1>Ragic 案件匯入</h1>';
    echo '<div style="background:#fff3cd;padding:15px;border-radius:8px;margin:10px 0">';
    echo '<b>警告：</b>此操作會清空現有 <b>' . $caseCount . '</b> 筆案件及所有關聯資料（排工、施工回報等），然後從 Ragic 匯入。';
    echo '</div>';
    echo '<p><a href="?step=clear" style="background:#ea4335;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none" onclick="return confirm(\'確定要清空並匯入？此操作無法復原！\')">開始清空並匯入</a></p>';
    echo '<p><a href="/cases.php">取消，返回案件管理</a></p>';
    exit;
}

// ============ Step 2: 清空 + 匯入 ============
if ($step === 'clear') {
    echo '<h1>正在處理...</h1><pre>';

    // 取得分公司對照
    $branchMap = array();
    $branches = $db->query("SELECT id, name FROM branches")->fetchAll();
    foreach ($branches as $b) {
        $branchMap[$b['name']] = $b['id'];
    }
    // 先確認中區專案部存在
    $hasBranch = $db->query("SELECT COUNT(*) FROM branches WHERE name = '中區專案部'")->fetchColumn();
    if (!$hasBranch) {
        $db->exec("INSERT INTO branches (name, code, is_active) VALUES ('中區專案部', 'ZHONGQU', 1)");
        echo "新增分公司：中區專案部\n";
        // 重新取得
        $branches = $db->query("SELECT id, name FROM branches")->fetchAll();
        $branchMap = array();
        foreach ($branches as $b) {
            $branchMap[$b['name']] = $b['id'];
        }
    }

    // 別名對應
    $branchAlias = array(
        '潭子分公司' => '潭子分公司',
        '員林分公司' => '員林分公司',
        '清水分公司' => '清水分公司',
        '東區電子鎖' => '東區電子鎖專賣店',
        '清水電子鎖' => '清水電子鎖專賣店',
        '中區專案部' => '中區專案部',
    );

    // 取得業務人員對照
    $userMap = array();
    $users = $db->query("SELECT id, real_name FROM users")->fetchAll();
    foreach ($users as $u) {
        $userMap[$u['real_name']] = $u['id'];
    }

    // 清空關聯表（順序很重要）
    echo "清空關聯資料...\n";
    $tables = array(
        'case_payments', 'case_work_logs', 'material_usage', 'worklog_photos', 'work_logs',
        'schedule_engineers', 'schedules',
        'case_required_skills', 'case_site_conditions', 'case_contacts', 'case_readiness',
        'cases'
    );
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables as $t) {
        try {
            $db->exec("TRUNCATE TABLE `$t`");
            echo "  TRUNCATE $t OK\n";
        } catch (Exception $e) {
            try {
                $db->exec("DELETE FROM `$t`");
                echo "  DELETE $t OK\n";
            } catch (Exception $e2) {
                echo "  SKIP $t: " . $e2->getMessage() . "\n";
            }
        }
    }
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "\n";

    // 讀取 JSON
    $jsonFile = __DIR__ . '/../ragic_cases.json';
    if (!file_exists($jsonFile)) {
        die('ERROR: ragic_cases.json 不存在，請先上傳');
    }
    $data = json_decode(file_get_contents($jsonFile), true);
    echo "讀取 " . count($data) . " 筆案件資料\n\n";

    // 案別對應
    $caseTypeMap = array(
        '新案' => 'new_install',
        '老客戶追加' => 'new_install',
        '舊客戶維修案' => 'repair',
        '新客戶維修案' => 'repair',
    );

    // 狀態對應
    $statusMap = array(
        '已成交' => 'in_progress',
        '未指派' => 'pending',
        '待聯絡' => 'pending',
        '已聯絡安排場勘' => 'pending',
        '已聯絡待場勘' => 'pending',
        '待場勘' => 'pending',
        '已會勘未報價' => 'pending',
        '已報價待追蹤' => 'pending',
        '現簽' => 'in_progress',
        '已聯絡電話報價' => 'pending',
        '已報價無意願' => 'cancelled',
        '跨月成交' => 'in_progress',
        '電話報價成交' => 'in_progress',
        '電話不通或未接' => 'pending',
        '規劃或預算案' => 'pending',
        '無效' => 'cancelled',
        '客戶毀約' => 'cancelled',
    );

    // 進度對應 sub_status
    $progressMap = array(
        '未完工' => 'in_progress',
        '待追蹤' => 'pending',
        '待安排派工查修' => 'ready',
        '完工未收款' => 'completed',
        '已完工結案' => 'completed',
        '已完工未收款' => 'completed',
        '客戶取消' => 'cancelled',
        '未成交' => 'cancelled',
        '保養案件' => 'in_progress',
        '毀約' => 'cancelled',
    );

    $imported = 0;
    $errors = 0;

    $stmt = $db->prepare("INSERT INTO cases (
        branch_id, case_number, title, case_type, status, address, description,
        ragic_id, sales_id, customer_name, customer_phone, customer_mobile,
        contact_person, city, case_source, sub_status, site_progress,
        completed_date, sales_note, lost_reason, deal_date, company,
        deal_amount, tax_included, tax_amount, total_amount,
        deposit_amount, deposit_date, deposit_method, balance_amount,
        invoice_title, tax_id_number, quote_amount, completion_amount, total_collected,
        est_start_date, est_end_date, est_workers, est_days,
        notes, created_at
    ) VALUES (
        ?,?,?,?,?,?,?,
        ?,?,?,?,?,
        ?,?,?,?,?,
        ?,?,?,?,?,
        ?,?,?,?,
        ?,?,?,?,
        ?,?,?,?,?,
        ?,?,?,?,
        ?,?
    )");

    foreach ($data as $i => $r) {
        try {
            // 分公司
            $branchName = isset($r['所屬分公司']) ? $r['所屬分公司'] : '';
            if (isset($branchAlias[$branchName])) {
                $branchName = $branchAlias[$branchName];
            }
            $branchId = isset($branchMap[$branchName]) ? $branchMap[$branchName] : 1;

            // 案件編號
            $caseNumber = isset($r['進件編號']) ? $r['進件編號'] : '';
            if (!$caseNumber) continue;

            // 標題：客戶名稱 + 系統別
            $customerName = isset($r['客戶名稱(現有客戶)']) && $r['客戶名稱(現有客戶)']
                ? $r['客戶名稱(現有客戶)']
                : (isset($r['客戶名稱(新建)']) ? $r['客戶名稱(新建)'] : '');
            $title = $customerName;
            if (!$title) $title = $caseNumber;

            // 類型
            $caseType = isset($caseTypeMap[$r['案別']]) ? $caseTypeMap[$r['案別']] : 'new_install';

            // 狀態：先看案件進度，再看狀態
            $progress = isset($r['案件進度']) ? $r['案件進度'] : '';
            $ragicStatus = isset($r['狀態']) ? $r['狀態'] : '';
            if (isset($progressMap[$progress])) {
                $status = $progressMap[$progress];
            } elseif (isset($statusMap[$ragicStatus])) {
                $status = $statusMap[$ragicStatus];
            } else {
                $status = 'tracking';
            }

            // 業務
            $salesName = isset($r['承辦業務']) ? $r['承辦業務'] : '';
            $salesId = isset($userMap[$salesName]) ? $userMap[$salesName] : null;

            // 金額
            $dealAmount = isset($r['成交金額(未稅)']) ? (float)$r['成交金額(未稅)'] : null;
            $taxAmount = isset($r['稅金']) ? (float)$r['稅金'] : null;
            $totalAmount = isset($r['含稅金額']) ? (float)$r['含稅金額'] : null;
            $quoteAmount = isset($r['報價金額']) ? (float)$r['報價金額'] : null;
            $depositAmount = isset($r['訂金金額']) ? (float)$r['訂金金額'] : null;
            $balanceAmount = isset($r['尾款']) ? (float)$r['尾款'] : null;
            $completionAmount = isset($r['完工金額(含稅)']) ? (float)$r['完工金額(含稅)'] : null;
            $totalCollected = isset($r['總收款金額']) ? (float)$r['總收款金額'] : null;

            // 是否含稅
            $taxStr = isset($r['是否含稅']) ? $r['是否含稅'] : '';
            $taxIncluded = (strpos($taxStr, '含稅') !== false) ? 1 : 0;

            // 日期
            $dealDate = isset($r['成交日期']) ? $r['成交日期'] : null;
            $completedDate = isset($r['完工日期']) ? $r['完工日期'] : null;
            $depositDate = isset($r['訂金付款日']) ? $r['訂金付款日'] : null;
            $estStartDate = isset($r['預計施作日期']) ? $r['預計施作日期'] : null;
            $estEndDate = isset($r['預計完工日期']) ? $r['預計完工日期'] : null;
            $createdAt = isset($r['進件日期']) ? $r['進件日期'] : date('Y-m-d');

            // 其他
            $estWorkers = isset($r['預計施工人數']) ? (int)$r['預計施工人數'] : null;
            $estDays = isset($r['預計工期']) ? (int)$r['預計工期'] : null;

            $stmt->execute(array(
                $branchId,
                $caseNumber,
                mb_substr($title, 0, 200),
                $caseType,
                $status,
                isset($r['地址']) ? $r['地址'] : null,
                isset($r['客戶需求']) ? $r['客戶需求'] : null,
                $caseNumber, // ragic_id
                $salesId,
                $customerName ?: null,
                isset($r['家用/公司電話']) ? $r['家用/公司電話'] : null,
                isset($r['行動電話']) ? $r['行動電話'] : null,
                isset($r['聯絡人']) ? $r['聯絡人'] : null,
                isset($r['縣市及鄉鎮地區']) ? $r['縣市及鄉鎮地區'] : null,
                isset($r['案件來源']) ? $r['案件來源'] : null,
                $ragicStatus ?: $progress,
                isset($r['現場進度']) ? $r['現場進度'] : null,
                $completedDate,
                isset($r['業務備註']) ? $r['業務備註'] : null,
                isset($r['無法成交原因']) ? $r['無法成交原因'] : null,
                $dealDate,
                isset($r['進件公司']) ? $r['進件公司'] : null,
                $dealAmount,
                $taxIncluded,
                $taxAmount,
                $totalAmount,
                $depositAmount,
                $depositDate,
                isset($r['訂金支付方式']) ? $r['訂金支付方式'] : null,
                $balanceAmount,
                isset($r['發票抬頭']) ? $r['發票抬頭'] : null,
                isset($r['統一編號']) ? $r['統一編號'] : null,
                $quoteAmount,
                $completionAmount,
                $totalCollected,
                $estStartDate,
                $estEndDate,
                $estWorkers ?: null,
                $estDays ?: null,
                isset($r['備註']) ? $r['備註'] : null,
                $createdAt ?: date('Y-m-d'),
            ));

            $imported++;
            if ($imported % 100 === 0) {
                echo "已匯入 $imported 筆...\n";
                flush();
            }

        } catch (Exception $e) {
            $errors++;
            if ($errors <= 10) {
                echo "ERROR [{$r['進件編號']}]: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n=============================\n";
    echo "匯入完成！\n";
    echo "成功：$imported 筆\n";
    echo "失敗：$errors 筆\n";
    echo '</pre>';
    echo '<p><a href="/cases.php" style="background:#1a73e8;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none">前往案件管理</a></p>';
}
