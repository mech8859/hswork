<?php
/**
 * MOA 雲考勤資料管理（簽到/簽退/異常）
 * 與既有 attendance.php（工程人員出勤行事曆）為不同功能
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../modules/attendance/AttendanceModel.php';

// cron_sync 用 token 驗證，跳過 session 登入；其餘 action 走正常登入
$_isCron = (($_GET['action'] ?? '') === 'cron_sync') && !empty($_GET['token']);
if (!$_isCron) {
    Auth::requireLogin();
    if (!Auth::hasPermission('attendance.manage') && !Auth::hasPermission('attendance.view') && !Auth::hasPermission('all')) {
        Session::flash('error', '無考勤管理權限');
        redirect('/index.php');
    }
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

    case 'sync_config':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/moa_attendance.php?action=sync_config'); }
            if (!Auth::hasPermission('attendance.manage') && !Auth::hasPermission('all')) {
                Session::flash('error', '無權限'); redirect('/moa_attendance.php?action=sync_config');
            }
            $companyId = (int)($_POST['moa_company_id'] ?? 4545);
            $orgId     = (int)($_POST['moa_org_id'] ?? 200021);
            $cookie    = trim($_POST['moa_cookie'] ?? '');
            // 空字串不覆寫 cookie
            $model->saveSettings($companyId, $orgId, $cookie !== '' ? $cookie : null);
            Session::flash('success', '同步設定已儲存');
            redirect('/moa_attendance.php?action=sync_config');
        }
        $settings = $model->getSettings();
        $pageTitle = 'MOA 同步設定';
        $currentPage = 'moa_attendance';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/attendance/sync_config.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'regen_token':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/moa_attendance.php?action=sync_config'); }
        if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/moa_attendance.php?action=sync_config'); }
        if (!Auth::hasPermission('attendance.manage') && !Auth::hasPermission('all')) {
            Session::flash('error', '無權限'); redirect('/moa_attendance.php?action=sync_config');
        }
        $newTok = $model->regenerateCronToken();
        Session::flash('success', 'Cron token 已重新產生：' . substr($newTok, 0, 8) . '...');
        redirect('/moa_attendance.php?action=sync_config');
        break;

    case 'sync_now':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/moa_attendance.php?action=sync_config'); }
        if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/moa_attendance.php?action=sync_config'); }
        if (!Auth::hasPermission('attendance.manage') && !Auth::hasPermission('all')) {
            Session::flash('error', '無權限'); redirect('/moa_attendance.php?action=sync_config');
        }
        $df = $_POST['date_from'] ?? date('Y-m-d', strtotime('-1 day'));
        $dt = $_POST['date_to']   ?? date('Y-m-d');
        set_time_limit(180);
        $stats = $model->syncFromApi($df, $dt);
        if ($stats['ok']) {
            $msg = '同步完成：員工 ' . $stats['employees'] . '，打卡原始 ' . $stats['records']
                 . '，員工日 ' . $stats['days'] . '（新 ' . $stats['inserted'] . '、更新 ' . $stats['updated'] . '）';
            if ($stats['unmatched'] > 0) $msg .= '，未對應姓名 ' . $stats['unmatched'];
            if (!empty($stats['errors'])) $msg .= '，部分員工失敗 ' . count($stats['errors']) . ' 筆（' . substr(implode(' / ', $stats['errors']), 0, 200) . '）';
            Session::flash('success', $msg);
            AuditLog::log('attendance', 'api_sync', 0, $msg);
        } else {
            Session::flash('error', '同步失敗：' . implode('；', $stats['errors']));
        }
        redirect('/moa_attendance.php?action=sync_config');
        break;

    case 'cron_sync':
        // CRON 用：純文字輸出，token 驗證（跳過登入 session）
        header('Content-Type: text/plain; charset=utf-8');
        $_token = $_GET['token'] ?? '';
        $_set = $model->getSettings();
        if (empty($_set['cron_token']) || !hash_equals((string)$_set['cron_token'], (string)$_token)) {
            http_response_code(403);
            echo "FORBIDDEN: token 不正確\n";
            exit;
        }
        $df = $_GET['date_from'] ?? date('Y-m-d', strtotime('-1 day'));
        $dt = $_GET['date_to']   ?? date('Y-m-d', strtotime('-1 day'));
        set_time_limit(180);
        $stats = $model->syncFromApi($df, $dt);
        echo "=== MOA 同步 ===\n";
        echo "區間：$df ~ $dt\n";
        echo "結果：" . ($stats['ok'] ? 'OK' : 'FAILED') . "\n";
        echo "員工：" . $stats['employees'] . "\n";
        echo "打卡原始：" . $stats['records'] . "\n";
        echo "員工日：" . $stats['days'] . "（新 " . $stats['inserted'] . " 更新 " . $stats['updated'] . "）\n";
        echo "未對應姓名：" . $stats['unmatched'] . "\n";
        if (!empty($stats['errors'])) {
            echo "錯誤：\n";
            foreach ($stats['errors'] as $e) echo "  - $e\n";
        }
        exit;

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

        $db = Database::getInstance();
        // 連動 leaves / overtimes
        $leaveMap = array();
        $overtimeMap = array();

        // 先撈日期區間內所有「已核准」請假（不限定 user_id，因為整天請假沒打卡的人不會出現在 records）
        $lvAllStmt = $db->prepare("
            SELECT l.user_id, l.leave_type, l.start_date, l.end_date,
                   u.real_name AS hswork_name,
                   ae.moa_name, ae.moa_dept
            FROM leaves l
            JOIN users u ON l.user_id = u.id
            LEFT JOIN attendance_employees ae ON ae.user_id = u.id
            WHERE l.status = 'approved'
              AND l.start_date <= ? AND l.end_date >= ?
        ");
        $lvAllStmt->execute(array($filters['date_to'], $filters['date_from']));
        $leaveRows = $lvAllStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($leaveRows as $lv) {
            $startTs = strtotime(max($lv['start_date'], $filters['date_from']));
            $endTs   = strtotime(min($lv['end_date'],   $filters['date_to']));
            for ($t = $startTs; $t <= $endTs; $t += 86400) {
                $leaveMap[$lv['user_id'] . '|' . date('Y-m-d', $t)] = $lv['leave_type'];
            }
        }

        // 加班（已核准；單日）
        $otAllStmt = $db->prepare("
            SELECT o.user_id, o.overtime_date, o.hours, o.overtime_type,
                   u.real_name AS hswork_name,
                   ae.moa_name, ae.moa_dept
            FROM overtimes o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN attendance_employees ae ON ae.user_id = u.id
            WHERE o.status = 'approved'
              AND o.overtime_date BETWEEN ? AND ?
        ");
        $otAllStmt->execute(array($filters['date_from'], $filters['date_to']));
        $overtimeRows = $otAllStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($overtimeRows as $ot) {
            $overtimeMap[$ot['user_id'] . '|' . $ot['overtime_date']] = array(
                'hours' => $ot['hours'],
                'type'  => $ot['overtime_type'],
            );
        }

        // 國定假日 map
        $holStmt = $db->prepare("SELECT holiday_date, name, is_workday FROM holidays WHERE holiday_date BETWEEN ? AND ?");
        $holStmt->execute(array($filters['date_from'], $filters['date_to']));
        $holidayMap = array();
        foreach ($holStmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $holidayMap[$h['holiday_date']] = $h;
        }

        // 收集這個區間內「曾經出現」的員工（打卡或請假任一）
        $existingKeys = array();
        $userSet = array();
        foreach ($records as $r) {
            if (!empty($r['user_id'])) {
                $existingKeys[$r['user_id'] . '|' . $r['work_date']] = true;
                $userSet[(int)$r['user_id']] = array(
                    'name' => $r['moa_name'],
                    'dept' => $r['moa_dept'],
                    'hswork_name' => $r['hswork_name'] ?? '',
                );
            }
        }
        foreach ($leaveRows as $lv) {
            $uid = (int)$lv['user_id'];
            if (!isset($userSet[$uid])) {
                $userSet[$uid] = array(
                    'name' => $lv['moa_name'] ?: $lv['hswork_name'],
                    'dept' => $lv['moa_dept'] ?: '',
                    'hswork_name' => $lv['hswork_name'],
                );
            }
        }

        // 展開「員工 × 日期區間」全部的列；缺打卡的列依優先序標：請假 > 國定假日 > 週末 > 未簽
        $weekdays = array('日','一','二','三','四','五','六');
        $startTs = strtotime($filters['date_from']);
        $endTs   = strtotime($filters['date_to']);
        foreach ($userSet as $uid => $info) {
            for ($t = $startTs; $t <= $endTs; $t += 86400) {
                $d = date('Y-m-d', $t);
                $key = $uid . '|' . $d;
                if (isset($existingKeys[$key])) continue;
                $name = $info['name'] ?: '';
                $dept = $info['dept'] ?: '';
                if (!empty($filters['name']) && $name !== $filters['name']) continue;
                if (!empty($filters['dept']) && $dept !== $filters['dept']) continue;
                if (!empty($filters['only_abnormal'])) continue;
                if (!empty($filters['unmatched'])) continue;

                $weekdayNum = (int)date('w', $t);
                $isWeekend = ($weekdayNum === 0 || $weekdayNum === 6);
                $hasLeave = isset($leaveMap[$key]);
                $holiday = isset($holidayMap[$d]) ? $holidayMap[$d] : null;

                if ($hasLeave) {
                    $status = '請假'; $rowType = 'leave';
                } elseif ($holiday && empty($holiday['is_workday'])) {
                    $status = $holiday['name']; $rowType = 'holiday';
                } elseif ($isWeekend) {
                    $status = $weekdayNum === 0 ? '週日' : '週六'; $rowType = 'weekend';
                } else {
                    $status = '未簽'; $rowType = 'no_record';
                }

                $records[] = array(
                    'id' => null,
                    'user_id' => $uid,
                    'moa_name' => $name,
                    'moa_employee_no' => null,
                    'moa_dept' => $dept,
                    'work_date' => $d,
                    'weekday' => '周' . $weekdays[$weekdayNum],
                    'is_abnormal' => 0,
                    'has_application' => $hasLeave ? 1 : 0,
                    'expected_minutes' => null,
                    'actual_minutes' => null,
                    'sign_in_time' => null,
                    'sign_out_time' => null,
                    'sign_in_status' => $status,
                    'sign_out_status' => $status,
                    'late_minutes' => null,
                    'early_leave_minutes' => null,
                    'absent_minutes' => null,
                    'hswork_name' => $info['hswork_name'],
                    '_synthetic' => true,
                    '_row_type' => $rowType,
                    '_holiday_name' => $holiday['name'] ?? null,
                );
                $existingKeys[$key] = true;
            }
        }
        // 重新排序：日期 DESC、部門、姓名
        usort($records, function ($a, $b) {
            $c = strcmp($b['work_date'], $a['work_date']);
            if ($c !== 0) return $c;
            $c = strcmp($a['moa_dept'] ?? '', $b['moa_dept'] ?? '');
            if ($c !== 0) return $c;
            return strcmp($a['moa_name'] ?? '', $b['moa_name'] ?? '');
        });
        $pageTitle = 'MOA 考勤明細';
        $currentPage = 'moa_attendance';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/attendance/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;
}
