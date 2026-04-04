<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/cases/CaseModel.php';

$model = new CaseModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// 權限檢查
if (!Auth::hasPermission('engineering_tracking.manage') && !Auth::hasPermission('engineering_tracking.view') && !Auth::hasPermission('engineering_tracking.own')) {
    Session::flash('error', '權限不足');
    redirect('/');
}

$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    // ---- 看板/列表 ----
    case 'list':
        $user = Auth::user();
        $engRoles = array('engineer');
        $engMgrRoles = array('eng_manager', 'eng_deputy');
        $isEngineer = in_array($user['role'], $engRoles);
        $isEngMgr = in_array($user['role'], $engMgrRoles);
        $isFirstVisit = !isset($_GET['engineer_id']) && !isset($_GET['branch_id']) && !isset($_GET['case_type']) && !isset($_GET['keyword']) && !isset($_GET['stage']) && !isset($_GET['all']);

        // 預設值：工程師→自己的案件；工程主管→分公司；boss/manager→全部
        $defaultEngineerId = '';
        $defaultBranchId = '';
        if ($isFirstVisit) {
            if ($isEngineer) {
                $defaultEngineerId = Auth::id();
            } elseif ($isEngMgr) {
                $defaultBranchId = $user['branch_id'];
            }
        }

        $filters = array(
            'engineer_id' => isset($_GET['engineer_id']) ? $_GET['engineer_id'] : $defaultEngineerId,
            'branch_id'   => isset($_GET['branch_id']) ? $_GET['branch_id'] : $defaultBranchId,
            'case_type'   => isset($_GET['case_type']) ? $_GET['case_type'] : '',
            'keyword'     => isset($_GET['keyword']) ? $_GET['keyword'] : '',
            'stage'       => isset($_GET['stage']) ? $_GET['stage'] : '',
        );

        // 僅自己的（engineer 角色且無 manage/view 權限）
        if (Auth::hasPermission('engineering_tracking.own') && !Auth::hasPermission('engineering_tracking.manage') && !Auth::hasPermission('engineering_tracking.view')) {
            $filters['engineer_id'] = Auth::id();
        }

        $cases = $model->getEngineeringTrackingList($filters);
        $stats = $model->getEngineeringStageStats($filters);
        $engineers = $model->getEngineers();
        $branches = $model->getBranches();

        // 取得「已進場/需再安排」案件的下次施工日
        $nextVisitMap = array();
        $db = Database::getInstance();
        $nvStmt = $db->query("
            SELECT s.case_id, wl.next_visit_date
            FROM work_logs wl
            JOIN schedules s ON wl.schedule_id = s.id
            WHERE wl.next_visit_date IS NOT NULL
              AND wl.is_completed = 0
            ORDER BY wl.id DESC
        ");
        foreach ($nvStmt->fetchAll(PDO::FETCH_ASSOC) as $nv) {
            if (!isset($nextVisitMap[$nv['case_id']])) {
                $nextVisitMap[$nv['case_id']] = $nv['next_visit_date'];
            }
        }

        $pageTitle = '工程追蹤表';
        $currentPage = 'engineering_tracking';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/engineering_tracking/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 檢視詳情 → 直接跳轉到案件管理 ----
    case 'view':
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        redirect('/cases.php?action=edit&id=' . $id . '&from=engineering');
        break;

    // ---- 更新階段 (AJAX) ----
    case 'update_stage':
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('ok' => false, 'msg' => 'Invalid method'));
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $id = (int)(isset($input['id']) ? $input['id'] : 0);
        $stage = (int)(isset($input['stage']) ? $input['stage'] : 0);

        if ($id <= 0 || $stage < 4 || $stage > 7) {
            echo json_encode(array('ok' => false, 'msg' => '參數錯誤'));
            exit;
        }

        $case = $model->getById($id);
        if (!$case) {
            echo json_encode(array('ok' => false, 'msg' => '案件不存在'));
            exit;
        }

        // own 權限檢查
        if (Auth::hasPermission('engineering_tracking.own') && !Auth::hasPermission('engineering_tracking.manage')) {
            if (!$model->isEngineerAssigned($id, Auth::id())) {
                echo json_encode(array('ok' => false, 'msg' => '權限不足'));
                exit;
            }
        }

        $data = array();
        if (isset($input['deal_amount'])) { $data['deal_amount'] = $input['deal_amount']; }

        $model->updateStage($id, $stage, $data);
        $labels = CaseModel::stageLabels();
        echo json_encode(array('ok' => true, 'msg' => '已更新為：' . (isset($labels[$stage]) ? $labels[$stage] : $stage)));
        exit;

    default:
        redirect('/engineering_tracking.php');
}
