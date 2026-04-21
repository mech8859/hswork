<?php
/**
 * ERP Accounting Model
 * Chart of Accounts, Cost Centers, Journal Entries, General Ledger
 */
class AccountingModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ============================================================
    // Chart of Accounts
    // ============================================================

    /**
     * Get accounts as tree structure
     * @param int|null $parentId
     * @return array
     */
    public function getAccounts($parentId = null)
    {
        if ($parentId === null) {
            // Get top-level accounts grouped by account_type
            $stmt = $this->db->query(
                "SELECT *, account_code AS code, account_name AS name FROM chart_of_accounts WHERE is_active = 1 ORDER BY sort_order, account_code"
            );
        } else {
            $stmt = $this->db->prepare(
                "SELECT *, account_code AS code, account_name AS name FROM chart_of_accounts WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order, account_code"
            );
            $stmt->execute(array($parentId));
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get flat list for dropdowns (only detail accounts by default)
     * @param bool $detailOnly
     * @return array
     */
    public function getAccountsFlat($detailOnly = true)
    {
        $where = 'is_active = 1';
        if ($detailOnly) {
            $where .= ' AND is_detail = 1';
        }
        $stmt = $this->db->query(
            "SELECT id, account_code AS code, account_name AS name, account_type, normal_balance, level, offset_type, relate_type, type_num FROM chart_of_accounts WHERE {$where} ORDER BY account_code"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get account by ID
     * @param int $id
     * @return array|false
     */
    public function getAccountById($id)
    {
        $stmt = $this->db->prepare("SELECT *, account_code AS code, account_name AS name FROM chart_of_accounts WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get account by code
     * @param string $code
     * @return array|false
     */
    public function getAccountByCode($code)
    {
        $stmt = $this->db->prepare("SELECT *, account_code AS code, account_name AS name FROM chart_of_accounts WHERE account_code = ?");
        $stmt->execute(array($code));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new account
     * @param array $data
     * @return int
     */
    public function createAccount($data)
    {
        $sql = "INSERT INTO chart_of_accounts (code, name, account_code, account_name, account_type, parent_id, level, is_detail, normal_balance, description, sort_order, offset_type, tx_type, type_num, type_name_full, cat_code, cat_name, attr, project_calc, dept_calc, relate_type, internal_flag, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            $data['code'], $data['name'], $data['code'], $data['name'],
            $data['account_type'],
            !empty($data['parent_id']) ? $data['parent_id'] : null,
            isset($data['level']) ? $data['level'] : 1,
            isset($data['is_detail']) ? $data['is_detail'] : 1,
            isset($data['normal_balance']) ? $data['normal_balance'] : 'debit',
            isset($data['description']) ? $data['description'] : '',
            isset($data['sort_order']) ? $data['sort_order'] : 0,
            isset($data['offset_type']) ? $data['offset_type'] : '',
            isset($data['tx_type']) ? $data['tx_type'] : '',
            isset($data['type_num']) ? $data['type_num'] : '',
            isset($data['type_name_full']) ? $data['type_name_full'] : '',
            isset($data['cat_code']) ? $data['cat_code'] : '',
            isset($data['cat_name']) ? $data['cat_name'] : '',
            isset($data['attr']) ? $data['attr'] : '',
            isset($data['project_calc']) ? $data['project_calc'] : '',
            isset($data['dept_calc']) ? $data['dept_calc'] : '',
            isset($data['relate_type']) ? $data['relate_type'] : '',
            isset($data['internal_flag']) ? $data['internal_flag'] : '',
            isset($data['is_active']) ? $data['is_active'] : 1,
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update account
     * @param int $id
     * @param array $data
     */
    public function updateAccount($id, $data)
    {
        $sql = "UPDATE chart_of_accounts SET code=?, name=?, account_code=?, account_name=?, account_type=?, parent_id=?, level=?, is_detail=?, normal_balance=?, description=?, sort_order=?, offset_type=?, tx_type=?, type_num=?, type_name_full=?, cat_code=?, cat_name=?, attr=?, project_calc=?, dept_calc=?, relate_type=?, internal_flag=?, is_active=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            $data['code'], $data['name'], $data['code'], $data['name'],
            $data['account_type'],
            !empty($data['parent_id']) ? $data['parent_id'] : null,
            isset($data['level']) ? $data['level'] : 1,
            isset($data['is_detail']) ? $data['is_detail'] : 1,
            isset($data['normal_balance']) ? $data['normal_balance'] : 'debit',
            isset($data['description']) ? $data['description'] : '',
            isset($data['sort_order']) ? $data['sort_order'] : 0,
            isset($data['offset_type']) ? $data['offset_type'] : '',
            isset($data['tx_type']) ? $data['tx_type'] : '',
            isset($data['type_num']) ? $data['type_num'] : '',
            isset($data['type_name_full']) ? $data['type_name_full'] : '',
            isset($data['cat_code']) ? $data['cat_code'] : '',
            isset($data['cat_name']) ? $data['cat_name'] : '',
            isset($data['attr']) ? $data['attr'] : '',
            isset($data['project_calc']) ? $data['project_calc'] : '',
            isset($data['dept_calc']) ? $data['dept_calc'] : '',
            isset($data['relate_type']) ? $data['relate_type'] : '',
            isset($data['internal_flag']) ? $data['internal_flag'] : '',
            isset($data['is_active']) ? $data['is_active'] : 1,
            $id,
        ));
    }

    /**
     * Toggle account active/inactive
     * @param int $id
     */
    public function toggleAccount($id)
    {
        $this->db->prepare("UPDATE chart_of_accounts SET is_active = IF(is_active=1,0,1) WHERE id = ?")->execute(array($id));
    }

    /**
     * Get account type options
     * @return array
     */
    public static function accountTypeOptions()
    {
        return array(
            'asset'     => '資產',
            'liability' => '負債',
            'equity'    => '權益',
            'revenue'   => '收入',
            'expense'   => '費用',
        );
    }

    /**
     * Get accounts grouped by type for tree display
     * @param bool $showInactive
     * @return array
     */
    public function getAccountsTree($showInactive = false)
    {
        // $showInactive=true → 只顯示停用；false → 只顯示啟用
        $where = $showInactive ? 'c.is_active = 0' : 'c.is_active = 1';
        $stmt = $this->db->query("SELECT c.*, c.account_code AS code, c.account_name AS name, p.account_code AS parent_code FROM chart_of_accounts c LEFT JOIN chart_of_accounts p ON c.parent_id = p.id WHERE {$where} ORDER BY c.account_code");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // Cost Centers
    // ============================================================

    /**
     * Get active cost centers
     * @return array
     */
    public function getCostCenters()
    {
        return $this->db->query(
            "SELECT cc.*, b.name as branch_name FROM cost_centers cc LEFT JOIN branches b ON cc.branch_id = b.id WHERE cc.is_active = 1 ORDER BY cc.code"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all cost centers (including inactive)
     * @return array
     */
    public function getAllCostCenters()
    {
        return $this->db->query(
            "SELECT cc.*, b.name as branch_name FROM cost_centers cc LEFT JOIN branches b ON cc.branch_id = b.id ORDER BY cc.code"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get cost center by ID
     * @param int $id
     * @return array|false
     */
    public function getCostCenterById($id)
    {
        $stmt = $this->db->prepare("SELECT cc.*, b.name as branch_name FROM cost_centers cc LEFT JOIN branches b ON cc.branch_id = b.id WHERE cc.id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create cost center
     * @param array $data
     * @return int
     */
    public function createCostCenter($data)
    {
        $stmt = $this->db->prepare("INSERT INTO cost_centers (code, name, type, branch_id, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(array(
            $data['code'],
            $data['name'],
            isset($data['type']) ? $data['type'] : 'branch',
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            isset($data['is_active']) ? $data['is_active'] : 1,
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update cost center
     * @param int $id
     * @param array $data
     */
    public function updateCostCenter($id, $data)
    {
        $stmt = $this->db->prepare("UPDATE cost_centers SET code = ?, name = ?, type = ?, branch_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute(array(
            $data['code'],
            $data['name'],
            isset($data['type']) ? $data['type'] : 'branch',
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            isset($data['is_active']) ? $data['is_active'] : 1,
            $id,
        ));
    }

    /**
     * Cost center type options
     * @return array
     */
    public static function costCenterTypeOptions()
    {
        return array(
            'branch'     => '分處',
            'project'    => '專案',
            'department' => '部門',
        );
    }

    // ============================================================
    // Journal Entries
    // ============================================================

    /**
     * Get journal entries list with filters
     * @param array $filters
     * @param int $limit
     * @return array
     */
    /**
     * 建 journal_entries 的 WHERE + params（給 getJournalEntries / countJournalEntries 共用）
     */
    private function buildJournalWhere($filters)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['status'])) {
            $where .= ' AND je.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['voucher_type'])) {
            $where .= ' AND je.voucher_type = ?';
            $params[] = $filters['voucher_type'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND je.voucher_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND je.voucher_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['keyword'])) {
            // 搜傳票號 / 描述 / 分錄摘要 / 會計科目代碼或名稱
            $where .= ' AND (
                je.voucher_number LIKE ?
                OR je.description LIKE ?
                OR EXISTS(
                    SELECT 1 FROM journal_entry_lines jl
                    LEFT JOIN chart_of_accounts coa ON jl.account_id = coa.id
                    WHERE jl.journal_entry_id = je.id
                      AND (jl.description LIKE ? OR coa.code LIKE ? OR coa.name LIKE ?)
                )
            )';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['source_module'])) {
            $where .= ' AND je.source_module = ?';
            $params[] = $filters['source_module'];
        }
        if (!empty($filters['created_by'])) {
            $where .= ' AND je.created_by = ?';
            $params[] = (int)$filters['created_by'];
        }
        return array($where, $params);
    }

    public function getJournalEntries($filters = array(), $limit = 100, $offset = 0)
    {
        list($where, $params) = $this->buildJournalWhere($filters);

        $sort = (isset($filters['sort']) && $filters['sort'] === 'asc') ? 'ASC' : 'DESC';

        $params[] = (int)$offset;
        $params[] = (int)$limit;
        $sql = "SELECT je.*, u.real_name as created_by_name, up.real_name as posted_by_name
                FROM journal_entries je
                LEFT JOIN users u ON je.created_by = u.id
                LEFT JOIN users up ON je.posted_by = up.id
                WHERE {$where}
                ORDER BY je.voucher_date {$sort}, je.id {$sort}
                LIMIT ?, ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得曾建立過傳票的使用者清單（for filter 下拉）
     */
    public function getJournalCreators()
    {
        $sql = "SELECT DISTINCT u.id, u.real_name
                FROM journal_entries je
                JOIN users u ON u.id = je.created_by
                WHERE je.created_by IS NOT NULL
                ORDER BY u.real_name";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countJournalEntries($filters = array())
    {
        list($where, $params) = $this->buildJournalWhere($filters);
        $sql = "SELECT COUNT(*) FROM journal_entries je WHERE {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get journal entry with lines
     * @param int $id
     * @return array|false
     */
    public function getJournalEntryById($id)
    {
        $stmt = $this->db->prepare(
            "SELECT je.*, u.real_name as created_by_name, up.real_name as posted_by_name, uv.real_name as voided_by_name
             FROM journal_entries je
             LEFT JOIN users u ON je.created_by = u.id
             LEFT JOIN users up ON je.posted_by = up.id
             LEFT JOIN users uv ON je.voided_by = uv.id
             WHERE je.id = ?"
        );
        $stmt->execute(array($id));
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entry) return false;

        // Load lines
        // relation_display_code: 廠商顯示 vendor_code (B-XXXX)，其他類型 fallback relation_id
        $lineStmt = $this->db->prepare(
            "SELECT jl.id, jl.journal_entry_id, jl.account_id, jl.cost_center_id,
                    jl.relation_type, jl.relation_id, jl.relation_name,
                    jl.offset_flag, jl.offset_amount, jl.offset_ref_id, jl.offset_ledger_id,
                    CAST(jl.debit_amount AS DECIMAL(12,0)) AS debit_amount,
                    CAST(jl.credit_amount AS DECIMAL(12,0)) AS credit_amount,
                    jl.description, jl.sort_order,
                    coa.account_code AS account_code, coa.account_name AS account_name,
                    cc.name AS cost_center_name,
                    ol.voucher_number AS offset_voucher_number,
                    ol.relation_name AS offset_relation_name,
                    CASE WHEN jl.relation_type = 'vendor' THEN v.vendor_code ELSE NULL END AS relation_display_code
             FROM journal_entry_lines jl
             LEFT JOIN chart_of_accounts coa ON jl.account_id = coa.id
             LEFT JOIN cost_centers cc ON jl.cost_center_id = cc.id
             LEFT JOIN offset_ledger ol ON jl.offset_ledger_id = ol.id
             LEFT JOIN vendors v ON jl.relation_type = 'vendor' AND v.id = jl.relation_id
             WHERE jl.journal_entry_id = ?
             ORDER BY jl.sort_order, jl.id"
        );
        $lineStmt->execute(array($id));
        $entry['lines'] = $lineStmt->fetchAll(PDO::FETCH_ASSOC);

        return $entry;
    }

    /**
     * Create journal entry with lines
     * Validates debit = credit balance
     * @param array $data
     * @return int
     * @throws Exception
     */
    public function createJournalEntry($data)
    {
        // Validate lines
        if (empty($data['lines']) || !is_array($data['lines'])) {
            throw new Exception('Please add at least one journal line');
        }

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($data['lines'] as $line) {
            $totalDebit += (float)$line['debit_amount'];
            $totalCredit += (float)$line['credit_amount'];
        }

        // Balance check (allow small rounding difference)
        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new Exception('Debit and Credit totals must be equal. Debit: ' . number_format($totalDebit, 2) . ', Credit: ' . number_format($totalCredit, 2));
        }

        if ($totalDebit <= 0) {
            throw new Exception('Total amount must be greater than zero');
        }

        $this->db->beginTransaction();
        try {
            $voucherNumber = generate_doc_number('journal_entries', isset($data['voucher_date']) ? $data['voucher_date'] : null);

            $stmt = $this->db->prepare(
                "INSERT INTO journal_entries (voucher_number, voucher_date, voucher_type, description, source_module, source_id, status, total_debit, total_credit, attachment, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, NOW())"
            );
            $stmt->execute(array(
                $voucherNumber,
                $data['voucher_date'],
                isset($data['voucher_type']) ? $data['voucher_type'] : 'general',
                isset($data['description']) ? $data['description'] : '',
                isset($data['source_module']) ? $data['source_module'] : 'manual',
                isset($data['source_id']) ? $data['source_id'] : null,
                $totalDebit,
                $totalCredit,
                isset($data['attachment']) ? $data['attachment'] : null,
                $data['created_by'],
            ));
            $journalId = (int)$this->db->lastInsertId();

            // Insert lines
            $lineStmt = $this->db->prepare(
                "INSERT INTO journal_entry_lines (journal_entry_id, account_id, cost_center_id, debit_amount, credit_amount, description, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $sortOrder = 0;
            foreach ($data['lines'] as $line) {
                $debit = (float)$line['debit_amount'];
                $credit = (float)$line['credit_amount'];
                if ($debit == 0 && $credit == 0) continue;

                $ccId = !empty($line['cost_center_id']) ? (int)$line['cost_center_id'] : null;
                $relType = !empty($line['relation_type']) ? $line['relation_type'] : null;
                $relId = !empty($line['relation_id']) ? (int)$line['relation_id'] : null;
                $relName = !empty($line['relation_name']) ? $line['relation_name'] : null;

                $this->db->prepare(
                    "INSERT INTO journal_entry_lines (journal_entry_id, account_id, cost_center_id, relation_type, relation_id, relation_name, offset_flag, offset_amount, offset_ledger_id, debit_amount, credit_amount, description, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute(array(
                    $journalId,
                    (int)$line['account_id'],
                    $ccId,
                    $relType,
                    $relId,
                    $relName,
                    isset($line['offset_flag']) ? (int)$line['offset_flag'] : 0,
                    isset($line['offset_amount']) ? (float)$line['offset_amount'] : 0,
                    !empty($line['offset_ledger_id']) ? (int)$line['offset_ledger_id'] : null,
                    $debit,
                    $credit,
                    isset($line['description']) ? $line['description'] : '',
                    $sortOrder++,
                ));
            }

            $this->db->commit();
            return $journalId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update journal entry (only draft)
     * @param int $id
     * @param array $data
     * @throws Exception
     */
    public function updateJournalEntry($id, $data)
    {
        $entry = $this->getJournalEntryById($id);
        if (!$entry) throw new Exception('Journal entry not found');
        if ($entry['status'] !== 'draft') throw new Exception('Only draft entries can be edited');

        if (empty($data['lines']) || !is_array($data['lines'])) {
            throw new Exception('Please add at least one journal line');
        }

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($data['lines'] as $line) {
            $totalDebit += (float)$line['debit_amount'];
            $totalCredit += (float)$line['credit_amount'];
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new Exception('Debit and Credit totals must be equal');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE journal_entries SET voucher_date = ?, voucher_type = ?, description = ?, total_debit = ?, total_credit = ?, attachment = ?, updated_by = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute(array(
                $data['voucher_date'],
                isset($data['voucher_type']) ? $data['voucher_type'] : 'general',
                isset($data['description']) ? $data['description'] : '',
                $totalDebit,
                $totalCredit,
                isset($data['attachment']) ? $data['attachment'] : null,
                $data['updated_by'],
                $id,
            ));

            // Delete old lines and re-insert
            $this->db->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute(array($id));

            $sortOrder = 0;
            foreach ($data['lines'] as $line) {
                $debit = (float)$line['debit_amount'];
                $credit = (float)$line['credit_amount'];
                if ($debit == 0 && $credit == 0) continue;

                $this->db->prepare(
                    "INSERT INTO journal_entry_lines (journal_entry_id, account_id, cost_center_id, relation_type, relation_id, relation_name, offset_flag, offset_amount, offset_ledger_id, debit_amount, credit_amount, description, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute(array(
                    $id,
                    (int)$line['account_id'],
                    !empty($line['cost_center_id']) ? (int)$line['cost_center_id'] : null,
                    !empty($line['relation_type']) ? $line['relation_type'] : null,
                    !empty($line['relation_id']) ? (int)$line['relation_id'] : null,
                    !empty($line['relation_name']) ? $line['relation_name'] : null,
                    isset($line['offset_flag']) ? (int)$line['offset_flag'] : 0,
                    isset($line['offset_amount']) ? (float)$line['offset_amount'] : 0,
                    !empty($line['offset_ledger_id']) ? (int)$line['offset_ledger_id'] : null,
                    $debit,
                    $credit,
                    isset($line['description']) ? $line['description'] : '',
                    $sortOrder++,
                ));
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Post journal entry
     * @param int $id
     * @param int $userId
     * @throws Exception
     */
    public function postJournalEntry($id, $userId)
    {
        $entry = $this->getJournalEntryById($id);
        if (!$entry) throw new Exception('Journal entry not found');
        if ($entry['status'] !== 'draft') throw new Exception('Only draft entries can be posted');
        if (empty($entry['lines'])) throw new Exception('Cannot post entry with no lines');

        $this->db->beginTransaction();
        try {
            // 過帳
            $this->db->prepare(
                "UPDATE journal_entries SET status = 'posted', posted_by = ?, posted_at = NOW() WHERE id = ?"
            )->execute(array($userId, $id));

            // 處理立沖記錄
            $this->processOffsetOnPost($entry);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 過帳時處理立沖記錄
     */
    private function processOffsetOnPost($entry)
    {
        foreach ($entry['lines'] as $line) {
            $flag = (int)$line['offset_flag'];
            if ($flag === 0) continue;

            $amt = max((float)$line['debit_amount'], (float)$line['credit_amount']);
            $direction = (float)$line['debit_amount'] > 0 ? 'debit' : 'credit';

            if ($flag === 1) {
                // 立帳：建立 offset_ledger 記錄
                $this->db->prepare("
                    INSERT INTO offset_ledger
                    (journal_entry_id, journal_line_id, account_id, cost_center_id,
                     relation_type, relation_id, relation_name,
                     voucher_date, voucher_number, direction,
                     original_amount, offset_total, remaining_amount, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'open')
                ")->execute(array(
                    $entry['id'],
                    $line['id'],
                    $line['account_id'],
                    $line['cost_center_id'],
                    $line['relation_type'],
                    $line['relation_id'],
                    $line['relation_name'],
                    $entry['voucher_date'],
                    $entry['voucher_number'],
                    $direction,
                    $amt,
                    $amt,
                ));

            } elseif ($flag === 2) {
                // 沖帳：更新對應的 offset_ledger
                $ledgerId = !empty($line['offset_ledger_id']) ? (int)$line['offset_ledger_id'] : 0;
                $offsetAmt = (float)$line['offset_amount'];

                if ($ledgerId > 0 && $offsetAmt > 0) {
                    // 驗證未沖額
                    $stmtCheck = $this->db->prepare("SELECT remaining_amount, voucher_number FROM offset_ledger WHERE id = ?");
                    $stmtCheck->execute(array($ledgerId));
                    $ledger = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                    if (!$ledger) {
                        throw new Exception('沖帳失敗：找不到對應的立帳記錄 (ID: ' . $ledgerId . ')');
                    }
                    if ((float)$ledger['remaining_amount'] <= 0) {
                        throw new Exception('沖帳失敗：立帳記錄 ' . $ledger['voucher_number'] . ' 已無餘額可沖');
                    }
                    if ($offsetAmt > (float)$ledger['remaining_amount']) {
                        throw new Exception('沖帳失敗：沖帳金額 ' . number_format($offsetAmt) . ' 超過未沖餘額 ' . number_format($ledger['remaining_amount']));
                    }

                    // 寫入沖帳明細
                    $this->db->prepare("
                        INSERT INTO offset_details
                        (ledger_id, journal_entry_id, journal_line_id, offset_amount, voucher_date, voucher_number)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute(array(
                        $ledgerId,
                        $entry['id'],
                        $line['id'],
                        $offsetAmt,
                        $entry['voucher_date'],
                        $entry['voucher_number'],
                    ));

                    // 更新立帳記錄
                    $this->db->prepare("
                        UPDATE offset_ledger SET
                            offset_total = offset_total + ?,
                            remaining_amount = remaining_amount - ?,
                            status = CASE
                                WHEN remaining_amount - ? <= 0 THEN 'closed'
                                ELSE 'partial'
                            END
                        WHERE id = ?
                    ")->execute(array($offsetAmt, $offsetAmt, $offsetAmt, $ledgerId));
                }
            }
        }
    }

    /**
     * Void journal entry (create reverse entry)
     * @param int $id
     * @param int $userId
     * @param string $reason
     * @return int new reverse entry id
     * @throws Exception
     */
    public function voidJournalEntry($id, $userId, $reason = '')
    {
        $entry = $this->getJournalEntryById($id);
        if (!$entry) throw new Exception('Journal entry not found');
        if ($entry['status'] !== 'posted') throw new Exception('Only posted entries can be voided');

        $this->db->beginTransaction();
        try {
            // 標記為作廢（不建立沖回傳票）
            $this->db->prepare(
                "UPDATE journal_entries SET status = 'voided', voided_by = ?, voided_at = NOW(), void_reason = ? WHERE id = ?"
            )->execute(array($userId, $reason, $id));

            // 反轉立沖記錄
            $this->reverseOffsetOnVoid($entry);

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 作廢時反轉立沖記錄
     */
    public function reverseOffsetPublic($entry)
    {
        $this->reverseOffsetOnVoid($entry);
    }

    private function reverseOffsetOnVoid($entry)
    {
        foreach ($entry['lines'] as $line) {
            $flag = (int)$line['offset_flag'];
            if ($flag === 0) continue;

            if ($flag === 1) {
                // 立帳被作廢：檢查是否有沖帳明細
                $ledger = $this->db->prepare("SELECT id, offset_total FROM offset_ledger WHERE journal_line_id = ?")->fetch(PDO::FETCH_ASSOC);
                // 注意：需要用 execute 才能 fetch
                $stmt = $this->db->prepare("SELECT id, offset_total FROM offset_ledger WHERE journal_line_id = ?");
                $stmt->execute(array($line['id']));
                $ledger = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($ledger) {
                    if ((float)$ledger['offset_total'] > 0) {
                        throw new Exception('此傳票包含已被沖帳的立帳記錄（已沖額 ' . number_format($ledger['offset_total']) . '），無法作廢。請先作廢沖帳傳票。');
                    }
                    // 無沖帳：直接刪除
                    $this->db->prepare("DELETE FROM offset_ledger WHERE id = ?")->execute(array($ledger['id']));
                }

            } elseif ($flag === 2) {
                // 沖帳被作廢：還原立帳的未沖額
                $ledgerId = !empty($line['offset_ledger_id']) ? (int)$line['offset_ledger_id'] : 0;
                if ($ledgerId > 0) {
                    // 找出沖帳明細
                    $stmt = $this->db->prepare("SELECT offset_amount FROM offset_details WHERE ledger_id = ? AND journal_line_id = ?");
                    $stmt->execute(array($ledgerId, $line['id']));
                    $detail = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($detail) {
                        $amt = (float)$detail['offset_amount'];
                        // 還原立帳記錄
                        $this->db->prepare("
                            UPDATE offset_ledger SET
                                offset_total = GREATEST(offset_total - ?, 0),
                                remaining_amount = remaining_amount + ?,
                                status = CASE
                                    WHEN offset_total - ? <= 0 THEN 'open'
                                    ELSE 'partial'
                                END
                            WHERE id = ?
                        ")->execute(array($amt, $amt, $amt, $ledgerId));

                        // 刪除沖帳明細
                        $this->db->prepare("DELETE FROM offset_details WHERE ledger_id = ? AND journal_line_id = ?")->execute(array($ledgerId, $line['id']));
                    }
                }
            }
        }
    }

    /**
     * Delete journal entry (only draft)
     * @param int $id
     * @throws Exception
     */
    public function deleteJournalEntry($id)
    {
        $entry = $this->getJournalEntryById($id);
        if (!$entry) throw new Exception('Journal entry not found');
        if ($entry['status'] !== 'draft') throw new Exception('Only draft entries can be deleted');

        $this->db->beginTransaction();
        try {
            // 刪除前反轉立沖記錄（以防草稿也有立沖資料）
            if (!empty($entry['lines'])) {
                foreach ($entry['lines'] as $line) {
                    $flag = (int)$line['offset_flag'];
                    if ($flag === 1) {
                        // 刪除立帳記錄
                        $stmt = $this->db->prepare("SELECT id, offset_total FROM offset_ledger WHERE journal_line_id = ?");
                        $stmt->execute(array($line['id']));
                        $ledger = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($ledger) {
                            if ((float)$ledger['offset_total'] > 0) {
                                throw new Exception('此傳票包含已被沖帳的立帳記錄（本次沖帳 ' . number_format($ledger['offset_total']) . '），無法刪除。請先刪除沖帳傳票。');
                            }
                            $this->db->prepare("DELETE FROM offset_ledger WHERE id = ?")->execute(array($ledger['id']));
                        }
                    } elseif ($flag === 2) {
                        // 還原立帳的未沖額
                        $ledgerId = !empty($line['offset_ledger_id']) ? (int)$line['offset_ledger_id'] : 0;
                        if ($ledgerId > 0) {
                            $stmt = $this->db->prepare("SELECT id, offset_amount FROM offset_details WHERE ledger_id = ? AND journal_line_id = ?");
                            $stmt->execute(array($ledgerId, $line['id']));
                            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($detail) {
                                $amt = (float)$detail['offset_amount'];
                                $this->db->prepare("
                                    UPDATE offset_ledger SET
                                        offset_total = offset_total - ?,
                                        remaining_amount = remaining_amount + ?,
                                        status = CASE
                                            WHEN remaining_amount + ? >= original_amount THEN 'open'
                                            ELSE 'partial'
                                        END
                                    WHERE id = ?
                                ")->execute(array($amt, $amt, $amt, $ledgerId));
                                $this->db->prepare("DELETE FROM offset_details WHERE id = ?")->execute(array($detail['id']));
                            }
                        }
                    }
                }
            }

            $this->db->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute(array($id));
            $this->db->prepare("DELETE FROM journal_entries WHERE id = ?")->execute(array($id));
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Generate next voucher number (preview only)
     * @return string
     */
    public function generateVoucherNumber($date = null)
    {
        return peek_next_doc_number('journal_entries', $date);
    }

    /**
     * Voucher type options
     * @return array
     */
    public static function voucherTypeOptions()
    {
        return array(
            'general'    => '一般傳票',
            'receipt'    => '收款傳票',
            'payment'    => '付款傳票',
            'transfer'   => '轉帳傳票',
            'adjustment' => '調整傳票',
        );
    }

    /**
     * Status options
     * @return array
     */
    public static function statusOptions()
    {
        return array(
            'draft'  => '草稿',
            'posted' => '已過帳',
            'voided' => '已作廢',
        );
    }

    // ============================================================
    // General Ledger
    // ============================================================

    /**
     * Get ledger entries for an account
     * @param int $accountId
     * @param string $startDate
     * @param string $endDate
     * @param int|null $costCenterId
     * @return array
     */
    public function getLedger($accountId, $startDate, $endDate, $costCenterId = null)
    {
        $where = 'je.status = ? AND jl.account_id = ? AND je.voucher_date >= ? AND je.voucher_date <= ?';
        $params = array('posted', $accountId, $startDate, $endDate);

        if ($costCenterId) {
            $where .= ' AND jl.cost_center_id = ?';
            $params[] = $costCenterId;
        }

        $sql = "SELECT jl.*, je.voucher_number, je.voucher_date, je.description as je_description, je.voucher_type,
                       coa.account_code, coa.account_name,
                       cc.name as cost_center_name
                FROM journal_entry_lines jl
                JOIN journal_entries je ON jl.journal_entry_id = je.id
                JOIN chart_of_accounts coa ON jl.account_id = coa.id
                LEFT JOIN cost_centers cc ON jl.cost_center_id = cc.id
                WHERE {$where}
                ORDER BY je.voucher_date, je.id, jl.sort_order";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get account balance as of a date
     * @param int $accountId
     * @param string $asOfDate
     * @param int|null $costCenterId
     * @return array [debit_total, credit_total, balance]
     */
    public function getAccountBalance($accountId, $asOfDate, $costCenterId = null)
    {
        $where = 'je.status = ? AND jl.account_id = ? AND je.voucher_date <= ?';
        $params = array('posted', $accountId, $asOfDate);

        if ($costCenterId) {
            $where .= ' AND jl.cost_center_id = ?';
            $params[] = $costCenterId;
        }

        $sql = "SELECT COALESCE(SUM(jl.debit_amount),0) as debit_total, COALESCE(SUM(jl.credit_amount),0) as credit_total
                FROM journal_entry_lines jl
                JOIN journal_entries je ON jl.journal_entry_id = je.id
                WHERE {$where}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get account info for normal_balance
        $account = $this->getAccountById($accountId);
        $debit = (float)$row['debit_total'];
        $credit = (float)$row['credit_total'];

        // Balance depends on normal balance side
        if ($account && $account['normal_balance'] === 'debit') {
            $balance = $debit - $credit;
        } else {
            $balance = $credit - $debit;
        }

        return array(
            'debit_total'  => $debit,
            'credit_total' => $credit,
            'balance'      => $balance,
            'normal_balance' => $account ? $account['normal_balance'] : 'debit',
        );
    }

    /**
     * Get opening balance for a period
     * @param int $accountId
     * @param string $startDate
     * @param int|null $costCenterId
     * @return float
     */
    public function getOpeningBalance($accountId, $startDate, $costCenterId = null)
    {
        $endDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
        $bal = $this->getAccountBalance($accountId, $endDate, $costCenterId);

        $account = $this->getAccountById($accountId);
        if ($account && $account['normal_balance'] === 'debit') {
            return $bal['debit_total'] - $bal['credit_total'];
        }
        return $bal['credit_total'] - $bal['debit_total'];
    }

    /**
     * Get trial balance as of a date
     * @param string $asOfDate
     * @param int|null $costCenterId
     * @return array
     */
    public function getTrialBalance($asOfDate, $costCenterId = null)
    {
        $ccWhere = '';
        $params = array('posted', $asOfDate);
        if ($costCenterId) {
            $ccWhere = ' AND jl.cost_center_id = ?';
            $params[] = $costCenterId;
        }

        $sql = "SELECT coa.id, coa.account_code AS code, coa.account_name AS name, coa.account_type, coa.normal_balance, coa.level1,
                       COALESCE(SUM(jl.debit_amount),0) as total_debit,
                       COALESCE(SUM(jl.credit_amount),0) as total_credit
                FROM chart_of_accounts coa
                LEFT JOIN journal_entry_lines jl ON jl.account_id = coa.id
                LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id AND je.status = ? AND je.voucher_date <= ?
                WHERE coa.is_active = 1{$ccWhere}
                GROUP BY coa.id, coa.account_code, coa.account_name, coa.account_type, coa.normal_balance, coa.level1
                HAVING total_debit > 0 OR total_credit > 0
                ORDER BY coa.account_code";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get journal entry count by status
     * @return array
     */
    public function getJournalStats()
    {
        $stmt = $this->db->query(
            "SELECT status, COUNT(*) as cnt FROM journal_entries GROUP BY status"
        );
        $stats = array('draft' => 0, 'posted' => 0, 'voided' => 0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[$row['status']] = (int)$row['cnt'];
        }
        return $stats;
    }

    /**
     * Get branches for forms
     * @return array
     */
    public function getBranches()
    {
        return $this->db->query("SELECT id, code, name FROM branches WHERE is_active = 1 ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 預算管理
    // ============================================================

    public function getBudgets($year, $costCenterId = null)
    {
        $where = 'b.year = ?';
        $params = array($year);
        if ($costCenterId) {
            $where .= ' AND b.cost_center_id = ?';
            $params[] = (int)$costCenterId;
        } else {
            $where .= ' AND b.cost_center_id IS NULL';
        }
        $stmt = $this->db->prepare("
            SELECT b.*, coa.account_code AS code, coa.account_name AS name, coa.account_type
            FROM budgets b
            JOIN chart_of_accounts coa ON b.account_id = coa.id
            WHERE $where
            ORDER BY coa.account_code, b.month
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveBudget($year, $month, $accountId, $costCenterId, $amount, $userId)
    {
        $ccId = $costCenterId ? (int)$costCenterId : null;
        $stmt = $this->db->prepare("
            INSERT INTO budgets (year, month, account_id, cost_center_id, amount, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_at = NOW()
        ");
        $stmt->execute(array($year, $month, (int)$accountId, $ccId, (float)$amount, $userId));
    }

    public function copyBudget($fromYear, $toYear, $costCenterId = null)
    {
        $ccWhere = $costCenterId ? 'AND cost_center_id = ?' : 'AND cost_center_id IS NULL';

        $sql = "INSERT IGNORE INTO budgets (year, month, account_id, cost_center_id, amount, created_by)
                SELECT ?, month, account_id, cost_center_id, amount, created_by
                FROM budgets WHERE year = ? $ccWhere";
        if ($costCenterId) {
            $this->db->prepare($sql)->execute(array($toYear, $fromYear, (int)$costCenterId));
        } else {
            $this->db->prepare($sql)->execute(array($toYear, $fromYear));
        }
    }

    // ============================================================
    // 財務報表查詢
    // ============================================================

    public function getIncomeStatement($dateFrom, $dateTo, $costCenterId = null)
    {
        $ccWhere = '';
        $params = array('posted', $dateFrom, $dateTo);
        if ($costCenterId) {
            $ccWhere = ' AND jl.cost_center_id = ?';
            $params[] = (int)$costCenterId;
        }

        $stmt = $this->db->prepare("
            SELECT coa.account_code AS code, coa.account_name AS name, coa.account_type, coa.normal_balance,
                   SUBSTRING(coa.account_code, 1, 1) AS code_prefix,
                   SUM(jl.debit_amount) AS total_debit,
                   SUM(jl.credit_amount) AS total_credit
            FROM journal_entry_lines jl
            JOIN journal_entries je ON jl.journal_entry_id = je.id
            JOIN chart_of_accounts coa ON jl.account_id = coa.id
            WHERE je.status = ? AND je.voucher_date >= ? AND je.voucher_date <= ? $ccWhere
              AND SUBSTRING(coa.account_code, 1, 1) IN ('4','5','6','7','8')
            GROUP BY coa.account_code, coa.account_name, coa.account_type, coa.normal_balance
            ORDER BY coa.account_code
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMonthlyIncomeStatement($year, $costCenterId = null)
    {
        $ccWhere = '';
        $params = array('posted', $year . '-01-01', $year . '-12-31');
        if ($costCenterId) {
            $ccWhere = ' AND jl.cost_center_id = ?';
            $params[] = (int)$costCenterId;
        }

        $stmt = $this->db->prepare("
            SELECT coa.account_code AS code, coa.account_name AS name, coa.account_type, coa.normal_balance,
                   SUBSTRING(coa.account_code, 1, 1) AS code_prefix,
                   MONTH(je.voucher_date) AS month,
                   SUM(jl.debit_amount) AS total_debit,
                   SUM(jl.credit_amount) AS total_credit
            FROM journal_entry_lines jl
            JOIN journal_entries je ON jl.journal_entry_id = je.id
            JOIN chart_of_accounts coa ON jl.account_id = coa.id
            WHERE je.status = ? AND je.voucher_date >= ? AND je.voucher_date <= ? $ccWhere
              AND SUBSTRING(coa.account_code, 1, 1) IN ('4','5','6','7','8')
            GROUP BY coa.account_code, coa.account_name, coa.account_type, coa.normal_balance, MONTH(je.voucher_date)
            ORDER BY coa.account_code, MONTH(je.voucher_date)
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBudgetSummary($year, $monthFrom, $monthTo, $costCenterId = null)
    {
        $ccWhere = $costCenterId ? 'AND b.cost_center_id = ?' : 'AND b.cost_center_id IS NULL';
        $params = array($year, $monthFrom, $monthTo);
        if ($costCenterId) $params[] = (int)$costCenterId;

        $stmt = $this->db->prepare("
            SELECT b.account_id, coa.account_code AS code, coa.account_name AS name, coa.account_type,
                   SUBSTRING(coa.account_code, 1, 1) AS code_prefix,
                   SUM(b.amount) AS budget_amount,
                   b.month
            FROM budgets b
            JOIN chart_of_accounts coa ON b.account_id = coa.id
            WHERE b.year = ? AND b.month >= ? AND b.month <= ? $ccWhere
            GROUP BY b.account_id, coa.account_code, coa.account_name, coa.account_type, b.month
            ORDER BY coa.account_code, b.month
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBalanceSheetData($asOfDate, $costCenterId = null)
    {
        $ccWhere = '';
        $params = array('posted', $asOfDate);
        if ($costCenterId) {
            $ccWhere = ' AND jl.cost_center_id = ?';
            $params[] = (int)$costCenterId;
        }

        $stmt = $this->db->prepare("
            SELECT coa.account_code AS code, coa.account_name AS name, coa.account_type, coa.normal_balance,
                   SUBSTRING(coa.account_code, 1, 1) AS code_prefix,
                   SUM(jl.debit_amount) AS total_debit,
                   SUM(jl.credit_amount) AS total_credit
            FROM journal_entry_lines jl
            JOIN journal_entries je ON jl.journal_entry_id = je.id
            JOIN chart_of_accounts coa ON jl.account_id = coa.id
            WHERE je.status = ? AND je.voucher_date <= ? $ccWhere
              AND SUBSTRING(coa.account_code, 1, 1) IN ('1','2','3')
            GROUP BY coa.account_code, coa.account_name, coa.account_type, coa.normal_balance
            ORDER BY coa.account_code
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPLAccounts()
    {
        return $this->db->query("
            SELECT id, account_code AS code, account_name AS name, account_type, normal_balance
            FROM chart_of_accounts
            WHERE is_active = 1 AND SUBSTRING(account_code, 1, 1) IN ('4','5','6','7','8')
            ORDER BY account_code
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
