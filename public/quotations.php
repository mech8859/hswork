<?php
/**
 * 報價管理
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/quotations/QuotationModel.php';

// 權限檢查
$canManage = Auth::hasPermission('quotations.manage');
$canView = Auth::hasPermission('quotations.view');
$canOwn = Auth::hasPermission('quotations.own');
if (!$canManage && !$canView && !$canOwn) {
    Session::flash('error', '無權限');
    redirect('/index.php');
}

$model = new QuotationModel();
$action = $_GET['action'] ?? 'list';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    case 'list':
        $filters = array(
            'month'   => $_GET['month'] ?? date('Y-m'),
            'status'  => $_GET['status'] ?? '',
            'keyword' => $_GET['keyword'] ?? '',
        );
        // 僅 own 權限的只能看自己
        if (!$canManage && !$canView && $canOwn) {
            $filters['own_only'] = Auth::id();
        }
        $quotations = $model->getList($branchIds, $filters);
        $pageTitle = '報價管理';
        $currentPage = 'quotations';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/quotations/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/quotations.php'); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/quotations.php'); }
            $quoteId = $model->create($_POST);
            AuditLog::log('quotations', 'create', $quoteId, $_POST['customer_name'] ?? '');
            if (!empty($_POST['sections'])) {
                $model->saveSections($quoteId, $_POST['sections']);
            }
            if ($canManage) {
                $model->saveLaborCost($quoteId, $_POST);
            }
            $model->syncCaseQuoteAmount($quoteId);
            Session::flash('success', '報價單已建立');
            redirect('/quotations.php?action=view&id=' . $quoteId);
        }
        $quote = null;
        $salespeople = $model->getSalespeople($branchIds);
        $branches = $model->getBranches();
        $cases = $model->getCaseOptions($branchIds);
        $pageTitle = '新增報價單';
        $currentPage = 'quotations';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/quotations/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/quotations.php'); }
        $id = (int)($_GET['id'] ?? 0);
        $quote = $model->getById($id);
        if (!$quote) { Session::flash('error', '報價單不存在'); redirect('/quotations.php'); }
        if (!QuotationModel::canEdit($quote['status'])) {
            Session::flash('error', '此報價單狀態不可編輯');
            redirect('/quotations.php?action=view&id=' . $id);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/quotations.php?action=edit&id='.$id); }
            AuditLog::logChange('quotations', $id, $quote['quote_number'] ?? "報價單#{$id}", $quote, $_POST, array('customer_name','status','total_amount','valid_until'));
            $model->update($id, $_POST);
            if (isset($_POST['sections'])) {
                $model->saveSections($id, $_POST['sections']);
            }
            if ($canManage) {
                $model->saveLaborCost($id, $_POST);
            }
            $model->syncCaseQuoteAmount($id);
            Session::flash('success', '報價單已更新');
            redirect('/quotations.php?action=view&id=' . $id);
        }
        $salespeople = $model->getSalespeople($branchIds);
        $branches = $model->getBranches();
        $cases = $model->getCaseOptions($branchIds);
        $pageTitle = '編輯報價單';
        $currentPage = 'quotations';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/quotations/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        $quote = $model->getById($id);
        if (!$quote) { Session::flash('error', '報價單不存在'); redirect('/quotations.php'); }
        // own 權限檢查
        if (!$canManage && !$canView && $canOwn && $quote['sales_id'] != Auth::id()) {
            Session::flash('error', '無權限'); redirect('/quotations.php');
        }
        $pageTitle = '報價單 ' . $quote['quotation_number'];
        $currentPage = 'quotations';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/quotations/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'print':
        $id = (int)($_GET['id'] ?? 0);
        $quote = $model->getById($id);
        if (!$quote) { Session::flash('error', '報價單不存在'); redirect('/quotations.php'); }
        require __DIR__ . '/../templates/quotations/print.php';
        break;

    case 'status':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/quotations.php'); }
        if (verify_csrf()) {
            $newStatus = $_GET['status'] ?? '';
            $validStatuses = array('draft', 'sent', 'approved', 'customer_accepted', 'customer_rejected', 'revision_needed');
            if (in_array($newStatus, $validStatuses)) {
                $qId = (int)$_GET['id'];
                $model->updateStatus($qId, $newStatus);
                AuditLog::log('quotations', 'status', $qId, '狀態變更為 ' . QuotationModel::statusLabel($newStatus));
                $model->syncCaseQuoteAmount($qId);

                // 客戶接受時，自動回填案件帳務資訊 + 上傳報價單到案件附件
                if ($newStatus === 'customer_accepted') {
                    $filled = $model->fillCaseFinancials($qId);

                    // 生成報價單 HTML 並存為案件附件
                    $quote = $model->getById($qId);
                    if ($quote && !empty($quote['case_id'])) {
                        $caseId = (int)$quote['case_id'];
                        $uploadDir = __DIR__ . '/uploads/cases/' . $caseId;
                        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

                        $fileName = '報價單_' . $quote['quotation_number'] . '.html';
                        $filePath = '/uploads/cases/' . $caseId . '/' . $fileName;

                        ob_start();
                        require __DIR__ . '/../templates/quotations/print.php';
                        $html = ob_get_clean();
                        file_put_contents($uploadDir . '/' . $fileName, $html);

                        require_once __DIR__ . '/../modules/cases/CaseModel.php';
                        $caseModel = new CaseModel();
                        $caseModel->saveAttachment($caseId, 'quotation', $fileName, $filePath);
                    }

                    if ($filled) {
                        Session::flash('success', '狀態已更新為「' . QuotationModel::statusLabel($newStatus) . '」，案件帳務資訊已自動回填，報價單已存入案件附件');
                    } else {
                        Session::flash('success', '狀態已更新為「' . QuotationModel::statusLabel($newStatus) . '」');
                    }
                } else {
                    Session::flash('success', '狀態已更新為「' . QuotationModel::statusLabel($newStatus) . '」');
                }
            }
        }
        redirect('/quotations.php?action=view&id=' . (int)$_GET['id']);
        break;

    // ---- 送簽核 ----
    case 'submit_approval':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/quotations.php'); }
        $id = (int)($_GET['id'] ?? 0);
        $quote = $model->getById($id);
        if (!$quote) { Session::flash('error', '報價單不存在'); redirect('/quotations.php'); }

        // 檢查是否有客戶
        if (empty($quote['customer_id'])) {
            Session::flash('error', '送簽核前請先關聯客戶（編輯報價單設定客戶名稱）');
            redirect('/quotations.php?action=view&id=' . $id);
            break;
        }

        // 檢查狀態
        if (!in_array($quote['status'], array('draft', 'rejected_internal', 'revision_needed'))) {
            Session::flash('error', '此報價單狀態無法送簽核');
            redirect('/quotations.php?action=view&id=' . $id);
            break;
        }

        if (verify_csrf()) {
            require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
            $approvalModel = new ApprovalModel();
            $amount = (float)$quote['total_amount'];
            $profitRate = $quote['profit_rate'] ? (float)$quote['profit_rate'] : null;

            $result = $approvalModel->submitForApproval('quotations', $id, $amount, $profitRate);

            if (isset($result['auto_approved']) && $result['auto_approved']) {
                // 沒有簽核規則或免簽核，直接核准
                $model->updateStatus($id, 'approved');
                AuditLog::log('quotations', 'auto_approve', $id, '自動核准（無需簽核）');
                Session::flash('success', '無需簽核，已自動核准');
            } else {
                $model->updateStatus($id, 'pending_approval');
                AuditLog::log('quotations', 'submit_approval', $id, '送出簽核');
                Session::flash('success', '已送出簽核，等待主管審核');
            }
        }
        redirect('/quotations.php?action=view&id=' . $id);
        break;

    case 'delete':
        if (!Auth::hasPermission('quotations.delete') && !Auth::hasPermission('all')) {
            Session::flash('error', '權限不足，無法刪除報價單');
            redirect('/quotations.php');
        }
        if (verify_csrf()) {
            $model->delete((int)$_GET['id']);
            Session::flash('success', '報價單已刪除');
        }
        redirect('/quotations.php');
        break;

    // ---- 從報價單建立出庫單 ----
    case 'create_stock_out':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/quotations.php'); }
        if (verify_csrf()) {
            $qId = (int)($_GET['id'] ?? 0);
            $quote = $model->getById($qId);
            if (!$quote || $quote['status'] !== 'customer_accepted') {
                Session::flash('error', '報價單不存在或狀態不正確');
                redirect('/quotations.php');
            }

            // 檢查是否已有此報價單的出庫單
            $db = Database::getInstance();
            $existSo = $db->prepare("SELECT id, so_number, status FROM stock_outs WHERE source_type = 'quotation' AND source_id = ?");
            $existSo->execute(array($qId));
            $existSoRecord = $existSo->fetch(PDO::FETCH_ASSOC);
            if ($existSoRecord && empty($_GET['force'])) {
                Session::flash('warning', '此報價單已建立過出庫單 <a href="/stock_outs.php?action=view&id=' . $existSoRecord['id'] . '">' . $existSoRecord['so_number'] . '</a>（' . $existSoRecord['status'] . '），如需再次建立請確認。');
                redirect('/quotations.php?action=view&id=' . $qId . '&show_force_stock_out=1');
                break;
            }

            // 取得預設倉庫
            $db = Database::getInstance();
            $wh = $db->query("SELECT id FROM warehouses ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $warehouseId = $wh ? (int)$wh['id'] : 0;

            // 收集所有品項
            $items = array();
            $totalQty = 0;
            if (!empty($quote['sections'])) {
                foreach ($quote['sections'] as $sec) {
                    if (!empty($sec['items'])) {
                        foreach ($sec['items'] as $item) {
                            $qty = (float)($item['quantity'] ?? 0);
                            if ($qty <= 0) continue;
                            $items[] = array(
                                'product_id' => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                                'product_name' => $item['item_name'] ?? '',
                                'model' => $item['model_number'] ?? '',
                                'unit' => $item['unit'] ?? '式',
                                'quantity' => $qty,
                                'unit_price' => (float)($item['unit_cost'] ?? 0),
                                'note' => $item['remark'] ?? '',
                            );
                            $totalQty += $qty;
                        }
                    }
                }
            }

            if (empty($items)) {
                Session::flash('error', '報價單無品項，無法建立出庫單');
                redirect('/quotations.php?action=view&id=' . $qId);
            }

            require_once __DIR__ . '/../modules/inventory/StockModel.php';
            $stockModel = new StockModel();
            $soId = $stockModel->createStockOut(array(
                'so_date' => date('Y-m-d'),
                'status' => '待確認',
                'source_type' => 'quotation',
                'source_id' => $qId,
                'source_number' => $quote['quotation_number'],
                'warehouse_id' => $warehouseId,
                'note' => '報價單 ' . $quote['quotation_number'] . ' 自動產生',
                'total_qty' => $totalQty,
                'created_by' => Auth::id(),
                'items' => $items,
            ));

            AuditLog::log('stock_outs', 'create', $soId, '從報價單 ' . $quote['quotation_number'] . ' 建立出庫單');
            Session::flash('success', '出庫單已建立');
            redirect('/stock_outs.php?action=view&id=' . $soId);
        }
        redirect('/quotations.php');
        break;

    case 'duplicate':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/quotations.php'); }
        if (verify_csrf()) {
            $newId = $model->duplicate((int)$_GET['id']);
            if ($newId) {
                Session::flash('success', '報價單已複製');
                redirect('/quotations.php?action=edit&id=' . $newId);
            }
        }
        redirect('/quotations.php');
        break;

    case 'ajax_products':
        header('Content-Type: application/json');
        $keyword = trim($_GET['keyword'] ?? '');
        $categoryId = (int)($_GET['category_id'] ?? 0);

        $db = Database::getInstance();
        $where = 'p.is_active = 1';
        $params = array();

        if ($categoryId > 0) {
            // 遞迴取所有子孫分類
            $catIds = array($categoryId);
            $queue = array($categoryId);
            while (!empty($queue)) {
                $parentId = array_shift($queue);
                $subStmt = $db->prepare('SELECT id FROM product_categories WHERE parent_id = ?');
                $subStmt->execute(array($parentId));
                foreach ($subStmt->fetchAll() as $sub) {
                    $catIds[] = (int)$sub['id'];
                    $queue[] = (int)$sub['id'];
                }
            }
            $placeholders = implode(',', array_fill(0, count($catIds), '?'));
            $where .= " AND p.category_id IN ($placeholders)";
            $params = array_merge($params, $catIds);
        }

        if (strlen($keyword) >= 1) {
            $where .= ' AND (p.name LIKE ? OR p.model LIKE ?)';
            $params[] = '%' . $keyword . '%';
            $params[] = '%' . $keyword . '%';
        }

        if (!$categoryId && strlen($keyword) < 1) {
            echo json_encode(array());
            exit;
        }

        $stmt = $db->prepare("
            SELECT p.id, p.name, p.model, p.unit, p.price, p.cost, p.brand,
                   pc.name AS category_name,
                   COALESCE(inv.total_stock, 0) AS stock_qty
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            LEFT JOIN (SELECT product_id, SUM(stock_qty) AS total_stock FROM inventory GROUP BY product_id) inv ON inv.product_id = p.id
            WHERE $where
            ORDER BY p.name
            LIMIT 50
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        exit;

    case 'ajax_categories':
        header('Content-Type: application/json');
        $db = Database::getInstance();
        $parentId = (int)($_GET['parent_id'] ?? 0);
        if ($parentId > 0) {
            // 取子分類
            $cats = $db->prepare("SELECT id, name, parent_id FROM product_categories WHERE parent_id = ? ORDER BY name");
            $cats->execute(array($parentId));
            echo json_encode($cats->fetchAll());
        } else {
            // 頂層分類
            $cats = $db->query("SELECT id, name, parent_id FROM product_categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name")->fetchAll();
            echo json_encode($cats);
        }
        exit;

    case 'ajax_customer':
        $custId = (int)($_GET['id'] ?? 0);
        if ($custId) {
            require_once __DIR__ . '/../modules/customers/CustomerModel.php';
            $custModel = new CustomerModel();
            $cust = $custModel->getById($custId);
            header('Content-Type: application/json');
            echo json_encode($cust ?: new stdClass());
        } else {
            header('Content-Type: application/json');
            echo '{}';
        }
        exit;

    default:
        redirect('/quotations.php?action=list');
        break;
}
