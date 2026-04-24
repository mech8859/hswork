<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('accounting.manage') && !Auth::hasPermission('accounting.view') && !Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view') && !in_array(Auth::user()['role'], array('boss','manager'))) {
    Session::flash('error', '無權限查看此頁面');
    redirect('/');
}
require_once __DIR__ . '/../modules/accounting/AccountingModel.php';

$model = new AccountingModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'journals';
$canManage = Auth::hasPermission('accounting.manage') || Auth::hasPermission('finance.manage') || in_array(Auth::user()['role'], array('boss','manager'));

switch ($action) {

    // ============================================================
    // 會計科目管理
    // ============================================================
    case 'accounts':
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
        $catFilter = isset($_GET['cat']) ? $_GET['cat'] : '';
        $showInactive = !empty($_GET['show_inactive']);

        $allAccounts = $model->getAccountsTree($showInactive);

        // Filter
        if ($keyword || $typeFilter || $catFilter) {
            $filtered = array();
            foreach ($allAccounts as $acc) {
                $match = true;
                if ($keyword && stripos($acc['code'] . $acc['name'], $keyword) === false) {
                    $match = false;
                }
                if ($typeFilter && (string)($acc['type_num'] ?? '') !== $typeFilter) {
                    $match = false;
                }
                if ($catFilter && (string)($acc['cat_code'] ?? '') !== $catFilter) {
                    $match = false;
                }
                if ($match) $filtered[] = $acc;
            }
            $allAccounts = $filtered;
        }

        $accountTypeOptions = AccountingModel::accountTypeOptions();
        $pageTitle = '會計科目管理';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/accounts.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'account_delete':
        header('Content-Type: application/json');
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '無權限'));
            exit;
        }
        $delId = (int)($_POST['id'] ?? 0);
        if (!$delId) { echo json_encode(array('error' => '缺少 ID')); exit; }

        // 確認科目已停用
        $chk = Database::getInstance()->prepare("SELECT is_active, code FROM chart_of_accounts WHERE id = ?");
        $chk->execute(array($delId));
        $delAcc = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$delAcc) { echo json_encode(array('error' => '科目不存在')); exit; }
        if ($delAcc['is_active']) { echo json_encode(array('error' => '科目尚未停用，請先停用再刪除')); exit; }

        // 檢查是否有分錄使用此科目
        $usedStmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM journal_entry_lines WHERE account_id = ?");
        $usedStmt->execute(array($delId));
        if ((int)$usedStmt->fetchColumn() > 0) {
            echo json_encode(array('error' => '此科目已有傳票分錄使用，無法刪除'));
            exit;
        }

        Database::getInstance()->prepare("DELETE FROM chart_of_accounts WHERE id = ?")->execute(array($delId));
        echo json_encode(array('success' => true));
        exit;

    case 'account_save':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/accounting.php?action=accounts');
        }
        verify_csrf();
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        $data = array(
            'code' => trim($_POST['code']),
            'name' => trim($_POST['name']),
            'account_type' => $_POST['account_type'],
            'parent_id' => null,
            'level' => !empty($_POST['level']) ? (int)$_POST['level'] : 1,
            'is_detail' => isset($_POST['is_detail']) ? 1 : 0,
            'normal_balance' => $_POST['normal_balance'],
            'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
            'sort_order' => !empty($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0,
            'offset_type' => isset($_POST['offset_type']) ? trim($_POST['offset_type']) : '',
            'tx_type' => isset($_POST['tx_type']) ? trim($_POST['tx_type']) : '',
            'type_num' => isset($_POST['type_num']) ? trim($_POST['type_num']) : '',
            'type_name_full' => isset($_POST['type_name_full']) ? trim($_POST['type_name_full']) : '',
            'cat_code' => isset($_POST['cat_code']) ? trim($_POST['cat_code']) : '',
            'cat_name' => isset($_POST['cat_name']) ? trim($_POST['cat_name']) : '',
            'attr' => isset($_POST['attr']) ? trim($_POST['attr']) : '',
            'project_calc' => isset($_POST['project_calc']) ? trim($_POST['project_calc']) : '',
            'dept_calc' => isset($_POST['dept_calc']) ? trim($_POST['dept_calc']) : '',
            'relate_type' => isset($_POST['relate_type']) ? trim($_POST['relate_type']) : '',
            'internal_flag' => isset($_POST['internal_flag']) ? trim($_POST['internal_flag']) : '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );
        // 解析隸屬科目 → parent_id
        if (!empty($_POST['parent_code'])) {
            $parentAcc = $model->getAccountByCode(trim($_POST['parent_code']));
            if ($parentAcc) $data['parent_id'] = $parentAcc['id'];
        }

        if (!$data['code'] || !$data['name'] || !$data['account_type']) {
            Session::flash('error', '請填寫必要欄位');
            redirect('/accounting.php?action=accounts');
        }

        try {
            if ($id) {
                $model->updateAccount($id, $data);
                Session::flash('success', '科目已更新');
            } else {
                $model->createAccount($data);
                Session::flash('success', '科目已新增');
            }
        } catch (Exception $e) {
            Session::flash('error', '操作失敗: ' . $e->getMessage());
        }
        redirect('/accounting.php?action=accounts');
        break;

    case 'account_toggle':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/accounting.php?action=accounts');
        }
        verify_csrf();
        $id = (int)$_POST['id'];
        $model->toggleAccount($id);
        Session::flash('success', '科目狀態已變更');
        redirect('/accounting.php?action=accounts');
        break;

    // ============================================================
    // 成本中心管理
    // ============================================================
    case 'cost_centers':
        $costCenters = $model->getAllCostCenters();
        $branches = $model->getBranches();
        $typeOptions = AccountingModel::costCenterTypeOptions();

        $pageTitle = '成本中心管理';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/cost_centers.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'cost_center_save':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/accounting.php?action=cost_centers');
        }
        verify_csrf();
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        $data = array(
            'code' => trim($_POST['code']),
            'name' => trim($_POST['name']),
            'type' => $_POST['type'],
            'branch_id' => !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );

        if (!$data['code'] || !$data['name']) {
            Session::flash('error', '請填寫必要欄位');
            redirect('/accounting.php?action=cost_centers');
        }

        try {
            if ($id) {
                $model->updateCostCenter($id, $data);
                Session::flash('success', '成本中心已更新');
            } else {
                $model->createCostCenter($data);
                Session::flash('success', '成本中心已新增');
            }
        } catch (Exception $e) {
            Session::flash('error', '操作失敗: ' . $e->getMessage());
        }
        redirect('/accounting.php?action=cost_centers');
        break;

    // ============================================================
    // 傳票列表
    // ============================================================
    // 傳票星號標註
    case 'toggle_star_journal':
        header('Content-Type: application/json; charset=utf-8');
        if (!$canManage) { echo json_encode(array('error' => '無權限')); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('error' => '方法錯誤')); exit; }
        $sid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($sid <= 0) { echo json_encode(array('error' => '參數錯誤')); exit; }
        try {
            $db = Database::getInstance();
            $cur = $db->prepare("SELECT is_starred FROM journal_entries WHERE id = ?");
            $cur->execute(array($sid));
            $c = $cur->fetchColumn();
            if ($c === false) { echo json_encode(array('error' => '記錄不存在')); exit; }
            $new = ((int)$c === 1) ? 0 : 1;
            $db->prepare("UPDATE journal_entries SET is_starred = ? WHERE id = ?")->execute(array($new, $sid));
            echo json_encode(array('success' => true, 'starred' => $new));
        } catch (Exception $e) {
            echo json_encode(array('error' => $e->getMessage()));
        }
        exit;

    // 立沖帳星號標註
    case 'toggle_star_offset':
        header('Content-Type: application/json; charset=utf-8');
        if (!$canManage) { echo json_encode(array('error' => '無權限')); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('error' => '方法錯誤')); exit; }
        $sid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($sid <= 0) { echo json_encode(array('error' => '參數錯誤')); exit; }
        try {
            $db = Database::getInstance();
            $cur = $db->prepare("SELECT is_starred FROM offset_ledger WHERE id = ?");
            $cur->execute(array($sid));
            $c = $cur->fetchColumn();
            if ($c === false) { echo json_encode(array('error' => '記錄不存在')); exit; }
            $new = ((int)$c === 1) ? 0 : 1;
            $db->prepare("UPDATE offset_ledger SET is_starred = ? WHERE id = ?")->execute(array($new, $sid));
            echo json_encode(array('success' => true, 'starred' => $new));
        } catch (Exception $e) {
            echo json_encode(array('error' => $e->getMessage()));
        }
        exit;

    case 'journals':
        $filters = array(
            'status'       => isset($_GET['status']) ? $_GET['status'] : '',
            'voucher_type' => isset($_GET['voucher_type']) ? $_GET['voucher_type'] : '',
            'date_from'    => isset($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'      => isset($_GET['date_to']) ? $_GET['date_to'] : '',
            'keyword'      => isset($_GET['keyword']) ? $_GET['keyword'] : '',
            'created_by'   => isset($_GET['created_by']) ? $_GET['created_by'] : '',
            'amount'       => isset($_GET['amount']) ? $_GET['amount'] : '',
            'account_id'   => isset($_GET['account_id']) ? $_GET['account_id'] : '',
            'sort'         => (isset($_GET['sort']) && $_GET['sort'] === 'asc') ? 'asc' : 'desc',
        );
        $perPage = 100;
        $page = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
        $offset = ($page - 1) * $perPage;
        $totalCount = $model->countJournalEntries($filters);
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }
        $entries = $model->getJournalEntries($filters, $perPage, $offset);
        $stats = $model->getJournalStats();
        $voucherTypeOptions = AccountingModel::voucherTypeOptions();
        $statusOptions = AccountingModel::statusOptions();
        $creators = $model->getJournalCreators();
        $accounts = $model->getAccountsFlat(false);

        $pageTitle = '傳票管理';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/journal_list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 傳票新增/編輯
    // ============================================================
    case 'journal_create':
    case 'journal_edit':
        if (!$canManage) {
            Session::flash('error', '無權限');
            redirect('/accounting.php?action=journals');
        }

        $entry = null;
        if ($action === 'journal_edit') {
            $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
            $entry = $model->getJournalEntryById($id);
            if (!$entry) {
                Session::flash('error', '找不到傳票');
                redirect('/accounting.php?action=journals');
            }
            if ($entry['status'] !== 'draft') {
                Session::flash('error', '僅草稿傳票可編輯');
                redirect('/accounting.php?action=journal_view&id=' . $id);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();

            $lines = array();
            if (!empty($_POST['lines']) && is_array($_POST['lines'])) {
                foreach ($_POST['lines'] as $line) {
                    if (empty($line['account_id'])) continue;
                    $lines[] = array(
                        'account_id'     => (int)$line['account_id'],
                        'cost_center_id' => !empty($line['cost_center_id']) ? (int)$line['cost_center_id'] : null,
                        'relation_type'  => !empty($line['relation_type']) ? $line['relation_type'] : null,
                        'relation_id'    => !empty($line['relation_id']) ? (int)$line['relation_id'] : null,
                        'relation_name'  => !empty($line['relation_name']) ? trim($line['relation_name']) : null,
                        'offset_flag'    => isset($line['offset_flag']) ? (int)$line['offset_flag'] : 0,
                        'offset_amount'  => isset($line['offset_amount']) ? (float)$line['offset_amount'] : 0,
                        'offset_ledger_id' => !empty($line['offset_ledger_id']) ? (int)$line['offset_ledger_id'] : null,
                        'debit_amount'   => isset($line['debit_amount']) ? (float)$line['debit_amount'] : 0,
                        'credit_amount'  => isset($line['credit_amount']) ? (float)$line['credit_amount'] : 0,
                        'description'    => isset($line['description']) ? trim($line['description']) : '',
                    );
                }
            }

            // 驗證：往來類型為「其他」時，往來編號及往來對象必填，且編號不可重複綁定不同名稱
            foreach ($lines as $li => $ln) {
                if ($ln['relation_type'] === 'other') {
                    if (empty($ln['relation_id'])) {
                        throw new Exception('第 ' . ($li + 1) . ' 行往來類型為「其他」，往來編號為必填');
                    }
                    if (empty($ln['relation_name'])) {
                        throw new Exception('第 ' . ($li + 1) . ' 行往來類型為「其他」，往來對象為必填');
                    }
                    // 檢查此編號是否已綁定其他名稱
                    $chkStmt = Database::getInstance()->prepare("
                        SELECT relation_name FROM journal_entry_lines
                        WHERE relation_type = 'other' AND relation_id = ? AND relation_name IS NOT NULL AND relation_name != ''
                        LIMIT 1
                    ");
                    $chkStmt->execute(array($ln['relation_id']));
                    $existingName = $chkStmt->fetchColumn();
                    if ($existingName && $existingName !== $ln['relation_name']) {
                        throw new Exception('第 ' . ($li + 1) . ' 行往來編號「' . $ln['relation_id'] . '」已綁定往來對象「' . $existingName . '」，不可使用不同名稱');
                    }
                }
            }

            // 處理附件上傳（支援多檔）
            $existingPaths = array();
            if ($entry && !empty($entry['attachment'])) {
                $decoded = json_decode($entry['attachment'], true);
                $existingPaths = is_array($decoded) ? $decoded : array($entry['attachment']);
            }
            if (!empty($_FILES['attachment']['tmp_name'])) {
                $uploadDir = __DIR__ . '/uploads/journals/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $files = $_FILES['attachment'];
                for ($fi = 0; $fi < count($files['tmp_name']); $fi++) {
                    if ($files['error'][$fi] !== 0 || empty($files['tmp_name'][$fi])) continue;
                    $ext = strtolower(pathinfo($files['name'][$fi], PATHINFO_EXTENSION));
                    $fname = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($files['tmp_name'][$fi], $uploadDir . $fname)) {
                        $existingPaths[] = '/uploads/journals/' . $fname;
                        backup_to_drive($uploadDir . $fname, 'journals', date('Y-m'));
                    }
                }
            }
            $attachmentPath = !empty($existingPaths) ? json_encode($existingPaths, JSON_UNESCAPED_UNICODE) : null;

            // DEBUG: log POST lines
            file_put_contents(__DIR__ . '/../logs/journal_debug.log', date('Y-m-d H:i:s') . " POST lines: " . json_encode($_POST['lines'], JSON_UNESCAPED_UNICODE) . "\nParsed: " . json_encode($lines, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

            $data = array(
                'voucher_date' => $_POST['voucher_date'],
                'voucher_type' => $_POST['voucher_type'],
                'description'  => isset($_POST['description']) ? trim($_POST['description']) : '',
                'lines'        => $lines,
                'attachment'   => $attachmentPath,
            );

            try {
                if ($entry) {
                    $data['updated_by'] = Auth::id();
                    $model->updateJournalEntry($entry['id'], $data);
                    Session::flash('success', '傳票已更新');
                } else {
                    $data['created_by'] = Auth::id();
                    $newId = $model->createJournalEntry($data);
                    Session::flash('success', '傳票已建立');
                    $entry = array('id' => $newId);
                }
                // 若有 return_to（來自核對報表等），存完回該處
                $rtn = isset($_POST['return_to']) ? $_POST['return_to'] : (isset($_GET['return_to']) ? $_GET['return_to'] : '');
                if ($rtn && strpos($rtn, '/') === 0 && strpos($rtn, '/accounting.php') === 0) {
                    redirect($rtn);
                }
                redirect('/accounting.php?action=journal_view&id=' . $entry['id']);
            } catch (Exception $e) {
                Session::flash('error', $e->getMessage());
                // Stay on form
            }
        }

        // Debug: check transaction state
        $_tdb = Database::getInstance();
        if ($_tdb->inTransaction()) { $_tdb->rollBack(); }

        $accounts = $model->getAccountsFlat();
        $costCenters = $model->getCostCenters();
        $voucherTypeOptions = AccountingModel::voucherTypeOptions();
        $nextNumber = $model->generateVoucherNumber();

        // 客戶和廠商清單（往來類型用）
        $db2 = Database::getInstance();
        try {
            $customers = $db2->query("SELECT id, customer_no, name, tax_id FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $customers = array(); }
        try {
            $vendors = $db2->query("SELECT id, vendor_code, name, tax_id FROM vendors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $vendors = array(); }

        // 「其他」往來過往紀錄（供 datalist 自動補完）
        try {
            $otherRelations = $db2->query("
                SELECT relation_id AS id, relation_name AS name, COUNT(*) AS cnt
                FROM journal_entry_lines
                WHERE relation_type = 'other'
                  AND relation_id IS NOT NULL AND relation_id <> ''
                  AND relation_name IS NOT NULL AND relation_name <> ''
                GROUP BY relation_id, relation_name
                ORDER BY cnt DESC, relation_id
                LIMIT 500
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $otherRelations = array(); }

        $pageTitle = $entry ? '編輯傳票 - ' . $entry['voucher_number'] : '新增傳票';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/journal_form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 傳票檢視
    // ============================================================
    case 'journal_view':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $entry = $model->getJournalEntryById($id);
        if (!$entry) {
            Session::flash('error', '找不到傳票');
            redirect('/accounting.php?action=journals');
        }

        $voucherTypeOptions = AccountingModel::voucherTypeOptions();
        $statusOptions = AccountingModel::statusOptions();

        // 上一筆/下一筆
        $db3 = Database::getInstance();
        $prevStmt = $db3->prepare("SELECT id FROM journal_entries WHERE id < ? ORDER BY id DESC LIMIT 1");
        $prevStmt->execute(array($id));
        $prevId = $prevStmt->fetchColumn();
        $nextStmt = $db3->prepare("SELECT id FROM journal_entries WHERE id > ? ORDER BY id ASC LIMIT 1");
        $nextStmt->execute(array($id));
        $nextId = $nextStmt->fetchColumn();

        $pageTitle = '傳票 ' . $entry['voucher_number'];
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/journal_view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 傳票過帳
    // ============================================================
    case 'journal_post':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/accounting.php?action=journals');
        }
        verify_csrf();
        $id = (int)$_POST['id'];
        try {
            $model->postJournalEntry($id, Auth::id());
            Session::flash('success', '傳票已過帳');
        } catch (Exception $e) {
            Session::flash('error', '過帳失敗: ' . $e->getMessage());
        }
        redirect('/accounting.php?action=journal_view&id=' . $id);
        break;

    // ============================================================
    // 取消過帳
    // ============================================================
    case 'journal_unpost':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/accounting.php?action=journals');
        }
        verify_csrf();
        $id = (int)$_POST['id'];
        try {
            $db = Database::getInstance();
            // 反轉立沖記錄
            $entry = $model->getJournalEntryById($id);
            if ($entry) {
                $model->reverseOffsetPublic($entry);
            }
            $db->prepare("UPDATE journal_entries SET status = 'draft', posted_by = NULL, posted_at = NULL WHERE id = ? AND status = 'posted'")->execute(array($id));
            Session::flash('success', '已取消過帳，傳票回到草稿狀態');
        } catch (Exception $e) {
            Session::flash('error', '取消過帳失敗: ' . $e->getMessage());
        }
        redirect('/accounting.php?action=journal_view&id=' . $id);
        break;

    // ============================================================
    // 傳票作廢
    // ============================================================
    case 'journal_void':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/accounting.php?action=journals');
        }
        verify_csrf();
        $id = (int)$_POST['id'];
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        try {
            $model->voidJournalEntry($id, Auth::id(), $reason);
            Session::flash('success', '傳票已作廢');
        } catch (Exception $e) {
            Session::flash('error', '作廢失敗: ' . $e->getMessage());
        }
        redirect('/accounting.php?action=journal_view&id=' . $id);
        break;

    // ============================================================
    // 傳票複製
    // ============================================================
    case 'journal_copy':
        if (!$canManage) { redirect('/accounting.php?action=journals'); }
        $srcId = (int)($_GET['id'] ?? 0);
        $srcEntry = $model->getJournalEntryById($srcId);
        if (!$srcEntry) { Session::flash('error', '來源傳票不存在'); redirect('/accounting.php?action=journals'); }

        // 把來源當成新增表單的預填資料
        $entry = null; // 標記為新增模式
        $prefillEntry = $srcEntry; // 供表單預填
        $accounts = $model->getAccountsFlat();
        $costCenters = $model->getCostCenters();
        $voucherTypeOptions = AccountingModel::voucherTypeOptions();
        $nextNumber = $model->generateVoucherNumber();

        // 載入客戶和廠商資料
        $db2 = Database::getInstance();
        try {
            $customers = $db2->query("SELECT id, customer_no, name, tax_id FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $customers = array(); }
        try {
            $vendors = $db2->query("SELECT id, vendor_code, name, tax_id FROM vendors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $vendors = array(); }

        $pageTitle = '複製傳票';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/journal_form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 傳票刪除
    // ============================================================
    case 'journal_delete':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/accounting.php?action=journals');
        }
        verify_csrf();
        $id = (int)$_POST['id'];
        try {
            $model->deleteJournalEntry($id);
            Session::flash('success', '傳票已刪除');
        } catch (Exception $e) {
            Session::flash('error', '刪除失敗: ' . $e->getMessage());
        }
        redirect('/accounting.php?action=journals');
        break;

    // ---- AJAX: 依日期取傳票號碼 ----
    case 'ajax_voucher_number':
        $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        $number = $model->generateVoucherNumber($date);
        header('Content-Type: application/json');
        echo json_encode(array('number' => $number));
        exit;

    // ============================================================
    // 總帳查詢
    // ============================================================
    case 'ledger':
        $accountId = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        $costCenterId = !empty($_GET['cost_center_id']) ? (int)$_GET['cost_center_id'] : null;
        $codeFrom = isset($_GET['code_from']) ? trim($_GET['code_from']) : '';
        $codeTo = isset($_GET['code_to']) ? trim($_GET['code_to']) : '';

        $accounts = $model->getAccountsFlat(false);
        $costCenters = $model->getCostCenters();
        $ledgerEntries = array();
        $openingBalance = 0;
        $selectedAccount = null;
        $rangeAccounts = array();

        $isQuery = isset($_GET['start_date']) || isset($_GET['account_id']);

        if ($codeFrom || $codeTo || ($isQuery && !$accountId)) {
            // 科目區間或全部科目：用單一 SQL 查詢
            $rangeAccounts = array();
            foreach ($accounts as $a) {
                $code = $a['code'];
                if ($codeFrom && strcmp($code, $codeFrom) < 0) continue;
                if ($codeTo && strcmp($code, $codeTo) > 0) continue;
                $rangeAccounts[] = $a;
            }

            $rWhere = "je.status = 'posted' AND je.voucher_date >= ? AND je.voucher_date <= ?";
            $rParams = array($startDate, $endDate);
            if ($codeFrom || $codeTo) {
                $rangeIds = array_column($rangeAccounts, 'id');
                if (!empty($rangeIds)) {
                    $rPh = implode(',', array_fill(0, count($rangeIds), '?'));
                    $rWhere .= " AND jl.account_id IN ({$rPh})";
                    $rParams = array_merge($rParams, $rangeIds);
                } else {
                    $rWhere .= " AND 1=0";
                }
            }
            if ($costCenterId) { $rWhere .= ' AND jl.cost_center_id = ?'; $rParams[] = $costCenterId; }

            $rStmt = Database::getInstance()->prepare("
                SELECT jl.*, je.voucher_number, je.voucher_date, je.description as je_description,
                       coa.code as _account_code, coa.name as _account_name,
                       cc.name as cost_center_name
                FROM journal_entry_lines jl
                JOIN journal_entries je ON jl.journal_entry_id = je.id
                JOIN chart_of_accounts coa ON jl.account_id = coa.id
                LEFT JOIN cost_centers cc ON jl.cost_center_id = cc.id
                WHERE {$rWhere}
                ORDER BY je.voucher_date, je.id, jl.sort_order
            ");
            $rStmt->execute($rParams);
            $ledgerEntries = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($accountId) {
            $selectedAccount = $model->getAccountById($accountId);
            $openingBalance = $model->getOpeningBalance($accountId, $startDate, $costCenterId);
            $ledgerEntries = $model->getLedger($accountId, $startDate, $endDate, $costCenterId);
        }

        $pageTitle = '總帳查詢';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/ledger.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 立沖帳查詢
    // ============================================================
    case 'offset_ledger':
        $olAccountId = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
        $olAccountCodeFrom = isset($_GET['account_code_from']) ? trim($_GET['account_code_from']) : '';
        $olAccountCodeTo   = isset($_GET['account_code_to'])   ? trim($_GET['account_code_to'])   : '';
        $olRelType = isset($_GET['relation_type']) ? $_GET['relation_type'] : '';
        $olRelName = isset($_GET['relation_name']) ? trim($_GET['relation_name']) : '';
        $olRelIdFrom = isset($_GET['rel_id_from']) ? trim($_GET['rel_id_from']) : '';
        $olRelIdTo = isset($_GET['rel_id_to']) ? trim($_GET['rel_id_to']) : '';
        $olStatus = isset($_GET['status']) ? $_GET['status'] : '';
        $olDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $olDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        $olCostCenterId = !empty($_GET['cost_center_id']) ? (int)$_GET['cost_center_id'] : 0;
        $olKeyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

        $accounts = $model->getAccountsFlat(false);
        $costCenters = $model->getCostCenters();
        // 下拉選項：依已選的 relation_type 過濾（未選時全部）
        $olRelIdsSql = "
            SELECT DISTINCT ol.relation_id, ol.relation_type, ol.relation_name,
                   CASE WHEN ol.relation_type = 'vendor' THEN v.vendor_code ELSE NULL END AS vendor_code
            FROM offset_ledger ol
            LEFT JOIN vendors v ON ol.relation_type = 'vendor' AND v.id = ol.relation_id
            WHERE ol.relation_id IS NOT NULL AND ol.relation_id != ''
        ";
        $olRelIdsParams = array();
        if ($olRelType) {
            $olRelIdsSql .= " AND ol.relation_type = ?";
            $olRelIdsParams[] = $olRelType;
        }
        $olRelIdsSql .= " ORDER BY CAST(ol.relation_id AS UNSIGNED)";
        $olRelIdsStmt = Database::getInstance()->prepare($olRelIdsSql);
        $olRelIdsStmt->execute($olRelIdsParams);
        $olRelIds = $olRelIdsStmt->fetchAll(PDO::FETCH_ASSOC);

        $where = '1=1';
        $params = array();
        if ($olAccountId) { $where .= ' AND ol.account_id = ?'; $params[] = $olAccountId; }
        // 會計科目起迄（依 code）
        if ($olAccountCodeFrom !== '' && $olAccountCodeTo !== '') {
            $where .= ' AND coa.code BETWEEN ? AND ?';
            $params[] = $olAccountCodeFrom;
            $params[] = $olAccountCodeTo;
        } elseif ($olAccountCodeFrom !== '') {
            $where .= ' AND coa.code >= ?';
            $params[] = $olAccountCodeFrom;
        } elseif ($olAccountCodeTo !== '') {
            $where .= ' AND coa.code <= ?';
            $params[] = $olAccountCodeTo;
        }
        if ($olRelType) { $where .= ' AND ol.relation_type = ?'; $params[] = $olRelType; }
        if ($olRelIdFrom && $olRelIdTo) {
            $where .= ' AND CAST(ol.relation_id AS UNSIGNED) BETWEEN ? AND ?';
            $params[] = (int)$olRelIdFrom;
            $params[] = (int)$olRelIdTo;
        } elseif ($olRelIdFrom) {
            $where .= ' AND CAST(ol.relation_id AS UNSIGNED) >= ?';
            $params[] = (int)$olRelIdFrom;
        } elseif ($olRelIdTo) {
            $where .= ' AND CAST(ol.relation_id AS UNSIGNED) <= ?';
            $params[] = (int)$olRelIdTo;
        }
        // relation_name filter removed - use relation_id range instead
        if ($olStatus) { $where .= ' AND ol.status = ?'; $params[] = $olStatus; }
        if ($olDateFrom) { $where .= ' AND ol.voucher_date >= ?'; $params[] = $olDateFrom; }
        if ($olDateTo) { $where .= ' AND ol.voucher_date <= ?'; $params[] = $olDateTo; }
        if ($olCostCenterId) { $where .= ' AND ol.cost_center_id = ?'; $params[] = $olCostCenterId; }
        // 全域關鍵字：搜尋 傳票號/往來對象/會計科目/廠商編號
        if ($olKeyword !== '') {
            $where .= ' AND (ol.voucher_number LIKE ? OR ol.relation_name LIKE ? OR coa.code LIKE ? OR coa.name LIKE ? OR v.vendor_code LIKE ?)';
            $kw = '%' . $olKeyword . '%';
            array_push($params, $kw, $kw, $kw, $kw, $kw);
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT ol.*, coa.code AS account_code, coa.name AS account_name, cc.name AS cost_center_name,
                   CASE WHEN ol.relation_type = 'vendor' THEN v.vendor_code ELSE NULL END AS relation_display_code
            FROM offset_ledger ol
            LEFT JOIN chart_of_accounts coa ON ol.account_id = coa.id
            LEFT JOIN cost_centers cc ON ol.cost_center_id = cc.id
            LEFT JOIN vendors v ON ol.relation_type = 'vendor' AND v.id = ol.relation_id
            WHERE {$where}
            ORDER BY ol.voucher_date DESC, ol.id DESC
        ");
        $stmt->execute($params);
        $offsetRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 沖帳明細
        $offsetDetails = array();
        if (!empty($offsetRecords)) {
            $ledgerIds = array_column($offsetRecords, 'id');
            if (!empty($ledgerIds)) {
                $placeholders = implode(',', array_fill(0, count($ledgerIds), '?'));
                $detStmt = $db->prepare("SELECT * FROM offset_details WHERE ledger_id IN ({$placeholders}) ORDER BY voucher_date");
                $detStmt->execute($ledgerIds);
                foreach ($detStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
                    $offsetDetails[$d['ledger_id']][] = $d;
                }
            }
        }

        $pageTitle = '立沖帳查詢';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/offset_ledger.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 預算編輯
    // ============================================================
    case 'budget':
        Auth::requirePermission('accounting.manage');
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $ccId = !empty($_GET['cost_center_id']) ? (int)$_GET['cost_center_id'] : null;
        $costCenters = $model->getCostCenters();
        $plAccounts = $model->getPLAccounts();
        $budgets = $model->getBudgets($year, $ccId);

        // 建立 budget map: account_id => month => amount
        $budgetMap = array();
        foreach ($budgets as $b) {
            $budgetMap[$b['account_id']][$b['month']] = (float)$b['amount'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['budget'])) {
            foreach ($_POST['budget'] as $accountId => $months) {
                foreach ($months as $month => $amount) {
                    $amt = (float)str_replace(',', '', $amount);
                    if ($amt != 0) {
                        $model->saveBudget($year, $month, $accountId, $ccId, $amt, Auth::user()['id']);
                    }
                }
            }
            redirect('/accounting.php?action=budget&year=' . $year . ($ccId ? '&cost_center_id=' . $ccId : '') . '&saved=1');
        }

        if (isset($_POST['copy_from_year'])) {
            $model->copyBudget((int)$_POST['copy_from_year'], $year, $ccId);
            redirect('/accounting.php?action=budget&year=' . $year . ($ccId ? '&cost_center_id=' . $ccId : '') . '&copied=1');
        }

        $pageTitle = '預算編輯';
        $currentPage = 'accounting';
        $action = 'budget';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/budget.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 財務報表
    // ============================================================
    case 'financial_reports':
        $frYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $frMonthFrom = isset($_GET['month_from']) ? (int)$_GET['month_from'] : 1;
        $frMonthTo = isset($_GET['month_to']) ? (int)$_GET['month_to'] : (int)date('n');
        $frCcId = !empty($_GET['cost_center_id']) ? (int)$_GET['cost_center_id'] : null;
        $frTab = isset($_GET['tab']) ? $_GET['tab'] : 'income_statement';
        $costCenters = $model->getCostCenters();

        $dateFrom = sprintf('%04d-%02d-01', $frYear, $frMonthFrom);
        $dateTo = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $frYear, $frMonthTo)));

        // 損益表資料
        $plData = $model->getIncomeStatement($dateFrom, $dateTo, $frCcId);

        // 月別損益
        $monthlyPL = $model->getMonthlyIncomeStatement($frYear, $frCcId);

        // 預算資料
        $budgetData = $model->getBudgetSummary($frYear, $frMonthFrom, $frMonthTo, $frCcId);

        // 資產負債表
        $bsData = $model->getBalanceSheetData($dateTo, $frCcId);

        // 試算表資料（for 閱報式）
        $trialData = $model->getTrialBalance($dateTo, $frCcId ? $frCcId : 0);

        // 彙總損益
        $plSummary = array('revenue' => 0, 'cost' => 0, 'expense' => 0, 'other_income' => 0, 'other_expense' => 0, 'tax' => 0);
        foreach ($plData as $row) {
            $net = (float)$row['total_credit'] - (float)$row['total_debit'];
            $prefix = $row['code_prefix'];
            if ($prefix === '4') $plSummary['revenue'] += $net;
            elseif ($prefix === '5') $plSummary['cost'] += ((float)$row['total_debit'] - (float)$row['total_credit']);
            elseif ($prefix === '6') $plSummary['expense'] += ((float)$row['total_debit'] - (float)$row['total_credit']);
            elseif ($prefix === '7') {
                if ($row['normal_balance'] === 'credit') $plSummary['other_income'] += $net;
                else $plSummary['other_expense'] += ((float)$row['total_debit'] - (float)$row['total_credit']);
            }
            elseif ($prefix === '8') $plSummary['tax'] += ((float)$row['total_debit'] - (float)$row['total_credit']);
        }
        $plSummary['gross_profit'] = $plSummary['revenue'] - $plSummary['cost'];
        $plSummary['operating_profit'] = $plSummary['gross_profit'] - $plSummary['expense'];
        $plSummary['net_income'] = $plSummary['operating_profit'] + $plSummary['other_income'] - $plSummary['other_expense'] - $plSummary['tax'];

        // 預算彙總
        $budgetSummary = array('revenue' => 0, 'cost' => 0, 'expense' => 0, 'other' => 0, 'tax' => 0);
        foreach ($budgetData as $row) {
            $prefix = $row['code_prefix'];
            if ($prefix === '4') $budgetSummary['revenue'] += (float)$row['budget_amount'];
            elseif ($prefix === '5') $budgetSummary['cost'] += (float)$row['budget_amount'];
            elseif ($prefix === '6') $budgetSummary['expense'] += (float)$row['budget_amount'];
            elseif ($prefix === '7') $budgetSummary['other'] += (float)$row['budget_amount'];
            elseif ($prefix === '8') $budgetSummary['tax'] += (float)$row['budget_amount'];
        }

        // 月別彙總
        $monthlySum = array();
        for ($m = 1; $m <= 12; $m++) {
            $monthlySum[$m] = array('revenue' => 0, 'cost' => 0, 'expense' => 0, 'other' => 0, 'tax' => 0, 'net' => 0);
        }
        foreach ($monthlyPL as $row) {
            $m = (int)$row['month'];
            $prefix = $row['code_prefix'];
            $net = (float)$row['total_credit'] - (float)$row['total_debit'];
            $debitNet = (float)$row['total_debit'] - (float)$row['total_credit'];
            if ($prefix === '4') $monthlySum[$m]['revenue'] += $net;
            elseif ($prefix === '5') $monthlySum[$m]['cost'] += $debitNet;
            elseif ($prefix === '6') $monthlySum[$m]['expense'] += $debitNet;
            elseif ($prefix === '7') $monthlySum[$m]['other'] += $net;
            elseif ($prefix === '8') $monthlySum[$m]['tax'] += $debitNet;
        }
        for ($m = 1; $m <= 12; $m++) {
            $monthlySum[$m]['net'] = $monthlySum[$m]['revenue'] - $monthlySum[$m]['cost'] - $monthlySum[$m]['expense'] + $monthlySum[$m]['other'] - $monthlySum[$m]['tax'];
        }

        $pageTitle = '財務報表';
        $currentPage = 'accounting';
        $action = 'financial_reports';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/financial_reports.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 立沖帳報表（明細表/科目餘額表/分類帳）
    // ============================================================
    // ============================================================
    // 傳票報表（日報表/日記帳/日計表/現金簿/總分類帳/明細分類帳）
    // ============================================================
    case 'journal_reports':
        $jrDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
        $jrDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
        $jrCostCenterId = !empty($_GET['cost_center_id']) ? (int)$_GET['cost_center_id'] : 0;
        $jrAccountFrom = isset($_GET['account_from']) ? trim($_GET['account_from']) : '';
        $jrAccountTo = isset($_GET['account_to']) ? trim($_GET['account_to']) : '';
        $jrTab = isset($_GET['tab']) ? $_GET['tab'] : 'daily_voucher';

        $costCenters = $model->getCostCenters();
        $db = Database::getInstance();

        $ccWhere = '';
        $ccParams = array('posted', $jrDateFrom, $jrDateTo);
        if ($jrCostCenterId) { $ccWhere .= ' AND jl.cost_center_id = ?'; $ccParams[] = $jrCostCenterId; }

        // 科目區間（用於 jrLines 查詢，影響日記帳/分類帳/日計表/明細分類帳）
        $accWhere = $ccWhere;
        $accParams = $ccParams;
        if ($jrAccountFrom !== '') { $accWhere .= ' AND coa.code >= ?'; $accParams[] = $jrAccountFrom; }
        if ($jrAccountTo !== '')   { $accWhere .= ' AND coa.code <= ?'; $accParams[] = $jrAccountTo; }

        // 1. 傳票列表（日報表用）— 日報表不篩科目，只篩日期 + 成本中心
        $jrVouchers = $db->prepare("
            SELECT je.*,
                   SUM(jl.debit_amount) as total_debit, SUM(jl.credit_amount) as total_credit,
                   u.real_name as created_by_name
            FROM journal_entries je
            LEFT JOIN journal_entry_lines jl ON je.id = jl.journal_entry_id
            LEFT JOIN users u ON je.created_by = u.id
            WHERE je.status = ? AND je.voucher_date >= ? AND je.voucher_date <= ? {$ccWhere}
            GROUP BY je.id
            ORDER BY je.voucher_date, je.voucher_number
        ");
        $jrVouchers->execute($ccParams);
        $jrVoucherList = $jrVouchers->fetchAll(PDO::FETCH_ASSOC);

        // 2. 全部分錄明細（日記帳/分類帳用）— 套用科目區間
        $jrLines = $db->prepare("
            SELECT jl.*, je.voucher_number, je.voucher_date, je.description as je_description,
                   coa.code as account_code, coa.name as account_name, coa.normal_balance,
                   coa.account_type as coa_account_type,
                   cc.name as cost_center_name,
                   u.real_name as line_created_by
            FROM journal_entry_lines jl
            JOIN journal_entries je ON jl.journal_entry_id = je.id
            JOIN chart_of_accounts coa ON jl.account_id = coa.id
            LEFT JOIN cost_centers cc ON jl.cost_center_id = cc.id
            LEFT JOIN users u ON je.created_by = u.id
            WHERE je.status = ? AND je.voucher_date >= ? AND je.voucher_date <= ? {$accWhere}
            ORDER BY je.voucher_date, je.voucher_number, jl.sort_order
        ");
        $jrLines->execute($accParams);
        $jrLineList = $jrLines->fetchAll(PDO::FETCH_ASSOC);

        // 按日期分組（日計表用）
        $jrDaily = array();
        foreach ($jrLineList as $l) {
            $d = $l['voucher_date'];
            if (!isset($jrDaily[$d])) $jrDaily[$d] = array('debit' => 0, 'credit' => 0, 'count' => 0);
            $jrDaily[$d]['debit'] += (float)$l['debit_amount'];
            $jrDaily[$d]['credit'] += (float)$l['credit_amount'];
        }
        foreach ($jrVoucherList as $v) {
            $d = $v['voucher_date'];
            if (isset($jrDaily[$d])) $jrDaily[$d]['count']++;
        }

        // 按科目分組（分類帳用）- 所有交易科目
        $jrByAccount = array();
        foreach ($jrLineList as $l) {
            $key = $l['account_code'];
            if (!isset($jrByAccount[$key])) {
                $jrByAccount[$key] = array('code' => $l['account_code'], 'name' => $l['account_name'], 'normal_balance' => $l['normal_balance'], 'lines' => array());
            }
            $jrByAccount[$key]['lines'][] = $l;
        }
        ksort($jrByAccount);

        // 總分類帳用 - 前4碼合併到xxx000
        $jrGeneralLedger = array();
        foreach ($jrLineList as $l) {
            $code = $l['account_code'];
            $prefix4 = substr($code, 0, 4);
            // 找上層科目 (前4碼+000)
            $parentCode = $prefix4 . str_repeat('0', strlen($code) - 4);
            if (!isset($jrGeneralLedger[$parentCode])) {
                // 嘗試找上層科目名稱
                $parentName = $l['account_name'];
                $parentNormal = $l['normal_balance'];
                foreach ($jrLineList as $chk) {
                    if ($chk['account_code'] === $parentCode) {
                        $parentName = $chk['account_name'];
                        $parentNormal = $chk['normal_balance'];
                        break;
                    }
                }
                // 查DB取上層科目名
                $pStmt = $db->prepare("SELECT name, normal_balance FROM chart_of_accounts WHERE code = ? LIMIT 1");
                $pStmt->execute(array($parentCode));
                $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
                if ($pRow) { $parentName = $pRow['name']; $parentNormal = $pRow['normal_balance']; }
                $jrGeneralLedger[$parentCode] = array('code' => $parentCode, 'name' => $parentName, 'normal_balance' => $parentNormal, 'lines' => array());
            }
            $jrGeneralLedger[$parentCode]['lines'][] = $l;
        }
        ksort($jrGeneralLedger);

        // 日計表按科目統計
        $jrDailyByAccount = array();
        foreach ($jrLineList as $l) {
            $key = $l['account_code'];
            if (!isset($jrDailyByAccount[$key])) {
                $jrDailyByAccount[$key] = array(
                    'code' => $l['account_code'],
                    'name' => $l['account_name'],
                    'normal_balance' => $l['normal_balance'],
                    'debit' => 0, 'credit' => 0,
                    'debit_count' => 0, 'credit_count' => 0
                );
            }
            $d = (float)$l['debit_amount']; $c = (float)$l['credit_amount'];
            $jrDailyByAccount[$key]['debit'] += $d;
            $jrDailyByAccount[$key]['credit'] += $c;
            if ($d > 0) $jrDailyByAccount[$key]['debit_count']++;
            if ($c > 0) $jrDailyByAccount[$key]['credit_count']++;
        }
        ksort($jrDailyByAccount);

        // 現金科目（1111開頭）
        $jrCashLines = array();
        foreach ($jrLineList as $l) {
            if (strpos($l['account_code'], '1111') === 0) {
                $jrCashLines[] = $l;
            }
        }

        // 期初餘額：每個科目在 date_from 之前的累計借/貸
        // 用於總分類帳、明細分類帳，讓期末餘額對應資產負債表（若 date_to = asOfDate）
        $ccOpeningWhere = '';
        $ccOpeningParams = array('posted', $jrDateFrom);
        if ($jrCostCenterId) { $ccOpeningWhere .= ' AND jl.cost_center_id = ?'; $ccOpeningParams[] = $jrCostCenterId; }

        $jrOpeningBalance = array(); // [account_code] => debit - credit (未乘 normal_balance)
        $openStmt = $db->prepare("
            SELECT coa.code AS account_code, coa.normal_balance,
                   COALESCE(SUM(jl.debit_amount), 0) AS d,
                   COALESCE(SUM(jl.credit_amount), 0) AS c
            FROM journal_entry_lines jl
            JOIN journal_entries je ON jl.journal_entry_id = je.id
            JOIN chart_of_accounts coa ON jl.account_id = coa.id
            WHERE je.status = ? AND je.voucher_date < ? {$ccOpeningWhere}
            GROUP BY coa.code, coa.normal_balance
        ");
        $openStmt->execute($ccOpeningParams);
        foreach ($openStmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $diff = (float)$o['d'] - (float)$o['c'];
            // 對借記科目：期初餘額 = 借-貸；對貸記科目：= 貸-借
            $jrOpeningBalance[$o['account_code']] = array(
                'debit_minus_credit' => $diff,
                'normal_balance' => $o['normal_balance'],
                // 依正常餘額方向計算的期初餘額（正值代表正常方向）
                'opening' => ($o['normal_balance'] === 'debit') ? $diff : -$diff,
            );
        }

        // 總分類帳（前 4 碼合併）期初餘額
        $jrGlOpeningBalance = array(); // [parent_code] => [opening => ..., normal_balance => ...]
        foreach ($jrOpeningBalance as $code => $info) {
            $prefix4 = substr($code, 0, 4);
            $parentCode = $prefix4 . str_repeat('0', strlen($code) - 4);
            if (!isset($jrGlOpeningBalance[$parentCode])) {
                $jrGlOpeningBalance[$parentCode] = array('opening' => 0, 'normal_balance' => $info['normal_balance']);
            }
            $jrGlOpeningBalance[$parentCode]['opening'] += $info['opening'];
        }

        $pageTitle = '傳票報表';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/journal_reports.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'offset_reports':
        $orAccountId = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
        $orAccountCodeFrom = isset($_GET['account_code_from']) ? trim($_GET['account_code_from']) : '';
        $orAccountCodeTo   = isset($_GET['account_code_to'])   ? trim($_GET['account_code_to'])   : '';
        $orRelType = isset($_GET['relation_type']) ? $_GET['relation_type'] : '';
        $orRelIdFrom = isset($_GET['rel_id_from']) ? trim($_GET['rel_id_from']) : '';
        $orRelIdTo = isset($_GET['rel_id_to']) ? trim($_GET['rel_id_to']) : '';
        $orDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-01-01');
        $orDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
        $orCostCenterId = !empty($_GET['cost_center_id']) ? (int)$_GET['cost_center_id'] : 0;
        $orTab = isset($_GET['tab']) ? $_GET['tab'] : 'detail';
        $orKeyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

        $accounts = $model->getAccountsFlat(false);
        $costCenters = $model->getCostCenters();
        $db = Database::getInstance();

        // 往來編號清單（依已選 relation_type 過濾；廠商顯示 vendor_code）
        $orRelIdsSql = "
            SELECT DISTINCT ol.relation_id, ol.relation_type, ol.relation_name,
                   CASE WHEN ol.relation_type = 'vendor' THEN v.vendor_code ELSE NULL END AS vendor_code
            FROM offset_ledger ol
            LEFT JOIN vendors v ON ol.relation_type = 'vendor' AND v.id = ol.relation_id
            WHERE ol.relation_id IS NOT NULL AND ol.relation_id != ''
        ";
        $orRelIdsParams = array();
        if ($orRelType) {
            $orRelIdsSql .= " AND ol.relation_type = ?";
            $orRelIdsParams[] = $orRelType;
        }
        $orRelIdsSql .= " ORDER BY CAST(ol.relation_id AS UNSIGNED)";
        $orRelIdsStmt = $db->prepare($orRelIdsSql);
        $orRelIdsStmt->execute($orRelIdsParams);
        $orRelIds = $orRelIdsStmt->fetchAll(PDO::FETCH_ASSOC);

        // 查詢立帳記錄
        $where = '1=1';
        $params = array();
        if ($orAccountId) { $where .= ' AND ol.account_id = ?'; $params[] = $orAccountId; }
        // 會計科目起迄（依 code）
        if ($orAccountCodeFrom !== '' && $orAccountCodeTo !== '') {
            $where .= ' AND coa.code BETWEEN ? AND ?';
            $params[] = $orAccountCodeFrom;
            $params[] = $orAccountCodeTo;
        } elseif ($orAccountCodeFrom !== '') {
            $where .= ' AND coa.code >= ?';
            $params[] = $orAccountCodeFrom;
        } elseif ($orAccountCodeTo !== '') {
            $where .= ' AND coa.code <= ?';
            $params[] = $orAccountCodeTo;
        }
        if ($orRelType) { $where .= ' AND ol.relation_type = ?'; $params[] = $orRelType; }
        if ($orRelIdFrom && $orRelIdTo) {
            $where .= ' AND CAST(ol.relation_id AS UNSIGNED) BETWEEN ? AND ?';
            $params[] = (int)$orRelIdFrom; $params[] = (int)$orRelIdTo;
        } elseif ($orRelIdFrom) {
            $where .= ' AND CAST(ol.relation_id AS UNSIGNED) >= ?'; $params[] = (int)$orRelIdFrom;
        } elseif ($orRelIdTo) {
            $where .= ' AND CAST(ol.relation_id AS UNSIGNED) <= ?'; $params[] = (int)$orRelIdTo;
        }
        if ($orDateFrom) { $where .= ' AND ol.voucher_date >= ?'; $params[] = $orDateFrom; }
        if ($orDateTo) { $where .= ' AND ol.voucher_date <= ?'; $params[] = $orDateTo; }
        if ($orCostCenterId) { $where .= ' AND ol.cost_center_id = ?'; $params[] = $orCostCenterId; }
        // 全域關鍵字：搜尋 傳票號/往來對象/會計科目/廠商編號
        if ($orKeyword !== '') {
            $where .= ' AND (ol.voucher_number LIKE ? OR ol.relation_name LIKE ? OR coa.code LIKE ? OR coa.name LIKE ? OR v.vendor_code LIKE ?)';
            $kw = '%' . $orKeyword . '%';
            array_push($params, $kw, $kw, $kw, $kw, $kw);
        }

        $stmt = $db->prepare("
            SELECT ol.*, coa.code AS account_code, coa.name AS account_name, cc.name AS cost_center_name,
                   CASE WHEN ol.relation_type = 'vendor' THEN v.vendor_code ELSE NULL END AS relation_display_code
            FROM offset_ledger ol
            LEFT JOIN chart_of_accounts coa ON ol.account_id = coa.id
            LEFT JOIN cost_centers cc ON ol.cost_center_id = cc.id
            LEFT JOIN vendors v ON ol.relation_type = 'vendor' AND v.id = ol.relation_id
            WHERE {$where}
            ORDER BY coa.code, CAST(ol.relation_id AS UNSIGNED), ol.voucher_date, ol.id
        ");
        $stmt->execute($params);
        $orRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 沖帳明細
        $orDetails = array();
        if (!empty($orRecords)) {
            $ids = array_column($orRecords, 'id');
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $dStmt = $db->prepare("SELECT od.*, je.voucher_number AS offset_voucher_number, je.voucher_date AS offset_date
                FROM offset_details od
                LEFT JOIN journal_entries je ON od.journal_entry_id = je.id
                WHERE od.ledger_id IN ({$ph}) ORDER BY od.voucher_date, od.id");
            $dStmt->execute($ids);
            foreach ($dStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
                $orDetails[$d['ledger_id']][] = $d;
            }
        }

        // 按科目+往來編號分組
        $orGrouped = array();
        foreach ($orRecords as $r) {
            $key = $r['account_code'] . '_' . $r['relation_id'];
            if (!isset($orGrouped[$key])) {
                $orGrouped[$key] = array(
                    'account_code' => $r['account_code'],
                    'account_name' => $r['account_name'],
                    'relation_type' => $r['relation_type'],
                    'relation_id' => $r['relation_id'],
                    'relation_display_code' => !empty($r['relation_display_code']) ? $r['relation_display_code'] : $r['relation_id'],
                    'relation_name' => $r['relation_name'],
                    'cost_center_name' => $r['cost_center_name'],
                    'records' => array(),
                    'sum_original' => 0,
                    'sum_offset' => 0,
                    'sum_remaining' => 0,
                );
            }
            $orGrouped[$key]['records'][] = $r;
            $orGrouped[$key]['sum_original'] += (float)$r['original_amount'];
            $orGrouped[$key]['sum_offset'] += (float)$r['offset_total'];
            $orGrouped[$key]['sum_remaining'] += (float)$r['remaining_amount'];
        }

        $pageTitle = '立沖帳報表';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/offset_reports.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 試算表
    // ============================================================
    case 'trial_balance':
        $asOfDate = isset($_GET['as_of_date']) ? $_GET['as_of_date'] : date('Y-m-d');
        $costCenterId = !empty($_GET['cost_center_id']) ? (int)$_GET['cost_center_id'] : null;
        $costCenters = $model->getCostCenters();
        $trialBalance = $model->getTrialBalance($asOfDate, $costCenterId);

        $pageTitle = '試算表';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/trial_balance.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // AJAX: 取得科目資訊
    // ============================================================
    case 'ajax_account_info':
        header('Content-Type: application/json');
        $accountId = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $account = $model->getAccountById($accountId);
        echo json_encode($account ? $account : array());
        exit;

    // ---- AJAX: 取得科目類別（依總類） ----
    case 'ajax_categories':
        header('Content-Type: application/json');
        $typeNum = isset($_GET['type_num']) ? $_GET['type_num'] : '';
        $stmt = Database::getInstance()->prepare("
            SELECT DISTINCT cat_code, cat_name FROM chart_of_accounts
            WHERE type_num = ? AND cat_code IS NOT NULL AND cat_code != ''
            ORDER BY cat_code
        ");
        $stmt->execute(array($typeNum));
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;

    // ---- AJAX: 查詢「其他」往來編號對應的名稱 ----
    case 'ajax_check_other_relation':
        header('Content-Type: application/json');
        $checkRelId = isset($_GET['relation_id']) ? trim($_GET['relation_id']) : '';
        if ($checkRelId === '') {
            echo json_encode(array('found' => false));
            exit;
        }
        $stmt = Database::getInstance()->prepare("
            SELECT relation_name FROM journal_entry_lines
            WHERE relation_type = 'other' AND relation_id = ? AND relation_name IS NOT NULL AND relation_name != ''
            LIMIT 1
        ");
        $stmt->execute(array($checkRelId));
        $existName = $stmt->fetchColumn();
        if ($existName) {
            echo json_encode(array('found' => true, 'relation_name' => $existName));
        } else {
            echo json_encode(array('found' => false));
        }
        exit;

    // ---- AJAX: 取得未沖完立帳記錄 ----
    case 'ajax_open_ledgers':
        header('Content-Type: application/json');
        $relType = isset($_GET['relation_type']) ? $_GET['relation_type'] : '';
        $relId = isset($_GET['relation_id']) ? $_GET['relation_id'] : '';
        $relName = isset($_GET['relation_name']) ? trim($_GET['relation_name']) : '';
        $accountId = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
        $costCenterId = isset($_GET['cost_center_id']) ? (int)$_GET['cost_center_id'] : 0;

        $where = "ol.status IN ('open','partial')";
        $params = array();

        // 往來類型與往來編號為必要過濾條件
        if ($relType) {
            $where .= " AND ol.relation_type = ?";
            $params[] = $relType;
        }
        $where .= " AND ol.relation_id = ?";
        $params[] = $relId;

        // 往來類型為「其他」時，必須同時比對往來對象名稱
        if ($relType === 'other' && $relName !== '') {
            $where .= " AND ol.relation_name = ?";
            $params[] = $relName;
        }

        if ($accountId) {
            $where .= " AND ol.account_id = ?";
            $params[] = $accountId;
        }
        if ($costCenterId) {
            $where .= " AND ol.cost_center_id = ?";
            $params[] = $costCenterId;
        }

        $stmt = Database::getInstance()->prepare("
            SELECT ol.*, coa.account_code, coa.account_name, cc.name as cost_center_name,
                   orig.description AS original_description
            FROM offset_ledger ol
            LEFT JOIN chart_of_accounts coa ON ol.account_id = coa.id
            LEFT JOIN cost_centers cc ON ol.cost_center_id = cc.id
            LEFT JOIN journal_entry_lines orig ON orig.id = ol.journal_line_id
            WHERE {$where}
            ORDER BY ol.id ASC
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;

    // ============================================================
    // 銀行對帳
    // ============================================================
    case 'voucher_reconciliation':
        $source = isset($_GET['source']) ? $_GET['source'] : 'bank';
        $validSources = array('bank','petty_cash','reserve_fund','cash_details');
        if (!in_array($source, $validSources)) $source = 'bank';
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
        $endDate   = isset($_GET['end_date'])   ? $_GET['end_date']   : date('Y-m-d');
        $statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
        $branchFilter = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
        $sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'asc' ? 'asc' : 'desc'; // 預設：新→舊

        $branches = $model->getBranches();
        $records = $model->getVoucherReconciliation($source, $startDate, $endDate, $branchFilter ?: null);

        // 統計 + 狀態篩選（合併列不計入 total/分類）
        $_realRecCount = 0;
        $stats = array('matched_precise'=>0, 'matched_fuzzy'=>0, 'matched_amount_mismatch'=>0, 'unmatched'=>0, 'total'=>0);
        foreach ($records as $r) {
            if ($r['match_status'] === 'merged_into_prev') continue;
            $_realRecCount++;
            if (isset($stats[$r['match_status']])) $stats[$r['match_status']]++;
        }
        $stats['total'] = $_realRecCount;
        if ($statusFilter) {
            $records = array_values(array_filter($records, function($r) use ($statusFilter) {
                return $r['match_status'] === $statusFilter;
            }));
        } else {
            // 預設隱藏「精準匹配」列（已經確認過的不再顯示，減少視覺干擾）
            // 若要看，點統計卡「精準匹配」進去即可
            $records = array_values(array_filter($records, function($r) {
                return $r['match_status'] !== 'matched_precise';
            }));
        }

        // 排序（預設新→舊）
        usort($records, function($a, $b) use ($sortOrder) {
            $cmp = strcmp($b['date'], $a['date']);
            if ($cmp === 0) $cmp = (int)$b['source_id'] - (int)$a['source_id'];
            return $sortOrder === 'asc' ? -$cmp : $cmp;
        });

        $pageTitle = '傳票核對報表';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/voucher_reconciliation.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'confirm_voucher_match':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗或無權限');
            redirect('/accounting.php?action=voucher_reconciliation');
        }
        $_cvSource = isset($_POST['source_module']) ? $_POST['source_module'] : '';
        $_cvSrcId  = isset($_POST['source_id']) ? (int)$_POST['source_id'] : 0;
        $_cvVid    = isset($_POST['voucher_id']) ? (int)$_POST['voucher_id'] : 0;
        $_cvValid  = array('bank','petty_cash','reserve_fund','cash_details');
        if (!in_array($_cvSource, $_cvValid) || $_cvSrcId <= 0 || $_cvVid <= 0) {
            Session::flash('error', '參數錯誤');
            redirect('/accounting.php?action=voucher_reconciliation');
        }
        try {
            $_cvDb = Database::getInstance();
            // 先檢查傳票存在
            $chk = $_cvDb->prepare("SELECT id FROM journal_entries WHERE id = ? LIMIT 1");
            $chk->execute(array($_cvVid));
            if (!$chk->fetchColumn()) {
                Session::flash('error', '傳票不存在');
            } else {
                $_cvDb->prepare("UPDATE journal_entries SET source_module = ?, source_id = ? WHERE id = ?")
                      ->execute(array($_cvSource, $_cvSrcId, $_cvVid));
                AuditLog::log('journal_entries', 'confirm_match', $_cvVid, "綁定到 {$_cvSource}#{$_cvSrcId}");
                Session::flash('success', '已確認匹配');
            }
        } catch (Exception $e) {
            Session::flash('error', '操作失敗: ' . $e->getMessage());
        }
        $_cvRtn = isset($_POST['return_to']) ? $_POST['return_to'] : '/accounting.php?action=voucher_reconciliation';
        if (strpos($_cvRtn, '/accounting.php') !== 0) $_cvRtn = '/accounting.php?action=voucher_reconciliation';
        redirect($_cvRtn);
        break;

    case 'reconciliation':
        require_once __DIR__ . '/../modules/accounting/ReconciliationModel.php';
        $reconModel = new ReconciliationModel();

        $filters = array(
            'bank_account' => isset($_GET['bank_account']) ? $_GET['bank_account'] : '',
            'date_from'    => isset($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'      => isset($_GET['date_to']) ? $_GET['date_to'] : '',
            'keyword'      => isset($_GET['keyword']) ? $_GET['keyword'] : '',
        );

        $bankTxs = $reconModel->getUnreconciledBankTransactions($filters);
        $sysTxs = $reconModel->getUnreconciledSystemTransactions($filters);
        $summary = $reconModel->getReconciliationSummary(
            $filters['bank_account'],
            !empty($filters['date_to']) ? $filters['date_to'] : date('Y-m-d')
        );
        $bankAccounts = $reconModel->getBankAccountNames();

        $pageTitle = '銀行對帳';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/reconciliation.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'reconciliation_auto_match':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/accounting.php?action=reconciliation');
        }
        verify_csrf();
        require_once __DIR__ . '/../modules/accounting/ReconciliationModel.php';
        $reconModel = new ReconciliationModel();
        $matchCount = $reconModel->autoMatch();
        Session::flash('success', "自動對帳完成，成功配對 {$matchCount} 筆");
        redirect('/accounting.php?action=reconciliation');
        break;

    case 'reconciliation_manual_match':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/accounting.php?action=reconciliation');
        }
        verify_csrf();
        require_once __DIR__ . '/../modules/accounting/ReconciliationModel.php';
        $reconModel = new ReconciliationModel();
        $bankTxId = (int)(!empty($_POST['bank_tx_id']) ? $_POST['bank_tx_id'] : 0);
        $sysType = !empty($_POST['sys_type']) ? $_POST['sys_type'] : '';
        $sysId = (int)(!empty($_POST['sys_id']) ? $_POST['sys_id'] : 0);
        if ($bankTxId && $sysType && $sysId) {
            $reconModel->manualMatch($bankTxId, $sysType, $sysId);
            Session::flash('success', '手動對帳成功');
        } else {
            Session::flash('error', '請選擇銀行交易與系統交易');
        }
        redirect('/accounting.php?action=reconciliation');
        break;

    case 'reconciliation_unmatch':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/accounting.php?action=reconciliation');
        }
        verify_csrf();
        require_once __DIR__ . '/../modules/accounting/ReconciliationModel.php';
        $reconModel = new ReconciliationModel();
        $bankTxId = (int)(!empty($_POST['bank_tx_id']) ? $_POST['bank_tx_id'] : 0);
        if ($bankTxId) {
            $reconModel->unmatch($bankTxId);
            Session::flash('success', '已取消對帳配對');
        }
        redirect('/accounting.php?action=reconciliation');
        break;

    // ============================================================
    // 損益表
    // ============================================================
    case 'income_statement':
        require_once __DIR__ . '/../modules/accounting/ReportModel.php';
        $reportModel = new ReportModel();
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        $costCenterId = !empty($_GET['cost_center_id']) ? (int)$_GET['cost_center_id'] : null;
        $costCenters = $model->getCostCenters();

        $report = $reportModel->getIncomeStatement($startDate, $endDate, $costCenterId);

        $pageTitle = '損益表';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/income_statement.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 資產負債表
    // ============================================================
    case 'balance_sheet':
        require_once __DIR__ . '/../modules/accounting/ReportModel.php';
        $reportModel = new ReportModel();
        $asOfDate = isset($_GET['as_of_date']) ? $_GET['as_of_date'] : date('Y-m-d');
        $costCenterId = !empty($_GET['cost_center_id']) ? (int)$_GET['cost_center_id'] : null;
        $costCenters = $model->getCostCenters();

        $report = $reportModel->getBalanceSheet($asOfDate, $costCenterId);

        $pageTitle = '資產負債表';
        $currentPage = 'accounting';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/balance_sheet.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    default:
        redirect('/accounting.php?action=journals');
        break;
}
