<?php
/**
 * 通用簽核引擎
 * 支援：報價單、支出單、請購單、請假單、加班單等
 */
class ApprovalModel
{
    /** @var PDO */
    private $db;

    private static $moduleLabels = array(
        'quotations'       => '報價單',
        'expenses'         => '支出單',
        'purchases'        => '請購單',
        'purchase_orders'  => '採購單',
        'leaves'           => '請假單',
        'overtime'         => '加班單',
        'case_completion'  => '案件 > 完工簽核',
        'case_payments'    => '案件 > 收款簽核',
    );

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function moduleLabel($module)
    {
        return isset(self::$moduleLabels[$module]) ? self::$moduleLabels[$module] : $module;
    }

    // ===== 簽核規則管理 =====

    /**
     * 取得簽核規則列表
     */
    public function getRules($module = null)
    {
        $where = '1=1';
        $params = array();
        if ($module) {
            $where .= ' AND r.module = ?';
            $params[] = $module;
        }
        $stmt = $this->db->prepare("
            SELECT r.*, u.real_name as approver_name
            FROM approval_rules r
            LEFT JOIN users u ON r.approver_id = u.id
            WHERE $where
            ORDER BY r.module, r.level_order, r.min_amount
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得單一規則
     */
    public function getRuleById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM approval_rules WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 儲存規則（新增或更新）
     */
    public function saveRule($data)
    {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare("
                UPDATE approval_rules SET
                    module = ?, rule_name = ?, min_amount = ?, max_amount = ?,
                    min_profit_rate = ?, condition_type = ?, product_ids = ?, product_category_id = ?,
                    approver_role = ?, approver_id = ?,
                    level_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute(array(
                $data['module'],
                $data['rule_name'],
                $data['min_amount'] ?: 0,
                $data['max_amount'] ?: null,
                $data['min_profit_rate'] ?: null,
                $data['condition_type'] ?: 'amount',
                $data['product_ids'] ?: null,
                $data['product_category_id'] ?: null,
                $data['approver_role'] ?: null,
                $data['approver_id'] ?: null,
                $data['level_order'] ?: 1,
                isset($data['is_active']) ? $data['is_active'] : 1,
                $data['id'],
            ));
            return (int)$data['id'];
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO approval_rules (module, rule_name, min_amount, max_amount,
                    min_profit_rate, condition_type, product_ids, product_category_id,
                    approver_role, approver_id, level_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(array(
                $data['module'],
                $data['rule_name'],
                $data['min_amount'] ?: 0,
                $data['max_amount'] ?: null,
                $data['min_profit_rate'] ?: null,
                $data['condition_type'] ?: 'amount',
                $data['product_ids'] ?: null,
                $data['product_category_id'] ?: null,
                $data['approver_role'] ?: null,
                $data['approver_id'] ?: null,
                $data['level_order'] ?: 1,
                isset($data['is_active']) ? $data['is_active'] : 1,
            ));
            return (int)$this->db->lastInsertId();
        }
    }

    /**
     * 刪除規則
     */
    public function deleteRule($id)
    {
        $this->db->prepare("DELETE FROM approval_rules WHERE id = ?")->execute(array($id));
    }

    // ===== 簽核流程 =====

    /**
     * 判斷是否需要簽核
     * @return array|false  需要簽核時回傳匹配的規則陣列，不需要回傳 false
     */
    public function needsApproval($module, $amount, $profitRate = null, $productIds = array())
    {
        $stmt = $this->db->prepare("
            SELECT * FROM approval_rules
            WHERE module = ? AND is_active = 1
            ORDER BY level_order, min_amount
        ");
        $stmt->execute(array($module));
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rules)) return false; // 沒設定規則 = 不需簽核

        $matched = array();
        foreach ($rules as $rule) {
            $condType = isset($rule['condition_type']) ? $rule['condition_type'] : 'amount';

            // 金額條件
            $amountMatch = true;
            if ($condType === 'amount' || $condType === '') {
                if ($rule['min_amount'] > 0 && $amount < $rule['min_amount']) $amountMatch = false;
                if ($rule['max_amount'] !== null && $amount > $rule['max_amount']) $amountMatch = false;
            }

            // 產品條件
            $productMatch = true;
            if ($condType === 'product' && !empty($rule['product_ids'])) {
                $ruleProductIds = array_map('intval', explode(',', $rule['product_ids']));
                $productMatch = !empty(array_intersect($ruleProductIds, array_map('intval', $productIds)));
            }

            // 產品分類條件
            if ($condType === 'category' && !empty($rule['product_category_id'])) {
                $catId = (int)$rule['product_category_id'];
                // 查詢品項是否屬於該分類
                if (!empty($productIds)) {
                    $ph = implode(',', array_fill(0, count($productIds), '?'));
                    $catStmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE id IN ($ph) AND category_id = ?");
                    $params = array_map('intval', $productIds);
                    $params[] = $catId;
                    $catStmt->execute($params);
                    $productMatch = $catStmt->fetchColumn() > 0;
                } else {
                    $productMatch = false;
                }
            }

            // 利潤率檢查（如果有設定）
            $profitMatch = true;
            if ($rule['min_profit_rate'] !== null && $profitRate !== null) {
                if ($profitRate < $rule['min_profit_rate']) $profitMatch = false;
            }

            if ($amountMatch && $profitMatch && $productMatch) {
                $matched[] = $rule;
            }
        }

        if (empty($matched)) return false;

        // 如果所有匹配的規則都是免簽核（auto_approve），則不需簽核
        $allAutoApprove = true;
        foreach ($matched as $m) {
            if ($m['approver_role'] !== 'auto_approve') {
                $allAutoApprove = false;
                break;
            }
        }
        if ($allAutoApprove) return false;

        return $matched;
    }

    /**
     * 送簽核
     * @return array  建立的 flow 記錄
     */
    public function submitForApproval($module, $targetId, $amount, $profitRate = null, $submittedBy = null)
    {
        if (!$submittedBy) $submittedBy = Auth::id();

        // 先清除舊的 pending 記錄（重新送簽時）
        $this->db->prepare("DELETE FROM approval_flows WHERE module = ? AND target_id = ? AND status = 'pending'")
            ->execute(array($module, $targetId));

        $matchedRules = $this->needsApproval($module, $amount, $profitRate);

        if (!$matchedRules) {
            // 不需簽核，直接通過
            return array('auto_approved' => true);
        }

        $flows = array();
        foreach ($matchedRules as $rule) {
            // 免簽核規則跳過，不建立簽核流程
            if ($rule['approver_role'] === 'auto_approve') continue;

            // 找出簽核人
            $approverId = $rule['approver_id'];

            // 如果指定角色而非指定人，找該角色的人
            if (!$approverId && $rule['approver_role']) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1 LIMIT 1");
                $stmt->execute(array($rule['approver_role']));
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) $approverId = $user['id'];
            }

            if (!$approverId) continue; // 找不到簽核人就跳過

            $stmt = $this->db->prepare("
                INSERT INTO approval_flows (module, target_id, rule_id, level_order, approver_id, status, submitted_by)
                VALUES (?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute(array($module, $targetId, $rule['id'], $rule['level_order'], $approverId, $submittedBy));
            $flows[] = array(
                'id' => (int)$this->db->lastInsertId(),
                'approver_id' => $approverId,
                'level_order' => $rule['level_order'],
            );
        }

        // 如果過濾掉 auto_approve 後沒有實際簽核流程，視為自動通過
        if (empty($flows)) {
            return array('auto_approved' => true);
        }

        return $flows;
    }

    /**
     * 核准
     */
    public function approve($flowId, $approverId, $comment = null)
    {
        $stmt = $this->db->prepare("
            UPDATE approval_flows SET status = 'approved', comment = ?, decided_at = NOW()
            WHERE id = ? AND approver_id = ? AND status = 'pending'
        ");
        $stmt->execute(array($comment, $flowId, $approverId));
        return $stmt->rowCount() > 0;
    }

    /**
     * 退回
     */
    public function reject($flowId, $approverId, $comment = null)
    {
        $stmt = $this->db->prepare("
            UPDATE approval_flows SET status = 'rejected', comment = ?, decided_at = NOW()
            WHERE id = ? AND approver_id = ? AND status = 'pending'
        ");
        $stmt->execute(array($comment, $flowId, $approverId));
        return $stmt->rowCount() > 0;
    }

    /**
     * 查詢某單據的簽核狀態
     * @return array  含 flows 和 overall_status
     */
    public function getFlowStatus($module, $targetId)
    {
        $stmt = $this->db->prepare("
            SELECT af.*, u.real_name as approver_name, sub.real_name as submitter_name
            FROM approval_flows af
            LEFT JOIN users u ON af.approver_id = u.id
            LEFT JOIN users sub ON af.submitted_by = sub.id
            WHERE af.module = ? AND af.target_id = ?
            ORDER BY af.level_order, af.submitted_at DESC
        ");
        $stmt->execute(array($module, $targetId));
        $flows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($flows)) {
            return array('flows' => array(), 'overall' => 'none');
        }

        $hasPending = false;
        $hasRejected = false;
        $allApproved = true;

        foreach ($flows as $f) {
            if ($f['status'] === 'pending') { $hasPending = true; $allApproved = false; }
            if ($f['status'] === 'rejected') { $hasRejected = true; $allApproved = false; }
        }

        $overall = 'pending';
        if ($hasRejected) $overall = 'rejected';
        elseif ($allApproved) $overall = 'approved';

        return array('flows' => $flows, 'overall' => $overall);
    }

    /**
     * 是否全部簽核通過
     */
    public function isFullyApproved($module, $targetId)
    {
        $status = $this->getFlowStatus($module, $targetId);
        return $status['overall'] === 'approved';
    }

    /**
     * 取得待簽核清單（給某個簽核人）
     */
    public function getPendingList($approverId)
    {
        $stmt = $this->db->prepare("
            SELECT af.*, sub.real_name as submitter_name
            FROM approval_flows af
            LEFT JOIN users sub ON af.submitted_by = sub.id
            WHERE af.approver_id = ? AND af.status = 'pending'
            ORDER BY af.submitted_at DESC
        ");
        $stmt->execute(array($approverId));
        $flows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 補充各模組的單據資訊
        foreach ($flows as &$f) {
            $f['target_info'] = $this->getTargetInfo($f['module'], $f['target_id']);
        }
        unset($f);

        return $flows;
    }

    /**
     * 取得待簽核數量
     */
    public function getPendingCount($approverId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM approval_flows WHERE approver_id = ? AND status = 'pending'");
        $stmt->execute(array($approverId));
        return (int)$stmt->fetchColumn();
    }

    /**
     * 取得單據資訊（各模組）
     */
    private function getTargetInfo($module, $targetId)
    {
        switch ($module) {
            case 'quotations':
                $stmt = $this->db->prepare("SELECT id, quotation_number, customer_name, total_amount, status, quote_date FROM quotations WHERE id = ?");
                $stmt->execute(array($targetId));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $row['label'] = $row['quotation_number'] . ' - ' . $row['customer_name'];
                    $row['url'] = '/quotations.php?action=view&id=' . $targetId;
                    $row['amount'] = $row['total_amount'];
                }
                return $row ?: array();
            case 'case_completion':
                $stmt = $this->db->prepare("SELECT id, case_number, title, customer_name, contract_amount, progress FROM cases WHERE id = ?");
                $stmt->execute(array($targetId));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $row['label'] = ($row['case_number'] ? $row['case_number'] . ' - ' : '') . $row['title'] . ' (' . $row['customer_name'] . ')';
                    $row['url'] = '/cases.php?action=edit&id=' . $targetId;
                    $row['amount'] = $row['contract_amount'];
                }
                return $row ?: array();
            case 'case_payments':
                $stmt = $this->db->prepare("
                    SELECT cp.*, c.case_number, c.title, c.customer_name
                    FROM case_payments cp
                    LEFT JOIN cases c ON cp.case_id = c.id
                    WHERE cp.id = ?
                ");
                $stmt->execute(array($targetId));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $row['label'] = ($row['case_number'] ? $row['case_number'] . ' - ' : '') . $row['customer_name'] . ' $' . number_format($row['amount']) . ' (' . $row['payment_type'] . ')';
                    $row['url'] = '/cases.php?action=edit&id=' . $row['case_id'] . '#sec-payments';
                }
                return $row ?: array();
            case 'purchases':
                $stmt = $this->db->prepare("SELECT id, requisition_number, requester_name, case_name, vendor_name, status FROM requisitions WHERE id = ?");
                $stmt->execute(array($targetId));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    // 計算總金額
                    $stmtAmt = $this->db->prepare("SELECT SUM(quantity * unit_price) as total FROM requisition_items WHERE requisition_id = ?");
                    $stmtAmt->execute(array($targetId));
                    $total = $stmtAmt->fetch(PDO::FETCH_ASSOC);
                    $row['label'] = $row['requisition_number'];
                    if (!empty($row['case_name'])) $row['label'] .= ' - ' . $row['case_name'];
                    if (!empty($row['vendor_name'])) $row['label'] .= ' (' . $row['vendor_name'] . ')';
                    $row['url'] = '/requisitions.php?action=edit&id=' . $targetId;
                    $row['amount'] = !empty($total['total']) ? $total['total'] : 0;
                }
                return $row ?: array();
            default:
                return array('label' => $module . ' #' . $targetId, 'url' => '#');
        }
    }

    /**
     * 取得可當簽核人的使用者列表
     */
    public function getApprovers()
    {
        $stmt = $this->db->query("
            SELECT id, real_name, role FROM users
            WHERE is_active = 1 AND role IN ('boss','sales_manager','eng_manager','eng_deputy','admin_staff')
            ORDER BY FIELD(role,'boss','sales_manager','eng_manager','eng_deputy','admin_staff'), real_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 角色中文
     */
    public static function roleLabel($role)
    {
        $map = array(
            'boss' => '系統管理者',
            'vice_president' => '副總',
            'manager' => '分公司／部門管理者',
            'assistant_manager' => '協理',
            'sales_manager' => '業務主管',
            'eng_manager' => '工程主管',
            'eng_deputy' => '工程副主管',
            'engineer' => '工程人員',
            'sales' => '業務',
            'sales_assistant' => '業務助理',
            'admin_staff' => '行政人員',
            'accountant' => '會計人員',
            'warehouse' => '倉管',
            'purchaser' => '採購',
            'hq' => '總公司',
            'auto_approve' => '免簽核（自動通過）',
        );
        return isset($map[$role]) ? $map[$role] : $role;
    }

    // ===== 完工簽核專用流程 =====

    /**
     * 送完工簽核（只送 level 1 = eng_manager）
     * 若無規則則自動建立預設規則
     */
    public function submitCaseCompletion($caseId, $submittedBy = null)
    {
        if (!$submittedBy) $submittedBy = Auth::id();

        // 確保有 case_completion 規則，沒有就自動建立
        $this->ensureCaseCompletionRules();

        // 清除舊的 pending 記錄
        $this->db->prepare("DELETE FROM approval_flows WHERE module = 'case_completion' AND target_id = ? AND status = 'pending'")
            ->execute(array($caseId));

        // 只送 level 1（工程主管）
        $stmt = $this->db->prepare("
            SELECT * FROM approval_rules
            WHERE module = 'case_completion' AND is_active = 1 AND level_order = 1
            ORDER BY id LIMIT 1
        ");
        $stmt->execute();
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) return array('auto_approved' => true);

        $approverId = $rule['approver_id'];
        if (!$approverId && $rule['approver_role']) {
            $stmt2 = $this->db->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1 LIMIT 1");
            $stmt2->execute(array($rule['approver_role']));
            $user = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($user) $approverId = $user['id'];
        }

        if (!$approverId) return array('auto_approved' => true);

        $stmt3 = $this->db->prepare("
            INSERT INTO approval_flows (module, target_id, rule_id, level_order, approver_id, status, submitted_by)
            VALUES ('case_completion', ?, ?, 1, ?, 'pending', ?)
        ");
        $stmt3->execute(array($caseId, $rule['id'], $approverId, $submittedBy));

        return array(
            array(
                'id' => (int)$this->db->lastInsertId(),
                'approver_id' => $approverId,
                'level_order' => 1,
            )
        );
    }

    /**
     * 完工簽核 - 工程主管核准後推進到 level 2（會計）
     * @return bool true=已推進到下一關, false=全部簽完
     */
    public function advanceCaseCompletion($caseId)
    {
        // 檢查 level 1 是否都通過
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM approval_flows
            WHERE module = 'case_completion' AND target_id = ? AND level_order = 1 AND status != 'approved'
        ");
        $stmt->execute(array($caseId));
        if ((int)$stmt->fetchColumn() > 0) return true; // level 1 還沒全過

        // 檢查 level 2 是否已建立
        $stmt2 = $this->db->prepare("
            SELECT COUNT(*) FROM approval_flows
            WHERE module = 'case_completion' AND target_id = ? AND level_order = 2
        ");
        $stmt2->execute(array($caseId));
        if ((int)$stmt2->fetchColumn() > 0) {
            // level 2 已存在 → 檢查是否全過
            $stmt3 = $this->db->prepare("
                SELECT COUNT(*) FROM approval_flows
                WHERE module = 'case_completion' AND target_id = ? AND level_order = 2 AND status != 'approved'
            ");
            $stmt3->execute(array($caseId));
            return (int)$stmt3->fetchColumn() > 0; // true=還沒全過
        }

        // level 2 不存在 → 自動建立
        $rule = $this->db->prepare("
            SELECT * FROM approval_rules
            WHERE module = 'case_completion' AND is_active = 1 AND level_order = 2
            ORDER BY id LIMIT 1
        ");
        $rule->execute();
        $rule2 = $rule->fetch(PDO::FETCH_ASSOC);

        if (!$rule2) {
            // 沒有 level 2 規則 → 直接結案
            return false;
        }

        $approverId = $rule2['approver_id'];
        if (!$approverId && $rule2['approver_role']) {
            $st = $this->db->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1 LIMIT 1");
            $st->execute(array($rule2['approver_role']));
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if ($u) $approverId = $u['id'];
        }

        if (!$approverId) return false; // 找不到簽核人 → 直接結案

        // 取得原始送簽人
        $origStmt = $this->db->prepare("
            SELECT submitted_by FROM approval_flows
            WHERE module = 'case_completion' AND target_id = ? AND level_order = 1
            ORDER BY id LIMIT 1
        ");
        $origStmt->execute(array($caseId));
        $submittedBy = $origStmt->fetchColumn() ?: 0;

        $this->db->prepare("
            INSERT INTO approval_flows (module, target_id, rule_id, level_order, approver_id, status, submitted_by)
            VALUES ('case_completion', ?, ?, 2, ?, 'pending', ?)
        ")->execute(array($caseId, $rule2['id'], $approverId, $submittedBy));

        return true; // 還有下一關
    }

    /**
     * 確保 case_completion 規則存在（自動建立預設）
     */
    private function ensureCaseCompletionRules()
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM approval_rules WHERE module = 'case_completion' AND is_active = 1");
        $stmt->execute();
        if ((int)$stmt->fetchColumn() > 0) return;

        // 自動建立預設規則
        $this->db->prepare("
            INSERT INTO approval_rules (module, rule_name, min_amount, max_amount, min_profit_rate, approver_role, approver_id, level_order, is_active)
            VALUES ('case_completion', '完工簽核 - 工程主管', 0, NULL, NULL, 'eng_manager', NULL, 1, 1)
        ")->execute();
        $this->db->prepare("
            INSERT INTO approval_rules (module, rule_name, min_amount, max_amount, min_profit_rate, approver_role, approver_id, level_order, is_active)
            VALUES ('case_completion', '完工簽核 - 會計確認', 0, NULL, NULL, 'admin_staff', NULL, 2, 1)
        ")->execute();
    }

    /**
     * 完工簽核退回 → 案件進度改回施工中
     */
    public function rejectCaseCompletion($caseId)
    {
        // 取消所有 pending 的 flow
        $this->db->prepare("
            UPDATE approval_flows SET status = 'cancelled', decided_at = NOW()
            WHERE module = 'case_completion' AND target_id = ? AND status = 'pending'
        ")->execute(array($caseId));

        // 案件進度改回 in_progress
        $this->db->prepare("UPDATE cases SET progress = 'in_progress' WHERE id = ?")->execute(array($caseId));
    }
}
