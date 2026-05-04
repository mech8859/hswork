<?php
/**
 * 報表資料模型
 */
class ReportModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 案件利潤分析
     */
    public function getCaseProfitReport(array $branchIds, string $startDate, string $endDate): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = array_merge($branchIds, [$startDate, $endDate]);

        $stmt = $this->db->prepare("
            SELECT c.id, c.case_number, c.title, c.case_type, c.status,
                   b.name AS branch_name,
                   COALESCE(p.total_paid, 0) AS total_paid,
                   COALESCE(s.work_days, 0) AS work_days,
                   COALESCE(s.engineer_count, 0) AS total_engineer_days
            FROM cases c
            JOIN branches b ON c.branch_id = b.id
            LEFT JOIN (
                SELECT case_id, SUM(amount) AS total_paid
                FROM payments
                GROUP BY case_id
            ) p ON p.case_id = c.id
            LEFT JOIN (
                SELECT s2.case_id,
                       COUNT(DISTINCT s2.id) AS work_days,
                       COUNT(se.id) AS engineer_count
                FROM schedules s2
                LEFT JOIN schedule_engineers se ON se.schedule_id = s2.id
                WHERE s2.status != 'cancelled'
                GROUP BY s2.case_id
            ) s ON s.case_id = c.id
            WHERE c.branch_id IN ($ph)
              AND c.created_at BETWEEN ? AND ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 員工產值統計
     */
    public function getStaffProductivityReport(array $branchIds, string $startDate, string $endDate): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = array_merge($branchIds, [$startDate, $endDate]);

        $stmt = $this->db->prepare("
            SELECT u.id, u.real_name, b.name AS branch_name,
                   COUNT(DISTINCT se.schedule_id) AS schedule_count,
                   COUNT(DISTINCT s.case_id) AS case_count,
                   COUNT(DISTINCT wl.id) AS worklog_count,
                   SUM(CASE WHEN wl.arrival_time IS NOT NULL AND wl.departure_time IS NOT NULL
                       THEN TIMESTAMPDIFF(MINUTE, wl.arrival_time, wl.departure_time) ELSE 0 END) AS total_minutes
            FROM users u
            JOIN branches b ON u.branch_id = b.id
            LEFT JOIN schedule_engineers se ON se.user_id = u.id
            LEFT JOIN schedules s ON se.schedule_id = s.id AND s.status != 'cancelled'
                AND s.schedule_date BETWEEN ? AND ?
            LEFT JOIN work_logs wl ON wl.user_id = u.id AND DATE(wl.arrival_time) BETWEEN ? AND ?
            WHERE u.branch_id IN ($ph) AND u.is_engineer = 1 AND u.is_active = 1
            GROUP BY u.id, u.real_name, b.name
            ORDER BY schedule_count DESC
        ");
        $allParams = array_merge([$startDate, $endDate, $startDate, $endDate], $branchIds);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }

    /**
     * 跨點點工費月結報表
     */
    public function getInterBranchMonthlyReport(array $branchIds, string $month): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $params = array_merge($branchIds, [$startDate, $endDate]);

        $stmt = $this->db->prepare("
            SELECT bf.name AS from_branch, bt.name AS to_branch,
                   COUNT(*) AS support_count,
                   SUM(CASE WHEN ibs.charge_type = 'full_day' THEN 1 ELSE 0 END) AS full_days,
                   SUM(CASE WHEN ibs.charge_type = 'half_day' THEN 1 ELSE 0 END) AS half_days,
                   SUM(CASE WHEN ibs.charge_type = 'hourly' THEN COALESCE(ibs.hours, 0) ELSE 0 END) AS total_hours,
                   SUM(ibs.settled) AS settled_count
            FROM inter_branch_support ibs
            JOIN branches bf ON ibs.from_branch_id = bf.id
            JOIN branches bt ON ibs.to_branch_id = bt.id
            WHERE ibs.from_branch_id IN ($ph)
              AND ibs.support_date BETWEEN ? AND ?
            GROUP BY bf.name, bt.name
            ORDER BY bf.name, bt.name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 案件綜合分析
     */
    public function getCaseAnalysis(array $branchIds, $year)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $yearStart = $year . '-01-01';
        $yearEnd   = $year . '-12-31';
        $closedStatuses = array('已成交', '跨月成交', '現簽', '電話報價成交');

        // 取得所有可存取的分公司名稱（即使沒有案件也列出）
        $stmt = $this->db->prepare("SELECT id, name FROM branches WHERE id IN ($ph) ORDER BY id");
        $stmt->execute($branchIds);
        $allBranches = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $allBranches[$row['id']] = $row['name'];
        }

        // 取得該年度所有月份
        $stmt = $this->db->prepare("
            SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS m
            FROM cases WHERE branch_id IN ($ph) AND created_at BETWEEN ? AND ?
            ORDER BY m
        ");
        $stmt->execute(array_merge($branchIds, array($yearStart, $yearEnd)));
        $months = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $months[] = $row['m'];
        }
        if (empty($months)) {
            return array('year' => $year, 'months' => array());
        }

        // 一次性取出該年度所有案件的關鍵欄位
        $stmt = $this->db->prepare("
            SELECT c.id, DATE_FORMAT(c.created_at, '%Y-%m') AS month,
                   DATE_FORMAT(c.deal_date, '%Y-%m') AS deal_month,
                   c.sub_status, c.status, c.case_type, c.case_source,
                   c.branch_id,
                   b.name AS branch_name,
                   u.real_name AS sales_name,
                   COALESCE(c.quote_amount, 0) AS quote_amount,
                   COALESCE(c.deal_amount, 0) AS deal_amount,
                   COALESCE(c.total_amount, 0) AS total_amount,
                   COALESCE(c.deposit_amount, 0) AS deposit_amount,
                   COALESCE(c.balance_amount, 0) AS balance_amount,
                   COALESCE(c.completion_amount, 0) AS completion_amount,
                   COALESCE(c.total_collected, 0) AS total_collected,
                   c.completion_date,
                   c.case_number,
                   c.customer_name
            FROM cases c
            JOIN branches b ON c.branch_id = b.id
            LEFT JOIN users u ON c.sales_id = u.id
            WHERE c.branch_id IN ($ph) AND (c.created_at BETWEEN ? AND ? OR c.deal_date BETWEEN ? AND ?)
            ORDER BY c.created_at
        ");
        $stmt->execute(array_merge($branchIds, array($yearStart, $yearEnd, $yearStart, $yearEnd)));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // PHP 端做 pivot 彙整
        $result = array(
            'year' => $year,
            'months' => $months,
            'total_cases' => count($rows),
        );

        // 初始化
        $monthlyEntry = array(); $monthlyClosed = array();
        $monthlyAmounts = array();
        $branchMonthly = array(); $branchMonthlyAmounts = array();
        $statusMonthly = array();
        $progressMonthly = array(); $sourceMonthly = array();
        $salesCases = array(); $salesAmount = array(); $salesClosed = array();
        $salesStats = array();
        $caseTypeMonthly = array(); $caseTypeClosedMonthly = array();

        // 預填所有分公司，確保即使沒案件也會出現
        foreach ($allBranches as $bid => $bname) {
            $branchMonthly[$bname] = array();
            $branchMonthlyAmounts[$bname] = array();
        }

        foreach ($rows as $r) {
            $m = $r['month'];
            $isClosed = in_array($r['sub_status'], $closedStatuses);

            // 1. 月度進件（以 created_at 月份，只算年度內的）
            $inYear = ($m >= substr($yearStart, 0, 7) && $m <= substr($yearEnd, 0, 7));
            if ($inYear) {
                if (!isset($monthlyEntry[$m])) $monthlyEntry[$m] = 0;
                $monthlyEntry[$m]++;
            }

            // 1a. 成交數（以 deal_date 月份為準）
            $dm = $r['deal_month'];
            if ($isClosed && $dm) {
                if (!isset($monthlyClosed[$dm])) $monthlyClosed[$dm] = 0;
                $monthlyClosed[$dm]++;
            }

            // 1b. 按案別分類統計（進件只算年度內）
            $ct = $r['case_type'] ?: 'new_install';
            if ($inYear) {
                if (!isset($caseTypeMonthly[$ct])) $caseTypeMonthly[$ct] = array();
                if (!isset($caseTypeMonthly[$ct][$m])) $caseTypeMonthly[$ct][$m] = 0;
                $caseTypeMonthly[$ct][$m]++;
            }
            if ($isClosed && $dm) {
                if (!isset($caseTypeClosedMonthly[$ct])) $caseTypeClosedMonthly[$ct] = array();
                if (!isset($caseTypeClosedMonthly[$ct][$dm])) $caseTypeClosedMonthly[$ct][$dm] = 0;
                $caseTypeClosedMonthly[$ct][$dm]++;
            }

            // 2. 金額（報價用進件月，成交金額用 deal_date 月）
            if (!isset($monthlyAmounts[$m])) {
                $monthlyAmounts[$m] = array('total_amount' => 0, 'quote_amount' => 0, 'deposit_amount' => 0, 'completion_amount' => 0, 'balance_amount' => 0, 'total_collected' => 0);
            }
            if ($isClosed && $dm) {
                if (!isset($monthlyAmounts[$dm])) {
                    $monthlyAmounts[$dm] = array('total_amount' => 0, 'quote_amount' => 0, 'deposit_amount' => 0, 'completion_amount' => 0, 'balance_amount' => 0, 'total_collected' => 0);
                }
                $monthlyAmounts[$dm]['total_amount'] += $r['total_amount'];
            }
            $monthlyAmounts[$m]['quote_amount'] += $r['quote_amount'];
            $monthlyAmounts[$m]['deposit_amount'] += $r['deposit_amount'];
            $monthlyAmounts[$m]['completion_amount'] += $r['completion_amount'];
            $monthlyAmounts[$m]['balance_amount'] += $r['balance_amount'];
            $monthlyAmounts[$m]['total_collected'] += $r['total_collected'];

            // 3. 分公司進件數（只算年度內）
            $bn = $r['branch_name'] ?: '(未設定)';
            if ($inYear) {
                if (!isset($branchMonthly[$bn])) $branchMonthly[$bn] = array();
                if (!isset($branchMonthly[$bn][$m])) $branchMonthly[$bn][$m] = 0;
                $branchMonthly[$bn][$m]++;
            }

            // 3b. 分公司成交金額（以 deal_date 月份）
            if ($isClosed && $dm && $r['total_amount'] > 0) {
                if (!isset($branchMonthlyAmounts[$bn])) $branchMonthlyAmounts[$bn] = array();
                if (!isset($branchMonthlyAmounts[$bn][$dm])) $branchMonthlyAmounts[$bn][$dm] = 0;
                $branchMonthlyAmounts[$bn][$dm] += $r['total_amount'];
            }

            // 以下統計只算 created_at 在年度內的案件
            if ($inYear) {
                // 4. 狀態(sub_status)
                $ss = $r['sub_status'] ?: '(空)';
                if (!isset($statusMonthly[$ss])) $statusMonthly[$ss] = array();
                if (!isset($statusMonthly[$ss][$m])) $statusMonthly[$ss][$m] = 0;
                $statusMonthly[$ss][$m]++;

                // 5. 進度(status/progress)
                $pg = $r['status'] ?: '(空)';
                if (!isset($progressMonthly[$pg])) $progressMonthly[$pg] = array();
                if (!isset($progressMonthly[$pg][$m])) $progressMonthly[$pg][$m] = 0;
                $progressMonthly[$pg][$m]++;

                // 6. 來源（轉換為中文標籤）
                $srcRaw = $r['case_source'] ?: '';
                $src = $this->getSourceLabel($srcRaw);
                if (!isset($sourceMonthly[$src])) $sourceMonthly[$src] = array();
                if (!isset($sourceMonthly[$src][$m])) $sourceMonthly[$src][$m] = 0;
                $sourceMonthly[$src][$m]++;

                // 7. 業務進件
                $sn = $r['sales_name'] ?: '(未指定)';
                if (!isset($salesCases[$sn])) $salesCases[$sn] = array();
                if (!isset($salesCases[$sn][$m])) $salesCases[$sn][$m] = 0;
                $salesCases[$sn][$m]++;
            } else {
                $sn = $r['sales_name'] ?: '(未指定)';
            }

            if ($isClosed && $dm && $r['total_amount'] > 0) {
                if (!isset($salesAmount[$sn])) $salesAmount[$sn] = array();
                if (!isset($salesAmount[$sn][$dm])) $salesAmount[$sn][$dm] = 0;
                $salesAmount[$sn][$dm] += $r['total_amount'];
            }
            if ($isClosed && $dm) {
                if (!isset($salesClosed[$sn])) $salesClosed[$sn] = array();
                if (!isset($salesClosed[$sn][$dm])) $salesClosed[$sn][$dm] = 0;
                $salesClosed[$sn][$dm]++;
            }

            // 10. 業務績效
            if (!isset($salesStats[$sn])) {
                $salesStats[$sn] = array('total' => 0, 'closed' => 0, 'amount' => 0, 'branch' => $r['branch_name']);
            }
            $salesStats[$sn]['total']++;
            if ($isClosed && $dm) {
                $salesStats[$sn]['closed']++;
                $salesStats[$sn]['amount'] += $r['total_amount'];
            }
        }

        // 排序 pivots by total desc
        $sortByTotal = function ($a, $b) use ($months) {
            $ta = 0; $tb = 0;
            foreach ($months as $m) { $ta += isset($a[$m]) ? $a[$m] : 0; $tb += isset($b[$m]) ? $b[$m] : 0; }
            return $tb - $ta;
        };
        uasort($branchMonthly, $sortByTotal);
        uasort($branchMonthlyAmounts, $sortByTotal);
        uasort($statusMonthly, $sortByTotal);
        uasort($progressMonthly, $sortByTotal);
        uasort($sourceMonthly, $sortByTotal);
        uasort($caseTypeMonthly, $sortByTotal);
        uasort($caseTypeClosedMonthly, $sortByTotal);
        uasort($salesCases, $sortByTotal);
        uasort($salesAmount, $sortByTotal);
        uasort($salesClosed, $sortByTotal);

        // 業務排名(按金額降序)
        uasort($salesStats, function ($a, $b) { return $b['amount'] - $a['amount']; });

        // 重算 total_cases（只算 created_at 在年度內的）
        $result['total_cases'] = array_sum($monthlyEntry);
        $result['monthly_entry'] = $monthlyEntry;
        $result['monthly_closed'] = $monthlyClosed;
        $result['case_type_monthly'] = $caseTypeMonthly;
        $result['case_type_closed_monthly'] = $caseTypeClosedMonthly;
        $result['monthly_amounts'] = $monthlyAmounts;
        $result['branch_monthly'] = $branchMonthly;
        $result['branch_monthly_amounts'] = $branchMonthlyAmounts;
        $result['status_monthly'] = $statusMonthly;
        $result['progress_monthly'] = $progressMonthly;
        $result['source_monthly'] = $sourceMonthly;
        $result['sales_cases'] = $salesCases;
        $result['sales_amount'] = $salesAmount;
        $result['sales_closed'] = $salesClosed;
        $result['sales_ranking'] = $salesStats;

        // 十一、業務各月收款金額（從收款單 receipts 取）
        $receiptSql = "
            SELECT u.real_name AS sales_name,
                   DATE_FORMAT(r.deposit_date, '%Y-%m') AS month,
                   SUM(r.subtotal) AS amt,
                   COUNT(*) AS cnt
            FROM receipts r
            LEFT JOIN users u ON r.sales_id = u.id
            WHERE r.deposit_date BETWEEN ? AND ?
              AND r.status = '已收款'
            GROUP BY u.real_name, DATE_FORMAT(r.deposit_date, '%Y-%m')
            ORDER BY u.real_name, month
        ";
        $stmt = $this->db->prepare($receiptSql);
        $stmt->execute(array($yearStart, $yearEnd));
        $receiptRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $salesReceipt = array();
        $salesReceiptCount = array();
        foreach ($receiptRows as $rr) {
            $sn = $rr['sales_name'] ?: '(未指定)';
            $m = $rr['month'];
            if (!isset($salesReceipt[$sn])) $salesReceipt[$sn] = array();
            $salesReceipt[$sn][$m] = (int)$rr['amt'];
            if (!isset($salesReceiptCount[$sn])) $salesReceiptCount[$sn] = array();
            $salesReceiptCount[$sn][$m] = (int)$rr['cnt'];
        }
        uasort($salesReceipt, $sortByTotal);
        uasort($salesReceiptCount, $sortByTotal);

        $result['sales_receipt'] = $salesReceipt;
        $result['sales_receipt_count'] = $salesReceiptCount;

        // 十五、案件進度交叉分析
        $progressTargets = array(
            'tracking'          => '待追蹤',
            'awaiting_dispatch' => '待安排查修',
            'incomplete'        => '未完工',
            'closed'            => '已完工結案',
            'unpaid'            => '完工未收款',
        );
        $progressCross = array();
        foreach ($progressTargets as $pKey => $pLabel) {
            $progressCross[$pKey] = array(
                'label' => $pLabel,
                'total' => 0,
                'has_quote' => 0,
                'is_completed' => 0,
                'is_closed_deal' => 0,
                'has_completion_date' => 0,
                'has_deal_amount' => 0,
                'no_deal_amount' => 0,
                'no_balance' => 0,
                'cases_total' => array(),
                'cases_has_quote' => array(),
                'cases_is_completed' => array(),
                'cases_is_closed_deal' => array(),
                'cases_has_completion_date' => array(),
                'cases_has_deal_amount' => array(),
                'cases_no_deal_amount' => array(),
                'cases_no_balance' => array(),
            );
        }
        foreach ($rows as $r) {
            $pg = isset($r['status']) ? $r['status'] : '';
            if (!isset($progressCross[$pg])) continue;
            $caseInfo = array(
                'id' => $r['id'],
                'case_number' => isset($r['case_number']) ? $r['case_number'] : '',
                'customer_name' => isset($r['customer_name']) ? $r['customer_name'] : '',
                'sales_name' => isset($r['sales_name']) ? $r['sales_name'] : '',
                'quote_amount' => (float)$r['quote_amount'],
                'deal_amount' => (float)$r['deal_amount'],
                'total_amount' => (float)$r['total_amount'],
                'balance_amount' => (float)$r['balance_amount'],
                'sub_status' => $r['sub_status'],
                'completion_date' => isset($r['completion_date']) ? $r['completion_date'] : '',
            );
            $progressCross[$pg]['total']++;
            $progressCross[$pg]['cases_total'][] = $caseInfo;
            if ($r['quote_amount'] > 0) {
                $progressCross[$pg]['has_quote']++;
                $progressCross[$pg]['cases_has_quote'][] = $caseInfo;
            }
            if (!empty($r['completion_date'])) {
                $progressCross[$pg]['is_completed']++;
                $progressCross[$pg]['cases_is_completed'][] = $caseInfo;
                $progressCross[$pg]['has_completion_date']++;
                $progressCross[$pg]['cases_has_completion_date'][] = $caseInfo;
            }
            if (in_array($r['sub_status'], $closedStatuses)) {
                $progressCross[$pg]['is_closed_deal']++;
                $progressCross[$pg]['cases_is_closed_deal'][] = $caseInfo;
            }
            if ($r['deal_amount'] > 0) {
                $progressCross[$pg]['has_deal_amount']++;
                $progressCross[$pg]['cases_has_deal_amount'][] = $caseInfo;
            } else {
                $progressCross[$pg]['no_deal_amount']++;
                $progressCross[$pg]['cases_no_deal_amount'][] = $caseInfo;
            }
            if ($r['balance_amount'] <= 0) {
                $progressCross[$pg]['no_balance']++;
                $progressCross[$pg]['cases_no_balance'][] = $caseInfo;
            }
        }
        $result['progress_cross'] = $progressCross;

        return $result;
    }

    /**
     * 案件來源英文代碼轉中文
     */
    private function getSourceLabel($src)
    {
        static $map = null;
        if ($map === null) {
            $map = array(
                // CaseModel::caseSourceOptions() 的 key
                'phone'        => '電話',
                'headquarters' => '總公司',
                'sales_dev'    => '業務開發',
                'referral'     => '老客戶介紹',
                'internet'     => '網路',
                'builder'      => '建商配合',
                'cross_biz'    => '異業合作',
                'other'        => '其他',
                // 舊系統匯入的代碼
                'existing'     => '老客戶',
                'hq'           => '總公司',
                'line'         => 'LINE',
                'website'      => '網站',
                'private'      => '自行開發',
                'store'        => '門市',
                'facebook'     => 'Facebook',
                'email'        => 'Email',
            );
        }
        if (empty($src)) return '(未設定)';
        return isset($map[$src]) ? $map[$src] : $src;
    }

    /**
     * 案件狀態彙總
     */
    public function getCaseStatusSummary(array $branchIds, $year = null): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = $branchIds;
        $dateFilter = '';
        if ($year) {
            $dateFilter = ' AND created_at BETWEEN ? AND ?';
            $params[] = $year . '-01-01';
            $params[] = $year . '-12-31';
        }
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) AS cnt
            FROM cases
            WHERE branch_id IN ($ph){$dateFilter}
            GROUP BY status
            ORDER BY FIELD(status, 'tracking','incomplete','unpaid','completed_pending','closed','lost','maint_case','breach','scheduled','needs_reschedule','awaiting_dispatch','customer_cancel')
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getCaseStatusSummaryByMonth(array $branchIds, $yearMonth)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = $branchIds;
        $params[] = $yearMonth . '-01';
        $params[] = date('Y-m-t', strtotime($yearMonth . '-01'));
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) AS cnt
            FROM cases
            WHERE branch_id IN ($ph) AND created_at BETWEEN ? AND ?
            GROUP BY status
            ORDER BY FIELD(status, 'tracking','incomplete','unpaid','completed_pending','closed','lost','maint_case','breach','scheduled','needs_reschedule','awaiting_dispatch','customer_cancel')
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 帳務綜合分析（對應 Google Sheets 帳務分析腳本）
     */
    public function getFinanceAnalysis($year)
    {
        $yearStart = $year . '-01-01';
        $yearEnd   = $year . '-12-31';
        $branches = array('潭子分公司','員林分公司','清水分公司','東區電子鎖','清水電子鎖','中區專案部','中區管理處');

        // ── 收款單（已入帳）──
        $stmt = $this->db->prepare("
            SELECT r.deposit_date, r.total_amount, b.name AS branch_name
            FROM receipts r
            LEFT JOIN branches b ON r.branch_id = b.id
            WHERE r.status IN ('已收款','已入帳','已收待查資料','預收待查')
              AND r.deposit_date BETWEEN ? AND ?
            ORDER BY r.deposit_date
        ");
        $stmt->execute(array($yearStart, $yearEnd));
        $recvRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── 付款單 + 分公司拆帳 ──
        // 加 exclude_from_branch_stats 旗標：用於計算「分公司年度統計（不含補帳）」
        // 防呆：欄位可能還沒建立（migration 112 未跑）→ try/catch 後用 fallback
        try {
            $stmt = $this->db->prepare("
                SELECT po.payment_date, po.total_amount AS po_total,
                       po.exclude_from_branch_stats,
                       pob.amount AS branch_amount, b.name AS branch_name
                FROM payments_out po
                LEFT JOIN payment_out_branches pob ON pob.payment_out_id = po.id
                LEFT JOIN branches b ON pob.branch_id = b.id
                WHERE po.payment_date BETWEEN ? AND ?
                ORDER BY po.payment_date
            ");
            $stmt->execute(array($yearStart, $yearEnd));
            $payRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // exclude_from_branch_stats 欄位不存在 → 使用舊版查詢
            $stmt = $this->db->prepare("
                SELECT po.payment_date, po.total_amount AS po_total,
                       0 AS exclude_from_branch_stats,
                       pob.amount AS branch_amount, b.name AS branch_name
                FROM payments_out po
                LEFT JOIN payment_out_branches pob ON pob.payment_out_id = po.id
                LEFT JOIN branches b ON pob.branch_id = b.id
                WHERE po.payment_date BETWEEN ? AND ?
                ORDER BY po.payment_date
            ");
            $stmt->execute(array($yearStart, $yearEnd));
            $payRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // ── 現金明細 ──
        $stmt = $this->db->prepare("
            SELECT cd.transaction_date, cd.income_amount, cd.expense_amount, b.name AS branch_name
            FROM cash_details cd
            LEFT JOIN branches b ON cd.branch_id = b.id
            WHERE cd.transaction_date BETWEEN ? AND ?
            ORDER BY cd.transaction_date
        ");
        $stmt->execute(array($yearStart, $yearEnd));
        $cashRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── 銀行帳戶最新餘額 ──
        // 動態取得所有銀行帳戶的最新餘額
        $stmt = $this->db->query("SELECT DISTINCT bank_account FROM bank_transactions WHERE bank_account IS NOT NULL ORDER BY bank_account");
        $bankAccounts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $bankLatest = array();
        foreach ($bankAccounts as $acct) {
            // 最新餘額
            $stmt = $this->db->prepare("
                SELECT balance FROM bank_transactions
                WHERE bank_account = ?
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
            $stmt->execute(array($acct));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $bal = $row ? (float)$row['balance'] : 0;
            // 轉入轉出合計
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(credit_amount),0) AS total_in,
                       COALESCE(SUM(debit_amount),0) AS total_out
                FROM bank_transactions WHERE bank_account = ?
            ");
            $stmt->execute(array($acct));
            $io = $stmt->fetch(PDO::FETCH_ASSOC);
            // 簡化名稱
            $shortName = $acct;
            if (strpos($acct, '中國信託') !== false && strpos($acct, '政遠') === false) $shortName = '中國信託';
            elseif (strpos($acct, '彰化銀行') !== false) $shortName = '彰化銀行';
            elseif (strpos($acct, '富邦') !== false) $shortName = '富邦銀行';
            elseif (strpos($acct, '政遠') !== false) $shortName = '政遠企業';
            $bankLatest[] = array(
                'name' => $shortName,
                'balance' => $bal,
                'total_in' => (float)$io['total_in'],
                'total_out' => (float)$io['total_out'],
            );
        }
        $bankTotal = 0;
        foreach ($bankLatest as $bl) { $bankTotal += $bl['balance']; }

        // ── 零用金餘額（各分公司）──
        $pettyBranches = array(
            1 => '潭子分公司', 3 => '員林分公司', 2 => '清水分公司', 4 => '東區電子鎖'
        );
        $pettyBalance = array();
        $pettyTotal = 0;
        foreach ($pettyBranches as $bid => $bname) {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(income_amount),0) AS inc,
                       COALESCE(SUM(expense_amount),0) AS exp
                FROM petty_cash WHERE branch_id = ?
            ");
            $stmt->execute(array($bid));
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $net = (float)$r['inc'] - (float)$r['exp'];
            $pettyBalance[] = array('branch' => $bname, 'income' => (float)$r['inc'], 'expense' => (float)$r['exp'], 'net' => $net);
            $pettyTotal += $net;
        }

        // ── 備用金 ──
        $stmt = $this->db->query("SELECT COALESCE(SUM(income_amount),0) AS inc, COALESCE(SUM(expense_amount),0) AS exp FROM reserve_fund");
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $reserveIn = (float)$r['inc'];
        $reserveOut = (float)$r['exp'];
        $reserveNet = $reserveIn - $reserveOut;

        // ── 現金淨額 ──
        $stmt = $this->db->query("SELECT COALESCE(SUM(income_amount),0) AS inc, COALESCE(SUM(expense_amount),0) AS exp FROM cash_details");
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $cashIn = (float)$r['inc'];
        $cashOut = (float)$r['exp'];
        $cashNet = $cashIn - $cashOut;

        // 週轉金
        $weeklyFund = 4517368;

        $grandTotal = $bankTotal + $weeklyFund + $pettyTotal + $reserveNet + $cashNet;

        // ── 月份清單 ──
        $monthSet = array();
        foreach ($recvRows as $r) {
            $m = substr($r['deposit_date'], 0, 7);
            $monthSet[$m] = true;
        }
        foreach ($payRows as $r) {
            if (!empty($r['payment_date'])) {
                $m = substr($r['payment_date'], 0, 7);
                $monthSet[$m] = true;
            }
        }
        $months = array_keys($monthSet);
        sort($months);

        // ── 收款 pivot: branch × month ──
        $recvPivot = array();
        foreach ($recvRows as $r) {
            $bn = !empty($r['branch_name']) ? $r['branch_name'] : '(未設定)';
            $m = substr($r['deposit_date'], 0, 7);
            if (!isset($recvPivot[$bn])) $recvPivot[$bn] = array();
            if (!isset($recvPivot[$bn][$m])) $recvPivot[$bn][$m] = 0;
            $recvPivot[$bn][$m] += (float)$r['total_amount'];
        }

        // ── 付款 pivot: branch × month ──
        // 1. payPivot (含全部資料 — 含補帳項，現有報表用)
        // 2. payPivotBranchOnly (排除已勾選「不列入分公司統計」項 — 分公司年度統計用)
        $payPivot = array();
        $payPivotBranchOnly = array();
        $excludedSummary = array('count' => 0, 'amount' => 0);
        $excludedKeys = array(); // 避免同一筆 po 被算多次（一筆可能拆給多分公司有多列）
        foreach ($payRows as $r) {
            if (empty($r['payment_date'])) continue;
            $bn = !empty($r['branch_name']) ? $r['branch_name'] : '(未設定)';
            $m = substr($r['payment_date'], 0, 7);
            $amt = (float)$r['branch_amount'];
            $isExcluded = !empty($r['exclude_from_branch_stats']);

            // 全部版本
            if (!isset($payPivot[$bn])) $payPivot[$bn] = array();
            if (!isset($payPivot[$bn][$m])) $payPivot[$bn][$m] = 0;
            $payPivot[$bn][$m] += $amt;

            // 排除補帳版本
            if (!$isExcluded) {
                if (!isset($payPivotBranchOnly[$bn])) $payPivotBranchOnly[$bn] = array();
                if (!isset($payPivotBranchOnly[$bn][$m])) $payPivotBranchOnly[$bn][$m] = 0;
                $payPivotBranchOnly[$bn][$m] += $amt;
            } else {
                // 統計被排除的金額（用 po_total 避免重複加）
                $key = $r['payment_date'] . '|' . (string)$r['po_total'];
                if (!isset($excludedKeys[$key])) {
                    $excludedKeys[$key] = true;
                    $excludedSummary['count']++;
                    $excludedSummary['amount'] += (float)$r['po_total'];
                }
            }
        }

        // ── 現金月別 ──
        $cashMonthly = array();
        foreach ($cashRows as $r) {
            if (empty($r['transaction_date'])) continue;
            $m = substr($r['transaction_date'], 0, 7);
            $bn = !empty($r['branch_name']) ? $r['branch_name'] : '(未設定)';
            if (!isset($cashMonthly[$m])) $cashMonthly[$m] = array();
            if (!isset($cashMonthly[$m][$bn])) $cashMonthly[$m][$bn] = array('in' => 0, 'out' => 0);
            $cashMonthly[$m][$bn]['in'] += (float)$r['income_amount'];
            $cashMonthly[$m][$bn]['out'] += (float)$r['expense_amount'];
        }

        // ── 每日收支差額 ──
        $dailyNet = array();
        foreach ($recvRows as $r) {
            $d = $r['deposit_date'];
            $bn = !empty($r['branch_name']) ? $r['branch_name'] : '(未設定)';
            if (!isset($dailyNet[$d])) $dailyNet[$d] = array('recv' => array(), 'pay' => array(), 'bank' => null, 'weekly_fund' => null);
            if (!isset($dailyNet[$d]['recv'][$bn])) $dailyNet[$d]['recv'][$bn] = 0;
            $dailyNet[$d]['recv'][$bn] += (float)$r['total_amount'];
        }
        foreach ($payRows as $r) {
            if (empty($r['payment_date'])) continue;
            $d = $r['payment_date'];
            $bn = !empty($r['branch_name']) ? $r['branch_name'] : '(未設定)';
            if (!isset($dailyNet[$d])) $dailyNet[$d] = array('recv' => array(), 'pay' => array(), 'bank' => null, 'weekly_fund' => null);
            if (!isset($dailyNet[$d]['pay'][$bn])) $dailyNet[$d]['pay'][$bn] = 0;
            $dailyNet[$d]['pay'][$bn] += (float)$r['branch_amount'];
        }
        // 銀行每日餘額：每帳戶取當日最後一筆餘額，無交易則沿用前日
        $stmt = $this->db->prepare("
            SELECT bank_account, transaction_date, balance, id
            FROM bank_transactions
            WHERE transaction_date BETWEEN ? AND ?
            ORDER BY bank_account, transaction_date, id
        ");
        $stmt->execute(array($yearStart, $yearEnd));
        $bankDailyRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 取得年初前各帳戶最新餘額作為期初
        $stmtInit = $this->db->prepare("
            SELECT bank_account, balance FROM bank_transactions
            WHERE transaction_date < ?
            ORDER BY bank_account, transaction_date DESC, id DESC
        ");
        $stmtInit->execute(array($yearStart));
        $acctBalance = array();
        while ($ri = $stmtInit->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($acctBalance[$ri['bank_account']])) {
                $acctBalance[$ri['bank_account']] = (float)$ri['balance'];
            }
        }

        // 每帳戶每日最後一筆餘額
        $acctDayBal = array(); // [account][date] = balance
        foreach ($bankDailyRaw as $r) {
            $acctDayBal[$r['bank_account']][$r['transaction_date']] = (float)$r['balance'];
        }

        // 收集所有有收付或銀行交易的日期
        $allDates = array_keys($dailyNet);
        foreach ($acctDayBal as $acct => $dates) {
            foreach (array_keys($dates) as $d) {
                if (!in_array($d, $allDates)) $allDates[] = $d;
            }
        }
        sort($allDates);

        // ── 計算零用金/備用金/現金每日累積餘額 ──
        // 期初（年初前）累積
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(income_amount - expense_amount),0) FROM petty_cash WHERE COALESCE(expense_date, entry_date) < ?");
        $stmt->execute(array($yearStart));
        $pettyAcc = (float)$stmt->fetchColumn();

        $stmt = $this->db->prepare("SELECT COALESCE(SUM(income_amount - expense_amount),0) FROM reserve_fund WHERE COALESCE(expense_date, entry_date) < ?");
        $stmt->execute(array($yearStart));
        $reserveAcc = (float)$stmt->fetchColumn();

        $stmt = $this->db->prepare("SELECT COALESCE(SUM(income_amount - expense_amount),0) FROM cash_details WHERE COALESCE(transaction_date, register_date) < ?");
        $stmt->execute(array($yearStart));
        $cashAcc = (float)$stmt->fetchColumn();

        // 當年每日變動
        $pettyByDate = array();
        $stmt = $this->db->prepare("SELECT COALESCE(expense_date, entry_date) AS d, SUM(income_amount - expense_amount) AS net FROM petty_cash WHERE COALESCE(expense_date, entry_date) BETWEEN ? AND ? GROUP BY d");
        $stmt->execute(array($yearStart, $yearEnd));
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $pettyByDate[$r['d']] = (float)$r['net']; }

        $reserveByDate = array();
        $stmt = $this->db->prepare("SELECT COALESCE(expense_date, entry_date) AS d, SUM(income_amount - expense_amount) AS net FROM reserve_fund WHERE COALESCE(expense_date, entry_date) BETWEEN ? AND ? GROUP BY d");
        $stmt->execute(array($yearStart, $yearEnd));
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $reserveByDate[$r['d']] = (float)$r['net']; }

        $cashByDate = array();
        $stmt = $this->db->prepare("SELECT COALESCE(transaction_date, register_date) AS d, SUM(income_amount - expense_amount) AS net FROM cash_details WHERE COALESCE(transaction_date, register_date) BETWEEN ? AND ? GROUP BY d");
        $stmt->execute(array($yearStart, $yearEnd));
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $cashByDate[$r['d']] = (float)$r['net']; }

        // 把零用金/備用金/現金的所有交易日期也加入 allDates（避免遺漏）
        foreach (array_keys($pettyByDate) as $d) if (!in_array($d, $allDates)) $allDates[] = $d;
        foreach (array_keys($reserveByDate) as $d) if (!in_array($d, $allDates)) $allDates[] = $d;
        foreach (array_keys($cashByDate) as $d) if (!in_array($d, $allDates)) $allDates[] = $d;
        sort($allDates);

        // 逐日計算所有帳戶餘額合計 + 公司資金總和
        foreach ($allDates as $d) {
            // 更新各帳戶當日餘額
            foreach ($acctDayBal as $acct => $dates) {
                if (isset($dates[$d])) {
                    $acctBalance[$acct] = $dates[$d];
                }
            }
            $dayBankTotal = 0;
            foreach ($acctBalance as $bal) {
                $dayBankTotal += $bal;
            }

            // 累積零用金/備用金/現金
            if (isset($pettyByDate[$d])) $pettyAcc += $pettyByDate[$d];
            if (isset($reserveByDate[$d])) $reserveAcc += $reserveByDate[$d];
            if (isset($cashByDate[$d])) $cashAcc += $cashByDate[$d];

            if (!isset($dailyNet[$d])) {
                $dailyNet[$d] = array('recv' => array(), 'pay' => array(), 'bank' => null, 'weekly_fund' => null);
            }
            $dailyNet[$d]['bank'] = $dayBankTotal;
            // 公司資金總和 = 銀行 + 週轉金(常數) + 零用金 + 備用金 + 現金
            $dailyNet[$d]['total_fund'] = $dayBankTotal + $weeklyFund + $pettyAcc + $reserveAcc + $cashAcc;
        }
        ksort($dailyNet);

        $recvCount = count($recvRows);
        $payCount = count($payRows);

        // ── 付款主分類月別分析 ──
        $stmt = $this->db->prepare("
            SELECT main_category, payment_date, total_amount
            FROM payments_out
            WHERE payment_date BETWEEN ? AND ?
              AND main_category IS NOT NULL AND main_category != ''
            ORDER BY payment_date
        ");
        $stmt->execute(array($yearStart, $yearEnd));
        $catRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $payCategoryMonthly = array(); // [category][month] = amount
        foreach ($catRows as $r) {
            $cat = $r['main_category'];
            $m = substr($r['payment_date'], 0, 7);
            if (!isset($payCategoryMonthly[$cat])) $payCategoryMonthly[$cat] = array();
            if (!isset($payCategoryMonthly[$cat][$m])) $payCategoryMonthly[$cat][$m] = 0;
            $payCategoryMonthly[$cat][$m] += (float)$r['total_amount'];
        }

        // ── 付款廠商月別分析 ──
        $stmt = $this->db->prepare("
            SELECT vendor_name, payment_date, total_amount
            FROM payments_out
            WHERE payment_date BETWEEN ? AND ?
              AND vendor_name IS NOT NULL AND vendor_name != ''
            ORDER BY payment_date
        ");
        $stmt->execute(array($yearStart, $yearEnd));
        $vendorRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $payVendorMonthly = array(); // [vendor][month] = amount
        foreach ($vendorRows as $r) {
            $v = $r['vendor_name'];
            $m = substr($r['payment_date'], 0, 7);
            if (!isset($payVendorMonthly[$v])) $payVendorMonthly[$v] = array();
            if (!isset($payVendorMonthly[$v][$m])) $payVendorMonthly[$v][$m] = 0;
            $payVendorMonthly[$v][$m] += (float)$r['total_amount'];
        }

        return array(
            'year'           => $year,
            'months'         => $months,
            'recv_count'     => $recvCount,
            'pay_count'      => $payCount,
            'grand_total'    => $grandTotal,
            'bank_total'     => $bankTotal,
            'weekly_fund'    => $weeklyFund,
            'petty_total'    => $pettyTotal,
            'reserve_net'    => $reserveNet,
            'cash_net'       => $cashNet,
            'bank_latest'    => $bankLatest,
            'petty_balance'  => $pettyBalance,
            'reserve_in'     => $reserveIn,
            'reserve_out'    => $reserveOut,
            'cash_in'        => $cashIn,
            'cash_out'       => $cashOut,
            'recv_pivot'     => $recvPivot,
            'pay_pivot'      => $payPivot,
            'pay_pivot_branch_only' => $payPivotBranchOnly,
            'pay_excluded_summary'  => $excludedSummary,
            'cash_monthly'   => $cashMonthly,
            'daily_net'      => $dailyNet,
            'branches'       => $branches,
            'pay_category_monthly' => $payCategoryMonthly,
            'pay_vendor_monthly'   => $payVendorMonthly,
        );
    }

    /**
     * 業務個人分析
     */
    public function getSalesPersonalAnalysis($salesId, $year, array $branchIds)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $yearStart = $year . '-01-01';
        $yearEnd   = $year . '-12-31';
        $closedStatuses = array('已成交', '跨月成交', '現簽', '電話報價成交');

        // 取得業務員資訊
        $stmt = $this->db->prepare("SELECT u.id, u.real_name, b.name AS branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
        $stmt->execute(array($salesId));
        $salesInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$salesInfo) {
            return array('year' => $year, 'months' => array(), 'sales_info' => null);
        }

        // 月份列表 (1~12)
        $months = array();
        for ($i = 1; $i <= 12; $i++) {
            $months[] = $year . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
        }

        // 取出該業務員年度案件（進件以 created_at，成交以 deal_date）
        $stmt = $this->db->prepare("
            SELECT c.id, c.case_number, c.title, c.case_type, c.status, c.sub_status,
                   DATE_FORMAT(c.created_at, '%Y-%m') AS month,
                   DATE_FORMAT(c.deal_date, '%Y-%m') AS deal_month,
                   c.deal_date,
                   COALESCE(c.quote_amount, 0) AS quote_amount,
                   COALESCE(c.deal_amount, 0) AS deal_amount,
                   COALESCE(c.total_amount, 0) AS total_amount
            FROM cases c
            WHERE c.sales_id = ?
              AND c.branch_id IN ($ph)
              AND (c.created_at BETWEEN ? AND ? OR c.deal_date BETWEEN ? AND ?)
            ORDER BY c.created_at
        ");
        $stmt->execute(array_merge(array($salesId), $branchIds, array($yearStart, $yearEnd, $yearStart, $yearEnd)));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 初始化統計
        $monthlyEntry = array();
        $monthlyClosed = array();
        $monthlyQuoteAmt = array();
        $monthlyDealAmt = array();
        $caseTypeMonthlyEntry = array();
        $caseTypeMonthlyClosed = array();
        $caseList = array();

        foreach ($months as $m) {
            $monthlyEntry[$m] = 0;
            $monthlyClosed[$m] = 0;
            $monthlyQuoteAmt[$m] = 0;
            $monthlyDealAmt[$m] = 0;
        }

        foreach ($rows as $r) {
            $m = $r['month'];
            $dm = $r['deal_month'];
            $isClosed = in_array($r['sub_status'], $closedStatuses);
            $inYear = ($m >= $year . '-01' && $m <= $year . '-12');
            $ct = $r['case_type'] ? $r['case_type'] : 'new_install';

            // 進件數（以 created_at 月份）
            if ($inYear) {
                $monthlyEntry[$m]++;
                // 報價金額用進件月
                $monthlyQuoteAmt[$m] += (float)$r['quote_amount'];

                // 案別進件
                if (!isset($caseTypeMonthlyEntry[$ct])) $caseTypeMonthlyEntry[$ct] = array();
                if (!isset($caseTypeMonthlyEntry[$ct][$m])) $caseTypeMonthlyEntry[$ct][$m] = 0;
                $caseTypeMonthlyEntry[$ct][$m]++;
            }

            // 成交數（以 deal_date 月份）
            if ($isClosed && $dm && $dm >= $year . '-01' && $dm <= $year . '-12') {
                $monthlyClosed[$dm]++;
                $monthlyDealAmt[$dm] += (float)$r['total_amount'];

                // 案別成交
                if (!isset($caseTypeMonthlyClosed[$ct])) $caseTypeMonthlyClosed[$ct] = array();
                if (!isset($caseTypeMonthlyClosed[$ct][$dm])) $caseTypeMonthlyClosed[$ct][$dm] = 0;
                $caseTypeMonthlyClosed[$ct][$dm]++;
            }

            // 案件明細（只列年度內進件的）
            if ($inYear) {
                $caseList[] = array(
                    'case_number' => $r['case_number'],
                    'title' => $r['title'],
                    'case_type' => $ct,
                    'status' => $r['status'],
                    'sub_status' => $r['sub_status'],
                    'deal_amount' => $r['deal_amount'],
                    'deal_date' => $r['deal_date'],
                );
            }
        }

        // 收款金額（從 receipts 表）
        $monthlyReceipt = array();
        foreach ($months as $m) {
            $monthlyReceipt[$m] = 0;
        }
        $stmt = $this->db->prepare("
            SELECT DATE_FORMAT(r.deposit_date, '%Y-%m') AS month,
                   SUM(r.subtotal) AS amt
            FROM receipts r
            WHERE r.sales_id = ?
              AND r.status = '已收款'
              AND r.deposit_date BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(r.deposit_date, '%Y-%m')
        ");
        $stmt->execute(array($salesId, $yearStart, $yearEnd));
        while ($rr = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $monthlyReceipt[$rr['month']] = (float)$rr['amt'];
        }

        // 團隊平均（同分公司同年度所有業務）
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT c.id) AS total_entry
            FROM cases c
            WHERE c.branch_id IN ($ph)
              AND c.created_at BETWEEN ? AND ?
        ");
        $stmt->execute(array_merge($branchIds, array($yearStart, $yearEnd)));
        $teamTotalEntry = (int)$stmt->fetchColumn();

        $csph = implode(',', array_fill(0, count($closedStatuses), '?'));
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT c.id) AS total_closed
            FROM cases c
            WHERE c.branch_id IN ($ph)
              AND c.sub_status IN ($csph)
              AND c.deal_date BETWEEN ? AND ?
        ");
        $stmt->execute(array_merge($branchIds, $closedStatuses, array($yearStart, $yearEnd)));
        $teamTotalClosed = (int)$stmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(c.total_amount), 0) AS total_deal_amt
            FROM cases c
            WHERE c.branch_id IN ($ph)
              AND c.sub_status IN ($csph)
              AND c.deal_date BETWEEN ? AND ?
        ");
        $stmt->execute(array_merge($branchIds, $closedStatuses, array($yearStart, $yearEnd)));
        $teamTotalDealAmt = (float)$stmt->fetchColumn();

        // 業務人數
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT sales_id) AS cnt FROM cases
            WHERE branch_id IN ($ph) AND created_at BETWEEN ? AND ? AND sales_id IS NOT NULL
        ");
        $stmt->execute(array_merge($branchIds, array($yearStart, $yearEnd)));
        $salesCount = (int)$stmt->fetchColumn();
        if ($salesCount < 1) $salesCount = 1;

        $personalEntry = array_sum($monthlyEntry);
        $personalClosed = array_sum($monthlyClosed);
        $personalDealAmt = array_sum($monthlyDealAmt);

        $teamAvg = array(
            'entry' => round($teamTotalEntry / $salesCount, 1),
            'closed' => round($teamTotalClosed / $salesCount, 1),
            'deal_amount' => round($teamTotalDealAmt / $salesCount),
            'close_rate' => $teamTotalEntry > 0 ? round($teamTotalClosed / $teamTotalEntry * 100, 1) : 0,
            'sales_count' => $salesCount,
        );

        $personal = array(
            'entry' => $personalEntry,
            'closed' => $personalClosed,
            'deal_amount' => $personalDealAmt,
            'close_rate' => $personalEntry > 0 ? round($personalClosed / $personalEntry * 100, 1) : 0,
        );

        return array(
            'year' => $year,
            'months' => $months,
            'sales_info' => $salesInfo,
            'monthly_entry' => $monthlyEntry,
            'monthly_closed' => $monthlyClosed,
            'monthly_quote_amount' => $monthlyQuoteAmt,
            'monthly_deal_amount' => $monthlyDealAmt,
            'monthly_receipt' => $monthlyReceipt,
            'case_type_monthly_entry' => $caseTypeMonthlyEntry,
            'case_type_monthly_closed' => $caseTypeMonthlyClosed,
            'case_list' => $caseList,
            'personal' => $personal,
            'team_avg' => $teamAvg,
        );
    }

    /**
     * 月度排工統計
     */
    public function getMonthlyScheduleStats(array $branchIds, string $month): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $params = array_merge($branchIds, [$startDate, $endDate]);

        $stmt = $this->db->prepare("
            SELECT s.schedule_date, COUNT(DISTINCT s.id) AS schedule_count,
                   COUNT(DISTINCT se.user_id) AS engineer_count
            FROM schedules s
            JOIN cases c ON s.case_id = c.id
            LEFT JOIN schedule_engineers se ON se.schedule_id = s.id
            WHERE c.branch_id IN ($ph)
              AND s.schedule_date BETWEEN ? AND ?
              AND s.status != 'cancelled'
            GROUP BY s.schedule_date
            ORDER BY s.schedule_date
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 完工未收款 / 未完工案件清單
     * - 來源：cases.status IN ('unpaid', 'incomplete')
     * - 尾款金額：cases.balance_amount
     * - 排序：completion_date ASC，NULL 放最上方
     */
    public function getUnpaidCases(array $branchIds, $salesId = null): array
    {
        if (empty($branchIds)) return array('total' => 0, 'total_balance' => 0, 'rows' => array());
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = $branchIds;
        $extra = '';
        if ($salesId !== null && $salesId !== '' && $salesId !== 'all') {
            if ($salesId === '__empty__') {
                $extra = ' AND c.sales_id IS NULL';
            } else {
                $extra = ' AND c.sales_id = ?';
                $params[] = (int)$salesId;
            }
        }
        $sql = "
            SELECT c.id, c.case_number, c.title, c.customer_name,
                   c.created_at, c.completion_date, c.status,
                   c.balance_amount, c.total_amount, c.deal_amount,
                   b.name AS branch_name,
                   u.real_name AS sales_name
            FROM cases c
            LEFT JOIN branches b ON c.branch_id = b.id
            LEFT JOIN users u ON c.sales_id = u.id
            WHERE c.branch_id IN ($ph)
              AND c.status IN ('unpaid', 'incomplete')
              {$extra}
            ORDER BY (c.completion_date IS NULL) DESC, c.completion_date ASC, c.id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalBalance = 0;
        foreach ($rows as $r) $totalBalance += (int)$r['balance_amount'];

        return array(
            'total' => count($rows),
            'total_balance' => $totalBalance,
            'rows' => $rows,
        );
    }

    /**
     * 取得「完工未收款/未完工」報表中出現過的業務清單（給篩選下拉用）
     */
    public function getUnpaidCasesSalesOptions(array $branchIds): array
    {
        if (empty($branchIds)) return array();
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $sql = "
            SELECT DISTINCT u.id, u.real_name
            FROM cases c
            JOIN users u ON c.sales_id = u.id
            WHERE c.branch_id IN ($ph)
              AND c.status IN ('unpaid', 'incomplete')
              AND c.sales_id IS NOT NULL
            ORDER BY u.real_name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($branchIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 案件更新進度報表
     * - 顯示每個案件的最後一次狀態變更（上次狀態 vs 本次狀態）
     * - 沒有歷史變更紀錄者，「上次狀態」顯示「—」、最後更新時間用 cases.updated_at
     * - 排除：已完工結案 (status = 'closed' 或 sub_status = '已完工結案')
     * - 隱藏：當前使用者在 case_progress_hidden 標記的案件
     */
    public function getCaseProgressReport(array $branchIds, $userId = null)
    {
        if (empty($branchIds)) return array();
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = $branchIds;

        // 取每案最後一次變更紀錄（用子查詢取最大 changed_at）
        $sql = "
            SELECT c.id, c.case_number, c.created_at, c.title, c.updated_at,
                   c.status AS current_status, c.sub_status AS current_sub_status,
                   u.real_name AS sales_name, b.name AS branch_name,
                   h.old_status, h.new_status, h.old_sub_status, h.new_sub_status,
                   h.changed_at
            FROM cases c
            LEFT JOIN users u ON c.sales_id = u.id
            LEFT JOIN branches b ON c.branch_id = b.id
            LEFT JOIN (
                SELECT csh.*
                FROM case_status_history csh
                INNER JOIN (
                    SELECT case_id, MAX(changed_at) AS max_changed_at
                    FROM case_status_history
                    GROUP BY case_id
                ) m ON m.case_id = csh.case_id AND m.max_changed_at = csh.changed_at
            ) h ON h.case_id = c.id
            WHERE c.branch_id IN ($ph)
              AND (c.status IS NULL OR c.status <> 'closed')
              AND (c.sub_status IS NULL OR c.sub_status <> '已完工結案')
        ";

        if ($userId) {
            $sql .= " AND NOT EXISTS (
                SELECT 1 FROM case_progress_hidden ph
                WHERE ph.case_id = c.id AND ph.user_id = ?
            )";
            $params[] = (int)$userId;
        }

        $sql .= " ORDER BY COALESCE(h.changed_at, c.updated_at, c.created_at) DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 隱藏案件（僅在當前使用者的報表內隱藏）
     */
    public function hideCaseProgress($userId, $caseId)
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO case_progress_hidden (user_id, case_id, hidden_at) VALUES (?, ?, NOW())"
        );
        $stmt->execute(array((int)$userId, (int)$caseId));
    }
}
