<?php
/**
 * 發票管理資料模型
 * 進項發票、銷項發票、401申報
 */
class InvoiceModel
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

    public static function purchaseInvoiceTypeOptions()
    {
        return array(
            '應稅'   => '應稅',
            '零稅率' => '零稅率',
            '免稅'   => '免稅',
            '特種'   => '特種',
        );
    }

    public static function salesInvoiceTypeOptions()
    {
        return array(
            '應稅'   => '應稅',
            '零稅率' => '零稅率',
            '免稅'   => '免稅',
        );
    }

    public static function invoiceStatusOptions()
    {
        return array(
            'pending'   => '待處理',
            'confirmed' => '已確認',
            'voided'    => '已作廢',
            'blank'     => '空白發票',
        );
    }

    public static function deductionTypeOptions()
    {
        return array(
            'deductible'     => '可扣抵',
            'non_deductible' => '不可扣抵',
        );
    }

    public static function referenceTypeOptions()
    {
        return array(
            ''          => '無',
            'payable'   => '應付帳款',
            'purchase'  => '進貨單',
        );
    }

    public static function salesReferenceTypeOptions()
    {
        return array(
            ''           => '無',
            'receivable' => '應收帳款',
            'delivery'   => '出貨單',
            'case'       => '案件',
        );
    }

    /**
     * 401報稅期間 (每2個月)
     */
    public static function taxPeriodOptions()
    {
        return array(
            '01-02' => '1-2月',
            '03-04' => '3-4月',
            '05-06' => '5-6月',
            '07-08' => '7-8月',
            '09-10' => '9-10月',
            '11-12' => '11-12月',
        );
    }

    // ============================================================
    // 進項發票 (Purchase Invoices)
    // ============================================================

    /**
     * 取得進項發票清單
     */
    public function getPurchaseInvoices($filters = array(), $page = 1, $perPage = 30)
    {
        $where = array('1=1');
        $params = array();

        if (!empty($filters['period'])) {
            $where[] = "pi.period = ?";
            $params[] = $filters['period'];
        }
        if (!empty($filters['vendor'])) {
            $where[] = "(pi.vendor_name LIKE ? OR pi.vendor_tax_id LIKE ?)";
            $params[] = '%' . $filters['vendor'] . '%';
            $params[] = '%' . $filters['vendor'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = "pi.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $rawKw = trim($filters['keyword']);
            // $ 開頭：精準比對三個金額欄位
            if (strlen($rawKw) > 1 && $rawKw[0] === '$') {
                $amt = (float)preg_replace('/[^0-9.\-]/', '', substr($rawKw, 1));
                $where[] = "(pi.amount_untaxed = ? OR pi.tax_amount = ? OR pi.total_amount = ?)";
                $params[] = $amt; $params[] = $amt; $params[] = $amt;
            } else {
                $kw = '%' . $rawKw . '%';
                // 申報期間：支援 YYYYMM（月）與 YYYY/M-M月、YYYY-MM-MM（兩月一期）
                $kwPeriod = '%' . preg_replace('/[^0-9]/', '', $rawKw) . '%';
                $kwBimonth = null;
                if (preg_match('/(\d{4})[^\d]+(\d{1,2})[^\d]+(\d{1,2})/', $rawKw, $_bm)) {
                    $kwBimonth = sprintf('%04d-%02d-%02d', (int)$_bm[1], (int)$_bm[2], (int)$_bm[3]);
                }
                $periodClauses = "pi.period LIKE ?";
                if ($kwBimonth !== null) {
                    $periodClauses .= " OR pi.report_period = ?";
                }
                $where[] = "(pi.invoice_number LIKE ? OR pi.vendor_name LIKE ? OR pi.note LIKE ?
                    OR pi.vendor_tax_id LIKE ?
                    OR CAST(pi.invoice_date AS CHAR) LIKE ?
                    OR CAST(pi.amount_untaxed AS CHAR) LIKE ?
                    OR CAST(pi.tax_amount AS CHAR) LIKE ?
                    OR CAST(pi.total_amount AS CHAR) LIKE ?
                    OR {$periodClauses})";
                for ($i = 0; $i < 8; $i++) { $params[] = $kw; }
                $params[] = $kwPeriod;
                if ($kwBimonth !== null) { $params[] = $kwBimonth; }
            }
        }
        if (!empty($filters['invoice_type'])) {
            $where[] = "pi.invoice_type = ?";
            $params[] = $filters['invoice_type'];
        }
        if (!empty($filters['invoice_format'])) {
            $where[] = "pi.invoice_format = ?";
            $params[] = $filters['invoice_format'];
        }
        if (!empty($filters['deduction_type'])) {
            $where[] = "pi.deduction_type = ?";
            $params[] = $filters['deduction_type'];
        }

        $whereStr = implode(' AND ', $where);

        // 計算總數
        $countSql = "SELECT COUNT(*) FROM purchase_invoices pi WHERE " . $whereStr;
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT pi.*, u.real_name AS created_by_name
                FROM purchase_invoices pi
                LEFT JOIN users u ON u.id = pi.created_by
                WHERE " . $whereStr . "
                ORDER BY COALESCE(pi.invoice_date, '1900-01-01') DESC, pi.id DESC
                LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array(
            'data'     => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'    => $total,
            'page'     => $page,
            'lastPage' => $lastPage,
            'perPage'  => $perPage,
        );
    }

    /**
     * 取得單筆進項發票
     */
    public function getPurchaseInvoiceById($id)
    {
        $stmt = $this->db->prepare("SELECT pi.*, u.real_name AS created_by_name FROM purchase_invoices pi LEFT JOIN users u ON pi.created_by = u.id WHERE pi.id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 檢查進項發票號碼是否重複
     * @param string $invoiceNumber 發票號碼
     * @param int|null $excludeId 排除的 ID（編輯時用）
     * @return bool 重複回傳 true
     */
    public function isPurchaseInvoiceNumberDuplicate($invoiceNumber, $excludeId = null)
    {
        if (empty($invoiceNumber)) {
            return false; // 空白不檢查
        }
        if ($excludeId) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM purchase_invoices WHERE invoice_number = ? AND id != ? AND status != 'voided'");
            $stmt->execute(array($invoiceNumber, (int)$excludeId));
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM purchase_invoices WHERE invoice_number = ? AND status != 'voided'");
            $stmt->execute(array($invoiceNumber));
        }
        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * 新增進項發票
     */
    public function createPurchaseInvoice($data)
    {
        // 唯一性檢查
        if (!empty($data['invoice_number']) && $this->isPurchaseInvoiceNumberDuplicate($data['invoice_number'])) {
            throw new Exception('發票號碼「' . $data['invoice_number'] . '」已存在，無法新增');
        }

        // 自動計算稅額
        $data = $this->autoCalculateTax($data);
        // 自動計算期間
        if (empty($data['period']) && !empty($data['invoice_date'])) {
            $data['period'] = $this->calculatePeriod($data['invoice_date']);
        }

        $sql = "INSERT INTO purchase_invoices
                (invoice_number, invoice_date, vendor_id, vendor_name, vendor_tax_id,
                 invoice_type, amount_untaxed, tax_amount, total_amount, tax_rate,
                 reference_type, reference_id, deduction_type, period, status, note,
                 report_period, invoice_format, deduction_category,
                 created_by, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            !empty($data['invoice_number']) ? $data['invoice_number'] : null,
            !empty($data['invoice_date']) ? $data['invoice_date'] : date('Y-m-d'),
            !empty($data['vendor_id']) ? $data['vendor_id'] : null,
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
            !empty($data['vendor_tax_id']) ? $data['vendor_tax_id'] : null,
            !empty($data['invoice_type']) ? $data['invoice_type'] : '應稅',
            isset($data['amount_untaxed']) ? $data['amount_untaxed'] : 0,
            isset($data['tax_amount']) ? $data['tax_amount'] : 0,
            isset($data['total_amount']) ? $data['total_amount'] : 0,
            isset($data['tax_rate']) ? $data['tax_rate'] : 5,
            !empty($data['reference_type']) ? $data['reference_type'] : null,
            !empty($data['reference_id']) ? $data['reference_id'] : null,
            !empty($data['deduction_type']) ? $data['deduction_type'] : 'deductible',
            !empty($data['period']) ? $data['period'] : null,
            !empty($data['status']) ? $data['status'] : 'pending',
            !empty($data['note']) ? $data['note'] : null,
            !empty($data['report_period']) ? $data['report_period'] : null,
            !empty($data['invoice_format']) ? $data['invoice_format'] : null,
            !empty($data['deduction_category']) ? $data['deduction_category'] : null,
            !empty($data['created_by']) ? $data['created_by'] : null,
        ));
        return $this->db->lastInsertId();
    }

    /**
     * 更新進項發票
     */
    public function updatePurchaseInvoice($id, $data)
    {
        // 唯一性檢查（排除自己）
        if (!empty($data['invoice_number']) && $this->isPurchaseInvoiceNumberDuplicate($data['invoice_number'], $id)) {
            throw new Exception('發票號碼「' . $data['invoice_number'] . '」已存在，無法更新');
        }

        $data = $this->autoCalculateTax($data);
        if (empty($data['period']) && !empty($data['invoice_date'])) {
            $data['period'] = $this->calculatePeriod($data['invoice_date']);
        }

        $sql = "UPDATE purchase_invoices SET
                invoice_number = ?, invoice_date = ?, vendor_id = ?, vendor_name = ?, vendor_tax_id = ?,
                invoice_type = ?, amount_untaxed = ?, tax_amount = ?, total_amount = ?, tax_rate = ?,
                reference_type = ?, reference_id = ?, deduction_type = ?, period = ?, status = ?,
                note = ?, report_period = ?, invoice_format = ?, deduction_category = ?,
                updated_at = NOW()
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            !empty($data['invoice_number']) ? $data['invoice_number'] : null,
            !empty($data['invoice_date']) ? $data['invoice_date'] : date('Y-m-d'),
            !empty($data['vendor_id']) ? $data['vendor_id'] : null,
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
            !empty($data['vendor_tax_id']) ? $data['vendor_tax_id'] : null,
            !empty($data['invoice_type']) ? $data['invoice_type'] : '應稅',
            isset($data['amount_untaxed']) ? $data['amount_untaxed'] : 0,
            isset($data['tax_amount']) ? $data['tax_amount'] : 0,
            isset($data['total_amount']) ? $data['total_amount'] : 0,
            isset($data['tax_rate']) ? $data['tax_rate'] : 5,
            !empty($data['reference_type']) ? $data['reference_type'] : null,
            !empty($data['reference_id']) ? $data['reference_id'] : null,
            !empty($data['deduction_type']) ? $data['deduction_type'] : 'deductible',
            !empty($data['period']) ? $data['period'] : null,
            !empty($data['status']) ? $data['status'] : 'pending',
            !empty($data['note']) ? $data['note'] : null,
            !empty($data['report_period']) ? $data['report_period'] : null,
            !empty($data['invoice_format']) ? $data['invoice_format'] : null,
            !empty($data['deduction_category']) ? $data['deduction_category'] : null,
            $id,
        ));
        return $stmt->rowCount();
    }

    /**
     * 刪除進項發票 (僅 voided 可刪，需先作廢)
     */
    public function deletePurchaseInvoice($id)
    {
        $invoice = $this->getPurchaseInvoiceById($id);
        if (!$invoice) {
            return false;
        }
        if ($invoice['status'] !== 'voided') {
            throw new Exception('請先作廢此發票再進行刪除');
        }
        $stmt = $this->db->prepare("DELETE FROM purchase_invoices WHERE id = ? AND status = 'voided'");
        $stmt->execute(array($id));
        return $stmt->rowCount() > 0;
    }

    /**
     * 作廢進項發票
     */
    public function voidPurchaseInvoice($id)
    {
        $stmt = $this->db->prepare("UPDATE purchase_invoices SET status = 'voided', updated_at = NOW() WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->rowCount() > 0;
    }

    // ============================================================
    // 銷項發票 (Sales Invoices)
    // ============================================================

    /**
     * 取得銷項發票清單
     */
    public function getSalesInvoices($filters = array(), $page = 1, $perPage = 100)
    {
        $where = array('1=1');
        $params = array();

        if (!empty($filters['period'])) {
            $where[] = "si.period = ?";
            $params[] = $filters['period'];
        }
        if (!empty($filters['customer'])) {
            $where[] = "(si.customer_name LIKE ? OR si.customer_tax_id LIKE ?)";
            $params[] = '%' . $filters['customer'] . '%';
            $params[] = '%' . $filters['customer'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = "si.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $rawKw = trim($filters['keyword']);
            // $ 開頭：精準比對三個金額欄位
            if (strlen($rawKw) > 1 && $rawKw[0] === '$') {
                $amt = (float)preg_replace('/[^0-9.\-]/', '', substr($rawKw, 1));
                $where[] = "(si.amount_untaxed = ? OR si.tax_amount = ? OR si.total_amount = ?)";
                $params[] = $amt; $params[] = $amt; $params[] = $amt;
            } else {
                $kw = '%' . $rawKw . '%';
                $kwPeriod = '%' . preg_replace('/[^0-9]/', '', $rawKw) . '%';
                $kwBimonth = null;
                if (preg_match('/(\d{4})[^\d]+(\d{1,2})[^\d]+(\d{1,2})/', $rawKw, $_bm)) {
                    $kwBimonth = sprintf('%04d-%02d-%02d', (int)$_bm[1], (int)$_bm[2], (int)$_bm[3]);
                }
                $periodClauses = "si.period LIKE ?";
                if ($kwBimonth !== null) {
                    $periodClauses .= " OR si.report_period = ?";
                }
                $where[] = "(si.invoice_number LIKE ? OR si.customer_name LIKE ? OR si.note LIKE ?
                    OR si.customer_tax_id LIKE ?
                    OR CAST(si.invoice_date AS CHAR) LIKE ?
                    OR CAST(si.amount_untaxed AS CHAR) LIKE ?
                    OR CAST(si.tax_amount AS CHAR) LIKE ?
                    OR CAST(si.total_amount AS CHAR) LIKE ?
                    OR {$periodClauses})";
                for ($i = 0; $i < 8; $i++) { $params[] = $kw; }
                $params[] = $kwPeriod;
                if ($kwBimonth !== null) { $params[] = $kwBimonth; }
            }
        }
        if (!empty($filters['invoice_type'])) {
            $where[] = "si.invoice_type = ?";
            $params[] = $filters['invoice_type'];
        }
        if (!empty($filters['invoice_format'])) {
            $where[] = "si.invoice_format = ?";
            $params[] = $filters['invoice_format'];
        }

        $whereStr = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM sales_invoices si WHERE " . $whereStr;
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT si.*, u.real_name AS created_by_name
                FROM sales_invoices si
                LEFT JOIN users u ON u.id = si.created_by
                WHERE " . $whereStr . "
                ORDER BY si.invoice_date DESC, si.id DESC
                LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array(
            'data'     => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'    => $total,
            'page'     => $page,
            'lastPage' => $lastPage,
            'perPage'  => $perPage,
        );
    }

    /**
     * 取得單筆銷項發票
     */
    public function getSalesInvoiceById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM sales_invoices WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 檢查銷項發票號碼是否重複
     * @param string $invoiceNumber 發票號碼
     * @param int|null $excludeId 排除的 ID（編輯時用）
     * @return bool 重複回傳 true
     */
    public function isSalesInvoiceNumberDuplicate($invoiceNumber, $excludeId = null)
    {
        if (empty($invoiceNumber)) {
            return false; // 空白不檢查（允許未開立的暫存）
        }
        if ($excludeId) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM sales_invoices WHERE invoice_number = ? AND id != ?");
            $stmt->execute(array($invoiceNumber, (int)$excludeId));
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM sales_invoices WHERE invoice_number = ?");
            $stmt->execute(array($invoiceNumber));
        }
        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * 新增銷項發票
     */
    public function createSalesInvoice($data)
    {
        // 唯一性檢查
        if (!empty($data['invoice_number']) && $this->isSalesInvoiceNumberDuplicate($data['invoice_number'])) {
            throw new Exception('發票號碼「' . $data['invoice_number'] . '」已存在，無法新增');
        }

        $data = $this->autoCalculateTax($data);
        if (empty($data['period']) && !empty($data['invoice_date'])) {
            $data['period'] = $this->calculatePeriod($data['invoice_date']);
        }

        $sql = "INSERT INTO sales_invoices
                (invoice_number, invoice_date, customer_name, customer_tax_id,
                 invoice_type, amount_untaxed, tax_amount, total_amount, tax_rate,
                 reference_type, reference_id, period, status, note,
                 report_period, invoice_format, deduction_category,
                 created_by, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            !empty($data['invoice_number']) ? $data['invoice_number'] : null,
            !empty($data['invoice_date']) ? $data['invoice_date'] : date('Y-m-d'),
            !empty($data['customer_name']) ? $data['customer_name'] : null,
            !empty($data['customer_tax_id']) ? $data['customer_tax_id'] : null,
            !empty($data['invoice_type']) ? $data['invoice_type'] : '應稅',
            isset($data['amount_untaxed']) ? $data['amount_untaxed'] : 0,
            isset($data['tax_amount']) ? $data['tax_amount'] : 0,
            isset($data['total_amount']) ? $data['total_amount'] : 0,
            isset($data['tax_rate']) ? $data['tax_rate'] : 5,
            !empty($data['reference_type']) ? $data['reference_type'] : null,
            !empty($data['reference_id']) ? $data['reference_id'] : null,
            !empty($data['period']) ? $data['period'] : null,
            !empty($data['status']) ? $data['status'] : 'pending',
            !empty($data['note']) ? $data['note'] : null,
            !empty($data['report_period']) ? $data['report_period'] : null,
            !empty($data['invoice_format']) ? $data['invoice_format'] : null,
            !empty($data['deduction_category']) ? $data['deduction_category'] : null,
            !empty($data['created_by']) ? $data['created_by'] : null,
        ));
        return $this->db->lastInsertId();
    }

    /**
     * 更新銷項發票
     */
    public function updateSalesInvoice($id, $data)
    {
        // 唯一性檢查（排除自己）
        if (!empty($data['invoice_number']) && $this->isSalesInvoiceNumberDuplicate($data['invoice_number'], $id)) {
            throw new Exception('發票號碼「' . $data['invoice_number'] . '」已存在，無法更新');
        }

        $data = $this->autoCalculateTax($data);
        if (empty($data['period']) && !empty($data['invoice_date'])) {
            $data['period'] = $this->calculatePeriod($data['invoice_date']);
        }

        $sql = "UPDATE sales_invoices SET
                invoice_number = ?, invoice_date = ?, customer_name = ?, customer_tax_id = ?,
                invoice_type = ?, amount_untaxed = ?, tax_amount = ?, total_amount = ?, tax_rate = ?,
                reference_type = ?, reference_id = ?, period = ?, status = ?,
                note = ?, report_period = ?, invoice_format = ?, deduction_category = ?,
                updated_at = NOW()
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            !empty($data['invoice_number']) ? $data['invoice_number'] : null,
            !empty($data['invoice_date']) ? $data['invoice_date'] : date('Y-m-d'),
            !empty($data['customer_name']) ? $data['customer_name'] : null,
            !empty($data['customer_tax_id']) ? $data['customer_tax_id'] : null,
            !empty($data['invoice_type']) ? $data['invoice_type'] : '應稅',
            isset($data['amount_untaxed']) ? $data['amount_untaxed'] : 0,
            isset($data['tax_amount']) ? $data['tax_amount'] : 0,
            isset($data['total_amount']) ? $data['total_amount'] : 0,
            isset($data['tax_rate']) ? $data['tax_rate'] : 5,
            !empty($data['reference_type']) ? $data['reference_type'] : null,
            !empty($data['reference_id']) ? $data['reference_id'] : null,
            !empty($data['period']) ? $data['period'] : null,
            !empty($data['status']) ? $data['status'] : 'pending',
            !empty($data['note']) ? $data['note'] : null,
            !empty($data['report_period']) ? $data['report_period'] : null,
            !empty($data['invoice_format']) ? $data['invoice_format'] : null,
            !empty($data['deduction_category']) ? $data['deduction_category'] : null,
            $id,
        ));
        return $stmt->rowCount();
    }

    /**
     * 刪除銷項發票 (僅 voided 可刪，需先作廢)
     */
    public function deleteSalesInvoice($id)
    {
        $invoice = $this->getSalesInvoiceById($id);
        if (!$invoice) {
            return false;
        }
        if ($invoice['status'] !== 'voided') {
            throw new Exception('請先作廢此發票再進行刪除');
        }
        $stmt = $this->db->prepare("DELETE FROM sales_invoices WHERE id = ? AND status = 'voided'");
        $stmt->execute(array($id));
        return $stmt->rowCount() > 0;
    }

    /**
     * 作廢銷項發票
     */
    public function voidSalesInvoice($id)
    {
        $stmt = $this->db->prepare("UPDATE sales_invoices SET status = 'voided', updated_at = NOW() WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->rowCount() > 0;
    }

    // ============================================================
    // 401 申報 (Tax Report)
    // ============================================================

    /**
     * 取得稅務彙總
     * @param string $period 期間 e.g. '202603-04' (year + bimonth)
     */
    public function getTaxSummary($period)
    {
        // 解析期間 → 起迄月
        $parsed = $this->parseTaxPeriod($period);
        if (!$parsed) {
            return null;
        }
        $startMonth = $parsed['start'];
        $endMonth = $parsed['end'];

        // 銷項 - 應稅銷售額 & 稅額（invoice_type: 應稅/三聯式/二聯式 皆為應稅）
        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN invoice_type NOT IN ('免稅','零稅率') AND status != 'voided' THEN amount_untaxed ELSE 0 END), 0) AS sales_taxable_amount,
                    COALESCE(SUM(CASE WHEN invoice_type NOT IN ('免稅','零稅率') AND status != 'voided' THEN tax_amount ELSE 0 END), 0) AS sales_tax,
                    COALESCE(SUM(CASE WHEN invoice_type = '免稅' AND status != 'voided' THEN amount_untaxed ELSE 0 END), 0) AS sales_exempt_amount,
                    COALESCE(SUM(CASE WHEN status != 'voided' THEN total_amount ELSE 0 END), 0) AS sales_total,
                    COUNT(CASE WHEN status != 'voided' THEN 1 END) AS sales_count,
                    COUNT(CASE WHEN status = 'voided' THEN 1 END) AS sales_voided_count
                FROM sales_invoices
                WHERE period IN (" . implode(',', array_fill(0, count($this->getMonthsInRange($startMonth, $endMonth)), '?')) . ")";
        $months = $this->getMonthsInRange($startMonth, $endMonth);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($months);
        $salesRow = $stmt->fetch(PDO::FETCH_ASSOC);

        // 進項 - 可扣抵 & 不可扣抵
        $sql2 = "SELECT
                    COALESCE(SUM(CASE WHEN deduction_type = 'deductible' AND status != 'voided' THEN amount_untaxed ELSE 0 END), 0) AS purchase_deductible_amount,
                    COALESCE(SUM(CASE WHEN deduction_type = 'deductible' AND status != 'voided' THEN tax_amount ELSE 0 END), 0) AS purchase_deductible_tax,
                    COALESCE(SUM(CASE WHEN deduction_type = 'non_deductible' AND status != 'voided' THEN amount_untaxed ELSE 0 END), 0) AS purchase_non_deductible_amount,
                    COALESCE(SUM(CASE WHEN deduction_type = 'non_deductible' AND status != 'voided' THEN tax_amount ELSE 0 END), 0) AS purchase_non_deductible_tax,
                    COALESCE(SUM(CASE WHEN status != 'voided' THEN total_amount ELSE 0 END), 0) AS purchase_total,
                    COUNT(CASE WHEN status != 'voided' THEN 1 END) AS purchase_count,
                    COUNT(CASE WHEN status = 'voided' THEN 1 END) AS purchase_voided_count
                FROM purchase_invoices
                WHERE period IN (" . implode(',', array_fill(0, count($months), '?')) . ")";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute($months);
        $purchaseRow = $stmt2->fetch(PDO::FETCH_ASSOC);

        $salesTax = (int) $salesRow['sales_tax'];
        $purchaseTax = (int) $purchaseRow['purchase_deductible_tax'];
        $taxPayable = $salesTax - $purchaseTax;

        return array_merge($salesRow, $purchaseRow, array(
            'tax_payable' => $taxPayable,
            'period'      => $period,
        ));
    }

    /**
     * 取得稅務明細 (進項或銷項)
     */
    public function getTaxDetail($period, $type = 'purchase')
    {
        $parsed = $this->parseTaxPeriod($period);
        if (!$parsed) {
            return array();
        }
        $months = $this->getMonthsInRange($parsed['start'], $parsed['end']);
        $placeholders = implode(',', array_fill(0, count($months), '?'));

        if ($type === 'purchase') {
            $sql = "SELECT pi.*, u.real_name AS created_by_name
                    FROM purchase_invoices pi
                    LEFT JOIN users u ON u.id = pi.created_by
                    WHERE pi.period IN ($placeholders)
                    ORDER BY pi.invoice_date ASC, pi.id ASC";
        } else {
            $sql = "SELECT si.*, u.real_name AS created_by_name
                    FROM sales_invoices si
                    LEFT JOIN users u ON u.id = si.created_by
                    WHERE si.period IN ($placeholders)
                    ORDER BY si.invoice_date ASC, si.id ASC";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($months);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得有資料的期間
     */
    public function getAvailablePeriods()
    {
        $periods = array();

        $sql1 = "SELECT DISTINCT period FROM purchase_invoices WHERE period IS NOT NULL ORDER BY period DESC";
        $stmt1 = $this->db->query($sql1);
        while ($row = $stmt1->fetch(PDO::FETCH_ASSOC)) {
            $periods[$row['period']] = true;
        }

        $sql2 = "SELECT DISTINCT period FROM sales_invoices WHERE period IS NOT NULL ORDER BY period DESC";
        $stmt2 = $this->db->query($sql2);
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $periods[$row['period']] = true;
        }

        $result = array_keys($periods);
        rsort($result);
        return $result;
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * 依日期計算所屬期間 (YYYYMM)
     */
    public function calculatePeriod($date)
    {
        $ts = strtotime($date);
        if (!$ts) {
            return null;
        }
        return date('Ym', $ts);
    }

    /**
     * 解析401報稅期間字串 e.g. '2026-03-04' → start=202603, end=202604
     */
    private function parseTaxPeriod($period)
    {
        // 格式: YYYY-MM-MM (e.g. 2026-01-02)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $period, $m)) {
            return array(
                'start' => $m[1] . $m[2],
                'end'   => $m[1] . $m[3],
            );
        }
        return null;
    }

    /**
     * 取得月份範圍內的所有月份 (YYYYMM 格式)
     */
    private function getMonthsInRange($startMonth, $endMonth)
    {
        $months = array();
        $current = $startMonth;
        while ($current <= $endMonth) {
            $months[] = $current;
            $year = (int) substr($current, 0, 4);
            $month = (int) substr($current, 4, 2);
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
            $current = sprintf('%04d%02d', $year, $month);
        }
        return $months;
    }

    /**
     * 自動計算稅額
     */
    private function autoCalculateTax($data)
    {
        $amountUntaxed = isset($data['amount_untaxed']) ? (float) $data['amount_untaxed'] : 0;
        $invoiceType = !empty($data['invoice_type']) ? $data['invoice_type'] : '應稅';

        // 免稅或零稅率 → 稅額為 0
        if (in_array($invoiceType, array('免稅', '零稅率'))) {
            $data['tax_amount'] = 0;
            $data['tax_rate'] = 0;
            $data['total_amount'] = $amountUntaxed;
        } else {
            $taxRate = isset($data['tax_rate']) ? (float) $data['tax_rate'] : 5;
            $data['tax_rate'] = $taxRate;
            if (!isset($data['tax_amount']) || $data['tax_amount'] === '' || $data['tax_amount'] === null) {
                $data['tax_amount'] = round($amountUntaxed * $taxRate / 100);
            }
            $data['total_amount'] = $amountUntaxed + (float) $data['tax_amount'];
        }

        return $data;
    }

    /**
     * 取得供應商清單 (from vendors table or distinct from purchase_invoices)
     */
    public function getVendors()
    {
        // 嘗試從 vendors 表取得
        try {
            $stmt = $this->db->query("SELECT id, TRIM(name) AS name, tax_id, tax_id1, TRIM(header1) AS header1, tax_id2, TRIM(header2) AS header2 FROM vendors WHERE is_active = 1 ORDER BY name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // fallback: 從進項發票取得不重複供應商
            $stmt = $this->db->query("SELECT DISTINCT vendor_name AS name, vendor_tax_id AS tax_id FROM purchase_invoices WHERE vendor_name IS NOT NULL AND vendor_name != '' ORDER BY vendor_name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    /**
     * 取得客戶清單
     */
    public function getCustomers()
    {
        try {
            $stmt = $this->db->query("SELECT id, TRIM(name) AS name, tax_id, invoice_title FROM customers WHERE is_active = 1 ORDER BY name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            try {
                $stmt = $this->db->query("SELECT id, TRIM(name) AS name, tax_id FROM customers ORDER BY name");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e2) {
                $stmt = $this->db->query("SELECT DISTINCT customer_name AS name, customer_tax_id AS tax_id FROM sales_invoices WHERE customer_name IS NOT NULL AND customer_name != '' ORDER BY customer_name");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

    /**
     * 取得供應商資訊 by ID
     */
    public function getVendorById($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, name, tax_id FROM vendors WHERE id = ?");
            $stmt->execute(array($id));
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 產生期間下拉選項 (近3年)
     */
    public function getPeriodOptions()
    {
        $options = array();
        $currentYear = (int) date('Y');
        for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
            for ($m = 12; $m >= 1; $m--) {
                $options[] = sprintf('%04d%02d', $y, $m);
            }
        }
        return $options;
    }

    /**
     * 產生401報稅期間選項 (近3年)
     */
    public function getTaxPeriodOptions()
    {
        $options = array();
        $currentYear = (int) date('Y');
        $bimonths = array(
            array('01', '02', '1-2月'),
            array('03', '04', '3-4月'),
            array('05', '06', '5-6月'),
            array('07', '08', '7-8月'),
            array('09', '10', '9-10月'),
            array('11', '12', '11-12月'),
        );
        for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
            foreach ($bimonths as $bm) {
                $value = $y . '-' . $bm[0] . '-' . $bm[1];
                $label = $y . '年 ' . $bm[2];
                $options[] = array('value' => $value, 'label' => $label);
            }
        }
        return $options;
    }
}
