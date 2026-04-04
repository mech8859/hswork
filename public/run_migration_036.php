<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage')) { die('No permission'); }

$db = Database::getInstance();
$errors = array();
$ok = 0;

// 1. Create table
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            setting_group VARCHAR(50) DEFAULT 'general',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $ok++;
} catch (Exception $e) {
    $errors[] = 'CREATE: ' . $e->getMessage();
}

// 2. Insert defaults
$defaults = array(
    array('quote_bank_name', '禾順監視數位科技有限公司', 'quotation'),
    array('quote_bank_branch', '中國信託銀行(822) 豐原分行', 'quotation'),
    array('quote_bank_account', '3925-4087-3162', 'quotation'),
    array('quote_contact_address', '台中市潭子區環中路一段138巷1之5號', 'quotation'),
    array('quote_contact_phone', '04-2534-7007', 'quotation'),
    array('quote_contact_fax', '04-2534-7661', 'quotation'),
    array('quote_service_phone', '0800-008-859', 'quotation'),
    array('quote_bank_reminder', '溫馨提醒:匯款後記得告知匯款帳號4-6碼及金額', 'quotation'),
    array('quote_deposit_notice', '附註說明:( 定金視同價金之一部分,除不可抗力之因素外,凡取消安裝.定金即視為違約金沒入。)', 'quotation'),
    array('quote_warranty_months', '12', 'quotation'),
    array('quote_warranty_text_1', '產品安裝日【設備(含變壓器)保固{months}個月，消耗品除外】，保固期內任何因材質、製造、裝配等瑕疵外屬人為因素導致設備與配線故障或損壞相關損壞之零件,本公司負責檢修及報價更換，如出勤維修判定非本公司因素,酌收出勤費用。', 'quotation'),
    array('quote_warranty_text_2', '應收工程款未點交完成前，標的物線路、設備之所有權仍歸屬本公司，買受人無異議且同意本公司無須經法律程序，隨時取回貨品或代物清償。(天災、雷擊、鼠害、人為破壞 非保固範圍)。第二年起出勤需加收費用。', 'quotation'),
    array('quote_warranty_text_3', '付款方式:30%定金 70% 完工當日付現或當日匯款，如配合工程則以雙方協調後之訂定付款方式。', 'quotation'),
    array('quote_stamp_image', '', 'quotation'),
    array('quote_qrcode_image', '', 'quotation'),
    array('quote_line_id', '@hs0425347007', 'quotation'),
);

$stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_key = setting_key");
foreach ($defaults as $d) {
    try {
        $stmt->execute($d);
        $ok++;
    } catch (Exception $e) {
        $errors[] = $d[0] . ': ' . $e->getMessage();
    }
}

// 3. Add warranty_months column to quotations
try {
    $db->exec("ALTER TABLE quotations ADD COLUMN warranty_months SMALLINT DEFAULT 12 AFTER discount_amount");
    $ok++;
} catch (Exception $e) {
    // Column might already exist
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        $errors[] = 'ALTER: ' . $e->getMessage();
    }
}

// 4. Add visit_method column to cases
try {
    $db->exec("ALTER TABLE cases ADD COLUMN visit_method VARCHAR(20) DEFAULT NULL AFTER survey_date");
    $ok++;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        $errors[] = 'ALTER visit_method: ' . $e->getMessage();
    }
}

echo "Done. {$ok} operations OK.";
if ($errors) echo "<br>Errors: " . implode("<br>", $errors);
