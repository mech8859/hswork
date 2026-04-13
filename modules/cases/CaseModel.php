<?php
/**
 * 案件資料模型
 */
class CaseModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得案件清單 (含分頁/篩選)
     */
    public function getList(array $branchIds, array $filters = [], int $page = 1, int $perPage = 100): array
    {
        $branchPh = implode(',', array_fill(0, count($branchIds), '?'));
        $where = '(c.branch_id IN (' . $branchPh . ') OR c.id IN (SELECT case_id FROM case_branch_support WHERE branch_id IN (' . $branchPh . ')))';
        $params = array_merge($branchIds, $branchIds);

        if (!empty($filters['status'])) {
            // 支援逗號分隔多值，例如 status=incomplete,awaiting_dispatch
            $statuses = explode(',', $filters['status']);
            if (count($statuses) > 1) {
                $ph = implode(',', array_fill(0, count($statuses), '?'));
                $where .= " AND c.status IN ($ph)";
                foreach ($statuses as $sv) {
                    $params[] = trim($sv);
                }
            } else {
                $where .= ' AND c.status = ?';
                $params[] = $filters['status'];
            }
        }
        if (!empty($filters['case_type'])) {
            $where .= ' AND c.case_type = ?';
            $params[] = $filters['case_type'];
        }
        if (!empty($filters['sub_status'])) {
            $where .= ' AND c.sub_status = ?';
            $params[] = $filters['sub_status'];
        }
        if (!empty($filters['keyword'])) {
            $kwRaw = $filters['keyword'];
            $kwClean = str_replace(array('-', ' '), '', $kwRaw);
            // $開頭 = 搜尋帳款金額
            if (preg_match('/^\$(\d[\d,]*)$/', $kwRaw, $m)) {
                $amt = (int)str_replace(',', '', $m[1]);
                $where .= ' AND c.id IN (SELECT case_id FROM case_payments WHERE amount = ?)';
                $params[] = $amt;
            } else {
                $where .= ' AND (c.title LIKE ? OR c.case_number LIKE ? OR c.address LIKE ? OR c.customer_name LIKE ? OR u.real_name LIKE ? OR REPLACE(REPLACE(c.customer_phone,"-","")," ","") LIKE ? OR REPLACE(REPLACE(c.customer_mobile,"-","")," ","") LIKE ? OR c.id IN (SELECT case_id FROM case_payments WHERE note LIKE ?))';
                $kw = '%' . $kwRaw . '%';
                $kwPhone = '%' . $kwClean . '%';
                $params[] = $kw;
                $params[] = $kw;
                $params[] = $kw;
                $params[] = $kw;
                $params[] = $kw;
                $params[] = $kwPhone;
                $params[] = $kwPhone;
                $params[] = $kw;
            }
        }
        if (!empty($filters['branch_id'])) {
            $where .= ' AND c.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['sales_id'])) {
            $sids = is_array($filters['sales_id']) ? $filters['sales_id'] : explode(',', $filters['sales_id']);
            $sids = array_filter(array_map('intval', $sids));
            if ($sids) {
                $sph = implode(',', array_fill(0, count($sids), '?'));
                $where .= " AND c.sales_id IN ($sph)";
                $params = array_merge($params, $sids);
            }
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND c.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND c.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // 計算總數與金額合計
        // 尾款即時算：(含稅金額 or 成交金額) - total_collected，與 updateTotalCollected() 一致
        $countStmt = $this->db->prepare("SELECT COUNT(*), COALESCE(SUM(GREATEST(COALESCE(CASE WHEN c.total_amount > 0 THEN c.total_amount ELSE c.deal_amount END, 0) - COALESCE(c.total_collected,0), 0)),0), COALESCE(SUM(c.deal_amount),0) FROM cases c LEFT JOIN users u ON c.sales_id = u.id WHERE $where");
        $countStmt->execute($params);
        $countRow = $countStmt->fetch(PDO::FETCH_NUM);
        $total = (int)$countRow[0];
        $totalBalance = (float)$countRow[1];
        $totalDeal = (float)$countRow[2];

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT c.*, b.name AS branch_name,
                   u.real_name AS sales_name,
                   cr.has_quotation, cr.has_site_photos, cr.has_amount_confirmed, cr.has_site_info,
                   cust.is_blacklisted, cust.blacklist_reason,
                   EXISTS(SELECT 1 FROM cases c2 WHERE c2.customer_id = c.customer_id AND ( c2.sub_status IN ('已成交','跨月成交','現簽','電話報價成交') OR c2.case_type IN ('old_repair','addition') )) as customer_has_deal
            FROM cases c
            JOIN branches b ON c.branch_id = b.id
            LEFT JOIN users u ON c.sales_id = u.id
            LEFT JOIN case_readiness cr ON cr.case_id = c.id
            LEFT JOIN customers cust ON c.customer_id = cust.id
            WHERE $where
            ORDER BY COALESCE(c.updated_at, c.created_at) DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);

        return [
            'data'         => $stmt->fetchAll(),
            'total'        => $total,
            'totalBalance' => $totalBalance,
            'totalDeal'    => $totalDeal,
            'page'         => $page,
            'perPage'      => $perPage,
            'lastPage'     => (int)ceil($total / $perPage),
        ];
    }

    /**
     * 取得單一案件完整資料
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT c.*, b.name AS branch_name, b.code AS branch_code,
                   u.real_name AS sales_name,
                   cu.customer_no AS linked_customer_no
            FROM cases c
            JOIN branches b ON c.branch_id = b.id
            LEFT JOIN users u ON c.sales_id = u.id
            LEFT JOIN customers cu ON c.customer_id = cu.id
            WHERE c.id = ?
        ');
        $stmt->execute([$id]);
        $case = $stmt->fetch();
        if (!$case) return null;

        // 排工條件
        $stmt = $this->db->prepare('SELECT * FROM case_readiness WHERE case_id = ?');
        $stmt->execute([$id]);
        $case['readiness'] = $stmt->fetch() ?: [];

        // 聯絡人
        $stmt = $this->db->prepare('SELECT * FROM case_contacts WHERE case_id = ? ORDER BY id');
        $stmt->execute([$id]);
        $case['contacts'] = $stmt->fetchAll();

        // 現場環境
        $stmt = $this->db->prepare('SELECT * FROM case_site_conditions WHERE case_id = ?');
        $stmt->execute([$id]);
        $case['site_conditions'] = $stmt->fetch() ?: [];

        // 所需技能
        $stmt = $this->db->prepare('
            SELECT crs.*, s.name AS skill_name, s.category
            FROM case_required_skills crs
            JOIN skills s ON crs.skill_id = s.id
            WHERE crs.case_id = ?
        ');
        $stmt->execute([$id]);
        $case['required_skills'] = $stmt->fetchAll();

        // 附件
        $stmt = $this->db->prepare('
            SELECT ca.*, u.real_name AS uploader_name
            FROM case_attachments ca
            LEFT JOIN users u ON ca.uploaded_by = u.id
            WHERE ca.case_id = ?
            ORDER BY ca.created_at DESC
        ');
        $stmt->execute([$id]);
        $case['attachments'] = $stmt->fetchAll();

        // 收款
        try {
            $stmt = $this->db->prepare('SELECT * FROM payments WHERE case_id = ? ORDER BY created_at');
            $stmt->execute([$id]);
            $case['payments'] = $stmt->fetchAll();
        } catch (Exception $e) {
            $case['payments'] = array();
        }

        // 帳款交易紀錄
        try {
            $stmt = $this->db->prepare('SELECT cp.*, u.real_name as creator_name FROM case_payments cp LEFT JOIN users u ON cp.created_by = u.id WHERE cp.case_id = ? ORDER BY cp.payment_date DESC');
            $stmt->execute([$id]);
            $case['case_payments'] = $stmt->fetchAll();
        } catch (Exception $e) {
            $case['case_payments'] = array();
        }

        // 請款流程
        try {
            $stmt = $this->db->prepare('SELECT * FROM case_billing_items WHERE case_id = ? ORDER BY seq_no');
            $stmt->execute([$id]);
            $case['billing_items'] = $stmt->fetchAll();
        } catch (Exception $e) {
            $case['billing_items'] = array();
        }

        // 金額異動紀錄（表可能尚未建立）
        $case['amount_changes'] = array();
        try {
            $chkTbl = $this->db->query("SHOW TABLES LIKE 'case_amount_changes'");
            if ($chkTbl && $chkTbl->rowCount() > 0) {
                $stmt = $this->db->prepare('SELECT * FROM case_amount_changes WHERE case_id = ? ORDER BY created_at DESC LIMIT 50');
                $stmt->execute(array($id));
                $case['amount_changes'] = $stmt->fetchAll();
            }
        } catch (Exception $e) {}

        // 該案件已開立的銷項發票
        try {
            $stmt = $this->db->prepare("SELECT id, invoice_number, invoice_date, total_amount, status FROM sales_invoices WHERE reference_type = 'case' AND reference_id = ? ORDER BY invoice_date DESC, id DESC");
            $stmt->execute([$id]);
            $case['sales_invoices'] = $stmt->fetchAll();
        } catch (Exception $e) {
            $case['sales_invoices'] = array();
        }

        // 無訂金排工簽核狀態（最新一筆）
        try {
            $stmt = $this->db->prepare("SELECT status FROM approval_flows WHERE module = 'no_deposit_schedule' AND target_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$id]);
            $case['no_deposit_approval_status'] = $stmt->fetchColumn() ?: null;
        } catch (Exception $e) {
            $case['no_deposit_approval_status'] = null;
        }

        // 施工回報紀錄（手動/Ragic匯入）
        try {
            $stmt = $this->db->prepare('SELECT cwl.*, u.real_name as creator_name FROM case_work_logs cwl LEFT JOIN users u ON cwl.created_by = u.id WHERE cwl.case_id = ? ORDER BY cwl.work_date DESC');
            $stmt->execute([$id]);
            $case['case_work_logs'] = $stmt->fetchAll();
        } catch (Exception $e) {
            $case['case_work_logs'] = array();
        }

        // 支援分公司
        try {
            $stmt = $this->db->prepare('SELECT cbs.branch_id, b.name AS branch_name FROM case_branch_support cbs JOIN branches b ON cbs.branch_id = b.id WHERE cbs.case_id = ? ORDER BY b.id');
            $stmt->execute([$id]);
            $case['support_branches'] = $stmt->fetchAll();
        } catch (Exception $e) {
            $case['support_branches'] = array();
        }

        // 預計使用線材與配件
        try {
            $stmt = $this->db->prepare('
                SELECT cme.*, p.name AS product_display_name, p.model AS product_model
                FROM case_material_estimates cme
                LEFT JOIN products p ON cme.product_id = p.id
                WHERE cme.case_id = ?
                ORDER BY cme.sort_order, cme.id
            ');
            $stmt->execute([$id]);
            $case['material_estimates'] = $stmt->fetchAll();
        } catch (Exception $e) {
            $case['material_estimates'] = array();
        }

        return $case;
    }

    /**
     * 新增案件
     */
    public function create(array $data): int
    {
        $caseNumber = generate_doc_number('cases');

        // 有指派業務時，狀態自動設為待聯絡
        if (!empty($data['sales_id']) && (empty($data['sub_status']) || $data['sub_status'] === '未指派')) {
            $data['sub_status'] = '待聯絡';
        }

        $stmt = $this->db->prepare('
            INSERT INTO cases (branch_id, case_number, title, case_type, status, sub_status, difficulty,
                             estimated_hours, total_visits, max_engineers, address, description,
                             system_type, quote_amount, notes,
                             deal_date, deal_amount, is_tax_included, tax_amount, total_amount,
                             deposit_amount, deposit_payment_date, deposit_method,
                             balance_amount, completion_amount, total_collected,
                             ragic_id, sales_id, created_by,
                             planned_start_date, planned_end_date, planned_start_time, is_flexible,
                             work_time_start, work_time_end, has_time_restriction,
                             customer_break_time, allow_night_work, urgency, is_large_project,
                             customer_id, customer_name, customer_category, customer_phone, customer_mobile, contact_person, contact_line_id, customer_email,
                             is_completed, completion_date,
                             contact_address, construction_area, construction_note, company, case_source,
                             survey_date, survey_time, visit_method,
                             settlement_confirmed, settlement_date,
                             billing_title, billing_tax_id, billing_contact, billing_phone, billing_mobile, billing_address, billing_email, billing_note,
                             registrar, updated_by,
                             repair_report_date, repair_fault_reason, repair_by_sales, repair_equipment, repair_staff, repair_helper,
                             repair_result, repair_description, repair_original_case, repair_original_complete_date, repair_original_warranty_date,
                             repair_is_charged, repair_no_charge_reason, sales_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['branch_id'],
            $caseNumber,
            $data['title'],
            $data['case_type'] ?? 'new_install',
            $data['status'] ?? 'tracking',
            $data['sub_status'] ?: null,
            $data['difficulty'] ?? 3,
            $data['estimated_hours'] ?: null,
            $data['total_visits'] ?? 1,
            $data['max_engineers'] ?? 4,
            $data['address'] ?? null,
            $data['description'] ?? null,
            $data['system_type'] ?: null,
            $data['quote_amount'] ?: null,
            $data['notes'] ?: null,
            !empty($data['deal_date']) ? $data['deal_date'] : null,
            $data['deal_amount'] ?: null,
            $data['is_tax_included'] ?: null,
            $data['tax_amount'] ? str_replace(',', '', $data['tax_amount']) : null,
            $data['total_amount'] ? str_replace(',', '', $data['total_amount']) : null,
            $data['deposit_amount'] ?: null,
            !empty($data['deposit_payment_date']) ? $data['deposit_payment_date'] : null,
            $data['deposit_method'] ?: null,
            $data['balance_amount'] ? str_replace(',', '', $data['balance_amount']) : null,
            $data['completion_amount'] ?: null,
            $data['total_collected'] ?: null,
            $data['ragic_id'] ?: null,
            $data['sales_id'] ?: null,
            Auth::id(),
            !empty($data['planned_start_date']) ? $data['planned_start_date'] : null,
            !empty($data['planned_end_date']) ? $data['planned_end_date'] : null,
            !empty($data['planned_start_time']) ? $data['planned_start_time'] : null,
            $data['is_flexible'] ?? 0,
            !empty($data['work_time_start']) ? $data['work_time_start'] : null,
            !empty($data['work_time_end']) ? $data['work_time_end'] : null,
            $data['has_time_restriction'] ?? 0,
            $data['customer_break_time'] ?? null,
            $data['allow_night_work'] ?? 0,
            $data['urgency'] ?? 3,
            $data['is_large_project'] ?? 0,
            !empty($data['customer_id']) ? $data['customer_id'] : null,
            !empty($data['customer_name']) ? $data['customer_name'] : null,
            !empty($data['customer_category']) ? $data['customer_category'] : null,
            !empty($data['customer_phone']) ? $data['customer_phone'] : null,
            !empty($data['customer_mobile']) ? $data['customer_mobile'] : null,
            !empty($data['contact_person']) ? $data['contact_person'] : null,
            !empty($data['contact_line_id']) ? $data['contact_line_id'] : null,
            !empty($data['customer_email']) ? $data['customer_email'] : null,
            isset($data['is_completed']) ? (int)$data['is_completed'] : 0,
            !empty($data['completion_date']) ? $data['completion_date'] : null,
            !empty($data['contact_address']) ? $data['contact_address'] : null,
            !empty($data['construction_area']) ? $data['construction_area'] : null,
            !empty($data['construction_note']) ? $data['construction_note'] : null,
            !empty($data['company']) ? $data['company'] : null,
            !empty($data['case_source']) ? $data['case_source'] : null,
            !empty($data['survey_date']) ? $data['survey_date'] : null,
            !empty($data['survey_time']) ? $data['survey_time'] : null,
            !empty($data['visit_method']) ? $data['visit_method'] : null,
            isset($data['settlement_confirmed']) && $data['settlement_confirmed'] !== '' ? (int)$data['settlement_confirmed'] : null,
            !empty($data['settlement_date']) ? $data['settlement_date'] : null,
            !empty($data['billing_title']) ? $data['billing_title'] : null,
            !empty($data['billing_tax_id']) ? $data['billing_tax_id'] : null,
            !empty($data['billing_contact']) ? $data['billing_contact'] : null,
            !empty($data['billing_phone']) ? $data['billing_phone'] : null,
            !empty($data['billing_mobile']) ? $data['billing_mobile'] : null,
            !empty($data['billing_address']) ? $data['billing_address'] : null,
            !empty($data['billing_email']) ? $data['billing_email'] : null,
            !empty($data['billing_note']) ? $data['billing_note'] : null,
            !empty($data['registrar']) ? $data['registrar'] : null,
            Auth::id(),
            !empty($data['repair_report_date']) ? $data['repair_report_date'] : null,
            !empty($data['repair_fault_reason']) ? $data['repair_fault_reason'] : null,
            isset($data['repair_by_sales']) && $data['repair_by_sales'] !== '' ? $data['repair_by_sales'] : null,
            !empty($data['repair_equipment']) ? $data['repair_equipment'] : null,
            !empty($data['repair_staff']) ? $data['repair_staff'] : null,
            !empty($data['repair_helper']) ? $data['repair_helper'] : null,
            !empty($data['repair_result']) ? $data['repair_result'] : null,
            !empty($data['repair_description']) ? $data['repair_description'] : null,
            !empty($data['repair_original_case']) ? $data['repair_original_case'] : null,
            !empty($data['repair_original_complete_date']) ? $data['repair_original_complete_date'] : null,
            !empty($data['repair_original_warranty_date']) ? $data['repair_original_warranty_date'] : null,
            !empty($data['repair_is_charged']) ? $data['repair_is_charged'] : null,
            !empty($data['repair_no_charge_reason']) ? $data['repair_no_charge_reason'] : null,
            !empty($data['sales_note']) ? $data['sales_note'] : null,
        ]);

        $caseId = (int)$this->db->lastInsertId();

        // 建立排工條件
        $this->db->prepare('INSERT INTO case_readiness (case_id) VALUES (?)')->execute([$caseId]);

        // 建立現場環境
        $this->db->prepare('INSERT INTO case_site_conditions (case_id) VALUES (?)')->execute([$caseId]);

        return $caseId;
    }

    /**
     * 更新案件
     */
    public function update(int $id, array $data): void
    {
        // 完工狀態保護：closed / completed_pending / unpaid 只能透過簽核流程變更
        $protectedStatuses = array('closed', 'completed_pending', 'unpaid');
        $newStatus = isset($data['status']) ? $data['status'] : '';
        if (in_array($newStatus, $protectedStatuses) && empty($data['_from_approval'])) {
            $cur = $this->db->prepare("SELECT status FROM cases WHERE id = ?");
            $cur->execute(array($id));
            $curStatus = $cur->fetchColumn();
            // 只有目前不是該狀態時才阻擋（已經是的就不擋）
            if ($curStatus !== $newStatus) {
                $user = Session::getUser();
                if (!$user || $user['role'] !== 'boss') {
                    $labels = array(
                        'closed' => '已完工結案',
                        'completed_pending' => '已完工待簽核',
                        'unpaid' => '完工未收款',
                    );
                    $label = isset($labels[$newStatus]) ? $labels[$newStatus] : $newStatus;
                    throw new \RuntimeException('「' . $label . '」需透過完工簽核流程變更，無法手動修改');
                }
            }
        }

        // 指派業務時自動調整狀態：僅在 sub_status 為「未指派」時才改為「待聯絡」
        if (!empty($data['sales_id'])) {
            $chk = $this->db->prepare("SELECT sales_id, sub_status FROM cases WHERE id = ?");
            $chk->execute(array($id));
            $old = $chk->fetch(PDO::FETCH_ASSOC);
            // 判斷目前實際的 sub_status（POST 資料優先，否則用 DB 值）
            $currentSubStatus = isset($data['sub_status']) && $data['sub_status'] !== ''
                ? $data['sub_status']
                : ($old ? $old['sub_status'] : '');
            // 只在「未指派」時自動改為「待聯絡」
            if ($currentSubStatus === '未指派') {
                $data['sub_status'] = '待聯絡';
                AuditLog::log('cases', 'auto_status', $id, '指派業務自動變更：未指派 → 待聯絡');
            }
        }

        $stmt = $this->db->prepare('
            UPDATE cases SET
                title = ?, case_type = ?, status = ?, sub_status = ?, difficulty = ?,
                estimated_hours = ?, total_visits = ?, max_engineers = ?,
                address = ?, description = ?,
                system_type = ?, quote_amount = ?, notes = ?,
                deal_date = ?, deal_amount = ?, is_tax_included = ?, tax_amount = ?, total_amount = ?,
                deposit_amount = ?, deposit_payment_date = ?, deposit_method = ?,
                balance_amount = ?, completion_amount = ?, total_collected = ?,
                ragic_id = ?, sales_id = ?,
                planned_start_date = ?, planned_end_date = ?, planned_start_time = ?, is_flexible = ?,
                work_time_start = ?, work_time_end = ?, has_time_restriction = ?,
                customer_break_time = ?, allow_night_work = ?, urgency = ?, is_large_project = ?,
                branch_id = ?,
                customer_id = ?, customer_name = ?, customer_category = ?, customer_phone = ?, customer_mobile = ?, contact_person = ?, contact_line_id = ?, customer_email = ?, construction_note = ?,
                contact_address = ?, construction_area = ?, company = ?, case_source = ?, is_completed = ?, completion_date = ?, survey_date = ?, survey_time = ?, visit_method = ?,
                settlement_confirmed = ?, settlement_date = ?,
                billing_title = ?, billing_tax_id = ?, billing_contact = ?,
                billing_phone = ?, billing_mobile = ?, billing_address = ?, billing_email = ?,
                billing_note = ?, updated_by = ?,
                repair_report_date = ?, repair_fault_reason = ?, repair_by_sales = ?, repair_equipment = ?,
                repair_staff = ?, repair_helper = ?, repair_result = ?, repair_description = ?,
                repair_original_case = ?, repair_original_complete_date = ?, repair_original_warranty_date = ?,
                repair_is_charged = ?, repair_no_charge_reason = ?,
                sales_note = ?
            WHERE id = ?
        ');
        $stmt->execute([
            isset($data['title']) ? $data['title'] : '',
            isset($data['case_type']) && $data['case_type'] !== '' ? $data['case_type'] : 'new_install',
            isset($data['status']) && $data['status'] !== '' ? $data['status'] : 'tracking',
            !empty($data['sub_status']) ? $data['sub_status'] : null,
            isset($data['difficulty']) ? $data['difficulty'] : 3,
            !empty($data['estimated_hours']) ? $data['estimated_hours'] : null,
            isset($data['total_visits']) && $data['total_visits'] !== '' ? $data['total_visits'] : 1,
            isset($data['max_engineers']) && $data['max_engineers'] !== '' ? $data['max_engineers'] : 4,
            !empty($data['address']) ? $data['address'] : null,
            !empty($data['description']) ? $data['description'] : null,
            !empty($data['system_type']) ? $data['system_type'] : null,
            !empty($data['quote_amount']) ? $data['quote_amount'] : null,
            !empty($data['notes']) ? $data['notes'] : null,
            !empty($data['deal_date']) ? $data['deal_date'] : null,
            !empty($data['deal_amount']) ? $data['deal_amount'] : null,
            !empty($data['is_tax_included']) ? $data['is_tax_included'] : null,
            isset($data['tax_amount']) && $data['tax_amount'] !== '' ? str_replace(',', '', $data['tax_amount']) : null,
            isset($data['total_amount']) && $data['total_amount'] !== '' ? str_replace(',', '', $data['total_amount']) : null,
            !empty($data['deposit_amount']) ? $data['deposit_amount'] : null,
            !empty($data['deposit_payment_date']) ? $data['deposit_payment_date'] : null,
            !empty($data['deposit_method']) ? $data['deposit_method'] : null,
            isset($data['balance_amount']) && $data['balance_amount'] !== '' ? str_replace(',', '', $data['balance_amount']) : null,
            !empty($data['completion_amount']) ? $data['completion_amount'] : null,
            !empty($data['total_collected']) ? $data['total_collected'] : null,
            !empty($data['ragic_id']) ? $data['ragic_id'] : null,
            !empty($data['sales_id']) ? $data['sales_id'] : null,
            !empty($data['planned_start_date']) ? $data['planned_start_date'] : null,
            !empty($data['planned_end_date']) ? $data['planned_end_date'] : null,
            !empty($data['planned_start_time']) ? $data['planned_start_time'] : null,
            isset($data['is_flexible']) ? $data['is_flexible'] : 0,
            !empty($data['work_time_start']) ? $data['work_time_start'] : null,
            !empty($data['work_time_end']) ? $data['work_time_end'] : null,
            isset($data['has_time_restriction']) ? $data['has_time_restriction'] : 0,
            isset($data['customer_break_time']) ? $data['customer_break_time'] : null,
            isset($data['allow_night_work']) ? $data['allow_night_work'] : 0,
            isset($data['urgency']) ? $data['urgency'] : 3,
            isset($data['is_large_project']) ? $data['is_large_project'] : 0,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['customer_id']) ? $data['customer_id'] : null,
            !empty($data['customer_name']) ? $data['customer_name'] : null,
            !empty($data['customer_category']) ? $data['customer_category'] : null,
            !empty($data['customer_phone']) ? $data['customer_phone'] : null,
            !empty($data['customer_mobile']) ? $data['customer_mobile'] : null,
            !empty($data['contact_person']) ? $data['contact_person'] : null,
            !empty($data['contact_line_id']) ? $data['contact_line_id'] : null,
            !empty($data['customer_email']) ? $data['customer_email'] : null,
            !empty($data['construction_note']) ? $data['construction_note'] : null,
            !empty($data['contact_address']) ? $data['contact_address'] : null,
            !empty($data['construction_area']) ? $data['construction_area'] : null,
            !empty($data['company']) ? $data['company'] : null,
            !empty($data['case_source']) ? $data['case_source'] : null,
            isset($data['is_completed']) ? (int)$data['is_completed'] : 0,
            !empty($data['completion_date']) ? $data['completion_date'] : null,
            !empty($data['survey_date']) ? $data['survey_date'] : null,
            !empty($data['survey_time']) ? $data['survey_time'] : null,
            !empty($data['visit_method']) ? $data['visit_method'] : null,
            isset($data['settlement_confirmed']) && $data['settlement_confirmed'] !== '' ? (int)$data['settlement_confirmed'] : null,
            !empty($data['settlement_date']) ? $data['settlement_date'] : null,
            !empty($data['billing_title']) ? $data['billing_title'] : null,
            !empty($data['billing_tax_id']) ? $data['billing_tax_id'] : null,
            !empty($data['billing_contact']) ? $data['billing_contact'] : null,
            !empty($data['billing_phone']) ? $data['billing_phone'] : null,
            !empty($data['billing_mobile']) ? $data['billing_mobile'] : null,
            !empty($data['billing_address']) ? $data['billing_address'] : null,
            !empty($data['billing_email']) ? $data['billing_email'] : null,
            !empty($data['billing_note']) ? $data['billing_note'] : null,
            !empty($data['updated_by']) ? $data['updated_by'] : Auth::id(),
            !empty($data['repair_report_date']) ? $data['repair_report_date'] : null,
            !empty($data['repair_fault_reason']) ? $data['repair_fault_reason'] : null,
            isset($data['repair_by_sales']) && $data['repair_by_sales'] !== '' ? $data['repair_by_sales'] : null,
            !empty($data['repair_equipment']) ? $data['repair_equipment'] : null,
            !empty($data['repair_staff']) ? $data['repair_staff'] : null,
            !empty($data['repair_helper']) ? $data['repair_helper'] : null,
            !empty($data['repair_result']) ? $data['repair_result'] : null,
            !empty($data['repair_description']) ? $data['repair_description'] : null,
            !empty($data['repair_original_case']) ? $data['repair_original_case'] : null,
            !empty($data['repair_original_complete_date']) ? $data['repair_original_complete_date'] : null,
            !empty($data['repair_original_warranty_date']) ? $data['repair_original_warranty_date'] : null,
            !empty($data['repair_is_charged']) ? $data['repair_is_charged'] : null,
            !empty($data['repair_no_charge_reason']) ? $data['repair_no_charge_reason'] : null,
            !empty($data['sales_note']) ? $data['sales_note'] : null,
            $id,
        ]);

        // 更新系統自動判斷難易度
        $this->updateSystemDifficulty($id);

        // 同步更新 stage
        $this->syncStage($id);

        // 業務備註同步到業務行事曆
        if (array_key_exists('sales_note', $data)) {
            $this->db->prepare("UPDATE business_calendar SET note = ? WHERE case_id = ? AND note IS NOT NULL")
                ->execute(array(!empty($data['sales_note']) ? $data['sales_note'] : null, $id));
        }

        // 場勘日期同步到業務行事曆
        if (!empty($data['survey_date'])) {
            $this->syncSurveyToCalendar($id, $data);
        }
    }

    /**
     * 場勘日期同步到業務行事曆
     */
    private function syncSurveyToCalendar($caseId, $data)
    {
        // 檢查是否已有此案件的場勘事件
        $stmt = $this->db->prepare('SELECT id, event_date FROM business_calendar WHERE case_id = ? AND activity_type = ?');
        $stmt->execute(array($caseId, 'survey'));
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $surveyDate = $data['survey_date'];
        $staffId = !empty($data['sales_id']) ? $data['sales_id'] : (Auth::id() ?: 1);

        // 取案件完整資料
        $caseStmt = $this->db->prepare('SELECT case_number, title, customer_name, customer_phone, customer_mobile, address, visit_method FROM cases WHERE id = ?');
        $caseStmt->execute(array($caseId));
        $c = $caseStmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) return;

        $phone = $c['customer_phone'] ?: $c['customer_mobile'];
        $note = $c['case_number'] . ' ' . $c['title'];
        if (!empty($c['visit_method'])) {
            $note .= ' (拜訪方式: ' . $c['visit_method'] . ')';
        }

        $surveyTime = !empty($data['survey_time']) ? $data['survey_time'] : null;

        if ($existing) {
            // 更新既有事件
            $upd = $this->db->prepare('UPDATE business_calendar SET event_date = ?, staff_id = ?, customer_name = ?, phone = ?, address = ?, note = ?, start_time = ? WHERE id = ?');
            $upd->execute(array($surveyDate, $staffId, $c['customer_name'], $phone, $c['address'], $note, $surveyTime, $existing['id']));
        } else {
            // 新建事件
            $ins = $this->db->prepare("INSERT INTO business_calendar (event_date, staff_id, case_id, customer_name, activity_type, phone, address, note, start_time, status, created_by, created_at) VALUES (?, ?, ?, ?, 'survey', ?, ?, ?, ?, 'planned', ?, NOW())");
            $ins->execute(array($surveyDate, $staffId, $caseId, $c['customer_name'], $phone, $c['address'], $note, $surveyTime, Auth::id() ?: 1));
        }
    }

    /**
     * 更新排工條件
     */
    public function updateReadiness(int $caseId, array $data): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO case_readiness (case_id, has_quotation, has_site_photos, has_amount_confirmed, has_site_info, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                has_quotation = VALUES(has_quotation),
                has_site_photos = VALUES(has_site_photos),
                has_amount_confirmed = VALUES(has_amount_confirmed),
                has_site_info = VALUES(has_site_info),
                notes = VALUES(notes)
        ');
        $stmt->execute([
            $caseId,
            $data['has_quotation'] ?? 0,
            $data['has_site_photos'] ?? 0,
            $data['has_amount_confirmed'] ?? 0,
            $data['has_site_info'] ?? 0,
            $data['readiness_notes'] ?? null,
        ]);
    }

    /**
     * 更新現場環境
     */
    public function updateSiteConditions(int $caseId, array $data): void
    {
        $structureType = !empty($data['structure_type']) ? (is_array($data['structure_type']) ? implode(',', $data['structure_type']) : $data['structure_type']) : null;
        $conduitType = !empty($data['conduit_type']) ? (is_array($data['conduit_type']) ? implode(',', $data['conduit_type']) : $data['conduit_type']) : null;
        $safetyEquipment = !empty($data['safety_equipment']) ? (is_array($data['safety_equipment']) ? implode(',', $data['safety_equipment']) : $data['safety_equipment']) : null;

        $stmt = $this->db->prepare('
            INSERT INTO case_site_conditions (case_id, structure_type, conduit_type, floor_count, has_elevator, has_ladder_needed, ladder_size, high_ceiling_height, needs_scissor_lift, scissor_lift_height, safety_equipment, special_requirements)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                structure_type = VALUES(structure_type),
                conduit_type = VALUES(conduit_type),
                floor_count = VALUES(floor_count),
                has_elevator = VALUES(has_elevator),
                has_ladder_needed = VALUES(has_ladder_needed),
                ladder_size = VALUES(ladder_size),
                high_ceiling_height = VALUES(high_ceiling_height),
                needs_scissor_lift = VALUES(needs_scissor_lift),
                scissor_lift_height = VALUES(scissor_lift_height),
                safety_equipment = VALUES(safety_equipment),
                special_requirements = VALUES(special_requirements)
        ');
        $ladderSize = !empty($data['has_ladder_needed']) ? ($data['ladder_size'] ?? null) : null;
        $highCeiling = !empty($data['high_ceiling_height']) ? $data['high_ceiling_height'] : null;
        $scissorLiftHeight = !empty($data['needs_scissor_lift']) ? ($data['scissor_lift_height'] ?? null) : null;
        $stmt->execute([
            $caseId,
            $structureType,
            $conduitType,
            $data['floor_count'] ?: null,
            $data['has_elevator'] ?? 0,
            $data['has_ladder_needed'] ?? 0,
            $ladderSize ?: null,
            $highCeiling,
            $data['needs_scissor_lift'] ?? 0,
            $scissorLiftHeight ?: null,
            $safetyEquipment,
            $data['special_requirements'] ?? null,
        ]);
    }

    /**
     * 儲存案件聯絡人 (先清除再新增)
     */
    public function saveContacts(int $caseId, array $contacts): void
    {
        $this->db->prepare('DELETE FROM case_contacts WHERE case_id = ?')->execute([$caseId]);
        $stmt = $this->db->prepare('
            INSERT INTO case_contacts (case_id, contact_name, contact_phone, contact_role, note)
            VALUES (?, ?, ?, ?, ?)
        ');
        foreach ($contacts as $c) {
            if (empty($c['contact_name'])) continue;
            $stmt->execute([
                $caseId,
                $c['contact_name'],
                $c['contact_phone'] ?? null,
                $c['contact_role'] ?? null,
                $c['contact_note'] ?? null,
            ]);
        }
    }

    /**
     * 儲存案件所需技能
     */
    public function saveRequiredSkills(int $caseId, array $skills): void
    {
        $this->db->prepare('DELETE FROM case_required_skills WHERE case_id = ?')->execute([$caseId]);
        $stmt = $this->db->prepare('
            INSERT INTO case_required_skills (case_id, skill_id, min_proficiency)
            VALUES (?, ?, ?)
        ');
        foreach ($skills as $skillId => $proficiency) {
            if ($proficiency < 1) continue;
            $stmt->execute([$caseId, $skillId, $proficiency]);
        }
    }

    /**
     * 取得所有技能清單
     */
    public function getAllSkills(): array
    {
        return $this->db->query('SELECT * FROM skills WHERE is_active = 1 ORDER BY category, name')->fetchAll();
    }

    /**
     * 儲存案件預估材料（delete-then-insert）
     */
    public function saveMaterialEstimates($caseId, array $estimates)
    {
        $this->db->prepare('DELETE FROM case_material_estimates WHERE case_id = ?')
                 ->execute(array($caseId));

        if (empty($estimates)) return;

        $stmt = $this->db->prepare('
            INSERT INTO case_material_estimates
            (case_id, product_id, material_name, model_number, unit, estimated_qty, note, sort_order, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $sortOrder = 0;
        foreach ($estimates as $est) {
            $name = isset($est['material_name']) ? trim($est['material_name']) : '';
            if ($name === '') continue;
            $stmt->execute(array(
                $caseId,
                !empty($est['product_id']) ? (int)$est['product_id'] : null,
                $name,
                isset($est['model_number']) ? trim($est['model_number']) : null,
                isset($est['unit']) ? trim($est['unit']) : null,
                isset($est['estimated_qty']) ? (float)$est['estimated_qty'] : 0,
                isset($est['note']) ? trim($est['note']) : null,
                $sortOrder++,
                Auth::id()
            ));
        }
    }

    /**
     * 取得案件預估材料
     */
    public function getMaterialEstimates($caseId)
    {
        $stmt = $this->db->prepare('
            SELECT cme.*, p.name AS product_display_name
            FROM case_material_estimates cme
            LEFT JOIN products p ON cme.product_id = p.id
            WHERE cme.case_id = ?
            ORDER BY cme.sort_order, cme.id
        ');
        $stmt->execute(array($caseId));
        return $stmt->fetchAll();
    }

    /**
     * 取得所有據點
     */
    public function getAllBranches(): array
    {
        return $this->db->query('SELECT * FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll();
    }

    /**
     * 取得案件的支援分公司
     */
    public function getSupportBranches($caseId)
    {
        $stmt = $this->db->prepare('
            SELECT cbs.*, b.name AS branch_name, u.real_name AS requested_by_name
            FROM case_branch_support cbs
            JOIN branches b ON cbs.branch_id = b.id
            LEFT JOIN users u ON cbs.requested_by = u.id
            WHERE cbs.case_id = ?
            ORDER BY cbs.created_at
        ');
        $stmt->execute(array($caseId));
        return $stmt->fetchAll();
    }

    /**
     * 儲存案件的支援分公司（全量替換）
     */
    public function saveSupportBranches($caseId, array $branchIds, $requestedBy)
    {
        $this->db->beginTransaction();
        try {
            $oldStmt = $this->db->prepare('SELECT branch_id FROM case_branch_support WHERE case_id = ?');
            $oldStmt->execute(array($caseId));
            $oldIds = array_column($oldStmt->fetchAll(), 'branch_id');

            $this->db->prepare('DELETE FROM case_branch_support WHERE case_id = ?')->execute(array($caseId));

            if (!empty($branchIds)) {
                $stmt = $this->db->prepare('INSERT INTO case_branch_support (case_id, branch_id, requested_by) VALUES (?, ?, ?)');
                foreach ($branchIds as $bid) {
                    $stmt->execute(array($caseId, (int)$bid, $requestedBy));
                }
            }

            $this->db->commit();

            AuditLog::log('cases', 'update_support_branches', $caseId,
                '支援分公司變更',
                json_encode(array('old' => $oldIds, 'new' => $branchIds), JSON_UNESCAPED_UNICODE)
            );
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 取得業務人員清單（含離職）
     */
    public function getSalesUsers(array $branchIds): array
    {
        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT id, real_name, branch_id, is_active FROM users
            WHERE branch_id IN ($placeholders)
              AND (role IN ('sales','sales_manager','sales_assistant','boss','vice_president','manager') OR is_sales = 1)
              AND employee_id IS NOT NULL AND employee_id != ''
              AND is_active = 1
            ORDER BY real_name
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    /**
     * 系統自動判斷難易度
     */
    public function updateSystemDifficulty($caseId)
    {
        $case = $this->db->prepare('SELECT * FROM cases WHERE id = ?');
        $case->execute(array($caseId));
        $c = $case->fetch();
        if (!$c) return;

        $score = 0;

        // 施工次數 (多次=難)
        $visits = (int)($c['total_visits'] ?: 1);
        if ($visits >= 5) $score += 2;
        elseif ($visits >= 3) $score += 1;

        // 人數需求 (多人=難)
        $maxEng = (int)($c['max_engineers'] ?: 4);
        if ($maxEng >= 6) $score += 2;
        elseif ($maxEng >= 4) $score += 1;

        // 預估工時
        $hours = (float)($c['estimated_hours'] ?: 0);
        if ($hours >= 16) $score += 2;
        elseif ($hours >= 8) $score += 1;

        // 現場環境
        $site = $this->db->prepare('SELECT * FROM case_site_conditions WHERE case_id = ?');
        $site->execute(array($caseId));
        $s = $site->fetch();
        if ($s) {
            if (!empty($s['has_ladder_needed'])) $score += 1;
            if (!empty($s['needs_scissor_lift'])) $score += 1;
            $floors = (int)($s['floor_count'] ?: 0);
            if ($floors >= 5) $score += 1;
        }

        // 時間限制
        if (!empty($c['has_time_restriction'])) $score += 1;

        // 所需技能數
        $skillStmt = $this->db->prepare('SELECT COUNT(*) FROM case_required_skills WHERE case_id = ?');
        $skillStmt->execute(array($caseId));
        $skillCount = (int)$skillStmt->fetchColumn();
        if ($skillCount >= 4) $score += 1;

        // 換算為 1-5
        $difficulty = min(5, max(1, (int)ceil($score / 2)));

        $this->db->prepare('UPDATE cases SET system_difficulty = ? WHERE id = ?')
                 ->execute(array($difficulty, $caseId));
    }

    /**
     * 儲存案件附件
     */
    public function saveAttachment($caseId, $fileType, $fileName, $filePath)
    {
        $stmt = $this->db->prepare('
            INSERT INTO case_attachments (case_id, file_type, file_name, file_path, uploaded_by)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute(array($caseId, $fileType, $fileName, $filePath, Auth::id()));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 刪除案件附件
     */
    public function deleteAttachment($attachmentId)
    {
        $stmt = $this->db->prepare('SELECT file_path FROM case_attachments WHERE id = ?');
        $stmt->execute(array($attachmentId));
        $path = $stmt->fetchColumn();
        if ($path) {
            $fullPath = __DIR__ . '/../../public' . $path;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        $this->db->prepare('DELETE FROM case_attachments WHERE id = ?')->execute(array($attachmentId));
    }

    /**
     * 刪除案件及所有關聯資料
     */
    public function deleteCase($caseId)
    {
        $caseId = (int)$caseId;
        // 刪除附件檔案
        $stmt = $this->db->prepare('SELECT file_path FROM case_attachments WHERE case_id = ?');
        $stmt->execute(array($caseId));
        while ($path = $stmt->fetchColumn()) {
            $fullPath = __DIR__ . '/../../public' . $path;
            if (file_exists($fullPath)) @unlink($fullPath);
        }
        // 刪除關聯表
        $tables = array('case_attachments','case_contacts','case_readiness','case_site_conditions','case_required_skills');
        foreach ($tables as $t) {
            $this->db->prepare("DELETE FROM {$t} WHERE case_id = ?")->execute(array($caseId));
        }
        // 刪除主表
        $this->db->prepare('DELETE FROM cases WHERE id = ?')->execute(array($caseId));
    }

    /**
     * 取得據點 (可選 branchIds 過濾)
     */
    public function getBranches($branchIds = array())
    {
        if (!empty($branchIds)) {
            $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
            $stmt = $this->db->prepare("SELECT * FROM branches WHERE is_active = 1 AND id IN ($placeholders) ORDER BY id");
            $stmt->execute($branchIds);
            return $stmt->fetchAll();
        }
        return $this->db->query('SELECT * FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll();
    }

    /**
     * 業務追蹤列表 (stage 1~4, 8)
     */
    public function getSalesTrackingList($filters = array())
    {
        $where = "c.stage IN (1,2,3,4,8)";
        $params = array();

        if (!empty($filters['sales_id'])) {
            $where .= " AND c.sales_id = ?";
            $params[] = $filters['sales_id'];
        }
        if (!empty($filters['stage'])) {
            $where .= " AND c.stage = ?";
            $params[] = $filters['stage'];
        }
        if (!empty($filters['branch_id'])) {
            $where .= " AND c.branch_id = ?";
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['case_type'])) {
            $where .= " AND c.case_type = ?";
            $params[] = $filters['case_type'];
        }
        if (!empty($filters['case_source'])) {
            $where .= " AND c.case_source = ?";
            $params[] = $filters['case_source'];
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $where .= " AND (c.title LIKE ? OR c.customer_name LIKE ? OR c.address LIKE ? OR c.case_number LIKE ?)";
            $params = array_merge($params, array($kw, $kw, $kw, $kw));
        }
        if (!empty($filters['start_date'])) {
            $where .= " AND c.created_at >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $where .= " AND c.created_at <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        $sql = "SELECT c.*, b.name as branch_name, u.real_name as sales_name
                FROM cases c
                LEFT JOIN branches b ON c.branch_id = b.id
                LEFT JOIN users u ON c.sales_id = u.id
                WHERE {$where}
                ORDER BY GREATEST(COALESCE(c.updated_at, c.created_at), c.created_at) DESC
                LIMIT 500";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 各階段統計
     */
    public function getStageStats($filters = array())
    {
        $where = "stage IN (1,2,3,4,8)";
        $params = array();

        if (!empty($filters['sales_id'])) {
            $where .= " AND sales_id = ?";
            $params[] = $filters['sales_id'];
        }
        if (!empty($filters['branch_id'])) {
            $where .= " AND branch_id = ?";
            $params[] = $filters['branch_id'];
        }

        $sql = "SELECT stage, COUNT(*) as cnt, COALESCE(SUM(deal_amount),0) as total_amount FROM cases WHERE {$where} GROUP BY stage";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats = array(1 => array('count' => 0, 'amount' => 0), 2 => array('count' => 0, 'amount' => 0), 3 => array('count' => 0, 'amount' => 0), 4 => array('count' => 0, 'amount' => 0), 8 => array('count' => 0, 'amount' => 0));
        foreach ($rows as $r) {
            $s = (int)$r['stage'];
            if (isset($stats[$s])) {
                $stats[$s]['count'] = (int)$r['cnt'];
                $stats[$s]['amount'] = (float)$r['total_amount'];
            }
        }
        return $stats;
    }

    /**
     * 工程追蹤 - 案件清單 (stage 4-7)
     */
    public function getEngineeringTrackingList($filters = array())
    {
        $where = "(c.stage BETWEEN 4 AND 7 OR c.status = 'awaiting_dispatch')";
        $params = array();

        if (!empty($filters['engineer_id'])) {
            $where .= " AND EXISTS (SELECT 1 FROM schedules s2 JOIN schedule_engineers se2 ON se2.schedule_id = s2.id WHERE s2.case_id = c.id AND se2.user_id = ?)";
            $params[] = $filters['engineer_id'];
        }
        if (!empty($filters['stage'])) {
            $where .= " AND c.stage = ?";
            $params[] = $filters['stage'];
        }
        if (!empty($filters['branch_id'])) {
            $where .= " AND c.branch_id = ?";
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['case_type'])) {
            $where .= " AND c.case_type = ?";
            $params[] = $filters['case_type'];
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $where .= " AND (c.title LIKE ? OR c.customer_name LIKE ? OR c.address LIKE ? OR c.case_number LIKE ?)";
            $params = array_merge($params, array($kw, $kw, $kw, $kw));
        }

        $sql = "SELECT c.*, b.name as branch_name, u.real_name as sales_name,
                (SELECT COUNT(DISTINCT se3.user_id) FROM schedules s3 JOIN schedule_engineers se3 ON se3.schedule_id = s3.id WHERE s3.case_id = c.id) as engineer_count
                FROM cases c
                LEFT JOIN branches b ON c.branch_id = b.id
                LEFT JOIN users u ON c.sales_id = u.id
                WHERE {$where}
                ORDER BY GREATEST(COALESCE(c.updated_at, c.created_at), c.created_at) DESC
                LIMIT 500";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 工程追蹤 - 各階段統計 (stage 4-7)
     */
    public function getEngineeringStageStats($filters = array())
    {
        $where = "(stage BETWEEN 4 AND 7 OR status = 'awaiting_dispatch')";
        $params = array();

        if (!empty($filters['engineer_id'])) {
            $where .= " AND EXISTS (SELECT 1 FROM schedules s2 JOIN schedule_engineers se2 ON se2.schedule_id = s2.id WHERE s2.case_id = cases.id AND se2.user_id = ?)";
            $params[] = $filters['engineer_id'];
        }
        if (!empty($filters['branch_id'])) {
            $where .= " AND branch_id = ?";
            $params[] = $filters['branch_id'];
        }

        // 先算 stage 4-7
        $sql = "SELECT stage, COUNT(*) as cnt, COALESCE(SUM(deal_amount),0) as total_amount FROM cases WHERE {$where} AND status != 'awaiting_dispatch' GROUP BY stage";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats = array('ad' => array('count' => 0, 'amount' => 0), 4 => array('count' => 0, 'amount' => 0), 5 => array('count' => 0, 'amount' => 0), 6 => array('count' => 0, 'amount' => 0), 7 => array('count' => 0, 'amount' => 0));
        foreach ($rows as $r) {
            $s = (int)$r['stage'];
            if (isset($stats[$s])) {
                $stats[$s]['count'] = (int)$r['cnt'];
                $stats[$s]['amount'] = (float)$r['total_amount'];
            }
        }

        // 再算待安排派工查修
        $sqlAd = "SELECT COUNT(*) as cnt, COALESCE(SUM(deal_amount),0) as total_amount FROM cases WHERE status = 'awaiting_dispatch'";
        $paramsAd = array();
        if (!empty($filters['engineer_id'])) {
            $sqlAd .= " AND EXISTS (SELECT 1 FROM schedules s2 JOIN schedule_engineers se2 ON se2.schedule_id = s2.id WHERE s2.case_id = cases.id AND se2.user_id = ?)";
            $paramsAd[] = $filters['engineer_id'];
        }
        if (!empty($filters['branch_id'])) {
            $sqlAd .= " AND branch_id = ?";
            $paramsAd[] = $filters['branch_id'];
        }
        $stmtAd = $this->db->prepare($sqlAd);
        $stmtAd->execute($paramsAd);
        $adRow = $stmtAd->fetch(PDO::FETCH_ASSOC);
        if ($adRow) {
            $stats['ad']['count'] = (int)$adRow['cnt'];
            $stats['ad']['amount'] = (float)$adRow['total_amount'];
        }

        return $stats;
    }

    /**
     * 取得工程人員列表
     */
    public function getEngineers()
    {
        $stmt = $this->db->query("SELECT id, real_name, is_active FROM users WHERE role IN ('engineer','eng_manager','eng_deputy') ORDER BY is_active DESC, real_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 檢查工程師是否參與該案件（透過排工）
     */
    public function isEngineerAssigned($caseId, $userId)
    {
        $stmt = $this->db->prepare("SELECT 1 FROM schedules s JOIN schedule_engineers se ON se.schedule_id = s.id WHERE s.case_id = ? AND se.user_id = ? LIMIT 1");
        $stmt->execute(array($caseId, $userId));
        return (bool)$stmt->fetchColumn();
    }

    /**
     * 更新案件階段
     */
    public function updateStage($id, $stage, $data = array())
    {
        $sets = array('stage = ?');
        $params = array($stage);

        if (!empty($data['sub_status'])) {
            $sets[] = 'sub_status = ?';
            $params[] = $data['sub_status'];
        }
        if (isset($data['deal_amount'])) {
            $sets[] = 'deal_amount = ?';
            $params[] = $data['deal_amount'];
        }
        if (!empty($data['deal_date'])) {
            $sets[] = 'deal_date = ?';
            $params[] = $data['deal_date'];
        }
        if (!empty($data['lost_reason'])) {
            $sets[] = 'lost_reason = ?';
            $params[] = $data['lost_reason'];
        }

        $params[] = $id;
        $sql = "UPDATE cases SET " . implode(', ', $sets) . " WHERE id = ?";
        $this->db->prepare($sql)->execute($params);
    }

    /**
     * 業務進件（簡化版建立案件）
     */
    public function createSalesCase($data)
    {
        $caseNumber = generate_doc_number('cases');

        $sql = "INSERT INTO cases (case_number, title, branch_id, case_type, case_source, company,
                customer_name, customer_phone, customer_mobile, customer_id, contact_person, contact_address,
                construction_area, city, district, address, sales_id, deal_amount, deal_date, sales_note, sub_status, survey_date, visit_method,
                stage, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'tracking', ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            $caseNumber,
            $data['title'],
            $data['branch_id'] ?: null,
            $data['case_type'] ?: null,
            $data['case_source'] ?: null,
            $data['company'] ?: null,
            $data['customer_name'] ?: null,
            $data['customer_phone'] ?: null,
            $data['customer_mobile'] ?: null,
            $data['customer_id'] ?: null,
            $data['contact_person'] ?: null,
            !empty($data['contact_address']) ? $data['contact_address'] : null,
            !empty($data['construction_area']) ? $data['construction_area'] : null,
            $data['city'] ?: null,
            $data['district'] ?: null,
            $data['address'] ?: null,
            $data['sales_id'] ?: Auth::id(),
            $data['deal_amount'] ?: null,
            !empty($data['deal_date']) ? $data['deal_date'] : null,
            $data['sales_note'] ?: null,
            !empty($data['sub_status']) ? $data['sub_status'] : null,
            !empty($data['survey_date']) ? $data['survey_date'] : null,
            !empty($data['visit_method']) ? $data['visit_method'] : null,
            Auth::id()
        ));
        $newId = $this->db->lastInsertId();

        // 場勘日期同步到業務行事曆
        if (!empty($data['survey_date'])) {
            $this->syncSurveyToCalendar($newId, $data);
        }

        return $newId;
    }

    /**
     * 取得業務人員列表（含離職，離職排後面）
     */
    public function getSalespeople()
    {
        $stmt = $this->db->query("SELECT id, real_name, is_active FROM users WHERE (role IN ('sales','sales_manager','sales_assistant','boss','vice_president','manager') OR is_sales = 1) AND employee_id IS NOT NULL AND employee_id != '' ORDER BY is_active DESC, real_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 階段名稱
     */
    /**
     * 自動計算案件階段（根據條件判斷）
     */
    public function calcAutoStage($caseId)
    {
        // 取得案件基本資料
        $stmt = $this->db->prepare("SELECT c.*, cr.has_quotation, cr.has_site_photos, cr.has_site_info
            FROM cases c LEFT JOIN case_readiness cr ON cr.case_id = c.id WHERE c.id = ?");
        $stmt->execute(array($caseId));
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) return 1;

        // 8 未成交/無效
        if ($c['status'] === 'lost' || $c['status'] === 'customer_cancel' || $c['status'] === 'breach') return 8;
        if (in_array($c['sub_status'], array('無效', '已報價無意願', '報價無下文', '客戶毀約'))) return 8;

        // 7 結案：已完工結案 或 完工未收款
        if ($c['status'] === 'closed' || $c['status'] === 'unpaid' || $c['status'] === 'completed') return 7;

        // 檢查是否有施工回報
        $wlCount = $this->db->prepare("SELECT COUNT(*) FROM work_logs wl JOIN schedules s ON wl.schedule_id = s.id WHERE s.case_id = ? AND wl.work_description IS NOT NULL AND wl.work_description != ''");
        $wlCount->execute(array($caseId));
        $hasWorklog = (int)$wlCount->fetchColumn() > 0;

        // 6 已進場/需再安排：有施工回報
        if ($hasWorklog) return 6;

        // 檢查是否有排工
        $schCount = $this->db->prepare("SELECT COUNT(*) FROM schedules WHERE case_id = ? AND status != 'cancelled'");
        $schCount->execute(array($caseId));
        $hasSchedule = (int)$schCount->fetchColumn() > 0;

        // 5 已排工/已排行事曆：有排工紀錄
        if ($hasSchedule) return 5;

        // 檢查報價單是否客戶接受
        $accCount = $this->db->prepare("SELECT COUNT(*) FROM quotations WHERE case_id = ? AND status IN ('customer_accepted','accepted')");
        $accCount->execute(array($caseId));
        $hasAccepted = (int)$accCount->fetchColumn() > 0;

        // 4 成交：報價單客戶已接受 或 有成交金額
        if ($hasAccepted || !empty($c['deal_amount'])) return 4;

        // 檢查是否有報價單
        $qCount = $this->db->prepare("SELECT COUNT(*) FROM quotations WHERE case_id = ?");
        $qCount->execute(array($caseId));
        $hasQuotation = (int)$qCount->fetchColumn() > 0;

        // 3 報價：有報價單
        if ($hasQuotation || !empty($c['has_quotation'])) return 3;

        // 2 場勘：有場勘日期、已聯絡安排場勘、或有現場資料
        if (!empty($c['survey_date']) || $c['sub_status'] === '已聯絡安排場勘' || $c['sub_status'] === '已聯絡待場勘' || $c['sub_status'] === '待場勘') return 2;
        if (!empty($c['has_site_info']) || !empty($c['has_site_photos']) || !empty($c['planned_start_date'])) return 2;

        // 1 進件
        return 1;
    }

    /**
     * 同步更新案件階段
     */
    public function syncStage($caseId)
    {
        $newStage = $this->calcAutoStage($caseId);
        $this->db->prepare("UPDATE cases SET stage = ? WHERE id = ?")->execute(array($newStage, $caseId));
        return $newStage;
    }

    public static function stageLabels()
    {
        return array(
            1 => '進件',
            2 => '場勘',
            3 => '報價',
            4 => '成交待排工',
            5 => '已排工/已排行事曆',
            6 => '已進場/需再安排',
            7 => '已完工結案',
            8 => '未成交/無效',
        );
    }

    /**
     * 案別選項
     */
    public static function caseTypeOptions()
    {
        $fallback = array(
            'new_install'  => '新案',
            'addition'     => '老客戶追加',
            'old_repair'   => '舊客戶維修案',
            'new_repair'   => '新客戶維修案',
            'maintenance'  => '維護保養',
        );
        return self::loadDropdownOptions('case_type', $fallback);
    }

    /**
     * 案件進度選項 (status)
     */
    public static function progressOptions()
    {
        $fallback = array(
            'tracking'           => '待追蹤',
            'incomplete'         => '未完工',
            'unpaid'             => '完工未收款',
            'completed_pending'  => '已完工待簽核',
            'closed'             => '已完工結案',
            'lost'               => '未成交',
            'maint_case'         => '保養案件',
            'breach'             => '毀約',
            'scheduled'          => '已排工/已排行事曆',
            'needs_reschedule'   => '已進場/需再安排',
            'awaiting_dispatch'  => '待安排派工查修',
            'customer_cancel'    => '客戶取消',
        );
        return self::loadDropdownOptions('case_progress', $fallback);
    }

    /**
     * 狀態選項 (sub_status)
     */
    public static function subStatusOptions()
    {
        $fallback = array(
            '未指派'           => '未指派',
            '待聯絡'           => '待聯絡',
            '電話不通或未接'   => '電話不通或未接',
            '待場勘'           => '待場勘',
            '已聯絡安排場勘'   => '已聯絡安排場勘',
            '已聯絡電話報價'   => '已聯絡電話報價',
            '已會勘未報價'     => '已會勘未報價',
            '已報價待追蹤'     => '已報價待追蹤',
            '規劃或預算案'     => '規劃或預算案',
            '電話報價成交'     => '電話報價成交',
            '已成交'           => '已成交',
            '跨月成交'         => '跨月成交',
            '現簽'             => '現簽',
            '已報價無意願'     => '已報價無意願',
            '報價無下文'       => '報價無下文',
            '無效'             => '無效',
            '客戶毀約'         => '客戶毀約',
        );
        return self::loadDropdownOptions('case_status', $fallback);
    }

    /**
     * 案件來源選項
     */
    public static function caseSourceOptions()
    {
        return array(
            'phone'        => '電話',
            'headquarters' => '總公司',
            'sales_dev'    => '業務開發',
            'referral'     => '老客戶介紹',
            'internet'     => '網路',
            'builder'      => '建商配合',
            'cross_biz'    => '異業合作',
            'other'        => '其他',
        );
    }

    /**
     * 階段顏色
     */
    public static function stageColor($stage)
    {
        $colors = array(
            1 => '#2196F3',
            2 => '#FF9800',
            3 => '#9C27B0',
            4 => '#4CAF50',
            5 => '#00BCD4',
            6 => '#F44336',
            7 => '#607D8B',
            8 => '#9E9E9E',
        );
        return isset($colors[$stage]) ? $colors[$stage] : '#9E9E9E';
    }

    /**
     * 案件進度中文
     */
    public static function statusLabel($status)
    {
        $map = self::progressOptions();
        // 相容實際資料庫值
        $extra = array(
            'pending'           => '待追蹤',
            'in_progress'       => '未完工',
            'completed'         => '已完工結案',
            'cancelled'         => '客戶取消',
            'ready'             => '待安排派工',
            'scheduled'         => '已排工/已排行事曆',
            'needs_reschedule'  => '已進場/需再安排',
            'active'            => '進行中',
        );
        if (isset($map[$status])) return $map[$status];
        if (isset($extra[$status])) return $extra[$status];
        return $status;
    }

    /**
     * 案件進度 badge class
     */
    public static function statusBadge($status)
    {
        $map = array(
            'tracking'          => 'warning',
            'incomplete'        => 'primary',
            'unpaid'            => 'info',
            'completed_pending' => 'warning',
            'lost'              => 'danger',
            'maint_case'        => 'success',
            'breach'            => 'danger',
            'awaiting_dispatch' => 'warning',
            'customer_cancel'   => 'danger',
            'pending'           => 'warning',
            'in_progress'       => 'primary',
            'completed'         => 'success',
            'cancelled'         => 'danger',
            'scheduled'         => 'info',
            'active'            => 'primary',
            'closed'            => 'success',
        );
        return 'badge-' . (isset($map[$status]) ? $map[$status] : 'primary');
    }

    /**
     * 案別中文
     */
    public static function typeLabel($type)
    {
        $all = self::caseTypeOptions();
        // 相容舊值
        $legacy = array(
            'new'        => '新案',
            'repair'     => '維修',
            'inspection' => '勘查',
            'other'      => '其他',
        );
        if (isset($all[$type])) return $all[$type];
        if (isset($legacy[$type])) return $legacy[$type];
        return $type;
    }

    /**
     * 從 dropdown_options 表載入選項，若表不存在或無資料則回傳 fallback
     * @param string $category
     * @param array $fallback key => label 的陣列
     * @return array
     */
    private static function loadDropdownOptions($category, $fallback)
    {
        static $cache = array();
        if (isset($cache[$category])) {
            return $cache[$category];
        }
        try {
            $db = Database::getInstance();
            // 檢查 option_key 欄位是否存在
            $cols = $db->query("SHOW COLUMNS FROM dropdown_options LIKE 'option_key'")->fetchAll();
            if (empty($cols)) {
                $cache[$category] = $fallback;
                return $fallback;
            }
            $stmt = $db->prepare(
                'SELECT option_key, label FROM dropdown_options WHERE category = ? AND is_active = 1 ORDER BY sort_order, label'
            );
            $stmt->execute(array($category));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                $cache[$category] = $fallback;
                return $fallback;
            }
            $result = array();
            foreach ($rows as $row) {
                $key = $row['option_key'] ? $row['option_key'] : $row['label'];
                $result[$key] = $row['label'];
            }
            $cache[$category] = $result;
            return $result;
        } catch (PDOException $e) {
            $cache[$category] = $fallback;
            return $fallback;
        }
    }

    /**
     * 取得下拉選單選項
     */
    public function getDropdownOptions($category)
    {
        $stmt = $this->db->prepare(
            'SELECT id, label FROM dropdown_options WHERE category = ? AND is_active = 1 ORDER BY sort_order, label'
        );
        $stmt->execute(array($category));
        return $stmt->fetchAll();
    }

    /**
     * 附件類型選項（從 dropdown_options 載入，fallback 到預設值）
     */
    public static function attachTypeOptions()
    {
        $fallback = array(
            'drawing'    => '施工圖',
            'quotation'  => '報價單',
            'warranty'   => '保固書',
            'wire_plan'  => '預計使用線材',
            'site_photo' => '現場照片',
            'other'      => '其他',
        );
        return self::loadDropdownOptions('case_attach_type', $fallback);
    }

    /**
     * 新增自訂附件類型到 dropdown_options
     */
    public static function addAttachType($key, $label)
    {
        $db = Database::getInstance();
        $maxSort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM dropdown_options WHERE category = 'case_attach_type'")->fetchColumn();
        $stmt = $db->prepare("INSERT INTO dropdown_options (category, option_key, label, sort_order, is_active, is_system) VALUES ('case_attach_type', ?, ?, ?, 1, 0)");
        $stmt->execute(array($key, $label, $maxSort + 1));
        return $db->lastInsertId();
    }

    // ===== 編輯鎖定（防衝突） =====

    public function setEditingLock($caseId, $userId, $userName)
    {
        $this->db->prepare("INSERT INTO case_editing_locks (case_id, user_id, user_name, locked_at, heartbeat_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE user_name = VALUES(user_name), heartbeat_at = NOW()")
            ->execute(array($caseId, $userId, $userName));
    }

    public function getEditingUsers($caseId, $excludeUserId, $timeoutMinutes = 5)
    {
        $stmt = $this->db->prepare("SELECT user_id, user_name, heartbeat_at FROM case_editing_locks WHERE case_id = ? AND user_id != ? AND heartbeat_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute(array($caseId, $excludeUserId, $timeoutMinutes));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function refreshEditingLock($caseId, $userId)
    {
        $this->db->prepare("UPDATE case_editing_locks SET heartbeat_at = NOW() WHERE case_id = ? AND user_id = ?")
            ->execute(array($caseId, $userId));
    }

    public function releaseEditingLock($caseId, $userId)
    {
        $this->db->prepare("DELETE FROM case_editing_locks WHERE case_id = ? AND user_id = ?")
            ->execute(array($caseId, $userId));
    }
}
