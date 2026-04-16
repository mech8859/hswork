<?php
/**
 * 財務會計資料模型
 * 應收帳款、收款單、應付帳款單、付款單
 */
class FinanceModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ============================================================
    // 靜態選項
    // ============================================================

    public static function receivableStatusOptions()
    {
        return array(
            '待請款' => '待請款',
            '已請款' => '已請款',
            '部分收款' => '部分收款',
            '已收款' => '已收款',
            '逾期'   => '逾期',
            '取消'   => '取消',
        );
    }

    public static function payableStatusOptions()
    {
        return array(
            '待付款' => '待付款',
            '已付款' => '已付款',
            '部分付款' => '部分付款',
            '取消'   => '取消',
        );
    }

    public static function paymentOutStatusOptions()
    {
        return self::loadOptions('payment_out_status', array(
            '草稿' => '草稿',
            '已付款' => '已付款',
            '預付待查' => '預付待查',
            '已付待查' => '已付待查',
        ));
    }

    public static function paymentOutMethodOptions()
    {
        return self::loadOptions('payment_out_method', array(
            '零用金' => '零用金',
            '現金' => '現金',
            '銀行支出-禾順' => '銀行支出-禾順',
            '銀行支出-政達' => '銀行支出-政達',
            '銀行支出-富邦' => '銀行支出-富邦',
            '支票' => '支票',
            '進銷對沖' => '進銷對沖',
            '銀行支出' => '銀行支出',
        ));
    }

    public static function paymentOutCategoryOptions()
    {
        return self::loadOptions('payment_out_category', array(
            '訂金' => '訂金',
            '保留款' => '保留款',
            '廠商-發票已申報' => '廠商-發票已申報',
            '廠商' => '廠商',
            '其他' => '其他',
        ));
    }

    public static function paymentMethodOptions()
    {
        return self::loadOptions('payment_method', array(
            '現金' => '現金',
            '匯款-禾順中信' => '匯款-禾順中信',
            '匯款-彰銀' => '匯款-彰銀',
            '支票' => '支票',
            '匯款-政達' => '匯款-政達',
            '代收付' => '代收付',
            '進銷對沖' => '進銷對沖',
        ));
    }

    public static function paymentTermsOptions()
    {
        return self::loadOptions('payment_terms', array(
            '月結30天' => '月結30天',
            '月結60天' => '月結60天',
            '月結90天' => '月結90天',
            '貨到付款' => '貨到付款',
            '預付'     => '預付',
            '其他'     => '其他',
        ));
    }

    public static function invoiceCategoryOptions()
    {
        return self::loadOptions('invoice_category', array(
            '全款' => '全款',
            '訂金' => '訂金',
            '第一期款' => '第一期款',
            '第二期款' => '第二期款',
            '第三期款' => '第三期款',
            '尾款' => '尾款',
            '保留款' => '保留款',
            '退款' => '退款',
        ));
    }

    public static function receiptStatusOptions()
    {
        return self::loadOptions('receipt_status', array(
            '已收款' => '已收款',
            '已收待查資料' => '已收待查資料',
            '預收待查' => '預收待查',
            '保留款' => '保留款',
            '待收款' => '待收款',
            '拋轉待確認' => '拋轉待確認',
            '已入帳' => '已入帳',
            '退款' => '退款',
            '取消' => '取消',
        ));
    }

    /**
     * 從 dropdown_options 載入選項，若無則回傳 fallback
     */
    private static function loadOptions($category, $fallback)
    {
        static $cache = array();
        if (isset($cache[$category])) return $cache[$category];
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT option_key, label FROM dropdown_options WHERE category = ? AND is_active = 1 ORDER BY sort_order, label");
            $stmt->execute(array($category));
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $result = array();
                foreach ($rows as $row) {
                    $key = $row['option_key'] ? $row['option_key'] : $row['label'];
                    $result[$key] = $row['label'];
                }
                $cache[$category] = $result;
                return $result;
            }
        } catch (\PDOException $e) {}
        $cache[$category] = $fallback;
        return $fallback;
    }

    /**
     * 付款單主分類選項 (21個)
     */
    public static function mainCategoryOptions()
    {
        $fallback = array(
            '銀行借款', '辦公設備', '資產設備/辦公設備', '電子鎖相關支出',
            '其他費用', '非公司帳款', '員工福利與娛樂費用', '外包與工班費用',
            '權利金與特殊費用', '廣告與行銷費用', '員工借支', '稅捐與專業費用',
            '交際費', '房租／水電／管理費', '設備／工具／維修費用', '勞健保與保險',
            '薪資與獎金', '文具／辦公／日常用品', '餐飲與聚餐費', '郵務／運輸／手續費',
            '設備器材', '設備器材廠商', '車輛相關支出', '系統/軟體費用',
        );
        try {
            $db = \Database::getInstance();
            $stmt = $db->prepare("SELECT label FROM dropdown_options WHERE category = 'payment_main_category' AND parent_id IS NULL AND is_active = 1 ORDER BY sort_order, id");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if (!empty($rows)) return $rows;
        } catch (\Exception $e) {}
        return $fallback;
    }

    /**
     * 主分類→細分類對照表
     */
    public static function subCategoryMap()
    {
        $fallback = array(
            '銀行借款' => array('本金加利息(還款)', '利息'),
            '辦公設備' => array('電腦軟體', '電腦設備'),
            '電子鎖相關支出' => array('電子鎖材料費', '電子鎖安裝費', '電子鎖維修費', '電子鎖更換費', '電子鎖耗材', '電子鎖硬件成本', '電子鎖設備備品', '電子鎖專案成本', '電子鎖其他支出'),
            '其他費用' => array('贊助禮品', '轉零用金', '退還客戶', '毀約退費', '電子鎖材料費', '電子鎖安裝費', '電子鎖維修費', '電子鎖更換費', '電子鎖耗材', '電子鎖硬件成本', '電子鎖設備備品', '電子鎖專案成本', '電子鎖其他支出', '其他費用'),
            '非公司帳款' => array('非公司帳務支出'),
            '員工福利與娛樂費用' => array('員工各項補助', '慰問金', '育訓練生日禮金', '周年禮金', '祝贈品', '春節活動', '團康活動', '公司尾牙', '教育訓練'),
            '外包與工班費用' => array('臨時工費', '工程外包費', '施工費用', '點工費用', '代工費用'),
            '權利金與特殊費用' => array('高雄獎品或權利金', '高雄權利金', '彰化權利金', '台中權利金'),
            '廣告與行銷費用' => array('高雄縣/廣告費', '在地網站廣告', 'GOOGLE廣告', 'YouTube行銷', 'FB廣告', '廣告費'),
            '員工借支' => array('員工借支'),
            '稅捐與專業費用' => array('NCC送審費', '扣繳申報費', '會務費', '營業稅', '會計師代辦費', '會計師記帳費'),
            '房租／水電／管理費' => array('水電費', '房租保證金', '房租', '電話費', '保全費'),
            '設備／工具／維修費用' => array('檢修設備', '五金材料配件', '水電材料', '設備改裝', '耗材設備', '電鑽設備', '維修費', '工具維修', '工具購買', '耗材費用', '雜項購置'),
            '勞健保與保險' => array('工程保險', '旅遊平安險', '雇主責任保險', '團保', '勞退', '健保', '勞保'),
            '薪資與獎金' => array('分紅支出', '獎金支出', '薪水支出'),
            '文具／辦公／日常用品' => array('辦公室用品雜物', '保全費用', '夾鏈袋', '生活用品', '日常支出', '文具用品'),
            '餐飲與聚餐費' => array('公司聚餐', '部門聯餐', '餐費', '午餐'),
            '郵務／運輸／手續費' => array('運費', '轉帳手續費', '印花稅', '郵資'),
            '設備器材' => array('設備器材', '線材、耗材'),
            '設備器材廠商' => array('設備器材', '設備器材廠商'),
            '資產設備/辦公設備' => array('電腦設備', '辦公設備', '廠房及設備', '冷氣、空調設備'),
            '交際費' => array('廠商交際費', '客戶交際費'),
            '車輛相關支出' => array('租賃保證金', '汽車租賃', '罰單', '修理', '保養', 'ETC', '停車費', '加油卡儲值', '現金加油'),
            '系統/軟體費用' => array('系統費用', '資訊服務費'),
        );
        try {
            $db = \Database::getInstance();
            // 取所有主分類 id => label
            $mainStmt = $db->prepare("SELECT id, label FROM dropdown_options WHERE category = 'payment_main_category' AND parent_id IS NULL AND is_active = 1 ORDER BY sort_order, id");
            $mainStmt->execute();
            $mains = $mainStmt->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($mains)) return $fallback;

            // 取所有子分類
            $subStmt = $db->prepare("SELECT parent_id, label FROM dropdown_options WHERE category = 'payment_main_category' AND parent_id IS NOT NULL AND is_active = 1 ORDER BY sort_order, id");
            $subStmt->execute();
            $subs = $subStmt->fetchAll(\PDO::FETCH_ASSOC);

            // 依 parent_id 分組
            $subsByParent = array();
            foreach ($subs as $s) {
                $pid = $s['parent_id'];
                if (!isset($subsByParent[$pid])) $subsByParent[$pid] = array();
                $subsByParent[$pid][] = $s['label'];
            }

            // 組成 mainLabel => [subLabels]
            $result = array();
            foreach ($mains as $m) {
                $result[$m['label']] = isset($subsByParent[$m['id']]) ? $subsByParent[$m['id']] : array();
            }
            return $result;
        } catch (\Exception $e) {}
        return $fallback;
    }

    // ============================================================
    // 編號生成
    // ============================================================

    /**
     * 產生流水號
     * @param string $prefix  前綴 (AR/RC/AP/PO)
     * @param string $table   表名
     * @param string $column  編號欄位名
     */
    public function generateNumber($prefix, $table, $column)
    {
        // 對應到新的 number_sequences 模組 key
        $moduleMap = array(
            'AR' => 'receivables',
            'RC' => 'receipts',
            'AP' => 'payables',
            'PO' => 'payments',
        );
        $module = isset($moduleMap[$prefix]) ? $moduleMap[$prefix] : null;
        if ($module) {
            return generate_doc_number($module);
        }
        // fallback 舊邏輯
        $year = date('Y');
        $like = $prefix . '-' . $year . '-%';
        $stmt = $this->db->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(array($like));
        $last = $stmt->fetchColumn();
        if ($last) {
            $parts = explode('-', $last);
            $seq = (int)end($parts) + 1;
        } else {
            $seq = 1;
        }
        return $prefix . '-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ============================================================
    // 應收帳款 CRUD
    // ============================================================

    public function getReceivables($filters = array(), $branchIds = array(), $page = 1, $perPage = 100)
    {
        $where = '1=1';
        $params = array();

        if (!empty($branchIds)) {
            $ph = implode(',', array_fill(0, count($branchIds), '?'));
            $where .= " AND (r.branch_id IN ({$ph}) OR r.branch_id IS NULL)";
            $params = array_merge($params, $branchIds);
        }
        if (!empty($filters['status'])) {
            $where .= ' AND r.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $kwRaw = trim($filters['keyword']);
            if (preg_match('/^[\$＄]\s*([\d,]+(?:\.\d+)?)$/u', $kwRaw, $m)) {
                $amt = (float)str_replace(',', '', $m[1]);
                $where .= ' AND r.total_amount = ?';
                $params[] = $amt;
            } else {
                $where .= ' AND (r.invoice_number LIKE ? OR r.receivable_number LIKE ? OR r.voucher_number LIKE ? OR r.customer_name LIKE ? OR r.invoice_title LIKE ? OR r.note LIKE ? OR r.case_number LIKE ?)';
                $kw = '%' . $kwRaw . '%';
                $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
            }
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND r.invoice_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND r.invoice_date <= ?';
            $params[] = $filters['date_to'];
        }

        $sortDir = (!empty($filters['sort']) && $filters['sort'] === 'asc') ? 'ASC' : 'DESC';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM receivables r WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT r.*, b.name AS branch_name, u.real_name AS sales_name
            FROM receivables r
            LEFT JOIN branches b ON r.branch_id = b.id
            LEFT JOIN users u ON r.sales_id = u.id
            WHERE {$where}
            ORDER BY r.invoice_date {$sortDir}, r.id {$sortDir}
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return array(
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        );
    }

    public function getReceivable($id)
    {
        $stmt = $this->db->prepare("
            SELECT r.*, b.name AS branch_name, u.real_name AS sales_name
            FROM receivables r
            LEFT JOIN branches b ON r.branch_id = b.id
            LEFT JOIN users u ON r.sales_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getReceivableItems($receivableId)
    {
        $stmt = $this->db->prepare("SELECT * FROM receivable_items WHERE receivable_id = ? ORDER BY id");
        $stmt->execute(array($receivableId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createReceivable($data)
    {
        $number = $this->generateNumber('AR', 'receivables', 'invoice_number');
        $stmt = $this->db->prepare("
            INSERT INTO receivables (invoice_number, voucher_number, invoice_date, case_id, case_number, customer_no, customer_name, branch_id, sales_id,
                invoice_category, status, invoice_title, tax_id, phone, mobile, invoice_email, invoice_address,
                payment_method, payment_terms,
                deposit, discount, subtotal, tax, shipping, total_amount,
                real_invoice_number, voucher_type, tax_rate,
                registrar, note, created_by, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute(array(
            $number,
            !empty($data['voucher_number']) ? $data['voucher_number'] : null,
            $data['invoice_date'],
            !empty($data['case_id']) ? $data['case_id'] : null,
            !empty($data['case_number']) ? $data['case_number'] : null,
            !empty($data['customer_no']) ? $data['customer_no'] : null,
            !empty($data['customer_name']) ? $data['customer_name'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['sales_id']) ? $data['sales_id'] : null,
            !empty($data['invoice_category']) ? $data['invoice_category'] : null,
            !empty($data['status']) ? $data['status'] : '待請款',
            !empty($data['invoice_title']) ? $data['invoice_title'] : null,
            !empty($data['tax_id']) ? $data['tax_id'] : null,
            !empty($data['phone']) ? $data['phone'] : null,
            !empty($data['mobile']) ? $data['mobile'] : null,
            !empty($data['invoice_email']) ? $data['invoice_email'] : null,
            !empty($data['invoice_address']) ? $data['invoice_address'] : null,
            !empty($data['payment_method']) ? $data['payment_method'] : null,
            !empty($data['payment_terms']) ? $data['payment_terms'] : null,
            isset($data['deposit']) ? (int)$data['deposit'] : 0,
            isset($data['discount']) ? (int)$data['discount'] : 0,
            isset($data['subtotal']) ? (int)$data['subtotal'] : 0,
            isset($data['tax']) ? (int)$data['tax'] : 0,
            isset($data['shipping']) ? (int)$data['shipping'] : 0,
            isset($data['total_amount']) ? (int)$data['total_amount'] : 0,
            !empty($data['real_invoice_number']) ? $data['real_invoice_number'] : null,
            !empty($data['voucher_type']) ? $data['voucher_type'] : null,
            !empty($data['tax_rate']) ? $data['tax_rate'] : null,
            !empty($data['registrar']) ? $data['registrar'] : null,
            !empty($data['note']) ? $data['note'] : null,
            $data['created_by'],
            $data['created_by'],
        ));
        return $this->db->lastInsertId();
    }

    public function updateReceivable($id, $data)
    {
        // 註：registrar 由建立時寫入，更新時不變動
        $stmt = $this->db->prepare("
            UPDATE receivables SET
                voucher_number=?, invoice_date=?, case_id=?, case_number=?, customer_no=?, customer_name=?, branch_id=?, sales_id=?,
                invoice_category=?, status=?, invoice_title=?, tax_id=?, phone=?, mobile=?,
                invoice_email=?, invoice_address=?, payment_method=?, payment_terms=?,
                deposit=?, discount=?, subtotal=?, tax=?, shipping=?, total_amount=?,
                real_invoice_number=?, voucher_type=?, tax_rate=?,
                note=?, updated_by=?
            WHERE id=?
        ");
        $stmt->execute(array(
            !empty($data['voucher_number']) ? $data['voucher_number'] : null,
            $data['invoice_date'],
            !empty($data['case_id']) ? $data['case_id'] : null,
            !empty($data['case_number']) ? $data['case_number'] : null,
            !empty($data['customer_no']) ? $data['customer_no'] : null,
            !empty($data['customer_name']) ? $data['customer_name'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['sales_id']) ? $data['sales_id'] : null,
            !empty($data['invoice_category']) ? $data['invoice_category'] : null,
            !empty($data['status']) ? $data['status'] : '待請款',
            !empty($data['invoice_title']) ? $data['invoice_title'] : null,
            !empty($data['tax_id']) ? $data['tax_id'] : null,
            !empty($data['phone']) ? $data['phone'] : null,
            !empty($data['mobile']) ? $data['mobile'] : null,
            !empty($data['invoice_email']) ? $data['invoice_email'] : null,
            !empty($data['invoice_address']) ? $data['invoice_address'] : null,
            !empty($data['payment_method']) ? $data['payment_method'] : null,
            !empty($data['payment_terms']) ? $data['payment_terms'] : null,
            isset($data['deposit']) ? (int)$data['deposit'] : 0,
            isset($data['discount']) ? (int)$data['discount'] : 0,
            isset($data['subtotal']) ? (int)$data['subtotal'] : 0,
            isset($data['tax']) ? (int)$data['tax'] : 0,
            isset($data['shipping']) ? (int)$data['shipping'] : 0,
            isset($data['total_amount']) ? (int)$data['total_amount'] : 0,
            !empty($data['real_invoice_number']) ? $data['real_invoice_number'] : null,
            !empty($data['voucher_type']) ? $data['voucher_type'] : null,
            !empty($data['tax_rate']) ? $data['tax_rate'] : null,
            !empty($data['note']) ? $data['note'] : null,
            $data['updated_by'],
            $id,
        ));
    }

    public function deleteReceivable($id)
    {
        $stmt = $this->db->prepare("DELETE FROM receivables WHERE id = ?");
        $stmt->execute(array($id));
    }

    public function saveReceivableItems($receivableId, $items)
    {
        $this->db->prepare("DELETE FROM receivable_items WHERE receivable_id = ?")->execute(array($receivableId));
        if (empty($items)) return;
        $stmt = $this->db->prepare("INSERT INTO receivable_items (receivable_id, main_case_number, merge_case_number, item_name, unit_price, quantity, amount, note, sort_order) VALUES (?,?,?,?,?,?,?,?,?)");
        $sort = 0;
        foreach ($items as $item) {
            if (empty($item['item_name']) && empty($item['main_case_number']) && empty($item['amount'])) continue;
            $unitPrice = !empty($item['unit_price']) ? (int)$item['unit_price'] : 0;
            $qty = !empty($item['quantity']) ? (float)$item['quantity'] : 1;
            $amount = !empty($item['amount']) ? (int)$item['amount'] : ($unitPrice * $qty);
            $stmt->execute(array(
                $receivableId,
                !empty($item['main_case_number']) ? $item['main_case_number'] : null,
                !empty($item['merge_case_number']) ? $item['merge_case_number'] : null,
                !empty($item['item_name']) ? $item['item_name'] : null,
                $unitPrice,
                $qty,
                $amount,
                !empty($item['note']) ? $item['note'] : null,
                $sort++,
            ));
        }
    }

    // ============================================================
    // 收款單 CRUD
    // ============================================================

    public function getReceipts($filters = array(), $branchIds = array(), $page = 1, $perPage = 100)
    {
        $where = '1=1';
        $params = array();

        if (!empty($branchIds)) {
            $ph = implode(',', array_fill(0, count($branchIds), '?'));
            $where .= " AND (r.branch_id IN ({$ph}) OR r.branch_id IS NULL)";
            $params = array_merge($params, $branchIds);
        }
        if (!empty($filters['status'])) {
            $where .= ' AND r.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $kwRaw = trim($filters['keyword']);
            // 以 $ 開頭 → 搜尋金額（total_amount = 數字）
            if (preg_match('/^[\$＄]\s*([\d,]+(?:\.\d+)?)$/u', $kwRaw, $m)) {
                $amt = (float)str_replace(',', '', $m[1]);
                $where .= ' AND r.total_amount = ?';
                $params[] = $amt;
            } else {
                $where .= ' AND (r.receipt_number LIKE ? OR r.voucher_number LIKE ? OR r.billing_number LIKE ? OR r.customer_name LIKE ? OR r.note LIKE ? OR r.case_number LIKE ?)';
                $kw = '%' . $kwRaw . '%';
                $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
            }
        }
        $rDateCol = (!empty($filters['date_type']) && $filters['date_type'] === 'deposit') ? 'r.deposit_date' : 'r.register_date';
        if (!empty($filters['date_from'])) {
            $where .= " AND {$rDateCol} >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= " AND {$rDateCol} <= ?";
            $params[] = $filters['date_to'];
        }

        $sortDir = (!empty($filters['sort']) && $filters['sort'] === 'asc') ? 'ASC' : 'DESC';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM receipts r WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT r.*, b.name AS branch_name, u.real_name AS sales_name
            FROM receipts r
            LEFT JOIN branches b ON r.branch_id = b.id
            LEFT JOIN users u ON r.sales_id = u.id
            WHERE {$where}
            ORDER BY {$rDateCol} {$sortDir}, r.id {$sortDir}
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $sumStmt = $this->db->prepare("SELECT COALESCE(SUM(r.total_amount),0) FROM receipts r WHERE {$where}");
        $sumStmt->execute($params);
        $sumAmount = (float)$sumStmt->fetchColumn();

        return array(
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'sum_amount' => $sumAmount,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        );
    }

    public function getReceipt($id)
    {
        $stmt = $this->db->prepare("
            SELECT r.*, b.name AS branch_name, u.real_name AS sales_name
            FROM receipts r
            LEFT JOIN branches b ON r.branch_id = b.id
            LEFT JOIN users u ON r.sales_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getReceiptItems($receiptId)
    {
        $stmt = $this->db->prepare("SELECT * FROM receipt_items WHERE receipt_id = ? ORDER BY id");
        $stmt->execute(array($receiptId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createReceipt($data)
    {
        $number = $this->generateNumber('RC', 'receipts', 'receipt_number');
        $stmt = $this->db->prepare("
            INSERT INTO receipts (receipt_number, voucher_number, billing_number, register_date, deposit_date, customer_name, receivable_id,
                case_id, case_number, customer_no, sales_id, branch_id, subtotal, tax, discount, total_amount, receipt_method,
                invoice_category, status, bank_ref, note, registrar, created_by, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute(array(
            $number,
            !empty($data['voucher_number']) ? $data['voucher_number'] : null,
            !empty($data['billing_number']) ? $data['billing_number'] : null,
            $data['register_date'],
            !empty($data['deposit_date']) ? $data['deposit_date'] : null,
            !empty($data['customer_name']) ? $data['customer_name'] : null,
            !empty($data['receivable_id']) ? $data['receivable_id'] : null,
            !empty($data['case_id']) ? $data['case_id'] : null,
            !empty($data['case_number']) ? $data['case_number'] : null,
            !empty($data['customer_no']) ? $data['customer_no'] : null,
            !empty($data['sales_id']) ? $data['sales_id'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['subtotal']) ? $data['subtotal'] : 0,
            !empty($data['tax']) ? $data['tax'] : 0,
            !empty($data['discount']) ? $data['discount'] : 0,
            !empty($data['total_amount']) ? $data['total_amount'] : 0,
            !empty($data['receipt_method']) ? $data['receipt_method'] : null,
            !empty($data['invoice_category']) ? $data['invoice_category'] : null,
            !empty($data['status']) ? $data['status'] : '待收款',
            !empty($data['bank_ref']) ? $data['bank_ref'] : null,
            !empty($data['note']) ? $data['note'] : null,
            !empty($data['registrar']) ? $data['registrar'] : null,
            $data['created_by'],
            $data['created_by'],
        ));
        return $this->db->lastInsertId();
    }

    public function updateReceipt($id, $data)
    {
        // 註：registrar 由建立時寫入，更新時不變動
        $stmt = $this->db->prepare("
            UPDATE receipts SET
                voucher_number=?, billing_number=?, register_date=?, deposit_date=?, customer_name=?, receivable_id=?, case_id=?, case_number=?, customer_no=?,
                sales_id=?, branch_id=?, subtotal=?, tax=?, discount=?, total_amount=?,
                receipt_method=?, invoice_category=?, status=?, bank_ref=?, note=?, updated_by=?
            WHERE id=?
        ");
        $stmt->execute(array(
            !empty($data['voucher_number']) ? $data['voucher_number'] : null,
            !empty($data['billing_number']) ? $data['billing_number'] : null,
            $data['register_date'],
            !empty($data['deposit_date']) ? $data['deposit_date'] : null,
            !empty($data['customer_name']) ? $data['customer_name'] : null,
            !empty($data['receivable_id']) ? $data['receivable_id'] : null,
            !empty($data['case_id']) ? $data['case_id'] : null,
            !empty($data['case_number']) ? $data['case_number'] : null,
            !empty($data['customer_no']) ? $data['customer_no'] : null,
            !empty($data['sales_id']) ? $data['sales_id'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['subtotal']) ? $data['subtotal'] : 0,
            !empty($data['tax']) ? $data['tax'] : 0,
            !empty($data['discount']) ? $data['discount'] : 0,
            !empty($data['total_amount']) ? $data['total_amount'] : 0,
            !empty($data['receipt_method']) ? $data['receipt_method'] : null,
            !empty($data['invoice_category']) ? $data['invoice_category'] : null,
            !empty($data['status']) ? $data['status'] : '待收款',
            !empty($data['bank_ref']) ? $data['bank_ref'] : null,
            !empty($data['note']) ? $data['note'] : null,
            $data['updated_by'],
            $id,
        ));
    }

    public function deleteReceipt($id)
    {
        $this->db->prepare("DELETE FROM receipts WHERE id = ?")->execute(array($id));
    }

    public function saveReceiptItems($receiptId, $items)
    {
        $this->db->prepare("DELETE FROM receipt_items WHERE receipt_id = ?")->execute(array($receiptId));
        if (empty($items)) return;
        $stmt = $this->db->prepare("INSERT INTO receipt_items (receipt_id, main_case_number, merge_case_number, amount, note) VALUES (?,?,?,?,?)");
        foreach ($items as $item) {
            if (empty($item['main_case_number']) && empty($item['amount'])) continue;
            $stmt->execute(array(
                $receiptId,
                !empty($item['main_case_number']) ? $item['main_case_number'] : null,
                !empty($item['merge_case_number']) ? $item['merge_case_number'] : null,
                !empty($item['amount']) ? $item['amount'] : 0,
                !empty($item['note']) ? $item['note'] : null,
            ));
        }
    }

    // ============================================================
    // 應付帳款 CRUD
    // ============================================================

    public function getPayables($filters = array(), $page = 1, $perPage = 100)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['keyword'])) {
            $kwRaw = trim($filters['keyword']);
            if (preg_match('/^[\$＄]\s*([\d,]+(?:\.\d+)?)$/u', $kwRaw, $m)) {
                $amt = (float)str_replace(',', '', $m[1]);
                $where .= ' AND p.total_amount = ?';
                $params[] = $amt;
            } else {
                $where .= ' AND (p.payable_number LIKE ? OR p.voucher_number LIKE ? OR p.vendor_name LIKE ? OR p.note LIKE ?)';
                $kw = '%' . $kwRaw . '%';
                $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
            }
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND p.create_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND p.create_date <= ?';
            $params[] = $filters['date_to'];
        }

        $sortDir = (!empty($filters['sort']) && $filters['sort'] === 'asc') ? 'ASC' : 'DESC';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM payables p WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT p.* FROM payables p WHERE {$where} ORDER BY p.create_date {$sortDir}, p.id {$sortDir} LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return array(
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        );
    }

    public function getPayable($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM payables WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPayableBranches($payableId)
    {
        $stmt = $this->db->prepare("
            SELECT pb.*, b.name AS branch_name
            FROM payable_branches pb
            LEFT JOIN branches b ON pb.branch_id = b.id
            WHERE pb.payable_id = ? ORDER BY pb.id
        ");
        $stmt->execute(array($payableId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPayableInvoices($payableId)
    {
        $stmt = $this->db->prepare("SELECT * FROM payable_invoices WHERE payable_id = ? ORDER BY id");
        $stmt->execute(array($payableId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPayable($data)
    {
        $number = $this->generateNumber('AP', 'payables', 'payable_number');
        // 註：case_number / customer_no 已從表單移除（DB 欄位保留以相容舊資料）
        $stmt = $this->db->prepare("
            INSERT INTO payables (payable_number, create_date, vendor_name, vendor_code, payment_period, payment_terms,
                subtotal, tax, total_amount, prepaid, payable_amount, note, registrar, created_by, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute(array(
            $number,
            $data['create_date'],
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
            !empty($data['vendor_code']) ? $data['vendor_code'] : null,
            !empty($data['payment_period']) ? $data['payment_period'] : null,
            !empty($data['payment_terms']) ? $data['payment_terms'] : null,
            !empty($data['subtotal']) ? $data['subtotal'] : 0,
            !empty($data['tax']) ? $data['tax'] : 0,
            !empty($data['total_amount']) ? $data['total_amount'] : 0,
            !empty($data['prepaid']) ? $data['prepaid'] : 0,
            !empty($data['payable_amount']) ? $data['payable_amount'] : 0,
            !empty($data['note']) ? $data['note'] : null,
            !empty($data['registrar']) ? $data['registrar'] : null,
            $data['created_by'],
            $data['created_by'],
        ));
        return $this->db->lastInsertId();
    }

    public function updatePayable($id, $data)
    {
        // 註：case_number / customer_no 不在 UPDATE 範圍，舊資料原值保留
        $stmt = $this->db->prepare("
            UPDATE payables SET
                create_date=?, vendor_name=?, vendor_code=?, payment_period=?, payment_terms=?,
                subtotal=?, tax=?, total_amount=?, prepaid=?, payable_amount=?, note=?, updated_by=?
            WHERE id=?
        ");
        $stmt->execute(array(
            $data['create_date'],
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
            !empty($data['vendor_code']) ? $data['vendor_code'] : null,
            !empty($data['payment_period']) ? $data['payment_period'] : null,
            !empty($data['payment_terms']) ? $data['payment_terms'] : null,
            !empty($data['subtotal']) ? $data['subtotal'] : 0,
            !empty($data['tax']) ? $data['tax'] : 0,
            !empty($data['total_amount']) ? $data['total_amount'] : 0,
            !empty($data['prepaid']) ? $data['prepaid'] : 0,
            !empty($data['payable_amount']) ? $data['payable_amount'] : 0,
            !empty($data['note']) ? $data['note'] : null,
            $data['updated_by'],
            $id,
        ));
    }

    public function deletePayable($id)
    {
        $this->db->prepare("DELETE FROM payables WHERE id = ?")->execute(array($id));
    }

    public function savePayableBranches($payableId, $branches)
    {
        $this->db->prepare("DELETE FROM payable_branches WHERE payable_id = ?")->execute(array($payableId));
        if (empty($branches)) return;
        $stmt = $this->db->prepare("INSERT INTO payable_branches (payable_id, branch_id, amount, note) VALUES (?,?,?,?)");
        foreach ($branches as $b) {
            if (empty($b['branch_id']) && empty($b['amount'])) continue;
            $stmt->execute(array(
                $payableId,
                !empty($b['branch_id']) ? $b['branch_id'] : null,
                !empty($b['amount']) ? $b['amount'] : 0,
                !empty($b['note']) ? $b['note'] : null,
            ));
        }
    }

    public function savePayableInvoices($payableId, $invoices)
    {
        $this->db->prepare("DELETE FROM payable_invoices WHERE payable_id = ?")->execute(array($payableId));
        if (empty($invoices)) return;
        $stmt = $this->db->prepare("INSERT INTO payable_invoices (payable_id, invoice_date, invoice_number, tax_id, amount_untaxed, tax, subtotal) VALUES (?,?,?,?,?,?,?)");
        foreach ($invoices as $inv) {
            if (empty($inv['invoice_number']) && empty($inv['amount_untaxed'])) continue;
            $stmt->execute(array(
                $payableId,
                !empty($inv['invoice_date']) ? $inv['invoice_date'] : null,
                !empty($inv['invoice_number']) ? $inv['invoice_number'] : null,
                !empty($inv['tax_id']) ? $inv['tax_id'] : null,
                !empty($inv['amount_untaxed']) ? $inv['amount_untaxed'] : 0,
                !empty($inv['tax']) ? $inv['tax'] : 0,
                !empty($inv['subtotal']) ? $inv['subtotal'] : 0,
            ));
        }
    }

    // ---- 進貨明細 ----
    public function getPayablePurchaseDetails($payableId)
    {
        $stmt = $this->db->prepare("
            SELECT pd.*, gr.id AS gr_id
            FROM payable_purchase_details pd
            LEFT JOIN goods_receipts gr ON pd.purchase_number IS NOT NULL AND pd.purchase_number <> '' AND gr.gr_number = pd.purchase_number
            WHERE pd.payable_id = ?
            ORDER BY pd.sort_order, pd.id
        ");
        $stmt->execute(array($payableId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function savePayablePurchaseDetails($payableId, $items)
    {
        $this->db->prepare("DELETE FROM payable_purchase_details WHERE payable_id = ?")->execute(array($payableId));
        if (empty($items)) return;
        $stmt = $this->db->prepare("INSERT INTO payable_purchase_details (payable_id, check_month, purchase_date, purchase_number, branch_name, vendor_name, amount_untaxed, tax_amount, total_amount, paid_amount, payment_date, invoice_date, invoice_track, invoice_amount, monthly_check, note, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $sort = 0;
        foreach ($items as $item) {
            if (empty($item['purchase_number']) && empty($item['amount_untaxed'])) continue;
            $stmt->execute(array(
                $payableId,
                !empty($item['check_month']) ? $item['check_month'] : null,
                !empty($item['purchase_date']) ? $item['purchase_date'] : null,
                !empty($item['purchase_number']) ? $item['purchase_number'] : null,
                !empty($item['branch_name']) ? $item['branch_name'] : null,
                !empty($item['vendor_name']) ? $item['vendor_name'] : null,
                !empty($item['amount_untaxed']) ? (int)$item['amount_untaxed'] : 0,
                !empty($item['tax_amount']) ? (int)$item['tax_amount'] : 0,
                !empty($item['total_amount']) ? (int)$item['total_amount'] : 0,
                !empty($item['paid_amount']) ? (int)$item['paid_amount'] : 0,
                !empty($item['payment_date']) ? $item['payment_date'] : null,
                !empty($item['invoice_date']) ? $item['invoice_date'] : null,
                !empty($item['invoice_track']) ? $item['invoice_track'] : null,
                !empty($item['invoice_amount']) ? (int)$item['invoice_amount'] : 0,
                !empty($item['monthly_check']) ? $item['monthly_check'] : null,
                !empty($item['note']) ? $item['note'] : null,
                $sort++,
            ));
        }
    }

    // ---- 進退明細 ----
    public function getPayableReturnDetails($payableId)
    {
        $stmt = $this->db->prepare("
            SELECT rd.*, gr.id AS gr_id
            FROM payable_return_details rd
            LEFT JOIN goods_receipts gr ON rd.purchase_number IS NOT NULL AND rd.purchase_number <> '' AND gr.gr_number = rd.purchase_number
            WHERE rd.payable_id = ?
            ORDER BY rd.sort_order, rd.id
        ");
        $stmt->execute(array($payableId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function savePayableReturnDetails($payableId, $items)
    {
        $this->db->prepare("DELETE FROM payable_return_details WHERE payable_id = ?")->execute(array($payableId));
        if (empty($items)) return;
        $stmt = $this->db->prepare("INSERT INTO payable_return_details (payable_id, return_date, return_number, purchase_number, vendor_name, doc_status, branch_name, warehouse_name, refund_amount, return_reason, accounting_method, allowance_doc, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $sort = 0;
        foreach ($items as $item) {
            if (empty($item['return_number']) && empty($item['refund_amount'])) continue;
            $stmt->execute(array(
                $payableId,
                !empty($item['return_date']) ? $item['return_date'] : null,
                !empty($item['return_number']) ? $item['return_number'] : null,
                !empty($item['purchase_number']) ? $item['purchase_number'] : null,
                !empty($item['vendor_name']) ? $item['vendor_name'] : null,
                !empty($item['doc_status']) ? $item['doc_status'] : null,
                !empty($item['branch_name']) ? $item['branch_name'] : null,
                !empty($item['warehouse_name']) ? $item['warehouse_name'] : null,
                !empty($item['refund_amount']) ? (int)$item['refund_amount'] : 0,
                !empty($item['return_reason']) ? $item['return_reason'] : null,
                !empty($item['accounting_method']) ? $item['accounting_method'] : null,
                !empty($item['allowance_doc']) ? $item['allowance_doc'] : null,
                $sort++,
            ));
        }
    }

    // ============================================================
    // 付款單 CRUD
    // ============================================================

    public function getPaymentsOut($filters = array(), $page = 1, $perPage = 100)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['status'])) {
            $where .= ' AND p.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['main_category'])) {
            $where .= ' AND p.main_category = ?';
            $params[] = $filters['main_category'];
        }
        if (!empty($filters['keyword'])) {
            $kwRaw = trim($filters['keyword']);
            if (preg_match('/^[\$＄]\s*([\d,]+(?:\.\d+)?)$/u', $kwRaw, $m)) {
                $amt = (float)str_replace(',', '', $m[1]);
                $where .= ' AND p.total_amount = ?';
                $params[] = $amt;
            } else {
                $where .= ' AND (p.payment_number LIKE ? OR p.vendor_name LIKE ? OR p.note LIKE ?)';
                $kw = '%' . $kwRaw . '%';
                $params[] = $kw; $params[] = $kw; $params[] = $kw;
            }
        }
        if (!empty($filters['branch_id'])) {
            $where .= ' AND EXISTS (SELECT 1 FROM payment_out_branches pob WHERE pob.payment_out_id = p.id AND pob.branch_id = ?)';
            $params[] = (int)$filters['branch_id'];
        }
        $dateCol = (!empty($filters['date_type']) && $filters['date_type'] === 'payment') ? 'p.payment_date' : 'p.create_date';
        if (!empty($filters['date_from'])) {
            $where .= " AND {$dateCol} >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= " AND {$dateCol} <= ?";
            $params[] = $filters['date_to'];
        }

        $sortDir = (!empty($filters['sort']) && $filters['sort'] === 'asc') ? 'ASC' : 'DESC';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM payments_out p WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT p.* FROM payments_out p WHERE {$where} ORDER BY {$dateCol} {$sortDir}, p.id {$sortDir} LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $sumStmt = $this->db->prepare("SELECT COALESCE(SUM(p.total_amount),0) FROM payments_out p WHERE {$where}");
        $sumStmt->execute($params);
        $sumAmount = (float)$sumStmt->fetchColumn();

        return array(
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'sum_amount' => $sumAmount,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        );
    }

    public function getPaymentOut($id)
    {
        $stmt = $this->db->prepare("
            SELECT po.*, p.payable_number
            FROM payments_out po
            LEFT JOIN payables p ON po.payable_id = p.id
            WHERE po.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPaymentOutBranches($paymentOutId)
    {
        $stmt = $this->db->prepare("
            SELECT pb.*, b.name AS branch_name
            FROM payment_out_branches pb
            LEFT JOIN branches b ON pb.branch_id = b.id
            WHERE pb.payment_out_id = ? ORDER BY pb.id
        ");
        $stmt->execute(array($paymentOutId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPaymentOutVouchers($paymentOutId)
    {
        $stmt = $this->db->prepare("SELECT * FROM payment_out_vouchers WHERE payment_out_id = ? ORDER BY id");
        $stmt->execute(array($paymentOutId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPaymentOut($data)
    {
        $number = $this->generateNumber('PO', 'payments_out', 'payment_number');
        // 註：case_number / customer_no 已從表單移除（DB 欄位保留以相容舊資料）
        // 防呆：exclude_from_branch_stats 欄位可能還沒建立（migration 112 未跑）→ try/catch
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payments_out (payment_number, create_date, payment_date, payable_id, vendor_name, vendor_code,
                    payment_method, payment_type, payment_terms, status, subtotal, tax, remittance_fee,
                    total_amount, main_category, sub_category, note, exclude_from_branch_stats, registrar, created_by, updated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute(array(
                $number,
                $data['create_date'],
                !empty($data['payment_date']) ? $data['payment_date'] : null,
                !empty($data['payable_id']) ? $data['payable_id'] : null,
                !empty($data['vendor_name']) ? $data['vendor_name'] : null,
                !empty($data['vendor_code']) ? $data['vendor_code'] : null,
                !empty($data['payment_method']) ? $data['payment_method'] : null,
                !empty($data['payment_type']) ? $data['payment_type'] : null,
                !empty($data['payment_terms']) ? $data['payment_terms'] : null,
                !empty($data['status']) ? $data['status'] : '待付款',
                !empty($data['subtotal']) ? $data['subtotal'] : 0,
                !empty($data['tax']) ? $data['tax'] : 0,
                !empty($data['remittance_fee']) ? $data['remittance_fee'] : 0,
                !empty($data['total_amount']) ? $data['total_amount'] : 0,
                !empty($data['main_category']) ? $data['main_category'] : null,
                !empty($data['sub_category']) ? $data['sub_category'] : null,
                !empty($data['note']) ? $data['note'] : null,
                !empty($data['exclude_from_branch_stats']) ? 1 : 0,
                !empty($data['registrar']) ? $data['registrar'] : null,
                $data['created_by'],
                $data['created_by'],
            ));
        } catch (Exception $e) {
            // Fallback：欄位不存在 → 不寫入 exclude_from_branch_stats
            $stmt = $this->db->prepare("
                INSERT INTO payments_out (payment_number, create_date, payment_date, payable_id, vendor_name, vendor_code,
                    payment_method, payment_type, payment_terms, status, subtotal, tax, remittance_fee,
                    total_amount, main_category, sub_category, note, registrar, created_by, updated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute(array(
                $number,
                $data['create_date'],
                !empty($data['payment_date']) ? $data['payment_date'] : null,
                !empty($data['payable_id']) ? $data['payable_id'] : null,
                !empty($data['vendor_name']) ? $data['vendor_name'] : null,
                !empty($data['vendor_code']) ? $data['vendor_code'] : null,
                !empty($data['payment_method']) ? $data['payment_method'] : null,
                !empty($data['payment_type']) ? $data['payment_type'] : null,
                !empty($data['payment_terms']) ? $data['payment_terms'] : null,
                !empty($data['status']) ? $data['status'] : '待付款',
                !empty($data['subtotal']) ? $data['subtotal'] : 0,
                !empty($data['tax']) ? $data['tax'] : 0,
                !empty($data['remittance_fee']) ? $data['remittance_fee'] : 0,
                !empty($data['total_amount']) ? $data['total_amount'] : 0,
                !empty($data['main_category']) ? $data['main_category'] : null,
                !empty($data['sub_category']) ? $data['sub_category'] : null,
                !empty($data['note']) ? $data['note'] : null,
                !empty($data['registrar']) ? $data['registrar'] : null,
                $data['created_by'],
                $data['created_by'],
            ));
        }
        return $this->db->lastInsertId();
    }

    public function updatePaymentOut($id, $data)
    {
        // 註：case_number / customer_no 不在 UPDATE 範圍，舊資料原值保留
        // 防呆：exclude_from_branch_stats 欄位可能還沒建立 → try/catch
        try {
            $stmt = $this->db->prepare("
                UPDATE payments_out SET
                    create_date=?, payment_date=?, payable_id=?, vendor_name=?, vendor_code=?,
                    payment_method=?, payment_type=?, payment_terms=?, status=?,
                    subtotal=?, tax=?, remittance_fee=?, total_amount=?,
                    main_category=?, sub_category=?, note=?, exclude_from_branch_stats=?, updated_by=?
                WHERE id=?
            ");
            $stmt->execute(array(
                $data['create_date'],
                !empty($data['payment_date']) ? $data['payment_date'] : null,
                !empty($data['payable_id']) ? $data['payable_id'] : null,
                !empty($data['vendor_name']) ? $data['vendor_name'] : null,
                !empty($data['vendor_code']) ? $data['vendor_code'] : null,
                !empty($data['payment_method']) ? $data['payment_method'] : null,
                !empty($data['payment_type']) ? $data['payment_type'] : null,
                !empty($data['payment_terms']) ? $data['payment_terms'] : null,
                !empty($data['status']) ? $data['status'] : '待付款',
                !empty($data['subtotal']) ? $data['subtotal'] : 0,
                !empty($data['tax']) ? $data['tax'] : 0,
                !empty($data['remittance_fee']) ? $data['remittance_fee'] : 0,
                !empty($data['total_amount']) ? $data['total_amount'] : 0,
                !empty($data['main_category']) ? $data['main_category'] : null,
                !empty($data['sub_category']) ? $data['sub_category'] : null,
                !empty($data['note']) ? $data['note'] : null,
                !empty($data['exclude_from_branch_stats']) ? 1 : 0,
                $data['updated_by'],
                $id,
            ));
        } catch (Exception $e) {
            // Fallback：欄位不存在 → 不寫入 exclude_from_branch_stats
            $stmt = $this->db->prepare("
                UPDATE payments_out SET
                    create_date=?, payment_date=?, payable_id=?, vendor_name=?, vendor_code=?,
                    payment_method=?, payment_type=?, payment_terms=?, status=?,
                    subtotal=?, tax=?, remittance_fee=?, total_amount=?,
                    main_category=?, sub_category=?, note=?, updated_by=?
                WHERE id=?
            ");
            $stmt->execute(array(
                $data['create_date'],
                !empty($data['payment_date']) ? $data['payment_date'] : null,
                !empty($data['payable_id']) ? $data['payable_id'] : null,
                !empty($data['vendor_name']) ? $data['vendor_name'] : null,
                !empty($data['vendor_code']) ? $data['vendor_code'] : null,
                !empty($data['payment_method']) ? $data['payment_method'] : null,
                !empty($data['payment_type']) ? $data['payment_type'] : null,
                !empty($data['payment_terms']) ? $data['payment_terms'] : null,
                !empty($data['status']) ? $data['status'] : '待付款',
                !empty($data['subtotal']) ? $data['subtotal'] : 0,
                !empty($data['tax']) ? $data['tax'] : 0,
                !empty($data['remittance_fee']) ? $data['remittance_fee'] : 0,
                !empty($data['total_amount']) ? $data['total_amount'] : 0,
                !empty($data['main_category']) ? $data['main_category'] : null,
                !empty($data['sub_category']) ? $data['sub_category'] : null,
                !empty($data['note']) ? $data['note'] : null,
                $data['updated_by'],
                $id,
            ));
        }
    }

    public function deletePaymentOut($id)
    {
        $this->db->prepare("DELETE FROM payments_out WHERE id = ?")->execute(array($id));
    }

    public function savePaymentOutBranches($paymentOutId, $branches)
    {
        $this->db->prepare("DELETE FROM payment_out_branches WHERE payment_out_id = ?")->execute(array($paymentOutId));
        if (empty($branches)) return;
        $stmt = $this->db->prepare("INSERT INTO payment_out_branches (payment_out_id, branch_id, amount, note) VALUES (?,?,?,?)");
        foreach ($branches as $b) {
            if (empty($b['branch_id']) && empty($b['amount'])) continue;
            $stmt->execute(array(
                $paymentOutId,
                !empty($b['branch_id']) ? $b['branch_id'] : null,
                !empty($b['amount']) ? $b['amount'] : 0,
                !empty($b['note']) ? $b['note'] : null,
            ));
        }
    }

    public function savePaymentOutVouchers($paymentOutId, $vouchers)
    {
        $this->db->prepare("DELETE FROM payment_out_vouchers WHERE payment_out_id = ?")->execute(array($paymentOutId));
        if (empty($vouchers)) return;
        $stmt = $this->db->prepare("INSERT INTO payment_out_vouchers (payment_out_id, voucher_type, amount) VALUES (?,?,?)");
        foreach ($vouchers as $v) {
            if (empty($v['voucher_type']) && empty($v['amount'])) continue;
            $stmt->execute(array(
                $paymentOutId,
                !empty($v['voucher_type']) ? $v['voucher_type'] : null,
                !empty($v['amount']) ? $v['amount'] : 0,
            ));
        }
    }

    // ============================================================
    // 通用查詢
    // ============================================================

    public function getBranches($branchIds = array())
    {
        if (!empty($branchIds)) {
            $ph = implode(',', array_fill(0, count($branchIds), '?'));
            $stmt = $this->db->prepare("SELECT id, name FROM branches WHERE id IN ({$ph}) ORDER BY id");
            $stmt->execute($branchIds);
        } else {
            $stmt = $this->db->query("SELECT id, name FROM branches ORDER BY id");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSalesUsers($branchIds = array())
    {
        $where = "u.is_active = 1 AND (u.is_sales = 1 OR u.role IN ('sales','sales_manager','boss'))";
        $params = array();
        $stmt = $this->db->prepare("SELECT u.id, u.real_name, b.name AS branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE {$where} ORDER BY u.real_name");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========== 銀行帳戶交易明細 ==========

    public static function bankAccountOptions()
    {
        return array(
            '王正宏-彰化銀行' => '王正宏-彰化銀行',
            '政遠企業有限公司-中國信託' => '政遠企業有限公司-中國信託',
            '禾順監視數位科技有限公司-中國信託' => '禾順監視數位科技有限公司-中國信託',
            '禾順監視數位科技有限公司-富邦' => '禾順監視數位科技有限公司-富邦',
            '週轉金' => '週轉金',
        );
    }

    /**
     * 取得單筆銀行交易
     */
    public function getBankTransaction($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM bank_transactions WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 新增銀行交易
     */
    public function createBankTransaction($data)
    {
        // 自動產生銀行交易編號（BT-2026-000001）
        $transactionNumber = generate_doc_number('bank_transactions', !empty($data['transaction_date']) ? $data['transaction_date'] : null);

        $stmt = $this->db->prepare("INSERT INTO bank_transactions
            (transaction_number, bank_account, transaction_date, summary, debit_amount, credit_amount, balance, description, remark, upload_no, remittance_code, counterparty_account, memo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(array(
            $transactionNumber,
            $data['bank_account'],
            $data['transaction_date'],
            isset($data['summary']) ? $data['summary'] : '',
            isset($data['debit_amount']) ? $data['debit_amount'] : 0,
            isset($data['credit_amount']) ? $data['credit_amount'] : 0,
            isset($data['balance']) ? $data['balance'] : 0,
            isset($data['description']) ? $data['description'] : '',
            isset($data['remark']) ? $data['remark'] : '',
            isset($data['upload_no']) ? ($data['upload_no'] ?: null) : null,
            isset($data['remittance_code']) ? ($data['remittance_code'] ?: null) : null,
            isset($data['counterparty_account']) ? ($data['counterparty_account'] ?: null) : null,
            isset($data['memo']) ? ($data['memo'] ?: null) : null,
        ));
        return $this->db->lastInsertId();
    }

    /**
     * 更新銀行交易
     */
    public function updateBankTransaction($id, $data)
    {
        $stmt = $this->db->prepare("UPDATE bank_transactions SET
            bank_account = ?, transaction_date = ?, summary = ?,
            debit_amount = ?, credit_amount = ?, balance = ?,
            description = ?, remark = ?,
            upload_no = ?, remittance_code = ?, counterparty_account = ?, memo = ?
            WHERE id = ?");
        $stmt->execute(array(
            $data['bank_account'],
            $data['transaction_date'],
            isset($data['summary']) ? $data['summary'] : '',
            isset($data['debit_amount']) ? $data['debit_amount'] : 0,
            isset($data['credit_amount']) ? $data['credit_amount'] : 0,
            isset($data['balance']) ? $data['balance'] : 0,
            isset($data['description']) ? $data['description'] : '',
            isset($data['remark']) ? $data['remark'] : '',
            isset($data['upload_no']) ? ($data['upload_no'] ?: null) : null,
            isset($data['remittance_code']) ? ($data['remittance_code'] ?: null) : null,
            isset($data['counterparty_account']) ? ($data['counterparty_account'] ?: null) : null,
            isset($data['memo']) ? ($data['memo'] ?: null) : null,
            $id,
        ));
    }

    /**
     * 刪除銀行交易
     */
    public function deleteBankTransaction($id)
    {
        $stmt = $this->db->prepare("DELETE FROM bank_transactions WHERE id = ?");
        $stmt->execute(array($id));
    }

    public static function approvalStatusOptions()
    {
        return array(
            '送簽核' => '送簽核',
            '已核准' => '已核准',
            '退回'   => '退回',
        );
    }

    public static function incomeExpenseOptions()
    {
        return array(
            '收入' => '收入',
            '支出' => '支出',
        );
    }

    public function getBankTransactions($filters = array(), $page = 1, $perPage = 100)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['bank_account'])) {
            $where .= ' AND bank_account = ?';
            $params[] = $filters['bank_account'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND transaction_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND transaction_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (summary LIKE ? OR description LIKE ? OR remark LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM bank_transactions WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("SELECT * FROM bank_transactions WHERE {$where} ORDER BY transaction_date DESC, id DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);

        return array(
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        );
    }

    /**
     * 銀行帳戶彙總：每帳戶最新餘額、轉入合計、轉出合計
     */
    public function getBankSummary($filters = array())
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['bank_account'])) {
            $where .= ' AND bank_account = ?';
            $params[] = $filters['bank_account'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND transaction_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND transaction_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (summary LIKE ? OR description LIKE ? OR remark LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        // 轉入/轉出合計 per bank account
        $sql = "SELECT bank_account,
                    SUM(credit_amount) AS total_in,
                    SUM(debit_amount) AS total_out
                FROM bank_transactions
                WHERE {$where}
                GROUP BY bank_account
                ORDER BY bank_account";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 最新餘額（不受篩選影響，取各帳戶最新一筆）
        $sql2 = "SELECT bank_account, balance
                 FROM bank_transactions t1
                 WHERE id = (SELECT id FROM bank_transactions t2 WHERE t2.bank_account = t1.bank_account ORDER BY transaction_date DESC, id DESC LIMIT 1)
                 ORDER BY bank_account";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute();
        $balances = array();
        foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $balances[$b['bank_account']] = $b['balance'];
        }

        $summary = array();
        $totalIn = 0;
        $totalOut = 0;
        $totalBalance = 0;
        foreach ($rows as $row) {
            $acct = $row['bank_account'];
            $bal = isset($balances[$acct]) ? $balances[$acct] : 0;
            $summary[] = array(
                'bank_account' => $acct,
                'total_in' => $row['total_in'],
                'total_out' => $row['total_out'],
                'balance' => $bal,
            );
            $totalIn += $row['total_in'];
            $totalOut += $row['total_out'];
            $totalBalance += $bal;
        }

        return array(
            'accounts' => $summary,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'total_balance' => $totalBalance,
            'revolving_fund' => 4517368,
        );
    }

    // ========== 零用金管理 ==========

    /**
     * 取得各分公司零用金最新餘額
     */
    public function getPettyCashBranchBalances($branchIds)
    {
        if (empty($branchIds)) return array();
        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT b.id AS branch_id, b.name AS branch_name,
                   COALESCE(SUM(pc.income_amount), 0) AS total_income,
                   COALESCE(SUM(pc.expense_amount), 0) AS total_expense,
                   COALESCE(SUM(pc.income_amount), 0) - COALESCE(SUM(pc.expense_amount), 0) AS balance
            FROM branches b
            LEFT JOIN petty_cash pc ON pc.branch_id = b.id
            WHERE b.id IN ({$placeholders}) AND b.is_active = 1
            GROUP BY b.id, b.name
            HAVING balance != 0 OR total_income > 0 OR total_expense > 0
            ORDER BY b.id
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPettyCashList($filters = array(), $page = 1, $perPage = 100, $accessibleBranchIds = null)
    {
        $where = '1=1';
        $params = array();

        // 強制分公司隔離：只能看可存取分公司的資料
        if ($accessibleBranchIds !== null) {
            if (empty($accessibleBranchIds)) {
                // 無任何可存取分公司 → 直接回空
                return array('data' => array(), 'total' => 0, 'page' => $page, 'perPage' => $perPage, 'lastPage' => 1);
            }
            $bph = implode(',', array_fill(0, count($accessibleBranchIds), '?'));
            $where .= " AND pc.branch_id IN ({$bph})";
            $params = array_merge($params, $accessibleBranchIds);
        }

        if (!empty($filters['branch_id'])) {
            $where .= ' AND pc.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['type'])) {
            $where .= ' AND pc.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (pc.description LIKE ? OR pc.entry_number LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND pc.entry_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND pc.entry_date <= ?';
            $params[] = $filters['date_to'];
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM petty_cash pc WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sortDir = (!empty($filters['sort']) && $filters['sort'] === 'asc') ? 'ASC' : 'DESC';
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT pc.*, b.name AS branch_name
            FROM petty_cash pc
            LEFT JOIN branches b ON pc.branch_id = b.id
            WHERE {$where}
            ORDER BY pc.entry_date {$sortDir}, pc.id {$sortDir}
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return array(
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        );
    }

    /**
     * Get total petty cash balance (income - expense) for all matching records.
     */
    public function getPettyCashBalanceUpTo($filters, $unused = 0, $accessibleBranchIds = null)
    {
        $where = '1=1';
        $params = array();

        if ($accessibleBranchIds !== null) {
            if (empty($accessibleBranchIds)) return 0.0;
            $bph = implode(',', array_fill(0, count($accessibleBranchIds), '?'));
            $where .= " AND pc.branch_id IN ({$bph})";
            $params = array_merge($params, $accessibleBranchIds);
        }

        if (!empty($filters['branch_id'])) {
            $where .= ' AND pc.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['type'])) {
            $where .= ' AND pc.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (pc.description LIKE ? OR pc.entry_number LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND pc.entry_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND pc.entry_date <= ?';
            $params[] = $filters['date_to'];
        }

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(pc.income_amount), 0) - COALESCE(SUM(pc.expense_amount), 0)
            FROM petty_cash pc
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Get sum of (income - expense) for the top N records (newest first).
     * Used to offset running balance for pagination.
     */
    public function getPettyCashPageSum($filters, $limit, $accessibleBranchIds = null)
    {
        $where = '1=1';
        $params = array();

        if ($accessibleBranchIds !== null) {
            if (empty($accessibleBranchIds)) return 0.0;
            $bph = implode(',', array_fill(0, count($accessibleBranchIds), '?'));
            $where .= " AND pc.branch_id IN ({$bph})";
            $params = array_merge($params, $accessibleBranchIds);
        }

        if (!empty($filters['branch_id'])) {
            $where .= ' AND pc.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['type'])) {
            $where .= ' AND pc.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (pc.description LIKE ? OR pc.entry_number LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND pc.entry_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND pc.entry_date <= ?';
            $params[] = $filters['date_to'];
        }

        $limit = (int)$limit;
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(sub.income_amount), 0) - COALESCE(SUM(sub.expense_amount), 0)
            FROM (
                SELECT pc.income_amount, pc.expense_amount
                FROM petty_cash pc
                WHERE {$where}
                ORDER BY pc.entry_date DESC, pc.id DESC
                LIMIT {$limit}
            ) sub
        ");
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /**
     * 新增零用金記錄
     */
    public function createPettyCash($data)
    {
        $number = $this->generateNumber('PC', 'petty_cash', 'entry_number');
        $type = !empty($data['type']) ? $data['type'] : '支出';
        $amount = !empty($data['amount']) ? (float)$data['amount'] : 0;
        $income = ($type === '收入') ? $amount : 0;
        $expense = ($type === '支出') ? $amount : 0;

        $stmt = $this->db->prepare("
            INSERT INTO petty_cash (entry_number, entry_date, type, income_amount, expense_amount,
                has_invoice, invoice_info, description, branch_id, registrar, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(array(
            $number,
            !empty($data['entry_date']) ? $data['entry_date'] : date('Y-m-d'),
            $type,
            $income,
            $expense,
            !empty($data['has_invoice']) ? $data['has_invoice'] : null,
            !empty($data['invoice_info']) ? $data['invoice_info'] : null,
            !empty($data['description']) ? $data['description'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['registrar']) ? $data['registrar'] : null,
        ));
        return $this->db->lastInsertId();
    }

    // ========== 零用金 getById / update / delete ==========

    public function getPettyCashById($id)
    {
        $stmt = $this->db->prepare("
            SELECT pc.*, b.name AS branch_name, u.real_name AS updater_name
            FROM petty_cash pc
            LEFT JOIN branches b ON pc.branch_id = b.id
            LEFT JOIN users u ON pc.updated_by = u.id
            WHERE pc.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePettyCash($id, $data)
    {
        $type = !empty($data['type']) ? $data['type'] : '支出';
        $amount = !empty($data['amount']) ? (float)$data['amount'] : 0;
        $income = ($type === '收入') ? $amount : 0;
        $expense = ($type === '支出') ? $amount : 0;

        $stmt = $this->db->prepare("
            UPDATE petty_cash SET
                entry_date = ?, type = ?, income_amount = ?, expense_amount = ?,
                has_invoice = ?, invoice_info = ?, description = ?, branch_id = ?,
                registrar = ?, approval_status = ?, updated_at = NOW(), updated_by = ?
            WHERE id = ?
        ");
        $stmt->execute(array(
            !empty($data['entry_date']) ? $data['entry_date'] : null,
            $type,
            $income,
            $expense,
            !empty($data['has_invoice']) ? $data['has_invoice'] : null,
            !empty($data['invoice_info']) ? $data['invoice_info'] : null,
            !empty($data['description']) ? $data['description'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['registrar']) ? $data['registrar'] : null,
            !empty($data['approval_status']) ? $data['approval_status'] : null,
            Auth::id(),
            $id,
        ));
        return $stmt->rowCount();
    }

    public function deletePettyCash($id)
    {
        $stmt = $this->db->prepare("DELETE FROM petty_cash WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->rowCount();
    }

    // ========== 備用金管理 ==========

    private function buildReserveFundWhere($filters, &$params)
    {
        $where = '1=1';
        if (!empty($filters['branch_id'])) {
            $where .= ' AND rf.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['type'])) {
            $where .= ' AND rf.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND rf.expense_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND rf.expense_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (rf.description LIKE ? OR rf.entry_number LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }
        return $where;
    }

    public function getReserveFundBalanceUpTo($filters)
    {
        $params = array();
        $where = $this->buildReserveFundWhere($filters, $params);

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(rf.income_amount), 0) - COALESCE(SUM(rf.expense_amount), 0)
            FROM reserve_fund rf
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    public function getReserveFundPageSum($filters, $limit)
    {
        $params = array();
        $where = $this->buildReserveFundWhere($filters, $params);
        $sortDir = (!empty($filters['sort']) && $filters['sort'] === 'asc') ? 'ASC' : 'DESC';

        $limit = (int)$limit;
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(sub.income_amount), 0) - COALESCE(SUM(sub.expense_amount), 0)
            FROM (
                SELECT rf.income_amount, rf.expense_amount
                FROM reserve_fund rf
                WHERE {$where}
                ORDER BY rf.expense_date {$sortDir}, rf.id {$sortDir}
                LIMIT {$limit}
            ) sub
        ");
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    public function getReserveFundList($filters = array(), $page = 1, $perPage = 100)
    {
        $params = array();
        $where = $this->buildReserveFundWhere($filters, $params);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM reserve_fund rf WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sortDir = (!empty($filters['sort']) && $filters['sort'] === 'asc') ? 'ASC' : 'DESC';
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT rf.*, b.name AS branch_name
            FROM reserve_fund rf
            LEFT JOIN branches b ON rf.branch_id = b.id
            WHERE {$where}
            ORDER BY rf.expense_date {$sortDir}, rf.id {$sortDir}
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return array(
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        );
    }

    /**
     * 新增備用金記錄
     */
    public function createReserveFund($data)
    {
        $number = $this->generateNumber('RF', 'reserve_fund', 'entry_number');
        $type = !empty($data['type']) ? $data['type'] : '支出';
        $amount = !empty($data['amount']) ? (float)$data['amount'] : 0;
        $income = ($type === '收入') ? $amount : 0;
        $expense = ($type === '支出') ? $amount : 0;

        $stmt = $this->db->prepare("
            INSERT INTO reserve_fund (entry_number, entry_date, expense_date, type, income_amount, expense_amount,
                description, branch_id, registrar, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(array(
            $number,
            date('Y-m-d'),
            !empty($data['expense_date']) ? $data['expense_date'] : date('Y-m-d'),
            $type,
            $income,
            $expense,
            !empty($data['description']) ? $data['description'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['registrar']) ? $data['registrar'] : null,
        ));
        return $this->db->lastInsertId();
    }

    // ========== 備用金 getById / update / delete ==========

    public function getReserveFundById($id)
    {
        $stmt = $this->db->prepare("
            SELECT rf.*, b.name AS branch_name
            FROM reserve_fund rf
            LEFT JOIN branches b ON rf.branch_id = b.id
            WHERE rf.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateReserveFund($id, $data)
    {
        $type = !empty($data['type']) ? $data['type'] : '支出';
        $amount = !empty($data['amount']) ? (float)$data['amount'] : 0;
        $income = ($type === '收入') ? $amount : 0;
        $expense = ($type === '支出') ? $amount : 0;

        $stmt = $this->db->prepare("
            UPDATE reserve_fund SET
                expense_date = ?, type = ?, income_amount = ?, expense_amount = ?,
                description = ?, invoice_info = ?, branch_id = ?,
                registrar = ?, approval_status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute(array(
            !empty($data['expense_date']) ? $data['expense_date'] : null,
            $type,
            $income,
            $expense,
            !empty($data['description']) ? $data['description'] : null,
            !empty($data['invoice_info']) ? $data['invoice_info'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['registrar']) ? $data['registrar'] : null,
            !empty($data['approval_status']) ? $data['approval_status'] : null,
            $id,
        ));
        return $stmt->rowCount();
    }

    public function deleteReserveFund($id)
    {
        $stmt = $this->db->prepare("DELETE FROM reserve_fund WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->rowCount();
    }

    // ========== 現金明細 ==========

    /**
     * 取得各分公司現金明細最新餘額
     */
    public function getCashDetailsBranchBalances($branchIds)
    {
        if (empty($branchIds)) return array();
        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT b.id AS branch_id, b.name AS branch_name,
                   COALESCE(SUM(cd.income_amount), 0) AS total_income,
                   COALESCE(SUM(cd.expense_amount), 0) AS total_expense,
                   COALESCE(SUM(cd.income_amount), 0) - COALESCE(SUM(cd.expense_amount), 0) AS balance
            FROM branches b
            LEFT JOIN cash_details cd ON cd.branch_id = b.id
            WHERE b.id IN ({$placeholders}) AND b.is_active = 1
            GROUP BY b.id, b.name
            HAVING balance != 0 OR total_income > 0 OR total_expense > 0
            ORDER BY b.id
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCashDetailsBalanceUpTo($filters)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['branch_id'])) {
            $where .= ' AND cd.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND cd.transaction_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND cd.transaction_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (cd.description LIKE ? OR cd.entry_number LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(cd.income_amount), 0) - COALESCE(SUM(cd.expense_amount), 0)
            FROM cash_details cd
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    public function getCashDetailsPageSum($filters, $limit)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['branch_id'])) {
            $where .= ' AND cd.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND cd.transaction_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND cd.transaction_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (cd.description LIKE ? OR cd.entry_number LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $sortDir = (!empty($filters['sort']) && $filters['sort'] === 'asc') ? 'ASC' : 'DESC';
        $limit = (int)$limit;
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(sub.income_amount), 0) - COALESCE(SUM(sub.expense_amount), 0)
            FROM (
                SELECT cd.income_amount, cd.expense_amount
                FROM cash_details cd
                WHERE {$where}
                ORDER BY cd.transaction_date {$sortDir}, cd.id {$sortDir}
                LIMIT {$limit}
            ) sub
        ");
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    public function getCashDetails($filters = array(), $page = 1, $perPage = 100)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['branch_id'])) {
            $where .= ' AND cd.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND cd.transaction_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND cd.transaction_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (cd.description LIKE ? OR cd.entry_number LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM cash_details cd WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sortDir = (!empty($filters['sort']) && $filters['sort'] === 'asc') ? 'ASC' : 'DESC';
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT cd.*, b.name AS branch_name
            FROM cash_details cd
            LEFT JOIN branches b ON cd.branch_id = b.id
            WHERE {$where}
            ORDER BY cd.transaction_date {$sortDir}, cd.id {$sortDir}
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return array(
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        );
    }

    /**
     * 新增現金明細記錄
     */
    public function createCashDetail($data)
    {
        $number = $this->generateNumber('CD', 'cash_details', 'entry_number');
        $type = !empty($data['type']) ? $data['type'] : '支出';
        $amount = !empty($data['amount']) ? (float)$data['amount'] : 0;
        $income = ($type === '收入') ? $amount : 0;
        $expense = ($type === '支出') ? $amount : 0;

        $stmt = $this->db->prepare("
            INSERT INTO cash_details (entry_number, register_date, transaction_date, income_amount, expense_amount,
                description, sales_name, branch_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(array(
            $number,
            date('Y-m-d'),
            !empty($data['transaction_date']) ? $data['transaction_date'] : date('Y-m-d'),
            $income,
            $expense,
            !empty($data['description']) ? $data['description'] : null,
            !empty($data['sales_name']) ? $data['sales_name'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
        ));
        return $this->db->lastInsertId();
    }

    // ========== 現金明細 getById / update / delete ==========

    public function getCashDetailById($id)
    {
        $stmt = $this->db->prepare("
            SELECT cd.*, b.name AS branch_name
            FROM cash_details cd
            LEFT JOIN branches b ON cd.branch_id = b.id
            WHERE cd.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateCashDetail($id, $data)
    {
        $type = !empty($data['type']) ? $data['type'] : '支出';
        $amount = !empty($data['amount']) ? (float)$data['amount'] : 0;
        $income = ($type === '收入') ? $amount : 0;
        $expense = ($type === '支出') ? $amount : 0;

        $stmt = $this->db->prepare("
            UPDATE cash_details SET
                transaction_date = ?, income_amount = ?, expense_amount = ?,
                description = ?, sales_name = ?, branch_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute(array(
            !empty($data['transaction_date']) ? $data['transaction_date'] : null,
            $income,
            $expense,
            !empty($data['description']) ? $data['description'] : null,
            !empty($data['sales_name']) ? $data['sales_name'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            $id,
        ));
        return $stmt->rowCount();
    }

    public function deleteCashDetail($id)
    {
        $stmt = $this->db->prepare("DELETE FROM cash_details WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->rowCount();
    }
}
