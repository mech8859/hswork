<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/cases/CaseModel.php';

$model = new CaseModel();
$action = $_GET['action'] ?? 'list';
$branchIds = Auth::getAccessibleBranchIds();

// 金額異動紀錄寫入（表不存在時靜默跳過）
function logAmountChange($caseId, $field, $oldVal, $newVal, $source) {
    if ((int)$oldVal === (int)$newVal) return;
    try {
        $db = Database::getInstance();
        $chk = $db->query("SHOW TABLES LIKE 'case_amount_changes'");
        if (!$chk || $chk->rowCount() === 0) return;
        $user = Session::getUser();
        $db->prepare("INSERT INTO case_amount_changes (case_id, field_name, old_value, new_value, change_source, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute(array($caseId, $field, (int)$oldVal, (int)$newVal, $source, $user ? $user['id'] : 0, $user ? $user['real_name'] : 'system'));
    } catch (Exception $e) {}
}

// 帳款交易合計回寫 total_collected + 訂金金額/方式 + balance_amount(尾款)
function updateTotalCollected($caseId, $changeSource = 'payment') {
    $db = Database::getInstance();
    // 先讀舊值（用於異動紀錄）
    $oldStmt = $db->prepare("SELECT total_collected, balance_amount FROM cases WHERE id = ?");
    $oldStmt->execute(array($caseId));
    $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC);
    $oldCollected = $oldRow ? (int)$oldRow['total_collected'] : 0;
    $oldBalance   = $oldRow ? (int)$oldRow['balance_amount'] : 0;

    // 總收款
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM case_payments WHERE case_id = ?");
    $stmt->execute(array($caseId));
    $total = (int)$stmt->fetchColumn();
    // 匯費合計（客人扣匯費，計算尾款時當作已收）
    $wireStmt = $db->prepare("SELECT COALESCE(SUM(wire_fee), 0) FROM case_payments WHERE case_id = ?");
    $wireStmt->execute(array($caseId));
    $wireTotal = (int)$wireStmt->fetchColumn();
    // 訂金（類別=訂金的應收合計：amount + wire_fee。amount 是實收已扣折讓，加回 wire_fee 才是應收/含稅）
    $depStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) + COALESCE(SUM(wire_fee), 0) FROM case_payments WHERE case_id = ? AND payment_type = '訂金'");
    $depStmt->execute(array($caseId));
    $depositAmount = (int)$depStmt->fetchColumn();
    $depMethodStmt = $db->prepare("SELECT transaction_type FROM case_payments WHERE case_id = ? AND payment_type = '訂金' ORDER BY payment_date DESC, id DESC LIMIT 1");
    $depMethodStmt->execute(array($caseId));
    $depositMethod = $depMethodStmt->fetchColumn() ?: null;
    // 訂金日期
    $depDateStmt = $db->prepare("SELECT payment_date FROM case_payments WHERE case_id = ? AND payment_type = '訂金' ORDER BY payment_date DESC, id DESC LIMIT 1");
    $depDateStmt->execute(array($caseId));
    $depositDate = $depDateStmt->fetchColumn() ?: null;
    // 尾款 = (含稅金額 > 0 ? 含稅金額 : 成交金額) - 總收款金額 - 匯費總額
    $caseStmt = $db->prepare("SELECT deal_amount, total_amount FROM cases WHERE id = ?");
    $caseStmt->execute(array($caseId));
    $caseRow = $caseStmt->fetch(PDO::FETCH_ASSOC);
    $dealAmt  = $caseRow ? (int)$caseRow['deal_amount'] : 0;
    $totalAmt = $caseRow ? (int)$caseRow['total_amount'] : 0;
    $base = $totalAmt > 0 ? $totalAmt : $dealAmt;
    $balance = $base > 0 ? max(0, $base - $total - $wireTotal) : null;
    $db->prepare("UPDATE cases SET total_collected = ?, deposit_amount = ?, deposit_method = ?, deposit_payment_date = ?, balance_amount = ? WHERE id = ?")
        ->execute(array($total, $depositAmount ?: null, $depositMethod, $depositDate, $balance, $caseId));

    // 自動結清：含稅金額 > 0 且 總收款 > 0 且 balance = 0 且尚未結清 → 標已結清
    // 結清日 = 最後一筆 case_payment 日期
    if ($totalAmt > 0 && (int)$balance === 0 && $total > 0) {
        $sStmt = $db->prepare("SELECT settlement_confirmed FROM cases WHERE id = ?");
        $sStmt->execute(array($caseId));
        $settled = (int)$sStmt->fetchColumn();
        if ($settled !== 1) {
            $latestStmt = $db->prepare("SELECT MAX(payment_date) FROM case_payments WHERE case_id = ?");
            $latestStmt->execute(array($caseId));
            $latestDate = $latestStmt->fetchColumn() ?: date('Y-m-d');
            $db->prepare("UPDATE cases SET settlement_confirmed = 1, settlement_date = ? WHERE id = ?")
                ->execute(array($latestDate, $caseId));
        }
    }

    // 記錄異動
    logAmountChange($caseId, 'total_collected', $oldCollected, $total, $changeSource);
    logAmountChange($caseId, 'balance_amount', $oldBalance, (int)$balance, $changeSource);
}


switch ($action) {
    // ---- 案件清單 ----
    case 'list':
        $filters = [
            'status'     => $_GET['status'] ?? '',
            'case_type'  => $_GET['case_type'] ?? '',
            'keyword'    => $_GET['keyword'] ?? '',
            'branch_id'  => $_GET['branch_id'] ?? '',
            'sub_status' => $_GET['sub_status'] ?? '',
            'sales_id'   => $_GET['sales_id'] ?? '',
            'date_from'  => $_GET['date_from'] ?? '',
            'date_to'    => $_GET['date_to'] ?? '',
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $result = $model->getList($branchIds, $filters, $page);
        $branches = $model->getAllBranches();
        $salesUsers = $model->getSalesUsers($branchIds);

        $pageTitle = '案件管理';
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增案件 ----
    case 'create':
        if (!Auth::hasPermission('cases.create')) {
            Session::flash('error', '無新增案件權限，請聯絡管理員設定');
            redirect('/cases.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/cases.php');
            }
            // 必填欄位驗證
            if (empty($_POST['branch_id'])) {
                Session::flash('error', '請選擇所屬分公司');
                redirect('/cases.php?action=create');
            }
            if (empty($_POST['title'])) {
                Session::flash('error', '請填寫案件名稱');
                redirect('/cases.php?action=create');
            }
            // 舊客戶維修案：原案件編號／完工日期／保固日期必填
            if (($_POST['case_type'] ?? '') === 'old_repair') {
                $_repairRequired = array(
                    'repair_original_case'           => '原案件編號',
                    'repair_original_complete_date'  => '原案件完工日期',
                    'repair_original_warranty_date'  => '原案件保固日期',
                );
                foreach ($_repairRequired as $_f => $_l) {
                    if (empty(trim((string)($_POST[$_f] ?? '')))) {
                        Session::flash('error', '案別為「舊客戶維修案」時，' . $_l . '為必填');
                        redirect('/cases.php?action=create&customer_id=' . (int)($_POST['customer_id'] ?? 0) . '&case_type=old_repair');
                    }
                }
            }
            // 已完工 → 是否需修改成交金額必填；選「是」則最後成交金額必填
            if ((string)($_POST['is_completed'] ?? '') === '1') {
                $_fan = isset($_POST['final_amount_needed']) ? trim($_POST['final_amount_needed']) : '';
                if ($_fan !== '是' && $_fan !== '否') {
                    Session::flash('error', '案件已完工，請選擇「是否需修改成交金額」');
                    redirect('/cases.php?action=create');
                }
                if ($_fan === '是' && (float)($_POST['final_deal_amount'] ?? 0) <= 0) {
                    Session::flash('error', '已選擇修改成交金額，請填寫最後成交金額');
                    redirect('/cases.php?action=create');
                }
            }
            $caseId = $model->create($_POST);
            // 完工後最後成交金額：直接 UPDATE 寫入（避免動 model 大型參數清單）
            {
                $_finalChoice = isset($_POST['final_amount_needed']) ? trim($_POST['final_amount_needed']) : '';
                $_finalAmt = ($_finalChoice === '是' && !empty($_POST['final_deal_amount']))
                    ? (float)$_POST['final_deal_amount']
                    : null;
                Database::getInstance()->prepare('UPDATE cases SET final_deal_amount = ? WHERE id = ?')
                    ->execute(array($_finalAmt, $caseId));
            }
            // 舊客戶維修案：自動把原案件的報價單附件複製到新案件「舊客戶報價單」分類
            $copiedCount = 0;
            if (($_POST['case_type'] ?? '') === 'old_repair' && !empty($_POST['customer_id'])) {
                $_db = Database::getInstance();
                $_custStmt = $_db->prepare('SELECT case_number FROM customers WHERE id = ?');
                $_custStmt->execute(array((int)$_POST['customer_id']));
                $_origCaseNumber = $_custStmt->fetchColumn();
                if ($_origCaseNumber) {
                    $_caseStmt = $_db->prepare('SELECT id FROM cases WHERE case_number = ? LIMIT 1');
                    $_caseStmt->execute(array($_origCaseNumber));
                    $_origCaseId = (int)$_caseStmt->fetchColumn();
                    if ($_origCaseId > 0 && $_origCaseId !== $caseId) {
                        // 找「舊客戶報價單」分類 key（自訂分類），取不到就退回 quotation
                        $_typeStmt = $_db->prepare("SELECT option_key FROM dropdown_options WHERE category='case_attach_type' AND label LIKE '%舊客戶報價單%' AND is_active=1 ORDER BY sort_order ASC LIMIT 1");
                        $_typeStmt->execute();
                        $_oldQuoteType = $_typeStmt->fetchColumn() ?: 'quotation';
                        // 取原案件報價單附件（含 quotation 與舊客戶報價單）
                        $_attStmt = $_db->prepare("SELECT file_name, file_path FROM case_attachments WHERE case_id = ? AND file_type IN ('quotation', ?)");
                        $_attStmt->execute(array($_origCaseId, $_oldQuoteType));
                        $_origAtts = $_attStmt->fetchAll(PDO::FETCH_ASSOC);
                        if ($_origAtts) {
                            $_dstDir = __DIR__ . '/uploads/cases/' . $caseId;
                            if (!is_dir($_dstDir)) @mkdir($_dstDir, 0755, true);
                            foreach ($_origAtts as $_a) {
                                $_srcFull = __DIR__ . $_a['file_path'];
                                if (!is_file($_srcFull)) continue;
                                $_safeName = preg_replace('/[^a-zA-Z0-9._\x{4e00}-\x{9fff}-]/u', '', $_a['file_name']);
                                $_newFname = date('Ymd_His') . '_' . mt_rand(1000,9999) . '_' . $_safeName;
                                $_dstFull = $_dstDir . '/' . $_newFname;
                                if (@copy($_srcFull, $_dstFull)) {
                                    $_newRel = '/uploads/cases/' . $caseId . '/' . $_newFname;
                                    $model->saveAttachment($caseId, $_oldQuoteType, $_a['file_name'], $_newRel);
                                    if (function_exists('backup_to_drive')) { backup_to_drive($_dstFull, 'cases', $caseId); }
                                    $copiedCount++;
                                }
                            }
                        }
                    }
                }
            }
            $model->updateReadiness($caseId, $_POST);
            $model->updateSiteConditions($caseId, $_POST);
            if (!empty($_POST['contacts'])) {
                $model->saveContacts($caseId, $_POST['contacts']);
            }
            if (!empty($_POST['required_skills'])) {
                $model->saveRequiredSkills($caseId, $_POST['required_skills']);
            }
            if (isset($_POST['est_materials'])) {
                $model->saveMaterialEstimates($caseId, $_POST['est_materials']);
            }
            $_okMsg = '案件已新增';
            if ($copiedCount > 0) {
                $_okMsg .= '；已自動帶入原案件報價單 ' . $copiedCount . ' 份至「舊客戶報價單」附件';
            }
            Session::flash('success', $_okMsg);
            redirect('/cases.php?action=view&id=' . $caseId);
        }

        $case = null;
        $worklogTimeline = array();
        $branches = $model->getAllBranches();
        $skills = $model->getAllSkills();
        $salesUsers = $model->getSalesUsers($branchIds);
        require_once __DIR__ . '/../modules/settings/DropdownModel.php';
        $ddModel = new DropdownModel();
        $caseCompanyOptions = $ddModel->getOptions('case_company');
        $caseSourceOptions = $ddModel->getOptions('case_source');
        $customerDemandOptions = $ddModel->getOptions('customer_demand');
        $systemTypeOptions = $ddModel->getOptions('system_type');
        $extraCss = array('/css/cases-form.css?v=20260413d');
        $extraJs = array('/js/tw_districts.js');

        // 從客戶管理帶入客戶資料
        $prefillCustomer = null;
        $prefillRepair = null;
        if (!empty($_GET['customer_id'])) {
            require_once __DIR__ . '/../modules/customers/CustomerModel.php';
            $custModel = new CustomerModel();
            $prefillCustomer = $custModel->getById((int)$_GET['customer_id']);
            if ($prefillCustomer) {
                $cs = Database::getInstance()->prepare('SELECT contact_name, phone, role FROM customer_contacts WHERE customer_id = ? LIMIT 10');
                $cs->execute(array($prefillCustomer['id']));
                $prefillCustomer['contacts'] = $cs->fetchAll(PDO::FETCH_ASSOC);
                // 維修案前置：把客戶的「進件編號／完工日期／保固日期」帶入新案件的維修案資料
                $prefillRepair = array(
                    'case_number'     => $prefillCustomer['case_number'] ?? '',
                    'completion_date' => $prefillCustomer['completion_date'] ?? '',
                    'warranty_date'   => $prefillCustomer['warranty_date'] ?? '',
                );
            }
        }

        $contactCount = $prefillCustomer && !empty($prefillCustomer['contacts']) ? count($prefillCustomer['contacts']) : 0;
        $extraHeadHtml = '<script>var CASE_DATA={contactCount:' . $contactCount . ',caseId:0};</script>';

        $pageTitle = '新增案件';
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯案件 ----
    case 'edit':
        // 避免瀏覽器拿到舊版 HTML
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        $id = (int)($_GET['id'] ?? 0);
        // 結案鎖：載入前先做懶式 timeout 重鎖（解鎖逾 30 分鐘自動回鎖）
        autoRelockCaseIfExpired($id);
        $case = $model->getById($id);
        if (!$case) {
            Session::flash('error', '案件不存在');
            redirect('/cases.php');
        }

        // 權限判定：高層 / cases.manage 可編；own/assist/view 可看
        // 注意：使用 $caseCanEdit 避免與表單裡的 $canEdit 區段陣列衝突
        $cu = Auth::user();
        $isAdmin = in_array($cu['role'], array('boss', 'manager', 'vice_president'));
        $hasManage = Auth::hasPermission('cases.manage');
        $hasView = Auth::hasPermission('cases.view') || $hasManage;
        $hasOwn = Auth::hasPermission('cases.own');
        $hasAssist = Auth::hasPermission('cases.assist');
        $isOwn = ((int)($case['sales_id'] ?? 0) === (int)Auth::id());

        if ($isAdmin || $hasManage) {
            $caseCanEdit = true;
        } elseif ($hasOwn) {
            $caseCanEdit = $isOwn;
        } else {
            $caseCanEdit = false;
        }
        if ($isAdmin || $hasManage || $hasView) {
            $caseCanView = true;
        } elseif (($hasOwn && $isOwn) || $hasAssist) {
            $caseCanView = true;
        } else {
            $caseCanView = false;
        }
        if (!$caseCanView) {
            Session::flash('error', '權限不足');
            redirect('/cases.php');
        }

        // 結案鎖：判定鎖定狀態，覆蓋 $caseCanEdit
        $caseLockState = getCaseLockState($case);
        if ($caseLockState['locked']) {
            // 鎖定中 → 任何人（含 boss/vp）都不能直接編輯，必須先解鎖
            $caseCanEdit = false;
        }
        // 提供模板使用：是否可看到「解鎖」按鈕
        $caseCanUnlock = canUnlockCase();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 後端守門：無編輯權限不能 POST
            if (!$caseCanEdit) {
                $errMsg = $caseLockState['locked'] ? '此案件已完工結案並上鎖，請先解鎖再編輯' : '無編輯權限，僅可檢視';
                Session::flash('error', $errMsg);
                redirect('/cases.php?action=edit&id=' . $id);
            }
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/cases.php?action=edit&id=' . $id);
            }
            // 後端校正：未稅(不開發票) → 強制清除稅金/含稅金額，尾款用成交金額算
            if (isset($_POST['is_tax_included']) && $_POST['is_tax_included'] === '未稅(不開發票)') {
                $_POST['tax_amount'] = '0';
                $_POST['total_amount'] = $_POST['deal_amount'] ?? '0';
                $deal = (int)str_replace(',', '', $_POST['deal_amount'] ?? '0');
                $collected = (int)str_replace(',', '', $_POST['total_collected'] ?? '0');
                $_POST['balance_amount'] = (string)($deal - $collected);
            }
            // 未成交狀態 → 業務備註必填
            $lostStatuses = array('lost', 'breach', 'customer_cancel');
            $lostSubStatuses = array('已報價無意願', '無效', '客戶毀約');
            $postStatus = isset($_POST['status']) ? $_POST['status'] : '';
            $postSubStatus = isset($_POST['sub_status']) ? $_POST['sub_status'] : '';
            $postSalesNote = isset($_POST['sales_note']) ? trim($_POST['sales_note']) : '';
            if ((in_array($postStatus, $lostStatuses) || in_array($postSubStatus, $lostSubStatuses)) && $postSalesNote === '') {
                Session::flash('error', '此案件狀態需填寫業務備註（未成交原因）');
                redirect('/cases.php?action=edit&id=' . $id);
            }
            // 舊客戶維修案：原案件編號／完工日期／保固日期必填
            if (($_POST['case_type'] ?? '') === 'old_repair') {
                $_repairRequired = array(
                    'repair_original_case'           => '原案件編號',
                    'repair_original_complete_date'  => '原案件完工日期',
                    'repair_original_warranty_date'  => '原案件保固日期',
                );
                foreach ($_repairRequired as $_f => $_l) {
                    if (empty(trim((string)($_POST[$_f] ?? '')))) {
                        Session::flash('error', '案別為「舊客戶維修案」時，' . $_l . '為必填');
                        redirect('/cases.php?action=edit&id=' . $id);
                    }
                }
            }
            // 已完工 → 是否需修改成交金額必填；選「是」則最後成交金額必填
            if ((string)($_POST['is_completed'] ?? '') === '1') {
                $_fan = isset($_POST['final_amount_needed']) ? trim($_POST['final_amount_needed']) : '';
                if ($_fan !== '是' && $_fan !== '否') {
                    Session::flash('error', '案件已完工，請選擇「是否需修改成交金額」');
                    redirect('/cases.php?action=edit&id=' . $id);
                }
                if ($_fan === '是' && (float)($_POST['final_deal_amount'] ?? 0) <= 0) {
                    Session::flash('error', '已選擇修改成交金額，請填寫最後成交金額');
                    redirect('/cases.php?action=edit&id=' . $id);
                }
            }
            // DEBUG: 記錄 POST 值到檔案
            file_put_contents('/tmp/case_save_debug.txt', date('H:i:s') . ' id=' . $id . ' POST[tax_amount]=' . var_export($_POST['tax_amount'] ?? 'NOT_SET', true) . ' POST[total_amount]=' . var_export($_POST['total_amount'] ?? 'NOT_SET', true) . "\n", FILE_APPEND);
            // 金額異動紀錄：存檔前讀舊值
            $oldCase = $model->getById($id);
            try {
                $model->update($id, $_POST);
            } catch (\RuntimeException $e) {
                Session::flash('error', $e->getMessage());
                redirect('/cases.php?action=edit&id=' . $id);
            }
            // 完工後最後成交金額：直接 UPDATE 寫入
            {
                $_finalChoice = isset($_POST['final_amount_needed']) ? trim($_POST['final_amount_needed']) : '';
                $_finalAmt = ($_finalChoice === '是' && !empty($_POST['final_deal_amount']))
                    ? (float)$_POST['final_deal_amount']
                    : null;
                Database::getInstance()->prepare('UPDATE cases SET final_deal_amount = ? WHERE id = ?')
                    ->execute(array($_finalAmt, $id));
            }
            // 金額異動紀錄：比對新舊值
            if ($oldCase) {
                $amtFields = array('deal_amount', 'total_amount', 'tax_amount');
                foreach ($amtFields as $af) {
                    $ov = (int)str_replace(',', '', isset($oldCase[$af]) ? $oldCase[$af] : '0');
                    $nv = (int)str_replace(',', '', isset($_POST[$af]) ? $_POST[$af] : '0');
                    logAmountChange($id, $af, $ov, $nv, 'manual_edit');
                }
            }
            $model->updateReadiness($id, $_POST);
            $model->updateSiteConditions($id, $_POST);
            if (isset($_POST['contacts'])) {
                $model->saveContacts($id, $_POST['contacts']);
            }
            if (isset($_POST['required_skills'])) {
                $model->saveRequiredSkills($id, $_POST['required_skills']);
            }
            if (isset($_POST['est_materials'])) {
                $model->saveMaterialEstimates($id, $_POST['est_materials']);
            }
            // 嘗試自動結案（手動編輯後若四項條件都到位）
            tryAutoCloseCase($id);
            // 結案鎖：若案件已是 closed 且乾淨（balance=0 + 結清 + 完工日齊），自動補鎖
            if (function_exists('lockCaseIfClean')) {
                lockCaseIfClean($id);
            }
            Session::flash('success', '案件已更新');
            redirect('/cases.php?action=view&id=' . $id);
        }

        // 載入排工回報（work_logs + photos + materials）
        require_once __DIR__ . '/../modules/schedule/WorklogModel.php';
        $_wlModel = new WorklogModel();
        $worklogTimeline = $_wlModel->getCaseTimeline($id);
        $contacts = $case['contacts'] ?? array();
        $branches = $model->getAllBranches();
        $skills = $model->getAllSkills();
        $salesUsers = $model->getSalesUsers($branchIds);
        require_once __DIR__ . '/../modules/settings/DropdownModel.php';
        $ddModel = new DropdownModel();
        $caseCompanyOptions = $ddModel->getOptions('case_company');
        $caseSourceOptions = $ddModel->getOptions('case_source');
        $customerDemandOptions = $ddModel->getOptions('customer_demand');
        $systemTypeOptions = $ddModel->getOptions('system_type');
        $extraCss = array('/css/cases-form.css?v=20260413d');
        $extraJs = array('/js/tw_districts.js');
        $extraHeadHtml = '<script>var CASE_DATA={contactCount:' . count($contacts) . ',caseId:' . $case['id'] . '};</script>';

        // 查詢該案件的報價單與出庫單狀態（純顯示用）
        $caseStockOutStatus = array('quote_count' => 0, 'stockout_count' => 0, 'stockouts' => array());
        $_csoStmt = Database::getInstance()->prepare("
            SELECT COUNT(DISTINCT q.id) AS quote_count
            FROM quotations q WHERE q.case_id = ?
        ");
        $_csoStmt->execute(array($id));
        $caseStockOutStatus['quote_count'] = (int)$_csoStmt->fetchColumn();
        // 取出庫單明細（含單號、狀態、ID）
        $_soStmt = Database::getInstance()->prepare("
            SELECT so.id, so.so_number, so.status
            FROM stock_outs so
            JOIN quotations q ON so.source_type = 'quotation' AND so.source_id = q.id
            WHERE q.case_id = ?
            ORDER BY so.id DESC
        ");
        $_soStmt->execute(array($id));
        $caseStockOutStatus['stockouts'] = $_soStmt->fetchAll(PDO::FETCH_ASSOC);
        $caseStockOutStatus['stockout_count'] = count($caseStockOutStatus['stockouts']);

        // ===== 案件利潤分析 =====
        $_paDb = Database::getInstance();
        $caseProfitAnalysis = array(
            'has_quotation' => false,
            'deal_amount' => (int)($case['deal_amount'] ?? 0),
            'final_deal_amount' => isset($case['final_deal_amount']) && $case['final_deal_amount'] !== null && (float)$case['final_deal_amount'] > 0
                ? (float)$case['final_deal_amount'] : null,
            // 報價預估
            'q_material_cost' => 0, 'q_labor_hours' => 0, 'q_labor_cost' => 0,
            'q_labor_days' => 0, 'q_labor_people' => 0, 'q_cable_cost' => 0,
            // 線材預估
            'est_cable_cost' => 0,
            // 實際數據
            'actual_equipment' => 0, 'actual_cable' => 0, 'actual_consumable' => 0,
            'actual_total_minutes' => 0,
            // 營運成本比例
            'op_rate' => 10,
            // 人力來源標記
            'labor_source' => '',
        );
        try {
            // 1) 報價單成本數據
            $_qStmt = $_paDb->prepare("
                SELECT labor_days, labor_people, labor_hours, labor_cost_total, cable_cost,
                       total_cost, profit_amount, profit_rate, subtotal
                FROM quotations WHERE case_id = ? AND status NOT IN ('draft')
                ORDER BY created_at DESC LIMIT 1
            ");
            $_qStmt->execute(array($id));
            $_qData = $_qStmt->fetch(PDO::FETCH_ASSOC);
            if ($_qData && (float)($_qData['labor_hours'] ?? 0) > 0) {
                $caseProfitAnalysis['has_quotation'] = true;
                $caseProfitAnalysis['q_cable_cost'] = (int)($_qData['cable_cost'] ?? 0);
                $caseProfitAnalysis['q_material_cost'] = (int)($_qData['total_cost'] ?? 0) - (int)($_qData['labor_cost_total'] ?? 0) - (int)($_qData['cable_cost'] ?? 0);
                $caseProfitAnalysis['q_labor_hours'] = (float)($_qData['labor_hours'] ?? 0);
                $caseProfitAnalysis['q_labor_cost'] = (int)($_qData['labor_cost_total'] ?? 0);
                $caseProfitAnalysis['q_labor_days'] = (float)($_qData['labor_days'] ?? 0);
                $caseProfitAnalysis['q_labor_people'] = (int)($_qData['labor_people'] ?? 0);
                $caseProfitAnalysis['labor_source'] = 'quotation';
            } elseif ($_qData) {
                // 有報價單但無人力數據，仍標記有報價單（材料成本等）
                $caseProfitAnalysis['has_quotation'] = true;
                $caseProfitAnalysis['q_cable_cost'] = (int)($_qData['cable_cost'] ?? 0);
                $caseProfitAnalysis['q_material_cost'] = (int)($_qData['total_cost'] ?? 0) - (int)($_qData['labor_cost_total'] ?? 0) - (int)($_qData['cable_cost'] ?? 0);
            }
            // Fallback: 報價單無人力數據時，讀案件預估值
            if ($caseProfitAnalysis['q_labor_hours'] == 0) {
                $_estDays = (float)($case['est_labor_days'] ?? 0);
                $_estPeople = (int)($case['est_labor_people'] ?? 0);
                $_estHours = (float)($case['est_labor_hours'] ?? 0);
                if ($_estHours > 0 || ($_estDays > 0 && $_estPeople > 0)) {
                    if ($_estHours == 0) $_estHours = $_estDays * $_estPeople * 8;
                    $caseProfitAnalysis['q_labor_hours'] = $_estHours;
                    $caseProfitAnalysis['q_labor_days'] = $_estDays;
                    $caseProfitAnalysis['q_labor_people'] = $_estPeople;
                    // 人力成本稍後用系統時薪計算（讀完 labor_hourly_cost 後）
                    $caseProfitAnalysis['labor_source'] = 'case';
                }
            }
            // 2) 實際材料成本（by type）
            // 聰明去重：同案件 + 同產品 + 同數量 + 同日（schedule_date）→ 視為重複，只算一次
            // 跨日（多日施工）即使品項數量相同也合計（例如 5/7 用 5 條 + 5/8 又用 5 條 → 累計 10 條）
            // 成本優先用 products.cost（內部成本），沒對應 product_id 才退回 mu.unit_cost
            $_muRawStmt = $_paDb->prepare("
                SELECT mu.material_type, mu.product_id, mu.material_name,
                       mu.used_qty, mu.unit_cost,
                       COALESCE(p.cost, mu.unit_cost) AS cost_per_unit,
                       wl.updated_at, s.id AS schedule_id, s.schedule_date
                FROM material_usage mu
                JOIN work_logs wl ON mu.work_log_id = wl.id
                JOIN schedules s ON wl.schedule_id = s.id
                LEFT JOIN products p ON mu.product_id = p.id
                WHERE s.case_id = ?
                ORDER BY s.schedule_date, s.id, wl.updated_at DESC, wl.id DESC
            ");
            $_muRawStmt->execute(array($id));
            $_costByType = array('equipment' => 0.0, 'cable' => 0.0, 'consumable' => 0.0);
            $_seenKey = array();
            foreach ($_muRawStmt->fetchAll(PDO::FETCH_ASSOC) as $_mu) {
                $itemKey = !empty($_mu['product_id']) ? 'p_'.$_mu['product_id'] : 'n_'.$_mu['material_name'];
                $qtyKey  = round((float)$_mu['used_qty'], 2);
                $dateKey = $_mu['schedule_date'] ?: '0';
                $k = $dateKey . '|' . $itemKey . '|' . $qtyKey;
                if (isset($_seenKey[$k])) continue;
                $_seenKey[$k] = true;
                $t = $_mu['material_type'];
                if (isset($_costByType[$t])) {
                    $_costByType[$t] += (float)$_mu['used_qty'] * (float)$_mu['cost_per_unit'];
                }
            }
            $caseProfitAnalysis['actual_equipment']  = (int)$_costByType['equipment'];
            $caseProfitAnalysis['actual_cable']      = (int)$_costByType['cable'];
            $caseProfitAnalysis['actual_consumable'] = (int)$_costByType['consumable'];
            // 3) 實際工時
            $_whStmt = $_paDb->prepare("
                SELECT SUM(TIMESTAMPDIFF(MINUTE, wl.arrival_time, wl.departure_time)) AS total_minutes
                FROM work_logs wl
                JOIN schedules s ON wl.schedule_id = s.id
                WHERE s.case_id = ? AND wl.arrival_time IS NOT NULL AND wl.departure_time IS NOT NULL
            ");
            $_whStmt->execute(array($id));
            $caseProfitAnalysis['actual_total_minutes'] = (int)$_whStmt->fetchColumn();
            // 4) 線材預估成本
            $_ecStmt = $_paDb->prepare("
                SELECT SUM(cme.estimated_qty * COALESCE(p.cost, 0)) AS est_cable_cost
                FROM case_material_estimates cme
                LEFT JOIN products p ON cme.product_id = p.id
                WHERE cme.case_id = ?
            ");
            $_ecStmt->execute(array($id));
            $caseProfitAnalysis['est_cable_cost'] = (int)$_ecStmt->fetchColumn();
            // 5) 營運成本（支援兩種模式：labor_ratio=人力成本×比率, deal_ratio=成交金額×比率）
            $_opModeStmt = $_paDb->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'operation_cost_mode' LIMIT 1");
            $_opModeStmt->execute();
            $caseProfitAnalysis['op_mode'] = $_opModeStmt->fetchColumn() ?: 'labor_ratio';
            $_opStmt = $_paDb->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'operation_cost_rate' LIMIT 1");
            $_opStmt->execute();
            $_opVal = $_opStmt->fetchColumn();
            if ($_opVal !== false && $_opVal !== null) {
                $caseProfitAnalysis['op_rate'] = (float)$_opVal;
            }
            // 6) 人力時薪（from system_settings，預設 361）
            $_hrStmt = $_paDb->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'labor_hourly_cost' LIMIT 1");
            $_hrStmt->execute();
            $_hrVal = $_hrStmt->fetchColumn();
            $caseProfitAnalysis['labor_hourly_cost'] = ($_hrVal !== false && $_hrVal !== null) ? (int)$_hrVal : 560;
            // 6b) 案件預估人力成本補算（用系統時薪）
            if ($caseProfitAnalysis['labor_source'] === 'case' && $caseProfitAnalysis['q_labor_cost'] == 0) {
                $caseProfitAnalysis['q_labor_cost'] = (int)round($caseProfitAnalysis['q_labor_hours'] * $caseProfitAnalysis['labor_hourly_cost']);
            }
            // 7) 收款單已收金額
            $_rcStmt = $_paDb->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM receipts WHERE case_id = ? AND status != '作廢'");
            $_rcStmt->execute(array($id));
            $caseProfitAnalysis['total_collected'] = (int)$_rcStmt->fetchColumn();
            // 8) 採購/進貨成本（從出庫單或進貨單關聯）
            $_poStmt = $_paDb->prepare("
                SELECT COALESCE(SUM(soi.qty * soi.unit_cost), 0)
                FROM stockout_items soi
                JOIN stockouts so ON soi.stockout_id = so.id
                WHERE so.case_id = ? AND so.status != '已取消'
            ");
            $_poStmt->execute(array($id));
            $caseProfitAnalysis['actual_stockout_cost'] = (int)$_poStmt->fetchColumn();
        } catch (Exception $e) {
            // 查詢失敗不影響頁面
        }

        // 編輯鎖定（多人同時編輯提醒，純警示不阻擋）
        require_once __DIR__ . '/../includes/EditingLock.php';
        $_curUser = Auth::user();
        if ($_curUser && $id > 0) {
            EditingLock::set('cases', $id, $_curUser['id'], $_curUser['real_name']);
        }
        $otherEditors = ($id > 0) ? EditingLock::getOthers('cases', $id, Auth::id()) : array();
        $editingLockModule = 'cases';
        $editingLockRecordId = $id;

        $pageTitle = $caseCanEdit ? '編輯案件' : '檢視案件';
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 案件詳情 ----
    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        $case = $model->getById($id);
        if (!$case) {
            Session::flash('error', '案件不存在');
            redirect('/cases.php');
        }

        $pageTitle = $case['case_number'] . ' - ' . $case['title'];
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除案件 ----
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/cases.php');
            }
            if (!Auth::hasPermission('cases.delete')) {
                Session::flash('error', '無刪除權限');
                redirect('/cases.php');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $model->deleteCase($id);
                Session::flash('success', '案件已刪除');
            }
        }
        redirect('/cases.php');
        break;

    // ---- AJAX: 取得預估材料 ----
    case 'get_material_estimates':
        header('Content-Type: application/json');
        $caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
        echo json_encode(array('success' => true, 'data' => $model->getMaterialEstimates($caseId)));
        exit;

    // ---- AJAX: 搜尋產品（線材&配件）----
    case 'search_products':
        header('Content-Type: application/json');
        $keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
        if (mb_strlen($keyword) < 1) { echo json_encode(array()); exit; }
        $db = Database::getInstance();
        $like = '%' . $keyword . '%';
        // 限定分類：所有打勾「預計線材顯示」的分類 + 子孫分類
        $catIds = array();
        try {
            $rootStmt = $db->query("SELECT id FROM product_categories WHERE show_in_material_estimate = 1");
            $rootIds = array();
            while ($rid = $rootStmt->fetchColumn()) { $rootIds[] = (int)$rid; }
            // 遞迴展開子孫
            $queue = $rootIds;
            $catIds = $rootIds;
            while (!empty($queue)) {
                $parentId = array_shift($queue);
                $cStmt = $db->prepare("SELECT id FROM product_categories WHERE parent_id = ?");
                $cStmt->execute(array($parentId));
                while ($cid = $cStmt->fetchColumn()) {
                    $cid = (int)$cid;
                    if (!in_array($cid, $catIds, true)) {
                        $catIds[] = $cid;
                        $queue[] = $cid;
                    }
                }
            }
        } catch (Exception $e) {
            // 欄位不存在等錯誤 → 走 fallback
            $catIds = array();
        }
        if (empty($catIds)) {
            // fallback: 搜全部
            $stmt = $db->prepare("SELECT id, name, model AS model_number, unit, price FROM products WHERE is_active = 1 AND (name LIKE ? OR model LIKE ? OR brand LIKE ?) ORDER BY name LIMIT 15");
            $stmt->execute(array($like, $like, $like));
        } else {
            $placeholders = implode(',', array_fill(0, count($catIds), '?'));
            $params = array_merge(array($like, $like, $like), $catIds);
            $stmt = $db->prepare("SELECT id, name, model AS model_number, unit, price FROM products WHERE is_active = 1 AND (name LIKE ? OR model LIKE ? OR brand LIKE ?) AND category_id IN ({$placeholders}) ORDER BY name LIMIT 15");
            $stmt->execute($params);
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;

    // ---- AJAX: 取得帳款交易 ----
    case 'get_payment':
        header('Content-Type: application/json');
        $pid = (int)($_GET['id'] ?? 0);
        $stmt = Database::getInstance()->prepare('SELECT * FROM case_payments WHERE id = ?');
        $stmt->execute(array($pid));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($data ? array('success' => true, 'data' => $data) : array('success' => false, 'error' => '找不到紀錄'));
        break;

    // ---- 送出無訂金排工簽核 ----
    case 'submit_no_deposit_approval':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/cases.php'); }
        if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/cases.php'); }
        $caseId = (int)($_POST['case_id'] ?? 0);
        if ($caseId > 0) {
            try {
                require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
                $appModel = new ApprovalModel();
                $result = $appModel->submitNoDepositSchedule($caseId, Auth::id());
                if (!empty($result['auto_approved'])) {
                    Session::flash('success', '此案件不需簽核，可直接排工');
                } elseif (!empty($result['error'])) {
                    Session::flash('error', '送簽失敗：' . $result['error']);
                } else {
                    Session::flash('success', '已送出無訂金排工簽核');
                    AuditLog::log('cases', 'submit_no_deposit_approval', $caseId, '送出無訂金排工簽核');
                }
            } catch (Exception $e) {
                Session::flash('error', '送簽失敗：' . $e->getMessage());
            }
        }
        redirect('/cases.php?action=edit&id=' . $caseId);
        break;

    // ---- AJAX: 新增帳款交易 ----
    case 'add_payment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        // 結案鎖：擋住對已上鎖案件的新增帳款（不影響未結案案件）
        $_addPayCaseId = (int)($_POST['case_id'] ?? 0);
        if ($_addPayCaseId > 0 && function_exists('assertCaseNotLocked')) {
            try { assertCaseNotLocked($_addPayCaseId, '新增帳款交易'); }
            catch (\RuntimeException $_lockEx) {
                echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage()));
                break;
            }
        }
        try {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $payDate = $_POST['payment_date'] ?? '';
        $payType = $_POST['payment_type'] ?? '';
        $payMethod = $_POST['transaction_type'] ?? '';
        $payAmount = (int)($_POST['amount'] ?? 0);
        $payUntaxed = (int)($_POST['untaxed_amount'] ?? 0);
        $payTax = (int)($_POST['tax_amount'] ?? 0);
        $payNote = $_POST['note'] ?? '';
        $payReceiptNo = $_POST['receipt_number'] ?? null;
        // 匯費僅限會計人員 / 會計主管 / boss 可填
        $__addRole = Auth::user() ? Auth::user()['role'] : '';
        $payWireFee = (in_array($__addRole, array('boss','accounting_supervisor','accountant'), true))
                      ? (int)($_POST['wire_fee'] ?? 0) : 0;

        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT INTO case_payments (case_id, payment_date, payment_type, transaction_type, amount, untaxed_amount, tax_amount, wire_fee, receipt_number, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($caseId, $payDate, $payType, $payMethod, $payAmount, $payUntaxed, $payTax, $payWireFee, $payReceiptNo, $payNote, Auth::id()));
        $newId = (int)$db->lastInsertId();

        // Handle images
        if (!empty($_FILES['images']['name'][0])) {
            $imgPaths = array();
            $dir = __DIR__ . '/uploads/cases/' . $caseId;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $fname = date('Ymd_His') . '_pay_' . $newId . '_' . $i . '.' . pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                move_uploaded_file($tmp, $dir . '/' . $fname);
                $imgPaths[] = 'uploads/cases/' . $caseId . '/' . $fname;
            }
            if ($imgPaths) {
                $db->prepare('UPDATE case_payments SET image_path = ? WHERE id = ?')->execute(array(json_encode($imgPaths), $newId));
            }
        }

        // 自動建立收款單（拋轉待確認）— 若使用者沒有手動填收款單號才建立
        $generatedReceiptNo = null;
        if (empty($payReceiptNo)) {
            try {
                // customer_no 優先用 cases 自身，空時 fallback 到 customers 主檔（避免關聯客戶但 customer_no 沒同步寫進來）
                $caseStmt = $db->prepare('
                    SELECT c.case_number, c.customer_id,
                           COALESCE(NULLIF(c.customer_no, ""), cu.customer_no) AS customer_no,
                           c.customer_name, c.sales_id, c.branch_id
                    FROM cases c
                    LEFT JOIN customers cu ON c.customer_id = cu.id
                    WHERE c.id = ?
                ');
                $caseStmt->execute(array($caseId));
                $caseRow = $caseStmt->fetch(PDO::FETCH_ASSOC);
                if ($caseRow) {
                    require_once __DIR__ . '/../modules/finance/FinanceModel.php';
                    $finModel = new FinanceModel();
                    $receiptData = array(
                        'register_date'    => $payDate,
                        'deposit_date'     => $payDate,
                        'customer_name'    => $caseRow['customer_name'],
                        'case_id'          => $caseId,
                        'case_number'      => $caseRow['case_number'],
                        'customer_no'      => $caseRow['customer_no'],
                        'sales_id'         => $caseRow['sales_id'],
                        'branch_id'        => $caseRow['branch_id'],
                        'receipt_method'   => $payMethod,
                        'invoice_category' => $payType,
                        'status'           => '拋轉待確認',
                        'bank_ref'         => null,
                        'subtotal'         => $payUntaxed,
                        'tax'              => $payTax,
                        'discount'         => 0,
                        'total_amount'     => $payAmount,
                        'note'             => '案件帳款自動產生 - ' . $payType . ($payNote ? ' / ' . $payNote : ''),
                        'created_by'       => Auth::id(),
                    );
                    $receiptId = $finModel->createReceipt($receiptData);
                    // 取得新建收款單的單號
                    $rn = $db->prepare('SELECT receipt_number FROM receipts WHERE id = ?');
                    $rn->execute(array($receiptId));
                    $generatedReceiptNo = $rn->fetchColumn();
                    if ($generatedReceiptNo) {
                        // 回寫至案件帳款交易
                        $db->prepare('UPDATE case_payments SET receipt_number = ? WHERE id = ?')
                           ->execute(array($generatedReceiptNo, $newId));
                    }
                }
            } catch (Exception $e) {
                // 收款單建立失敗不影響帳款交易，記錯誤
                error_log('auto create receipt failed: ' . $e->getMessage());
            }
        }

        updateTotalCollected($caseId, 'payment_add');

        // 通知派發：自動建立的收款單也發通知
        if ($generatedReceiptNo && isset($receiptData) && isset($receiptId)) {
            try {
                require_once __DIR__ . '/../modules/notifications/NotificationDispatcher.php';
                $receiptData['id'] = $receiptId;
                $receiptData['receipt_number'] = $generatedReceiptNo;
                NotificationDispatcher::dispatch('receipts', 'created', $receiptData, Auth::id());
            } catch (Exception $ne) {}
        }

        echo json_encode(array('success' => true, 'receipt_number' => $generatedReceiptNo));
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    // ---- AJAX: 編輯帳款交易（僅 boss）----
    case 'edit_payment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        // 權限保護：只有 boss 可編輯（前端 readonly + 後端雙重檢查）
        $__editUser = Auth::user();
        if (!$__editUser || !in_array($__editUser['role'], array('boss','accounting_supervisor'), true)) {
            echo json_encode(array('success' => false, 'error' => '無編輯權限，僅系統管理者或會計主管可修改已存的帳款交易'));
            break;
        }
        try {
        $pid = (int)($_POST['payment_id'] ?? 0);
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM case_payments WHERE id = ?');
        $stmt->execute(array($pid));
        $pay = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pay) { echo json_encode(array('success' => false, 'error' => '找不到紀錄')); break; }
        // 結案鎖：擋住對已上鎖案件的帳款編輯（不影響未結案案件）
        if (!empty($pay['case_id']) && function_exists('assertCaseNotLocked')) {
            try { assertCaseNotLocked((int)$pay['case_id'], '修改帳款交易'); }
            catch (\RuntimeException $_lockEx) {
                echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage()));
                break;
            }
        }

        $newDate = $_POST['payment_date'] ?? '';
        $newType = $_POST['payment_type'] ?? '';
        $newMethod = $_POST['transaction_type'] ?? '';
        $newAmount = (int)($_POST['amount'] ?? 0);
        $newUntaxed = (int)($_POST['untaxed_amount'] ?? 0);
        $newTax = (int)($_POST['tax_amount'] ?? 0);
        // ⚠ 連動保護：receipt_number 永遠不可修改（用原值），避免破壞與 receipts 表的連動
        $newReceiptNo = $pay['receipt_number'];
        $newNote = $_POST['note'] ?? '';
        // 匯費（已被外層 boss-only 守住，此處僅做型別轉換；未送則保留原值）
        $newWireFee = isset($_POST['wire_fee']) ? (int)$_POST['wire_fee'] : (int)$pay['wire_fee'];

        $db->prepare('UPDATE case_payments SET payment_date=?, payment_type=?, transaction_type=?, amount=?, untaxed_amount=?, tax_amount=?, wire_fee=?, note=? WHERE id=?')
            ->execute(array($newDate, $newType, $newMethod, $newAmount, $newUntaxed, $newTax, $newWireFee, $newNote, $pid));

        // 同步更新對應的收款單（若有 receipt_number）
        // note 格式：「案件帳款自動產生 - {類別} / {使用者備註}」；使用者備註取自 case_payments
        // wire_fee → discount 雙向同步
        if (!empty($newReceiptNo)) {
            try {
                $syncNote = '案件帳款自動產生 - ' . $newType . ($newNote ? ' / ' . $newNote : '');
                $db->prepare("UPDATE receipts SET register_date=?, deposit_date=?, receipt_method=?, invoice_category=?, subtotal=?, tax=?, total_amount=?, discount=?, note=? WHERE receipt_number=?")
                   ->execute(array($newDate, $newDate, $newMethod, $newType, $newUntaxed, $newTax, $newAmount, $newWireFee, $syncNote, $newReceiptNo));
            } catch (Exception $e) {
                error_log('sync receipt failed: ' . $e->getMessage());
            }
        }

        // Handle new images
        if (!empty($_FILES['images']['name'][0])) {
            $existing = $pay['image_path'] ? json_decode($pay['image_path'], true) : array();
            if (!is_array($existing)) $existing = $pay['image_path'] ? array($pay['image_path']) : array();
            $dir = __DIR__ . '/uploads/cases/' . $pay['case_id'];
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $fname = date('Ymd_His') . '_pay_' . $pid . '_' . $i . '.' . pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                move_uploaded_file($tmp, $dir . '/' . $fname);
                $existing[] = 'uploads/cases/' . $pay['case_id'] . '/' . $fname;
            }
            $db->prepare('UPDATE case_payments SET image_path = ? WHERE id = ?')->execute(array(json_encode($existing), $pid));
        }
        updateTotalCollected((int)$pay['case_id'], 'payment_edit');
        echo json_encode(array('success' => true));
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    // ---- AJAX: 搜尋銷項發票（僅會計可用，用於補掛舊資料）----
    case 'ajax_search_si':
        header('Content-Type: application/json');
        if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('all')) {
            echo json_encode(array('success' => false, 'error' => '無權限'));
            break;
        }
        $q = trim($_GET['q'] ?? '');
        if ($q === '') { echo json_encode(array('success' => true, 'data' => array())); break; }
        $kw = '%' . $q . '%';
        $db = Database::getInstance();
        // 回傳發票 + 目前連結狀態（含已連結到哪張 case）
        $stmt = $db->prepare("
            SELECT si.id, si.invoice_number, si.invoice_date, si.customer_name, si.customer_tax_id,
                   si.total_amount, si.status, si.reference_type, si.reference_id,
                   CASE WHEN si.reference_type='case' AND si.reference_id REGEXP '^[0-9]+$' THEN c.case_number ELSE NULL END AS linked_case_number,
                   CASE WHEN si.reference_type='case' AND si.reference_id REGEXP '^[0-9]+$' THEN c.id ELSE NULL END AS linked_case_id
            FROM sales_invoices si
            LEFT JOIN cases c ON si.reference_type='case' AND si.reference_id REGEXP '^[0-9]+$' AND c.id = CAST(si.reference_id AS UNSIGNED)
            WHERE si.status != 'voided' AND si.invoice_number LIKE ?
            ORDER BY si.invoice_date DESC, si.id DESC
            LIMIT 30
        ");
        $stmt->execute(array($kw));
        echo json_encode(array('success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)));
        break;

    // ---- AJAX: 連結銷項發票到案件（僅會計可用）----
    case 'link_si_to_case':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('all')) {
            echo json_encode(array('success' => false, 'error' => '無權限'));
            break;
        }
        $caseId = (int)($_POST['case_id'] ?? 0);
        $siId = (int)($_POST['sales_invoice_id'] ?? 0);
        if ($caseId <= 0 || $siId <= 0) { echo json_encode(array('success' => false, 'error' => '參數錯誤')); break; }
        try {
            $db = Database::getInstance();
            // 確認發票存在
            $stmt = $db->prepare("SELECT id, invoice_number, reference_type, reference_id FROM sales_invoices WHERE id = ?");
            $stmt->execute(array($siId));
            $si = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$si) { echo json_encode(array('success' => false, 'error' => '發票不存在')); break; }
            // 保護：已連結其他案件不允許搬
            if ($si['reference_type'] === 'case' && $si['reference_id'] !== '' && $si['reference_id'] !== null) {
                $currentRef = $si['reference_id'];
                $sameCase = false;
                if (preg_match('/^\d+$/', $currentRef) && (int)$currentRef === $caseId) $sameCase = true;
                if (!$sameCase) {
                    echo json_encode(array('success' => false, 'error' => '此發票已連結到其他案件（ref_id=' . $currentRef . '），請先解除'));
                    break;
                }
            }
            // 確認 case 存在
            $cs = $db->prepare("SELECT id, case_number FROM cases WHERE id = ?");
            $cs->execute(array($caseId));
            $case = $cs->fetch(PDO::FETCH_ASSOC);
            if (!$case) { echo json_encode(array('success' => false, 'error' => '案件不存在')); break; }
            // 更新連結
            $db->prepare("UPDATE sales_invoices SET reference_type='case', reference_id=? WHERE id=?")
               ->execute(array((string)$caseId, $siId));
            AuditLog::log('sales_invoices', 'link_to_case', $siId, '連結發票 ' . $si['invoice_number'] . ' 到案件 ' . $case['case_number']);
            echo json_encode(array('success' => true, 'invoice_number' => $si['invoice_number']));
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    // ---- AJAX: 上傳銷項發票憑證（案件端專屬，不同步至銷項發票模組）----
    case 'upload_si_voucher':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $caseId = (int)($_POST['case_id'] ?? 0);
        $siId = (int)($_POST['sales_invoice_id'] ?? 0);
        if ($caseId <= 0 || $siId <= 0) {
            echo json_encode(array('success' => false, 'error' => '參數錯誤'));
            break;
        }
        if (empty($_FILES['file']['tmp_name'])) {
            echo json_encode(array('success' => false, 'error' => '未選取檔案'));
            break;
        }
        try {
            $dir = __DIR__ . '/uploads/cases/' . $caseId . '/sales_invoices';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $origName = $_FILES['file']['name'];
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            $fname = 'si' . $siId . '_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . ($ext ? '.' . $ext : '');
            $target = $dir . '/' . $fname;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
                echo json_encode(array('success' => false, 'error' => '檔案移動失敗'));
                break;
            }
            $relPath = 'uploads/cases/' . $caseId . '/sales_invoices/' . $fname;
            $db = Database::getInstance();
            $ins = $db->prepare("INSERT INTO case_sales_invoice_vouchers (case_id, sales_invoice_id, file_path, file_name, uploaded_by) VALUES (?,?,?,?,?)");
            $ins->execute(array($caseId, $siId, $relPath, $origName, Auth::id()));
            $newId = (int)$db->lastInsertId();
            echo json_encode(array('success' => true, 'id' => $newId, 'file_path' => $relPath, 'file_name' => $origName));
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    // ---- AJAX: 刪除銷項發票憑證 ----
    case 'delete_si_voucher':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $voucherId = (int)($_POST['voucher_id'] ?? 0);
        if ($voucherId <= 0) { echo json_encode(array('success' => false, 'error' => '參數錯誤')); break; }
        try {
            $db = Database::getInstance();
            $row = $db->prepare("SELECT file_path FROM case_sales_invoice_vouchers WHERE id = ?");
            $row->execute(array($voucherId));
            $r = $row->fetch(PDO::FETCH_ASSOC);
            if (!$r) { echo json_encode(array('success' => false, 'error' => '找不到紀錄')); break; }
            $full = __DIR__ . '/' . $r['file_path'];
            if (file_exists($full)) @unlink($full);
            $db->prepare("DELETE FROM case_sales_invoice_vouchers WHERE id = ?")->execute(array($voucherId));
            echo json_encode(array('success' => true));
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    // ---- AJAX: 刪除帳款交易（僅 boss + 連動防呆）----
    case 'delete_payment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        // 權限保護：boss / 會計主管 可刪除
        $__delUser = Auth::user();
        if (!$__delUser || !in_array($__delUser['role'], array('boss','accounting_supervisor'), true)) {
            echo json_encode(array('success' => false, 'error' => '無刪除權限，僅系統管理者或會計主管可刪除已存的帳款交易'));
            break;
        }
        $pid = (int)($_POST['payment_id'] ?? 0);
        $db = Database::getInstance();
        $delStmt = $db->prepare('SELECT case_id, receipt_number FROM case_payments WHERE id = ?');
        $delStmt->execute(array($pid));
        $delRow = $delStmt->fetch(PDO::FETCH_ASSOC);
        if (!$delRow) {
            echo json_encode(array('success' => false, 'error' => '找不到紀錄'));
            break;
        }
        $delCaseId = (int)$delRow['case_id'];
        $delReceiptNo = $delRow['receipt_number'];

        // 結案鎖：擋住對已上鎖案件的帳款刪除（不影響未結案案件）
        if ($delCaseId > 0 && function_exists('assertCaseNotLocked')) {
            try { assertCaseNotLocked($delCaseId, '刪除帳款交易'); }
            catch (\RuntimeException $_lockEx) {
                echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage()));
                break;
            }
        }

        // ⚠ 連動防呆：如果有對應收款單，擋下並提示
        if (!empty($delReceiptNo)) {
            try {
                $rcptStmt = $db->prepare("SELECT id FROM receipts WHERE receipt_number = ? LIMIT 1");
                $rcptStmt->execute(array($delReceiptNo));
                $rcptId = $rcptStmt->fetchColumn();
                if ($rcptId) {
                    echo json_encode(array(
                        'success' => false,
                        'error' => '無法刪除：此交易連動到收款單 ' . $delReceiptNo . '。請先到「收款單管理」刪除該張收款單，再回來刪除此交易（避免兩邊資料不一致）。',
                    ));
                    break;
                }
            } catch (Exception $e) {
                error_log('check linked receipt failed: ' . $e->getMessage());
            }
        }

        $db->prepare('DELETE FROM case_payments WHERE id = ?')->execute(array($pid));
        if ($delCaseId) updateTotalCollected($delCaseId, 'payment_delete');
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 取得施工回報 ----
    case 'get_worklog':
        header('Content-Type: application/json');
        $wid = (int)($_GET['id'] ?? 0);
        $stmt = Database::getInstance()->prepare('SELECT * FROM case_work_logs WHERE id = ?');
        $stmt->execute(array($wid));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($data ? array('success' => true, 'data' => $data) : array('success' => false, 'error' => '找不到紀錄'));
        break;

    // ---- AJAX: 新增施工回報 ----
    case 'add_worklog':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $caseId = (int)($_POST['case_id'] ?? 0);
        // 結案鎖
        if ($caseId > 0 && function_exists('assertCaseNotLocked')) {
            try { assertCaseNotLocked($caseId, '新增施工回報'); }
            catch (\RuntimeException $_lockEx) { echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage())); break; }
        }
        $stmt = Database::getInstance()->prepare('INSERT INTO case_work_logs (case_id, work_date, work_content, equipment_used, cable_used, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($caseId, $_POST['work_date'] ?? '', $_POST['work_content'] ?? '', $_POST['equipment_used'] ?? '', $_POST['cable_used'] ?? '', Auth::id()));
        $newId = (int)Database::getInstance()->lastInsertId();
        // Handle photos
        if (!empty($_FILES['photos']['name'][0])) {
            $photoPaths = array();
            $dir = __DIR__ . '/uploads/cases/' . $caseId . '/worklogs';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $fname = date('Ymd_His') . '_wl_' . $newId . '_' . $i . '.' . pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION);
                move_uploaded_file($tmp, $dir . '/' . $fname);
                $photoPaths[] = 'uploads/cases/' . $caseId . '/worklogs/' . $fname;
            }
            if ($photoPaths) {
                Database::getInstance()->prepare('UPDATE case_work_logs SET photo_paths = ? WHERE id = ?')->execute(array(json_encode($photoPaths), $newId));
            }
        }
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 編輯施工回報 ----
    case 'edit_worklog':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $wid = (int)($_POST['worklog_id'] ?? 0);
        $stmt = Database::getInstance()->prepare('SELECT * FROM case_work_logs WHERE id = ?');
        $stmt->execute(array($wid));
        $wl = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wl) { echo json_encode(array('success' => false, 'error' => '找不到紀錄')); break; }
        // 結案鎖
        if (!empty($wl['case_id']) && function_exists('assertCaseNotLocked')) {
            try { assertCaseNotLocked((int)$wl['case_id'], '修改施工回報'); }
            catch (\RuntimeException $_lockEx) { echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage())); break; }
        }
        Database::getInstance()->prepare('UPDATE case_work_logs SET work_date=?, work_content=?, equipment_used=?, cable_used=? WHERE id=?')
            ->execute(array($_POST['work_date'] ?? '', $_POST['work_content'] ?? '', $_POST['equipment_used'] ?? '', $_POST['cable_used'] ?? '', $wid));
        if (!empty($_FILES['photos']['name'][0])) {
            $existing = $wl['photo_paths'] ? json_decode($wl['photo_paths'], true) : array();
            if (!is_array($existing)) $existing = array();
            $dir = __DIR__ . '/uploads/cases/' . $wl['case_id'] . '/worklogs';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $fname = date('Ymd_His') . '_wl_' . $wid . '_' . $i . '.' . pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION);
                move_uploaded_file($tmp, $dir . '/' . $fname);
                $existing[] = 'uploads/cases/' . $wl['case_id'] . '/worklogs/' . $fname;
            }
            Database::getInstance()->prepare('UPDATE case_work_logs SET photo_paths = ? WHERE id = ?')->execute(array(json_encode($existing), $wid));
        }
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 刪除施工回報 ----
    case 'delete_worklog':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        if (!Auth::canEditSection('delete') && !Auth::hasPermission('schedule.delete') && !Auth::hasPermission('all')) {
            echo json_encode(array('success' => false, 'error' => '無刪除權限'));
            break;
        }
        $wid = (int)($_POST['worklog_id'] ?? 0);
        // 結案鎖：先查 case_id
        $_wlCheck = Database::getInstance()->prepare('SELECT case_id FROM case_work_logs WHERE id = ?');
        $_wlCheck->execute(array($wid));
        $_wlCaseId = (int)$_wlCheck->fetchColumn();
        if ($_wlCaseId > 0 && function_exists('assertCaseNotLocked')) {
            try { assertCaseNotLocked($_wlCaseId, '刪除施工回報'); }
            catch (\RuntimeException $_lockEx) { echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage())); break; }
        }
        Database::getInstance()->prepare('DELETE FROM case_work_logs WHERE id = ?')->execute(array($wid));
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 上傳附件 ----
    case 'upload_attachment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $caseId = (int)($_GET['id'] ?? 0);
        // 結案鎖（行政人員可繞過，方便事後補件）
        if ($caseId > 0 && function_exists('assertCaseNotLocked')
            && !(function_exists('canBypassLockForAttach') && canBypassLockForAttach())) {
            try { assertCaseNotLocked($caseId, '上傳附件'); }
            catch (\RuntimeException $_lockEx) { echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage())); break; }
        }
        $fileType = $_POST['file_type'] ?? 'other';
        if (empty($_FILES['file']['tmp_name'])) { echo json_encode(array('success' => false, 'error' => '無檔案')); break; }
        $dir = __DIR__ . '/uploads/cases/' . $caseId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $origName = $_FILES['file']['name'];
        $fname = date('Ymd_His') . '_' . mt_rand(1000,9999) . '_' . preg_replace('/[^a-zA-Z0-9._\x{4e00}-\x{9fff}-]/u', '', $origName);
        $filePath = '/uploads/cases/' . $caseId . '/' . $fname;
        move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $fname);
        $attId = $model->saveAttachment($caseId, $fileType, $origName, $filePath);
        if (function_exists('backup_to_drive')) { backup_to_drive($dir . '/' . $fname, 'cases', $caseId); }
        echo json_encode(array('success' => true, 'id' => $attId, 'file_name' => $origName, 'file_path' => $filePath));
        break;

    // ---- AJAX: 刪除附件 ----
    case 'delete_attachment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        if (!Auth::canEditSection('attach') && !Auth::hasPermission('all')) {
            echo json_encode(array('success' => false, 'error' => '無權限'));
            break;
        }
        $attId = (int)($_POST['attachment_id'] ?? 0);
        // 結案鎖：先查附件對應的 case_id
        $_attCheck = Database::getInstance()->prepare('SELECT case_id FROM case_attachments WHERE id = ?');
        $_attCheck->execute(array($attId));
        $_attCaseId = (int)$_attCheck->fetchColumn();
        // 結案鎖（行政人員可繞過）
        if ($_attCaseId > 0 && function_exists('assertCaseNotLocked')
            && !(function_exists('canBypassLockForAttach') && canBypassLockForAttach())) {
            try { assertCaseNotLocked($_attCaseId, '刪除附件'); }
            catch (\RuntimeException $_lockEx) { echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage())); break; }
        }
        $model->deleteAttachment($attId);
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 新增附件分類 ----
    case 'add_attach_type':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $label = trim($_POST['label'] ?? '');
        if (!$label) { echo json_encode(array('success' => false, 'error' => '名稱不可為空')); break; }
        $key = 'custom_' . time();
        CaseModel::addAttachType($key, $label);
        echo json_encode(array('success' => true, 'key' => $key));
        break;

    // ---- AJAX: 切換客戶不允許拍照 ----
    case 'toggle_no_photo':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $caseId = (int)($_POST['case_id'] ?? 0);
        // 結案鎖（行政人員可繞過，與附件管理共用）
        if ($caseId > 0 && function_exists('assertCaseNotLocked')
            && !(function_exists('canBypassLockForAttach') && canBypassLockForAttach())) {
            try { assertCaseNotLocked($caseId, '切換不允許拍照'); }
            catch (\RuntimeException $_lockEx) { echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage())); break; }
        }
        $noPhoto = (int)($_POST['no_photo'] ?? 0);
        Database::getInstance()->prepare('UPDATE case_readiness SET no_photo_allowed = ? WHERE case_id = ?')->execute(array($noPhoto, $caseId));
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 搜尋客戶 ----
    // ---- AJAX: 依進件編號查案件基本資訊（給請款單/收款單即時帶入系統別等用）----
    case 'ajax_get_case_by_number':
        header('Content-Type: application/json');
        $cn = trim($_GET['case_number'] ?? '');
        if ($cn === '') { echo json_encode(array('found' => false)); break; }
        $stmt = Database::getInstance()->prepare("
            SELECT id, case_number, title, system_type,
                   customer_id, customer_no, customer_name, customer_phone, customer_mobile,
                   branch_id, sales_id,
                   billing_title, billing_tax_id,
                   billing_phone, billing_mobile, billing_address, billing_email,
                   contact_address
            FROM cases WHERE case_number = ? LIMIT 1
        ");
        $stmt->execute(array($cn));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(array('found' => false)); break; }
        echo json_encode(array('found' => true, 'data' => $row));
        break;

    case 'ajax_search_customer':
        header('Content-Type: application/json');
        $keyword = $_GET['keyword'] ?? '';
        if (mb_strlen($keyword) < 2) { echo '[]'; break; }
        $stmt = Database::getInstance()->prepare("SELECT c.id, c.customer_no, c.name, c.phone, c.mobile, c.tax_id, c.site_address, c.contact_person, c.line_official, c.source_company, c.is_blacklisted, c.blacklist_reason FROM customers c WHERE c.name LIKE ? OR c.phone LIKE ? OR c.mobile LIKE ? OR c.tax_id LIKE ? OR c.customer_no LIKE ? ORDER BY c.name LIMIT 20");
        $like = '%' . $keyword . '%';
        $stmt->execute(array($like, $like, $like, $like, $like));
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Load contacts for each customer
        foreach ($customers as &$c) {
            $cs = Database::getInstance()->prepare('SELECT contact_name, phone, role FROM customer_contacts WHERE customer_id = ? LIMIT 5');
            $cs->execute(array($c['id']));
            $c['contacts'] = $cs->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($customers);
        break;

    // ---- AJAX: 依統一編號查詢可關聯客戶與相同統編案件 ----
    case 'ajax_lookup_by_tax_id':
        header('Content-Type: application/json');
        $taxId = trim($_GET['tax_id'] ?? '');
        $excludeCaseId = (int)($_GET['exclude_case_id'] ?? 0);
        if ($taxId === '') { echo json_encode(array('customers' => array(), 'cases' => array())); break; }
        $db = Database::getInstance();
        // 1) 客戶資料中統編相同者
        $cs = $db->prepare("SELECT id, customer_no, name, phone, mobile, tax_id, site_address, contact_person, line_official, source_company, is_blacklisted, blacklist_reason FROM customers WHERE tax_id = ? ORDER BY customer_no LIMIT 30");
        $cs->execute(array($taxId));
        $customers = $cs->fetchAll(PDO::FETCH_ASSOC);
        // 帶聯絡人（給 selectCustomer 使用）
        foreach ($customers as &$c) {
            $cts = $db->prepare('SELECT contact_name, phone, role FROM customer_contacts WHERE customer_id = ? LIMIT 5');
            $cts->execute(array($c['id']));
            $c['contacts'] = $cts->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($c);
        // 2) 其他案件中相同統編者（含其已關聯之客戶）
        $sql = "SELECT c.id, c.case_number, c.title, c.customer_name, c.customer_id, c.billing_title, c.billing_tax_id,
                       c.sub_status, c.status, c.created_at,
                       cu.customer_no AS linked_customer_no, cu.name AS linked_customer_name
                FROM cases c
                LEFT JOIN customers cu ON c.customer_id = cu.id
                WHERE c.billing_tax_id = ?";
        $params = array($taxId);
        if ($excludeCaseId > 0) { $sql .= " AND c.id <> ?"; $params[] = $excludeCaseId; }
        $sql .= " ORDER BY c.created_at DESC LIMIT 50";
        $st = $db->prepare($sql);
        $st->execute($params);
        $cases = $st->fetchAll(PDO::FETCH_ASSOC);
        // 補齊：案件中已關聯的客戶若不在 customers 清單則加入（例如客戶 tax_id 為空但案件 billing_tax_id 已填）
        $known = array();
        foreach ($customers as $cc) { $known[(int)$cc['id']] = true; }
        $extraIds = array();
        foreach ($cases as $kc) {
            $cid = (int)($kc['customer_id'] ?? 0);
            if ($cid > 0 && empty($known[$cid])) { $extraIds[$cid] = true; $known[$cid] = true; }
        }
        if (!empty($extraIds)) {
            $idList = array_keys($extraIds);
            $place = implode(',', array_fill(0, count($idList), '?'));
            $eq = $db->prepare("SELECT id, customer_no, name, phone, mobile, tax_id, site_address, contact_person, line_official, source_company, is_blacklisted, blacklist_reason FROM customers WHERE id IN ($place)");
            $eq->execute($idList);
            $extras = $eq->fetchAll(PDO::FETCH_ASSOC);
            foreach ($extras as &$ec) {
                $cts = $db->prepare('SELECT contact_name, phone, role FROM customer_contacts WHERE customer_id = ? LIMIT 5');
                $cts->execute(array($ec['id']));
                $ec['contacts'] = $cts->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($ec);
            $customers = array_merge($customers, $extras);
        }
        echo json_encode(array('customers' => $customers, 'cases' => $cases));
        break;

    // ---- AJAX: 快速新增客戶 ----
    case 'ajax_create_customer':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        if (!Auth::hasPermission('customers.create')) {
            echo json_encode(array('success' => false, 'error' => '無新增客戶權限，請聯絡管理員設定'));
            break;
        }
        $name = trim($_POST['name'] ?? '');
        if (!$name) { echo json_encode(array('success' => false, 'error' => '名稱不可為空')); break; }
        $db = Database::getInstance();
        // Generate customer_no
        $maxNo = $db->query("SELECT MAX(CAST(SUBSTRING(customer_no, 3) AS UNSIGNED)) FROM customers WHERE customer_no LIKE 'A-%'")->fetchColumn();
        $customerNo = 'A-' . str_pad(($maxNo ?: 0) + 1, 6, '0', STR_PAD_LEFT);
        $caseNumber = trim($_POST['case_number'] ?? '');
        $caseDate = trim($_POST['case_date'] ?? '');
        $sourceCompany = trim($_POST['source_company'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $mobile        = trim($_POST['mobile'] ?? '');
        $lineId        = trim($_POST['line_id'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $invoiceTitle  = trim($_POST['invoice_title'] ?? '');
        $taxIdNew      = trim($_POST['tax_id'] ?? '');
        $salesIdNew    = !empty($_POST['sales_id']) ? (int)$_POST['sales_id'] : null;
        $address       = trim($_POST['address'] ?? '');
        $db->prepare('INSERT INTO customers (customer_no, name, contact_person, phone, mobile, line_id, email, invoice_title, tax_id, sales_id, site_address, case_number, case_date, source_company, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute(array($customerNo, $name, $contactPerson, $phone, $mobile, $lineId ?: null, $email ?: null, $invoiceTitle ?: null, $taxIdNew ?: null, $salesIdNew, $address, $caseNumber ?: null, $caseDate ?: null, $sourceCompany ?: null, Auth::id()));
        $newId = (int)$db->lastInsertId();
        echo json_encode(array('success' => true, 'customer' => array(
            'id' => $newId,
            'customer_no' => $customerNo,
            'name' => $name,
            'phone' => $phone,
            'mobile' => $mobile,
            'line_id' => $lineId,
            'email' => $email,
            'invoice_title' => $invoiceTitle,
            'tax_id' => $taxIdNew,
            'sales_id' => $salesIdNew,
            'site_address' => $address,
            'contact_person' => $contactPerson,
            'contacts' => array(),
        )));
        break;

    // ---- AJAX: 請款流程 新增/編輯/刪除 ----
    case 'ajax_billing_item_save':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $db = Database::getInstance();
        $biId = (int)($_POST['id'] ?? 0);
        $caseId = (int)($_POST['case_id'] ?? 0);
        if (!$caseId) { echo json_encode(array('success' => false, 'error' => '缺少案件ID')); break; }
        // 結案鎖
        if (function_exists('assertCaseNotLocked')) {
            try { assertCaseNotLocked($caseId, '新增/修改請款項目'); }
            catch (\RuntimeException $_lockEx) { echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage())); break; }
        }
        $_biDate = !empty($_POST['billing_date']) ? trim($_POST['billing_date']) : null;
        if ($_biDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $_biDate)) { $_biDate = null; }
        // 偵測 billing_date 欄位是否存在（migration 140 是否已執行）— 沒跑就略過該欄位寫入
        $_hasBillingDate = false;
        try {
            $_chkCol = $db->query("SHOW COLUMNS FROM case_billing_items LIKE 'billing_date'");
            $_hasBillingDate = ($_chkCol && $_chkCol->rowCount() > 0);
        } catch (Exception $_chkEx) {}
        $biData = array(
            !empty($_POST['payment_category']) ? $_POST['payment_category'] : '',
            !empty($_POST['amount_untaxed']) ? (int)str_replace(',', '', $_POST['amount_untaxed']) : null,
            !empty($_POST['tax_amount']) ? (int)str_replace(',', '', $_POST['tax_amount']) : null,
            !empty($_POST['total_amount']) ? (int)str_replace(',', '', $_POST['total_amount']) : 0,
            !empty($_POST['tax_included']) ? 1 : 0,
            !empty($_POST['customer_billable']) ? 1 : 0,
            !empty($_POST['customer_paid']) ? 1 : 0,
            !empty($_POST['customer_paid_info']) ? trim($_POST['customer_paid_info']) : null,
            !empty($_POST['is_billed']) ? 1 : 0,
            !empty($_POST['billed_info']) ? trim($_POST['billed_info']) : null,
            !empty($_POST['invoice_number']) ? trim($_POST['invoice_number']) : null,
            !empty($_POST['note']) ? trim($_POST['note']) : null,
        );
        if ($_hasBillingDate) {
            array_unshift($biData, $_biDate);
        }
        // 處理附件上傳
        $attachPath = null;
        if (!empty($_FILES['bi_attachment']['tmp_name']) && $_FILES['bi_attachment']['error'] === UPLOAD_ERR_OK) {
            $dir = __DIR__ . '/uploads/cases/' . $caseId . '/billing';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['bi_attachment']['name'], PATHINFO_EXTENSION));
            $fname = 'bi_' . date('Ymd_His') . '_' . mt_rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['bi_attachment']['tmp_name'], $dir . '/' . $fname)) {
                $attachPath = 'uploads/cases/' . $caseId . '/billing/' . $fname;
            }
        }

        // 讀取舊值（用於通知比對）
        $oldBi = null;
        if ($biId) {
            $oldBiStmt = $db->prepare("SELECT customer_billable, customer_paid, is_billed FROM case_billing_items WHERE id=? AND case_id=?");
            $oldBiStmt->execute(array($biId, $caseId));
            $oldBi = $oldBiStmt->fetch(PDO::FETCH_ASSOC);
        }
        // 新值
        $newBillable = !empty($_POST['customer_billable']) ? 1 : 0;
        $newPaid = !empty($_POST['customer_paid']) ? 1 : 0;
        $newBilled = !empty($_POST['is_billed']) ? 1 : 0;

        // 依欄位是否存在動態組 SQL（首次部署在 migration 未跑時也能存）
        $_setBillingDate  = $_hasBillingDate ? 'billing_date=?, ' : '';
        $_colsBillingDate = $_hasBillingDate ? 'billing_date, '  : '';
        $_phsBillingDate  = $_hasBillingDate ? '?,'              : '';
        try {
            if ($biId) {
                $sql = "UPDATE case_billing_items SET {$_setBillingDate}payment_category=?, amount_untaxed=?, tax_amount=?, total_amount=?, tax_included=?, customer_billable=?, customer_paid=?, customer_paid_info=?, is_billed=?, billed_info=?, invoice_number=?, note=?";
                $params = array_merge($biData);
                if ($attachPath) { $sql .= ", attachment_path=?"; $params[] = $attachPath; }
                $sql .= " WHERE id=? AND case_id=?";
                $params[] = $biId;
                $params[] = $caseId;
                $db->prepare($sql)->execute($params);
            } else {
                $maxSeq = $db->prepare("SELECT COALESCE(MAX(seq_no),0) FROM case_billing_items WHERE case_id=?");
                $maxSeq->execute(array($caseId));
                $seqNo = (int)$maxSeq->fetchColumn() + 1;
                $insSql = "INSERT INTO case_billing_items (case_id, seq_no, {$_colsBillingDate}payment_category, amount_untaxed, tax_amount, total_amount, tax_included, customer_billable, customer_paid, customer_paid_info, is_billed, billed_info, invoice_number, note, attachment_path, created_by) VALUES (?,?,{$_phsBillingDate}?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $db->prepare($insSql)
                    ->execute(array_merge(array($caseId, $seqNo), $biData, array($attachPath, Auth::id())));
                $biId = (int)$db->lastInsertId();
            }
        } catch (Exception $_saveEx) {
            echo json_encode(array('success' => false, 'error' => '儲存失敗：' . $_saveEx->getMessage()));
            break;
        }

        // 通知：偵測三個勾選變更（從 0→1 才發）
        try {
            require_once __DIR__ . '/../modules/notifications/NotificationDispatcher.php';
            $caseStmt = $db->prepare("SELECT case_number, customer_name, branch_id FROM cases WHERE id = ?");
            $caseStmt->execute(array($caseId));
            $caseInfo = $caseStmt->fetch(PDO::FETCH_ASSOC);
            $notifData = array(
                'id' => $biId,
                'case_id' => $caseId,
                'case_number' => $caseInfo ? $caseInfo['case_number'] : '',
                'customer_name' => $caseInfo ? $caseInfo['customer_name'] : '',
                'branch_id' => $caseInfo ? $caseInfo['branch_id'] : null,
                'payment_category' => !empty($_POST['payment_category']) ? $_POST['payment_category'] : '',
                'total_amount' => !empty($_POST['total_amount']) ? (int)str_replace(',', '', $_POST['total_amount']) : 0,
            );
            $biChecks = array(
                'customer_billable' => 'customer_billable_changed',
                'customer_paid'     => 'customer_paid_changed',
                'is_billed'         => 'is_billed_changed',
            );
            $biNewVals = array('customer_billable' => $newBillable, 'customer_paid' => $newPaid, 'is_billed' => $newBilled);
            foreach ($biChecks as $field => $event) {
                $wasOn = $oldBi ? (int)$oldBi[$field] : 0;
                if (!$wasOn && $biNewVals[$field]) {
                    NotificationDispatcher::dispatch('billing_items', $event, $notifData, Auth::id());
                }
            }
        } catch (Exception $ne) {}

        echo json_encode(array('success' => true));
        break;

    case 'ajax_billing_item_delete':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $biId = (int)($_POST['id'] ?? 0);
        $caseId = (int)($_POST['case_id'] ?? 0);
        // 結案鎖
        if ($caseId > 0 && function_exists('assertCaseNotLocked')) {
            try { assertCaseNotLocked($caseId, '刪除請款項目'); }
            catch (\RuntimeException $_lockEx) { echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage())); break; }
        }
        if ($biId && $caseId) {
            Database::getInstance()->prepare("DELETE FROM case_billing_items WHERE id=? AND case_id=?")->execute(array($biId, $caseId));
        }
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 取得支援分公司 ----
    case 'get_support_branches':
        header('Content-Type: application/json');
        $caseId = (int)($_GET['id'] ?? 0);
        try {
            $supportBranches = $model->getSupportBranches($caseId);
            echo json_encode(array('success' => true, 'data' => $supportBranches));
        } catch (Exception $e) {
            echo json_encode(array('success' => true, 'data' => array()));
        }
        break;

    // ---- AJAX: 儲存支援分公司 ----
    case 'save_support_branches':
        header('Content-Type: application/json');
        if (!verify_csrf()) {
            echo json_encode(array('success' => false, 'error' => 'CSRF'));
            break;
        }
        if (!Auth::hasPermission('cases.manage') && !Auth::hasPermission('all')) {
            echo json_encode(array('success' => false, 'error' => '無權限'));
            break;
        }
        $caseId = (int)($_POST['case_id'] ?? 0);
        // 結案鎖
        if ($caseId > 0 && function_exists('assertCaseNotLocked')) {
            try { assertCaseNotLocked($caseId, '修改支援分公司'); }
            catch (\RuntimeException $_lockEx) { echo json_encode(array('success' => false, 'error' => $_lockEx->getMessage())); break; }
        }
        $selectedBranches = $_POST['branch_ids'] ?? array();
        if (!is_array($selectedBranches)) {
            $selectedBranches = array();
        }
        $selectedBranches = array_map('intval', $selectedBranches);
        $model->saveSupportBranches($caseId, $selectedBranches, Auth::id());
        echo json_encode(array('success' => true));
        break;

    // ---- 結案鎖：解鎖案件（boss / vice_president）----
    case 'unlock_case':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/cases.php'); }
        if (!verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/cases.php');
        }
        if (!canUnlockCase()) {
            Session::flash('error', '無解鎖權限（限 boss / 副總）');
            redirect('/cases.php');
        }
        $caseId = (int)($_POST['case_id'] ?? 0);
        if ($caseId > 0) {
            $db = Database::getInstance();
            $u = Auth::user();
            $stmt = $db->prepare("UPDATE cases SET is_locked = 0, unlocked_at = NOW(), unlocked_by = ? WHERE id = ? AND status = 'closed'");
            $stmt->execute(array($u['id'], $caseId));
            if ($stmt->rowCount() > 0) {
                AuditLog::log('cases', 'unlock', $caseId, '解鎖結案案件（' . $u['real_name'] . '）');
                // 寫入 case_status_history 留軌跡
                try {
                    $db->prepare("INSERT INTO case_status_history (case_id, old_status, new_status, changed_by, change_reason, changed_at) VALUES (?, ?, ?, ?, ?, NOW())")
                       ->execute(array($caseId, 'closed_locked', 'closed_unlocked', $u['id'], '管理員解鎖編輯'));
                } catch (Exception $e) { /* 表不存在或欄位不同則跳過 */ }
                Session::flash('success', '已解鎖，請於 30 分鐘內完成編輯並儲存（存檔後自動重鎖）');
            } else {
                Session::flash('error', '解鎖失敗（案件不存在或非已結案狀態）');
            }
        }
        redirect('/cases.php?action=edit&id=' . $caseId);
        break;

    // ---- 結案鎖：手動上鎖（boss / vice_president）----
    case 'lock_case':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/cases.php'); }
        if (!verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/cases.php');
        }
        if (!canUnlockCase()) {
            Session::flash('error', '無上鎖權限（限 boss / 副總）');
            redirect('/cases.php');
        }
        $caseId = (int)($_POST['case_id'] ?? 0);
        if ($caseId > 0) {
            $db = Database::getInstance();
            $u = Auth::user();
            $stmt = $db->prepare("UPDATE cases SET is_locked = 1, locked_by = ?, locked_at = NOW(), unlocked_at = NULL, unlocked_by = NULL WHERE id = ? AND status = 'closed'");
            $stmt->execute(array($u['id'], $caseId));
            if ($stmt->rowCount() > 0) {
                AuditLog::log('cases', 'lock', $caseId, '手動上鎖結案案件（' . $u['real_name'] . '）');
                Session::flash('success', '案件已上鎖');
            } else {
                Session::flash('error', '上鎖失敗（案件不存在或非已結案狀態）');
            }
        }
        redirect('/cases.php?action=edit&id=' . $caseId);
        break;

    default:
        redirect('/cases.php');
}
