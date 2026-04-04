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
$canViewApprovals = Auth::hasPermission('approvals.view') || Auth::hasPermission('approvals.manage') || Auth::hasPermission('all');

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

    // ---- 簽核設定 ----
    case 'settings':
        if (!$canManageRules) { Session::flash('error', '無權限'); redirect('/approvals.php'); }
        $rules = $model->getRules();
        $approvers = $model->getApprovers();
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
            // 處理額外層級
            if (!empty($_POST['extra_approver_role']) && is_array($_POST['extra_approver_role'])) {
                $extraRoles = $_POST['extra_approver_role'];
                $extraIds = isset($_POST['extra_approver_id']) ? $_POST['extra_approver_id'] : array();
                $extraOrders = isset($_POST['extra_level_order']) ? $_POST['extra_level_order'] : array();
                for ($i = 0; $i < count($extraRoles); $i++) {
                    if (!empty($extraRoles[$i]) || !empty($extraIds[$i])) {
                        $extraData = $_POST;
                        unset($extraData['id']);
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
                    $caseStmt = $db->prepare("SELECT title, branch_id FROM cases WHERE id = ?");
                    $caseStmt->execute(array($targetId));
                    $caseInfo = $caseStmt->fetch();
                    $caseTitleAppr = $caseInfo ? $caseInfo['title'] : '';

                    // 完工簽核多關流程
                    $stillPending = $model->advanceCaseCompletion($targetId);
                    if (!$stillPending) {
                        // 全部簽完 → 案件結案
                        $db->prepare("UPDATE cases SET status = 'closed' WHERE id = ?")->execute(array($targetId));
                        Session::flash('success', '已核准，案件已完工結案');
                    } else {
                        // 送下一關（會計）→ 通知會計
                        $db->prepare("UPDATE cases SET status = 'accounting_pending' WHERE id = ?")->execute(array($targetId));
                        $notifModel->sendToRole(
                            'admin_staff',
                            $caseInfo ? $caseInfo['branch_id'] : null,
                            'accounting_pending',
                            '待入帳確認：' . $caseTitleAppr,
                            '工程主管已簽核完工，請確認帳款入帳',
                            '/approvals.php',
                            'case', $targetId, Auth::id()
                        );
                        Session::flash('success', '已核准，已送會計確認入帳');
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
                } elseif ($module && $targetId && $model->isFullyApproved($module, $targetId)) {
                    // 其他模組：自動更新單據狀態
                    if ($module === 'quotations') {
                        require_once __DIR__ . '/../modules/quotations/QuotationModel.php';
                        $qm = new QuotationModel();
                        $qm->updateStatus($targetId, 'approved');
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
            if ($model->reject($flowId, Auth::id(), $comment)) {
                if ($module === 'case_completion' && $targetId) {
                    // 完工簽核退回 → 案件回到施工中
                    $model->rejectCaseCompletion($targetId);
                    AuditLog::log('approvals', 'reject', $flowId, '完工簽核退回 案件#' . $targetId . ' - ' . $comment);
                    Session::flash('success', '已退回，案件進度已改回施工中');
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
                } else {
                    // 其他模組退回
                    if ($module === 'quotations' && $targetId) {
                        require_once __DIR__ . '/../modules/quotations/QuotationModel.php';
                        $qm = new QuotationModel();
                        $qm->updateStatus($targetId, 'rejected_internal');
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
