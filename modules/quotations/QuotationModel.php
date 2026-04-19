<?php
/**
 * 報價單模型
 */
class QuotationModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function statusLabel($status)
    {
        $map = array(
            'draft' => '草稿',
            'pending_approval' => '待簽核',
            'approved' => '已核准',
            'rejected_internal' => '退回修改',
            'sent' => '已送客戶',
            'accepted' => '已接受',  // 舊值相容
            'rejected' => '已拒絕',  // 舊值相容
            'customer_accepted' => '客戶已接受',
            'customer_rejected' => '客戶已拒絕',
            'revision_needed' => '待修改',
            'pending_revision' => '變更簽核中',
        );
        return isset($map[$status]) ? $map[$status] : $status;
    }

    public static function statusBadge($status)
    {
        $map = array(
            'draft' => 'warning',
            'pending_approval' => 'info',
            'approved' => 'primary',
            'rejected_internal' => 'danger',
            'sent' => 'primary',
            'accepted' => 'success',
            'rejected' => 'danger',
            'customer_accepted' => 'success',
            'customer_rejected' => 'danger',
            'revision_needed' => 'warning',
            'pending_revision' => 'warning',
        );
        return isset($map[$status]) ? $map[$status] : '';
    }

    /**
     * 是否可編輯
     */
    public static function canEdit($status)
    {
        return in_array($status, array('draft', 'rejected_internal', 'revision_needed', 'approved'));
    }

    public static function formatLabel($format)
    {
        $map = array('simple' => '普銷', 'project' => '專案');
        return isset($map[$format]) ? $map[$format] : $format;
    }

    /**
     * 列表
     */
    public function getList(array $branchIds, array $filters = array())
    {
        $where = 'q.branch_id IN (' . implode(',', array_fill(0, count($branchIds), '?')) . ')';
        $params = $branchIds;

        if (!empty($filters['month'])) {
            $where .= ' AND q.quote_date LIKE ?';
            $params[] = $filters['month'] . '%';
        }
        if (!empty($filters['status'])) {
            $where .= ' AND q.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (q.customer_name LIKE ? OR q.quotation_number LIKE ? OR q.site_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        // 僅自己的報價單
        if (!empty($filters['own_only'])) {
            $where .= ' AND q.sales_id = ?';
            $params[] = $filters['own_only'];
        }

        $stmt = $this->db->prepare("
            SELECT q.*, b.name AS branch_name, s.real_name AS sales_name
            FROM quotations q
            JOIN branches b ON q.branch_id = b.id
            LEFT JOIN users s ON q.sales_id = s.id
            WHERE $where
            ORDER BY q.quote_date DESC, q.id DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 取得單筆（含區段和項目）
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare('
            SELECT q.*, b.name AS branch_name, s.real_name AS sales_name,
                   cr.real_name AS creator_name
            FROM quotations q
            JOIN branches b ON q.branch_id = b.id
            LEFT JOIN users s ON q.sales_id = s.id
            LEFT JOIN users cr ON q.created_by = cr.id
            WHERE q.id = ?
        ');
        $stmt->execute(array($id));
        $quote = $stmt->fetch();
        if (!$quote) return null;

        // 載入區段
        $secStmt = $this->db->prepare('SELECT * FROM quotation_sections WHERE quotation_id = ? ORDER BY sort_order, id');
        $secStmt->execute(array($id));
        $sections = $secStmt->fetchAll();

        // 載入每個區段的項目
        $itemStmt = $this->db->prepare('SELECT * FROM quotation_items WHERE section_id = ? ORDER BY sort_order, id');
        foreach ($sections as &$sec) {
            $itemStmt->execute(array($sec['id']));
            $sec['items'] = $itemStmt->fetchAll();
        }

        $quote['sections'] = $sections;
        return $quote;
    }

    /**
     * 新增
     */
    public function create(array $data)
    {
        $number = $this->generateNumber();
        $stmt = $this->db->prepare('
            INSERT INTO quotations (quotation_number, branch_id, quote_company, case_id, format, customer_id, customer_name,
                contact_person, contact_phone, site_name, site_address,
                invoice_title, invoice_tax_id, quote_date, valid_date,
                payment_terms, notes, sales_id, hide_model_on_print, tax_free, has_discount, discount_amount, warranty_months, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            $number,
            $data['branch_id'],
            !empty($data['quote_company']) ? $data['quote_company'] : 'hershun',
            $data['case_id'] ?: null,
            $data['format'],
            !empty($data['customer_id']) ? $data['customer_id'] : null,
            $data['customer_name'],
            $data['contact_person'] ?: null,
            $data['contact_phone'] ?: null,
            $data['site_name'] ?: null,
            $data['site_address'] ?: null,
            $data['invoice_title'] ?: null,
            $data['invoice_tax_id'] ?: null,
            $data['quote_date'],
            $data['valid_date'],
            $data['payment_terms'] ?: null,
            $data['notes'] ?: null,
            $data['sales_id'] ?: null,
            !empty($data['hide_model_on_print']) ? 1 : 0,
            !empty($data['tax_free']) ? 1 : 0,
            !empty($data['has_discount']) ? 1 : 0,
            !empty($data['discount_amount']) ? $data['discount_amount'] : null,
            !empty($data['warranty_months']) ? (int)$data['warranty_months'] : 12,
            Auth::id(),
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新
     */
    public function update($id, array $data)
    {
        $stmt = $this->db->prepare('
            UPDATE quotations SET
                quote_company = ?, case_id = ?, format = ?, customer_id = ?, customer_name = ?,
                contact_person = ?, contact_phone = ?, site_name = ?, site_address = ?,
                invoice_title = ?, invoice_tax_id = ?, quote_date = ?, valid_date = ?,
                payment_terms = ?, notes = ?, sales_id = ?, hide_model_on_print = ?,
                tax_free = ?, has_discount = ?, discount_amount = ?, warranty_months = ?
            WHERE id = ?
        ');
        $stmt->execute(array(
            !empty($data['quote_company']) ? $data['quote_company'] : 'hershun',
            $data['case_id'] ?: null,
            $data['format'],
            !empty($data['customer_id']) ? $data['customer_id'] : null,
            $data['customer_name'],
            $data['contact_person'] ?: null,
            $data['contact_phone'] ?: null,
            $data['site_name'] ?: null,
            $data['site_address'] ?: null,
            $data['invoice_title'] ?: null,
            $data['invoice_tax_id'] ?: null,
            $data['quote_date'],
            $data['valid_date'],
            $data['payment_terms'] ?: null,
            $data['notes'] ?: null,
            $data['sales_id'] ?: null,
            !empty($data['hide_model_on_print']) ? 1 : 0,
            !empty($data['tax_free']) ? 1 : 0,
            !empty($data['has_discount']) ? 1 : 0,
            !empty($data['discount_amount']) ? $data['discount_amount'] : null,
            !empty($data['warranty_months']) ? (int)$data['warranty_months'] : 12,
            $id,
        ));
    }

    /**
     * 儲存內部成本欄位
     */
    public function saveLaborCost($id, array $data)
    {
        $days   = !empty($data['labor_days']) ? (float)$data['labor_days'] : null;
        $people = !empty($data['labor_people']) ? (int)$data['labor_people'] : null;
        $hours  = !empty($data['labor_hours']) ? (float)$data['labor_hours'] : null;

        // 時數為單人工時；若有填天數+人數則強制 hours = days × 8（忽略送來的 hours）
        if ($days && $people) {
            $hours = $days * 8;
        }
        // 施工時數最低 1
        if ($hours !== null && $hours > 0 && $hours < 1) {
            $hours = 1;
        }
        // 人力成本一律 = 人數 × 時數 × 時薪（禁止手動輸入）
        $laborCost = null;
        if ($hours && $people) {
            $hrStmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'labor_hourly_cost' LIMIT 1");
            $hrStmt->execute();
            $hourlyCost = (int)$hrStmt->fetchColumn() ?: 560;
            $laborCost = (int)round($people * $hours * $hourlyCost);
        }

        // cable_not_used：有勾選 → 線材成本 0；無連結案件 → 視為 0
        $cableNotUsed = !empty($data['cable_not_used']) ? 1 : 0;
        $hasCase = !empty($data['case_id']);
        $cableCost = ($cableNotUsed || !$hasCase) ? 0 : (int)($data['cable_cost'] ?? 0);

        $stmt = $this->db->prepare('
            UPDATE quotations SET labor_days = ?, labor_people = ?, labor_hours = ?,
                labor_cost_total = ?, cable_cost = ?, cable_not_used = ?
            WHERE id = ?
        ');
        $stmt->execute(array(
            $days, $people, $hours, $laborCost,
            $cableCost, $cableNotUsed,
            $id,
        ));
    }

    /**
     * 從 case_material_estimates 自動同步線材成本到報價單
     */
    public function syncCableCostFromEstimates($quotationId, $caseId)
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(cme.estimated_qty * COALESCE(
                CASE
                    WHEN p.cost_per_unit > 0 THEN p.cost_per_unit
                    WHEN p.pack_qty > 0 THEN p.cost / p.pack_qty
                    ELSE p.cost
                END, 0
            )), 0) AS cable_total
            FROM case_material_estimates cme
            LEFT JOIN products p ON cme.product_id = p.id
            WHERE cme.case_id = ?
        ");
        $stmt->execute(array($caseId));
        $cableTotal = (int)$stmt->fetchColumn();

        $this->db->prepare("UPDATE quotations SET cable_cost = ? WHERE id = ?")
                 ->execute(array($cableTotal, $quotationId));

        // 重算利潤（取最新 subtotal 與 material_cost）
        $qStmt = $this->db->prepare("SELECT subtotal FROM quotations WHERE id = ?");
        $qStmt->execute(array($quotationId));
        $q = $qStmt->fetch();
        if ($q) {
            $matStmt = $this->db->prepare("
                SELECT COALESCE(SUM(qi.unit_cost * qi.quantity), 0)
                FROM quotation_items qi
                JOIN quotation_sections qs ON qi.section_id = qs.id
                WHERE qs.quotation_id = ?
            ");
            $matStmt->execute(array($quotationId));
            $materialCost = (int)$matStmt->fetchColumn();
            $this->recalcTotals($quotationId, (int)$q['subtotal'], $materialCost);
        }
    }

    /**
     * 刪除（僅草稿）
     */
    public function delete($id)
    {
        // CASCADE 會自動刪除 sections 和 items
        $this->db->prepare("DELETE FROM quotations WHERE id = ? AND status = 'draft'")->execute(array($id));
    }

    /**
     * 更新狀態
     */
    public function updateStatus($id, $status)
    {
        $this->db->prepare('UPDATE quotations SET status = ? WHERE id = ?')->execute(array($status, $id));

        // 連動案件 has_quotation
        $quote = $this->getById($id);
        if ($quote && $quote['case_id']) {
            $this->syncCaseQuotationFlag($quote['case_id']);
        }
    }

    /**
     * 儲存區段和項目（delete-all-then-insert）
     */
    public function saveSections($quotationId, array $sectionsData)
    {
        // 刪除舊資料（CASCADE 會連帶刪除 items）
        $this->db->prepare('DELETE FROM quotation_sections WHERE quotation_id = ?')->execute(array($quotationId));

        $secStmt = $this->db->prepare('
            INSERT INTO quotation_sections (quotation_id, title, sort_order, subtotal, discount_amount)
            VALUES (?, ?, ?, ?, ?)
        ');
        $itemStmt = $this->db->prepare('
            INSERT INTO quotation_items (section_id, product_id, item_name, model_number, quantity, unit, unit_price, amount, remark, sort_order, unit_cost, cost_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $grandSubtotal = 0;
        $grandCost = 0;

        foreach ($sectionsData as $sIdx => $sec) {
            $secSubtotal = 0;
            // 區段優惠價（空字串/未勾選 = NULL）
            $secDiscount = null;
            if (isset($sec['discount_amount']) && $sec['discount_amount'] !== '' && $sec['discount_amount'] !== null) {
                $secDiscount = (int)$sec['discount_amount'];
                if ($secDiscount < 0) $secDiscount = 0;
            }
            $secStmt->execute(array($quotationId, $sec['title'] ?: '', (int)$sIdx, 0, $secDiscount));
            $sectionId = (int)$this->db->lastInsertId();

            $items = isset($sec['items']) ? $sec['items'] : array();
            foreach ($items as $iIdx => $item) {
                if (empty($item['item_name'])) continue;
                $qty = (float)$item['quantity'];
                if ($qty < 0) $qty = 0;
                $price = (int)$item['unit_price'];
                $amount = (int)round($qty * $price);
                $unitCost = (int)($item['unit_cost'] ?: 0);
                $costAmount = (int)round($qty * $unitCost);

                $itemStmt->execute(array(
                    $sectionId,
                    $item['product_id'] ?: null,
                    $item['item_name'],
                    isset($item['model_number']) && $item['model_number'] !== '' ? $item['model_number'] : null,
                    $qty,
                    $item['unit'] ?: '式',
                    $price,
                    $amount,
                    $item['remark'] ?: null,
                    (int)$iIdx,
                    $unitCost,
                    $costAmount,
                ));
                $secSubtotal += $amount;
                $grandCost += $costAmount;
            }

            // 更新區段小計
            $this->db->prepare('UPDATE quotation_sections SET subtotal = ? WHERE id = ?')
                ->execute(array($secSubtotal, $sectionId));
            // 有優惠價則以優惠價入合計，否則用小計
            $grandSubtotal += ($secDiscount !== null) ? $secDiscount : $secSubtotal;
        }

        // 更新報價單金額
        $this->recalcTotals($quotationId, $grandSubtotal, $grandCost);
    }

    /**
     * 公開重算（供 controller 呼叫）
     */
    public function recalcTotalsPublic($quotationId, $subtotal, $materialCost)
    {
        $this->recalcTotals($quotationId, $subtotal, $materialCost);
    }

    /**
     * 重算報價單金額
     */
    private function recalcTotals($quotationId, $subtotal, $materialCost)
    {
        $stmt = $this->db->prepare('SELECT tax_rate, labor_cost_total, cable_cost, tax_free, has_discount, discount_amount FROM quotations WHERE id = ?');
        $stmt->execute(array($quotationId));
        $q = $stmt->fetch();
        $taxRate = $q ? (float)$q['tax_rate'] : 5.0;
        $laborCost = $q ? (int)$q['labor_cost_total'] : 0;
        $cableCost = $q ? (int)$q['cable_cost'] : 0;
        $isTaxFree = $q ? (int)$q['tax_free'] : 0;
        $hasDiscount = $q ? (int)$q['has_discount'] : 0;
        $discountAmount = $q ? (int)$q['discount_amount'] : 0;

        $taxAmount = $isTaxFree ? 0 : (int)round($subtotal * $taxRate / 100);
        $totalAmount = $subtotal + $taxAmount;
        // 營運成本 = 人力 × 倍率（不計入 total_cost 以免破壞 cases.php 的 q_material_cost 計算）
        $opRateStmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'operation_cost_rate' LIMIT 1");
        $opRateStmt->execute();
        $opRate = (float)($opRateStmt->fetchColumn() ?: 128);
        $opsCost = (int)round($laborCost * $opRate / 100);
        $totalCost = $materialCost + $laborCost + $cableCost;
        // 預估成交金額：有優惠價取優惠價，無則取未稅合計
        $projected = ($hasDiscount && $discountAmount > 0) ? $discountAmount : $subtotal;
        // 利潤以預估成交金額為基礎，扣除實體成本 + 營運成本
        $profitAmount = $projected - ($totalCost + $opsCost);
        $profitRate = ($projected > 0) ? round($profitAmount / $projected * 100, 2) : 0;

        $this->db->prepare('
            UPDATE quotations SET subtotal = ?, tax_amount = ?, total_amount = ?,
                total_cost = ?, profit_amount = ?, profit_rate = ?
            WHERE id = ?
        ')->execute(array($subtotal, $taxAmount, $totalAmount, $totalCost, $profitAmount, $profitRate, $quotationId));
    }

    /**
     * 複製報價單
     */
    public function duplicate($id)
    {
        $orig = $this->getById($id);
        if (!$orig) return null;

        $data = $orig;
        $data['format'] = $orig['format'];
        $data['quote_date'] = date('Y-m-d');
        $data['valid_date'] = date('Y-m-d', strtotime('+30 days'));
        $newId = $this->create($data);

        // 複製區段和項目
        $sectionsData = array();
        foreach ($orig['sections'] as $sec) {
            $secData = array('title' => $sec['title'], 'items' => array(), 'discount_amount' => isset($sec['discount_amount']) ? $sec['discount_amount'] : null);
            foreach ($sec['items'] as $item) {
                $secData['items'][] = array(
                    'product_id' => $item['product_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'remark' => $item['remark'],
                    'unit_cost' => $item['unit_cost'],
                );
            }
            $sectionsData[] = $secData;
        }
        $this->saveSections($newId, $sectionsData);

        // 複製人力成本
        $this->saveLaborCost($newId, array(
            'labor_days' => $orig['labor_days'],
            'labor_people' => $orig['labor_people'],
            'labor_hours' => $orig['labor_hours'],
            'labor_cost_total' => $orig['labor_cost_total'],
            'cable_cost' => $orig['cable_cost'],
        ));

        return $newId;
    }

    /**
     * 同步案件的 has_quotation 旗標
     */
    public function syncCaseQuotationFlag($caseId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM quotations
            WHERE case_id = ? AND status IN ('sent','accepted')
        ");
        $stmt->execute(array($caseId));
        $hasQuote = (int)$stmt->fetchColumn() > 0 ? 1 : 0;

        $this->db->prepare('UPDATE case_readiness SET has_quotation = ? WHERE case_id = ?')
            ->execute(array($hasQuote, $caseId));
    }

    /**
     * 取得業務人員列表
     */
    public function getSalespeople(array $branchIds)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT id, real_name, role FROM users
            WHERE branch_id IN ($ph) AND is_active = 1
              AND (role IN ('boss','manager','sales_manager','sales','sales_assistant','vice_president') OR is_sales = 1)
              AND employee_id IS NOT NULL AND employee_id != ''
            ORDER BY real_name
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    /**
     * 取得案件列表（供選擇關聯）
     */
    public function getCaseOptions(array $branchIds)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $excludeSubStatus = array('已報價無意願', '報價無下文', '無效', '客戶毀約');
        $phSub = implode(',', array_fill(0, count($excludeSubStatus), '?'));
        $stmt = $this->db->prepare("
            SELECT id, case_number, title FROM cases
            WHERE branch_id IN ($ph)
              AND (sub_status IS NULL OR sub_status NOT IN ($phSub))
            ORDER BY id DESC
            LIMIT 200
        ");
        $stmt->execute(array_merge($branchIds, $excludeSubStatus));
        return $stmt->fetchAll();
    }

    public function getBranches()
    {
        return $this->db->query('SELECT * FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll();
    }

    /**
     * 取得案件的報價單列表
     */
    public function getByCase($caseId)
    {
        $stmt = $this->db->prepare('
            SELECT q.*, s.real_name AS sales_name
            FROM quotations q
            LEFT JOIN users s ON q.sales_id = s.id
            WHERE q.case_id = ?
            ORDER BY q.quote_date DESC
        ');
        $stmt->execute(array($caseId));
        return $stmt->fetchAll();
    }

    /**
     * 客戶接受報價時，自動回填案件帳務資訊
     */
    /**
     * 同步報價金額到案件（任何報價單狀態都觸發）
     */
    public function syncCaseQuoteAmount($quotationId)
    {
        $stmt = $this->db->prepare('SELECT case_id FROM quotations WHERE id = ?');
        $stmt->execute(array($quotationId));
        $caseId = $stmt->fetchColumn();
        if (!$caseId) return;

        // 報價金額：取最新一筆報價單的 total_amount（不分狀態，草稿即帶入）
        $stmt = $this->db->prepare('SELECT total_amount FROM quotations WHERE case_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(array($caseId));
        $quoteTotal = $stmt->fetchColumn();

        if ($quoteTotal !== false) {
            $this->db->prepare('UPDATE cases SET quote_amount = ? WHERE id = ?')
                ->execute(array((int)$quoteTotal, $caseId));
        }
    }

    public function fillCaseFinancials($quotationId)
    {
        $stmt = $this->db->prepare('SELECT case_id, subtotal, tax_amount, total_amount, tax_free FROM quotations WHERE id = ?');
        $stmt->execute(array($quotationId));
        $q = $stmt->fetch();
        if (!$q || empty($q['case_id'])) {
            return false;
        }

        $isTaxFree = (int)$q['tax_free'];
        $isTaxIncluded = $isTaxFree ? '未稅(不開發票)' : '含稅(需開發票)';

        // 計算總收款金額（從 case_payments）
        $payStmt = $this->db->prepare('SELECT COALESCE(SUM(amount), 0) FROM case_payments WHERE case_id = ?');
        $payStmt->execute(array($q['case_id']));
        $totalCollected = (int)$payStmt->fetchColumn();

        $totalAmount = (int)$q['total_amount'];
        $balanceAmount = $totalAmount - $totalCollected;

        $this->db->prepare('
            UPDATE cases SET
                deal_amount = ?,
                is_tax_included = ?,
                tax_amount = ?,
                total_amount = ?,
                total_collected = ?,
                balance_amount = ?
            WHERE id = ?
        ')->execute(array(
            (int)$q['subtotal'],
            $isTaxIncluded,
            (int)$q['tax_amount'],
            $totalAmount,
            $totalCollected,
            $balanceAmount,
            $q['case_id']
        ));

        return true;
    }

    private function generateNumber()
    {
        return generate_doc_number('quotations');
    }

    /**
     * 報價單風險評分（純顯示、不影響簽核）
     * 分項：客戶類型 20、庫存 20、工期 15、特殊設備 15、付款紀錄 20、材料新近 10 = 100
     * 回傳：array('score'=>int, 'level'=>'low|medium|high', 'items'=>[['name','score','max','note']])
     */
    public function calculateRiskScore($quoteId)
    {
        $q = $this->db->prepare('SELECT id, case_id, customer_id, customer_name, labor_days FROM quotations WHERE id = ?');
        $q->execute(array($quoteId));
        $quote = $q->fetch();
        if (!$quote) return null;

        $items = array();
        $score = 0;

        // 1. 客戶類型 (max 20)
        $custScore = 0;
        $custNote = '新客戶';
        if (!empty($quote['customer_id'])) {
            // 成交過的案件數（含已完工結案）
            $st = $this->db->prepare("
                SELECT COUNT(*) FROM cases
                WHERE customer_id = ?
                  AND (sub_status IN ('已成交','跨月成交','現簽','電話報價成交','已完工結案','已完工待簽核')
                       OR status IN ('completed','closed','completed_pending'))
            ");
            $st->execute(array($quote['customer_id']));
            $doneCount = (int)$st->fetchColumn();
            if ($doneCount >= 3) {
                $custScore = 20;
                $custNote = '老客戶（成交 ' . $doneCount . ' 次）';
            } elseif ($doneCount >= 1) {
                $custScore = 12;
                $custNote = '合作過 ' . $doneCount . ' 次';
            } else {
                // 再查總案件數（有詢價但未成交也算部分關係）
                $st2 = $this->db->prepare("SELECT COUNT(*) FROM cases WHERE customer_id = ?");
                $st2->execute(array($quote['customer_id']));
                $anyCount = (int)$st2->fetchColumn();
                if ($anyCount >= 1) {
                    $custScore = 6;
                    $custNote = '往來過但尚無成交紀錄';
                } else {
                    $custScore = 3;
                    $custNote = '新客戶（首次）';
                }
            }
        }
        $items[] = array('name' => '客戶類型', 'score' => $custScore, 'max' => 20, 'note' => $custNote);
        $score += $custScore;

        // 2. 庫存充足度 (max 20) - 看報價區段所有產品庫存
        $stockScore = 0;
        $stockNote = '無料件需備';
        $itemStmt = $this->db->prepare('
            SELECT qi.product_id, qi.quantity
            FROM quotation_items qi
            JOIN quotation_sections qs ON qi.section_id = qs.id
            WHERE qs.quotation_id = ? AND qi.product_id IS NOT NULL
        ');
        $itemStmt->execute(array($quoteId));
        $needed = array();
        foreach ($itemStmt->fetchAll() as $it) {
            $pid = (int)$it['product_id'];
            if ($pid <= 0) continue;
            if (!isset($needed[$pid])) $needed[$pid] = 0;
            $needed[$pid] += (float)$it['quantity'];
        }
        if (!empty($needed)) {
            $ids = implode(',', array_keys($needed));
            $invStmt = $this->db->query("SELECT product_id, SUM(available_qty) AS qty FROM inventory WHERE product_id IN ($ids) GROUP BY product_id");
            $avail = array();
            foreach ($invStmt->fetchAll() as $r) {
                $avail[(int)$r['product_id']] = (float)$r['qty'];
            }
            $okCount = 0;
            $total = count($needed);
            foreach ($needed as $pid => $qty) {
                $have = isset($avail[$pid]) ? $avail[$pid] : 0;
                if ($have >= $qty) $okCount++;
            }
            if ($okCount == $total) {
                $stockScore = 20;
                $stockNote = '全部料件庫存充足';
            } elseif ($okCount >= $total * 0.7) {
                $stockScore = 12;
                $stockNote = '多數料件有庫存（' . $okCount . '/' . $total . '）';
            } elseif ($okCount > 0) {
                $stockScore = 6;
                $stockNote = '部分料件需叫貨（' . $okCount . '/' . $total . ' 有庫存）';
            } else {
                $stockScore = 0;
                $stockNote = '全部料件需叫貨';
            }
        } else {
            // 無明細料件 (純施工) — 中性給分
            $stockScore = 12;
            $stockNote = '無指定料件（純施工）';
        }
        $items[] = array('name' => '庫存狀態', 'score' => $stockScore, 'max' => 20, 'note' => $stockNote);
        $score += $stockScore;

        // 3. 工期長短 (max 15)
        $days = (float)($quote['labor_days'] ?: 0);
        $durScore = 0;
        $durNote = '未設定';
        if ($days > 0) {
            if ($days <= 1) {
                $durScore = 15; $durNote = '短工期（' . $days . ' 天）';
            } elseif ($days <= 2) {
                $durScore = 10; $durNote = '中工期（' . $days . ' 天）';
            } elseif ($days <= 3) {
                $durScore = 5; $durNote = '稍長工期（' . $days . ' 天）';
            } else {
                $durScore = 0; $durNote = '長工期（' . $days . ' 天，延誤風險高）';
            }
        } else {
            $durScore = 8; // 未設定給中性
            $durNote = '工期未設定';
        }
        $items[] = array('name' => '工期', 'score' => $durScore, 'max' => 15, 'note' => $durNote);
        $score += $durScore;

        // 4. 特殊設備 (max 15) - 查 case_site_conditions
        $eqScore = 15;
        $eqNote = '無特殊設備';
        if (!empty($quote['case_id'])) {
            $eq = $this->db->prepare('SELECT has_ladder_needed, needs_scissor_lift, high_ceiling_height FROM case_site_conditions WHERE case_id = ?');
            $eq->execute(array($quote['case_id']));
            $cond = $eq->fetch();
            if ($cond) {
                $flags = array();
                if (!empty($cond['has_ladder_needed'])) $flags[] = '拉梯';
                if (!empty($cond['needs_scissor_lift'])) $flags[] = '自走車';
                if (!empty($cond['high_ceiling_height'])) $flags[] = '挑高';
                $n = count($flags);
                if ($n == 0) { $eqScore = 15; $eqNote = '無特殊設備'; }
                elseif ($n == 1) { $eqScore = 8; $eqNote = '需' . implode('、', $flags); }
                else { $eqScore = 0; $eqNote = '需' . implode('、', $flags); }
            }
        }
        $items[] = array('name' => '特殊設備', 'score' => $eqScore, 'max' => 15, 'note' => $eqNote);
        $score += $eqScore;

        // 5. 付款紀錄 (max 20) - 查該客戶歷史 receivables（用 status='逾期' 判風險）
        $payScore = 10; // 中性預設
        $payNote = '無歷史紀錄';
        if (!empty($quote['customer_id'])) {
            $rStmt = $this->db->prepare("
                SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status = '已收款' THEN 1 ELSE 0 END) AS paid,
                       SUM(CASE WHEN status = '逾期' THEN 1 ELSE 0 END) AS overdue
                FROM receivables r
                WHERE r.case_id IN (SELECT id FROM cases WHERE customer_id = ?)
            ");
            try {
                $rStmt->execute(array($quote['customer_id']));
                $hist = $rStmt->fetch();
                $totalR = (int)(isset($hist['total']) ? $hist['total'] : 0);
                $paidR = (int)(isset($hist['paid']) ? $hist['paid'] : 0);
                $lateR = (int)(isset($hist['overdue']) ? $hist['overdue'] : 0);
                if ($totalR >= 2) {
                    if ($lateR == 0) { $payScore = 20; $payNote = '無逾期紀錄（' . $paidR . '/' . $totalR . ' 已收）'; }
                    elseif ($lateR <= $totalR * 0.2) { $payScore = 14; $payNote = '偶有逾期（' . $lateR . '/' . $totalR . '）'; }
                    elseif ($lateR <= $totalR * 0.5) { $payScore = 6; $payNote = '常逾期（' . $lateR . '/' . $totalR . '）'; }
                    else { $payScore = 0; $payNote = '付款風險高（逾期 ' . $lateR . '/' . $totalR . '）'; }
                } elseif ($totalR == 1) {
                    $payScore = $lateR > 0 ? 6 : 12;
                    $payNote = '僅 1 筆紀錄' . ($lateR > 0 ? '（逾期）' : '');
                } else {
                    $payScore = 10;
                    $payNote = '尚無請款紀錄';
                }
            } catch (Exception $e) {
                $payScore = 10;
                $payNote = '無法取得付款紀錄';
            }
        }
        $items[] = array('name' => '付款紀錄', 'score' => $payScore, 'max' => 20, 'note' => $payNote);
        $score += $payScore;

        // 6. 材料新近度 (max 10) - 查報價項目最近進貨日
        $recentScore = 0;
        $recentNote = '無料件';
        if (!empty($needed)) {
            $ids = implode(',', array_keys($needed));
            $inStmt = $this->db->query("SELECT product_id, MAX(created_at) AS last_in FROM inventory_transactions WHERE product_id IN ($ids) AND type = 'purchase_in' GROUP BY product_id");
            $recent30 = 0; $recent90 = 0; $total = count($needed); $noRec = 0;
            $now = time();
            $lastIns = array();
            foreach ($inStmt->fetchAll() as $r) {
                $lastIns[(int)$r['product_id']] = $r['last_in'];
            }
            foreach ($needed as $pid => $_q) {
                if (!isset($lastIns[$pid])) { $noRec++; continue; }
                $diffDays = ($now - strtotime($lastIns[$pid])) / 86400;
                if ($diffDays <= 30) $recent30++;
                elseif ($diffDays <= 90) $recent90++;
            }
            if ($total > 0) {
                $freshRatio = ($recent30 + $recent90 * 0.5) / $total;
                if ($freshRatio >= 0.8) { $recentScore = 10; $recentNote = '多數料件近期進貨（價格新）'; }
                elseif ($freshRatio >= 0.4) { $recentScore = 6; $recentNote = '部分料件近期進貨'; }
                else { $recentScore = 2; $recentNote = '料件多久未進貨（漲價風險）'; }
            }
        } else {
            $recentScore = 6;
            $recentNote = '無指定料件';
        }
        $items[] = array('name' => '材料新近度', 'score' => $recentScore, 'max' => 10, 'note' => $recentNote);
        $score += $recentScore;

        // 分級
        if ($score >= 70) $level = 'low';
        elseif ($score >= 40) $level = 'medium';
        else $level = 'high';

        return array(
            'score' => $score,
            'level' => $level,
            'items' => $items,
        );
    }
}
