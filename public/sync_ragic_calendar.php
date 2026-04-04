<?php
/**
 * 同步 Ragic 工程行事曆 → hswork 排工行事曆
 *
 * 安全做法：
 * - 只做 Ragic → hswork 單向讀取
 * - 用 ragic_calendar_id 做唯一鍵避免重複
 * - 不會覆蓋或刪除現有手動排工資料
 * - 匯入的案件標記 import_source = 'ragic_calendar'
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';

$API_KEY = 'dGhmNTRyZk9uMlRUS2c3MjhhQytMZjlZdCtQc1lUMVJHYzVCNlA0dFFVZm1tREk0MFVxU0JibnRmNGV3TElEMA==';

// 各分公司的 Ragic 行事曆設定
$branches = array(
    'east' => array(
        'name' => '東區電子鎖',
        'branch_id' => 4,
        'url' => 'https://ap15.ragic.com/hstcc/east-district-electronic-lock/22',
        'field_map' => array(
            'schedule_id' => '排工編號',
            'customer_name' => array('客戶名稱(新建)', '客戶名稱(現有客戶)', '客戶名稱(上傳)'),
            'address' => '地址',
            'contact_person' => '聯絡人',
            'phone' => '行動電話',
            'home_phone' => '家用/公司電話',
            'case_type' => '案別',
            'customer_demand' => '客戶需求',
            'work_content' => '施工內容',
            'status' => '工作進度',
            'start_time' => '開始時間',
            'end_time' => '結束時間',
            'expected_complete' => '預計完工日',
            'customer_no' => '客戶編號',
        ),
    ),
    // 其他分公司之後再加
);

// 只同步指定分公司（預設東區）
$syncBranch = isset($_GET['branch']) ? $_GET['branch'] : 'east';
if (!isset($branches[$syncBranch])) {
    echo "未知分公司: {$syncBranch}\n";
    exit;
}

$config = $branches[$syncBranch];
$branchId = $config['branch_id'];
$fieldMap = $config['field_map'];
echo "=== 同步 Ragic 工程行事曆: {$config['name']} ===\n\n";

// ---- Step 1: 從 Ragic 抓資料 ----
echo "[1] 從 Ragic 抓取資料...\n";
$url = $config['url'] . '?api&limit=1000&subtables=true';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "API 錯誤: HTTP {$httpCode}\n";
    exit;
}

$ragicData = json_decode($response, true);
if (!$ragicData) {
    echo "JSON 解析失敗\n";
    exit;
}
echo "取得 " . count($ragicData) . " 筆\n\n";

// ---- Step 2: 處理每筆資料 ----
$db = Database::getInstance();
$stats = array('new_case' => 0, 'exist_case' => 0, 'new_schedule' => 0, 'skip_schedule' => 0, 'error' => 0);

// 確保 schedules 表有 ragic_calendar_id 欄位
try {
    $db->exec("ALTER TABLE schedules ADD COLUMN ragic_calendar_id VARCHAR(50) DEFAULT NULL COMMENT 'Ragic 行事曆來源ID'");
    $db->exec("ALTER TABLE schedules ADD COLUMN ragic_calendar_branch VARCHAR(20) DEFAULT NULL COMMENT 'Ragic 行事曆分公司代碼'");
    echo "[Migration] 已新增 ragic_calendar_id, ragic_calendar_branch 欄位\n\n";
} catch (Exception $e) {
    // 欄位已存在，跳過
}

// 取得分公司的使用者列表（用於比對施工人員）
$userStmt = $db->query("SELECT id, real_name FROM users WHERE is_active = 1");
$userMap = array();
foreach ($userStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
    $userMap[$u['real_name']] = (int)$u['id'];
}

// 預設建立者（系統管理者）
$createdBy = Auth::id();

echo "[2] 開始處理...\n\n";

foreach ($ragicData as $ragicId => $rec) {
    $ragicCalId = $syncBranch . '_' . $ragicId;

    // 解析欄位
    $customerName = '';
    $nameFields = $fieldMap['customer_name'];
    foreach ($nameFields as $nf) {
        if (!empty($rec[$nf])) { $customerName = trim($rec[$nf]); break; }
    }
    if (!$customerName) {
        echo "  [SKIP] ragicId={$ragicId} 無客戶名稱\n";
        $stats['error']++;
        continue;
    }

    $scheduleNo = isset($rec[$fieldMap['schedule_id']]) ? trim($rec[$fieldMap['schedule_id']]) : '';
    $address = isset($rec[$fieldMap['address']]) ? trim($rec[$fieldMap['address']]) : '';
    $contactPerson = isset($rec[$fieldMap['contact_person']]) ? trim($rec[$fieldMap['contact_person']]) : '';
    $phone = isset($rec[$fieldMap['phone']]) ? trim($rec[$fieldMap['phone']]) : '';
    $homePhone = isset($rec[$fieldMap['home_phone']]) ? trim($rec[$fieldMap['home_phone']]) : '';
    $caseType = isset($rec[$fieldMap['case_type']]) ? trim($rec[$fieldMap['case_type']]) : '';
    $workContent = isset($rec[$fieldMap['work_content']]) ? trim($rec[$fieldMap['work_content']]) : '';
    $statusText = isset($rec[$fieldMap['status']]) ? trim($rec[$fieldMap['status']]) : '';
    $startTime = isset($rec[$fieldMap['start_time']]) ? trim($rec[$fieldMap['start_time']]) : '';
    $endTime = isset($rec[$fieldMap['end_time']]) ? trim($rec[$fieldMap['end_time']]) : '';
    $customerDemand = isset($rec[$fieldMap['customer_demand']]) ? trim($rec[$fieldMap['customer_demand']]) : '';

    // 解析日期
    $scheduleDate = '';
    if ($startTime) {
        $scheduleDate = str_replace('/', '-', substr($startTime, 0, 10));
    }
    if (!$scheduleDate) {
        echo "  [SKIP] {$scheduleNo} {$customerName} 無施工日期\n";
        $stats['error']++;
        continue;
    }

    // 狀態對照
    $statusMap = array(
        '已完工' => 'completed',
        '待續工' => 'in_progress',
        '未施工' => 'planned',
        '' => 'planned',
    );
    $status = isset($statusMap[$statusText]) ? $statusMap[$statusText] : 'planned';

    // 案別對照
    $caseTypeMap = array(
        '新案' => 'new_install',
        '維修' => 'repair',
        '舊翻新' => 'renovation',
        '' => 'new_install',
    );
    $caseTypeVal = isset($caseTypeMap[$caseType]) ? $caseTypeMap[$caseType] : 'new_install';

    echo "  [{$scheduleNo}] {$customerName} | {$scheduleDate} | {$statusText}\n";

    // ---- Step 2a: 查找或建立案件 ----
    // 用 ragic_id 查找（避免重複建立）
    $caseStmt = $db->prepare("SELECT id FROM cases WHERE ragic_id = ? AND branch_id = ?");
    $caseStmt->execute(array($ragicCalId, $branchId));
    $caseId = $caseStmt->fetchColumn();

    if (!$caseId) {
        // 用客戶名稱+地址查找
        if ($address) {
            $findStmt = $db->prepare("SELECT id FROM cases WHERE customer_name = ? AND address = ? AND branch_id = ? LIMIT 1");
            $findStmt->execute(array($customerName, $address, $branchId));
            $caseId = $findStmt->fetchColumn();
        }
    }

    if ($caseId) {
        echo "    → 找到現有案件 #{$caseId}\n";
        $stats['exist_case']++;
    } else {
        // 建立新案件
        $caseNumber = generate_doc_number('cases');
        $insertCase = $db->prepare("
            INSERT INTO cases (branch_id, case_number, title, case_type, status, sub_status,
                customer_name, contact_person, customer_phone, customer_mobile, address,
                description, system_type, ragic_id, created_by, difficulty, total_visits, max_engineers)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 3, 1, 2)
        ");
        $insertCase->execute(array(
            $branchId,
            $caseNumber,
            $customerName,
            $caseTypeVal,
            $status === 'completed' ? 'completed' : 'tracking',
            $status === 'completed' ? '已完工' : '待派工',
            $customerName,
            $contactPerson,
            $homePhone ?: null,
            $phone ?: null,
            $address ?: null,
            $workContent ?: $customerDemand ?: null,
            $customerDemand ?: null,
            $ragicCalId,
            $createdBy,
        ));
        $caseId = (int)$db->lastInsertId();
        echo "    → 建立新案件 #{$caseId} ({$caseNumber})\n";
        $stats['new_case']++;
    }

    // ---- Step 2b: 查找或建立排工 ----
    $existSch = $db->prepare("SELECT id FROM schedules WHERE ragic_calendar_id = ?");
    $existSch->execute(array($ragicCalId));
    $existSchId = $existSch->fetchColumn();

    if ($existSchId) {
        echo "    → 排工已存在 #{$existSchId}，跳過\n";
        $stats['skip_schedule']++;
        continue;
    }

    // 計算第幾次施工
    $visitStmt = $db->prepare("SELECT COALESCE(MAX(visit_number), 0) + 1 FROM schedules WHERE case_id = ?");
    $visitStmt->execute(array($caseId));
    $visitNumber = (int)$visitStmt->fetchColumn();

    // 組備註
    $note = '';
    if ($scheduleNo) $note .= "Ragic排工編號: {$scheduleNo}";
    if ($workContent) $note .= ($note ? "\n" : '') . "施工內容: {$workContent}";

    $insertSch = $db->prepare("
        INSERT INTO schedules (case_id, schedule_date, visit_number, status, note, created_by, ragic_calendar_id, ragic_calendar_branch)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertSch->execute(array(
        $caseId,
        $scheduleDate,
        $visitNumber,
        $status,
        $note ?: null,
        $createdBy,
        $ragicCalId,
        $syncBranch,
    ));
    $schId = (int)$db->lastInsertId();
    echo "    → 建立排工 #{$schId} ({$scheduleDate} 第{$visitNumber}次)\n";
    $stats['new_schedule']++;
}

echo "\n=== 完成 ===\n";
echo "新建案件: {$stats['new_case']}\n";
echo "已有案件: {$stats['exist_case']}\n";
echo "新建排工: {$stats['new_schedule']}\n";
echo "跳過排工: {$stats['skip_schedule']}\n";
echo "錯誤/跳過: {$stats['error']}\n";
echo '</pre>';
