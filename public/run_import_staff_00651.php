<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 從 Ragic 人事資料表 00651 楊雨倫
$data = array(
    'employee_id' => '00651',
    'real_name' => '楊雨倫',
    'id_number' => 'L225116823',
    'birth_date' => '1998-08-24',
    'gender' => 'female',
    'marital_status' => 'single',
    'blood_type' => 'B',
    'department' => '清水電子鎖門市部',
    'job_title' => '門市',
    'phone' => '0975-407350',
    'address' => '台中市清水區橋頭里30鄰民治五街141號',
    'registered_address' => '台中市清水區橋頭里30鄰民治五街141號',
    'bank_name' => '822',
    'bank_account' => '1605-4073-5636',
    'hire_date' => '2026-03-16',
    'employment_status' => 'probation',
    'email' => '',
);

// 查清水電子鎖 branch_id
$brStmt = $db->prepare("SELECT id, name FROM branches WHERE name LIKE '%清水%' LIMIT 1");
$brStmt->execute();
$branch = $brStmt->fetch(PDO::FETCH_ASSOC);
$branchId = $branch ? $branch['id'] : null;
echo "分公司: " . ($branch ? $branch['name'] . " (ID: {$branchId})" : '未找到，需手動設定') . "\n";

// 檢查是否已存在
$chk = $db->prepare("SELECT id FROM users WHERE employee_id = ? OR (real_name = ? AND id_number = ?)");
$chk->execute(array('00651', '楊雨倫', 'L225116823'));
$existingId = $chk->fetchColumn();

echo "\n--- 楊雨倫 (00651) 資料 ---\n";
echo "  員工編號: 00651\n";
echo "  姓名: 楊雨倫\n";
echo "  身分證: L225116823\n";
echo "  出生日期: 1998/08/24\n";
echo "  性別: 女\n";
echo "  婚姻: 未婚\n";
echo "  血型: B\n";
echo "  部門: 清水電子鎖門市部\n";
echo "  職稱: 門市\n";
echo "  手機: 0975-407350\n";
echo "  通訊地址: 台中市清水區橋頭里30鄰民治五街141號\n";
echo "  戶籍地址: 台中市清水區橋頭里30鄰民治五街141號\n";
echo "  銀行帳號: 822-1605-4073-5636\n";
echo "  到職日: 2026/03/16\n";
echo "  在職狀態: 試用\n";
echo "  緊急聯絡人: 陳秀玲（母女）096-343-0528\n";

if ($existingId) {
    echo "\n[已存在] 楊雨倫 (ID: {$existingId})，將更新資料\n";
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
            '00651', '楊雨倫', 'L225116823', '1998-08-24', 'female',
            'single', 'B', '清水電子鎖門市部', '門市',
            '0975-407350', '台中市清水區橋頭里30鄰民治五街141號', '台中市清水區橋頭里30鄰民治五街141號',
            '822', '1605-4073-5636', '2026-03-16', 'probation',
            $branchId,
            $existingId
        ));
        echo "  → 更新完成\n";
    }
} else {
    echo "\n[新增] 楊雨倫\n";
    if ($execute) {
        // 帳號: yang_yulun，密碼: 手機後4碼
        $username = 'yang_yulun';
        $password = '7350';

        $db->prepare("
            INSERT INTO users (branch_id, username, password_hash, plain_password, real_name, role, phone, is_engineer, is_active, can_view_all_branches,
                employee_id, department, id_number, birth_date, gender, marital_status, blood_type, job_title,
                address, registered_address, bank_name, bank_account, hire_date, employment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute(array(
            $branchId, $username, password_hash($password, PASSWORD_DEFAULT), $password,
            '楊雨倫', 'admin_staff', '0975-407350', 0, 1, 0,
            '00651', '清水電子鎖門市部', 'L225116823', '1998-08-24', 'female', 'single', 'B', '門市',
            '台中市清水區橋頭里30鄰民治五街141號', '台中市清水區橋頭里30鄰民治五街141號',
            '822', '1605-4073-5636', '2026-03-16', 'probation'
        ));
        $newId = $db->lastInsertId();
        echo "  → 新增完成 (ID: {$newId}, 帳號: {$username}, 密碼: {$password})\n";

        // 緊急聯絡人
        try {
            $db->prepare("INSERT INTO staff_emergency_contacts (user_id, contact_name, relationship, mobile) VALUES (?, ?, ?, ?)")
               ->execute(array($newId, '陳秀玲', '母女', '096-343-0528'));
            echo "  → 緊急聯絡人已建立\n";
        } catch (PDOException $e) {
            echo "  → 緊急聯絡人建立失敗: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  帳號: yang_yulun\n";
        echo "  密碼: 7350 (手機後4碼)\n";
        echo "  角色: admin_staff (行政人員)\n";
    }
}

echo "\n※ 照片需到人員管理→檢視→證照文件手動上傳\n";
echo "\n完成\n";
