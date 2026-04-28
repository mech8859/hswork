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
            // 同案件允許多張報價（普銷 + 專案分張，由業務從案件頁「+新增另一張報價」進入）
            $quoteId = $model->create($_POST);
            AuditLog::log('quotations', 'create', $quoteId, $_POST['customer_name'] ?? '');
            // 個人預設：勾選才存（以登入者為主）
            $_pt = !empty($_POST['save_payment_terms_default']) ? ($_POST['payment_terms'] ?? '') : null;
            $_nt = !empty($_POST['save_notes_default']) ? ($_POST['notes'] ?? '') : null;
            if ($_pt !== null || $_nt !== null) $model->saveUserDefaults(Auth::id(), $_pt, $_nt);
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
            // 線材成本：有連結案件且未勾「無使用線材」才強制同步（內含 recalcTotals）
            if ($createCaseId > 0 && empty($_POST['cable_not_used'])) {
                $model->syncCableCostFromEstimates($quoteId, $createCaseId);
            } else {
                // 無案件或勾選無使用線材：手動觸發利潤重算以反映最新人力成本
                $_nq = $model->getById($quoteId);
                if ($_nq) {
                    $_mStmt = Database::getInstance()->prepare("SELECT COALESCE(SUM(qi.unit_cost * qi.quantity), 0) FROM quotation_items qi JOIN quotation_sections qs ON qi.section_id = qs.id WHERE qs.quotation_id = ?");
                    $_mStmt->execute(array($quoteId));
                    $model->recalcTotalsPublic($quoteId, (int)$_nq['subtotal'], (int)$_mStmt->fetchColumn());
                }
            }
            Session::flash('success', '報價單已建立');
            redirect('/quotations.php?action=view&id=' . $quoteId);
        }
        $quote = null;
        // 從案件帶入施工天數/人數/時數預設（B 方案：單向 case → quotation 預填）
        // 用獨立變數避免污染 $quote（$quote 為 null 表示「新增」狀態）
        $caseLaborDefaults = null;
        $_preCaseId = (int)($_GET['case_id'] ?? 0);
        if ($_preCaseId > 0) {
            $_preStmt = Database::getInstance()->prepare("SELECT est_labor_days, est_labor_people, est_labor_hours FROM cases WHERE id = ?");
            $_preStmt->execute(array($_preCaseId));
            $_preCase = $_preStmt->fetch(PDO::FETCH_ASSOC);
            if ($_preCase) {
                $caseLaborDefaults = array(
                    'labor_days'       => $_preCase['est_labor_days'],
                    'labor_people'     => $_preCase['est_labor_people'],
                    'labor_unit_hours' => $_preCase['est_labor_hours'],
                );
            }
        }
        $userDefaults = $model->getUserDefaults(Auth::id());
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
            // 個人預設：勾選才存（以登入者為主）
            $_pt = !empty($_POST['save_payment_terms_default']) ? ($_POST['payment_terms'] ?? '') : null;
            $_nt = !empty($_POST['save_notes_default']) ? ($_POST['notes'] ?? '') : null;
            if ($_pt !== null || $_nt !== null) $model->saveUserDefaults(Auth::id(), $_pt, $_nt);
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
            // 線材成本：有連結案件且未勾「無使用線材」才強制同步（內含 recalcTotals）
            if ($editCaseId > 0 && empty($_POST['cable_not_used'])) {
                $model->syncCableCostFromEstimates($id, $editCaseId);
            } else {
                // 無案件或勾選無使用線材：手動觸發利潤重算以反映最新人力成本
                $_nq = $model->getById($id);
                if ($_nq) {
                    $_mStmt = Database::getInstance()->prepare("SELECT COALESCE(SUM(qi.unit_cost * qi.quantity), 0) FROM quotation_items qi JOIN quotation_sections qs ON qi.section_id = qs.id WHERE qs.quotation_id = ?");
                    $_mStmt->execute(array($id));
                    $model->recalcTotalsPublic($id, (int)$_nq['subtotal'], (int)$_mStmt->fetchColumn());
                }
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

        // 舊資料不在檢視時自動補算（避免覆蓋使用者意圖），改於下次編輯儲存時同步
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

        // 必須有關聯案件才能送簽核
        if (empty($quote['case_id'])) {
            Session::flash('error', '送簽核前請先關聯案件');
            redirect('/quotations.php?action=view&id=' . $id);
            break;
        }

        // 檢查狀態
        if (!in_array($quote['status'], array('draft', 'rejected_internal', 'revision_needed'))) {
            Session::flash('error', '此報價單狀態無法送簽核');
            redirect('/quotations.php?action=view&id=' . $id);
            break;
        }

        // 送簽核前檢查：施工天數/施工時數(二擇一) + 施工人數 + 預估使用線材
        $_missing = array();
        $_days       = (float)($quote['labor_days']       ?? 0);
        $_unitHours  = (float)($quote['labor_unit_hours'] ?? 0);
        $_people     = (int)  ($quote['labor_people']     ?? 0);
        if ($_days <= 0 && $_unitHours <= 0) {
            $_missing[] = '施工天數或施工時數（需擇一填寫）';
        }
        if ($_people <= 0) {
            $_missing[] = '施工人數';
        }
        // 預估使用線材：若未勾「無使用線材」則必須有 estimate 紀錄
        if (empty($quote['cable_not_used'])) {
            $_emStmt = Database::getInstance()->prepare('SELECT COUNT(*) FROM case_material_estimates WHERE case_id = ?');
            $_emStmt->execute(array($quote['case_id']));
            $_emCount = (int)$_emStmt->fetchColumn();
            if ($_emCount === 0) {
                $_missing[] = '預估使用線材（或勾選「無使用線材」）';
            }
        }
        if (!empty($_missing)) {
            Session::flash('error', '送簽核前請先填寫：' . implode('、', $_missing));
            redirect('/quotations.php?action=view&id=' . $id);
            break;
        }

        if (verify_csrf()) {
            require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
            $approvalModel = new ApprovalModel();
            $amount = (float)$quote['total_amount'];
            $profitRate = $quote['profit_rate'] ? (float)$quote['profit_rate'] : null;

            // 虧損（profit_rate < 0）必須填寫虧損原因
            if ($profitRate !== null && $profitRate < 0) {
                $lossReason = isset($_POST['loss_reason']) ? trim((string)$_POST['loss_reason']) : '';
                if ($lossReason === '') {
                    Session::flash('error', '虧損報價單送簽核必須填寫虧損原因');
                    redirect('/quotations.php?action=view&id=' . $id);
                    break;
                }
                // 寫入 quotations.loss_reason
                Database::getInstance()->prepare("UPDATE quotations SET loss_reason = ? WHERE id = ?")
                    ->execute(array($lossReason, $id));
            }

            $result = $approvalModel->submitForApproval('quotations', $id, $amount, $profitRate);

            // 寫入簽核 comment（讓簽核人看到原因）
            if ($profitRate !== null && $profitRate < 0 && !empty($result) && is_array($result)) {
                $lossReason = isset($_POST['loss_reason']) ? trim((string)$_POST['loss_reason']) : '';
                if ($lossReason !== '' && empty($result['auto_approved'])) {
                    foreach ($result as $flow) {
                        if (is_array($flow) && !empty($flow['id'])) {
                            Database::getInstance()->prepare("UPDATE approval_flows SET comment = ? WHERE id = ?")
                                ->execute(array('虧損原因：' . $lossReason, $flow['id']));
                        }
                    }
                }
            }

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

            // 「此案件無使用設備」→ 擋建立出庫單
            if (!empty($quote['case_id'])) {
                $_neStmt = Database::getInstance()->prepare('SELECT no_equipment FROM cases WHERE id = ?');
                $_neStmt->execute(array($quote['case_id']));
                if ((int)$_neStmt->fetchColumn() === 1) {
                    Session::flash('error', '此案件標記為「無使用設備」，不需建立出庫單');
                    redirect('/quotations.php?action=view&id=' . $qId);
                }
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

                            // 查該產品的分類 + 名稱/型號（若報價 item 沒存名稱就從 products 回補）
                            $prodStmt = $db->prepare("SELECT category_id, name AS p_name, model AS p_model, unit AS p_unit FROM products WHERE id = ?");
                            $prodStmt->execute(array($pid));
                            $prodRow = $prodStmt->fetch(PDO::FETCH_ASSOC);
                            $pCatId = $prodRow ? (int)$prodRow['category_id'] : 0;
                            if ($pCatId && isset($excludedCatIds[$pCatId])) { $skippedCount++; continue; }

                            $itemName = isset($item['item_name']) && $item['item_name'] !== '' ? $item['item_name'] : ($prodRow['p_name'] ?? '');
                            $itemModel = isset($item['model_number']) && $item['model_number'] !== '' ? $item['model_number'] : ($prodRow['p_model'] ?? '');
                            $itemUnit = isset($item['unit']) && $item['unit'] !== '' ? $item['unit'] : ($prodRow['p_unit'] ?? '式');

                            // 名稱與型號都空 → 出庫明細無法識別，跳過
                            if ($itemName === '' && $itemModel === '') { $skippedCount++; continue; }

                            $items[] = array(
                                'product_id' => $pid,
                                'product_name' => $itemName,
                                'model' => $itemModel,
                                'unit' => $itemUnit,
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

    case 'create_additional_stock_out':
        // 追加出庫單：針對前次出庫單之後新增的項目/追加數量建立新出庫單
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/quotations.php'); }
        if (verify_csrf()) {
            $qId = (int)($_GET['id'] ?? 0);
            $quote = $model->getById($qId);
            if (!$quote || $quote['status'] !== 'customer_accepted') {
                Session::flash('error', '報價單不存在或狀態不正確');
                redirect('/quotations.php');
            }

            $db = Database::getInstance();

            // 必須已建立過出庫單
            $existSo = $db->prepare("SELECT id FROM stock_outs WHERE source_type = 'quotation' AND source_id = ? LIMIT 1");
            $existSo->execute(array($qId));
            if (!$existSo->fetch(PDO::FETCH_ASSOC)) {
                Session::flash('error', '尚未建立過出庫單，請使用「建立出庫單」');
                redirect('/quotations.php?action=view&id=' . $qId);
            }

            // 累加已出庫數量（依 product_id，排除備品 is_spare=1）
            $prevStmt = $db->prepare("
                SELECT soi.product_id, SUM(soi.quantity) AS total_qty
                FROM stock_out_items soi
                JOIN stock_outs so ON so.id = soi.stock_out_id
                WHERE so.source_type = 'quotation' AND so.source_id = ?
                  AND (soi.is_spare IS NULL OR soi.is_spare = 0)
                GROUP BY soi.product_id
            ");
            $prevStmt->execute(array($qId));
            $prevQtyMap = array();
            foreach ($prevStmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
                if (!empty($pr['product_id'])) {
                    $prevQtyMap[(int)$pr['product_id']] = (float)$pr['total_qty'];
                }
            }

            // 取得預設倉庫
            $wh = $db->query("SELECT id FROM warehouses ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $warehouseId = $wh ? (int)$wh['id'] : 0;

            // 被排除的分類（與 create_stock_out 相同邏輯）
            $excludedCatIds = array();
            $_ecAll = $db->query("SELECT id, parent_id, exclude_from_stockout FROM product_categories")->fetchAll(PDO::FETCH_ASSOC);
            $_ecMap = array();
            foreach ($_ecAll as $_ec) { $_ecMap[(int)$_ec['id']] = $_ec; }
            foreach ($_ecMap as $_ecId => $_ec) {
                $cid = $_ecId;
                while ($cid) {
                    if (!empty($_ecMap[$cid]['exclude_from_stockout'])) { $excludedCatIds[$_ecId] = true; break; }
                    $cid = !empty($_ecMap[$cid]['parent_id']) ? (int)$_ecMap[$cid]['parent_id'] : 0;
                }
            }

            // 逐項計算差額，只取正差額
            $items = array();
            $totalQty = 0;
            $skippedCount = 0;
            if (!empty($quote['sections'])) {
                foreach ($quote['sections'] as $sec) {
                    if (empty($sec['items'])) continue;
                    foreach ($sec['items'] as $item) {
                        $qty = (float)($item['quantity'] ?? 0);
                        if ($qty <= 0) continue;
                        $pid = !empty($item['product_id']) ? (int)$item['product_id'] : null;
                        if (!$pid) { $skippedCount++; continue; }

                        $prodStmt = $db->prepare("SELECT category_id, name AS p_name, model AS p_model, unit AS p_unit FROM products WHERE id = ?");
                        $prodStmt->execute(array($pid));
                        $prodRow = $prodStmt->fetch(PDO::FETCH_ASSOC);
                        $pCatId = $prodRow ? (int)$prodRow['category_id'] : 0;
                        if ($pCatId && isset($excludedCatIds[$pCatId])) { $skippedCount++; continue; }

                        $prevQty = isset($prevQtyMap[$pid]) ? $prevQtyMap[$pid] : 0;
                        $diff = $qty - $prevQty;
                        if ($diff <= 0) continue; // 減少或相等 → 忽略（餘料由退庫處理）

                        $itemName = isset($item['item_name']) && $item['item_name'] !== '' ? $item['item_name'] : ($prodRow['p_name'] ?? '');
                        $itemModel = isset($item['model_number']) && $item['model_number'] !== '' ? $item['model_number'] : ($prodRow['p_model'] ?? '');
                        $itemUnit = isset($item['unit']) && $item['unit'] !== '' ? $item['unit'] : ($prodRow['p_unit'] ?? '式');
                        if ($itemName === '' && $itemModel === '') { $skippedCount++; continue; }

                        $items[] = array(
                            'product_id' => $pid,
                            'product_name' => $itemName,
                            'model' => $itemModel,
                            'unit' => $itemUnit,
                            'quantity' => $diff,
                            'unit_price' => (float)($item['unit_price'] ?? 0),
                            'note' => $item['remark'] ?? '',
                        );
                        $totalQty += $diff;
                    }
                }
            }

            if (empty($items)) {
                Session::flash('error', '無可追加項目（現有數量未超過已出庫數量）');
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
                'note' => '報價單 ' . $quote['quotation_number'] . ' 追加出庫，' . date('Y-m-d') . '建立',
                'total_qty' => $totalQty,
                'created_by' => Auth::id(),
                'items' => $items,
            ));

            AuditLog::log('stock_outs', 'create', $soId, '從報價單 ' . $quote['quotation_number'] . ' 建立追加出庫單');
            Session::flash('success', '追加出庫單已建立');
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
                   p.pack_qty, p.cost_per_unit, p.discontinue_when_empty,
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

    case 'ajax_case_lookup':
        // 由 case_number 查 case_id
        header('Content-Type: application/json');
        $cNum = trim($_GET['case_number'] ?? '');
        if ($cNum === '') { echo json_encode(array('success' => false)); exit; }
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM cases WHERE case_number = ? LIMIT 1");
        $stmt->execute(array($cNum));
        $cid = $stmt->fetchColumn();
        echo json_encode(array('success' => (bool)$cid, 'case_id' => $cid ? (int)$cid : 0));
        exit;

    case 'ajax_case_info':
        // 取得案件基本資料 + 是否已有報價單（排除當前編輯中的報價單）
        header('Content-Type: application/json');
        $qCaseId = (int)($_GET['case_id'] ?? 0);
        $excludeQid = (int)($_GET['exclude_qid'] ?? 0);
        if ($qCaseId <= 0) {
            echo json_encode(array('success' => false));
            exit;
        }
        $db = Database::getInstance();
        $cStmt = $db->prepare("
            SELECT c.case_number, c.title, c.customer_name, c.customer_phone, c.customer_mobile,
                   c.contact_person, c.contact_address, c.construction_area,
                   c.billing_title, c.billing_tax_id,
                   c.est_labor_days, c.est_labor_people, c.est_labor_hours
            FROM cases c WHERE c.id = ?
        ");
        $cStmt->execute(array($qCaseId));
        $c = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) {
            echo json_encode(array('success' => false, 'error' => '查無此案件'));
            exit;
        }
        $qStmt = $db->prepare("SELECT id, quotation_number, status FROM quotations WHERE case_id = ? AND id != ? ORDER BY id DESC LIMIT 1");
        $qStmt->execute(array($qCaseId, $excludeQid));
        $existing = $qStmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(array(
            'success' => true,
            'case' => array(
                'case_number'     => $c['case_number'],
                'title'           => $c['title'],
                'customer_name'   => $c['customer_name'],
                'contact_person'  => $c['contact_person'],
                'contact_phone'   => $c['customer_phone'] ?: $c['customer_mobile'],
                'site_name'       => $c['title'],
                'site_address'    => $c['contact_address'],
                'invoice_title'   => $c['billing_title'],
                'invoice_tax_id'  => $c['billing_tax_id'],
                'est_labor_days'   => $c['est_labor_days'],
                'est_labor_people' => $c['est_labor_people'],
                'est_labor_hours'  => $c['est_labor_hours'],
            ),
            'existing_quotation' => $existing,
        ));
        exit;

    default:
        redirect('/quotations.php?action=list');
        break;
}
