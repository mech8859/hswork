<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('reports.view');
require_once __DIR__ . '/../modules/reports/ReportModel.php';
require_once __DIR__ . '/../modules/cases/CaseModel.php';

$model = new ReportModel();
$action = $_GET['action'] ?? 'index';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    // ---- 報表首頁 ----
    case 'index':
        $summaryYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
        $summaryMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        $caseSummary = $model->getCaseStatusSummary($branchIds, $summaryYear);
        $caseSummaryMonth = $model->getCaseStatusSummaryByMonth($branchIds, $summaryMonth);
        $currentMonth = date('Y-m');
        $monthlyStats = $model->getMonthlyScheduleStats($branchIds, $currentMonth);

        $pageTitle = '報表與分析';
        $currentPage = 'reports';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/reports/index.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 案件利潤 ----
    case 'case_profit':
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $data = $model->getCaseProfitReport($branchIds, $startDate, $endDate);

        $pageTitle = '案件利潤分析';
        $currentPage = 'reports';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/reports/case_profit.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 員工產值 ----
    case 'staff_productivity':
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $data = $model->getStaffProductivityReport($branchIds, $startDate, $endDate);

        $pageTitle = '員工產值統計';
        $currentPage = 'reports';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/reports/staff_productivity.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 案件綜合分析 ----
    case 'case_analysis':
        $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        $analysis = $model->getCaseAnalysis($branchIds, $year);

        $pageTitle = '案件綜合分析';
        $currentPage = 'reports';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/reports/case_analysis.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 帳務綜合分析 ----
    case 'finance_analysis':
        $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        $analysis = $model->getFinanceAnalysis($year);

        $pageTitle = '帳務綜合分析';
        $currentPage = 'reports';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/reports/finance_analysis.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 業務個人分析 ----
    case 'sales_personal':
        $salesId = isset($_GET['sales_id']) ? (int)$_GET['sales_id'] : Auth::id();
        $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        $analysis = $model->getSalesPersonalAnalysis($salesId, $year, $branchIds);
        // Get sales list for dropdown
        $caseModel = new CaseModel();
        $salespeople = $caseModel->getSalespeople();

        $pageTitle = '業務個人分析';
        $currentPage = 'reports';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/reports/sales_personal.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 點工費月結 ----
    case 'inter_branch':
        $month = $_GET['month'] ?? date('Y-m');
        $data = $model->getInterBranchMonthlyReport($branchIds, $month);

        $pageTitle = '跨點點工費月結報表';
        $currentPage = 'reports';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/reports/inter_branch_monthly.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 分公司月報 ----
    case 'branch_monthly':
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $user = Auth::user();
        $isBoss = in_array($user['role'], array('boss', 'vice_president', 'manager'));
        $userBranchId = $user['branch_id'];

        // 分公司選擇：管理者可選全部，其他人只能看自己
        if ($isBoss && !empty($_GET['branch_id'])) {
            $viewBranchId = (int)$_GET['branch_id'];
        } else {
            $viewBranchId = $isBoss ? 0 : (int)$userBranchId;
        }

        $db = Database::getInstance();
        $allBranches = $db->query("SELECT id, name FROM branches ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

        // 月份列表
        $bmMonths = array();
        for ($mi = 1; $mi <= 12; $mi++) {
            $start = sprintf('%04d-%02d-01', $year, $mi);
            $end = date('Y-m-t', mktime(0, 0, 0, $mi, 1, $year));
            if ($start > date('Y-m-d')) break;
            $bmMonths[] = array('month' => $mi, 'start' => $start, 'end' => $end);
        }

        // 收款：依 branch_id + deposit_date
        $bmRecv = array();
        foreach ($bmMonths as $bm) {
            $rWhere = "r.deposit_date BETWEEN '{$bm['start']}' AND '{$bm['end']}' AND r.status != '作廢'";
            if ($viewBranchId > 0) $rWhere .= " AND r.branch_id = {$viewBranchId}";
            $r = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM receipts r WHERE {$rWhere}")->fetchColumn();
            $bmRecv[$bm['month']] = (int)$r;
        }

        // 付款：依 payment_out_branches.branch_id + payments_out.payment_date
        $bmPay = array();
        foreach ($bmMonths as $bm) {
            $pWhere = "p.payment_date BETWEEN '{$bm['start']}' AND '{$bm['end']}' AND p.status != '取消' AND (p.exclude_from_branch_stats IS NULL OR p.exclude_from_branch_stats = 0)";
            if ($viewBranchId > 0) {
                $p = $db->query("SELECT COALESCE(SUM(pob.amount), 0) FROM payment_out_branches pob JOIN payments_out p ON pob.payment_out_id = p.id WHERE {$pWhere} AND pob.branch_id = {$viewBranchId}")->fetchColumn();
            } else {
                $p = $db->query("SELECT COALESCE(SUM(p.total_amount), 0) FROM payments_out p WHERE {$pWhere}")->fetchColumn();
            }
            $bmPay[$bm['month']] = (int)$p;
        }

        $pageTitle = '分公司月報';
        $currentPage = 'reports';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/reports/branch_monthly.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 分公司月報 AJAX 明細 ----
    case 'branch_monthly_detail':
        header('Content-Type: application/json; charset=utf-8');
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? 1);
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

        $db = Database::getInstance();

        // 收款明細
        $rWhere = "r.deposit_date BETWEEN ? AND ? AND r.status != '作廢'";
        $rParams = array($start, $end);
        if ($branchId > 0) { $rWhere .= " AND r.branch_id = ?"; $rParams[] = $branchId; }
        $rStmt = $db->prepare("SELECT r.receipt_number, r.deposit_date, r.customer_name, r.total_amount, r.status, r.receipt_method FROM receipts r WHERE {$rWhere} ORDER BY r.deposit_date");
        $rStmt->execute($rParams);
        $recvList = $rStmt->fetchAll(PDO::FETCH_ASSOC);

        // 付款明細
        $pParams = array($start, $end);
        if ($branchId > 0) {
            $pStmt = $db->prepare("SELECT p.payment_number, p.payment_date, p.vendor_name, pob.amount, p.main_category, p.status FROM payment_out_branches pob JOIN payments_out p ON pob.payment_out_id = p.id WHERE p.payment_date BETWEEN ? AND ? AND p.status != '取消' AND (p.exclude_from_branch_stats IS NULL OR p.exclude_from_branch_stats = 0) AND pob.branch_id = ? ORDER BY p.payment_date");
            $pParams[] = $branchId;
        } else {
            $pStmt = $db->prepare("SELECT p.payment_number, p.payment_date, p.vendor_name, p.total_amount AS amount, p.main_category, p.status FROM payments_out p WHERE p.payment_date BETWEEN ? AND ? AND p.status != '取消' AND (p.exclude_from_branch_stats IS NULL OR p.exclude_from_branch_stats = 0) ORDER BY p.payment_date");
        }
        $pStmt->execute($pParams);
        $payList = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(array('success' => true, 'recv' => $recvList, 'pay' => $payList), JSON_UNESCAPED_UNICODE);
        exit;

    // ---- AJAX 報表鑽取明細 ----
    case 'drill_down':
        header('Content-Type: application/json; charset=utf-8');
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = isset($_GET['month']) ? $_GET['month'] : ''; // '2026-03' or ''
        $salesName = isset($_GET['sales_name']) ? $_GET['sales_name'] : '';
        $type = isset($_GET['type']) ? $_GET['type'] : 'deal_amount'; // deal_amount, closed, entry, receipt

        $db = Database::getInstance();
        $closedStatuses = array('已成交', '跨月成交', '現簽', '電話報價成交');
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = $branchIds;

        if ($type === 'deal_amount' || $type === 'closed') {
            // 成交金額或成交件數：依 deal_date 月份
            $sql = "SELECT c.id, c.case_number, c.title, c.customer_name,
                        u.real_name AS sales_name, c.deal_date,
                        COALESCE(c.total_amount, 0) AS total_amount,
                        c.sub_status, c.case_type
                    FROM cases c
                    LEFT JOIN users u ON c.sales_id = u.id
                    WHERE c.branch_id IN ($ph)
                      AND c.sub_status IN ('已成交','跨月成交','現簽','電話報價成交')";
            if ($month) {
                $sql .= " AND DATE_FORMAT(c.deal_date, '%Y-%m') = ?";
                $params[] = $month;
            } else {
                $sql .= " AND c.deal_date BETWEEN ? AND ?";
                $params[] = $year . '-01-01';
                $params[] = $year . '-12-31';
            }
            if ($salesName) {
                $sql .= " AND u.real_name = ?";
                $params[] = $salesName;
            }
            $sql .= " ORDER BY c.deal_date DESC, c.id DESC";
        } elseif ($type === 'entry') {
            // 進件數：依 created_at 月份
            $sql = "SELECT c.id, c.case_number, c.title, c.customer_name,
                        u.real_name AS sales_name, DATE(c.created_at) AS created_date,
                        c.deal_date, COALESCE(c.total_amount, 0) AS total_amount,
                        c.sub_status, c.case_type
                    FROM cases c
                    LEFT JOIN users u ON c.sales_id = u.id
                    WHERE c.branch_id IN ($ph)";
            if ($month) {
                $sql .= " AND DATE_FORMAT(c.created_at, '%Y-%m') = ?";
                $params[] = $month;
            } else {
                $sql .= " AND c.created_at BETWEEN ? AND ?";
                $params[] = $year . '-01-01';
                $params[] = $year . '-12-31 23:59:59';
            }
            if ($salesName) {
                $sql .= " AND u.real_name = ?";
                $params[] = $salesName;
            }
            $sql .= " ORDER BY c.created_at DESC, c.id DESC";
        } elseif ($type === 'status') {
            // 案件狀態（sub_status）篩選
            $statusVal = isset($_GET['status_val']) ? $_GET['status_val'] : '';
            $sql = "SELECT c.id, c.case_number, c.title, c.customer_name,
                        u.real_name AS sales_name, DATE(c.created_at) AS created_date,
                        c.deal_date, COALESCE(c.total_amount, 0) AS total_amount,
                        c.sub_status, c.case_type
                    FROM cases c
                    LEFT JOIN users u ON c.sales_id = u.id
                    WHERE c.branch_id IN ($ph)";
            if ($month) {
                $sql .= " AND DATE_FORMAT(c.created_at, '%Y-%m') = ?";
                $params[] = $month;
            } else {
                $sql .= " AND c.created_at BETWEEN ? AND ?";
                $params[] = $year . '-01-01';
                $params[] = $year . '-12-31 23:59:59';
            }
            if ($statusVal) {
                $sql .= " AND c.sub_status = ?";
                $params[] = $statusVal;
            }
            if ($salesName) {
                $sql .= " AND u.real_name = ?";
                $params[] = $salesName;
            }
            $sql .= " ORDER BY c.created_at DESC, c.id DESC";
        } elseif ($type === 'receipt') {
            // 收款金額：從 receipts
            $sql = "SELECT r.id, r.receipt_number AS case_number,
                        r.customer_name AS title, r.customer_name,
                        u.real_name AS sales_name, r.deposit_date AS deal_date,
                        r.subtotal AS total_amount, r.status AS sub_status,
                        'receipt' AS case_type
                    FROM receipts r
                    LEFT JOIN users u ON r.sales_id = u.id
                    WHERE r.status = '已收款'";
            $params = array();
            if ($month) {
                $sql .= " AND DATE_FORMAT(r.deposit_date, '%Y-%m') = ?";
                $params[] = $month;
            } else {
                $sql .= " AND r.deposit_date BETWEEN ? AND ?";
                $params[] = $year . '-01-01';
                $params[] = $year . '-12-31';
            }
            if ($salesName) {
                $sql .= " AND u.real_name = ?";
                $params[] = $salesName;
            }
            $sql .= " ORDER BY r.deposit_date DESC";
        } elseif ($type === 'realtime_status') {
            // 即時：業務 × 案件狀態(sub_status) 鑽取
            // 成交類狀態取 deal_amount，其他取 total_amount
            $statusVal = isset($_GET['status_val']) ? $_GET['status_val'] : '';
            $sql = "SELECT c.id, c.case_number, c.title, c.customer_name,
                        u.real_name AS sales_name, DATE(c.created_at) AS created_date,
                        c.deal_date,
                        CASE WHEN c.sub_status IN ('已成交','跨月成交','現簽','電話報價成交')
                             THEN COALESCE(c.deal_amount, 0)
                             ELSE COALESCE(c.total_amount, 0) END AS total_amount,
                        c.sub_status, c.status, c.case_type
                    FROM cases c
                    LEFT JOIN users u ON c.sales_id = u.id
                    WHERE c.branch_id IN ($ph)
                      AND c.status NOT IN ('closed','cancelled','已完工結案','客戶取消')";
            if ($statusVal) { $sql .= " AND c.sub_status = ?"; $params[] = $statusVal; }
            if ($salesName) { $sql .= " AND u.real_name = ?"; $params[] = $salesName; }
            $sql .= " ORDER BY c.created_at DESC LIMIT 500";
        } elseif ($type === 'realtime_progress') {
            // 即時：業務 × 案件進度(status) 鑽取
            $statusVal = isset($_GET['status_val']) ? $_GET['status_val'] : '';
            $_pgReverseMap = array(
                '待追蹤'=>'tracking','未完工'=>'incomplete','完工未收款'=>'unpaid',
                '已完工待簽核'=>'completed_pending','已完工結案'=>'closed','未成交'=>'lost',
                '保養案件'=>'maint_case','毀約'=>'breach','已排工/已排行事曆'=>'scheduled',
                '已進場/需再安排'=>'needs_reschedule','待安排派工查修'=>'awaiting_dispatch',
                '客戶取消'=>'customer_cancel',
            );
            $statusEng = isset($_pgReverseMap[$statusVal]) ? $_pgReverseMap[$statusVal] : $statusVal;
            $sql = "SELECT c.id, c.case_number, c.title, c.customer_name,
                        u.real_name AS sales_name, DATE(c.created_at) AS created_date,
                        c.deal_date,
                        GREATEST(COALESCE(c.deal_amount, 0) - COALESCE(c.total_collected, 0), 0) AS total_amount,
                        c.sub_status, c.status, c.case_type
                    FROM cases c
                    LEFT JOIN users u ON c.sales_id = u.id
                    WHERE c.branch_id IN ($ph)
                      AND c.status NOT IN ('closed','cancelled','已完工結案','客戶取消')";
            if ($statusEng) { $sql .= " AND c.status = ?"; $params[] = $statusEng; }
            if ($salesName) { $sql .= " AND u.real_name = ?"; $params[] = $salesName; }
            $sql .= " ORDER BY c.created_at DESC LIMIT 500";
        } else {
            echo json_encode(array('cases' => array()));
            exit;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array('cases' => $cases), JSON_UNESCAPED_UNICODE);
        exit;

    default:
        redirect('/reports.php');
}
