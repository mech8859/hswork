<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/cases/CaseModel.php';

$model = new CaseModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// 權限檢查
if (!Auth::hasPermission('business_tracking.manage') && !Auth::hasPermission('business_tracking.view') && !Auth::hasPermission('business_tracking.own')) {
    Session::flash('error', '權限不足');
    redirect('/');
}

$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    // ---- 看板/列表 ----
    case 'list':
        $user = Auth::user();
        $salesRoles = array('sales', 'sales_manager', 'sales_assistant');
        $isSalesRole = in_array($user['role'], $salesRoles);
        $isFirstVisit = !isset($_GET['sales_id']) && !isset($_GET['branch_id']) && !isset($_GET['case_type']) && !isset($_GET['case_source']) && !isset($_GET['keyword']) && !isset($_GET['stage']) && !isset($_GET['all']);

        // 預設值：業務→預設自己；非業務→預設所屬分公司（管理處級別預設全部）
        $defaultSalesId = '';
        $defaultBranchId = '';
        if ($isFirstVisit) {
            if ($isSalesRole) {
                $defaultSalesId = Auth::id();
            } else {
                // 管理處/管理部/總公司 預設看全部
                $branchName = '';
                if ($user['branch_id']) {
                    $brStmt = Database::getInstance()->prepare("SELECT name FROM branches WHERE id = ?");
                    $brStmt->execute(array($user['branch_id']));
                    $br = $brStmt->fetch(PDO::FETCH_ASSOC);
                    $branchName = $br ? $br['name'] : '';
                }
                if (strpos($branchName, '管理處') !== false || strpos($branchName, '管理部') !== false || strpos($branchName, '總公司') !== false) {
                    $defaultBranchId = '';
                } else {
                    $defaultBranchId = $user['branch_id'];
                }
            }
        }

        $filters = array(
            'sales_id'    => isset($_GET['sales_id']) ? $_GET['sales_id'] : $defaultSalesId,
            'branch_id'   => isset($_GET['branch_id']) ? $_GET['branch_id'] : $defaultBranchId,
            'case_type'   => isset($_GET['case_type']) ? $_GET['case_type'] : '',
            'case_source' => isset($_GET['case_source']) ? $_GET['case_source'] : '',
            'keyword'     => isset($_GET['keyword']) ? $_GET['keyword'] : '',
            'start_date'  => isset($_GET['start_date']) ? $_GET['start_date'] : '',
            'end_date'    => isset($_GET['end_date']) ? $_GET['end_date'] : '',
            'stage'       => isset($_GET['stage']) ? $_GET['stage'] : '',
        );

        // 僅自己的
        if (Auth::hasPermission('business_tracking.own') && !Auth::hasPermission('business_tracking.manage') && !Auth::hasPermission('business_tracking.view')) {
            $filters['sales_id'] = Auth::id();
        }

        $cases = $model->getSalesTrackingList($filters);
        $stats = $model->getStageStats($filters);
        $salespeople = $model->getSalespeople();
        $branches = $model->getBranches();

        $pageTitle = '業務追蹤表';
        $currentPage = 'business_tracking';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/business_tracking/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增進件 ----
    case 'create':
        if (!Auth::hasPermission('business_tracking.manage') && !Auth::hasPermission('business_tracking.own')) {
            Session::flash('error', '權限不足');
            redirect('/business_tracking.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/business_tracking.php?action=create');
            }
            $caseId = $model->createSalesCase($_POST);

            // 通知分派
            require_once __DIR__ . '/../modules/notifications/NotificationDispatcher.php';
            $dispatchData = array_merge($_POST, array('id' => $caseId));
            NotificationDispatcher::dispatch('business_tracking', 'created', $dispatchData, Auth::id());

            Session::flash('success', '進件已新增');
            redirect('/business_tracking.php?action=view&id=' . $caseId);
        }

        $salespeople = $model->getSalespeople();
        $branches = $model->getBranches();

        $pageTitle = '新增進件';
        $currentPage = 'business_tracking';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/business_tracking/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯 ----
    case 'edit':
        $cu = Auth::user();
        $isAdmin = in_array($cu['role'], array('boss', 'manager', 'vice_president'));
        $hasManage = Auth::hasPermission('business_tracking.manage');
        $hasView = Auth::hasPermission('business_tracking.view') || $hasManage;
        $hasOwn = Auth::hasPermission('business_tracking.own');

        if (!$isAdmin && !$hasManage && !$hasView && !$hasOwn) {
            Session::flash('error', '權限不足');
            redirect('/business_tracking.php');
        }

        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $case = $model->getById($id);
        if (!$case) {
            Session::flash('error', '案件不存在');
            redirect('/business_tracking.php');
        }

        $isOwn = ((int)$case['sales_id'] === (int)Auth::id());

        // 判定可否編輯（高層 / manage 一律可編；own 權限只能編自己；view 權限不能編）
        if ($isAdmin || $hasManage) {
            $canEdit = true;
        } elseif ($hasOwn) {
            $canEdit = $isOwn;
        } else {
            $canEdit = false;
        }

        // 判定可否檢視（own 只能看自己的；view/manage 都能看）
        if ($isAdmin || $hasManage || $hasView) {
            $canView = true;
        } elseif ($hasOwn) {
            $canView = $isOwn;
        } else {
            $canView = false;
        }

        if (!$canView) {
            Session::flash('error', '權限不足');
            redirect('/business_tracking.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 後端守門：無編輯權限不能 POST 儲存
            if (!$canEdit) {
                Session::flash('error', '無編輯權限，僅可檢視');
                redirect('/business_tracking.php?action=edit&id=' . $id);
            }
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/business_tracking.php?action=edit&id=' . $id);
            }

            $updates = array(
                'title'           => isset($_POST['title']) ? $_POST['title'] : '',
                'branch_id'       => isset($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'case_type'       => isset($_POST['case_type']) ? $_POST['case_type'] : null,
                'case_source'     => isset($_POST['case_source']) ? $_POST['case_source'] : null,
                'company'         => isset($_POST['company']) ? $_POST['company'] : null,
                'customer_name'   => isset($_POST['customer_name']) ? $_POST['customer_name'] : null,
                'customer_phone'  => isset($_POST['customer_phone']) ? $_POST['customer_phone'] : null,
                'customer_mobile' => isset($_POST['customer_mobile']) ? $_POST['customer_mobile'] : null,
                'contact_person'  => isset($_POST['contact_person']) ? $_POST['contact_person'] : null,
                'contact_address' => isset($_POST['contact_address']) ? $_POST['contact_address'] : null,
                'city'            => isset($_POST['city']) ? $_POST['city'] : null,
                'district'        => isset($_POST['district']) ? $_POST['district'] : null,
                'address'         => isset($_POST['address']) ? $_POST['address'] : null,
                'sales_id'        => isset($_POST['sales_id']) ? $_POST['sales_id'] : null,
                'deal_amount'     => isset($_POST['deal_amount']) ? $_POST['deal_amount'] : null,
                'deal_date'       => isset($_POST['deal_date']) ? $_POST['deal_date'] : null,
                'sales_note'      => isset($_POST['sales_note']) ? $_POST['sales_note'] : null,
                'sub_status'      => isset($_POST['sub_status']) ? $_POST['sub_status'] : null,
                'survey_date'     => isset($_POST['survey_date']) ? $_POST['survey_date'] : null,
                'survey_time'     => isset($_POST['survey_time']) ? $_POST['survey_time'] : null,
                'lost_reason'     => isset($_POST['lost_reason']) ? $_POST['lost_reason'] : null,
            );

            // 處理電話撥打紀錄
            if (!empty($_POST['call_dates']) && is_array($_POST['call_dates'])) {
                $callAttempts = array();
                foreach ($_POST['call_dates'] as $i => $cd) {
                    if (!empty($cd)) {
                        $callAttempts[] = array(
                            'date' => $cd,
                            'note' => isset($_POST['call_notes'][$i]) ? $_POST['call_notes'][$i] : ''
                        );
                    }
                }
                $updates['call_attempts'] = !empty($callAttempts) ? json_encode($callAttempts) : null;
            }

            $sets = array();
            $params = array();
            foreach ($updates as $col => $val) {
                $sets[] = $col . ' = ?';
                $params[] = ($val !== '' && $val !== null) ? $val : null;
            }
            $params[] = $id;
            $db = Database::getInstance();
            $db->prepare("UPDATE cases SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

            // 同步 stage
            $model->syncStage($id);

            // === 通知觸發（使用 NotificationDispatcher）===
            require_once __DIR__ . '/../modules/notifications/NotificationDispatcher.php';
            $newStatus = isset($updates['sub_status']) ? $updates['sub_status'] : null;
            $oldStatus = isset($case['sub_status']) ? $case['sub_status'] : '';
            $branchId = isset($case['branch_id']) ? $case['branch_id'] : null;

            // 成交 → 進度改未完工 + 通知
            $dealStatuses = array('已成交', '跨月成交', '現簽', '電話報價成交');
            if ($newStatus && in_array($newStatus, $dealStatuses) && !in_array($oldStatus, $dealStatuses)) {
                $db->prepare("UPDATE cases SET status = 'incomplete' WHERE id = ?")->execute(array($id));
            }

            // 未成交 → 進度改未成交
            $lostStatuses = array('已報價無意願', '報價無下文', '無效', '客戶毀約');
            if ($newStatus && in_array($newStatus, $lostStatuses)) {
                $db->prepare("UPDATE cases SET status = 'lost' WHERE id = ?")->execute(array($id));
            }

            // 狀態變更通知
            if ($newStatus && $newStatus !== $oldStatus) {
                $dispatchData = array_merge($case, $updates, array('id' => $id, 'branch_id' => $branchId));
                NotificationDispatcher::dispatch('business_tracking', 'status_changed', $dispatchData, Auth::id(), $case);
            }

            // 指派業務變更通知
            $newSalesId = isset($updates['sales_id']) ? $updates['sales_id'] : null;
            $oldSalesId = isset($case['sales_id']) ? $case['sales_id'] : null;
            if ($newSalesId && $newSalesId != $oldSalesId) {
                $dispatchData = array_merge($case, $updates, array('id' => $id, 'sales_id' => $newSalesId, 'branch_id' => $branchId));
                NotificationDispatcher::dispatch('business_tracking', 'assigned', $dispatchData, Auth::id());
            }

            // 場勘日期 → 自動建立業務行事曆事件
            $newSurveyDate = isset($updates['survey_date']) ? $updates['survey_date'] : null;
            if ($newSurveyDate && $newSurveyDate !== ($case['survey_date'] ?: '')) {
                require_once __DIR__ . '/../modules/business_calendar/BusinessCalendarModel.php';
                $calModel = new BusinessCalendarModel();
                // 檢查是否已有此案件的場勘行程
                $existStmt = $db->prepare("SELECT id FROM business_calendar WHERE case_id = ? AND activity_type = 'survey'");
                $existStmt->execute(array($id));
                $existId = $existStmt->fetchColumn();
                if ($existId) {
                    $db->prepare("UPDATE business_calendar SET event_date = ? WHERE id = ?")->execute(array($newSurveyDate, $existId));
                } else {
                    $surveyTime = isset($updates['survey_time']) ? $updates['survey_time'] : null;
                    $calModel->create(array(
                        'event_date' => $newSurveyDate,
                        'staff_id' => $case['sales_id'] ?: Auth::id(),
                        'case_id' => $id,
                        'customer_id' => $case['customer_id'] ?: null,
                        'customer_name' => $case['customer_name'] ?: $case['title'],
                        'activity_type' => 'survey',
                        'phone' => $case['customer_mobile'] ?: $case['customer_phone'],
                        'region' => null,
                        'address' => $updates['contact_address'] ?: ($case['address'] ?: null),
                        'start_time' => $surveyTime,
                        'end_time' => null,
                        'note' => '場勘 - ' . ($case['title'] ?: ''),
                    ));
                }
            }

            Session::flash('success', '案件已更新');
            redirect('/business_tracking.php?action=view&id=' . $id);
        }

        $salespeople = $model->getSalespeople();
        $branches = $model->getBranches();

        $pageTitle = $canEdit ? '編輯案件' : '檢視案件';
        $currentPage = 'business_tracking';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/business_tracking/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 檢視詳情 ----
    case 'view':
        $cu = Auth::user();
        $isAdmin = in_array($cu['role'], array('boss', 'manager', 'vice_president'));
        $hasManage = Auth::hasPermission('business_tracking.manage');
        $hasView = Auth::hasPermission('business_tracking.view') || $hasManage;
        $hasOwn = Auth::hasPermission('business_tracking.own');

        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $case = $model->getById($id);
        if (!$case) {
            Session::flash('error', '案件不存在');
            redirect('/business_tracking.php');
        }

        $isOwn = ((int)$case['sales_id'] === (int)Auth::id());

        // own 權限只能看自己的；view/manage 都能看
        if (!$isAdmin && !$hasManage && !$hasView) {
            if (!$hasOwn || !$isOwn) {
                Session::flash('error', '權限不足');
                redirect('/business_tracking.php');
            }
        }

        $pageTitle = $case['title'] . ' - 案件詳情';
        $currentPage = 'business_tracking';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/business_tracking/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
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

        if ($id <= 0 || $stage < 1 || $stage > 7) {
            echo json_encode(array('ok' => false, 'msg' => '參數錯誤'));
            exit;
        }

        $case = $model->getById($id);
        if (!$case) {
            echo json_encode(array('ok' => false, 'msg' => '案件不存在'));
            exit;
        }

        if (Auth::hasPermission('business_tracking.own') && !Auth::hasPermission('business_tracking.manage')) {
            if ((int)$case['sales_id'] !== Auth::id()) {
                echo json_encode(array('ok' => false, 'msg' => '權限不足'));
                exit;
            }
        }

        $data = array();
        if (isset($input['deal_amount'])) { $data['deal_amount'] = $input['deal_amount']; }
        if (isset($input['deal_date'])) { $data['deal_date'] = $input['deal_date']; }
        if (isset($input['lost_reason'])) { $data['lost_reason'] = $input['lost_reason']; }

        $model->updateStage($id, $stage, $data);
        $labels = CaseModel::stageLabels();
        echo json_encode(array('ok' => true, 'msg' => '已更新為：' . (isset($labels[$stage]) ? $labels[$stage] : $stage)));
        exit;

    default:
        redirect('/business_tracking.php');
}
