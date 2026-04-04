<?php
/**
 * Bank Reconciliation Model
 * Match bank transactions with system transactions (receipts, payments).
 * PHP 7.2 compatible.
 */
class ReconciliationModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get unreconciled bank transactions
     * @param array $filters
     * @param int $limit
     * @return array
     */
    public function getUnreconciledBankTransactions($filters = array(), $limit = 200)
    {
        $where = 'bt.reconciled = 0';
        $params = array();

        if (!empty($filters['bank_account'])) {
            $where .= ' AND bt.bank_account = ?';
            $params[] = $filters['bank_account'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND bt.transaction_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND bt.transaction_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (bt.summary LIKE ? OR bt.description LIKE ? OR bt.remark LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $params[] = (int)$limit;
        $sql = "SELECT bt.* FROM bank_transactions bt WHERE {$where} ORDER BY bt.transaction_date DESC, bt.id DESC LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get unreconciled system transactions (receipts + payments_out)
     * @param array $filters
     * @param int $limit
     * @return array
     */
    public function getUnreconciledSystemTransactions($filters = array(), $limit = 200)
    {
        $results = array();

        // Receipts with status 已入帳 that are not matched
        $rWhere = "r.status = '已入帳'";
        $rParams = array();
        if (!empty($filters['date_from'])) {
            $rWhere .= ' AND r.deposit_date >= ?';
            $rParams[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $rWhere .= ' AND r.deposit_date <= ?';
            $rParams[] = $filters['date_to'];
        }

        $rSql = "SELECT r.id, 'receipt' AS sys_type, r.receipt_number AS sys_number,
                        r.deposit_date AS sys_date, r.total_amount AS sys_amount,
                        r.customer_name AS sys_party, r.receipt_method AS sys_method,
                        r.note AS sys_note
                 FROM receipts r
                 WHERE {$rWhere}
                   AND r.id NOT IN (SELECT COALESCE(reconciled_id, 0) FROM bank_transactions WHERE reconciled = 1 AND reconciled_type = 'receipt')
                 ORDER BY r.deposit_date DESC
                 LIMIT ?";
        $rParams[] = (int)$limit;
        $stmt = $this->db->prepare($rSql);
        $stmt->execute($rParams);
        $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Payments out with status 已付款
        $pWhere = "p.status = '已付款'";
        $pParams = array();
        if (!empty($filters['date_from'])) {
            $pWhere .= ' AND p.payment_date >= ?';
            $pParams[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $pWhere .= ' AND p.payment_date <= ?';
            $pParams[] = $filters['date_to'];
        }

        $pSql = "SELECT p.id, 'payment_out' AS sys_type, p.payment_number AS sys_number,
                        p.payment_date AS sys_date, p.total_amount AS sys_amount,
                        p.vendor_name AS sys_party, p.payment_method AS sys_method,
                        p.note AS sys_note
                 FROM payments_out p
                 WHERE {$pWhere}
                   AND p.id NOT IN (SELECT COALESCE(reconciled_id, 0) FROM bank_transactions WHERE reconciled = 1 AND reconciled_type = 'payment_out')
                 ORDER BY p.payment_date DESC
                 LIMIT ?";
        $pParams[] = (int)$limit;
        $stmt = $this->db->prepare($pSql);
        $stmt->execute($pParams);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = array_merge($receipts, $payments);
        // Sort by date descending
        usort($results, function($a, $b) {
            return strcmp($b['sys_date'], $a['sys_date']);
        });

        return array_slice($results, 0, $limit);
    }

    /**
     * Auto-match bank transactions with system transactions by amount + date proximity
     * @return int number of matches made
     */
    public function autoMatch()
    {
        $matchCount = 0;

        // Get all unreconciled bank transactions
        $bankTxs = $this->getUnreconciledBankTransactions(array(), 500);
        $sysTxs = $this->getUnreconciledSystemTransactions(array(), 500);

        if (empty($bankTxs) || empty($sysTxs)) return 0;

        // Index system transactions by amount for quick lookup
        $sysUsed = array();

        foreach ($bankTxs as $bt) {
            $btCredit = (float)$bt['credit_amount']; // bank credit = money in = receipt
            $btDebit = (float)$bt['debit_amount'];   // bank debit = money out = payment
            $btDate = $bt['transaction_date'];

            $bestMatch = null;
            $bestDateDiff = 999;

            foreach ($sysTxs as $idx => $st) {
                if (isset($sysUsed[$st['sys_type'] . '_' . $st['id']])) continue;

                $sysAmount = (float)$st['sys_amount'];

                // Match receipt with bank credit (money in)
                if ($st['sys_type'] === 'receipt' && $btCredit > 0 && abs($btCredit - $sysAmount) < 1) {
                    $dateDiff = abs(strtotime($btDate) - strtotime($st['sys_date'])) / 86400;
                    if ($dateDiff <= 7 && $dateDiff < $bestDateDiff) {
                        $bestDateDiff = $dateDiff;
                        $bestMatch = $st;
                    }
                }

                // Match payment with bank debit (money out)
                if ($st['sys_type'] === 'payment_out' && $btDebit > 0 && abs($btDebit - $sysAmount) < 1) {
                    $dateDiff = abs(strtotime($btDate) - strtotime($st['sys_date'])) / 86400;
                    if ($dateDiff <= 7 && $dateDiff < $bestDateDiff) {
                        $bestDateDiff = $dateDiff;
                        $bestMatch = $st;
                    }
                }
            }

            if ($bestMatch) {
                $this->matchTransaction($bt['id'], $bestMatch['sys_type'], $bestMatch['id']);
                $sysUsed[$bestMatch['sys_type'] . '_' . $bestMatch['id']] = true;
                $matchCount++;
            }
        }

        return $matchCount;
    }

    /**
     * Manually match a bank transaction with a system transaction
     * @param int $bankTxId
     * @param string $systemType receipt|payment_out|journal
     * @param int $systemId
     * @return bool
     */
    public function manualMatch($bankTxId, $systemType, $systemId)
    {
        return $this->matchTransaction($bankTxId, $systemType, $systemId);
    }

    /**
     * Internal: perform the match
     * @param int $bankTxId
     * @param string $systemType
     * @param int $systemId
     * @return bool
     */
    private function matchTransaction($bankTxId, $systemType, $systemId)
    {
        $stmt = $this->db->prepare("
            UPDATE bank_transactions
            SET reconciled = 1, reconciled_type = ?, reconciled_id = ?, reconciled_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute(array($systemType, $systemId, $bankTxId));
        return $stmt->rowCount() > 0;
    }

    /**
     * Unmatch a bank transaction
     * @param int $bankTxId
     * @return bool
     */
    public function unmatch($bankTxId)
    {
        $stmt = $this->db->prepare("
            UPDATE bank_transactions
            SET reconciled = 0, reconciled_type = NULL, reconciled_id = NULL, reconciled_at = NULL
            WHERE id = ?
        ");
        $stmt->execute(array($bankTxId));
        return $stmt->rowCount() > 0;
    }

    /**
     * Get reconciliation summary
     * @param string $bankAccount
     * @param string $asOfDate
     * @return array
     */
    public function getReconciliationSummary($bankAccount = '', $asOfDate = '')
    {
        if (empty($asOfDate)) {
            $asOfDate = date('Y-m-d');
        }

        // Bank balance: last balance from bank_transactions up to date
        $bWhere = 'transaction_date <= ?';
        $bParams = array($asOfDate);
        if ($bankAccount) {
            $bWhere .= ' AND bank_account = ?';
            $bParams[] = $bankAccount;
        }

        $bankBalStmt = $this->db->prepare("
            SELECT balance FROM bank_transactions
            WHERE {$bWhere}
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");
        $bankBalStmt->execute($bParams);
        $bankRow = $bankBalStmt->fetch(PDO::FETCH_ASSOC);
        $bankBalance = $bankRow ? (float)$bankRow['balance'] : 0;

        // Book balance: sum from journal entries for account 1102 (bank deposit)
        require_once __DIR__ . '/AccountingModel.php';
        $am = new AccountingModel();
        $bankAccount1102 = $am->getAccountByCode('1102');
        $bookBalance = 0;
        if ($bankAccount1102) {
            $bal = $am->getAccountBalance($bankAccount1102['id'], $asOfDate);
            $bookBalance = $bal['balance'];
        }

        // Unreconciled counts
        $unreconciledBank = $this->db->prepare("
            SELECT COUNT(*) as cnt FROM bank_transactions WHERE reconciled = 0 AND transaction_date <= ?
        ");
        $unreconciledBank->execute(array($asOfDate));
        $unreconciledBankCount = (int)$unreconciledBank->fetch(PDO::FETCH_ASSOC)['cnt'];

        // Reconciled counts
        $reconciledBank = $this->db->prepare("
            SELECT COUNT(*) as cnt FROM bank_transactions WHERE reconciled = 1 AND transaction_date <= ?
        ");
        $reconciledBank->execute(array($asOfDate));
        $reconciledBankCount = (int)$reconciledBank->fetch(PDO::FETCH_ASSOC)['cnt'];

        return array(
            'bank_balance'          => $bankBalance,
            'book_balance'          => $bookBalance,
            'difference'            => $bankBalance - $bookBalance,
            'unreconciled_count'    => $unreconciledBankCount,
            'reconciled_count'      => $reconciledBankCount,
            'as_of_date'            => $asOfDate,
        );
    }

    /**
     * Get reconciled bank transactions
     * @param array $filters
     * @param int $limit
     * @return array
     */
    public function getReconciledBankTransactions($filters = array(), $limit = 100)
    {
        $where = 'bt.reconciled = 1';
        $params = array();

        if (!empty($filters['date_from'])) {
            $where .= ' AND bt.transaction_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND bt.transaction_date <= ?';
            $params[] = $filters['date_to'];
        }

        $params[] = (int)$limit;
        $sql = "SELECT bt.* FROM bank_transactions bt WHERE {$where} ORDER BY bt.reconciled_at DESC LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get distinct bank account names
     * @return array
     */
    public function getBankAccountNames()
    {
        $stmt = $this->db->query("SELECT DISTINCT bank_account FROM bank_transactions WHERE bank_account IS NOT NULL AND bank_account != '' ORDER BY bank_account");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
