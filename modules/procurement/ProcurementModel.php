<?php
/**
 * 採購庫存資料模型
 * 廠商、請購單、採購單、調撥單
 */
class ProcurementModel
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

    public static function poStatusOptions()
    {
        return array(
            '尚未進貨'   => '尚未進貨',
            '確認進貨'   => '確認進貨',
            '已轉進貨單' => '已轉進貨單',
            '確認付款'   => '確認付款',
            '取消'       => '取消',
        );
    }

    public static function requisitionStatusOptions()
    {
        return array(
            '簽核中'   => '簽核中',
            '簽核完成' => '簽核完成',
            '退回'     => '退回',
            '已轉採購' => '已轉採購',
            '取消'     => '取消',
        );
    }

    public static function transferStatusOptions()
    {
        return array(
            '待出貨' => '待出貨',
            '已出貨' => '已出貨',
            '已到貨' => '已到貨',
            '完成'   => '完成',
            '取消'   => '取消',
        );
    }

    public static function urgencyOptions()
    {
        return array(
            '一般件' => '一般件',
            '急件'   => '急件',
            '特急件' => '特急件',
        );
    }

    public static function taxTypeOptions()
    {
        return array(
            '營業稅' => '營業稅',
            '免稅'   => '免稅',
            '零稅率' => '零稅率',
        );
    }

    public static function paymentMethodOptions()
    {
        return array(
            '電匯' => '電匯',
            '現金' => '現金',
            '支票' => '支票',
            '其他' => '其他',
        );
    }

    public static function invoiceMethodOptions()
    {
        return array(
            '開立發票' => '開立發票',
            '無發票'   => '無發票',
        );
    }

    // ============================================================
    // 編號生成
    // ============================================================

    /**
     * 產生流水號
     * @param string $prefix  前綴 (PR/PUR/ST)
     * @param string $table   表名
     * @param string $column  編號欄位名
     */
    public function generateNumber($prefix, $table, $column)
    {
        $moduleMap = array(
            'PR'  => 'requisitions',
            'PUR' => 'purchase_orders',
            'ST'  => 'warehouse_transfers',
        );
        $module = isset($moduleMap[$prefix]) ? $moduleMap[$prefix] : null;
        if ($module) {
            return generate_doc_number($module);
        }
        // fallback
        $date = date('Ymd');
        $like = $prefix . '-' . $date . '-%';
        $stmt = $this->db->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(array($like));
        $last = $stmt->fetchColumn();
        $seq = $last ? (int)end(explode('-', $last)) + 1 : 1;
        return $prefix . '-' . $date . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    // ============================================================
    // 廠商 CRUD
    // ============================================================

    public function getVendors($filters = array())
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['keyword'])) {
            $where .= ' AND (v.name LIKE ? OR v.short_name LIKE ? OR v.vendor_code LIKE ? OR v.contact_person LIKE ? OR v.tax_id LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        if (!empty($filters['category'])) {
            $where .= ' AND v.category = ?';
            $params[] = $filters['category'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where .= ' AND v.is_active = ?';
            $params[] = $filters['is_active'];
        }

        $stmt = $this->db->prepare("
            SELECT v.* FROM vendors v
            WHERE {$where}
            ORDER BY v.name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVendor($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 產生下一個廠商編號（B-XXXX 格式，4 位數字）
     * 規則：取現有 vendors 中 B-XXXX 格式的最大數字 + 1
     * 若無現有編號則從 B-0001 開始
     */
    public function generateNextVendorCode()
    {
        // 從 B-XXXX 抓數字部分並取最大
        $stmt = $this->db->query("
            SELECT vendor_code FROM vendors
            WHERE vendor_code REGEXP '^B-[0-9]{4}$'
            ORDER BY CAST(SUBSTRING(vendor_code, 3) AS UNSIGNED) DESC
            LIMIT 1
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxNum = 0;
        if ($row && !empty($row['vendor_code'])) {
            $maxNum = (int)substr($row['vendor_code'], 2);
        }
        $next = $maxNum + 1;
        return 'B-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function createVendor($data)
    {
        // 若未指定 vendor_code 或為空，自動產生
        if (empty($data['vendor_code'])) {
            $data['vendor_code'] = $this->generateNextVendorCode();
        }

        $stmt = $this->db->prepare("
            INSERT INTO vendors (vendor_code, name, short_name, tax_id, category, service_items,
                contact_person, phone, fax, email, postal_code, city_district, street_address, address,
                payment_method, payment_terms, settlement_day, invoice_method, invoice_type,
                header1, tax_id1, header2, tax_id2, invoice_type2, note, is_active, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute(array(
            !empty($data['vendor_code']) ? $data['vendor_code'] : null,
            $data['name'],
            !empty($data['short_name']) ? $data['short_name'] : null,
            !empty($data['tax_id']) ? $data['tax_id'] : null,
            !empty($data['category']) ? $data['category'] : null,
            !empty($data['service_items']) ? $data['service_items'] : null,
            !empty($data['contact_person']) ? $data['contact_person'] : null,
            !empty($data['phone']) ? $data['phone'] : null,
            !empty($data['fax']) ? $data['fax'] : null,
            !empty($data['email']) ? $data['email'] : null,
            !empty($data['postal_code']) ? $data['postal_code'] : null,
            !empty($data['city_district']) ? $data['city_district'] : null,
            !empty($data['street_address']) ? $data['street_address'] : null,
            !empty($data['address']) ? $data['address'] : null,
            !empty($data['payment_method']) ? $data['payment_method'] : null,
            !empty($data['payment_terms']) ? $data['payment_terms'] : null,
            !empty($data['settlement_day']) ? $data['settlement_day'] : null,
            !empty($data['invoice_method']) ? $data['invoice_method'] : null,
            !empty($data['invoice_type']) ? $data['invoice_type'] : null,
            !empty($data['header1']) ? $data['header1'] : null,
            !empty($data['tax_id1']) ? $data['tax_id1'] : null,
            !empty($data['header2']) ? $data['header2'] : null,
            !empty($data['tax_id2']) ? $data['tax_id2'] : null,
            !empty($data['invoice_type2']) ? $data['invoice_type2'] : null,
            !empty($data['note']) ? $data['note'] : null,
            isset($data['is_active']) ? $data['is_active'] : 1,
            !empty($data['created_by']) ? $data['created_by'] : null,
        ));
        return $this->db->lastInsertId();
    }

    public function updateVendor($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE vendors SET
                vendor_code=?, name=?, short_name=?, tax_id=?, category=?, service_items=?,
                contact_person=?, phone=?, fax=?, email=?, postal_code=?, city_district=?,
                street_address=?, address=?, payment_method=?, payment_terms=?, settlement_day=?,
                invoice_method=?, invoice_type=?, header1=?, tax_id1=?, header2=?, tax_id2=?,
                invoice_type2=?, note=?, is_active=?
            WHERE id=?
        ");
        $stmt->execute(array(
            !empty($data['vendor_code']) ? $data['vendor_code'] : null,
            $data['name'],
            !empty($data['short_name']) ? $data['short_name'] : null,
            !empty($data['tax_id']) ? $data['tax_id'] : null,
            !empty($data['category']) ? $data['category'] : null,
            !empty($data['service_items']) ? $data['service_items'] : null,
            !empty($data['contact_person']) ? $data['contact_person'] : null,
            !empty($data['phone']) ? $data['phone'] : null,
            !empty($data['fax']) ? $data['fax'] : null,
            !empty($data['email']) ? $data['email'] : null,
            !empty($data['postal_code']) ? $data['postal_code'] : null,
            !empty($data['city_district']) ? $data['city_district'] : null,
            !empty($data['street_address']) ? $data['street_address'] : null,
            !empty($data['address']) ? $data['address'] : null,
            !empty($data['payment_method']) ? $data['payment_method'] : null,
            !empty($data['payment_terms']) ? $data['payment_terms'] : null,
            !empty($data['settlement_day']) ? $data['settlement_day'] : null,
            !empty($data['invoice_method']) ? $data['invoice_method'] : null,
            !empty($data['invoice_type']) ? $data['invoice_type'] : null,
            !empty($data['header1']) ? $data['header1'] : null,
            !empty($data['tax_id1']) ? $data['tax_id1'] : null,
            !empty($data['header2']) ? $data['header2'] : null,
            !empty($data['tax_id2']) ? $data['tax_id2'] : null,
            !empty($data['invoice_type2']) ? $data['invoice_type2'] : null,
            !empty($data['note']) ? $data['note'] : null,
            isset($data['is_active']) ? $data['is_active'] : 1,
            $id,
        ));
    }

    public function deleteVendor($id)
    {
        $stmt = $this->db->prepare("UPDATE vendors SET is_active = 0 WHERE id = ?");
        $stmt->execute(array($id));
    }

    // ============================================================
    // 請購單 CRUD
    // ============================================================

    public function getRequisitions($filters = array())
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['branch_id'])) {
            $where .= ' AND r.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND r.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (r.requisition_number LIKE ? OR r.requester_name LIKE ? OR r.case_name LIKE ? OR r.vendor_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND r.requisition_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND r.requisition_date <= ?';
            $params[] = $filters['date_to'];
        }

        $stmt = $this->db->prepare("
            SELECT r.*, b.name AS branch_name
            FROM requisitions r
            LEFT JOIN branches b ON r.branch_id = b.id
            WHERE {$where}
            ORDER BY r.id DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRequisition($id)
    {
        $stmt = $this->db->prepare("
            SELECT r.*, b.name AS branch_name
            FROM requisitions r
            LEFT JOIN branches b ON r.branch_id = b.id
            WHERE r.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getRequisitionItems($requisitionId)
    {
        $stmt = $this->db->prepare("SELECT * FROM requisition_items WHERE requisition_id = ? ORDER BY sort_order, id");
        $stmt->execute(array($requisitionId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createRequisition($data)
    {
        $number = $this->generateNumber('PR', 'requisitions', 'requisition_number');
        $stmt = $this->db->prepare("
            INSERT INTO requisitions (requisition_number, requisition_date, requester_name, branch_id,
                sales_name, urgency, case_name, quotation_number, vendor_name, expected_date,
                status, approval_user, approval_date, approval_note, next_approver, note,
                created_by, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute(array(
            $number,
            $data['requisition_date'],
            !empty($data['requester_name']) ? $data['requester_name'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['sales_name']) ? $data['sales_name'] : null,
            !empty($data['urgency']) ? $data['urgency'] : '一般件',
            !empty($data['case_name']) ? $data['case_name'] : null,
            !empty($data['quotation_number']) ? $data['quotation_number'] : null,
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
            !empty($data['expected_date']) ? $data['expected_date'] : null,
            !empty($data['status']) ? $data['status'] : '簽核中',
            !empty($data['approval_user']) ? $data['approval_user'] : null,
            !empty($data['approval_date']) ? $data['approval_date'] : null,
            !empty($data['approval_note']) ? $data['approval_note'] : null,
            !empty($data['next_approver']) ? $data['next_approver'] : null,
            !empty($data['note']) ? $data['note'] : null,
            $data['created_by'],
            $data['created_by'],
        ));
        return $this->db->lastInsertId();
    }

    public function updateRequisition($id, $data)
    {
        $sets = array();
        $params = array();
        $allowed = array(
            'requisition_date', 'requester_name', 'branch_id', 'sales_name', 'urgency',
            'case_name', 'quotation_number', 'vendor_name', 'expected_date', 'status',
            'approval_user', 'approval_date', 'approval_note', 'next_approver', 'note',
            'updated_by'
        );
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = $col . '=?';
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) return;
        $params[] = $id;
        $stmt = $this->db->prepare("UPDATE requisitions SET " . implode(', ', $sets) . " WHERE id=?");
        $stmt->execute($params);
    }

    public function saveRequisitionItems($requisitionId, $items)
    {
        $this->db->prepare("DELETE FROM requisition_items WHERE requisition_id = ?")->execute(array($requisitionId));
        if (empty($items)) return;
        $stmt = $this->db->prepare("
            INSERT INTO requisition_items (requisition_id, product_id, model, product_name, quantity, unit_price, amount, purpose, approved_qty, sort_order)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $sort = 0;
        foreach ($items as $item) {
            if (empty($item['model']) && empty($item['product_name'])) continue;
            $qty = !empty($item['quantity']) ? (int)$item['quantity'] : 1;
            $price = !empty($item['unit_price']) ? (int)$item['unit_price'] : 0;
            $stmt->execute(array(
                $requisitionId,
                !empty($item['product_id']) ? $item['product_id'] : null,
                !empty($item['model']) ? $item['model'] : null,
                !empty($item['product_name']) ? $item['product_name'] : null,
                $qty,
                $price,
                round($qty * $price),
                !empty($item['purpose']) ? $item['purpose'] : null,
                !empty($item['approved_qty']) ? $item['approved_qty'] : null,
                $sort++,
            ));
        }
    }

    public function deleteRequisition($id)
    {
        $stmt = $this->db->prepare("DELETE FROM requisitions WHERE id = ?");
        $stmt->execute(array($id));
    }

    // ============================================================
    // 採購單 CRUD
    // ============================================================

    public function getPurchaseOrders($filters = array())
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['branch_id'])) {
            $where .= ' AND po.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND po.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['vendor_name'])) {
            $where .= ' AND po.vendor_name LIKE ?';
            $params[] = '%' . $filters['vendor_name'] . '%';
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (po.po_number LIKE ? OR po.vendor_name LIKE ? OR po.case_name LIKE ? OR po.purchaser_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND po.po_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND po.po_date <= ?';
            $params[] = $filters['date_to'];
        }

        $stmt = $this->db->prepare("
            SELECT po.*, b.name AS branch_name
            FROM purchase_orders po
            LEFT JOIN branches b ON po.branch_id = b.id
            WHERE {$where}
            ORDER BY po.id DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPurchaseOrder($id)
    {
        $stmt = $this->db->prepare("
            SELECT po.*, b.name AS branch_name
            FROM purchase_orders po
            LEFT JOIN branches b ON po.branch_id = b.id
            WHERE po.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPurchaseOrderItems($purchaseOrderId)
    {
        $stmt = $this->db->prepare("SELECT * FROM purchase_order_items WHERE purchase_order_id = ? ORDER BY sort_order, id");
        $stmt->execute(array($purchaseOrderId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPurchaseOrder($data)
    {
        $number = $this->generateNumber('PUR', 'purchase_orders', 'po_number');
        $stmt = $this->db->prepare("
            INSERT INTO purchase_orders (po_number, po_date, status, purchaser_name, requisition_id,
                requisition_number, receiving_date, case_name, branch_id, sales_name, urgency,
                req_vendor_name, vendor_id, vendor_code, vendor_name, vendor_tax_id, vendor_contact,
                vendor_phone, vendor_fax, vendor_email, vendor_address,
                payment_method, payment_terms, invoice_method, invoice_type, payment_date,
                is_paid, paid_amount, bank_code, bank_name, bank_branch, account_name, account_number,
                subtotal, tax_type, tax_rate, tax_amount, shipping_fee, total_amount, this_amount,
                discount_untaxed, discount_taxed, use_payment_flow, convert_to_receiving,
                is_cancelled, refund_date, delivery_location, required_date, promised_date,
                note, created_by, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute(array(
            $number,
            $data['po_date'],
            !empty($data['status']) ? $data['status'] : '尚未進貨',
            !empty($data['purchaser_name']) ? $data['purchaser_name'] : null,
            !empty($data['requisition_id']) ? $data['requisition_id'] : null,
            !empty($data['requisition_number']) ? $data['requisition_number'] : null,
            !empty($data['receiving_date']) ? $data['receiving_date'] : null,
            !empty($data['case_name']) ? $data['case_name'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['sales_name']) ? $data['sales_name'] : null,
            !empty($data['urgency']) ? $data['urgency'] : '一般件',
            !empty($data['req_vendor_name']) ? $data['req_vendor_name'] : null,
            !empty($data['vendor_id']) ? $data['vendor_id'] : null,
            !empty($data['vendor_code']) ? $data['vendor_code'] : null,
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
            !empty($data['vendor_tax_id']) ? $data['vendor_tax_id'] : null,
            !empty($data['vendor_contact']) ? $data['vendor_contact'] : null,
            !empty($data['vendor_phone']) ? $data['vendor_phone'] : null,
            !empty($data['vendor_fax']) ? $data['vendor_fax'] : null,
            !empty($data['vendor_email']) ? $data['vendor_email'] : null,
            !empty($data['vendor_address']) ? $data['vendor_address'] : null,
            !empty($data['payment_method']) ? $data['payment_method'] : null,
            !empty($data['payment_terms']) ? $data['payment_terms'] : null,
            !empty($data['invoice_method']) ? $data['invoice_method'] : null,
            !empty($data['invoice_type']) ? $data['invoice_type'] : null,
            !empty($data['payment_date']) ? $data['payment_date'] : null,
            !empty($data['is_paid']) ? $data['is_paid'] : 0,
            !empty($data['paid_amount']) ? $data['paid_amount'] : 0,
            !empty($data['bank_code']) ? $data['bank_code'] : null,
            !empty($data['bank_name']) ? $data['bank_name'] : null,
            !empty($data['bank_branch']) ? $data['bank_branch'] : null,
            !empty($data['account_name']) ? $data['account_name'] : null,
            !empty($data['account_number']) ? $data['account_number'] : null,
            !empty($data['subtotal']) ? $data['subtotal'] : 0,
            !empty($data['tax_type']) ? $data['tax_type'] : '營業稅',
            !empty($data['tax_rate']) ? $data['tax_rate'] : 5.00,
            !empty($data['tax_amount']) ? $data['tax_amount'] : 0,
            !empty($data['shipping_fee']) ? $data['shipping_fee'] : 0,
            !empty($data['total_amount']) ? $data['total_amount'] : 0,
            !empty($data['this_amount']) ? $data['this_amount'] : 0,
            !empty($data['discount_untaxed']) ? $data['discount_untaxed'] : null,
            !empty($data['discount_taxed']) ? $data['discount_taxed'] : null,
            !empty($data['use_payment_flow']) ? $data['use_payment_flow'] : 0,
            !empty($data['convert_to_receiving']) ? $data['convert_to_receiving'] : 0,
            !empty($data['is_cancelled']) ? $data['is_cancelled'] : 0,
            !empty($data['refund_date']) ? $data['refund_date'] : null,
            !empty($data['delivery_location']) ? $data['delivery_location'] : null,
            !empty($data['required_date']) ? $data['required_date'] : null,
            !empty($data['promised_date']) ? $data['promised_date'] : null,
            !empty($data['note']) ? $data['note'] : null,
            $data['created_by'],
            $data['created_by'],
        ));
        return $this->db->lastInsertId();
    }

    public function updatePurchaseOrder($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE purchase_orders SET
                po_date=?, status=?, purchaser_name=?, requisition_id=?, requisition_number=?,
                receiving_date=?, case_name=?, branch_id=?, sales_name=?, urgency=?,
                vendor_id=?, vendor_code=?, vendor_name=?, vendor_tax_id=?, vendor_contact=?,
                vendor_phone=?, vendor_fax=?, vendor_email=?, vendor_address=?,
                payment_method=?, payment_terms=?, invoice_method=?, invoice_type=?, payment_date=?,
                is_paid=?, paid_amount=?, bank_code=?, bank_name=?, bank_branch=?,
                account_name=?, account_number=?, subtotal=?, tax_type=?, tax_rate=?,
                tax_amount=?, shipping_fee=?, total_amount=?, this_amount=?,
                discount_untaxed=?, discount_taxed=?, use_payment_flow=?, convert_to_receiving=?,
                is_cancelled=?, refund_date=?, delivery_location=?, required_date=?, promised_date=?,
                note=?, updated_by=?
            WHERE id=?
        ");
        $stmt->execute(array(
            $data['po_date'],
            !empty($data['status']) ? $data['status'] : '尚未進貨',
            !empty($data['purchaser_name']) ? $data['purchaser_name'] : null,
            !empty($data['requisition_id']) ? $data['requisition_id'] : null,
            !empty($data['requisition_number']) ? $data['requisition_number'] : null,
            !empty($data['receiving_date']) ? $data['receiving_date'] : null,
            !empty($data['case_name']) ? $data['case_name'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['sales_name']) ? $data['sales_name'] : null,
            !empty($data['urgency']) ? $data['urgency'] : '一般件',
            !empty($data['vendor_id']) ? $data['vendor_id'] : null,
            !empty($data['vendor_code']) ? $data['vendor_code'] : null,
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
            !empty($data['vendor_tax_id']) ? $data['vendor_tax_id'] : null,
            !empty($data['vendor_contact']) ? $data['vendor_contact'] : null,
            !empty($data['vendor_phone']) ? $data['vendor_phone'] : null,
            !empty($data['vendor_fax']) ? $data['vendor_fax'] : null,
            !empty($data['vendor_email']) ? $data['vendor_email'] : null,
            !empty($data['vendor_address']) ? $data['vendor_address'] : null,
            !empty($data['payment_method']) ? $data['payment_method'] : null,
            !empty($data['payment_terms']) ? $data['payment_terms'] : null,
            !empty($data['invoice_method']) ? $data['invoice_method'] : null,
            !empty($data['invoice_type']) ? $data['invoice_type'] : null,
            !empty($data['payment_date']) ? $data['payment_date'] : null,
            !empty($data['is_paid']) ? $data['is_paid'] : 0,
            !empty($data['paid_amount']) ? $data['paid_amount'] : 0,
            !empty($data['bank_code']) ? $data['bank_code'] : null,
            !empty($data['bank_name']) ? $data['bank_name'] : null,
            !empty($data['bank_branch']) ? $data['bank_branch'] : null,
            !empty($data['account_name']) ? $data['account_name'] : null,
            !empty($data['account_number']) ? $data['account_number'] : null,
            !empty($data['subtotal']) ? $data['subtotal'] : 0,
            !empty($data['tax_type']) ? $data['tax_type'] : '營業稅',
            !empty($data['tax_rate']) ? $data['tax_rate'] : 5.00,
            !empty($data['tax_amount']) ? $data['tax_amount'] : 0,
            !empty($data['shipping_fee']) ? $data['shipping_fee'] : 0,
            !empty($data['total_amount']) ? $data['total_amount'] : 0,
            !empty($data['this_amount']) ? $data['this_amount'] : 0,
            !empty($data['discount_untaxed']) ? $data['discount_untaxed'] : null,
            !empty($data['discount_taxed']) ? $data['discount_taxed'] : null,
            !empty($data['use_payment_flow']) ? $data['use_payment_flow'] : 0,
            !empty($data['convert_to_receiving']) ? $data['convert_to_receiving'] : 0,
            !empty($data['is_cancelled']) ? $data['is_cancelled'] : 0,
            !empty($data['refund_date']) ? $data['refund_date'] : null,
            !empty($data['delivery_location']) ? $data['delivery_location'] : null,
            !empty($data['required_date']) ? $data['required_date'] : null,
            !empty($data['promised_date']) ? $data['promised_date'] : null,
            !empty($data['note']) ? $data['note'] : null,
            $data['updated_by'],
            $id,
        ));
    }

    public function savePurchaseOrderItems($purchaseOrderId, $items)
    {
        $this->db->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = ?")->execute(array($purchaseOrderId));
        if (empty($items)) return;
        $stmt = $this->db->prepare("
            INSERT INTO purchase_order_items (purchase_order_id, product_id, model, product_name, spec, unit_price, quantity, amount, delivery_date, received_qty, sort_order)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $sort = 0;
        foreach ($items as $item) {
            if (empty($item['product_name'])) continue;
            $stmt->execute(array(
                $purchaseOrderId,
                !empty($item['product_id']) ? $item['product_id'] : null,
                !empty($item['model']) ? $item['model'] : null,
                !empty($item['product_name']) ? $item['product_name'] : null,
                !empty($item['spec']) ? $item['spec'] : null,
                !empty($item['unit_price']) ? $item['unit_price'] : 0,
                !empty($item['quantity']) ? $item['quantity'] : 1,
                !empty($item['amount']) ? $item['amount'] : 0,
                !empty($item['delivery_date']) ? $item['delivery_date'] : null,
                !empty($item['received_qty']) ? $item['received_qty'] : 0,
                $sort++,
            ));
        }
    }

    public function deletePurchaseOrder($id)
    {
        $stmt = $this->db->prepare("DELETE FROM purchase_orders WHERE id = ?");
        $stmt->execute(array($id));
    }

    /**
     * 從請購單轉採購單 — 回傳預填資料
     */
    public function convertFromRequisition($requisitionId)
    {
        $req = $this->getRequisition($requisitionId);
        if (!$req) return null;

        $items = $this->getRequisitionItems($requisitionId);

        $poData = array(
            'po_date'            => date('Y-m-d'),
            'requisition_id'     => $req['id'],
            'requisition_number' => $req['requisition_number'],
            'case_name'          => !empty($req['case_name']) ? $req['case_name'] : null,
            'branch_id'          => !empty($req['branch_id']) ? $req['branch_id'] : null,
            'sales_name'         => !empty($req['sales_name']) ? $req['sales_name'] : null,
            'urgency'            => !empty($req['urgency']) ? $req['urgency'] : '一般件',
            'vendor_name'        => !empty($req['vendor_name']) ? $req['vendor_name'] : null,
            'req_vendor_name'    => !empty($req['vendor_name']) ? $req['vendor_name'] : null,
        );

        $poItems = array();
        foreach ($items as $item) {
            $qty = !empty($item['approved_qty']) ? (int)$item['approved_qty'] : (!empty($item['quantity']) ? (int)$item['quantity'] : 1);
            $price = !empty($item['unit_price']) ? (int)$item['unit_price'] : 0;
            $poItems[] = array(
                'product_id'   => !empty($item['product_id']) ? $item['product_id'] : null,
                'model'        => !empty($item['model']) ? $item['model'] : null,
                'product_name' => !empty($item['product_name']) ? $item['product_name'] : null,
                'quantity'     => $qty,
                'unit_price'   => $price,
                'amount'       => $qty * $price,
            );
        }

        return array(
            'header' => $poData,
            'items'  => $poItems,
        );
    }

    // ============================================================
    // 調撥單 CRUD
    // ============================================================

    public function getTransfers($filters = array())
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['from_warehouse_id'])) {
            $where .= ' AND t.from_warehouse_id = ?';
            $params[] = $filters['from_warehouse_id'];
        }
        if (!empty($filters['to_warehouse_id'])) {
            $where .= ' AND t.to_warehouse_id = ?';
            $params[] = $filters['to_warehouse_id'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND t.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (t.transfer_number LIKE ? OR t.note LIKE ? OR t.shipper_name LIKE ? OR t.receiver_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND t.transfer_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND t.transfer_date <= ?';
            $params[] = $filters['date_to'];
        }

        $stmt = $this->db->prepare("
            SELECT t.*,
                wf.name AS from_warehouse_display,
                wt.name AS to_warehouse_display,
                bf.name AS from_branch_name,
                bt.name AS to_branch_name,
                uc.real_name AS created_by_name,
                uu.real_name AS updated_by_name
            FROM warehouse_transfers t
            LEFT JOIN warehouses wf ON t.from_warehouse_id = wf.id
            LEFT JOIN warehouses wt ON t.to_warehouse_id = wt.id
            LEFT JOIN branches bf ON t.from_branch_id = bf.id
            LEFT JOIN branches bt ON t.to_branch_id = bt.id
            LEFT JOIN users uc ON t.created_by = uc.id
            LEFT JOIN users uu ON t.updated_by = uu.id
            WHERE {$where}
            ORDER BY t.id DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTransfer($id)
    {
        $stmt = $this->db->prepare("
            SELECT t.*,
                wf.name AS from_warehouse_display,
                wt.name AS to_warehouse_display,
                bf.name AS from_branch_name,
                bt.name AS to_branch_name,
                uc.real_name AS created_by_name,
                uu.real_name AS updated_by_name
            FROM warehouse_transfers t
            LEFT JOIN warehouses wf ON t.from_warehouse_id = wf.id
            LEFT JOIN warehouses wt ON t.to_warehouse_id = wt.id
            LEFT JOIN branches bf ON t.from_branch_id = bf.id
            LEFT JOIN branches bt ON t.to_branch_id = bt.id
            LEFT JOIN users uc ON t.created_by = uc.id
            LEFT JOIN users uu ON t.updated_by = uu.id
            WHERE t.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTransferItems($transferId)
    {
        $stmt = $this->db->prepare("SELECT * FROM warehouse_transfer_items WHERE transfer_id = ? ORDER BY sort_order, id");
        $stmt->execute(array($transferId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTransfer($data)
    {
        $number = $this->generateNumber('ST', 'warehouse_transfers', 'transfer_number');
        $stmt = $this->db->prepare("
            INSERT INTO warehouse_transfers (transfer_number, transfer_date, from_branch_id, to_branch_id,
                from_warehouse_id, to_warehouse_id, from_warehouse_name, to_warehouse_name,
                status, update_inventory, shipper_name, receiver_name, total_amount, note,
                created_by, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute(array(
            $number,
            $data['transfer_date'],
            !empty($data['from_branch_id']) ? $data['from_branch_id'] : null,
            !empty($data['to_branch_id']) ? $data['to_branch_id'] : null,
            !empty($data['from_warehouse_id']) ? $data['from_warehouse_id'] : null,
            !empty($data['to_warehouse_id']) ? $data['to_warehouse_id'] : null,
            !empty($data['from_warehouse_name']) ? $data['from_warehouse_name'] : null,
            !empty($data['to_warehouse_name']) ? $data['to_warehouse_name'] : null,
            !empty($data['status']) ? $data['status'] : '待出貨',
            !empty($data['update_inventory']) ? $data['update_inventory'] : 0,
            !empty($data['shipper_name']) ? $data['shipper_name'] : null,
            !empty($data['receiver_name']) ? $data['receiver_name'] : null,
            !empty($data['total_amount']) ? $data['total_amount'] : 0,
            !empty($data['note']) ? $data['note'] : null,
            $data['created_by'],
            $data['created_by'],
        ));
        return $this->db->lastInsertId();
    }

    public function updateTransfer($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE warehouse_transfers SET
                transfer_date=?, from_branch_id=?, to_branch_id=?,
                from_warehouse_id=?, to_warehouse_id=?, from_warehouse_name=?, to_warehouse_name=?,
                status=?, update_inventory=?, shipper_name=?, receiver_name=?,
                total_amount=?, note=?, updated_by=?
            WHERE id=?
        ");
        $stmt->execute(array(
            $data['transfer_date'],
            !empty($data['from_branch_id']) ? $data['from_branch_id'] : null,
            !empty($data['to_branch_id']) ? $data['to_branch_id'] : null,
            !empty($data['from_warehouse_id']) ? $data['from_warehouse_id'] : null,
            !empty($data['to_warehouse_id']) ? $data['to_warehouse_id'] : null,
            !empty($data['from_warehouse_name']) ? $data['from_warehouse_name'] : null,
            !empty($data['to_warehouse_name']) ? $data['to_warehouse_name'] : null,
            !empty($data['status']) ? $data['status'] : '待出貨',
            !empty($data['update_inventory']) ? $data['update_inventory'] : 0,
            !empty($data['shipper_name']) ? $data['shipper_name'] : null,
            !empty($data['receiver_name']) ? $data['receiver_name'] : null,
            !empty($data['total_amount']) ? $data['total_amount'] : 0,
            !empty($data['note']) ? $data['note'] : null,
            $data['updated_by'],
            $id,
        ));
    }

    public function saveTransferItems($transferId, $items)
    {
        $this->db->prepare("DELETE FROM warehouse_transfer_items WHERE transfer_id = ?")->execute(array($transferId));
        if (empty($items)) return;
        $stmt = $this->db->prepare("
            INSERT INTO warehouse_transfer_items (transfer_id, product_id, model, product_name, quantity, unit_price, amount, sort_order)
            VALUES (?,?,?,?,?,?,?,?)
        ");
        $sort = 0;
        foreach ($items as $item) {
            if (empty($item['product_name'])) continue;
            $stmt->execute(array(
                $transferId,
                !empty($item['product_id']) ? $item['product_id'] : null,
                !empty($item['model']) ? $item['model'] : null,
                !empty($item['product_name']) ? $item['product_name'] : null,
                !empty($item['quantity']) ? $item['quantity'] : 1,
                !empty($item['unit_price']) ? $item['unit_price'] : 0,
                !empty($item['amount']) ? $item['amount'] : 0,
                $sort++,
            ));
        }
    }

    public function deleteTransfer($id)
    {
        $stmt = $this->db->prepare("DELETE FROM warehouse_transfers WHERE id = ?");
        $stmt->execute(array($id));
    }

    // ============================================================
    // 通用查詢
    // ============================================================

    public function getWarehouses()
    {
        $stmt = $this->db->query("
            SELECT w.*, b.name AS branch_name
            FROM warehouses w
            LEFT JOIN branches b ON w.branch_id = b.id
            WHERE w.is_active = 1
            ORDER BY w.id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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
}
