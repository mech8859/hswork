<?php
class CustomerModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 建立搜尋 WHERE 條件
     */
    private function buildListWhere($filters, &$params)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $where .= " AND (c.name LIKE ? OR c.contact_person LIKE ? OR c.phone LIKE ? OR c.mobile LIKE ? OR c.tax_id LIKE ? OR c.customer_no LIKE ? OR c.legacy_customer_no LIKE ? OR c.note LIKE ? OR c.site_address LIKE ? OR c.case_number LIKE ? OR c.source_company LIKE ? OR c.original_customer_no LIKE ? OR c.id IN (SELECT customer_id FROM cases WHERE customer_id IS NOT NULL AND (case_number LIKE ? OR title LIKE ?)))";
            $params = array_merge($params, array($kw, $kw, $kw, $kw, $kw, $kw, $kw, $kw, $kw, $kw, $kw, $kw, $kw, $kw));
        }
        if (!empty($filters['category'])) {
            $where .= " AND c.category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['sales_id'])) {
            $where .= " AND c.sales_id = ?";
            $params[] = $filters['sales_id'];
        }
        if (!empty($filters['import_source'])) {
            $where .= " AND c.import_source = ?";
            $params[] = $filters['import_source'];
        }
        if (!empty($filters['has_cases'])) {
            $where .= " AND c.id IN (SELECT DISTINCT customer_id FROM cases WHERE customer_id IS NOT NULL)";
        }
        if (!empty($filters['source_branch'])) {
            $where .= " AND c.source_branch = ?";
            $params[] = $filters['source_branch'];
        }
        if (!empty($filters['date_from'])) {
            $where .= " AND c.completion_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= " AND c.completion_date <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['has_relations'])) {
            $where .= " AND c.related_group_id IS NOT NULL AND c.related_group_id IN (SELECT related_group_id FROM customers WHERE related_group_id IS NOT NULL GROUP BY related_group_id HAVING COUNT(*) >= 2)";
        }
        if (!empty($filters['excel_2026'])) {
            $where .= " AND c.import_source = 'excel_import' AND (YEAR(c.completion_date) = 2026 OR c.completion_date IS NULL OR c.completion_date = '0000-00-00')";
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where .= " AND c.is_active = ?";
            $params[] = (int)$filters['is_active'];
        } else {
            $where .= " AND c.is_active = 1";
        }

        return $where;
    }

    /**
     * 客戶列表（分頁版）
     */
    public function getList($filters = array(), $limit = 100, $page = 1)
    {
        $params = array();
        $where = $this->buildListWhere($filters, $params);

        $offset = ($page - 1) * $limit;

        $sql = "SELECT c.*, u.real_name as sales_name,
                CONCAT(COALESCE(c.site_city,''), COALESCE(c.site_district,''), COALESCE(c.site_address,'')) as full_address,
                EXISTS(SELECT 1 FROM cases ca WHERE ca.customer_id = c.id) as has_cases,
                EXISTS(SELECT 1 FROM cases ca WHERE ca.customer_id = c.id AND (ca.sub_status IN ('已成交','跨月成交','現簽','電話報價成交') OR ca.case_type IN ('old_repair','addition'))) as has_deal,
                (SELECT COUNT(*) FROM customer_files cf WHERE cf.customer_id = c.id) as file_count
                FROM customers c
                LEFT JOIN users u ON c.sales_id = u.id
                WHERE $where
                ORDER BY c.updated_at DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 客戶搜尋結果總數
     */
    public function getListCount($filters = array())
    {
        $params = array();
        $where = $this->buildListWhere($filters, $params);

        $sql = "SELECT COUNT(*) FROM customers c WHERE $where";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * 客戶統計儀表板
     */
    public function getDashboardStats()
    {
        $stats = array();

        // 總客戶數
        $stats['total'] = (int)$this->db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn();

        // 成交客戶數
        try {
            $stats['with_deals'] = (int)$this->db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1 AND import_source = 'excel_import'")->fetchColumn();
        } catch (PDOException $e) { $stats['with_deals'] = 0; }

        // 有關聯客戶數
        try {
            $stats['with_relations'] = (int)$this->db->query("SELECT COALESCE(SUM(cnt),0) FROM (SELECT COUNT(*) as cnt FROM customers WHERE related_group_id IS NOT NULL AND is_active = 1 GROUP BY related_group_id HAVING cnt >= 2) t")->fetchColumn();
        } catch (PDOException $e) { $stats['with_relations'] = 0; }

        // 分類統計
        try {
            $stmt = $this->db->query("SELECT category, COUNT(*) as cnt FROM customers WHERE is_active = 1 AND category IS NOT NULL AND category != '' GROUP BY category ORDER BY cnt DESC");
            $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $stats['by_category'] = array(); }

        // 來源統計
        try {
            $stmt = $this->db->query("SELECT COALESCE(import_source, '手動建立') as source, COUNT(*) as cnt FROM customers WHERE is_active = 1 GROUP BY import_source ORDER BY cnt DESC");
            $stats['by_source'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $stats['by_source'] = array(); }

        // 年度統計（有完工日期的，排除無日期歸2026的Excel匯入）
        try {
            $stmt = $this->db->query("
                SELECT YEAR(completion_date) as yr, COUNT(*) as cnt
                FROM customers WHERE is_active = 1 AND completion_date IS NOT NULL AND completion_date != '0000-00-00'
                AND (YEAR(completion_date) != 2026 OR import_source != 'excel_import' OR import_source IS NULL)
                GROUP BY yr ORDER BY yr DESC LIMIT 10
            ");
            $stats['by_year'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Excel 匯入中 2026 年或無日期的
            $excel2026 = (int)$this->db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1 AND import_source = 'excel_import' AND (YEAR(completion_date) = 2026 OR completion_date IS NULL OR completion_date = '0000-00-00')")->fetchColumn();
            if ($excel2026 > 0) {
                $stats['by_year'][] = array('yr' => '2026-Excel', 'cnt' => $excel2026);
            }

            // 2026 年案件管理已成交客戶
            $dealCount = (int)$this->db->query("SELECT COUNT(DISTINCT customer_id) FROM cases WHERE customer_id IS NOT NULL AND sub_status IN ('已成交','跨月成交','現簽','電話報價成交') AND YEAR(created_at) = 2026")->fetchColumn();
            if ($dealCount > 0) {
                // 插到最前面
                array_unshift($stats['by_year'], array('yr' => '2026', 'cnt' => $dealCount));
            }
        } catch (PDOException $e) { $stats['by_year'] = array(); }

        // 各分公司客戶數
        try {
            $stmt = $this->db->query("SELECT COALESCE(source_branch, '未分類') as branch, COUNT(*) as cnt FROM customers WHERE is_active = 1 GROUP BY source_branch ORDER BY cnt DESC");
            $stats['by_branch'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $stats['by_branch'] = array(); }

        // 有案件的客戶數
        try {
            $stats['with_cases'] = (int)$this->db->query("SELECT COUNT(DISTINCT customer_id) FROM cases WHERE customer_id IS NOT NULL")->fetchColumn();
        } catch (PDOException $e) { $stats['with_cases'] = 0; }

        // 黑名單客戶數
        $stats['blacklisted'] = (int)$this->db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1 AND is_blacklisted = 1")->fetchColumn();

        // 最近客戶 (10筆)
        try {
            $stmt = $this->db->query("SELECT id, customer_no, name, category, COALESCE(completion_date, created_at) as created_at FROM customers WHERE is_active = 1 AND COALESCE(completion_date, created_at) <= CURDATE() ORDER BY COALESCE(completion_date, created_at) DESC LIMIT 10");
            $stats['recent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $stats['recent'] = array(); }

        return $stats;
    }

    /**
     * 客戶詳情
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT c.*, u.real_name as sales_name FROM customers c LEFT JOIN users u ON c.sales_id = u.id WHERE c.id = ?");
        $stmt->execute(array($id));
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($customer) {
            $customer['contacts'] = $this->getContacts($id);
        }
        return $customer;
    }

    /**
     * 取得客戶聯絡人列表
     */
    public function getContacts($customerId)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, contact_name, phone, mobile, role, note FROM customer_contacts WHERE customer_id = ? ORDER BY id");
            $stmt->execute(array($customerId));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 儲存客戶聯絡人（刪除重建）
     */
    public function saveContacts($customerId, $contacts)
    {
        try {
            $this->db->prepare("DELETE FROM customer_contacts WHERE customer_id = ?")->execute(array($customerId));
            $stmt = $this->db->prepare("INSERT INTO customer_contacts (customer_id, contact_name, phone, mobile, role, note) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($contacts as $c) {
                if (empty($c['contact_name'])) continue;
                $stmt->execute(array(
                    $customerId,
                    $c['contact_name'],
                    isset($c['phone']) ? $c['phone'] : null,
                    isset($c['mobile']) ? $c['mobile'] : null,
                    isset($c['role']) ? $c['role'] : null,
                    isset($c['note']) ? $c['note'] : null,
                ));
            }
        } catch (Exception $e) {
            // customer_contacts 表不存在時不報錯
        }
    }

    /**
     * 新增客戶
     */
    public function create($data)
    {
        $no = $this->generateNumber();
        $stmt = $this->db->prepare("
            INSERT INTO customers (customer_no, legacy_customer_no, name, category, contact_person, phone, mobile, fax, email,
                invoice_title, tax_id, invoice_email,
                billing_city, billing_district, billing_address,
                site_city, site_district, site_address,
                completion_date, warranty_date,
                payment_method, payment_terms, sales_id, note, created_by,
                is_blacklisted, blacklist_reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(array(
            $no,
            isset($data['legacy_customer_no']) && $data['legacy_customer_no'] !== '' ? $data['legacy_customer_no'] : null,
            $data['name'],
            $data['category'] ?: null,
            $data['contact_person'] ?: null,
            $data['phone'] ?: null,
            $data['mobile'] ?: null,
            $data['fax'] ?: null,
            $data['email'] ?: null,
            $data['invoice_title'] ?: null,
            $data['tax_id'] ?: null,
            $data['invoice_email'] ?: null,
            $data['billing_city'] ?: null,
            $data['billing_district'] ?: null,
            $data['billing_address'] ?: null,
            $data['site_city'] ?: null,
            $data['site_district'] ?: null,
            $data['site_address'] ?: null,
            !empty($data['completion_date']) ? $data['completion_date'] : null,
            !empty($data['warranty_date']) ? $data['warranty_date'] : null,
            $data['payment_method'] ?: null,
            $data['payment_terms'] ?: null,
            $data['sales_id'] ?: null,
            $data['note'] ?: null,
            Auth::id(),
            !empty($data['is_blacklisted']) ? 1 : 0,
            !empty($data['blacklist_reason']) ? $data['blacklist_reason'] : null
        ));
        return $this->db->lastInsertId();
    }

    /**
     * 更新客戶
     */
    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE customers SET
                legacy_customer_no = ?, name = ?, category = ?, contact_person = ?, phone = ?, mobile = ?, fax = ?, email = ?,
                invoice_title = ?, tax_id = ?, invoice_email = ?,
                billing_city = ?, billing_district = ?, billing_address = ?,
                site_city = ?, site_district = ?, site_address = ?,
                completion_date = ?, warranty_date = ?,
                payment_method = ?, payment_terms = ?, sales_id = ?, note = ?,
                is_blacklisted = ?, blacklist_reason = ?
            WHERE id = ?
        ");
        $stmt->execute(array(
            isset($data['legacy_customer_no']) && $data['legacy_customer_no'] !== '' ? $data['legacy_customer_no'] : null,
            $data['name'],
            $data['category'] ?: null,
            $data['contact_person'] ?: null,
            $data['phone'] ?: null,
            $data['mobile'] ?: null,
            $data['fax'] ?: null,
            $data['email'] ?: null,
            $data['invoice_title'] ?: null,
            $data['tax_id'] ?: null,
            $data['invoice_email'] ?: null,
            $data['billing_city'] ?: null,
            $data['billing_district'] ?: null,
            $data['billing_address'] ?: null,
            $data['site_city'] ?: null,
            $data['site_district'] ?: null,
            $data['site_address'] ?: null,
            !empty($data['completion_date']) ? $data['completion_date'] : null,
            !empty($data['warranty_date']) ? $data['warranty_date'] : null,
            $data['payment_method'] ?: null,
            $data['payment_terms'] ?: null,
            $data['sales_id'] ?: null,
            $data['note'] ?: null,
            !empty($data['is_blacklisted']) ? 1 : 0,
            !empty($data['blacklist_reason']) ? $data['blacklist_reason'] : null,
            $id
        ));
    }

    /**
     * 停用/啟用
     */
    public function toggleActive($id)
    {
        $this->db->prepare("UPDATE customers SET is_active = NOT is_active WHERE id = ?")->execute(array($id));
    }

    /**
     * 取得客戶的案件列表
     */
    public function getCases($customerId)
    {
        $stmt = $this->db->prepare("SELECT c.*, b.name as branch_name FROM cases c LEFT JOIN branches b ON c.branch_id = b.id WHERE c.customer_id = ? ORDER BY c.created_at DESC");
        $stmt->execute(array($customerId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得客戶的成交紀錄
     */
    public function getDeals($customerId)
    {
        $stmt = $this->db->prepare("SELECT cd.*, c.case_number, c.title as case_title FROM customer_deals cd LEFT JOIN cases c ON cd.case_id = c.id WHERE cd.customer_id = ? ORDER BY cd.completion_date DESC");
        $stmt->execute(array($customerId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 新增成交紀錄
     */
    public function addDeal($customerId, $data)
    {
        $stmt = $this->db->prepare("INSERT INTO customer_deals (customer_id, case_id, site_address, deal_amount, completion_date, warranty_date, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(array(
            $customerId,
            $data['case_id'] ?: null,
            $data['site_address'] ?: null,
            $data['deal_amount'] ?: null,
            $data['completion_date'] ?: null,
            $data['warranty_date'] ?: null,
            $data['note'] ?: null
        ));
    }

    /**
     * 取得客戶帳款交易
     */
    public function getTransactions($customerId)
    {
        $stmt = $this->db->prepare("SELECT * FROM customer_transactions WHERE customer_id = ? ORDER BY transaction_date DESC");
        $stmt->execute(array($customerId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得業務人員列表
     */
    public function getSalespeople()
    {
        $stmt = $this->db->query("SELECT id, real_name FROM users WHERE role IN ('sales','sales_manager','sales_assistant','boss','manager') AND is_active = 1 ORDER BY real_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得客戶分類選項
     */
    public static function categoryOptions()
    {
        return array(
            'residential'  => '個人／住戶',
            'food'         => '餐飲業',
            'shop'         => '零售／店面',
            'hospital'     => '醫療／健康照護',
            'school'       => '教育',
            'religion'     => '宗教',
            'leisure'      => '休閒娛樂',
            'hotel'        => '旅宿業',
            'financial'    => '金融／保險',
            'industrial'   => '製造／工廠',
            'builder'      => '建設／營造',
            'logistics'    => '物流／倉儲',
            'community'    => '社區／管委會',
            'government'   => '機關／政府',
            'commercial'   => '一般公司／企業',
            'enterprise'   => '上市櫃企業',
            'association'  => '協會／團體',
        );
    }

    /**
     * 文件類型選項（從 dropdown_options 載入，fallback 到預設值）
     */
    public static function fileTypeOptions()
    {
        $fallback = array(
            'quotation' => '報價單',
            'contract'  => '合約書',
            'photo'     => '照片',
            'invoice'   => '發票',
            'other'     => '其他',
        );
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT option_key, label FROM dropdown_options WHERE category = 'customer_file_type' AND is_active = 1 ORDER BY sort_order, label");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $result = array();
                foreach ($rows as $row) {
                    $key = $row['option_key'] ? $row['option_key'] : $row['label'];
                    $result[$key] = $row['label'];
                }
                return $result;
            }
        } catch (PDOException $e) {
            // fallback
        }
        return $fallback;
    }

    /**
     * 新增自訂文件類型到 dropdown_options
     */
    public static function addFileType($key, $label)
    {
        $db = Database::getInstance();
        $maxSort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM dropdown_options WHERE category = 'customer_file_type'")->fetchColumn();
        $stmt = $db->prepare("INSERT INTO dropdown_options (category, option_key, label, sort_order, is_active, is_system) VALUES ('customer_file_type', ?, ?, ?, 1, 0)");
        $stmt->execute(array($key, $label, $maxSort + 1));
        return $db->lastInsertId();
    }

    /**
     * 產生客戶編號（使用統一自動編號系統）
     */
    private function generateNumber()
    {
        return generate_doc_number('customers');
    }

    /**
     * AJAX搜尋客戶（給案件表單用）
     */
    public function search($keyword, $limit = 10)
    {
        $kw = '%' . $keyword . '%';
        $stmt = $this->db->prepare("SELECT id, customer_no, name, phone, mobile, contact_person, site_address, invoice_title, tax_id, completion_date, warranty_date, payment_terms, is_blacklisted, blacklist_reason FROM customers WHERE is_active = 1 AND (name LIKE ? OR phone LIKE ? OR mobile LIKE ? OR tax_id LIKE ?) ORDER BY name LIMIT ?");
        $stmt->execute(array($kw, $kw, $kw, $kw, $limit));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得該客戶的維修單照片（透過客戶名稱比對）
     */
    public function getRepairPhotos($customerId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT rp.id, rp.file_path, rp.caption, rp.uploaded_at,
                       r.repair_number, r.repair_date
                FROM repair_photos rp
                JOIN repairs r ON rp.repair_id = r.id
                WHERE r.customer_name = (SELECT name FROM customers WHERE id = ?)
                ORDER BY rp.uploaded_at DESC
            ");
            $stmt->execute(array($customerId));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return array();
        }
    }

    // File management
    public function getFiles($customerId)
    {
        $stmt = $this->db->prepare("SELECT cf.*, u.real_name as uploader_name FROM customer_files cf LEFT JOIN users u ON cf.uploaded_by = u.id WHERE cf.customer_id = ? ORDER BY cf.created_at DESC");
        $stmt->execute(array($customerId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addFile($customerId, $data)
    {
        $stmt = $this->db->prepare("INSERT INTO customer_files (customer_id, file_type, file_name, file_path, file_size, note, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(array(
            $customerId,
            $data['file_type'] ?: 'other',
            $data['file_name'],
            $data['file_path'],
            $data['file_size'] ?: 0,
            $data['note'] ?: null,
            Auth::id()
        ));
        return $this->db->lastInsertId();
    }

    public function getFile($fileId)
    {
        $stmt = $this->db->prepare("SELECT * FROM customer_files WHERE id = ?");
        $stmt->execute(array($fileId));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteFile($fileId)
    {
        $file = $this->getFile($fileId);
        if ($file) {
            $fullPath = __DIR__ . '/../../public' . $file['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            $this->db->prepare("DELETE FROM customer_files WHERE id = ?")->execute(array($fileId));
        }
        return $file;
    }

    public function deleteDeal($dealId)
    {
        $this->db->prepare("DELETE FROM customer_deals WHERE id = ?")->execute(array($dealId));
    }

    public function addTransaction($customerId, $data)
    {
        $stmt = $this->db->prepare("INSERT INTO customer_transactions (customer_id, transaction_date, description, amount, note) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(array(
            $customerId,
            !empty($data['transaction_date']) ? $data['transaction_date'] : null,
            !empty($data['description']) ? $data['description'] : null,
            !empty($data['amount']) ? $data['amount'] : 0,
            !empty($data['note']) ? $data['note'] : null
        ));
        return $this->db->lastInsertId();
    }

    public function deleteTransaction($transactionId)
    {
        $this->db->prepare("DELETE FROM customer_transactions WHERE id = ?")->execute(array($transactionId));
    }

    /**
     * 取得客戶在各模組的關聯筆數
     * 任一非零都應封鎖刪除
     */
    public function getCustomerReferences($customerId)
    {
        $cust = $this->db->prepare("SELECT id, customer_no FROM customers WHERE id = ?");
        $cust->execute(array($customerId));
        $row = $cust->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $cno = $row['customer_no'];

        $count = function($sql, $params) {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return (int)$st->fetchColumn();
        };

        $refs = array(
            '案件 (cases)'         => $count("SELECT COUNT(*) FROM cases WHERE customer_id = ?", array($customerId)),
            '業務行事曆'           => $count("SELECT COUNT(*) FROM business_calendar WHERE customer_id = ?", array($customerId)),
            '報價單'               => $count("SELECT COUNT(*) FROM quotations WHERE customer_id = ?", array($customerId)),
            '出庫單'               => $count("SELECT COUNT(*) FROM stock_outs WHERE customer_id = ?", array($customerId)),
            '附件 (files)'         => $count("SELECT COUNT(*) FROM customer_files WHERE customer_id = ?", array($customerId)),
            '成交紀錄 (deals)'     => $count("SELECT COUNT(*) FROM customer_deals WHERE customer_id = ?", array($customerId)),
            '帳款交易'             => $count("SELECT COUNT(*) FROM customer_transactions WHERE customer_id = ?", array($customerId)),
        );
        // customer_no 欄位的引用（無 FK，逐表查）
        if (!empty($cno)) {
            $refs['案件 (case_no)']    = $count("SELECT COUNT(*) FROM cases WHERE customer_no = ?", array($cno));
            $refs['應收帳款 (AR)']     = $count("SELECT COUNT(*) FROM receivables WHERE customer_no = ?", array($cno));
            $refs['收款單']            = $count("SELECT COUNT(*) FROM receipts WHERE customer_no = ?", array($cno));
            $refs['應付帳款 (AP)']     = $count("SELECT COUNT(*) FROM payables WHERE customer_no = ?", array($cno));
            $refs['付款單']            = $count("SELECT COUNT(*) FROM payments_out WHERE customer_no = ?", array($cno));
        }
        return $refs;
    }

    /**
     * 刪除客戶（僅允許無任何關聯資料時）
     * @throws RuntimeException 若有關聯
     */
    public function deleteCustomer($customerId)
    {
        $refs = $this->getCustomerReferences($customerId);
        if ($refs === null) {
            throw new RuntimeException('客戶不存在');
        }
        $blocking = array();
        foreach ($refs as $label => $cnt) {
            if ($cnt > 0) $blocking[] = $label . ' ' . $cnt . ' 筆';
        }
        if (!empty($blocking)) {
            throw new RuntimeException('客戶尚有關聯資料，無法刪除：' . implode('、', $blocking));
        }
        // 連帶刪除聯絡人（contacts 是內部資料，FK CASCADE 會自動處理）
        $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute(array($customerId));
        return true;
    }
}
