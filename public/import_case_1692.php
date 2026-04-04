<?php
/**
 * 匯入 Ragic 案件 2026-1692 到 hswork
 * 一次性腳本，執行完即可刪除
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

$db = Database::getInstance();

// === 1. 查詢或建立客戶 ===
$stmt = $db->prepare("SELECT id FROM customers WHERE tax_id = ? OR name LIKE ? LIMIT 1");
$stmt->execute(array('24217232', '%台鑫堆高機%'));
$customer = $stmt->fetch();

if ($customer) {
    $customerId = $customer['id'];
    echo "客戶已存在 ID: {$customerId}\n";
} else {
    $stmt = $db->prepare("INSERT INTO customers (customer_no, name, category, contact_person, mobile, invoice_title, tax_id, site_city, site_district, site_address, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(array(
        'Y210-2',
        '台鑫堆高機',
        'old_customer',
        '林先生',
        '0927-965-592',
        '台鑫堆高機有限公司',
        '24217232',
        '彰化縣',
        '鹿港鎮',
        '濱海路598號',
        1
    ));
    $customerId = $db->lastInsertId();
    echo "客戶建立成功 ID: {$customerId}\n";
}

// === 2. 查詢分公司 ID ===
$stmt = $db->prepare("SELECT id FROM branches WHERE name LIKE ? LIMIT 1");
$stmt->execute(array('%員林%'));
$branch = $stmt->fetch();
$branchId = $branch ? $branch['id'] : 1;
echo "分公司 ID: {$branchId}\n";

// === 3. 查詢業務 ID (謝旻倫) ===
$stmt = $db->prepare("SELECT id FROM users WHERE real_name = ? OR real_name LIKE ? LIMIT 1");
$stmt->execute(array('謝旻倫', '%旻倫%'));
$sales = $stmt->fetch();
$salesId = $sales ? $sales['id'] : null;
echo "業務 ID: {$salesId}\n";

// === 4. 檢查案件是否已存在 ===
$stmt = $db->prepare("SELECT id FROM cases WHERE case_number = ? LIMIT 1");
$stmt->execute(array('2026-1692'));
$existingCase = $stmt->fetch();

if ($existingCase) {
    echo "案件 2026-1692 已存在 ID: {$existingCase['id']}，跳過建立\n";
    $caseId = $existingCase['id'];
} else {
    // === 5. 建立案件 ===
    $stmt = $db->prepare("INSERT INTO cases (
        branch_id, case_number, title, case_type, status, sub_status,
        customer_id, customer_name, address, system_type, description,
        quote_amount, deal_date, deal_amount, is_tax_included, tax_amount, total_amount,
        deposit_amount, balance_amount, total_collected,
        billing_title, billing_tax_id,
        sales_id, ragic_id, created_by, notes
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?,
        ?, ?, ?, ?
    )");
    $stmt->execute(array(
        $branchId,
        '2026-1692',
        '台鑫堆高機 - 弱電工程',
        'old_customer_add',
        'closed',
        'completed',
        $customerId,
        '台鑫堆高機',
        '彰化縣鹿港鎮濱海路598號',
        '老客戶查修,移機,追加,更換',
        '客戶需求：弱電。聯絡人：林先生 0927-965-592',
        120000,
        '2026-03-25',
        120000,
        1,
        6000,
        126000,
        0,
        126000,
        126000,
        '台鑫堆高機有限公司',
        '24217232',
        $salesId,
        2546,
        1,
        "完工日期：2026-03-09\n帳款結清：2026-03-25 支票\n追款備註：2026/03/26發票以開 3/27郵寄客戶"
    ));
    $caseId = $db->lastInsertId();
    echo "案件建立成功 ID: {$caseId}\n";
}

// === 6. 上傳附件到案件 ===
// 先確認 case_attachments 表存在
try {
    $db->query("SELECT 1 FROM case_attachments LIMIT 1");
    $hasAttachTable = true;
} catch (Exception $e) {
    $hasAttachTable = false;
    echo "case_attachments 表不存在，跳過附件匯入\n";
}

if ($hasAttachTable) {
    $importDir = __DIR__ . '/../uploads/ragic_import/2026-1692/';
    $uploadDir = __DIR__ . '/../uploads/cases/' . $caseId . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $files = array(
        array('quotation_1.jpg', '報價單-1', 'quotation'),
        array('quotation_2.jpg', '報價單-2', 'quotation'),
        array('ZN00168827.pdf', '保固書 ZN00168827', 'document'),
        array('ZN00168828.pdf', '保固書 ZN00168828', 'document'),
        array('check_photo.jpg', '支票照片', 'payment'),
    );

    foreach ($files as $f) {
        $srcFile = $importDir . $f[0];
        $dstFile = $uploadDir . $f[0];

        if (file_exists($srcFile)) {
            copy($srcFile, $dstFile);

            // 檢查是否已匯入
            $chk = $db->prepare("SELECT id FROM case_attachments WHERE case_id = ? AND file_name = ? LIMIT 1");
            $chk->execute(array($caseId, $f[0]));
            if (!$chk->fetch()) {
                $fileSize = filesize($dstFile) ?: 0;
                $stmt = $db->prepare("INSERT INTO case_attachments (case_id, file_type, file_name, file_path, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute(array(
                    $caseId,
                    $f[2],
                    $f[0],
                    'cases/' . $caseId . '/' . $f[0],
                    $fileSize,
                    1
                ));
                echo "附件匯入: {$f[1]}\n";
            } else {
                echo "附件已存在: {$f[1]}\n";
            }
        } else {
            echo "檔案不存在: {$srcFile}\n";
        }
    }
}

echo "\n=== 匯入完成 ===\n";
echo "案件 ID: {$caseId}\n";
echo "客戶 ID: {$customerId}\n";
echo "檢視案件: https://hswork.com.tw/cases.php?action=view&id={$caseId}\n";
echo '</pre>';
