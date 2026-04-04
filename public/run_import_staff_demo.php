<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 從 Ragic 人事資料表 00652 朱良明
$data = array(
    'employee_id' => '00652',
    'real_name' => '朱良明',
    'id_number' => 'B121565642',
    'birth_date' => '1977-09-28',
    'gender' => 'male',
    'marital_status' => 'married',
    'blood_type' => 'O',
    'department' => '潭子分公司工程部',
    'job_title' => '工程師',
    'phone' => '0935-727252',
    'address' => '台中市西區中興里19鄰公正路140巷2號',
    'registered_address' => '台中市西區中興里19鄰公正路140巷2號',
    'bank_name' => '822',
    'bank_account' => '9015-6823-3078',
    'hire_date' => '2026-03-20',
    'employment_status' => 'active',
    'branch_id' => null, // 需查 潭子分公司 ID
);

// 查 潭子分公司 branch_id
$brStmt = $db->prepare("SELECT id FROM branches WHERE name LIKE '%潭子%' LIMIT 1");
$brStmt->execute();
$branchId = $brStmt->fetchColumn();
echo "潭子分公司 ID: " . ($branchId ?: '未找到') . "\n";

// 檢查是否已存在
$chk = $db->prepare("SELECT id FROM users WHERE employee_id = ? OR (real_name = ? AND id_number = ?)");
$chk->execute(array('00652', '朱良明', 'B121565642'));
$existingId = $chk->fetchColumn();

if ($existingId) {
    echo "\n[已存在] 朱良明 (ID: {$existingId})，將更新資料\n";
    if ($execute) {
        $db->prepare("
            UPDATE users SET
                employee_id = ?, real_name = ?, id_number = ?, birth_date = ?, gender = ?,
                marital_status = ?, blood_type = ?, department = ?, job_title = ?,
                phone = ?, address = ?, registered_address = ?,
                bank_name = ?, bank_account = ?, hire_date = ?, employment_status = ?,
                branch_id = ?
            WHERE id = ?
        ")->execute(array(
            '00652', '朱良明', 'B121565642', '1977-09-28', 'male',
            'married', 'O', '潭子分公司工程部', '工程師',
            '0935-727252', '台中市西區中興里19鄰公正路140巷2號', '台中市西區中興里19鄰公正路140巷2號',
            '822', '9015-6823-3078', '2026-03-20', 'active',
            $branchId,
            $existingId
        ));
        echo "  → 更新完成\n";
    }
} else {
    echo "\n[新增] 朱良明\n";
    echo "  資料編號: 00652\n";
    echo "  身分證: B121565642\n";
    echo "  出生日期: 1977/09/28\n";
    echo "  性別: 男\n";
    echo "  婚姻: 已婚\n";
    echo "  血型: O\n";
    echo "  部門: 潭子分公司工程部\n";
    echo "  職稱: 工程師\n";
    echo "  手機: 0935-727252\n";
    echo "  通訊地址: 台中市西區中興里19鄰公正路140巷2號\n";
    echo "  戶籍地址: 台中市西區中興里19鄰公正路140巷2號\n";
    echo "  銀行帳號: 822-9015-6823-3078\n";
    echo "  到職日: 2026/03/20\n";
    echo "  分公司: 潭子分公司 (ID: {$branchId})\n";
    echo "  緊急聯絡人: 游慧敏（配偶）091-112-6620\n";

    if ($execute && $branchId) {
        // 建立帳號（username 用 employee_id，密碼用手機後4碼）
        $username = 'chu_liangming';
        $password = '7252'; // 手機後4碼

        $db->prepare("
            INSERT INTO users (branch_id, username, password_hash, plain_password, real_name, role, phone, is_engineer, is_active, can_view_all_branches,
                employee_id, department, id_number, birth_date, gender, marital_status, blood_type, job_title,
                address, registered_address, bank_name, bank_account, hire_date, employment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute(array(
            $branchId, $username, password_hash($password, PASSWORD_DEFAULT), $password,
            '朱良明', 'eng_manager', '0935-727252', 1, 1, 0,
            '00652', '潭子分公司工程部', 'B121565642', '1977-09-28', 'male', 'married', 'O', '工程師',
            '台中市西區中興里19鄰公正路140巷2號', '台中市西區中興里19鄰公正路140巷2號',
            '822', '9015-6823-3078', '2026-03-20', 'active'
        ));
        $newId = $db->lastInsertId();
        echo "  → 新增完成 (ID: {$newId}, 帳號: {$username}, 密碼: {$password})\n";

        // 緊急聯絡人
        $db->prepare("INSERT INTO emergency_contacts (user_id, name, relationship, phone) VALUES (?, ?, ?, ?)")
           ->execute(array($newId, '游慧敏', '配偶', '091-112-6620'));
        echo "  → 緊急聯絡人已建立\n";
    }
}

echo "\n※ 照片需到人員管理→檢視→證照文件手動上傳\n";
echo "  (Ragic 照片無法直接透過 API 下載，需手動從 Ragic 下載後上傳)\n";
echo "\n完成\n";
