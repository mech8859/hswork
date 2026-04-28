<?php
/**
 * 簽核管理
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';

$model = new ApprovalModel();
$action = $_GET['action'] ?? 'pending';

$canManageRules = Auth::user()['role'] === 'boss';
// 有簽核權限，或本人有待簽項目，都可進入待簽核頁面
$myPendingCount = $model->getPendingCount(Auth::id());
$canViewApprovals = $myPendingCount > 0 || Auth::hasPermission('approvals.view') || Auth::hasPermission('approvals.manage') || Auth::hasPermission('all');

switch ($action) {
    // ---- 待簽核清單 ----
    case 'pending':
        if (!$canViewApprovals) { Session::flash('error', '無權限查看待簽核'); redirect('/index.php'); }
        $pendingList = $model->getPendingList(Auth::id());
        $pageTitle = '待簽核';
        $currentPage = 'approvals';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/approvals/pending.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 簽核歷史紀錄（預設只顯示自己簽的，可切換全部） ----
    case 'history':
        if (!$canViewApprovals) { Session::flash('error', '無權限'); redirect('/index.php'); }
        // scope: mine=只看自己(預設) / all=全部(需 manage 權限)
        $scope = isset($_GET['scope']) ? $_GET['scope'] : 'mine';
        $canViewAll = Auth::hasPermission('approvals.manage') || Auth::hasPermission('all');
        if ($scope === 'all' && !$canViewAll) $scope = 'mine';
        $filters = array(
            'module'      => isset($_GET['module']) ? $_GET['module'] : '',
            'status'      => isset($_GET['status']) ? $_GET['status'] : '',
            'date_from'   => isset($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'     => isset($_GET['date_to']) ? $_GET['date_to'] : '',
            'keyword'     => isset($_GET['keyword']) ? $_GET['keyword'] : '',
            'approver_id' => $scope === 'mine' ? Auth::id() : 0,
        );
        $history = $model->getApprovalHistory($filters);
        $pageTitle = '簽核紀錄';
        $currentPage = 'approvals';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/approvals/history.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 簽核設定 ----
    case 'settings':
        if (!$canManageRules) { Session::flash('error', '無權限'); redirect('/approvals.php'); }
        $rules = $model->getRules();
        $approvers = $model->getApprovers();
        $branches = Database::getInstance()->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $pageTitle = '簽核設定';
        $currentPage = 'approvals';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/approvals/settings.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 儲存規則 ----
    case 'save_rule':
        if (!$canManageRules) { Session::flash('error', '無權限'); redirect('/approvals.php?action=settings'); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/approvals.php?action=settings'); }
            // 處理不需簽核勾選
            if (!empty($_POST['approver_role']) && $_POST['approver_role'] === 'auto_approve') {
                $_POST['approver_id'] = '';
                $_POST['level_order'] = 1;
            }
            $model->saveRule($_POST);
            // 處理額外層級（多層簽核）
            if (!empty($_POST['extra_approver_role']) && is_array($_POST['extra_approver_role'])) {
                $extraRoles = $_POST['extra_approver_role'];
                $extraIds = isset($_POST['extra_approver_id']) ? $_POST['extra_approver_id'] : array();
                $extraOrders = isset($_POST['extra_level_order']) ? $_POST['extra_level_order'] : array();
                for ($i = 0; $i < count($extraRoles); $i++) {
                    if (!empty($extraRoles[$i]) || !empty($extraIds[$i])) {
                        $extraData = $_POST;
                        unset($extraData['id']);
                        unset($extraData['extra_approver_ids']); // 額外層級不繼承「其他可簽核人」
                        $extraData['approver_role'] = $extraRoles[$i];
                        $extraData['approver_id'] = isset($extraIds[$i]) ? $extraIds[$i] : '';
                        $extraData['level_order'] = isset($extraOrders[$i]) ? $extraOrders[$i] : ($i + 2);
                        $model->saveRule($extraData);
                    }
                }
            }
            Session::flash('success', '簽核規則已儲存');
        }
        redirect('/approvals.php?action=settings');
        break;

    // ---- 刪除規則 ----
    case 'delete_rule':
        if (!$canManageRules) { Session::flash('error', '無權限'); redirect('/approvals.php?action=settings'); }
        if (verify_csrf()) {
            $model->deleteRule((int)$_GET['id']);
            Session::flash('success', '規則已刪除');
        }
        redirect('/approvals.php?action=settings');
        break;

    // ---- 核准 ----
    case 'approve':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $flowId = (int)$_POST['flow_id'];
            $comment = $_POST['comment'] ?? '';
            if ($model->approve($flowId, Auth::id(), $comment)) {
                $module = $_POST['module'] ?? '';
                $targetId = (int)($_POST['target_id'] ?? 0);

                if ($module === 'case_completion' && $targetId) {
                    require_once __DIR__ . '/../modules/notifications/NotificationModel.php';
                    $notifModel = new NotificationModel();
                    $db = Database::getInstance();
                    $caseStmt = $db->prepare("SELECT title, branch_id, status, balance_amount FROM cases WHERE id = ?");
                    $caseStmt->execute(array($targetId));
                    $caseInfo = $caseStmt->fetch();
                    $caseTitleAppr = $caseInfo ? $caseInfo['title'] : '';

                    // 取得當前 flow 的 level
                    $lvStmt = $db->prepare("SELECT level_order FROM approval_flows WHERE id = ?");
                    $lvStmt->execute(array($flowId));
                    $flowLevel = (int)$lvStmt->fetchColumn();

                    // Level 3 結案前先檢查 balance_amount = 0（強制）
                    if ($flowLevel === 3 && $caseInfo && (int)$caseInfo['balance_amount'] !== 0) {
                        // 將剛剛 approve 的 flow 退回 pending（撤回）
                        $db->prepare("UPDATE approval_flows SET status='pending', decided_at=NULL WHERE id=?")->execute(array($flowId));
                        Session::flash('error', '尾款還有 $' . number_format($caseInfo['balance_amount']) . '，無法結案。請先補登收款或折讓金額。');
                        $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '/approvals.php';
                        redirect($redirect);
                        break;
                    }

                    // 寫入 payload (Level 2: has_payment + warranty_service, Level 3: payment_received)
                    if ($flowLevel === 2) {
                        $hasPayment      = !empty($_POST['has_payment']);
                        $warrantyService = !empty($_POST['warranty_service']);
                        $model->setFlowPayload($flowId, array(
                            'has_payment'      => $hasPayment,
                            'warranty_service' => $warrantyService,
                        ));

                        // 保固/服務直結：跳過第 3 關直接結案，仍需尾款 = 0（balance > 0 時 rollback L2 approve）
                        if ($hasPayment && $warrantyService) {
                            $_balRow = $db->prepare("SELECT GREATEST(COALESCE(CASE WHEN total_amount > 0 THEN total_amount ELSE deal_amount END, 0) - COALESCE(total_collected, 0), 0) FROM cases WHERE id = ?");
                            $_balRow->execute(array($targetId));
                            $_bal = (int)$_balRow->fetchColumn();
                            if ($_bal > 0) {
                                $db->prepare("UPDATE approval_flows SET status='pending', decided_at=NULL WHERE id=?")->execute(array($flowId));
                                Session::flash('error', '保固/服務直結需尾款 = 0，目前尾款 $' . number_format($_bal) . '。請先補收款或折讓，或取消「保固／做服務」改走第 3 關。');
                                $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '/approvals.php';
                                redirect($redirect);
                                break;
                            }
                        }
                    } elseif ($flowLevel === 3) {
                        $paymentReceived = !empty($_POST['payment_received']);
                        if (!$paymentReceived) {
                            // 必勾才能核准
                            $db->prepare("UPDATE approval_flows SET status='pending', decided_at=NULL WHERE id=?")->execute(array($flowId));
                            Session::flash('error', '會計簽核必須勾選「款項已入帳」');
                            $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '/approvals.php';
                            redirect($redirect);
                            break;
                        }
                        $model->setFlowPayload($flowId, array('payment_received' => true));
                    }

                    // 多人擇一簽核：取消同 level 其他 pending
                    if ($flowLevel > 0) {
                        $model->cancelSiblingPendingFlows('case_completion', $targetId, $flowLevel, $flowId);
                    }

                    // 推進到下一關
                    $advance = $model->advanceCaseCompletion($targetId);
                    $advStatus = isset($advance['status']) ? $advance['status'] : 'no_rule';

                    // L2 (行政人員) 核准後不論有/無收款/是否走保固直結都標示已完工，完工日期 = 該案最新完工工程回報的施工日期
                    if ($flowLevel === 2 && in_array($advStatus, array('pending_next', 'unpaid', 'closed'), true)) {
                        $cdStmt = $db->prepare("SELECT MAX(s.schedule_date) FROM work_logs wl JOIN schedules s ON wl.schedule_id = s.id WHERE s.case_id = ? AND wl.is_completed = 1");
                        $cdStmt->execute(array($targetId));
                        $compDate = $cdStmt->fetchColumn() ?: date('Y-m-d');
                        $db->prepare("UPDATE cases SET is_completed = 1, completion_date = ? WHERE id = ? AND (is_completed = 0 OR completion_date IS NULL)")
                           ->execute(array($compDate, $targetId));
                        // 嘗試自動結案（若四項條件都到位）
                        tryAutoCloseCase($targetId);
                    }

                    if ($advStatus === 'closed_blocked') {
                        // 不應該到這裡（前面已擋），保險起見再擋一次
                        Session::flash('error', $advance['error']);
                    } elseif ($advStatus === 'closed') {
                        // 全部簽完 + 尾款=0 → 案件進度改為已完工結案，並自動上鎖
                        $db->prepare("UPDATE cases SET status = 'closed', is_locked = 1, locked_by = COALESCE(locked_by, ?), locked_at = COALESCE(locked_at, NOW()), unlocked_at = NULL, unlocked_by = NULL WHERE id = ?")
                           ->execute(array(Auth::id(), $targetId));
                        Session::flash('success', '已核准，案件已完工結案並自動上鎖');
                    } elseif ($advStatus === 'unpaid') {
                        // Level 2 勾無收款 → 案件進度改為完工未收款
                        $db->prepare("UPDATE cases SET status = 'unpaid' WHERE id = ?")->execute(array($targetId));
                        Session::flash('success', '已核准 (勾選無收款)，案件進度：完工未收款');
                    } elseif ($advStatus === 'pending_next') {
                        // 送下一關 → 通知
                        $nextLevel = (int)$advance['next_level'];
                        $levelLabels = array(2 => '行政人員', 3 => '會計人員');
                        $nextLabel = isset($levelLabels[$nextLevel]) ? $levelLabels[$nextLevel] : '下一關';

                        // 通知下一關所有 pending 簽核人
                        $nextStmt = $db->prepare("SELECT approver_id FROM approval_flows WHERE module='case_completion' AND target_id=? AND level_order=? AND status='pending'");
                        $nextStmt->execute(array($targetId, $nextLevel));
                        foreach ($nextStmt->fetchAll(PDO::FETCH_COLUMN) as $approverId) {
                            $notifModel->send(
                                $approverId,
                                'approval_pending',
                                '待簽核（完工 第' . $nextLevel . '關）：' . $caseTitleAppr,
                                '請進入案件確認簽核',
                                '/cases.php?action=edit&id=' . $targetId . '#sec-billing',
                                'case', $targetId, Auth::id()
                            );
                        }
                        Session::flash('success', '已核准，已送 ' . $nextLabel . ' 簽核');
                    } else {
                        Session::flash('success', '已核准');
                    }
                } elseif (($module === 'leaves' || $module === 'overtime') && $targetId) {
                    // 分關簽核：取消同 level 其他 pending（多人擇一）
                    $db = Database::getInstance();
                    $lvStmt = $db->prepare("SELECT level_order, submitted_by FROM approval_flows WHERE id = ?");
                    $lvStmt->execute(array($flowId));
                    $flowInfo = $lvStmt->fetch(PDO::FETCH_ASSOC);
                    $flowLevel = (int)$flowInfo['level_order'];
                    $flowSubmitter = (int)$flowInfo['submitted_by'];

                    if ($flowLevel > 0) {
                        $model->cancelSiblingPendingFlows($module, $targetId, $flowLevel, $flowId);
                    }

                    // 推進到下一關
                    $advance = $model->advanceSequentialApproval($module, $targetId, $flowSubmitter);
                    if ($advance['status'] === 'completed') {
                        // 全部簽完 → 核准
                        if ($module === 'leaves') {
                            $db->prepare("UPDATE leaves SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
                               ->execute(array(Auth::id(), $targetId));
                        } elseif ($module === 'overtime') {
                            $db->prepare("UPDATE overtimes SET status = 'approved', approved_by = ?, approved_at = NOW(), reject_reason = NULL WHERE id = ?")
                               ->execute(array(Auth::id(), $targetId));
                        }
                        Session::flash('success', '已核准（最終關），已核准');
                    } elseif ($advance['status'] === 'pending_next') {
                        // 送下一關：通知
                        require_once __DIR__ . '/../modules/notifications/NotificationModel.php';
                        $notifModel = new NotificationModel();
                        $nextLevel = (int)$advance['next_level'];
                        $nextStmt = $db->prepare("SELECT DISTINCT approver_id FROM approval_flows WHERE module=? AND target_id=? AND level_order=? AND status='pending'");
                        $nextStmt->execute(array($module, $targetId, $nextLevel));
                        foreach ($nextStmt->fetchAll(PDO::FETCH_COLUMN) as $approverId) {
                            $notifModel->send(
                                $approverId,
                                'approval_pending',
                                '待簽核（請假 第' . $nextLevel . '關）#' . $targetId,
                                '請進入待簽核確認',
                                '/approvals.php?action=pending',
                                'leaves', $targetId, Auth::id()
                            );
                        }
                        Session::flash('success', '已核准，已送第' . $nextLevel . '關簽核');
                    } else {
                        Session::flash('success', '已核准');
                    }
                } elseif ($module === 'case_payments' && $targetId) {
                    if ($model->isFullyApproved('case_payments', $targetId)) {
                        $db = Database::getInstance();
                        $db->prepare("UPDATE case_payments SET approval_status = 'approved' WHERE id = ?")->execute(array($targetId));
                        // 通知建立人
                        $cpStmt = $db->prepare("SELECT cp.created_by, cp.amount, cp.case_id, c.customer_name, c.case_number FROM case_payments cp LEFT JOIN cases c ON cp.case_id = c.id WHERE cp.id = ?");
                        $cpStmt->execute(array($targetId));
                        $cpInfo = $cpStmt->fetch(PDO::FETCH_ASSOC);
                        if ($cpInfo && $cpInfo['created_by']) {
                            require_once __DIR__ . '/../modules/notifications/NotificationModel.php';
                            $notifModel = new NotificationModel();
                            $caseLabel = ($cpInfo['case_number'] ? $cpInfo['case_number'] . ' ' : '') . $cpInfo['customer_name'];
                            $notifModel->send(
                                $cpInfo['created_by'],
                                'approval_approved',
                                '收款已簽核：' . $caseLabel,
                                '金額 $' . number_format($cpInfo['amount']) . ' 已通過簽核',
                                '/cases.php?action=edit&id=' . $cpInfo['case_id'] . '#sec-payments',
                                'case_payments', $targetId, Auth::id()
                            );
                        }
                    }
                    Session::flash('success', '已核准');
                } elseif ($module === 'stocktakes' && $targetId) {
                    if ($model->isFullyApproved('stocktakes', $targetId)) {
                        require_once __DIR__ . '/../modules/inventory/InventoryModel.php';
                        $invModel = new InventoryModel();
                        $invModel->completeStocktake($targetId, Auth::id());
                        Session::flash('success', '已核准，庫存差異已調整');
                    } else {
                        Session::flash('success', '已核准，等待其他簽核人');
                    }
                } elseif ($module && $targetId && $model->isFullyApproved($module, $targetId)) {
                    // 其他模組：自動更新單據狀態
                    if ($module === 'quotations') {
                        require_once __DIR__ . '/../modules/quotations/QuotationModel.php';
                        $qm = new QuotationModel();
                        $db = Database::getInstance();
                        // 判斷是否為變更簽核
                        $revFlow = $db->prepare("SELECT payload FROM approval_flows WHERE module = 'quotations' AND target_id = ? AND payload IS NOT NULL AND payload != '' LIMIT 1");
                        $revFlow->execute(array($targetId));
                        $revPayload = $revFlow->fetchColumn();
                        $revData = $revPayload ? json_decode($revPayload, true) : null;
                        if ($revData && isset($revData['type']) && $revData['type'] === 'revision') {
                            // 變更簽核通過 → 退回草稿
                            $qm->updateStatus($targetId, 'draft');
                            AuditLog::log('quotations', 'revision_approved', $targetId, '變更簽核通過，退回草稿可編輯');
                        } else {
                            $qm->updateStatus($targetId, 'approved');
                        }
                    } elseif ($module === 'purchases') {
                        require_once __DIR__ . '/../modules/procurement/ProcurementModel.php';
                        $pm = new ProcurementModel();
                        $pm->updateRequisition($targetId, array(
                            'status' => '已核准',
                            'approval_user' => Auth::user()['real_name'],
                            'approval_date' => date('Y-m-d'),
                        ));
                    }
                    Session::flash('success', '已核准');
                } else {
                    Session::flash('success', '已核准');
                }
                AuditLog::log('approvals', 'approve', $flowId, $module . ' #' . $targetId);
            } else {
                Session::flash('error', '核准失敗');
            }
        }
        // 導回來源頁
        $redirect = $_POST['redirect'] ?? '/approvals.php';
        redirect($redirect);
        break;

    // ---- 退回 ----
    case 'reject':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $flowId = (int)$_POST['flow_id'];
            $comment = $_POST['comment'] ?? '';
            $module = $_POST['module'] ?? '';
            $targetId = (int)($_POST['target_id'] ?? 0);
            // 若 module/targetId 沒傳，從 flow 補取
            if ((!$module || !$targetId) && $flowId) {
                $_fstmt = Database::getInstance()->prepare("SELECT module, target_id FROM approval_flows WHERE id = ?");
                $_fstmt->execute(array($flowId));
                $_frow = $_fstmt->fetch(PDO::FETCH_ASSOC);
                if ($_frow) { $module = $_frow['module']; $targetId = (int)$_frow['target_id']; }
            }
            if ($model->reject($flowId, Auth::id(), $comment)) {
                if ($module === 'case_completion' && $targetId) {
                    // 取被駁回 flow 的 level_order：第3關駁回 → 退一關回第2關；第1/2關駁回 → 整個退回施工中
                    $_lvStmt = Database::getInstance()->prepare("SELECT level_order FROM approval_flows WHERE id = ?");
                    $_lvStmt->execute(array($flowId));
                    $rejectedLevel = (int)$_lvStmt->fetchColumn();

                    if ($rejectedLevel === 3) {
                        // 第3關（會計）駁回 → 退回第2關（行政人員）重新核准
                        $l2FlowId = $model->retreatCaseCompletionFromLevel3($targetId);
                        // 通知第2關簽核人
                        if ($l2FlowId) {
                            $_db = Database::getInstance();
                            $_apprStmt = $_db->prepare("SELECT approver_id FROM approval_flows WHERE id = ?");
                            $_apprStmt->execute(array($l2FlowId));
                            $l2ApproverId = (int)$_apprStmt->fetchColumn();
                            if ($l2ApproverId) {
                                $_caseStmt = $_db->prepare("SELECT case_number, customer_name FROM cases WHERE id = ?");
                                $_caseStmt->execute(array($targetId));
                                $_caseInfo = $_caseStmt->fetch(PDO::FETCH_ASSOC);
                                $_caseTitle = ($_caseInfo['case_number'] ? $_caseInfo['case_number'] . ' ' : '') . ($_caseInfo['customer_name'] ?? '');
                                require_once __DIR__ . '/../modules/notifications/NotificationModel.php';
                                $_notifModel = new NotificationModel();
                                $_notifModel->send(
                                    $l2ApproverId,
                                    'approval_pending',
                                    '完工簽核退回 第2關：' . $_caseTitle,
                                    '會計駁回，請重新確認' . ($comment ? '（原因：' . $comment . '）' : ''),
                                    '/cases.php?action=edit&id=' . $targetId . '#sec-billing',
                                    'case', $targetId, Auth::id()
                                );
                            }
                        }
                        AuditLog::log('approvals', 'reject', $flowId, '完工簽核退回第2關 案件#' . $targetId . ' - ' . $comment);
                        Session::flash('success', '已退回第2關，請行政人員重新確認');
                    } else {
                        // 第1/2關駁回 → 案件回到施工中（既有行為）
                        $model->rejectCaseCompletion($targetId);
                        AuditLog::log('approvals', 'reject', $flowId, '完工簽核退回 案件#' . $targetId . ' - ' . $comment);
                        Session::flash('success', '已退回，案件進度已改回施工中');
                    }
                } elseif ($module === 'case_payments' && $targetId) {
                    $db = Database::getInstance();
                    $db->prepare("UPDATE case_payments SET approval_status = 'rejected' WHERE id = ?")->execute(array($targetId));
                    // 通知建立人
                    $cpStmt = $db->prepare("SELECT cp.created_by, cp.amount, cp.case_id, c.customer_name, c.case_number FROM case_payments cp LEFT JOIN cases c ON cp.case_id = c.id WHERE cp.id = ?");
                    $cpStmt->execute(array($targetId));
                    $cpInfo = $cpStmt->fetch(PDO::FETCH_ASSOC);
                    if ($cpInfo && $cpInfo['created_by']) {
                        require_once __DIR__ . '/../modules/notifications/NotificationModel.php';
                        $notifModel = new NotificationModel();
                        $caseLabel = ($cpInfo['case_number'] ? $cpInfo['case_number'] . ' ' : '') . $cpInfo['customer_name'];
                        $notifModel->send(
                            $cpInfo['created_by'],
                            'approval_rejected',
                            '收款簽核退回：' . $caseLabel,
                            '金額 $' . number_format($cpInfo['amount']) . ' 簽核被退回' . ($comment ? '，原因：' . $comment : ''),
                            '/cases.php?action=edit&id=' . $cpInfo['case_id'] . '#sec-payments',
                            'case_payments', $targetId, Auth::id()
                        );
                    }
                    AuditLog::log('approvals', 'reject', $flowId, '收款簽核退回 #' . $targetId . ' - ' . $comment);
                    Session::flash('success', '已退回');
                } elseif ($module === 'stocktakes' && $targetId) {
                    require_once __DIR__ . '/../modules/inventory/InventoryModel.php';
                    $invModel = new InventoryModel();
                    $invModel->rejectStocktake($targetId);
                    AuditLog::log('approvals', 'reject', $flowId, '盤點簽核退回 #' . $targetId . ' - ' . $comment);
                    Session::flash('success', '已退回，倉管可修改後重新提交');
                } elseif (($module === 'leaves' || $module === 'overtime') && $targetId) {
                    // 分關簽核駁回 → 取消同 level 其他 pending
                    $db = Database::getInstance();
                    $lvStmt = $db->prepare("SELECT level_order FROM approval_flows WHERE id = ?");
                    $lvStmt->execute(array($flowId));
                    $flowLevel = (int)$lvStmt->fetchColumn();
                    if ($flowLevel > 0) {
                        $model->cancelSiblingPendingFlows($module, $targetId, $flowLevel, $flowId);
                    }
                    if ($module === 'leaves') {
                        $db->prepare("UPDATE leaves SET status = 'rejected', approved_by = ?, approved_at = NOW(), reject_reason = ? WHERE id = ?")
                           ->execute(array(Auth::id(), $comment, $targetId));
                    } elseif ($module === 'overtime') {
                        $db->prepare("UPDATE overtimes SET status = 'rejected', approved_by = ?, approved_at = NOW(), reject_reason = ? WHERE id = ?")
                           ->execute(array(Auth::id(), $comment, $targetId));
                    }
                    AuditLog::log('approvals', 'reject', $flowId, $module . ' 簽核退回 #' . $targetId . ' - ' . $comment);
                    Session::flash('success', '已退回');
                } else {
                    // 其他模組退回
                    $db = Database::getInstance();
                    if ($module === 'quotations' && $targetId) {
                        require_once __DIR__ . '/../modules/quotations/QuotationModel.php';
                        $qm = new QuotationModel();
                        // 判斷是否為變更簽核
                        $revFlow2 = $db->prepare("SELECT payload FROM approval_flows WHERE module = 'quotations' AND target_id = ? AND payload IS NOT NULL AND payload != '' LIMIT 1");
                        $revFlow2->execute(array($targetId));
                        $revPayload2 = $revFlow2->fetchColumn();
                        $revData2 = $revPayload2 ? json_decode($revPayload2, true) : null;
                        if ($revData2 && isset($revData2['type']) && $revData2['type'] === 'revision') {
                            // 變更簽核駁回 → 恢復原狀態
                            $origStatus = isset($revData2['original_status']) ? $revData2['original_status'] : 'sent';
                            $qm->updateStatus($targetId, $origStatus);
                            AuditLog::log('quotations', 'revision_rejected', $targetId, '變更簽核駁回，恢復為' . QuotationModel::statusLabel($origStatus));
                        } else {
                            $qm->updateStatus($targetId, 'rejected_internal');
                        }
                    } elseif ($module === 'purchases' && $targetId) {
                        require_once __DIR__ . '/../modules/procurement/ProcurementModel.php';
                        $pm = new ProcurementModel();
                        $pm->updateRequisition($targetId, array(
                            'status' => '退回',
                            'approval_user' => Auth::user()['real_name'],
                            'approval_date' => date('Y-m-d'),
                            'approval_note' => $comment,
                        ));
                    }
                    AuditLog::log('approvals', 'reject', $flowId, $module . ' #' . $targetId . ' - ' . $comment);
                    Session::flash('success', '已退回');
                }
            } else {
                Session::flash('error', '退回失敗');
            }
        }
        $redirect = $_POST['redirect'] ?? '/approvals.php';
        redirect($redirect);
        break;

    default:
        redirect('/approvals.php');
}
