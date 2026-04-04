<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('staff.manage');

$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$type = $_GET['type'] ?? 'dispatch'; // dispatch or outsource

// ========== 外包廠商 ==========
if ($type === 'vendor') {
    switch ($action) {
        case 'list':
            $vendors = $db->query("SELECT ov.*, (SELECT COUNT(*) FROM dispatch_workers dw WHERE dw.vendor_id = ov.id AND dw.is_active = 1) as worker_count FROM outsource_vendors ov ORDER BY ov.is_active DESC, ov.name")->fetchAll();
            $pageTitle = '外包廠商管理';
            $currentPage = 'staff';
            require __DIR__ . '/../templates/layouts/header.php';
            require __DIR__ . '/../templates/dispatch_workers/vendor_list.php';
            require __DIR__ . '/../templates/layouts/footer.php';
            break;

        case 'create':
        case 'edit':
            $vendor = null;
            if ($action === 'edit') {
                $id = (int)($_GET['id'] ?? 0);
                $stmt = $db->prepare("SELECT * FROM outsource_vendors WHERE id = ?");
                $stmt->execute(array($id));
                $vendor = $stmt->fetch();
                if (!$vendor) { Session::flash('error', '廠商不存在'); redirect('/dispatch_workers.php?type=vendor'); }
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/dispatch_workers.php?type=vendor'); }
                $data = array(
                    trim($_POST['name'] ?? ''),
                    trim($_POST['contact_person'] ?? ''),
                    trim($_POST['phone'] ?? ''),
                    trim($_POST['address'] ?? ''),
                    trim($_POST['tax_id'] ?? ''),
                    trim($_POST['note'] ?? ''),
                    isset($_POST['is_active']) ? 1 : 0,
                );

                if ($action === 'edit') {
                    $db->prepare("UPDATE outsource_vendors SET name=?, contact_person=?, phone=?, address=?, tax_id=?, note=?, is_active=? WHERE id=?")->execute(array_merge($data, array($vendor['id'])));
                    Session::flash('success', '廠商已更新');
                } else {
                    $db->prepare("INSERT INTO outsource_vendors (name, contact_person, phone, address, tax_id, note, is_active) VALUES (?,?,?,?,?,?,?)")->execute($data);
                    Session::flash('success', '廠商已新增');
                }
                redirect('/dispatch_workers.php?type=vendor');
            }

            $pageTitle = $action === 'edit' ? '編輯廠商' : '新增廠商';
            $currentPage = 'staff';
            require __DIR__ . '/../templates/layouts/header.php';
            require __DIR__ . '/../templates/dispatch_workers/vendor_form.php';
            require __DIR__ . '/../templates/layouts/footer.php';
            break;
    }
    exit;
}

// ========== 點工人員 ==========
switch ($action) {
    case 'list':
        $where = "WHERE dw.worker_type = ?";
        $params = array($type === 'outsource' ? 'outsource' : 'dispatch');
        
        $keyword = trim($_GET['keyword'] ?? '');
        if ($keyword) {
            $where .= " AND (dw.name LIKE ? OR dw.phone LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }

        $stmt = $db->prepare("SELECT dw.*, ov.name as vendor_name FROM dispatch_workers dw LEFT JOIN outsource_vendors ov ON dw.vendor_id = ov.id $where ORDER BY dw.is_active DESC, dw.status ASC, dw.name");
        $stmt->execute($params);
        $workers = $stmt->fetchAll();

        $vendors = $db->query("SELECT id, name FROM outsource_vendors WHERE is_active = 1 ORDER BY name")->fetchAll();

        $pageTitle = $type === 'outsource' ? '外包人員管理' : '點工人員管理';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/dispatch_workers/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
    case 'edit':
        $worker = null;
        if ($action === 'edit') {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM dispatch_workers WHERE id = ?");
            $stmt->execute(array($id));
            $worker = $stmt->fetch();
            if (!$worker) { Session::flash('error', '人員不存在'); redirect('/dispatch_workers.php'); }

            // 附件
            $fstmt = $db->prepare("SELECT * FROM dispatch_worker_files WHERE worker_id = ? ORDER BY file_type, created_at");
            $fstmt->execute(array($id));
            $worker['files'] = $fstmt->fetchAll();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/dispatch_workers.php'); }
            
            $wType = $_POST['worker_type'] ?? 'dispatch';
            $data = array(
                $wType,
                trim($_POST['name'] ?? ''),
                trim($_POST['id_number'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['address'] ?? ''),
                !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
                trim($_POST['specialty'] ?? ''),
                $_POST['status'] ?? 'primary',
                (int)($_POST['daily_rate'] ?? 0),
                trim($_POST['emergency_contact'] ?? ''),
                trim($_POST['emergency_phone'] ?? ''),
                !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null,
                trim($_POST['note'] ?? ''),
                isset($_POST['is_active']) ? 1 : 0,
            );

            if ($action === 'edit') {
                $db->prepare("UPDATE dispatch_workers SET worker_type=?, name=?, id_number=?, phone=?, address=?, birth_date=?, specialty=?, status=?, daily_rate=?, emergency_contact=?, emergency_phone=?, vendor_id=?, note=?, is_active=? WHERE id=?")->execute(array_merge($data, array($worker['id'])));
                $workerId = $worker['id'];
                Session::flash('success', '人員已更新');
            } else {
                $db->prepare("INSERT INTO dispatch_workers (worker_type, name, id_number, phone, address, birth_date, specialty, status, daily_rate, emergency_contact, emergency_phone, vendor_id, note, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($data);
                $workerId = (int)$db->lastInsertId();
                Session::flash('success', '人員已新增');
            }

            // 處理附件上傳
            if (!empty($_FILES['files']['name'][0])) {
                $fileTypes = $_POST['file_types'] ?? array();
                $uploadDir = __DIR__ . '/uploads/dispatch_workers/' . $workerId;
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                foreach ($_FILES['files']['name'] as $i => $fname) {
                    if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $safeName = date('Ymd_His') . '_' . mt_rand(1000,9999) . '_' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $fname);
                    $dest = $uploadDir . '/' . $safeName;
                    if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $dest)) {
                        $relPath = 'uploads/dispatch_workers/' . $workerId . '/' . $safeName;
                        $fType = isset($fileTypes[$i]) ? $fileTypes[$i] : 'other';
                        $db->prepare("INSERT INTO dispatch_worker_files (worker_id, file_type, file_name, file_path, file_size, uploaded_by) VALUES (?,?,?,?,?,?)")->execute(array(
                            $workerId, $fType, $fname, $relPath, filesize($dest), Auth::id()
                        ));
                        backup_to_drive($dest, 'dispatch_workers', $workerId);
                    }
                }
            }

            redirect('/dispatch_workers.php?action=edit&id=' . $workerId);
        }

        $vendors = $db->query("SELECT id, name FROM outsource_vendors WHERE is_active = 1 ORDER BY name")->fetchAll();

        $pageTitle = $action === 'edit' ? '編輯人員' : '新增人員';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/dispatch_workers/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'delete_file':
        $fileId = (int)($_GET['file_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM dispatch_worker_files WHERE id = ?");
        $stmt->execute(array($fileId));
        $file = $stmt->fetch();
        if ($file) {
            @unlink(__DIR__ . '/' . $file['file_path']);
            $db->prepare("DELETE FROM dispatch_worker_files WHERE id = ?")->execute(array($fileId));
        }
        redirect('/dispatch_workers.php?action=edit&id=' . ($file['worker_id'] ?? 0));
        break;

    // ---- 技能設定 ----
    case 'skills':
        if (!Auth::hasPermission('staff.manage') && !Auth::hasPermission('schedule.manage')) {
            Session::flash('error', '無權限');
            redirect('/dispatch_workers.php');
        }
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM dispatch_workers WHERE id = ?");
        $stmt->execute(array($id));
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$worker) { Session::flash('error', '人員不存在'); redirect('/dispatch_workers.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/dispatch_workers.php?action=skills&id=' . $id); }
            $skills = isset($_POST['skills']) ? $_POST['skills'] : array();
            // 清除舊資料
            $db->prepare("DELETE FROM dispatch_worker_skills WHERE dispatch_worker_id = ?")->execute(array($id));
            // 寫入新資料
            $insStmt = $db->prepare("INSERT INTO dispatch_worker_skills (dispatch_worker_id, skill_id, proficiency) VALUES (?, ?, ?)");
            foreach ($skills as $skillId => $prof) {
                $prof = (int)$prof;
                if ($prof > 0) {
                    $insStmt->execute(array($id, (int)$skillId, $prof));
                }
            }
            Session::flash('success', '技能已儲存');
            redirect('/dispatch_workers.php?action=skills&id=' . $id);
        }

        // 取得所有技能
        $allSkills = $db->query("SELECT * FROM skills WHERE is_active = 1 ORDER BY skill_group, category, sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
        // 取得此人員已設技能
        $wsStmt = $db->prepare("SELECT skill_id, proficiency FROM dispatch_worker_skills WHERE dispatch_worker_id = ?");
        $wsStmt->execute(array($id));
        $workerSkillMap = array();
        foreach ($wsStmt->fetchAll(PDO::FETCH_ASSOC) as $ws) {
            $workerSkillMap[(int)$ws['skill_id']] = (int)$ws['proficiency'];
        }

        $pageTitle = $worker['name'] . ' - 技能設定';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/dispatch_workers/skills.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 工程師配對 ----
    case 'pairs':
        if (!Auth::hasPermission('staff.manage') && !Auth::hasPermission('schedule.manage')) {
            Session::flash('error', '無權限');
            redirect('/dispatch_workers.php');
        }
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM dispatch_workers WHERE id = ?");
        $stmt->execute(array($id));
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$worker) { Session::flash('error', '人員不存在'); redirect('/dispatch_workers.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/dispatch_workers.php?action=pairs&id=' . $id); }
            $pairs = isset($_POST['pairs']) ? $_POST['pairs'] : array();
            // 清除舊資料
            $db->prepare("DELETE FROM dispatch_engineer_pairs WHERE dispatch_worker_id = ?")->execute(array($id));
            // 寫入新資料
            $insStmt = $db->prepare("INSERT INTO dispatch_engineer_pairs (dispatch_worker_id, user_id, compatibility, note) VALUES (?, ?, ?, ?)");
            foreach ($pairs as $userId => $pData) {
                $compat = (int)($pData['compatibility'] ?? 0);
                if ($compat > 0) {
                    $insStmt->execute(array($id, (int)$userId, $compat, $pData['note'] ?: null));
                }
            }
            Session::flash('success', '配對已儲存');
            redirect('/dispatch_workers.php?action=pairs&id=' . $id);
        }

        // 取得所有工程師
        $branchIds = Auth::getAccessibleBranchIds();
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $engineers = $db->prepare("SELECT u.id, u.real_name, b.name AS branch_name FROM users u JOIN branches b ON u.branch_id = b.id WHERE u.role IN ('engineer','eng_manager') AND u.is_active = 1 AND u.branch_id IN ($ph) ORDER BY b.name, u.real_name");
        $engineers->execute($branchIds);
        $engineers = $engineers->fetchAll(PDO::FETCH_ASSOC);

        // 取得現有配對
        $epStmt = $db->prepare("SELECT user_id, compatibility, note FROM dispatch_engineer_pairs WHERE dispatch_worker_id = ?");
        $epStmt->execute(array($id));
        $existingPairs = array();
        foreach ($epStmt->fetchAll(PDO::FETCH_ASSOC) as $ep) {
            $existingPairs[(int)$ep['user_id']] = $ep;
        }

        $pageTitle = $worker['name'] . ' - 工程師配對';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/dispatch_workers/pairs.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 可上工日期登錄 ----
    case 'availability':
        // 新增登錄
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/dispatch_workers.php?action=availability'); }
            $workerId = (int)($_POST['dispatch_worker_id'] ?? 0);
            $dateVal = $_POST['available_date'] ?? '';
            if ($workerId && $dateVal) {
                try {
                    $db->prepare('INSERT INTO dispatch_worker_availability (dispatch_worker_id, available_date, registered_by) VALUES (?, ?, ?)')
                       ->execute(array($workerId, $dateVal, Auth::id()));
                    Session::flash('success', '已登錄可上工日期');
                } catch (Exception $e) {
                    Session::flash('error', '該人員該日期已登錄過');
                }
            }
            redirect('/dispatch_workers.php?action=availability&worker_id=' . $workerId);
        }

        // 刪除登錄
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/dispatch_workers.php?action=availability'); }
            $avId = (int)($_POST['availability_id'] ?? 0);
            $workerId = (int)($_POST['worker_id'] ?? 0);
            if ($avId) {
                $db->prepare('DELETE FROM dispatch_worker_availability WHERE id = ?')->execute(array($avId));
                Session::flash('success', '已刪除');
            }
            redirect('/dispatch_workers.php?action=availability&worker_id=' . $workerId);
        }

        // 載入資料
        $workers = $db->query("SELECT id, name, specialty, vendor FROM dispatch_workers WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $selectedWorker = (int)($_GET['worker_id'] ?? 0);
        $records = array();
        if ($selectedWorker) {
            $stmt = $db->prepare("SELECT dwa.*, u.real_name AS registered_by_name FROM dispatch_worker_availability dwa LEFT JOIN users u ON dwa.registered_by = u.id WHERE dwa.dispatch_worker_id = ? AND dwa.available_date >= CURDATE() ORDER BY dwa.available_date");
            $stmt->execute(array($selectedWorker));
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $pageTitle = '可上工日期登錄';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/dispatch_workers/availability.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;
}
