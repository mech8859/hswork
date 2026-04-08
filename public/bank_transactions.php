<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view')) {
    Session::flash('error', '無權限查看此頁面');
    redirect('/');
}
require_once __DIR__ . '/../modules/finance/FinanceModel.php';

$model = new FinanceModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$isBoss = Auth::hasPermission('all');
$canManageFinance = Auth::hasPermission('finance.manage');

switch ($action) {
    // ---- 列表 ----
    case 'list':
        $filters = array(
            'bank_account' => !empty($_GET['bank_account']) ? $_GET['bank_account'] : '',
            'date_from'    => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'      => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
            'keyword'      => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
        );
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $result = $model->getBankTransactions($filters, $page);
        $records = $result['data'];
        $bankSummary = $model->getBankSummary($filters);

        $pageTitle = '銀行帳戶明細';
        $currentPage = 'bank_transactions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/bank_transactions/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增表單 ----
    case 'create':
        if (!$canManageFinance && !$isBoss) {
            Session::flash('error', '無權限執行此操作');
            redirect('/bank_transactions.php');
        }
        $record = null;
        $pageTitle = '新增銀行交易';
        $currentPage = 'bank_transactions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/bank_transactions/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯表單 ----
    case 'edit':
        if (!$canManageFinance && !$isBoss) {
            Session::flash('error', '無權限執行此操作');
            redirect('/bank_transactions.php');
        }
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $record = $model->getBankTransaction($id);
        if (!$record) { redirect('/bank_transactions.php'); }

        // 批次匯入的舊資料若無交易編號，開啟編輯時自動補上
        if (empty($record['transaction_number'])) {
            $newNum = generate_doc_number('bank_transactions', !empty($record['transaction_date']) ? $record['transaction_date'] : null);
            if (!empty($newNum)) {
                $db = Database::getInstance();
                try {
                    $db->prepare("UPDATE bank_transactions SET transaction_number = ? WHERE id = ? AND (transaction_number IS NULL OR transaction_number = '')")
                       ->execute(array($newNum, $id));
                    $record['transaction_number'] = $newNum;
                } catch (Exception $autoNumEx) {
                    error_log('bank_transaction auto number failed: ' . $autoNumEx->getMessage());
                }
            }
        }

        $pageTitle = '編輯銀行交易';
        $currentPage = 'bank_transactions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/bank_transactions/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 儲存（新增/更新） ----
    case 'store':
        if (!$canManageFinance && !$isBoss) {
            Session::flash('error', '無權限執行此操作');
            redirect('/bank_transactions.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/bank_transactions.php'); }
        verify_csrf();

        $data = array(
            'bank_account'   => isset($_POST['bank_account']) ? trim($_POST['bank_account']) : '',
            'transaction_date' => isset($_POST['transaction_date']) ? trim($_POST['transaction_date']) : '',
            'summary'        => isset($_POST['summary']) ? trim($_POST['summary']) : '',
            'debit_amount'   => isset($_POST['debit_amount']) ? (int)str_replace(',', '', $_POST['debit_amount']) : 0,
            'credit_amount'  => isset($_POST['credit_amount']) ? (int)str_replace(',', '', $_POST['credit_amount']) : 0,
            'balance'        => isset($_POST['balance']) ? (int)str_replace(',', '', $_POST['balance']) : 0,
            'description'    => isset($_POST['description']) ? trim($_POST['description']) : '',
            'remark'         => isset($_POST['remark']) ? trim($_POST['remark']) : '',
            'upload_no'      => isset($_POST['upload_no']) ? trim($_POST['upload_no']) : '',
            'remittance_code'=> isset($_POST['remittance_code']) ? trim($_POST['remittance_code']) : '',
            'counterparty_account' => isset($_POST['counterparty_account']) ? trim($_POST['counterparty_account']) : '',
            'memo'           => isset($_POST['memo']) ? trim($_POST['memo']) : '',
        );

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $model->updateBankTransaction($id, $data);
            AuditLog::log('bank_transactions', 'update', $id, '更新銀行交易');
            Session::flash('success', '已更新銀行交易');
        } else {
            $newId = $model->createBankTransaction($data);
            AuditLog::log('bank_transactions', 'create', $newId ? $newId : 0, '新增銀行交易');
            Session::flash('success', '已新增銀行交易');
        }
        redirect('/bank_transactions.php');
        break;

    // ---- AJAX: 切換星號記號 ----
    case 'toggle_star':
        header('Content-Type: application/json; charset=utf-8');
        if (!$canManageFinance && !$isBoss) {
            echo json_encode(array('error' => '無權限'));
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法錯誤'));
            exit;
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(array('error' => '參數錯誤'));
            exit;
        }
        try {
            $db = Database::getInstance();
            // 取得目前狀態
            $curStmt = $db->prepare("SELECT is_starred FROM bank_transactions WHERE id = ?");
            $curStmt->execute(array($id));
            $cur = $curStmt->fetchColumn();
            if ($cur === false) {
                echo json_encode(array('error' => '記錄不存在'));
                exit;
            }
            $new = ((int)$cur === 1) ? 0 : 1;
            $db->prepare("UPDATE bank_transactions SET is_starred = ? WHERE id = ?")
               ->execute(array($new, $id));
            echo json_encode(array('success' => true, 'starred' => $new));
        } catch (Exception $e) {
            echo json_encode(array('error' => $e->getMessage()));
        }
        exit;

    // ---- 批次匯入表單 ----
    case 'import':
        if (!$canManageFinance && !$isBoss) {
            Session::flash('error', '無權限執行此操作');
            redirect('/bank_transactions.php');
        }
        $pageTitle = '批次匯入銀行明細';
        $currentPage = 'bank_transactions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/bank_transactions/import.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 匯入預覽 ----
    case 'import_preview':
        if (!$canManageFinance && !$isBoss) {
            Session::flash('error', '無權限執行此操作');
            redirect('/bank_transactions.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/bank_transactions.php?action=import'); }
        verify_csrf();

        if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', '請選擇 Excel 檔案');
            redirect('/bank_transactions.php?action=import');
        }

        $file = $_FILES['excel_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('xlsx', 'xls', 'csv'))) {
            Session::flash('error', '僅支援 .xlsx / .xls / .csv 格式');
            redirect('/bank_transactions.php?action=import');
        }

        // 儲存暫存檔
        $tmpDir = __DIR__ . '/uploads/tmp';
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
        $tmpFile = $tmpDir . '/bank_import_' . session_id() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $tmpFile);

        // 解析 Excel
        ini_set('memory_limit', '256M');
        set_time_limit(120);
        require_once __DIR__ . '/../includes/ExcelReader.php';
        try {
            $rows = ExcelReader::read($tmpFile);
        } catch (Exception $readEx) {
            Session::flash('error', '解析失敗: ' . $readEx->getMessage());
            redirect('/bank_transactions.php?action=import');
        }

        if (empty($rows)) {
            Session::flash('error', '檔案無資料或格式不正確');
            redirect('/bank_transactions.php?action=import');
        }

        // 欄位自動對應
        $header = $rows[0];
        $colMap = ExcelReader::mapBankColumns($header);
        $dataRows = array_slice($rows, 1);

        // 預覽前50筆
        $previewRows = array_slice($dataRows, 0, 50);
        $totalRows = count($dataRows);
        $_SESSION['bank_import_file'] = $tmpFile;
        $_SESSION['bank_import_colmap'] = $colMap;
        $_SESSION['bank_import_total'] = $totalRows;

        $pageTitle = '匯入預覽';
        $currentPage = 'bank_transactions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/bank_transactions/import_preview.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 確認匯入 ----
    case 'import_execute':
        if (!$canManageFinance && !$isBoss) {
            Session::flash('error', '無權限執行此操作');
            redirect('/bank_transactions.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/bank_transactions.php?action=import'); }
        verify_csrf();
        ini_set('memory_limit', '256M');
        set_time_limit(300);

        $tmpFile = isset($_SESSION['bank_import_file']) ? $_SESSION['bank_import_file'] : '';
        $colMap = isset($_SESSION['bank_import_colmap']) ? $_SESSION['bank_import_colmap'] : array();
        if (!$tmpFile || !file_exists($tmpFile)) {
            Session::flash('error', '暫存檔已過期，請重新上傳');
            redirect('/bank_transactions.php?action=import');
        }

        require_once __DIR__ . '/../includes/ExcelReader.php';
        $rows = ExcelReader::read($tmpFile);
        $dataRows = array_slice($rows, 1);

        $imported = 0;
        $skipped = 0;
        $duplicated = 0;
        $db = Database::getInstance();

        // 預載已存在的 upload_number 做重複比對
        $existingNums = array();
        $exStmt = $db->query("SELECT upload_number FROM bank_transactions WHERE upload_number IS NOT NULL AND upload_number != ''");
        foreach ($exStmt->fetchAll(PDO::FETCH_COLUMN) as $n) { $existingNums[$n] = true; }

        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                INSERT INTO bank_transactions
                (sys_number, upload_number, bank_account, transaction_date, posting_date, transaction_time,
                 cash_transfer, summary, currency, debit_amount, credit_amount, balance,
                 note, transfer_account, bank_code, counter_account, remark, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($dataRows as $row) {
                $uploadNo = ExcelReader::getVal($row, $colMap, 'upload_number');
                $txDate = ExcelReader::getVal($row, $colMap, 'transaction_date');
                if (empty($txDate)) { $skipped++; continue; }

                // 重複比對：upload_number 已存在就跳過
                if ($uploadNo && isset($existingNums[$uploadNo])) { $duplicated++; continue; }

                // 格式化日期
                $txDate = ExcelReader::parseDate($txDate);
                $postDate = ExcelReader::parseDate(ExcelReader::getVal($row, $colMap, 'posting_date'));

                $debit = (int)str_replace(',', '', ExcelReader::getVal($row, $colMap, 'debit_amount') ?: '0');
                $credit = (int)str_replace(',', '', ExcelReader::getVal($row, $colMap, 'credit_amount') ?: '0');
                $balance = (int)str_replace(',', '', ExcelReader::getVal($row, $colMap, 'balance') ?: '0');

                $stmt->execute(array(
                    ExcelReader::getVal($row, $colMap, 'sys_number') ?: null,
                    ExcelReader::getVal($row, $colMap, 'upload_number') ?: null,
                    ExcelReader::getVal($row, $colMap, 'bank_account') ?: '',
                    $txDate,
                    $postDate ?: null,
                    ExcelReader::getVal($row, $colMap, 'transaction_time') ?: null,
                    ExcelReader::getVal($row, $colMap, 'cash_transfer') ?: null,
                    ExcelReader::getVal($row, $colMap, 'summary') ?: null,
                    ExcelReader::getVal($row, $colMap, 'currency') ?: 'TWD',
                    $debit,
                    $credit,
                    $balance,
                    ExcelReader::getVal($row, $colMap, 'note') ?: null,
                    ExcelReader::getVal($row, $colMap, 'transfer_account') ?: null,
                    ExcelReader::getVal($row, $colMap, 'bank_code') ?: null,
                    ExcelReader::getVal($row, $colMap, 'counter_account') ?: null,
                    ExcelReader::getVal($row, $colMap, 'remark') ?: null,
                    ExcelReader::getVal($row, $colMap, 'description') ?: null,
                ));
                $imported++;
            }

            $db->commit();
            @unlink($tmpFile);
            unset($_SESSION['bank_import_file'], $_SESSION['bank_import_colmap'], $_SESSION['bank_import_total']);
            AuditLog::log('bank_transactions', 'import', 0, "批次匯入 {$imported} 筆銀行明細");
            $msg = "成功匯入 {$imported} 筆銀行明細";
            if ($duplicated > 0) $msg .= "，重複跳過 {$duplicated} 筆";
            if ($skipped > 0) $msg .= "，無日期跳過 {$skipped} 筆";
            Session::flash('success', $msg);
        } catch (Exception $e) {
            $db->rollBack();
            Session::flash('error', '匯入失敗: ' . $e->getMessage());
        }

        redirect('/bank_transactions.php');
        break;

    // ---- 刪除 ----
    case 'delete':
        if (!Auth::hasPermission('finance.delete')) {
            Session::flash('error', '無刪除權限');
            redirect('/bank_transactions.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/bank_transactions.php'); }
        verify_csrf();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            AuditLog::log('bank_transactions', 'delete', $id, '刪除銀行交易');
            $model->deleteBankTransaction($id);
            Session::flash('success', '已刪除銀行交易');
        }
        redirect('/bank_transactions.php');
        break;

    default:
        redirect('/bank_transactions.php');
}
