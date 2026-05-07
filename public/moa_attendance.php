<?php
/**
 * MOA 雲考勤資料管理（簽到/簽退/異常）
 * 與既有 attendance.php（工程人員出勤行事曆）為不同功能
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../modules/attendance/AttendanceModel.php';

Auth::requireLogin();
if (!Auth::hasPermission('attendance.manage') && !Auth::hasPermission('attendance.view') && !Auth::hasPermission('all')) {
    Session::flash('error', '無考勤管理權限');
    redirect('/index.php');
}

$model = new AttendanceModel();
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'import':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/moa_attendance.php?action=import'); }
            if (!Auth::hasPermission('attendance.manage') && !Auth::hasPermission('all')) {
                Session::flash('error', '無匯入權限');
                redirect('/moa_attendance.php?action=import');
            }
            if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                Session::flash('error', '檔案上傳失敗');
                redirect('/moa_attendance.php?action=import');
            }
            $orig = $_FILES['file']['name'];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, array('xlsx', 'xls'))) {
                Session::flash('error', '只接受 .xlsx 檔案');
                redirect('/moa_attendance.php?action=import');
            }
            $dir = __DIR__ . '/uploads/attendance';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $saveName = date('Ymd_His') . '_' . mt_rand(1000,9999) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
            $savePath = $dir . '/' . $saveName;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $savePath)) {
                Session::flash('error', '檔案儲存失敗');
                redirect('/moa_attendance.php?action=import');
            }
            try {
                $rows = $model->parseMoaExcel($savePath);
                if (empty($rows)) {
                    Session::flash('error', 'Excel 解析失敗：找不到「考勤詳細」工作表或表頭（簽到/簽退）。請確認檔案來自 MOA「匯出報表 → 詳細報表」。');
                    redirect('/moa_attendance.php?action=import');
                }
                // 從檔名抓年份 hint（MOA 檔名：id...._2026-05-04_2026-05-05_xxx.xlsx）
                $yearHint = (int)date('Y');
                if (preg_match('/(\d{4})-\d{2}-\d{2}/', $orig, $m)) $yearHint = (int)$m[1];
                $stats = $model->importParsedRows($rows, $yearHint, $saveName);
                AuditLog::log('attendance', 'import', 0,
                    'MOA xlsx 匯入：' . $stats['total'] . ' 筆（新 ' . $stats['inserted'] . ' / 更新 ' . $stats['updated'] . ' / 未對應 ' . $stats['unmatched'] . '）');
                $msg = '匯入完成：' . $stats['total'] . ' 筆（新 ' . $stats['inserted'] . '、更新 ' . $stats['updated'] . '、未對應姓名 ' . $stats['unmatched'] . '、跳過 ' . $stats['skipped'] . '）';
                Session::flash('success', $msg);
                redirect('/moa_attendance.php?action=list&date_from=' . urlencode($stats['date_from'] ?: '') . '&date_to=' . urlencode($stats['date_to'] ?: ''));
            } catch (Exception $e) {
                Session::flash('error', '匯入失敗：' . $e->getMessage());
                redirect('/moa_attendance.php?action=import');
            }
        }
        $logs = $model->getImportLogs(20);
        $pageTitle = '匯入 MOA 出勤';
        $currentPage = 'moa_attendance';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/attendance/import.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'employees':
        // 員工對照管理
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/moa_attendance.php?action=employees'); }
            if (!Auth::hasPermission('attendance.manage') && !Auth::hasPermission('all')) {
                Session::flash('error', '無權限'); redirect('/moa_attendance.php?action=employees');
            }
            $empId = (int)($_POST['emp_id'] ?? 0);
            $userId = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (int)$_POST['user_id'] : null;
            $model->setEmployeeMapping($empId, $userId);
            Session::flash('success', '對應已更新');
            redirect('/moa_attendance.php?action=employees');
        }
        $employees = $model->getEmployees();
        $usersStmt = Database::getInstance()->query("SELECT id, real_name FROM users WHERE is_active = 1 ORDER BY real_name");
        $allUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $pageTitle = '考勤員工對照';
        $currentPage = 'moa_attendance';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/attendance/employees.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'list':
    default:
        $filters = array(
            'date_from'     => $_GET['date_from'] ?? date('Y-m-01'),
            'date_to'       => $_GET['date_to']   ?? date('Y-m-d'),
            'name'          => $_GET['name']      ?? '',
            'dept'          => $_GET['dept']      ?? '',
            'only_abnormal' => !empty($_GET['only_abnormal']),
            'unmatched'     => !empty($_GET['unmatched']),
        );
        $records = $model->getRecords($filters, 1000);
        $departments = $model->getDepartments();
        $pageTitle = 'MOA 考勤明細';
        $currentPage = 'moa_attendance';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/attendance/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;
}
