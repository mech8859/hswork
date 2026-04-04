<?php
/**
 * Financial Statements Report Model
 * Income Statement, Balance Sheet, Cash Flow Statement
 * PHP 7.2 compatible.
 */
require_once __DIR__ . '/AccountingModel.php';

class ReportModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get Income Statement (損益表)
     * Revenue - Cost - Expense = Net Income
     * @param string $startDate
     * @param string $endDate
     * @param int|null $costCenterId
     * @return array
     */
    public function getIncomeStatement($startDate, $endDate, $costCenterId = null)
    {
        $ccWhere = '';
        $params = array('posted', $startDate, $endDate);
        if ($costCenterId) {
            $ccWhere = ' AND jl.cost_center_id = ?';
            $params[] = $costCenterId;
        }

        // Get all revenue, expense accounts with their totals
        $sql = "SELECT coa.id, coa.code, coa.name, coa.account_type, coa.normal_balance, coa.level1,
                       COALESCE(SUM(jl.debit_amount), 0) AS total_debit,
                       COALESCE(SUM(jl.credit_amount), 0) AS total_credit
                FROM chart_of_accounts coa
                LEFT JOIN journal_entry_lines jl ON jl.account_id = coa.id
                LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id
                    AND je.status = ?
                    AND je.voucher_date >= ?
                    AND je.voucher_date <= ?
                WHERE coa.is_active = 1
                  AND coa.account_type IN ('revenue', 'expense')
                  {$ccWhere}
                GROUP BY coa.id, coa.code, coa.name, coa.account_type, coa.normal_balance, coa.level1
                HAVING total_debit > 0 OR total_credit > 0
                ORDER BY coa.code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $revenue = array();
        $expense = array();
        $totalRevenue = 0;
        $totalExpense = 0;

        foreach ($rows as $row) {
            $debit = (float)$row['total_debit'];
            $credit = (float)$row['total_credit'];

            if ($row['account_type'] === 'revenue') {
                // Revenue: normal balance is credit
                $amount = $credit - $debit;
                $row['amount'] = $amount;
                $revenue[] = $row;
                $totalRevenue += $amount;
            } else {
                // Expense: normal balance is debit
                $amount = $debit - $credit;
                $row['amount'] = $amount;
                $expense[] = $row;
                $totalExpense += $amount;
            }
        }

        $netIncome = $totalRevenue - $totalExpense;

        return array(
            'revenue'       => $revenue,
            'expense'       => $expense,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_income'    => $netIncome,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
        );
    }

    /**
     * Get Balance Sheet (資產負債表)
     * Assets = Liabilities + Equity
     * @param string $asOfDate
     * @param int|null $costCenterId
     * @return array
     */
    public function getBalanceSheet($asOfDate, $costCenterId = null)
    {
        $ccWhere = '';
        $params = array('posted', $asOfDate);
        if ($costCenterId) {
            $ccWhere = ' AND jl.cost_center_id = ?';
            $params[] = $costCenterId;
        }

        $sql = "SELECT coa.id, coa.code, coa.name, coa.account_type, coa.normal_balance, coa.level1,
                       COALESCE(SUM(jl.debit_amount), 0) AS total_debit,
                       COALESCE(SUM(jl.credit_amount), 0) AS total_credit
                FROM chart_of_accounts coa
                LEFT JOIN journal_entry_lines jl ON jl.account_id = coa.id
                LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id
                    AND je.status = ?
                    AND je.voucher_date <= ?
                WHERE coa.is_active = 1
                  AND coa.account_type IN ('asset', 'liability', 'equity')
                  {$ccWhere}
                GROUP BY coa.id, coa.code, coa.name, coa.account_type, coa.normal_balance, coa.level1
                HAVING total_debit > 0 OR total_credit > 0
                ORDER BY coa.code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $assets = array();
        $liabilities = array();
        $equity = array();
        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        foreach ($rows as $row) {
            $debit = (float)$row['total_debit'];
            $credit = (float)$row['total_credit'];

            if ($row['account_type'] === 'asset') {
                $amount = $debit - $credit; // Assets: normal debit
                $row['amount'] = $amount;
                $assets[] = $row;
                $totalAssets += $amount;
            } elseif ($row['account_type'] === 'liability') {
                $amount = $credit - $debit; // Liabilities: normal credit
                $row['amount'] = $amount;
                $liabilities[] = $row;
                $totalLiabilities += $amount;
            } else {
                $amount = $credit - $debit; // Equity: normal credit
                $row['amount'] = $amount;
                $equity[] = $row;
                $totalEquity += $amount;
            }
        }

        // Add retained earnings (net income to date) to equity
        $retainedEarnings = $this->getRetainedEarnings($asOfDate, $costCenterId);
        if (abs($retainedEarnings) > 0.01) {
            $equity[] = array(
                'id' => 0,
                'code' => '3199',
                'name' => '本期損益',
                'account_type' => 'equity',
                'normal_balance' => 'credit',
                'level1' => '權益',
                'total_debit' => 0,
                'total_credit' => 0,
                'amount' => $retainedEarnings,
            );
            $totalEquity += $retainedEarnings;
        }

        return array(
            'assets'            => $assets,
            'liabilities'       => $liabilities,
            'equity'            => $equity,
            'total_assets'      => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity'      => $totalEquity,
            'total_le'          => $totalLiabilities + $totalEquity,
            'as_of_date'        => $asOfDate,
            'balanced'          => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
        );
    }

    /**
     * Calculate retained earnings (revenue - expense up to date)
     * @param string $asOfDate
     * @param int|null $costCenterId
     * @return float
     */
    private function getRetainedEarnings($asOfDate, $costCenterId = null)
    {
        // Get current year start
        $yearStart = date('Y', strtotime($asOfDate)) . '-01-01';
        $is = $this->getIncomeStatement($yearStart, $asOfDate, $costCenterId);
        return $is['net_income'];
    }

    /**
     * Get Cash Flow Statement (現金流量表) - simplified
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getCashFlowStatement($startDate, $endDate)
    {
        $am = new AccountingModel();

        // Cash accounts: 1100 (Cash) + 1102 (Bank)
        $cashAccounts = array('1100', '1102', '1103');
        $openingCash = 0;
        $closingCash = 0;

        foreach ($cashAccounts as $code) {
            $account = $am->getAccountByCode($code);
            if ($account) {
                $openBal = $am->getOpeningBalance($account['id'], $startDate);
                $closeBal = $am->getAccountBalance($account['id'], $endDate);
                $openingCash += $openBal;
                $closingCash += $closeBal['balance'];
            }
        }

        // Operating: Revenue type journal entries affecting cash
        $operatingStmt = $this->db->prepare("
            SELECT COALESCE(SUM(jl.debit_amount), 0) - COALESCE(SUM(jl.credit_amount), 0) AS net_operating
            FROM journal_entry_lines jl
            JOIN journal_entries je ON jl.journal_entry_id = je.id
            JOIN chart_of_accounts coa ON jl.account_id = coa.id
            WHERE je.status = 'posted'
              AND je.voucher_date >= ? AND je.voucher_date <= ?
              AND coa.code IN ('1100', '1102', '1103')
              AND je.voucher_type IN ('receipt', 'payment')
        ");
        $operatingStmt->execute(array($startDate, $endDate));
        $operating = (float)$operatingStmt->fetch(PDO::FETCH_ASSOC)['net_operating'];

        $netChange = $closingCash - $openingCash;

        return array(
            'opening_cash'  => $openingCash,
            'closing_cash'  => $closingCash,
            'net_change'    => $netChange,
            'operating'     => $operating,
            'investing'     => 0,
            'financing'     => 0,
            'other'         => $netChange - $operating,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
        );
    }
}
