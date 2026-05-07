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

// Migration 檢查：避免 attendance_records 表不存在時 500
try {
    $_chk = Database::getInstance()->query("SHOW TABLES LIKE 'attendance_records'")->fetch();
    if (!$_chk) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #ddd;border-radius:8px;background:#fff8e1">';
        echo '<h2>⚠ MOA 考勤資料表尚未建立</h2>';
        echo '<p>請點擊下方連結執行 migration（boss 帳號）：</p>';
        echo '<p><a href="/run_migration_141.php" target="_blank" style="display:inline-block;background:#1976d2;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none">執行 Migration 141</a></p>';
        echo '<p style="color:#666;margin-top:20px">完成後重新整理本頁面即可使用。</p>';
        echo '</div>';
        exit;
    }
} catch (Exception $_e) {
    // 忽略檢查錯誤
}

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

    case 'debug_dump':
        // 上傳檔案，dump sheet 名稱與每張 sheet 前 25 列原始解析結果
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['tmp_name'])) {
            require_once __DIR__ . '/../includes/ExcelReader.php';
            $tmp = $_FILES['file']['tmp_name'];
            $names = ExcelReader::listSheets($tmp);
            header('Content-Type: text/html; charset=utf-8');
            echo '<style>body{font-family:sans-serif;padding:20px;}table{border-collapse:collapse;font-size:.85rem;margin:8px 0}td,th{border:1px solid #ccc;padding:4px 6px;vertical-align:top}h3{margin-top:24px}</style>';
            echo '<h2>MOA xlsx 解析 dump</h2>';
            echo '<p>Sheet 名稱：<code>' . implode(' | ', array_map('htmlspecialchars', $names)) . '</code></p>';
            foreach ($names as $sn) {
                echo '<h3>Sheet: ' . htmlspecialchars($sn) . '</h3>';
                $rows = ExcelReader::read($tmp, $sn);
                echo '<p>共 ' . count($rows) . ' 列，列出前 25 列：</p><table><thead><tr><th>#</th>';
                $maxC = 0; foreach (array_slice($rows, 0, 25) as $r) { $maxC = max($maxC, count($r)); }
                for ($c = 0; $c < $maxC; $c++) echo '<th>' . chr(65 + ($c % 26)) . '</th>';
                echo '</tr></thead><tbody>';
                foreach (array_slice($rows, 0, 25) as $i => $r) {
                    echo '<tr><td>' . ($i + 1) . '</td>';
                    for ($c = 0; $c < $maxC; $c++) echo '<td>' . htmlspecialchars($r[$c] ?? '') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            echo '<p><a href="/moa_attendance.php?action=debug_dump">回到上傳</a></p>';
            exit;
        }
        $pageTitle = 'MOA xlsx Debug';
        $currentPage = 'moa_attendance';
        require __DIR__ . '/../templates/layouts/header.php';
        ?>
        <h2>MOA xlsx Debug Dump</h2>
        <div class="card"><div style="padding:14px">
            <form method="POST" enctype="multipart/form-data">
                <p>上傳同一個 .xlsx，會 dump 出所有 sheet 名稱與前 25 列內容（純診斷用，不會寫入 DB）</p>
                <input type="file" name="file" accept=".xlsx" required>
                <button type="submit" class="btn btn-primary btn-sm">Dump</button>
            </form>
        </div></div>
        <?php
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
