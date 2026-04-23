<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/schedule/WorklogModel.php';

$model = new WorklogModel();
$action = $_GET['action'] ?? 'today';
$userId = Auth::id();

switch ($action) {
    // ---- 今日排工 (手機首頁) ----
    case 'today':
        $todaySchedules = $model->getTodaySchedules($userId);

        // 未完成回報提醒
        $reminders = array();
        $branchIds = Auth::getAccessibleBranchIds();
        if (Auth::hasPermission('schedule.manage')) {
            $reminders = $model->getIncompleteReports($branchIds);
        }

        $pageTitle = '今日施工';
        $currentPage = 'worklog';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/worklog_today.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 打卡到場 ----
    case 'checkin':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $scheduleId = (int)$_POST['schedule_id'];
            $worklogId = $model->checkIn($scheduleId, $userId);
            Session::flash('success', '已打卡到場');
            redirect('/worklog.php?action=report&id=' . $worklogId);
        }
        redirect('/worklog.php');
        break;

    // ---- 打卡離場 ----
    case 'checkout':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $worklogId = (int)$_POST['worklog_id'];
            $model->checkOut($worklogId);
            Session::flash('success', '已打卡離場');
            redirect('/worklog.php?action=report&id=' . $worklogId);
        }
        redirect('/worklog.php');
        break;

    // ---- 從案件管理新增回報 ----
    case 'new_from_case':
        $caseId = (int)($_GET['case_id'] ?? 0);
        if (!$caseId) { Session::flash('error', '缺少案件ID'); redirect('/worklog.php'); }

        // 找該案件最近的未完成排工（排除 completed/cancelled）
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM schedules WHERE case_id = ? AND status NOT IN ('completed','cancelled') ORDER BY schedule_date DESC LIMIT 1");
        $stmt->execute(array($caseId));
        $scheduleId = $stmt->fetchColumn();

        if ($scheduleId) {
            // 有排工 → 找「目前使用者」的未完成 worklog 或建新的
            $existStmt = $db->prepare('SELECT id FROM work_logs WHERE schedule_id = ? AND user_id = ? AND work_description IS NULL AND arrival_time IS NULL ORDER BY id DESC LIMIT 1');
            $existStmt->execute(array($scheduleId, $userId));
            $existId = $existStmt->fetchColumn();
            if ($existId) {
                $wlId = (int)$existId;
            } else {
                $wlId = $model->createBlank((int)$scheduleId, $userId);
            }
            redirect('/worklog.php?action=report&id=' . $wlId . '&from_case=' . $caseId);
        } else {
            // 沒排工 → 跳到手動回報表單（不直接建空記錄）
            $caseData = array();
            $cStmt = $db->prepare('SELECT id, title, customer_name FROM cases WHERE id = ?');
            $cStmt->execute(array($caseId));
            $caseData = $cStmt->fetch(PDO::FETCH_ASSOC);
            $pageTitle = '手動施工回報';
            $currentPage = 'worklog';
            $isManualReport = true;
            require __DIR__ . '/../templates/layouts/header.php';
            require __DIR__ . '/../templates/schedule/manual_worklog.php';
            require __DIR__ . '/../templates/layouts/footer.php';
        }
        break;

    // ---- 手動施工回報儲存 ----
    case 'save_manual_report':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/worklog.php');
            break;
        }
        $caseId = (int)($_POST['case_id'] ?? 0);
        if (!$caseId) { Session::flash('error', '缺少案件ID'); redirect('/worklog.php'); break; }

        $db = Database::getInstance();

        // 組合時間
        $workDate = $_POST['work_date'] ?? date('Y-m-d');
        $arrivalTime = !empty($_POST['arrival_time']) ? $workDate . ' ' . $_POST['arrival_time'] . ':00' : null;
        $departureTime = !empty($_POST['departure_time']) ? $workDate . ' ' . $_POST['departure_time'] . ':00' : null;

        // 處理照片上傳
        $photoPaths = array();
        if (!empty($_FILES['photos']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/worklogs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
                        $fn = 'cwl_' . $caseId . '_' . time() . '_' . $i . '.' . $ext;
                        if (move_uploaded_file($tmp, $uploadDir . $fn)) {
                            $photoPaths[] = '/uploads/worklogs/' . $fn;
                            backup_to_drive($uploadDir . $fn, 'worklogs', $caseId);
                        }
                    }
                }
            }
        }
        $photoJson = !empty($photoPaths) ? json_encode($photoPaths) : null;

        // 儲存到 case_work_logs
        $stmt = $db->prepare("
            INSERT INTO case_work_logs (case_id, work_date, work_content, equipment_used, arrival_time, departure_time, photo_paths, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(array(
            $caseId,
            $workDate,
            $_POST['work_content'] ?? '',
            $_POST['issues'] ?? '',
            $arrivalTime,
            $departureTime,
            $photoJson,
            $userId,
        ));

        // 處理完工狀態
        if (!empty($_POST['is_completed'])) {
            $db->prepare("UPDATE cases SET status = 'completed_pending' WHERE id = ?")->execute(array($caseId));
            // 送簽核給工程主管（level 1）
            try {
                require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
                $approvalModel = new ApprovalModel();
                $result = $approvalModel->submitCaseCompletion($caseId, $userId);

                // 通知（依 notification_settings 規則）
                require_once __DIR__ . '/../modules/notifications/NotificationDispatcher.php';
                $caseStmt = $db->prepare("SELECT title, branch_id FROM cases WHERE id = ?");
                $caseStmt->execute(array($caseId));
                $caseInfo = $caseStmt->fetch();
                $dispatchData = array(
                    'id' => $caseId,
                    'branch_id' => $caseInfo ? $caseInfo['branch_id'] : null,
                    'case_title' => $caseInfo ? $caseInfo['title'] : '',
                    'title' => $caseInfo ? $caseInfo['title'] : '',
                    'status' => '完工',
                );
                NotificationDispatcher::dispatch('worklog', 'status_changed', $dispatchData, $userId);
            } catch (Exception $e) {
                // 簽核模組尚未設定規則時不中斷
            }
        }

        // 處理需再次施工
        if (!empty($_POST['next_visit_needed'])) {
            $db->prepare("UPDATE cases SET progress = 'needs_reschedule' WHERE id = ?")->execute(array($caseId));
        }

        AuditLog::log('worklog', 'create', $caseId, '手動施工回報');
        Session::flash('success', '施工回報已儲存');
        redirect('/cases.php?action=edit&id=' . $caseId . '#sec-worklog');
        break;

    // ---- 更新手動施工回報 ----
    case 'update_manual_report':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/worklog.php');
            break;
        }
        $wlId = (int)($_POST['worklog_id'] ?? 0);
        $caseId = (int)($_POST['case_id'] ?? 0);
        if (!$wlId || !$caseId) { Session::flash('error', '缺少必要參數'); redirect('/worklog.php'); break; }

        $db = Database::getInstance();

        // 取得現有資料
        $stmt = $db->prepare('SELECT * FROM case_work_logs WHERE id = ?');
        $stmt->execute(array($wlId));
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) { Session::flash('error', '找不到紀錄'); redirect('/worklog.php'); break; }

        $workDate = $_POST['work_date'] ?? $existing['work_date'];
        $arrivalTime = !empty($_POST['arrival_time']) ? $workDate . ' ' . $_POST['arrival_time'] . ':00' : null;
        $departureTime = !empty($_POST['departure_time']) ? $workDate . ' ' . $_POST['departure_time'] . ':00' : null;

        // 合併照片：現有 + 新上傳
        $photoPaths = array();
        if (!empty($existing['photo_paths'])) {
            $decoded = json_decode($existing['photo_paths'], true);
            if (is_array($decoded)) $photoPaths = $decoded;
        }
        if (!empty($_FILES['photos']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/worklogs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
                        $fn = 'cwl_' . $caseId . '_' . time() . '_' . $i . '.' . $ext;
                        if (move_uploaded_file($tmp, $uploadDir . $fn)) {
                            $photoPaths[] = '/uploads/worklogs/' . $fn;
                            backup_to_drive($uploadDir . $fn, 'worklogs', $caseId);
                        }
                    }
                }
            }
        }
        $photoJson = !empty($photoPaths) ? json_encode($photoPaths) : null;

        $stmt = $db->prepare("UPDATE case_work_logs SET work_date=?, work_content=?, equipment_used=?, arrival_time=?, departure_time=?, photo_paths=? WHERE id=?");
        $stmt->execute(array(
            $workDate,
            $_POST['work_content'] ?? '',
            $_POST['issues'] ?? '',
            $arrivalTime,
            $departureTime,
            $photoJson,
            $wlId
        ));

        AuditLog::log('worklog', 'update', $wlId, '更新手動施工回報');
        Session::flash('success', '施工回報已更新');
        redirect('/cases.php?action=edit&id=' . $caseId . '#sec-worklog');
        break;

    // ---- 編輯手動施工回報 ----
    case 'edit_manual':
        $wlId = (int)($_GET['id'] ?? 0);
        $fromCase = (int)($_GET['from_case'] ?? 0);
        if (!$wlId) { Session::flash('error', '缺少回報ID'); redirect('/worklog.php'); break; }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM case_work_logs WHERE id = ?');
        $stmt->execute(array($wlId));
        $editWorklog = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editWorklog) { Session::flash('error', '找不到紀錄'); redirect('/worklog.php'); break; }

        $caseId = $editWorklog['case_id'];
        $cStmt = $db->prepare('SELECT id, title, customer_name FROM cases WHERE id = ?');
        $cStmt->execute(array($caseId));
        $caseData = $cStmt->fetch(PDO::FETCH_ASSOC);

        $pageTitle = '編輯施工回報';
        $currentPage = 'worklog';
        $isManualReport = true;
        $isEditMode = true;
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/manual_worklog.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 施工回報 ----
    case 'report':
        $id = (int)($_GET['id'] ?? 0);
        $worklog = $model->getWorklog($id);
        if (!$worklog) {
            Session::flash('error', '找不到此施工回報紀錄');
            redirect(!empty($_GET['from_case']) ? '/cases.php?action=edit&id=' . (int)$_GET['from_case'] . '#sec-worklog' : '/worklog.php');
        }
        if ($worklog['user_id'] != $userId) {
            if (!Auth::hasPermission('schedule.manage') && !Auth::hasPermission('all')) {
                Session::flash('error', '無權限');
                redirect('/worklog.php');
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            // 手動上工/下工時間
            $scheduleDate = $worklog['schedule_date'] ?? date('Y-m-d');
            $arrivalTime = !empty($_POST['arrival_time']) ? $scheduleDate . ' ' . $_POST['arrival_time'] . ':00' : null;
            $departureTime = !empty($_POST['departure_time']) ? $scheduleDate . ' ' . $_POST['departure_time'] . ':00' : null;
            $model->setManualTime($id, $arrivalTime, $departureTime);

            $model->saveReport($id, $_POST);

            // 收款同步到 case_payments（POST 後才執行）
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['payment_collected']) && !empty($_POST['payment_amount']) && $worklog) {
                try {
                    $wlCaseIdForPay = isset($worklog['case_id']) ? (int)$worklog['case_id'] : 0;
                    if ($wlCaseIdForPay > 0) {
                        $payDb = Database::getInstance();
                        // 以排工 ID 為識別（同排工多位工程師共用同一筆交易，避免重複）
                        $scheduleIdForPay = isset($worklog['schedule_id']) ? (int)$worklog['schedule_id'] : 0;
                        $payRef = $scheduleIdForPay > 0
                            ? '施工回報收款 排工#' . $scheduleIdForPay
                            : '施工回報收款 #' . $id;
                        $payMethod = !empty($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
                        $methodMap = array('cash' => '現金', 'transfer' => '匯款', 'check' => '支票');
                        $transType = isset($methodMap[$payMethod]) ? $methodMap[$payMethod] : $payMethod;
                        $payDate = isset($worklog['schedule_date']) ? $worklog['schedule_date'] : date('Y-m-d');
                        $payAmount = $_POST['payment_amount'];

                        $chkStmt = $payDb->prepare("SELECT id, amount FROM case_payments WHERE case_id = ? AND note = ? LIMIT 1");
                        $chkStmt->execute(array($wlCaseIdForPay, $payRef));
                        $existingPay = $chkStmt->fetch(PDO::FETCH_ASSOC);

                        $payChanged = false;
                        $newPayId = null;
                        if (!$existingPay) {
                            // 新增
                            $payDb->prepare("INSERT INTO case_payments (case_id, payment_date, payment_type, transaction_type, amount, note, created_by, created_at) VALUES (?, ?, '其他', ?, ?, ?, ?, NOW())")
                                  ->execute(array($wlCaseIdForPay, $payDate, $transType, $payAmount, $payRef, $userId));
                            $newPayId = $payDb->lastInsertId();
                            $payChanged = true;

                            // 自動拋轉收款單
                            try {
                                $caseStmt = $payDb->prepare('SELECT case_number, customer_id, customer_no, customer_name, sales_id, branch_id FROM cases WHERE id = ?');
                                $caseStmt->execute(array($wlCaseIdForPay));
                                $caseRow = $caseStmt->fetch(PDO::FETCH_ASSOC);
                                if ($caseRow) {
                                    require_once __DIR__ . '/../modules/finance/FinanceModel.php';
                                    $finModel = new FinanceModel();
                                    $receiptData = array(
                                        'register_date'    => $payDate,
                                        'deposit_date'     => $payDate,
                                        'customer_name'    => $caseRow['customer_name'],
                                        'case_id'          => $wlCaseIdForPay,
                                        'case_number'      => $caseRow['case_number'],
                                        'customer_no'      => $caseRow['customer_no'],
                                        'sales_id'         => $caseRow['sales_id'],
                                        'branch_id'        => $caseRow['branch_id'],
                                        'receipt_method'   => $transType,
                                        'invoice_category' => '其他',
                                        'status'           => '拋轉待確認',
                                        'bank_ref'         => null,
                                        'subtotal'         => 0,
                                        'tax'              => 0,
                                        'discount'         => 0,
                                        'total_amount'     => $payAmount,
                                        'note'             => '施工回報自動產生 - ' . $payRef,
                                        'created_by'       => $userId,
                                    );
                                    $receiptId = $finModel->createReceipt($receiptData);
                                    $rn = $payDb->prepare('SELECT receipt_number FROM receipts WHERE id = ?');
                                    $rn->execute(array($receiptId));
                                    $generatedReceiptNo = $rn->fetchColumn();
                                    if ($generatedReceiptNo && $newPayId) {
                                        $payDb->prepare('UPDATE case_payments SET receipt_number = ? WHERE id = ?')
                                              ->execute(array($generatedReceiptNo, $newPayId));
                                    }
                                }
                            } catch (Exception $e) {
                                error_log('worklog auto create receipt failed: ' . $e->getMessage());
                            }
                        } elseif ((int)$existingPay['amount'] !== (int)$payAmount) {
                            // 金額有變更，更新
                            $payDb->prepare("UPDATE case_payments SET amount = ?, transaction_type = ?, payment_date = ?, updated_at = NOW() WHERE id = ?")
                                  ->execute(array($payAmount, $transType, $payDate, $existingPay['id']));
                            $payChanged = true;

                            // 金額變更時也拋轉新收款單（舊的由會計刪除）
                            try {
                                $caseStmt2 = $payDb->prepare('SELECT case_number, customer_id, customer_no, customer_name, sales_id, branch_id FROM cases WHERE id = ?');
                                $caseStmt2->execute(array($wlCaseIdForPay));
                                $caseRow2 = $caseStmt2->fetch(PDO::FETCH_ASSOC);
                                if ($caseRow2) {
                                    require_once __DIR__ . '/../modules/finance/FinanceModel.php';
                                    $finModel2 = new FinanceModel();
                                    $receiptData2 = array(
                                        'register_date'    => $payDate,
                                        'deposit_date'     => $payDate,
                                        'customer_name'    => $caseRow2['customer_name'],
                                        'case_id'          => $wlCaseIdForPay,
                                        'case_number'      => $caseRow2['case_number'],
                                        'customer_no'      => $caseRow2['customer_no'],
                                        'sales_id'         => $caseRow2['sales_id'],
                                        'branch_id'        => $caseRow2['branch_id'],
                                        'receipt_method'   => $transType,
                                        'invoice_category' => '其他',
                                        'status'           => '拋轉待確認',
                                        'bank_ref'         => null,
                                        'subtotal'         => 0,
                                        'tax'              => 0,
                                        'discount'         => 0,
                                        'total_amount'     => $payAmount,
                                        'note'             => '施工回報金額更新 - ' . $payRef,
                                        'created_by'       => $userId,
                                    );
                                    $receiptId2 = $finModel2->createReceipt($receiptData2);
                                    $rn2 = $payDb->prepare('SELECT receipt_number FROM receipts WHERE id = ?');
                                    $rn2->execute(array($receiptId2));
                                    $genNo2 = $rn2->fetchColumn();
                                    if ($genNo2) {
                                        $payDb->prepare('UPDATE case_payments SET receipt_number = ? WHERE id = ?')
                                              ->execute(array($genNo2, $existingPay['id']));
                                    }
                                }
                            } catch (Exception $e) {
                                error_log('worklog update receipt failed: ' . $e->getMessage());
                            }
                        }

                        if ($payChanged) {
                            // 讀舊值
                            $oldStmt = $payDb->prepare("SELECT total_collected, balance_amount FROM cases WHERE id = ?");
                            $oldStmt->execute(array($wlCaseIdForPay));
                            $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC);
                            $oldCollected = $oldRow ? (int)$oldRow['total_collected'] : 0;
                            $oldBalance   = $oldRow ? (int)$oldRow['balance_amount'] : 0;

                            // 回寫案件帳務欄位
                            $syncStmt = $payDb->prepare("SELECT COALESCE(SUM(amount), 0) FROM case_payments WHERE case_id = ?");
                            $syncStmt->execute(array($wlCaseIdForPay));
                            $syncTotal = (int)$syncStmt->fetchColumn();
                            $syncStmt2 = $payDb->prepare("SELECT total_amount, deal_amount FROM cases WHERE id = ?");
                            $syncStmt2->execute(array($wlCaseIdForPay));
                            $syncCaseRow = $syncStmt2->fetch(PDO::FETCH_ASSOC);
                            $syncBase = (int)$syncCaseRow['total_amount'] > 0 ? (int)$syncCaseRow['total_amount'] : (int)$syncCaseRow['deal_amount'];
                            $syncBalance = $syncBase > 0 ? max(0, $syncBase - $syncTotal) : 0;
                            $payDb->prepare("UPDATE cases SET total_collected = ?, balance_amount = ? WHERE id = ?")
                                  ->execute(array($syncTotal, $syncBalance, $wlCaseIdForPay));

                            // 金額異動紀錄
                            try {
                                $chkTbl = $payDb->query("SHOW TABLES LIKE 'case_amount_changes'");
                                if ($chkTbl && $chkTbl->rowCount() > 0) {
                                    $user = Session::getUser();
                                    $uId = $user ? $user['id'] : 0;
                                    $uName = $user ? $user['real_name'] : 'system';
                                    $changeSource = 'worklog_payment';
                                    if ($oldCollected !== $syncTotal) {
                                        $payDb->prepare("INSERT INTO case_amount_changes (case_id, field_name, old_value, new_value, change_source, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)")
                                              ->execute(array($wlCaseIdForPay, 'total_collected', $oldCollected, $syncTotal, $changeSource, $uId, $uName));
                                    }
                                    if ($oldBalance !== $syncBalance) {
                                        $payDb->prepare("INSERT INTO case_amount_changes (case_id, field_name, old_value, new_value, change_source, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)")
                                              ->execute(array($wlCaseIdForPay, 'balance_amount', $oldBalance, $syncBalance, $changeSource, $uId, $uName));
                                    }
                                }
                            } catch (Exception $e) {}
                        }
                    }
                } catch (Exception $e) {
                    error_log('worklog payment sync error: ' . $e->getMessage());
                }
            }

            // 取消收款時刪除對應交易紀錄
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['payment_collected']) && $worklog) {
                try {
                    $wlCaseIdForPay = isset($worklog['case_id']) ? (int)$worklog['case_id'] : 0;
                    if ($wlCaseIdForPay > 0) {
                        $payDb = Database::getInstance();
                        // 以排工 ID 為識別
                        $scheduleIdForPay = isset($worklog['schedule_id']) ? (int)$worklog['schedule_id'] : 0;
                        $payRef = $scheduleIdForPay > 0
                            ? '施工回報收款 排工#' . $scheduleIdForPay
                            : '施工回報收款 #' . $id;

                        // 檢查同排工是否還有其他工程師勾選收款 → 若有則不刪（避免誤刪）
                        $shouldDelete = true;
                        if ($scheduleIdForPay > 0) {
                            $otherStmt = $payDb->prepare("SELECT COUNT(*) FROM work_logs WHERE schedule_id = ? AND id <> ? AND payment_collected = 1");
                            $otherStmt->execute(array($scheduleIdForPay, $id));
                            if ((int)$otherStmt->fetchColumn() > 0) {
                                $shouldDelete = false;
                            }
                        }

                        // 讀舊值
                        $oldStmt = $payDb->prepare("SELECT total_collected, balance_amount FROM cases WHERE id = ?");
                        $oldStmt->execute(array($wlCaseIdForPay));
                        $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC);
                        $oldCollected = $oldRow ? (int)$oldRow['total_collected'] : 0;
                        $oldBalance   = $oldRow ? (int)$oldRow['balance_amount'] : 0;

                        if (!$shouldDelete) {
                            // 同排工還有別人勾選收款，保留交易不刪除
                            $delStmt = null;
                        } else {
                            $delStmt = $payDb->prepare("DELETE FROM case_payments WHERE case_id = ? AND note = ?");
                            $delStmt->execute(array($wlCaseIdForPay, $payRef));
                        }
                        if ($shouldDelete && $delStmt && $delStmt->rowCount() > 0) {
                            // 回寫案件帳務欄位
                            $syncStmt = $payDb->prepare("SELECT COALESCE(SUM(amount), 0) FROM case_payments WHERE case_id = ?");
                            $syncStmt->execute(array($wlCaseIdForPay));
                            $syncTotal = (int)$syncStmt->fetchColumn();
                            $syncStmt2 = $payDb->prepare("SELECT total_amount, deal_amount FROM cases WHERE id = ?");
                            $syncStmt2->execute(array($wlCaseIdForPay));
                            $syncCaseRow = $syncStmt2->fetch(PDO::FETCH_ASSOC);
                            $syncBase = (int)$syncCaseRow['total_amount'] > 0 ? (int)$syncCaseRow['total_amount'] : (int)$syncCaseRow['deal_amount'];
                            $syncBalance = $syncBase > 0 ? max(0, $syncBase - $syncTotal) : 0;
                            $payDb->prepare("UPDATE cases SET total_collected = ?, balance_amount = ? WHERE id = ?")
                                  ->execute(array($syncTotal, $syncBalance, $wlCaseIdForPay));

                            // 金額異動紀錄
                            try {
                                $chkTbl = $payDb->query("SHOW TABLES LIKE 'case_amount_changes'");
                                if ($chkTbl && $chkTbl->rowCount() > 0) {
                                    $user = Session::getUser();
                                    $uId = $user ? $user['id'] : 0;
                                    $uName = $user ? $user['real_name'] : 'system';
                                    $changeSource = 'worklog_payment_cancel';
                                    if ($oldCollected !== $syncTotal) {
                                        $payDb->prepare("INSERT INTO case_amount_changes (case_id, field_name, old_value, new_value, change_source, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)")
                                              ->execute(array($wlCaseIdForPay, 'total_collected', $oldCollected, $syncTotal, $changeSource, $uId, $uName));
                                    }
                                    if ($oldBalance !== $syncBalance) {
                                        $payDb->prepare("INSERT INTO case_amount_changes (case_id, field_name, old_value, new_value, change_source, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)")
                                              ->execute(array($wlCaseIdForPay, 'balance_amount', $oldBalance, $syncBalance, $changeSource, $uId, $uName));
                                    }
                                }
                            } catch (Exception $e) {}
                        }
                    }
                } catch (Exception $e) {
                    error_log('worklog payment unsync error: ' . $e->getMessage());
                }
            }

            if (isset($_POST['materials'])) {
                $model->saveMaterials($id, $_POST['materials']);
            }

            // 照片上傳
            if (!empty($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                $uploadDir = __DIR__ . '/uploads/worklogs/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                foreach ($_FILES['photos']['name'] as $idx => $name) {
                    if ($_FILES['photos']['error'][$idx] !== UPLOAD_ERR_OK) continue;
                    if ($_FILES['photos']['size'][$idx] > 10 * 1024 * 1024) continue;

                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) continue;

                    $newName = $id . '_' . time() . '_' . $idx . '.' . $ext;
                    $dest = $uploadDir . $newName;
                    if (move_uploaded_file($_FILES['photos']['tmp_name'][$idx], $dest)) {
                        $model->savePhoto($id, '/uploads/worklogs/' . $newName, $_POST['photo_captions'][$idx] ?? null);
                        backup_to_drive($dest, 'worklogs', $id);
                    }
                }
            }

            // 自動更新案件進度
            $wlForStage = $model->getWorklog($id);
            $wlCaseId = 0;
            if ($wlForStage && !empty($wlForStage['schedule_id'])) {
                $stageDb = Database::getInstance();
                $caseStmt = $stageDb->prepare("SELECT case_id FROM schedules WHERE id = ?");
                $caseStmt->execute(array($wlForStage['schedule_id']));
                $wlCaseId = (int)$caseStmt->fetchColumn();
            }

            // ---- 餘料判斷（僅完工時觸發）----
            if (!empty($_POST['is_completed']) && isset($_POST['materials'])) {
                try {
                    $retDb = Database::getInstance();
                    // 找該案件關聯的已確認出庫單
                    $retCaseId = $wlCaseId;
                    if ($retCaseId > 0) {
                        $retSoStmt = $retDb->prepare("
                            SELECT so.id FROM stock_outs so
                            JOIN quotations q ON so.source_id = q.id AND so.source_type = 'quotation'
                            WHERE q.case_id = ? AND so.status = '已確認'
                        ");
                        $retSoStmt->execute(array($retCaseId));
                        $retSoIds = $retSoStmt->fetchAll(PDO::FETCH_COLUMN);

                        // 檢查材料是否有餘料
                        $hasReturn = false;
                        foreach ($_POST['materials'] as $mat) {
                            $shipped = (float)($mat['shipped_qty'] ?? 0);
                            $used = (float)($mat['used_qty'] ?? 0);
                            if ($shipped > 0 && $used < $shipped) {
                                $hasReturn = true;
                                break;
                            }
                        }

                        if ($hasReturn && !empty($retSoIds)) {
                            // 標記出庫單有餘料
                            $ph = implode(',', array_fill(0, count($retSoIds), '?'));
                            $retDb->prepare("UPDATE stock_outs SET has_return_material = 1 WHERE id IN ($ph)")
                                  ->execute($retSoIds);

                            // 通知工程主管
                            try {
                                require_once __DIR__ . '/../modules/notifications/NotificationModel.php';
                                $notifModel = new NotificationModel();
                                $soNum = $retDb->prepare("SELECT so_number FROM stock_outs WHERE id = ?");
                                $soNum->execute(array($retSoIds[0]));
                                $soNumStr = $soNum->fetchColumn();
                                $notifModel->sendToRole(
                                    'eng_manager',
                                    !empty($user['branch_id']) ? $user['branch_id'] : null,
                                    'return_material',
                                    '餘料待入庫：' . $soNumStr,
                                    '施工完工後有餘料需入庫，請確認處理。',
                                    '/stock_outs.php?action=view&id=' . $retSoIds[0],
                                    'stock_out',
                                    $retSoIds[0],
                                    Auth::id()
                                );
                            } catch (Exception $ne) {
                                error_log('return material notification error: ' . $ne->getMessage());
                            }
                        }
                    }
                } catch (Exception $re) {
                    error_log('return material check error: ' . $re->getMessage());
                }
            }

            $msg = '回報已儲存';
            if (!empty($_POST['is_completed'])) {
                $msg = '已標記完工，案件進度已更新為「已完工待簽核」';
                // markCaseCompletedPending already handles this in WorklogModel
            } elseif (!empty($_POST['next_visit_needed']) && $wlCaseId > 0) {
                // 需要再次施工 → 更新為「已進場/需再安排」(stage 6)
                $stageDb = Database::getInstance();
                $stageDb->prepare("UPDATE cases SET stage = 6, status = 'needs_reschedule' WHERE id = ?")
                        ->execute(array($wlCaseId));
                $msg = '回報已儲存，案件進度已更新為「已進場/需再安排」';
            }
            Session::flash('success', $msg);

            // 從排工詳情來的回到排工詳情
            $redirectBack = $_POST['redirect_back'] ?? '';
            if ($redirectBack) {
                redirect($redirectBack);
            }
            redirect('/worklog.php?action=report&id=' . $id);
        }

        // 查詢該案件的已確認出庫單品項（供器材預填）
        $stockOutMaterials = array();
        if ($worklog && !empty($worklog['schedule_id'])) {
            try {
                $soDb = Database::getInstance();
                $soCaseStmt = $soDb->prepare("SELECT case_id FROM schedules WHERE id = ?");
                $soCaseStmt->execute(array($worklog['schedule_id']));
                $soCaseId = (int)$soCaseStmt->fetchColumn();
                if ($soCaseId > 0) {
                    $soItemsStmt = $soDb->prepare("
                        SELECT soi.product_id, soi.product_name, soi.model, soi.unit, soi.quantity, soi.unit_price
                        FROM stock_out_items soi
                        JOIN stock_outs so ON soi.stock_out_id = so.id
                        JOIN quotations q ON so.source_id = q.id AND so.source_type = 'quotation'
                        WHERE q.case_id = ? AND so.status = '已確認'
                        ORDER BY so.id, soi.sort_order
                    ");
                    $soItemsStmt->execute(array($soCaseId));
                    $stockOutMaterials = $soItemsStmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                // stock_outs 表可能尚未建立
                $stockOutMaterials = array();
            }
        }

        // 若本工程師自己尚未填過 materials，但同排工其他工程師已填過 → 帶入當預設（繼承編輯）
        $siblingMaterials = array();
        $siblingFromName = '';
        if ($worklog && empty($worklog['materials']) && !empty($worklog['schedule_id'])) {
            try {
                $sibDb = Database::getInstance();
                $sibStmt = $sibDb->prepare("
                    SELECT wl.id AS wl_id, u.real_name
                    FROM work_logs wl
                    LEFT JOIN users u ON wl.user_id = u.id
                    WHERE wl.schedule_id = ? AND wl.id <> ?
                      AND EXISTS (SELECT 1 FROM material_usage mu WHERE mu.work_log_id = wl.id)
                    ORDER BY wl.updated_at DESC, wl.id DESC
                    LIMIT 1
                ");
                $sibStmt->execute(array((int)$worklog['schedule_id'], (int)$worklog['id']));
                $sibRow = $sibStmt->fetch(PDO::FETCH_ASSOC);
                if ($sibRow) {
                    $mStmt = $sibDb->prepare("SELECT mu.*, p.name AS product_name FROM material_usage mu LEFT JOIN products p ON mu.product_id = p.id WHERE mu.work_log_id = ? ORDER BY mu.id");
                    $mStmt->execute(array((int)$sibRow['wl_id']));
                    $siblingMaterials = $mStmt->fetchAll(PDO::FETCH_ASSOC);
                    $siblingFromName = $sibRow['real_name'] ?: '';
                }
            } catch (Exception $e) {
                $siblingMaterials = array();
            }
        }

        // 若無出庫單材料，從案件預估材料預填
        $estimateMaterials = array();
        if (empty($stockOutMaterials) && !empty($soCaseId) && $soCaseId > 0) {
            try {
                $estStmt = Database::getInstance()->prepare('
                    SELECT cme.product_id, cme.material_name AS product_name,
                           cme.model_number AS model, cme.unit, cme.estimated_qty AS quantity, 0 AS unit_price
                    FROM case_material_estimates cme
                    WHERE cme.case_id = ?
                    ORDER BY cme.sort_order, cme.id
                ');
                $estStmt->execute(array($soCaseId));
                $estimateMaterials = $estStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $estimateMaterials = array();
            }
        }

        $pageTitle = '施工回報';
        $currentPage = 'worklog';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/worklog_report.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除照片 (AJAX) ----
    case 'delete_photo':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(array('error' => '方法不允許'), 405);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $photoId = (int)($input['photo_id'] ?? 0);
        if (!$photoId) {
            json_response(array('error' => '缺少 photo_id'));
        }
        $model->deletePhoto($photoId);
        json_response(array('success' => true));
        break;

    // ---- 刪除整筆施工回報 ----
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/worklog.php');
            break;
        }
        $id = (int)($_POST['id'] ?? 0);
        $fromCase = (int)($_POST['from_case'] ?? 0);
        $fromSchedule = (int)($_POST['from_schedule'] ?? 0);
        $worklog = $model->getWorklog($id);
        if (!$worklog) {
            Session::flash('error', '找不到此施工回報紀錄');
            redirect('/worklog.php');
            break;
        }
        // 權限：本人或有 schedule.manage / all 權限
        $canDelete = ($worklog['user_id'] == $userId)
            || Auth::hasPermission('schedule.manage')
            || Auth::hasPermission('all');
        if (!$canDelete) {
            Session::flash('error', '無權限刪除此施工回報');
            redirect('/worklog.php?action=report&id=' . $id);
            break;
        }
        $caseId = $model->deleteWorklog($id);
        AuditLog::log('worklog', 'delete', $id, '刪除施工回報');
        Session::flash('success', '施工回報已刪除');
        if ($fromCase) {
            redirect('/cases.php?action=edit&id=' . $fromCase . '#sec-worklog');
        } elseif ($fromSchedule) {
            redirect('/schedule.php?action=view&id=' . $fromSchedule);
        } else {
            redirect('/worklog.php');
        }
        break;

    // ---- 歷史記錄（時間軸式）----
    case 'history':
        $history = $model->getHistory($userId);
        $pageTitle = '施工記錄';
        $currentPage = 'worklog';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/worklog_history.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    default:
        redirect('/worklog.php');
}
