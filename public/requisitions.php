<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/procurement/ProcurementModel.php';

$model = new ProcurementModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    case 'list':
        $filters = array(
            'branch_id' => !empty($_GET['branch_id']) ? $_GET['branch_id'] : '',
            'status'    => !empty($_GET['status']) ? $_GET['status'] : '',
            'keyword'   => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from' => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'   => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
        );
        $records = $model->getRequisitions($filters);
        $branches = $model->getBranches($branchIds);

        $pageTitle = '請購單';
        $currentPage = 'requisitions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/requisitions/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/requisitions.php');
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'requisition_date' => !empty($_POST['requisition_date']) ? $_POST['requisition_date'] : date('Y-m-d'),
                'requester_name'   => !empty($_POST['requester_name']) ? $_POST['requester_name'] : null,
                'branch_id'        => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'sales_name'       => !empty($_POST['sales_name']) ? $_POST['sales_name'] : null,
                'urgency'          => !empty($_POST['urgency']) ? $_POST['urgency'] : '一般件',
                'case_name'        => !empty($_POST['case_name']) ? $_POST['case_name'] : null,
                'quotation_number' => !empty($_POST['quotation_number']) ? $_POST['quotation_number'] : null,
                'vendor_name'      => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'expected_date'    => !empty($_POST['expected_date']) ? $_POST['expected_date'] : null,
                'status'           => '草稿',
                'note'             => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'       => $userId,
            );

            $reqId = $model->createRequisition($data);

            if (!empty($_POST['items'])) {
                $model->saveRequisitionItems($reqId, $_POST['items']);
            }

            // 通知（Throwable 攔截 Error + Exception）
            try {
                require_once __DIR__ . '/../modules/notifications/NotificationDispatcher.php';
                $reqRecord = $model->getRequisition($reqId);
                if ($reqRecord) NotificationDispatcher::dispatch('purchases', 'created', $reqRecord);
            } catch (\Throwable $e) { /* 通知失敗不影響主流程 */ }

            Session::flash('success', '請購單已新增');
            redirect('/requisitions.php?action=edit&id=' . $reqId);
        }

        $record = null;
        $items = array();
        $branches = $model->getBranches($branchIds);

        $pageTitle = '新增請購單';
        $currentPage = 'requisitions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/requisitions/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getRequisition($id);
        if (!$record) {
            Session::flash('error', '請購單不存在');
            redirect('/requisitions.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/requisitions.php?action=edit&id=' . $id);
            }
            // 已鎖定狀態不可修改（送簽核除外）
            if (in_array($record['status'], array('簽核中', '已核准', '簽核完成', '已轉採購')) && empty($_POST['submit_after_save'])) {
                Session::flash('error', '此請購單已鎖定，無法修改');
                redirect('/requisitions.php?action=edit&id=' . $id);
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'requisition_date' => !empty($_POST['requisition_date']) ? $_POST['requisition_date'] : date('Y-m-d'),
                'requester_name'   => !empty($_POST['requester_name']) ? $_POST['requester_name'] : null,
                'branch_id'        => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'sales_name'       => !empty($_POST['sales_name']) ? $_POST['sales_name'] : null,
                'urgency'          => !empty($_POST['urgency']) ? $_POST['urgency'] : '一般件',
                'case_name'        => !empty($_POST['case_name']) ? $_POST['case_name'] : null,
                'quotation_number' => !empty($_POST['quotation_number']) ? $_POST['quotation_number'] : null,
                'vendor_name'      => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'expected_date'    => !empty($_POST['expected_date']) ? $_POST['expected_date'] : null,
                'note'             => !empty($_POST['note']) ? $_POST['note'] : null,
                'updated_by'       => $userId,
            );
            // 簽核欄位不覆蓋（由簽核流程自動處理）

            $model->updateRequisition($id, $data);

            if (isset($_POST['items'])) {
                $model->saveRequisitionItems($id, $_POST['items']);
            }

            // 如果是「送簽核」按鈕觸發的儲存，先存再跳轉送簽核
            if (!empty($_POST['submit_after_save'])) {
                redirect('/requisitions.php?action=submit_approval&id=' . $id . '&csrf_token=' . urlencode(Session::getCsrfToken()));
            }

            Session::flash('success', '請購單已更新');
            redirect('/requisitions.php?action=edit&id=' . $id);
        }

        $items = $model->getRequisitionItems($record['id']);
        $branches = $model->getBranches($branchIds);

        $pageTitle = '編輯請購單 - ' . $record['requisition_number'];
        $currentPage = 'requisitions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/requisitions/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'delete':
        if (!Auth::hasPermission('procurement.manage')) {
            Session::flash('error', '無權限');
            redirect('/requisitions.php');
        }
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            $model->deleteRequisition($id);
            Session::flash('success', '請購單已刪除');
        }
        redirect('/requisitions.php');
        break;

    // ---- 送簽核 ----
    case 'submit_approval':
        $id = (int)($_GET['id'] ?? 0);
        $record = $model->getRequisition($id);
        if (!$record) { Session::flash('error', '請購單不存在'); redirect('/requisitions.php'); }
        if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/requisitions.php?action=edit&id=' . $id); }

        require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
        $approvalModel = new ApprovalModel();

        // 計算總金額（品項數量×單價）
        $reqItems = $model->getRequisitionItems($id);
        $totalAmount = 0;
        $productIds = array();
        foreach ($reqItems as $ri) {
            $totalAmount += (float)($ri['quantity'] ?? 0) * (float)($ri['unit_price'] ?? 0);
            if (!empty($ri['product_id'])) $productIds[] = (int)$ri['product_id'];
        }

        $rules = $approvalModel->needsApproval('purchases', $totalAmount, null, $productIds);
        if (!$rules) {
            // 不需簽核，直接核准
            $model->updateRequisition($id, array('status' => '已核准', 'approval_date' => date('Y-m-d'), 'approval_user' => '系統自動'));
            AuditLog::log('requisitions', 'auto_approve', $id, '自動核准（無需簽核）');
            Session::flash('success', '請購單已自動核准（無需簽核）');
        } else {
            // 需要簽核
            $approvalModel->submitForApproval('purchases', $id, $totalAmount, null, Auth::id());
            $model->updateRequisition($id, array('status' => '簽核中'));

            // 發通知給簽核人
            require_once __DIR__ . '/../modules/notifications/NotificationModel.php';
            $notifModel = new NotificationModel();
            foreach ($rules as $rule) {
                if ($rule['approver_role'] === 'auto_approve') continue;
                if (!empty($rule['approver_id'])) {
                    $notifModel->send(
                        $rule['approver_id'], 'approval_request',
                        '請購單待簽核：' . $record['requisition_number'],
                        '請購人：' . ($record['requester_name'] ?? '') . '，金額：$' . number_format($totalAmount),
                        '/requisitions.php?action=edit&id=' . $id,
                        'purchases', $id, Auth::id()
                    );
                } elseif (!empty($rule['approver_role'])) {
                    $notifModel->sendToRole(
                        $rule['approver_role'], null,
                        'approval_request',
                        '請購單待簽核：' . $record['requisition_number'],
                        '請購人：' . ($record['requester_name'] ?? '') . '，金額：$' . number_format($totalAmount),
                        '/requisitions.php?action=edit&id=' . $id,
                        'purchases', $id, Auth::id()
                    );
                }
            }
            AuditLog::log('requisitions', 'submit_approval', $id, '送出簽核');
            Session::flash('success', '請購單已送出簽核');
        }
        redirect('/requisitions.php?action=edit&id=' . $id);
        break;

    // ---- 簽核人核准 ----
    case 'approve':
        $id = (int)($_GET['id'] ?? 0);
        $record = $model->getRequisition($id);
        if (!$record) { Session::flash('error', '請購單不存在'); redirect('/requisitions.php'); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/requisitions.php?action=edit&id=' . $id);
        }

        // 更新覆核數量
        if (!empty($_POST['items'])) {
            foreach ($_POST['items'] as $idx => $item) {
                if (isset($item['id']) && isset($item['approved_qty'])) {
                    $db = Database::getInstance();
                    $db->prepare("UPDATE requisition_items SET approved_qty = ? WHERE id = ?")->execute(array(
                        (int)$item['approved_qty'], (int)$item['id']
                    ));
                }
            }
        }

        // 更新請購單狀態
        $model->updateRequisition($id, array(
            'status' => '已核准',
            'approval_user' => Auth::user()['real_name'],
            'approval_date' => date('Y-m-d'),
            'approval_note' => !empty($_POST['approval_note']) ? $_POST['approval_note'] : null,
        ));

        // 更新簽核流程
        require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
        $approvalModel = new ApprovalModel();
        $flowStatus = $approvalModel->getFlowStatus('purchases', $id);
        foreach ($flowStatus['flows'] as $flow) {
            if ($flow['status'] === 'pending') {
                $approvalModel->approve($flow['id'], Auth::id(), $_POST['approval_note'] ?? '');
            }
        }

        // 通知申請人
        require_once __DIR__ . '/../modules/notifications/NotificationModel.php';
        $notifModel = new NotificationModel();
        if (!empty($record['created_by'])) {
            $notifModel->send(
                $record['created_by'], 'approval_approved',
                '請購單已核准：' . $record['requisition_number'],
                '簽核人：' . Auth::user()['real_name'],
                '/requisitions.php?action=edit&id=' . $id,
                'purchases', $id, Auth::id()
            );
        }

        AuditLog::log('requisitions', 'approve', $id, '核准請購單');
        Session::flash('success', '請購單已核准');
        redirect('/requisitions.php?action=edit&id=' . $id);
        break;

    // ---- 簽核人退回 ----
    case 'reject':
        $id = (int)($_GET['id'] ?? 0);
        $record = $model->getRequisition($id);
        if (!$record) { Session::flash('error', '請購單不存在'); redirect('/requisitions.php'); }
        if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/requisitions.php?action=edit&id=' . $id); }

        $model->updateRequisition($id, array(
            'status' => '退回',
            'approval_user' => Auth::user()['real_name'],
            'approval_date' => date('Y-m-d'),
            'approval_note' => $_GET['reason'] ?? '退回修改',
        ));

        require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
        $approvalModel = new ApprovalModel();
        $flowStatus = $approvalModel->getFlowStatus('purchases', $id);
        foreach ($flowStatus['flows'] as $flow) {
            if ($flow['status'] === 'pending') {
                $approvalModel->reject($flow['id'], Auth::id(), $_GET['reason'] ?? '退回修改');
            }
        }

        require_once __DIR__ . '/../modules/notifications/NotificationModel.php';
        $notifModel = new NotificationModel();
        if (!empty($record['created_by'])) {
            $notifModel->send(
                $record['created_by'], 'approval_rejected',
                '請購單被退回：' . $record['requisition_number'],
                '原因：' . ($_GET['reason'] ?? '退回修改'),
                '/requisitions.php?action=edit&id=' . $id,
                'purchases', $id, Auth::id()
            );
        }

        AuditLog::log('requisitions', 'reject', $id, '退回請購單');
        Session::flash('info', '請購單已退回');
        redirect('/requisitions.php?action=edit&id=' . $id);
        break;

    case 'ajax_search_product':
        header('Content-Type: application/json');
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        if (strlen($q) < 1) { echo json_encode(array()); break; }
        $db = Database::getInstance();
        $like = '%' . $q . '%';
        // 排除「不進出庫單」分類（及其子孫）下的產品（工程單價/工資單價等非實體品項不應出現在採購/庫存搜尋）
        require_once __DIR__ . '/../modules/products/ProductModel.php';
        $excludedCatIds = ProductModel::getCategoryIdsByFlag('exclude_from_stockout');
        $sql = "SELECT id, name, model, CAST(COALESCE(NULLIF(cost,0), price, 0) AS SIGNED) as price, unit, pack_qty, pack_unit, cost_per_unit FROM products WHERE is_active = 1 AND (name LIKE ? OR model LIKE ?)";
        $params = array($like, $like);
        if (!empty($excludedCatIds)) {
            $ph = implode(',', array_fill(0, count($excludedCatIds), '?'));
            $sql .= " AND (category_id IS NULL OR category_id NOT IN ($ph))";
            $params = array_merge($params, $excludedCatIds);
        }
        $sql .= " ORDER BY name LIMIT 15";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // 查庫存
        if (!empty($products)) {
            $ids = array_column($products, 'id');
            try {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $invStmt = $db->prepare("SELECT product_id, CAST(SUM(stock_qty) AS SIGNED) as stock FROM inventory WHERE product_id IN ($ph) GROUP BY product_id");
                $invStmt->execute($ids);
                $stockMap = array();
                foreach ($invStmt->fetchAll(PDO::FETCH_ASSOC) as $inv) {
                    $stockMap[$inv['product_id']] = (int)$inv['stock'];
                }
                foreach ($products as &$p) {
                    $p['stock'] = isset($stockMap[$p['id']]) ? $stockMap[$p['id']] : 0;
                }
                unset($p);
            } catch (\Throwable $e) {
                foreach ($products as &$p) { $p['stock'] = '-'; }
                unset($p);
            }
        }
        echo json_encode($products);
        break;

    case 'ajax_search_quotation':
        header('Content-Type: application/json');
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $db = Database::getInstance();
        if (strlen($q) < 1) {
            // 點擊時：列出客戶已接受的報價單（含舊值 accepted）
            $stmt = $db->query("SELECT quotation_number, customer_name, total_amount FROM quotations WHERE status IN ('customer_accepted','accepted') ORDER BY created_at DESC LIMIT 30");
        } else {
            // 輸入搜尋：搜尋客戶已接受的報價單
            $like = '%' . $q . '%';
            $stmt = $db->prepare("SELECT quotation_number, customer_name, total_amount FROM quotations WHERE (quotation_number LIKE ? OR customer_name LIKE ?) AND status IN ('customer_accepted','accepted') ORDER BY created_at DESC LIMIT 20");
            $stmt->execute(array($like, $like));
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        redirect('/requisitions.php');
        break;
}
