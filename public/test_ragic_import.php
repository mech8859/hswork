<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

$API_KEY = 'dGhmNTRyZk9uMlRUS2c3MjhhQytMZjlZdCtQc1lUMVJHYzVCNlA0dFFVZm1tREk0MFVxU0JibnRmNGV3TElEMA==';

echo '<h2>Ragic 子表格匯入測試 - 2026-1561</h2>';

// 1. 從 Ragic API 取得資料
$url = 'https://ap15.ragic.com/hstcc/new-case-registration/16/2415?api&subtables=true';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (!$data || empty($data['2415'])) {
    echo '<p style="color:red">✗ 無法取得 Ragic 資料</p>';
    exit;
}

$record = $data['2415'];
echo '<p style="color:green">✓ 成功取得 Ragic 資料: ' . htmlspecialchars($record['進件編號']) . ' | ' . htmlspecialchars($record['客戶名稱(現有客戶)']) . '</p>';

// 2. 找到對應的 hswork 案件
$caseNumber = $record['進件編號']; // 2026-1561
$stmt = $db->prepare("SELECT id, case_number, title FROM cases WHERE case_number = ? OR case_number LIKE ?");
$stmt->execute(array($caseNumber, '%' . $caseNumber . '%'));
$case = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$case) {
    // 嘗試用 ragic_id 找
    echo '<p style="color:orange">⚠ 找不到案件 ' . htmlspecialchars($caseNumber) . '，嘗試用進件編號搜尋...</p>';
    $stmt = $db->prepare("SELECT id, case_number, title FROM cases WHERE case_number LIKE ?");
    $stmt->execute(array('%1561%'));
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$case) {
    echo '<p style="color:red">✗ 系統中找不到此案件，無法匯入子表格</p>';
    echo '<p>建議：先確認案件資料已匯入 hswork</p>';
    exit;
}

echo '<p style="color:green">✓ 對應到案件: ID=' . $case['id'] . ' | ' . htmlspecialchars($case['case_number']) . ' | ' . htmlspecialchars($case['title']) . '</p>';
$caseId = $case['id'];

// ========== 3. 匯入帳款交易 ==========
echo '<h3>1. 帳款交易 (_subtable_1007228)</h3>';
$payments = isset($record['_subtable_1007228']) ? $record['_subtable_1007228'] : array();
$payCount = 0;
foreach ($payments as $rid => $row) {
    $date = $row['帳款交易日期'] ?? '';
    if (empty($date)) continue;
    
    // 轉日期格式
    $date = str_replace('/', '-', $date);
    
    // 檢查是否已匯入
    $chk = $db->prepare("SELECT id FROM case_payments WHERE case_id = ? AND ragic_id = ?");
    $chk->execute(array($caseId, 'st1007228_' . $rid));
    if ($chk->fetch()) {
        echo '<p style="color:orange">⚠ 已存在，跳過: ' . htmlspecialchars($row['帳款交易日期'] . ' | ' . ($row['交易內容'] ?? '') . ' | $' . ($row['金額'] ?? 0)) . '</p>';
        continue;
    }
    
    // 下載圖片
    $imagePath = null;
    if (!empty($row['圖片'])) {
        $imgInfo = $row['圖片']; // 格式: "1BQMyyOPdo@S__27795475.jpg"
        $parts = explode('@', $imgInfo, 2);
        if (count($parts) == 2) {
            $imgUrl = 'https://ap15.ragic.com/sims/file.jsp?a=hstcc&f=' . urlencode($parts[0]);
            $imgData = file_get_contents($imgUrl);
            if ($imgData) {
                $imgDir = __DIR__ . '/uploads/case_payments/' . $caseId;
                if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);
                $imgName = $parts[1];
                file_put_contents($imgDir . '/' . $imgName, $imgData);
                $imagePath = 'uploads/case_payments/' . $caseId . '/' . $imgName;
                echo '<p style="color:green">  ✓ 圖片下載: ' . htmlspecialchars($imgName) . '</p>';
            }
        }
    }
    
    $stmt = $db->prepare("INSERT INTO case_payments (case_id, payment_date, payment_type, transaction_type, amount, note, image_path, ragic_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(array(
        $caseId,
        $date,
        $row['帳款類別'] ?? null,
        $row['交易內容'] ?? null,
        (int)($row['金額'] ?? 0),
        $row['備註'] ?? null,
        $imagePath,
        'st1007228_' . $rid,
        Auth::id()
    ));
    $payCount++;
    echo '<p style="color:green">✓ 帳款: ' . htmlspecialchars($row['帳款交易日期'] . ' | ' . ($row['交易內容'] ?? '-') . ' | $' . number_format((int)($row['金額'] ?? 0))) . '</p>';
}
echo '<p><strong>匯入 ' . $payCount . ' 筆帳款交易</strong></p>';

// ========== 4. 匯入報價單/附件 ==========
echo '<h3>2. 報價單/附件 (_subtable_1004158)</h3>';
$attachments = isset($record['_subtable_1004158']) ? $record['_subtable_1004158'] : array();
$attCount = 0;
$fileTypeMap = array(
    '報價單' => 'quotation',
    '報價單(檔案)' => 'quotation',
    '圖面' => 'blueprint',
    '保固書' => 'warranty',
    '檔案' => 'other',
    '檔案2' => 'other',
    '相片' => 'site_photo',
);

foreach ($attachments as $rid => $row) {
    foreach ($fileTypeMap as $ragicField => $hsType) {
        $fileInfo = $row[$ragicField] ?? '';
        if (empty($fileInfo) || $fileInfo === '-') continue;
        
        // 格式: "kvhR0PCXJO@林東慶先生 報價單-網路系統 conv 1.jpeg"
        $parts = explode('@', $fileInfo, 2);
        if (count($parts) != 2) continue;
        
        $fileKey = $parts[0];
        $fileName = $parts[1];
        
        // 檢查是否已匯入
        $chk = $db->prepare("SELECT id FROM case_attachments WHERE case_id = ? AND file_name = ?");
        $chk->execute(array($caseId, $fileName));
        if ($chk->fetch()) {
            echo '<p style="color:orange">⚠ 已存在: ' . htmlspecialchars($fileName) . '</p>';
            continue;
        }
        
        // 下載檔案（用 curl 帶 auth）
        $fileUrl = 'https://ap15.ragic.com/sims/file.jsp?a=hstcc&f=' . urlencode($fileKey);
        $ch2 = curl_init($fileUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
        $fileData = curl_exec($ch2);
        curl_close($ch2);

        if ($fileData && strlen($fileData) > 100) {
            $attDir = __DIR__ . '/uploads/cases/' . $caseId;
            if (!is_dir($attDir)) mkdir($attDir, 0755, true);
            $safeName = date('Ymd_His') . '_' . mt_rand(1000,9999) . '_' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $fileName);
            file_put_contents($attDir . '/' . $safeName, $fileData);
            
            $relPath = 'uploads/cases/' . $caseId . '/' . $safeName;
            $stmt = $db->prepare("INSERT INTO case_attachments (case_id, file_type, file_name, file_path, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(array($caseId, $hsType, $fileName, $relPath, strlen($fileData), Auth::id()));
            $attCount++;
            echo '<p style="color:green">✓ 附件 [' . $hsType . ']: ' . htmlspecialchars($fileName) . ' (' . round(strlen($fileData)/1024) . 'KB)</p>';
        } else {
            echo '<p style="color:orange">⚠ 下載失敗: ' . htmlspecialchars($fileName) . '</p>';
        }
    }
}
echo '<p><strong>匯入 ' . $attCount . ' 個附件</strong></p>';

// ========== 5. 匯入施工紀錄 ==========
echo '<h3>3. 施工紀錄 (_subtable_1007229)</h3>';
$worklogs = isset($record['_subtable_1007229']) ? $record['_subtable_1007229'] : array();

// 先建 case_work_logs 表
try {
    $db->exec("CREATE TABLE IF NOT EXISTS case_work_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NOT NULL,
        work_date DATE DEFAULT NULL,
        work_content TEXT,
        photo_paths TEXT COMMENT 'JSON陣列',
        equipment_used TEXT,
        cable_used TEXT,
        workers TEXT COMMENT 'JSON陣列',
        ragic_id VARCHAR(50) DEFAULT NULL,
        created_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_case (case_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

$wlCount = 0;
foreach ($worklogs as $rid => $row) {
    $content = $row['施工內容'] ?? '';
    if (empty($content)) continue;
    
    $chk = $db->prepare("SELECT id FROM case_work_logs WHERE case_id = ? AND ragic_id = ?");
    $chk->execute(array($caseId, 'st1007229_' . $rid));
    if ($chk->fetch()) {
        echo '<p style="color:orange">⚠ 已存在，跳過</p>';
        continue;
    }
    
    $workDate = !empty($row['施工日期']) ? str_replace('/', '-', $row['施工日期']) : null;
    
    $stmt = $db->prepare("INSERT INTO case_work_logs (case_id, work_date, work_content, equipment_used, cable_used, workers, ragic_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(array(
        $caseId,
        $workDate,
        $content,
        $row['使用器材'] ?? null,
        $row['使用線材'] ?? null,
        !empty($row['施工人員']) ? json_encode($row['施工人員']) : null,
        'st1007229_' . $rid,
        Auth::id()
    ));
    $wlCount++;
    echo '<p style="color:green">✓ 施工: ' . htmlspecialchars(mb_strimwidth($content, 0, 80, '...')) . '</p>';
}
echo '<p><strong>匯入 ' . $wlCount . ' 筆施工紀錄</strong></p>';

// 總結
echo '<hr>';
echo '<h3>匯入完成</h3>';
echo '<ul>';
echo '<li>帳款交易: ' . $payCount . ' 筆</li>';
echo '<li>附件: ' . $attCount . ' 個</li>';
echo '<li>施工紀錄: ' . $wlCount . ' 筆</li>';
echo '</ul>';
echo '<a href="/cases.php?action=edit&id=' . $caseId . '" class="btn btn-primary">查看案件</a>';
