<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
set_time_limit(600);
ini_set('memory_limit', '512M');
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';

$db = Database::getInstance();
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
$withPhotos = isset($_GET['photos']) && $_GET['photos'] == '1';
$startFrom = isset($_GET['from']) ? (int)$_GET['from'] : 0;

echo $execute ? "=== 執行模式 ===" : "=== 預覽模式 === (加 ?execute=1 執行，加 &amp;photos=1 含照片)";
if ($startFrom > 0) echo " (從第 {$startFrom} 筆開始)";
echo "\n\n";

// ========== 讀取本地 JSON ==========
$jsonPath = __DIR__ . '/../ragic_staff_all.json';
if (!file_exists($jsonPath)) { die("ragic_staff_all.json 不存在\n"); }
$records = json_decode(file_get_contents($jsonPath), true);
if (!$records) { die("JSON 解析失敗\n"); }
echo "讀取 " . count($records) . " 筆人員資料\n\n";

// ========== 分公司對照 ==========
$branches = $db->query("SELECT id, name FROM branches")->fetchAll(PDO::FETCH_ASSOC);
$branchMap = array();
foreach ($branches as $br) { $branchMap[$br['name']] = $br['id']; }

function findBranchId($ragicBranch, $branchMap) {
    if (empty($ragicBranch)) return null;
    if (isset($branchMap[$ragicBranch])) return $branchMap[$ragicBranch];
    foreach ($branchMap as $name => $id) {
        if (strpos($ragicBranch, str_replace(array('分公司','據點'), '', $name)) !== false) return $id;
        if (strpos($name, str_replace(array('分公司','據點'), '', $ragicBranch)) !== false) return $id;
    }
    $keywords = array('潭子'=>'潭子', '清水'=>'清水', '員林'=>'員林', '東區'=>'東區', '管理'=>'中區管理');
    foreach ($keywords as $kw => $brName) {
        if (strpos($ragicBranch, $kw) !== false) {
            foreach ($branchMap as $name => $id) { if (strpos($name, $brName) !== false) return $id; }
        }
    }
    return null;
}

function mapGender($v) { return ($v === '男' || $v === 'male') ? 'male' : (($v === '女' || $v === 'female') ? 'female' : ''); }
function mapMarital($v) {
    if (strpos($v, '未婚') !== false) return 'single';
    if (strpos($v, '已婚') !== false) return 'married';
    if (strpos($v, '離婚') !== false) return 'divorced';
    return $v ?: '';
}
function mapBlood($v) { return str_replace('型', '', trim($v)); }
function mapEmployment($v) {
    if (strpos($v, '試用') !== false) return 'probation';
    if (strpos($v, '離職') !== false) return 'resigned';
    if (strpos($v, '留停') !== false) return 'active';
    if (strpos($v, '退休') !== false) return 'retired';
    return 'active';
}
function parseDate($v) {
    if (empty($v)) return null;
    $v = str_replace('/', '-', trim($v));
    // Ragic 有些離職日期格式異常如 0114-02-28
    if (preg_match('/^0\d{3}-/', $v)) return null;
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : null;
}
function generateUsername($empId, $name, $db) {
    $base = $empId ?: preg_replace('/[^a-zA-Z0-9]/', '', $name);
    if (empty($base)) $base = 'staff';
    $base = strtolower($base);
    $username = $base;
    $suffix = 1;
    while (true) {
        $chk = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $chk->execute(array($username));
        if ($chk->fetchColumn() == 0) break;
        $username = $base . '_' . $suffix;
        $suffix++;
    }
    return $username;
}

// ========== Ragic 圖片下載設定 ==========
$ragicAuth = 'Authorization: Basic ' . base64_encode('hscctvttv@gmail.com:hstc88588859');
$cookieStr = '';
if ($withPhotos) {
    // 取 cookie
    $ch = curl_init('https://ap15.ragic.com/hstcc/ragicforms4/20004');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($ragicAuth, 'User-Agent: Mozilla/5.0'));
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $resp = curl_exec($ch);
    preg_match_all('/Set-Cookie:\s*([^;\r\n]+)/i', $resp, $cm);
    $cookieStr = implode('; ', $cm[1]);
    curl_close($ch);
    echo "照片下載 Cookie: " . (strlen($cookieStr) > 0 ? '已取得' : '失敗') . "\n\n";
}
$uploadDir = __DIR__ . '/uploads/staff/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// ========== 批次匯入 ==========
$imported = 0; $updated = 0; $skipped = 0; $errors = 0; $photoCount = 0;

foreach ($records as $idx => $r) {
    $num = $idx + 1;
    if ($num <= $startFrom) continue;
    $empId = trim($r['employee_id']);
    $name = trim($r['name']);
    if (empty($name)) { $skipped++; continue; }

    $branchId = findBranchId($r['branch'], $branchMap);
    $genderVal = mapGender($r['gender']);
    $maritalVal = mapMarital($r['marital']);
    $bloodVal = mapBlood($r['blood_type']);
    $empStatusVal = mapEmployment($r['status']);
    $hireDateVal = parseDate($r['hire_date']);
    $resignDateVal = parseDate($r['resign_date']);
    $birthDateVal = parseDate($r['birth_date']);
    $bankName = '';
    $bankAcct = $r['bank_account'];
    if ($bankAcct && preg_match('/^(\d{3})[- ](.+)$/', $bankAcct, $bm)) {
        $bankName = $bm[1]; $bankAcct = $bm[2];
    }
    $cleanPhone = preg_replace('/[^0-9]/', '', $r['phone']);
    $password = strlen($cleanPhone) >= 4 ? substr($cleanPhone, -4) : '1234';

    $role = 'admin_staff';
    $isEngineer = 0;
    $jt = $r['job_title'];
    if (strpos($jt, '工程') !== false) { $isEngineer = 1; }
    if (strpos($jt, '業務') !== false) { $role = 'sales'; }

    $statusLabel = $empStatusVal === 'resigned' ? '離職' : ($empStatusVal === 'probation' ? '試用' : '在職');
    echo "[{$num}/101] {$empId} {$name} | {$r['department']} {$jt} | {$r['branch']} | {$statusLabel}\n";

    // 檢查是否已存在
    $chk = $db->prepare("SELECT id FROM users WHERE employee_id = ? AND employee_id != '' AND employee_id IS NOT NULL");
    $chk->execute(array($empId));
    $existingId = $chk->fetchColumn();
    if (!$existingId && $r['id_number']) {
        $chk2 = $db->prepare("SELECT id FROM users WHERE id_number = ? AND id_number != ''");
        $chk2->execute(array($r['id_number']));
        $existingId = $chk2->fetchColumn();
    }
    if (!$existingId) {
        $chk3 = $db->prepare("SELECT id FROM users WHERE real_name = ? AND phone = ? AND phone != ''");
        $chk3->execute(array($name, $r['phone']));
        $existingId = $chk3->fetchColumn();
    }

    if ($existingId) {
        echo "  [已存在 ID:{$existingId}] 更新\n";
        if ($execute) {
            try {
                $db->prepare("
                    UPDATE users SET
                        employee_id=?, real_name=?, id_number=?, birth_date=?, gender=?,
                        marital_status=?, blood_type=?, education_level=?, department=?, job_title=?,
                        phone=?, email=?, address=?, registered_address=?,
                        bank_name=?, bank_account=?, hire_date=?, resignation_date=?,
                        employment_status=?, labor_insurance_company=?,
                        dependent_insurance=?, annual_leave_days=?,
                        branch_id=COALESCE(?, branch_id), is_engineer=?
                    WHERE id=?
                ")->execute(array(
                    $empId, $name, $r['id_number'], $birthDateVal, $genderVal,
                    $maritalVal, $bloodVal, $r['education'], $r['department'], $jt,
                    $r['phone'], $r['email'], $r['address'], $r['reg_address'],
                    $bankName, $bankAcct, $hireDateVal, $resignDateVal,
                    $empStatusVal, $r['labor_company'],
                    $r['dependent'], (int)$r['annual_leave'],
                    $branchId, $isEngineer,
                    $existingId
                ));
                $updated++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n"; $errors++;
            }
        } else { $updated++; }
        $userId = $existingId;
    } else {
        echo "  [新增]\n";
        if ($execute) {
            try {
                $username = generateUsername($empId, $name, $db);
                $db->prepare("
                    INSERT INTO users (branch_id, username, password_hash, plain_password, real_name, role, phone, email,
                        is_engineer, is_active, can_view_all_branches, is_mobile,
                        employee_id, department, id_number, birth_date, gender, marital_status, blood_type,
                        education_level, job_title, address, registered_address,
                        bank_name, bank_account, hire_date, resignation_date, employment_status,
                        labor_insurance_company, dependent_insurance, annual_leave_days)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute(array(
                    $branchId, $username, password_hash($password, PASSWORD_DEFAULT), $password,
                    $name, $role, $r['phone'], $r['email'],
                    $isEngineer, ($empStatusVal === 'resigned' ? 0 : 1), 0, 1,
                    $empId, $r['department'], $r['id_number'], $birthDateVal, $genderVal, $maritalVal, $bloodVal,
                    $r['education'], $jt, $r['address'], $r['reg_address'],
                    $bankName, $bankAcct, $hireDateVal, $resignDateVal, $empStatusVal,
                    $r['labor_company'], $r['dependent'], (int)$r['annual_leave']
                ));
                $userId = $db->lastInsertId();
                echo "  → ID:{$userId}, 帳號:{$username}, 密碼:{$password}\n";
                $imported++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n"; $errors++;
                $userId = null;
            }
        } else {
            $imported++;
            $userId = null;
        }
    }

    // ========== 照片 ==========
    if ($withPhotos && $execute && $userId) {
        $photoFields = array(
            'id_front' => '身分證-正面',
            'id_back' => '身分證-反面',
            'license_front' => '汽車駕照-正面',
            'license_back' => '汽車駕照-反面',
            'photo' => '大頭貼',
            'safety_officer' => '甲種營造業職業安全衛生業務主管',
            'safety_cert' => '一般安全衛生教育結業證書 - 營造業',
            'aerial_worker' => '高空作業車操作人員',
            'first_aid' => '急救人員',
            'telecom_c' => '通訊技術士-丙',
            'telecom_b' => '通訊技術士-乙',
            'network_c' => '網路架設-丙',
            'network_b' => '網路架設-乙',
            'wiring_c' => '室內配線-丙',
            'wiring_b' => '室內配線-乙',
            'cad_c' => '繪圖設計-丙',
            'cad_b' => '繪圖設計-乙',
            'web_c' => '網頁設計-丙',
            'web_b' => '網頁設計-乙',
            'unifi' => 'UNIFI證書',
            'fluke' => 'FLUKE證書',
            'panduit' => 'PANDUIT證書',
        );
        foreach ($photoFields as $docType => $label) {
            $fileVal = isset($r[$docType]) ? $r[$docType] : '';
            // avatar 欄位名在 JSON 裡是 avatar
            if ($docType === 'photo' && empty($fileVal)) $fileVal = isset($r['avatar']) ? $r['avatar'] : '';
            if (empty($fileVal) || strpos($fileVal, '@') === false) continue;

            $imgUrl = 'https://ap15.ragic.com/sims/file.jsp?a=hstcc&f=' . rawurlencode($fileVal);
            $ich = curl_init($imgUrl);
            curl_setopt($ich, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ich, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ich, CURLOPT_HTTPHEADER, array($ragicAuth, 'User-Agent: Mozilla/5.0', 'Cookie: ' . $cookieStr));
            curl_setopt($ich, CURLOPT_TIMEOUT, 15);
            $imgData = curl_exec($ich);
            $imgHttp = curl_getinfo($ich, CURLINFO_HTTP_CODE);
            $imgCt = curl_getinfo($ich, CURLINFO_CONTENT_TYPE);
            curl_close($ich);

            if ($imgHttp == 200 && strlen($imgData) > 100) {
                $ext = 'jpg';
                if (strpos($imgCt, 'png') !== false) $ext = 'png';
                $origExt = pathinfo($fileVal, PATHINFO_EXTENSION);
                if ($origExt) $ext = strtolower($origExt);

                $saveName = 'staff_' . $userId . '_' . $docType . '_' . date('Ymd_His') . '.' . $ext;
                file_put_contents($uploadDir . $saveName, $imgData);
                $dbPath = '/uploads/staff/' . $saveName;

                $db->prepare("DELETE FROM staff_documents WHERE user_id = ? AND doc_type = ?")->execute(array($userId, $docType));
                $db->prepare("INSERT INTO staff_documents (user_id, doc_type, doc_label, file_path, file_name, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())")
                   ->execute(array($userId, $docType, $label, $dbPath, $saveName));
                if ($docType === 'photo') {
                    $db->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute(array($dbPath, $userId));
                }
                echo "  📷 {$label} (" . round(strlen($imgData)/1024) . "KB)\n";
                $photoCount++;
            }
        }
    }
    echo "\n";
    ob_flush(); flush();
}

echo "======================================================\n";
echo "完成！\n";
echo "  新增: {$imported} 筆\n";
echo "  更新: {$updated} 筆\n";
echo "  跳過: {$skipped} 筆\n";
echo "  錯誤: {$errors} 筆\n";
if ($withPhotos) echo "  照片: {$photoCount} 張\n";
echo "\n用法：\n";
echo "  預覽: /run_import_all_staff.php\n";
echo "  執行(不含照片): /run_import_all_staff.php?execute=1\n";
echo "  執行(含照片): /run_import_all_staff.php?execute=1&amp;photos=1\n";
echo '</pre>';
