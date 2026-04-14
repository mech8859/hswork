<?php
/**
 * 報價管理
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/quotations/QuotationModel.php';
require_once __DIR__ . '/../modules/cases/CaseModel.php';

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
            // 儲存預估線材到 case_material_estimates
            $createCaseId = (int)($_POST['case_id'] ?? 0);
            if ($createCaseId > 0 && isset($_POST['est_materials'])) {
                $caseModel = new CaseModel();
                $caseModel->saveMaterialEstimates($createCaseId, $_POST['est_materials']);
            }
            // 自動同步線材成本
            if ($createCaseId > 0) {
                $model->syncCableCostFromEstimates($quoteId, $createCaseId);
            }
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
        $cu = Auth::user();
        $isAdmin = in_array($cu['role'], array('boss', 'manager', 'vice_president'));
        $id = (int)($_GET['id'] ?? 0);
        $quote = $model->getById($id);
        if (!$quote) { Session::flash('error', '報價單不存在'); redirect('/quotations.php'); }

        $isOwn = ((int)($quote['sales_id'] ?? 0) === (int)Auth::id());
        $statusEditable = QuotationModel::canEdit($quote['status']);

        // 判定可否編輯：高層 / manage 一律可（前提：狀態允許編輯）
        // own：可編自己的（前提：狀態允許編輯）
        // view：不可編
        $canEditQuote = $statusEditable && ($isAdmin || $canManage || ($canOwn && $isOwn));
        // 判定可否檢視：view/manage/own 都能看
        $canViewQuote = $isAdmin || $canManage || $canView || ($canOwn && $isOwn);
        if (!$canViewQuote) {
            Session::flash('error', '無權限');
            redirect('/quotations.php');
        }
        if (!$canEditQuote && !$statusEditable) {
            // 狀態不可編就算 manage 也不能編；至少讓使用者看
            Session::flash('error', '此報價單狀態不可編輯，僅可檢視');
            redirect('/quotations.php?action=view&id=' . $id);
        }
        // 兼容舊變數名
        $canEdit = $canEditQuote;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 後端守門
            if (!$canEdit) {
                Session::flash('error', '無編輯權限，僅可檢視');
                redirect('/quotations.php?action=edit&id='.$id);
            }
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
            // 儲存預估線材到 case_material_estimates
            $editCaseId = (int)($_POST['case_id'] ?? 0);
            if ($editCaseId > 0 && isset($_POST['est_materials'])) {
                $caseModel = new CaseModel();
                $caseModel->saveMaterialEstimates($editCaseId, $_POST['est_materials']);
            }
            // 自動同步線材成本
            if ($editCaseId > 0) {
                $model->syncCableCostFromEstimates($id, $editCaseId);
            }
            // 已核准狀態編輯後退回草稿，需重新送簽核
            if ($quote['status'] === 'approved') {
                $model->updateStatus($id, 'draft');
                $db = Database::getInstance();
                $db->prepare("DELETE FROM approval_flows WHERE module = 'quotations' AND target_id = ?")->execute(array($id));
                AuditLog::log('quotations', 'revision_to_draft', $id, '已核准報價單修改後退回草稿');
            }
            Session::flash('success', '報價單已更新' . ($quote['status'] === 'approved' ? '（狀態已退回草稿，請重新送簽核）' : ''));
            redirect('/quotations.php?action=view&id=' . $id);
        }
        $salespeople = $model->getSalespeople($branchIds);
        $branches = $model->getBranches();
        $cases = $model->getCaseOptions($branchIds);

        // 編輯鎖定（多人同時編輯提醒）
        require_once __DIR__ . '/../includes/EditingLock.php';
        $_curUser = Auth::user();
        if ($_curUser && $id > 0) EditingLock::set('quotations', $id, $_curUser['id'], $_curUser['real_name']);
        $otherEditors = ($id > 0) ? EditingLock::getOthers('quotations', $id, Auth::id()) : array();
        $editingLockModule = 'quotations';
        $editingLockRecordId = $id;

        $pageTitle = $canEdit ? '編輯報價單' : '檢視報價單';
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

        // 編輯鎖定（多人同時開啟提醒；view 也納入因為使用者多從這裡進入）
        require_once __DIR__ . '/../includes/EditingLock.php';
        $_curUser = Auth::user();
        if ($_curUser && $id > 0) EditingLock::set('quotations', $id, $_curUser['id'], $_curUser['real_name']);
        $otherEditors = ($id > 0) ? EditingLock::getOthers('quotations', $id, Auth::id()) : array();
        $editingLockModule = 'quotations';
        $editingLockRecordId = $id;

        // 查詢此報價單已建立的出庫單
        $_db = Database::getInstance();
        $_so = $_db->prepare("SELECT id, so_number, status, so_date FROM stock_outs WHERE source_type = 'quotation' AND source_id = ? ORDER BY id DESC");
        $_so->execute(array($id));
        $relatedStockOuts = $_so->fetchAll(PDO::FETCH_ASSOC);

        // 自動補算：施工時數/人力成本/線材成本（若 DB 未存但可算出）
        if ($canManage) {
            $needSync = false;
            $syncDays   = (float)($quote['labor_days'] ?: 0);
            $syncPeople = (int)($quote['labor_people'] ?: 0);
            $syncHours  = (float)($quote['labor_hours'] ?: 0);
            $syncLabor  = (int)($quote['labor_cost_total'] ?: 0);
            $syncCable  = (int)($quote['cable_cost'] ?: 0);

            // 自動算施工時數
            if (!$syncHours && $syncDays > 0 && $syncPeople > 0) {
                $syncHours = $syncDays * $syncPeople * 8;
                $needSync = true;
            }
            // 自動算人力成本
            if (!$syncLabor && $syncHours > 0) {
                $_hrStmt = $_db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'labor_hourly_cost' LIMIT 1");
                $_hrStmt->execute();
                $_hrCost = (int)$_hrStmt->fetchColumn() ?: 404;
                $syncLabor = (int)round($syncHours * $_hrCost);
                $needSync = true;
            }
            // 自動同步線材成本
            if (!$syncCable && !empty($quote['case_id'])) {
                $model->syncCableCostFromEstimates($id, $quote['case_id']);
                $needSync = true;
            }
            if ($needSync) {
                $model->saveLaborCost($id, array(
                    'labor_days' => $syncDays ?: null,
                    'labor_people' => $syncPeople ?: null,
                    'labor_hours' => $syncHours ?: null,
                    'labor_cost_total' => $syncLabor ?: null,
                    'cable_cost' => $syncCable,
                ));
                // 重算利潤
                $_matStmt = $_db->prepare("
                    SELECT COALESCE(SUM(qi.unit_cost * qi.quantity), 0)
                    FROM quotation_items qi
                    JOIN quotation_sections qs ON qi.section_id = qs.id
                    WHERE qs.quotation_id = ?
                ");
                $_matStmt->execute(array($id));
                $_matCost = (int)$_matStmt->fetchColumn();
                $model->recalcTotalsPublic($id, (int)$quote['subtotal'], $_matCost);
                // 重新讀取
                $quote = $model->getById($id);
            }
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

        // 檢查是否有客戶資訊：未成交客戶不會有 customer_id（從案件來）
        // 只要有 customer_name 或關聯案件即可送簽核
        if (empty($quote['customer_name']) && empty($quote['case_id'])) {
            Session::flash('error', '送簽核前請先填寫客戶名稱或關聯案件');
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

    // ---- 申請變更（已送客戶/客戶已接受 → 變更簽核 → 退回草稿）----
    case 'request_revision':
        $id = (int)($_GET['id'] ?? 0);
        $quote = $model->getById($id);
        if (!$quote) { Session::flash('error', '報價單不存在'); redirect('/quotations.php'); }
        if (!in_array($quote['status'], array('sent', 'customer_accepted'))) {
            Session::flash('error', '此報價單狀態無法申請變更');
            redirect('/quotations.php?action=view&id=' . $id);
            break;
        }
        if (verify_csrf()) {
            require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
            $approvalModel = new ApprovalModel();
            $amount = (float)$quote['total_amount'];
            $profitRate = $quote['profit_rate'] ? (float)$quote['profit_rate'] : null;

            $result = $approvalModel->submitForApproval('quotations', $id, $amount, $profitRate);

            // 在 approval_flows 記錄這是變更簽核，以及原始狀態
            $db = Database::getInstance();
            $revPayload = json_encode(array('type' => 'revision', 'original_status' => $quote['status']));
            $db->prepare("UPDATE approval_flows SET payload = ? WHERE module = 'quotations' AND target_id = ? AND status = 'pending'")
               ->execute(array($revPayload, $id));

            if (isset($result['auto_approved']) && $result['auto_approved']) {
                $model->updateStatus($id, 'draft');
                AuditLog::log('quotations', 'revision_auto_approved', $id, '變更申請自動核准，退回草稿');
                Session::flash('success', '變更申請已自動核准，報價單已退回草稿可編輯');
            } else {
                $model->updateStatus($id, 'pending_revision');
                AuditLog::log('quotations', 'request_revision', $id, '申請變更報價單（原狀態：' . $quote['status'] . '）');
                Session::flash('success', '已送出變更申請，等待主管審核');
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

            // 預先載入被排除的分類 ID（含子分類繼承父分類的旗標）
            $excludedCatIds = array();
            $_ecStmt = $db->query("SELECT id, parent_id, exclude_from_stockout FROM product_categories");
            $_ecAll = $_ecStmt->fetchAll(PDO::FETCH_ASSOC);
            $_ecMap = array();
            foreach ($_ecAll as $_ec) { $_ecMap[(int)$_ec['id']] = $_ec; }
            foreach ($_ecMap as $_ecId => $_ec) {
                // 自身有旗標，或任一上層分類有旗標 → 排除
                $cid = $_ecId;
                while ($cid) {
                    if (!empty($_ecMap[$cid]['exclude_from_stockout'])) { $excludedCatIds[$_ecId] = true; break; }
                    $cid = !empty($_ecMap[$cid]['parent_id']) ? (int)$_ecMap[$cid]['parent_id'] : 0;
                }
            }

            // 收集品項（排除「不進出庫單」分類 + 無 product_id 的工程項）
            $items = array();
            $totalQty = 0;
            $skippedCount = 0;
            if (!empty($quote['sections'])) {
                foreach ($quote['sections'] as $sec) {
                    if (!empty($sec['items'])) {
                        foreach ($sec['items'] as $item) {
                            $qty = (float)($item['quantity'] ?? 0);
                            if ($qty <= 0) continue;
                            $pid = !empty($item['product_id']) ? (int)$item['product_id'] : null;

                            // 無 product_id → 跳過（手動輸入的工程項）
                            if (!$pid) { $skippedCount++; continue; }

                            // 查該產品的分類是否被排除
                            $catId = $db->prepare("SELECT category_id FROM products WHERE id = ?");
                            $catId->execute(array($pid));
                            $pCatId = (int)$catId->fetchColumn();
                            if ($pCatId && isset($excludedCatIds[$pCatId])) { $skippedCount++; continue; }

                            $items[] = array(
                                'product_id' => $pid,
                                'product_name' => $item['item_name'] ?? '',
                                'model' => $item['model_number'] ?? '',
                                'unit' => $item['unit'] ?? '式',
                                'quantity' => $qty,
                                'unit_price' => (float)($item['unit_price'] ?? 0),
                                'note' => $item['remark'] ?? '',
                            );
                            $totalQty += $qty;
                        }
                    }
                }
            }

            if (empty($items)) {
                $msg = '報價單無可出庫品項';
                if ($skippedCount > 0) $msg .= '（已排除 ' . $skippedCount . ' 項工程項次/非庫存品）';
                Session::flash('error', $msg);
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
                'customer_name' => $quote['customer_name'] ?? null,
                'customer_id' => $quote['customer_id'] ?? null,
                'note' => '報價單 ' . $quote['quotation_number'] . ' 自動產生，' . date('Y-m-d') . '建立',
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
                   p.pack_qty, p.cost_per_unit,
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

    case 'ajax_est_materials':
        header('Content-Type: application/json');
        $estCaseId = (int)($_GET['case_id'] ?? 0);
        if ($estCaseId <= 0) {
            echo json_encode(array('success' => true, 'data' => array()));
            exit;
        }
        $caseModel = new CaseModel();
        echo json_encode(array('success' => true, 'data' => $caseModel->getMaterialEstimates($estCaseId)));
        exit;

    default:
        redirect('/quotations.php?action=list');
        break;
}
