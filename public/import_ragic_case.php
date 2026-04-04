<?php
/**
 * 從 Ragic 匯入單筆案件 2026-1692（含附件）
 * /import_ragic_case.php?token=hswork2026img
 */
if (($_GET['token'] ?? '') !== 'hswork2026img') die('Token required');

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../modules/cases/CaseModel.php';

$db = Database::getInstance();

echo "<h2>匯入 Ragic 案件 2026-1692</h2><pre>";

// 找承辦業務
$salesStmt = $db->prepare("SELECT id FROM users WHERE real_name = ? LIMIT 1");
$salesStmt->execute(array('謝旻倫'));
$salesRow = $salesStmt->fetch(PDO::FETCH_ASSOC);
$salesId = $salesRow ? (int)$salesRow['id'] : null;

// 找分公司
$branchStmt = $db->prepare("SELECT id FROM branches WHERE name LIKE ? LIMIT 1");
$branchStmt->execute(array('%員林%'));
$branchRow = $branchStmt->fetch(PDO::FETCH_ASSOC);
$branchId = $branchRow ? (int)$branchRow['id'] : 1;

// 找或建立客戶
$custStmt = $db->prepare("SELECT id FROM customers WHERE name LIKE ? LIMIT 1");
$custStmt->execute(array('%台鑫堆高機%'));
$custRow = $custStmt->fetch(PDO::FETCH_ASSOC);
$customerId = $custRow ? (int)$custRow['id'] : null;

if (!$customerId) {
    $db->prepare("INSERT INTO customers (name, contact_name, phone, address, tax_id, created_at) VALUES (?,?,?,?,?,NOW())")
       ->execute(array('台鑫堆高機有限公司', '林先生', '0927-965-592', '彰化縣鹿港鎮濱海路598號', '24217232'));
    $customerId = (int)$db->lastInsertId();
    echo "客戶已建立 (ID: $customerId)\n";
} else {
    echo "客戶已存在 (ID: $customerId)\n";
}

// 檢查案件是否已存在
$existStmt = $db->prepare("SELECT id FROM cases WHERE case_number = ? LIMIT 1");
$existStmt->execute(array('2026-1692'));
$existRow = $existStmt->fetch(PDO::FETCH_ASSOC);

if ($existRow) {
    $caseId = (int)$existRow['id'];
    echo "案件已存在 (ID: $caseId)，跳過建立\n";
} else {
    try {
        // 使用 CaseModel::create 方法
        $caseModel2 = new CaseModel();
        $caseId = $caseModel2->create(array(
            'branch_id'       => $branchId,
            'title'           => '台鑫堆高機 - 老客戶查修/移機/追加/更換',
            'case_type'       => 'maintenance',
            'status'          => 'completed',
            'sub_status'      => '完工未收款',
            'difficulty'      => 3,
            'total_visits'    => 1,
            'max_engineers'   => 4,
            'address'         => '彰化縣鹿港鎮濱海路598號',
            'description'     => '弱電 - 老客戶查修,移機,追加,更換',
            'system_type'     => '老客戶查修,移機,追加,更換',
            'notes'           => '從 Ragic 案件追蹤表匯入',
            'quote_amount'    => 120000,
            'deal_date'       => '2026-03-25',
            'deal_amount'     => 120000,
            'is_tax_included' => 1,
            'tax_amount'      => 6000,
            'total_amount'    => 126000,
            'balance_amount'  => 126000,
            'total_collected' => 0,
            'ragic_id'        => '2026-1692',
            'sales_id'        => $salesId,
            'customer_id'     => $customerId,
            'customer_name'   => '台鑫堆高機',
            'billing_title'   => '台鑫堆高機有限公司',
            'billing_tax_id'  => '24217232',
            'billing_contact' => '林先生',
            'billing_mobile'  => '0927-965-592',
        ));

        // 更新 case_number 為 Ragic 編號
        $db->prepare("UPDATE cases SET case_number = ? WHERE id = ?")->execute(array('2026-1692', $caseId));

        echo "案件已建立 (ID: $caseId)\n";

        // 建立聯絡人
        try {
            $db->prepare("INSERT INTO case_contacts (case_id, contact_name, contact_phone, contact_role) VALUES (?,?,?,?)")
               ->execute(array($caseId, '林先生', '0927-965-592', '客戶'));
            echo "聯絡人已建立\n";
        } catch (Exception $e) {
            echo "聯絡人建立失敗: " . $e->getMessage() . "\n";
        }
    } catch (Exception $e) {
        echo "案件建立失敗: " . $e->getMessage() . "\n";
        echo "</pre>";
        exit;
    }
}

// ====== 附件下載 ======
$ragicBaseUrl = 'https://ap15.ragic.com/sims/file.jsp?a=hstcc&f=';
$uploadDir = __DIR__ . '/uploads/cases/' . $caseId . '/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$attachments = array(
    array('type' => 'quotation',   'file' => 'xNmW5kyBIf@0325台鑫合併議價後.jpg'),
    array('type' => 'quotation',   'file' => '7jXwZkzsKS@0325台鑫合併議價後-1.jpg'),
    array('type' => 'completion',  'file' => 'ekD7lHnDVm@ZN00168827 202512-3-31 2026-1692.pdf'),
    array('type' => 'completion',  'file' => '5gByRhAMkY@ZN00168828 202512-3-31 2026-1692.pdf'),
    array('type' => 'in_progress', 'file' => 'ecuUgcVdbM@S__1261596.jpg'),
);

$caseModel = new CaseModel();
$downloaded = 0;

foreach ($attachments as $att) {
    $ragicFile = $att['file'];
    $fileType = $att['type'];

    $origName = $ragicFile;
    if (strpos($ragicFile, '@') !== false) {
        $origName = substr($ragicFile, strpos($ragicFile, '@') + 1);
    }

    $saveName = preg_replace('/[^\x{4e00}-\x{9fff}a-zA-Z0-9._\- ]/u', '', $origName);
    if (empty($saveName)) $saveName = 'file_' . $downloaded;

    if (file_exists($uploadDir . $saveName)) {
        echo "已存在: $saveName (跳過)\n";
        continue;
    }

    $encodedFile = rawurlencode($ragicFile);
    $downloadUrl = $ragicBaseUrl . $encodedFile;

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $downloadUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Basic ' . base64_encode('hscctvttv@gmail.com:hstc88588859')
        ),
    ));
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && strlen($content) > 100) {
        file_put_contents($uploadDir . $saveName, $content);
        $webPath = '/uploads/cases/' . $caseId . '/' . $saveName;
        $caseModel->saveAttachment($caseId, $fileType, $saveName, $webPath);
        $downloaded++;
        echo "OK: [$fileType] $saveName (" . number_format(strlen($content)) . " bytes)\n";
    } else {
        echo "FAIL: $saveName (HTTP $httpCode)\n";
    }
}

echo "\n=== 完成 ===\n";
echo "案件 ID: $caseId\n";
echo "下載附件: $downloaded 筆\n";
echo "\n<a href='https://hswork.com.tw/cases.php?action=view&id=$caseId'>查看案件</a>\n";
echo "</pre>";
