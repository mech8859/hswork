<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/schedule/ScheduleModel.php';

$model = new ScheduleModel();
$action = $_GET['action'] ?? 'calendar';
$branchIds = Auth::getAccessibleBranchIds();
$allBranchIds = $branchIds; // 保留完整權限範圍

switch ($action) {
    // ---- 行事曆 ----
    case 'calendar':
        $year  = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('m'));
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        // 分公司篩選
        $filterBranchId = (int)($_GET['branch_id'] ?? 0);
        if ($filterBranchId && in_array($filterBranchId, $branchIds)) {
            $branchIds = array($filterBranchId);
        }

        // 分公司清單（篩選下拉用）
        $db = Database::getInstance();
        $branchPlaceholders = implode(',', array_fill(0, count($allBranchIds), '?'));
        $branchListStmt = $db->prepare("SELECT id, name FROM branches WHERE is_active = 1 AND id IN ({$branchPlaceholders}) ORDER BY id");
        $branchListStmt->execute($allBranchIds);
        $branchList = $branchListStmt->fetchAll(PDO::FETCH_ASSOC);

        // 人員篩選
        $filterUserId = (int)($_GET['user_id'] ?? 0);
        if ($filterUserId) {
            $schedules = $model->getByPerson($filterUserId, $startDate, $endDate);
        } else {
            $schedules = $model->getByDateRange($branchIds, $startDate, $endDate);
        }
        $visitWarnings = $model->getVisitWarnings($branchIds);

        // 按日期分組
        $schedulesByDate = [];
        foreach ($schedules as $s) {
            $schedulesByDate[$s['schedule_date']][] = $s;
        }

        // 容量資料（綠/黃/紅狀態用）
        $dailyCapacity = $model->getDailyCapacity($branchIds, $startDate, $endDate);
        $totalEngineers = $model->getTotalEngineers($branchIds);

        // 工程人員清單（人員篩選下拉用）
        require_once __DIR__ . '/../modules/staff/StaffModel.php';
        $staffModel = new StaffModel();
        $engineerList = $staffModel->getEngineers($branchIds);

        // 請假資料（行事曆上顯示休假人員）
        require_once __DIR__ . '/../modules/leaves/LeaveModel.php';
        $leaveModel = new LeaveModel();
        $leavesByDate = $leaveModel->getCalendarData(sprintf('%04d-%02d', $year, $month), $branchIds, array('engineer','eng_manager','eng_deputy'));

        // 排工日設定（每日是否開放、容量限制）
        $daySettings = $model->getDaySettings($startDate, $endDate);
        $dailyTeams = $model->getDailyTeamCount($branchIds, $startDate, $endDate);

        // 登入者ID（行事曆優先顯示用）
        $currentUserId = Auth::id();
        $isBoss = (Auth::user()['role'] === 'boss');

        $pageTitle = '工程行事曆';
        $currentPage = 'schedule';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/calendar.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增排工 ----
    case 'create':
        Auth::requirePermission('schedule.manage');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/schedule.php'); }

            // 虧損報價未簽核擋排工
            $checkCaseId = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
            if ($checkCaseId > 0) {
                $_lbStmt = Database::getInstance()->prepare("
                    SELECT quotation_number FROM quotations
                    WHERE case_id = ? AND profit_rate < 0
                      AND status IN ('draft','pending_approval','rejected_internal','revision_needed','pending_revision')
                    LIMIT 1
                ");
                $_lbStmt->execute(array($checkCaseId));
                $_lbRow = $_lbStmt->fetch(PDO::FETCH_ASSOC);
                if ($_lbRow) {
                    Session::flash('error', '報價單 ' . $_lbRow['quotation_number'] . ' 為虧損且尚未完成簽核，不可排工');
                    redirect('/cases.php?action=edit&id=' . $checkCaseId);
                }
            }

            // 排工條件檢查：無訂金 + 符合簽核規則 才需簽核通過
            if ($checkCaseId > 0) {
                $checkDb = Database::getInstance();
                $checkStmt = $checkDb->prepare("SELECT deposit_amount FROM cases WHERE id = ?");
                $checkStmt->execute(array($checkCaseId));
                $depositAmt = (float)$checkStmt->fetchColumn();

                if ($depositAmt <= 0) {
                    // 無訂金 → 依規則判斷是否需簽核
                    require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
                    $appModel = new ApprovalModel();
                    $needsApproval = $appModel->checkNoDepositNeedsApproval($checkCaseId);
                    if ($needsApproval) {
                        // 檢查是否已有簽核通過
                        $approvedStmt = $checkDb->prepare("SELECT COUNT(*) FROM approval_flows WHERE module = 'no_deposit_schedule' AND target_id = ? AND status = 'approved'");
                        $approvedStmt->execute(array($checkCaseId));
                        if ((int)$approvedStmt->fetchColumn() <= 0) {
                            Session::flash('error', '此案件無訂金且符合需簽核條件，請先申請無訂金排工簽核');
                            redirect('/schedule.php?action=create&case_id=' . $checkCaseId);
                            break;
                        }
                    }
                    // 不需簽核或已通過 → 放行
                }
            }

            // 收集施工日期（支援單日或批次多日）
            $scheduleDates = array();
            if (isset($_POST['schedule_dates']) && is_array($_POST['schedule_dates'])) {
                foreach ($_POST['schedule_dates'] as $d) {
                    $d = trim($d);
                    if ($d !== '' && !in_array($d, $scheduleDates, true)) {
                        $scheduleDates[] = $d;
                    }
                }
            }
            if (empty($scheduleDates) && !empty($_POST['schedule_date'])) {
                $scheduleDates[] = trim($_POST['schedule_date']);
            }
            if (empty($scheduleDates)) {
                Session::flash('error', '請選擇施工日期');
                redirect('/schedule.php?action=create' . ($checkCaseId > 0 ? '&case_id=' . $checkCaseId : ''));
                break;
            }
            sort($scheduleDates);

            $createdIds = array();
            foreach ($scheduleDates as $d) {
                $postForOne = $_POST;
                $postForOne['schedule_date'] = $d;
                $sid = $model->create($postForOne);
                $createdIds[] = $sid;
                AuditLog::log('schedule', 'create', $sid, $d . ' 排工');
            }

            // 自動更新案件階段為 5 (已排工/已排行事曆) — 僅需執行一次
            $caseIdForStage = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
            if ($caseIdForStage > 0) {
                $stageDb = Database::getInstance();
                $stageStmt = $stageDb->prepare("SELECT stage FROM cases WHERE id = ?");
                $stageStmt->execute(array($caseIdForStage));
                $curStage = (int)$stageStmt->fetchColumn();
                if ($curStage >= 4 && $curStage < 5) {
                    $stageDb->prepare("UPDATE cases SET stage = 5, status = 'scheduled' WHERE id = ?")
                            ->execute(array($caseIdForStage));
                }
            }

            if (count($createdIds) > 1) {
                Session::flash('success', '已批次建立 ' . count($createdIds) . ' 筆排工（' . implode('、', $scheduleDates) . '）');
                redirect('/schedule.php');
            } else {
                Session::flash('success', '排工已建立');
                redirect('/schedule.php?action=view&id=' . $createdIds[0]);
            }
        }

        $date = $_GET['date'] ?? date('Y-m-d');
        $cases = $model->getSchedulableCases($branchIds);
        $vehicles = $model->getAvailableVehicles($date, $branchIds);
        $dispatch_workers = $model->getDispatchWorkers($date);

        // 如果有指定案件，取得推薦工程師
        $caseId = (int)($_GET['case_id'] ?? 0);
        $engineers = [];
        if ($caseId) {
            $engineers = $model->getAvailableEngineers($date, $caseId, $branchIds);
        }

        $pageTitle = '新增排工';
        $currentPage = 'schedule';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 排工詳情 ----
    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        $schedule = $model->getById($id);
        if (!$schedule) { Session::flash('error', '排工不存在'); redirect('/schedule.php'); }

        // 載入施工回報
        require_once __DIR__ . '/../modules/schedule/WorklogModel.php';
        $worklogModel = new WorklogModel();
        $worklogs = $worklogModel->getBySchedule($id);

        // 準備當前使用者的 worklog（直接在排工頁顯示表單）
        $currentUserId = Auth::id();

        // 找當前使用者最新的未完成 worklog（沒有施工內容的空白記錄）
        $myWorklog = null;
        $myWorklogs = array(); // 當前使用者的所有歷史回報
        foreach ($worklogs as $wl) {
            if ((int)$wl['user_id'] === $currentUserId) {
                $myWorklogs[] = $wl;
                // 找空白的（還沒填的）作為表單
                if (!$myWorklog && empty($wl['work_description'])) {
                    $myWorklog = $wl;
                }
            }
        }
        // 如果沒有空白的，建立一筆新的
        if (!$myWorklog) {
            $myWlId = $worklogModel->createBlank($id, $currentUserId);
            $myWorklog = $worklogModel->getWorklog($myWlId);
        }
        // 歷史回報（排除當前正在填的空白記錄）
        $historyWorklogs = array();
        foreach ($worklogs as $wl) {
            if ($wl['id'] != $myWorklog['id'] && !empty($wl['work_description'])) {
                $historyWorklogs[] = $wl;
            }
        }

        $pageTitle = '排工詳情';
        $currentPage = 'schedule';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯排工 ----
    case 'edit':
        Auth::requirePermission('schedule.manage');
        $id = (int)($_GET['id'] ?? 0);
        $schedule = $model->getById($id);
        if (!$schedule) { Session::flash('error', '排工不存在'); redirect('/schedule.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/schedule.php?action=edit&id='.$id); }
            AuditLog::logChange('schedule', $id, $schedule['schedule_date'] . ' ' . ($schedule['case_title'] ?? ''), $schedule, $_POST, array('schedule_date','case_id','vehicle_id','status','note'));
            $model->update($id, $_POST);
            Session::flash('success', '排工已更新');
            redirect('/schedule.php?action=view&id=' . $id);
        }

        $date = $schedule['schedule_date'];
        $cases = $model->getSchedulableCases($branchIds);
        $vehicles = $model->getAvailableVehicles($date, $branchIds);
        $engineers = $model->getAvailableEngineers($date, (int)$schedule['case_id'], $branchIds);
        $dispatch_workers = $model->getDispatchWorkers($date);

        $pageTitle = '編輯排工';
        $currentPage = 'schedule';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- AJAX: 即時搜尋可排工案件（涵蓋待安排查修/成交待排工/已排工/需再安排）----
    case 'ajax_search_cases':
        header('Content-Type: application/json; charset=utf-8');
        $keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
        $db = Database::getInstance();
        $ph = implode(',', array_fill(0, count($branchIds), '?'));

        $where = "c.branch_id IN ($ph) AND (c.status = 'awaiting_dispatch' OR c.stage IN (4,5,6))";
        $params = $branchIds;

        if ($keyword !== '') {
            $kw = '%' . $keyword . '%';
            $where .= " AND (c.case_number LIKE ? OR c.title LIKE ? OR c.customer_name LIKE ? OR c.address LIKE ?)";
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $sql = "SELECT c.id, c.case_number, c.title, c.customer_name, c.address,
                       c.stage, c.status, c.case_type, c.planned_start_time,
                       b.name AS branch_name
                FROM cases c
                LEFT JOIN branches b ON c.branch_id = b.id
                WHERE {$where}
                ORDER BY
                  CASE
                    WHEN c.status = 'awaiting_dispatch' THEN 0
                    WHEN c.stage = 4 THEN 1
                    WHEN c.stage = 6 THEN 2
                    WHEN c.stage = 5 THEN 3
                    ELSE 9
                  END,
                  c.updated_at DESC
                LIMIT 30";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $statusMap = array(
            'awaiting_dispatch' => array('label' => '待安排查修', 'color' => '#7e57c2'),
        );
        $stageMap = array(
            4 => array('label' => '成交待排工', 'color' => '#2e7d32'),
            5 => array('label' => '已排工/已排行事曆', 'color' => '#1976d2'),
            6 => array('label' => '已進場/需再安排', 'color' => '#e53935'),
        );

        $data = array();
        foreach ($rows as $r) {
            $tagLabel = '';
            $tagColor = '#777';
            if ($r['status'] === 'awaiting_dispatch') {
                $tagLabel = $statusMap['awaiting_dispatch']['label'];
                $tagColor = $statusMap['awaiting_dispatch']['color'];
            } elseif (isset($stageMap[(int)$r['stage']])) {
                $tagLabel = $stageMap[(int)$r['stage']]['label'];
                $tagColor = $stageMap[(int)$r['stage']]['color'];
            }
            $data[] = array(
                'id'            => (int)$r['id'],
                'case_number'   => $r['case_number'],
                'title'         => $r['title'],
                'customer_name' => $r['customer_name'],
                'address'       => $r['address'],
                'branch_name'   => $r['branch_name'],
                'tag_label'     => $tagLabel,
                'tag_color'     => $tagColor,
            );
        }

        echo json_encode(array('data' => $data), JSON_UNESCAPED_UNICODE);
        exit;

    // ---- AJAX: 取得可用工程師 ----
    case 'ajax_engineers':
        $date = $_GET['date'] ?? date('Y-m-d');
        $caseId = (int)($_GET['case_id'] ?? 0);
        if (!$caseId) json_response(['error' => 'Missing case_id'], 400);
        // 支援多日查詢：dates=2026-04-15,2026-04-16,2026-04-17
        $extraDates = array();
        if (!empty($_GET['dates'])) {
            $extraDates = array_filter(array_map('trim', explode(',', $_GET['dates'])));
        }
        $engineers = $model->getAvailableEngineers($date, $caseId, $branchIds, $extraDates);
        json_response(['data' => $engineers]);
        break;

    // ---- AJAX: 取得可用車輛 ----
    case 'ajax_vehicles':
        $date = $_GET['date'] ?? date('Y-m-d');
        $vehicles = $model->getAvailableVehicles($date, $branchIds);
        json_response(['data' => $vehicles]);
        break;

    // ---- AJAX: 取得可用點工人員（依日期過濾） ----
    case 'ajax_dispatch_workers':
        $date = $_GET['date'] ?? date('Y-m-d');
        $workers = $model->getDispatchWorkers($date);
        json_response(array('data' => $workers));
        break;

    // ---- 智慧排工 ----
    case 'smart':
        Auth::requirePermission('schedule.manage');
        $caseId = (int)($_GET['case_id'] ?? 0);
        if (!$caseId) { Session::flash('error', '請指定案件'); redirect('/cases.php'); }

        // 虧損報價未簽核擋排工
        $_lbStmt = Database::getInstance()->prepare("
            SELECT quotation_number FROM quotations
            WHERE case_id = ? AND profit_rate < 0
              AND status IN ('draft','pending_approval','rejected_internal','revision_needed','pending_revision')
            LIMIT 1
        ");
        $_lbStmt->execute(array($caseId));
        $_lbRow = $_lbStmt->fetch(PDO::FETCH_ASSOC);
        if ($_lbRow) {
            Session::flash('error', '報價單 ' . $_lbRow['quotation_number'] . ' 為虧損且尚未完成簽核，不可智慧排工');
            redirect('/cases.php?action=edit&id=' . $caseId);
        }

        require_once __DIR__ . '/../modules/cases/CaseModel.php';
        $caseModel = new CaseModel();
        $case = $caseModel->getById($caseId);
        if (!$case) { Session::flash('error', '案件不存在'); redirect('/cases.php'); }

        $recommendations = $model->getSmartRecommendations($caseId, $branchIds);

        $pageTitle = '智慧排工 - ' . $case['case_number'];
        $currentPage = 'schedule';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/smart.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 智慧排工套用 ----
    case 'smart_apply':
        Auth::requirePermission('schedule.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Invalid method'], 405);
        }
        if (!verify_csrf()) {
            json_response(['error' => '安全驗證失敗'], 403);
        }

        $data = [
            'case_id'              => (int)$_POST['case_id'],
            'schedule_date'        => $_POST['schedule_date'],
            'vehicle_id'           => (int)($_POST['vehicle_id'] ?? 0) ?: null,
            'visit_number'         => (int)($_POST['visit_number'] ?? 1),
            'engineer_ids'         => $_POST['engineer_ids'] ?? [],
            'dispatch_worker_ids'  => $_POST['dispatch_worker_ids'] ?? [],
            'lead_engineer_id'     => (int)($_POST['lead_engineer_id'] ?? 0),
            'status'               => 'planned',
            'note'                 => '由智慧排工建立',
        ];

        $scheduleId = $model->create($data);

        // 自動更新案件階段為 5 (已排工/已排行事曆)
        $smartCaseId = (int)$data['case_id'];
        if ($smartCaseId > 0) {
            $stageDb = Database::getInstance();
            $stageStmt = $stageDb->prepare("SELECT stage FROM cases WHERE id = ?");
            $stageStmt->execute(array($smartCaseId));
            $curStage = (int)$stageStmt->fetchColumn();
            if ($curStage >= 4 && $curStage < 5) {
                $stageDb->prepare("UPDATE cases SET stage = 5, status = 'scheduled' WHERE id = ?")
                        ->execute(array($smartCaseId));
            }
        }

        json_response(['success' => true, 'schedule_id' => $scheduleId, 'redirect' => '/schedule.php?action=view&id=' . $scheduleId]);
        break;

    // ---- AJAX: 儲存排工日設定（管理者用） ----
    case 'save_day_setting':
        header('Content-Type: application/json');
        if (Auth::user()['role'] !== 'boss') {
            echo json_encode(array('error' => '僅系統管理者可操作'));
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法不允許'));
            exit;
        }
        $date = isset($_POST['date']) ? $_POST['date'] : '';
        if (!$date) {
            echo json_encode(array('error' => '缺少日期'));
            exit;
        }
        $model->saveDaySetting($date, array(
            'is_open' => isset($_POST['is_open']) ? $_POST['is_open'] : 1,
            'max_teams' => isset($_POST['max_teams']) ? $_POST['max_teams'] : '',
            'max_engineers' => isset($_POST['max_engineers']) ? $_POST['max_engineers'] : '',
            'note' => isset($_POST['note']) ? $_POST['note'] : '',
            'updated_by' => Auth::id(),
        ));
        echo json_encode(array('success' => true));
        exit;

    // ---- AJAX: 設定當日休假/不可排工 ----
    case 'set_day_leave':
        header('Content-Type: application/json');
        Auth::requirePermission('schedule.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法不允許'));
            exit;
        }
        $date = isset($_POST['date']) ? $_POST['date'] : '';
        $userIds = isset($_POST['user_ids']) ? $_POST['user_ids'] : array();
        if (!$date) {
            echo json_encode(array('error' => '缺少日期'));
            exit;
        }

        require_once __DIR__ . '/../modules/leaves/LeaveModel.php';
        $leaveModel = new LeaveModel();
        $branchIds = Auth::getAccessibleBranchIds();

        // 取得當日目前已有休假的人員
        $currentOnLeave = $leaveModel->getEngineersOnLeave($date, $branchIds);

        // 需要新增的（勾選了但目前沒休假）
        $toAdd = array_diff($userIds, $currentOnLeave);
        // 需要取消的（目前有休假但取消勾選）
        $toRemove = array_diff($currentOnLeave, $userIds);

        // 新增休假
        if (!empty($toAdd)) {
            $leaveModel->createBatch($date, $toAdd, 'official');
        }
        // 取消休假
        foreach ($toRemove as $uid) {
            $leaveModel->cancelLeaveOnDate($uid, $date);
        }

        echo json_encode(array('success' => true, 'added' => count($toAdd), 'removed' => count($toRemove)));
        exit;

    // ---- 刪除排工 ----
    case 'delete':
        if (!Auth::hasPermission('schedule.delete') && !Auth::hasPermission('all')) {
            Session::flash('error', '權限不足，無法刪除排工');
            redirect('/schedule.php');
        }
        if (!verify_csrf()) {
            Session::flash('error', '安全驗證失敗，請重試');
            redirect('/schedule.php');
        }
        $delId = (int)($_GET['id'] ?? 0);
        if (!$delId) {
            Session::flash('error', '缺少排工ID');
            redirect('/schedule.php');
        }
        $delSchedule = $model->getById($delId);
        if (!$delSchedule) {
            Session::flash('error', '排工不存在');
            redirect('/schedule.php');
        }
        try {
            AuditLog::log('schedule', 'delete', $delId, $delSchedule['schedule_date'] . ' ' . ($delSchedule['case_title'] ?? ''));
            $model->delete($delId);
            Session::flash('success', '排工已刪除');
        } catch (Exception $ex) {
            Session::flash('error', '刪除失敗：' . $ex->getMessage());
        }
        redirect('/schedule.php');
        break;

    // ---- 參加排工（工程人員自行加入）----
    case 'join':
        $joinId = (int)($_GET['id'] ?? 0);
        if (!$joinId || !verify_csrf()) {
            json_response(array('error' => '參數錯誤'), 400);
        }
        $joinSchedule = $model->getById($joinId);
        if (!$joinSchedule) {
            json_response(array('error' => '排工不存在'), 404);
        }
        if (in_array($joinSchedule['status'], array('cancelled', 'completed'))) {
            json_response(array('error' => '此排工已完成或取消，無法參加'));
        }
        $userId = Auth::id();
        // 檢查是否已在名單中
        $existingEngIds = array_column($joinSchedule['engineers'], 'user_id');
        if (in_array($userId, $existingEngIds)) {
            json_response(array('error' => '你已在此排工名單中'));
        }
        $db = Database::getInstance();
        try {
            // 加入 schedule_engineers（標記支援）
            $stmt = $db->prepare("INSERT INTO schedule_engineers (schedule_id, user_id, is_support) VALUES (?, ?, 1)");
            $stmt->execute(array($joinId, $userId));
            // 自動建立空白 worklog
            require_once __DIR__ . '/../modules/schedule/WorklogModel.php';
            $wlModel = new WorklogModel();
            $wlModel->createBlank($joinId, $userId);
            json_response(array('success' => true));
        } catch (Exception $e) {
            json_response(array('error' => '加入失敗：' . $e->getMessage()));
        }
        break;

    // ---- 施工回報（自動建立 worklog 並跳轉） ----
    case 'worklog_report':
        $scheduleId = (int)($_GET['id'] ?? 0);
        $schedule = $model->getById($scheduleId);
        if (!$schedule) { Session::flash('error', '排工不存在'); redirect('/schedule.php'); }

        require_once __DIR__ . '/../modules/schedule/WorklogModel.php';
        $wlModel = new WorklogModel();
        $userId = Auth::id();

        // 找現有的 worklog
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM work_logs WHERE schedule_id = ? AND user_id = ?');
        $stmt->execute(array($scheduleId, $userId));
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            redirect('/worklog.php?action=report&id=' . $existingId . '&from_schedule=' . $scheduleId);
        } else {
            // 自動建立 worklog（不打卡，只建記錄）
            $wlId = $wlModel->createBlank($scheduleId, $userId);
            redirect('/worklog.php?action=report&id=' . $wlId . '&from_schedule=' . $scheduleId);
        }
        break;

    default:
        redirect('/schedule.php');
}
