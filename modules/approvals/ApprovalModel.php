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
        'no_deposit_schedule' => '案件 > 無訂金排工簽核',
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
        // case_types 處理（陣列轉逗號字串）
        $caseTypesStr = null;
        if (!empty($data['case_types'])) {
            if (is_array($data['case_types'])) {
                $caseTypesStr = implode(',', $data['case_types']);
            } else {
                $caseTypesStr = $data['case_types'];
            }
        }

        // extra_approver_ids 處理（陣列轉逗號字串、過濾空值與主簽核人本身）
        $extraIdsStr = null;
        if (!empty($data['extra_approver_ids'])) {
            $arr = is_array($data['extra_approver_ids']) ? $data['extra_approver_ids'] : explode(',', $data['extra_approver_ids']);
            $mainId = !empty($data['approver_id']) ? (int)$data['approver_id'] : 0;
            $clean = array();
            foreach ($arr as $v) {
                $v = (int)$v;
                if ($v > 0 && $v !== $mainId && !in_array($v, $clean)) $clean[] = $v;
            }
            if (!empty($clean)) $extraIdsStr = implode(',', $clean);
        }

        // condition_branch_ids / condition_user_roles 處理
        $branchIdsStr = null;
        if (!empty($data['condition_branch_ids'])) {
            $arr = is_array($data['condition_branch_ids']) ? $data['condition_branch_ids'] : explode(',', $data['condition_branch_ids']);
            $clean = array_filter(array_map('intval', $arr));
            if (!empty($clean)) $branchIdsStr = implode(',', $clean);
        }
        $userRolesStr = null;
        if (!empty($data['condition_user_roles'])) {
            $arr = is_array($data['condition_user_roles']) ? $data['condition_user_roles'] : explode(',', $data['condition_user_roles']);
            $clean = array_filter(array_map('trim', $arr));
            if (!empty($clean)) $userRolesStr = implode(',', $clean);
        }

        $maxProfitRate = (isset($data['max_profit_rate']) && $data['max_profit_rate'] !== '' && $data['max_profit_rate'] !== null) ? (float)$data['max_profit_rate'] : null;
        $minProfitRate = (isset($data['min_profit_rate']) && $data['min_profit_rate'] !== '' && $data['min_profit_rate'] !== null) ? (float)$data['min_profit_rate'] : null;

        if (!empty($data['id'])) {
            $stmt = $this->db->prepare("
                UPDATE approval_rules SET
                    module = ?, rule_name = ?, min_amount = ?, max_amount = ?,
                    min_profit_rate = ?, max_profit_rate = ?, condition_type = ?, product_ids = ?, product_category_id = ?,
                    case_types = ?, condition_branch_ids = ?, condition_user_roles = ?,
                    approver_role = ?, approver_id = ?, extra_approver_ids = ?,
                    level_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute(array(
                $data['module'],
                $data['rule_name'],
                $data['min_amount'] ?: 0,
                $data['max_amount'] ?: null,
                $minProfitRate,
                $maxProfitRate,
                $data['condition_type'] ?: 'amount',
                $data['product_ids'] ?: null,
                $data['product_category_id'] ?: null,
                $caseTypesStr,
                $branchIdsStr,
                $userRolesStr,
                $data['approver_role'] ?: null,
                $data['approver_id'] ?: null,
                $extraIdsStr,
                $data['level_order'] ?: 1,
                isset($data['is_active']) ? $data['is_active'] : 1,
                $data['id'],
            ));
            return (int)$data['id'];
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO approval_rules (module, rule_name, min_amount, max_amount,
                    min_profit_rate, max_profit_rate, condition_type, product_ids, product_category_id,
                    case_types, condition_branch_ids, condition_user_roles,
                    approver_role, approver_id, extra_approver_ids, level_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(array(
                $data['module'],
                $data['rule_name'],
                $data['min_amount'] ?: 0,
                $data['max_amount'] ?: null,
                $minProfitRate,
                $maxProfitRate,
                $data['condition_type'] ?: 'amount',
                $data['product_ids'] ?: null,
                $data['product_category_id'] ?: null,
                $caseTypesStr,
                $branchIdsStr,
                $userRolesStr,
                $data['approver_role'] ?: null,
                $data['approver_id'] ?: null,
                $extraIdsStr,
                $data['level_order'] ?: 1,
                isset($data['is_active']) ? $data['is_active'] : 1,
            ));
            return (int)$this->db->lastInsertId();
        }
    }

    /**
     * 解析規則的所有可簽核人 id（主簽核人 + extra_approver_ids，不含 role 解析）
     */
    private function resolveRuleApproverIds($rule)
    {
        $ids = array();
        if (!empty($rule['approver_id'])) $ids[] = (int)$rule['approver_id'];
        if (!empty($rule['extra_approver_ids'])) {
            foreach (explode(',', $rule['extra_approver_ids']) as $v) {
                $v = (int)$v;
                if ($v > 0 && !in_array($v, $ids)) $ids[] = $v;
            }
        }
        // 沒指定人時用 role 找一個
        if (empty($ids) && !empty($rule['approver_role'])) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1 LIMIT 1");
            $stmt->execute(array($rule['approver_role']));
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u) $ids[] = (int)$u['id'];
        }
        return $ids;
    }

    /**
     * 任一人核准後，把同 (module, target_id, level_order) 其他 pending 設為 cancelled
     * 用於「多人擇一簽核」場景。flow 已 approved 的不會被影響。
     */
    public function cancelSiblingPendingFlows($module, $targetId, $levelOrder, $excludeFlowId)
    {
        $stmt = $this->db->prepare("
            UPDATE approval_flows
            SET status = 'cancelled', decided_at = NOW()
            WHERE module = ? AND target_id = ? AND level_order = ?
              AND status = 'pending' AND id <> ?
        ");
        $stmt->execute(array($module, $targetId, $levelOrder, $excludeFlowId));
        return $stmt->rowCount();
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
    public function needsApproval($module, $amount, $profitRate = null, $productIds = array(), $submitterId = null)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM approval_rules
            WHERE module = ? AND is_active = 1
            ORDER BY level_order, min_amount
        ");
        $stmt->execute(array($module));
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rules)) return false; // 沒設定規則 = 不需簽核

        // 取得送簽人的 branch_id / role（若有分公司或角色條件才需要）
        $submitterBranchId = null;
        $submitterRole = null;
        if ($submitterId) {
            $uStmt = $this->db->prepare("SELECT branch_id, role FROM users WHERE id = ?");
            $uStmt->execute(array($submitterId));
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow) {
                $submitterBranchId = (int)$uRow['branch_id'];
                $submitterRole = $uRow['role'];
            }
        }

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
            if (isset($rule['min_profit_rate']) && $rule['min_profit_rate'] !== null && $profitRate !== null) {
                if ($profitRate < (float)$rule['min_profit_rate']) $profitMatch = false;
            }
            if (isset($rule['max_profit_rate']) && $rule['max_profit_rate'] !== null && $profitRate !== null) {
                if ($profitRate > (float)$rule['max_profit_rate']) $profitMatch = false;
            }

            // 分公司條件（空=全部，有設定則需匹配）
            $branchMatch = true;
            if (!empty($rule['condition_branch_ids'])) {
                $allowedBranches = array_map('intval', array_filter(explode(',', $rule['condition_branch_ids'])));
                if (!empty($allowedBranches)) {
                    $branchMatch = ($submitterBranchId && in_array($submitterBranchId, $allowedBranches));
                }
            }

            // 角色條件（空=全部，有設定則需匹配）
            $roleMatch = true;
            if (!empty($rule['condition_user_roles'])) {
                $allowedRoles = array_map('trim', array_filter(explode(',', $rule['condition_user_roles'])));
                if (!empty($allowedRoles)) {
                    $roleMatch = ($submitterRole && in_array($submitterRole, $allowedRoles));
                }
            }

            if ($amountMatch && $profitMatch && $productMatch && $branchMatch && $roleMatch) {
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

        $matchedRules = $this->needsApproval($module, $amount, $profitRate, array(), $submittedBy);

        if (!$matchedRules) {
            // 不需簽核，直接通過
            return array('auto_approved' => true);
        }

        // leaves/overtime 採「分關建立」模式：只建 L1，待 L1 核准後才建 L2
        $sequentialModules = array('leaves', 'overtime');
        $isSequential = in_array($module, $sequentialModules, true);

        $flows = array();
        foreach ($matchedRules as $rule) {
            // 免簽核規則跳過
            if ($rule['approver_role'] === 'auto_approve') continue;

            // 分關模式：只建最小 level 的規則
            if ($isSequential) {
                $minLevel = PHP_INT_MAX;
                foreach ($matchedRules as $mr) {
                    if ($mr['approver_role'] !== 'auto_approve' && (int)$mr['level_order'] < $minLevel) {
                        $minLevel = (int)$mr['level_order'];
                    }
                }
                if ((int)$rule['level_order'] !== $minLevel) continue;
            }

            // 找出簽核人
            $approverId = $rule['approver_id'];
            if (!$approverId && $rule['approver_role']) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1 LIMIT 1");
                $stmt->execute(array($rule['approver_role']));
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) $approverId = $user['id'];
            }
            if (!$approverId) continue;

            // 主簽核人
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

            // extra_approver_ids（其他可簽核人，任一即可）
            if (!empty($rule['extra_approver_ids'])) {
                $extras = array_filter(array_map('intval', explode(',', $rule['extra_approver_ids'])));
                foreach ($extras as $extraId) {
                    if ($extraId === (int)$approverId) continue;
                    $stmt->execute(array($module, $targetId, $rule['id'], $rule['level_order'], $extraId, $submittedBy));
                    $flows[] = array(
                        'id' => (int)$this->db->lastInsertId(),
                        'approver_id' => $extraId,
                        'level_order' => $rule['level_order'],
                    );
                }
            }
        }

        // 如果過濾掉 auto_approve 後沒有實際簽核流程，視為自動通過
        if (empty($flows)) {
            return array('auto_approved' => true);
        }

        return $flows;
    }

    /**
     * 分關模組（leaves/overtime）的下一關推進
     * 當該 level 的所有 pending flow 被決定（核准/駁回/取消）後，
     * 依規則建立下一關的 flow
     * @return array next_level|null, status: 'pending_next' / 'completed' / 'rejected'
     */
    public function advanceSequentialApproval($module, $targetId, $submittedBy)
    {
        // 取所有已決定或進行中的 flow
        $fs = $this->db->prepare("SELECT level_order, status FROM approval_flows WHERE module = ? AND target_id = ?");
        $fs->execute(array($module, $targetId));
        $allFlows = $fs->fetchAll(PDO::FETCH_ASSOC);

        if (empty($allFlows)) return array('next_level' => null, 'status' => 'no_rule');

        // 如果任一 flow 是 rejected → 整個流程結束
        foreach ($allFlows as $f) {
            if ($f['status'] === 'rejected') {
                return array('next_level' => null, 'status' => 'rejected');
            }
        }

        // 找目前最大已核准 level
        $maxApprovedLevel = 0;
        $hasPending = false;
        foreach ($allFlows as $f) {
            if ($f['status'] === 'approved' && (int)$f['level_order'] > $maxApprovedLevel) {
                $maxApprovedLevel = (int)$f['level_order'];
            }
            if ($f['status'] === 'pending') $hasPending = true;
        }
        if ($hasPending) return array('next_level' => null, 'status' => 'pending_current');

        // 所有現有 level 都決定完 → 找下一 level 的規則
        $nextLevel = $maxApprovedLevel + 1;
        // 需要用送簽人 branch_id/role 過濾
        $matched = $this->needsApproval($module, 0, null, array(), $submittedBy);
        if (!$matched) return array('next_level' => null, 'status' => 'completed');

        $nextRules = array();
        foreach ($matched as $r) {
            if ($r['approver_role'] === 'auto_approve') continue;
            if ((int)$r['level_order'] === $nextLevel) $nextRules[] = $r;
        }
        if (empty($nextRules)) {
            return array('next_level' => null, 'status' => 'completed');
        }

        // 建立下一關
        $stmt = $this->db->prepare("
            INSERT INTO approval_flows (module, target_id, rule_id, level_order, approver_id, status, submitted_by)
            VALUES (?, ?, ?, ?, ?, 'pending', ?)
        ");
        foreach ($nextRules as $rule) {
            $approverId = $rule['approver_id'];
            if (!$approverId && $rule['approver_role']) {
                $u = $this->db->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1 LIMIT 1");
                $u->execute(array($rule['approver_role']));
                $uRow = $u->fetch(PDO::FETCH_ASSOC);
                if ($uRow) $approverId = $uRow['id'];
            }
            if (!$approverId) continue;

            $stmt->execute(array($module, $targetId, $rule['id'], $nextLevel, $approverId, $submittedBy));

            if (!empty($rule['extra_approver_ids'])) {
                foreach (array_filter(array_map('intval', explode(',', $rule['extra_approver_ids']))) as $extraId) {
                    if ($extraId === (int)$approverId) continue;
                    $stmt->execute(array($module, $targetId, $rule['id'], $nextLevel, $extraId, $submittedBy));
                }
            }
        }

        return array('next_level' => $nextLevel, 'status' => 'pending_next');
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
     * 包在 try/catch 內，避免單一模組 SQL 錯誤導致整個待簽核頁面 500
     */
    private function getTargetInfo($module, $targetId)
    {
        try {
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
                // 修正: 原本用 contract_amount/progress 但 cases 表沒這兩個欄位
                $stmt = $this->db->prepare("SELECT id, case_number, title, customer_name, deal_amount, total_amount, current_visit, total_visits FROM cases WHERE id = ?");
                $stmt->execute(array($targetId));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $row['label'] = ($row['case_number'] ? $row['case_number'] . ' - ' : '') . $row['title'] . ' (' . $row['customer_name'] . ')';
                    $row['url'] = '/cases.php?action=edit&id=' . $targetId;
                    $row['amount'] = !empty($row['total_amount']) ? $row['total_amount'] : $row['deal_amount'];
                    $row['progress'] = $row['current_visit'] . '/' . $row['total_visits'];
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
            case 'stocktakes':
                $stmt = $this->db->prepare("SELECT s.*, w.name AS warehouse_name FROM stocktakes s LEFT JOIN warehouses w ON s.warehouse_id = w.id WHERE s.id = ?");
                $stmt->execute(array($targetId));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $row['label'] = $row['stocktake_number'] . ' - ' . ($row['warehouse_name'] ?: '');
                    $row['url'] = '/inventory.php?action=stocktake_edit&id=' . $targetId;
                    $row['amount'] = 0;
                }
                return $row ?: array();
            case 'leaves':
                $stmt = $this->db->prepare("SELECT l.*, u.real_name FROM leaves l LEFT JOIN users u ON l.user_id = u.id WHERE l.id = ?");
                $stmt->execute(array($targetId));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $typeLabels = array('annual'=>'特休','personal'=>'事假','sick'=>'病假','official'=>'公假','day_off'=>'補休','menstrual'=>'生理假','bereavement'=>'喪假','marriage'=>'婚假','maternity'=>'產假','paternity'=>'陪產假','funeral'=>'喪假','other'=>'其他');
                    $typeLabel = isset($typeLabels[$row['leave_type']]) ? $typeLabels[$row['leave_type']] : $row['leave_type'];
                    $row['label'] = '#' . $targetId . ' ' . $row['real_name'] . ' ' . $typeLabel . ' ' . $row['start_date'] . ($row['start_date'] !== $row['end_date'] ? '~' . $row['end_date'] : '');
                    $row['url'] = '/leaves.php?action=view&id=' . $targetId;
                    $row['amount'] = 0;
                }
                return $row ?: array();
            case 'overtime':
                $stmt = $this->db->prepare("SELECT o.*, u.real_name FROM overtimes o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
                $stmt->execute(array($targetId));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $typeLabels = array('weekday'=>'平日延長','rest_day'=>'例假日','holiday'=>'國定假日','other'=>'其他');
                    $typeLabel = isset($typeLabels[$row['overtime_type']]) ? $typeLabels[$row['overtime_type']] : $row['overtime_type'];
                    $st = isset($row['start_time']) ? substr($row['start_time'], 0, 5) : '';
                    $et = isset($row['end_time']) ? substr($row['end_time'], 0, 5) : '';
                    $hoursStr = !empty($row['hours']) ? ' ' . rtrim(rtrim(number_format((float)$row['hours'], 2), '0'), '.') . 'h' : '';
                    $row['label'] = '#' . $targetId . ' ' . $row['real_name'] . ' ' . $typeLabel . ' ' . $row['overtime_date']
                        . ($st ? ' ' . $st . '~' . $et : '') . $hoursStr;
                    $row['url'] = '/overtimes.php?action=view&id=' . $targetId;
                    $row['amount'] = 0;
                }
                return $row ?: array();
            default:
                return array('label' => $module . ' #' . $targetId, 'url' => '#');
        }
        } catch (Exception $e) {
            error_log('ApprovalModel::getTargetInfo error (' . $module . '#' . $targetId . '): ' . $e->getMessage());
            return array(
                'label' => $module . ' #' . $targetId . '（讀取失敗）',
                'url' => '#',
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * 取得可當簽核人的使用者列表
     */
    public function getApprovers()
    {
        // 原本的主管/行政類 + 指定的 4 位業務（蔣佩曄 104、許進鑫 88、謝旻倫 96、顏伸祐 77）
        $stmt = $this->db->query("
            SELECT id, real_name, role FROM users
            WHERE is_active = 1 AND (
                role IN ('boss','sales_manager','eng_manager','eng_deputy','admin_staff','accountant')
                OR id IN (104, 88, 96, 77)
            )
            ORDER BY FIELD(role,'boss','sales_manager','eng_manager','eng_deputy','admin_staff','accountant','sales'), real_name
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

    // ===== 無訂金排工簽核 =====

    /**
     * 檢查案件是否需要無訂金排工簽核
     * 邏輯：查 active 的 no_deposit_schedule 規則，逐條比對
     * 符合任一規則 → 需簽核 (return true)
     * 沒有規則或都不符合 → 不需簽核 (return false)
     */
    public function checkNoDepositNeedsApproval($caseId)
    {
        // 取案件資料
        $stmt = $this->db->prepare("SELECT case_type, deal_amount, total_amount FROM cases WHERE id = ?");
        $stmt->execute(array($caseId));
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) return false;

        $caseType = isset($c['case_type']) ? $c['case_type'] : '';
        // 優先用含稅總金額，若無則用成交金額
        $amount = (float)(!empty($c['total_amount']) ? $c['total_amount'] : (!empty($c['deal_amount']) ? $c['deal_amount'] : 0));

        // 查規則
        $rStmt = $this->db->prepare("SELECT * FROM approval_rules WHERE module = 'no_deposit_schedule' AND is_active = 1");
        $rStmt->execute();
        $rules = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rules)) return false;

        foreach ($rules as $rule) {
            // 案件類型比對
            $ruleTypes = !empty($rule['case_types']) ? array_filter(explode(',', $rule['case_types'])) : array();
            if (!empty($ruleTypes) && !in_array($caseType, $ruleTypes)) continue;

            // 金額範圍比對
            $min = (float)(isset($rule['min_amount']) ? $rule['min_amount'] : 0);
            $max = isset($rule['max_amount']) && $rule['max_amount'] !== null ? (float)$rule['max_amount'] : null;
            if ($amount < $min) continue;
            if ($max !== null && $amount > $max) continue;

            // 符合此規則 → 需簽核
            return true;
        }
        return false;
    }

    /**
     * 送無訂金排工簽核
     */
    public function submitNoDepositSchedule($caseId, $submittedBy = null)
    {
        if (!$submittedBy) $submittedBy = Auth::id();

        // 清除舊的 pending 記錄
        $this->db->prepare("DELETE FROM approval_flows WHERE module = 'no_deposit_schedule' AND target_id = ? AND status = 'pending'")
            ->execute(array($caseId));

        // 取案件資料以便匹配規則
        $stmt = $this->db->prepare("SELECT case_type, deal_amount, total_amount FROM cases WHERE id = ?");
        $stmt->execute(array($caseId));
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) return array('error' => 'case not found');

        $caseType = isset($c['case_type']) ? $c['case_type'] : '';
        $amount = (float)(!empty($c['total_amount']) ? $c['total_amount'] : (!empty($c['deal_amount']) ? $c['deal_amount'] : 0));

        // 找出匹配的規則
        $rStmt = $this->db->prepare("SELECT * FROM approval_rules WHERE module = 'no_deposit_schedule' AND is_active = 1 ORDER BY level_order, id");
        $rStmt->execute();
        $rules = $rStmt->fetchAll(PDO::FETCH_ASSOC);

        $matchedRule = null;
        foreach ($rules as $rule) {
            $ruleTypes = !empty($rule['case_types']) ? array_filter(explode(',', $rule['case_types'])) : array();
            if (!empty($ruleTypes) && !in_array($caseType, $ruleTypes)) continue;
            $min = (float)(isset($rule['min_amount']) ? $rule['min_amount'] : 0);
            $max = isset($rule['max_amount']) && $rule['max_amount'] !== null ? (float)$rule['max_amount'] : null;
            if ($amount < $min) continue;
            if ($max !== null && $amount > $max) continue;
            $matchedRule = $rule;
            break;
        }

        if (!$matchedRule) return array('auto_approved' => true, 'reason' => '不符合任何規則，不需簽核');

        // 找簽核人
        $approverId = $matchedRule['approver_id'];
        if (!$approverId && $matchedRule['approver_role']) {
            $aStmt = $this->db->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1 LIMIT 1");
            $aStmt->execute(array($matchedRule['approver_role']));
            $au = $aStmt->fetch(PDO::FETCH_ASSOC);
            if ($au) $approverId = $au['id'];
        }
        if (!$approverId) return array('error' => '找不到對應簽核人');

        $insStmt = $this->db->prepare("
            INSERT INTO approval_flows (module, target_id, rule_id, level_order, approver_id, status, submitted_by)
            VALUES ('no_deposit_schedule', ?, ?, ?, ?, 'pending', ?)
        ");
        $insStmt->execute(array($caseId, $matchedRule['id'], (int)$matchedRule['level_order'] ?: 1, $approverId, $submittedBy));
        return array('flow_id' => (int)$this->db->lastInsertId(), 'approver_id' => $approverId);
    }

    /**
     * 取得案件無訂金排工簽核狀態
     * @return string|null 'pending'|'approved'|'rejected'|null
     */
    public function getNoDepositApprovalStatus($caseId)
    {
        $stmt = $this->db->prepare("SELECT status FROM approval_flows WHERE module = 'no_deposit_schedule' AND target_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(array($caseId));
        $s = $stmt->fetchColumn();
        return $s ?: null;
    }

    // ===== 完工簽核專用流程 =====

    /**
     * 送完工簽核（只送 level 1 = eng_manager）
     * 若規則含 extra_approver_ids，會為每個簽核人建立一筆 flow（任一簽核即可通過該關）
     * 若無規則則自動建立預設規則
     */
    public function submitCaseCompletion($caseId, $submittedBy = null)
    {
        if (!$submittedBy) $submittedBy = Auth::id();

        // 確保有 case_completion 規則，沒有就自動建立
        $this->ensureCaseCompletionRules();

        // 如果已有 pending 記錄就不重複建立（由呼叫端檢查，這裡做保險）
        $chk = $this->db->prepare("SELECT COUNT(*) FROM approval_flows WHERE module = 'case_completion' AND target_id = ? AND status = 'pending'");
        $chk->execute(array($caseId));
        if ((int)$chk->fetchColumn() > 0) return array('already_pending' => true);

        // 只送 level 1（工程主管）— 依案件分公司優先挑選規則
        $rule = $this->pickCompletionRuleForCase($caseId, 1);

        if (!$rule) return array('auto_approved' => true);

        $approverIds = $this->resolveRuleApproverIds($rule);
        if (empty($approverIds)) return array('auto_approved' => true);

        $insertStmt = $this->db->prepare("
            INSERT INTO approval_flows (module, target_id, rule_id, level_order, approver_id, status, submitted_by)
            VALUES ('case_completion', ?, ?, 1, ?, 'pending', ?)
        ");
        $created = array();
        foreach ($approverIds as $aid) {
            $insertStmt->execute(array($caseId, $rule['id'], $aid, $submittedBy));
            $created[] = array(
                'id' => (int)$this->db->lastInsertId(),
                'approver_id' => $aid,
                'level_order' => 1,
            );
        }
        return $created;
    }

    /**
     * 完工簽核 - 多關推進邏輯（3 關）
     *
     * Level 1 (工程主管) approved → 自動建立 Level 2 (行政人員)
     * Level 2 (行政人員) approved with payload has_payment=true → 自動建立 Level 3 (會計)
     * Level 2 (行政人員) approved with payload has_payment=false → 不建 Level 3，案件 status='unpaid'
     * Level 3 (會計) approved → 案件 status='closed' (但需先檢查 balance_amount=0，否則 throw)
     *
     * @return array
     *   - next_level: int|null 下一關層級（null=已結束）
     *   - status: string  'pending_next' / 'unpaid' / 'closed' / 'no_rule'
     *   - error: string|null  錯誤訊息（例如尾款不為 0）
     */
    public function advanceCaseCompletion($caseId)
    {
        // ---- 1) 檢查 level 1 ----
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM approval_flows WHERE module='case_completion' AND target_id=? AND level_order=1 AND status='pending'");
        $stmt->execute(array($caseId));
        if ((int)$stmt->fetchColumn() > 0) {
            return array('next_level' => 1, 'status' => 'pending_next', 'error' => null);
        }
        $okStmt = $this->db->prepare("SELECT COUNT(*) FROM approval_flows WHERE module='case_completion' AND target_id=? AND level_order=1 AND status='approved'");
        $okStmt->execute(array($caseId));
        if ((int)$okStmt->fetchColumn() === 0) {
            // 沒人核准 level 1 → 不推進
            return array('next_level' => null, 'status' => 'no_rule', 'error' => null);
        }

        // ---- 2) 檢查 level 2 ----
        $stmt2 = $this->db->prepare("SELECT COUNT(*) FROM approval_flows WHERE module='case_completion' AND target_id=? AND level_order=2");
        $stmt2->execute(array($caseId));
        if ((int)$stmt2->fetchColumn() === 0) {
            // 還沒建 level 2 → 建立
            return $this->buildNextCompletionLevel($caseId, 2);
        }
        // level 2 已存在 → 是否還有 pending
        $stmt2p = $this->db->prepare("SELECT COUNT(*) FROM approval_flows WHERE module='case_completion' AND target_id=? AND level_order=2 AND status='pending'");
        $stmt2p->execute(array($caseId));
        if ((int)$stmt2p->fetchColumn() > 0) {
            return array('next_level' => 2, 'status' => 'pending_next', 'error' => null);
        }
        // level 2 已決定 → 讀取 payload 看 has_payment / warranty_service
        $payloadStmt = $this->db->prepare("
            SELECT payload FROM approval_flows
            WHERE module='case_completion' AND target_id=? AND level_order=2 AND status='approved'
            ORDER BY decided_at DESC LIMIT 1
        ");
        $payloadStmt->execute(array($caseId));
        $payloadStr = $payloadStmt->fetchColumn();
        $payload = $payloadStr ? json_decode($payloadStr, true) : array();
        if (!is_array($payload)) $payload = array();
        $hasPayment      = !empty($payload['has_payment']);
        $warrantyService = !empty($payload['warranty_service']);

        // 舊客戶維修案 保固/做服務：不勾「有收款」+ 勾「保固服務」→ 跳過第 3 關，直接結案（仍須尾款 = 0）
        if (!$hasPayment && $warrantyService) {
            $bal = $this->db->prepare("SELECT GREATEST(COALESCE(CASE WHEN total_amount > 0 THEN total_amount ELSE deal_amount END, 0) - COALESCE(total_collected, 0), 0) AS real_balance FROM cases WHERE id = ?");
            $bal->execute(array($caseId));
            $balance = (int)$bal->fetchColumn();
            if ($balance > 0) {
                return array(
                    'next_level' => null,
                    'status' => 'closed_blocked',
                    'error' => '尾款還有 $' . number_format($balance) . '，無法結案。請先補登收款或折讓金額。',
                );
            }
            return array('next_level' => null, 'status' => 'closed', 'error' => null);
        }

        if (!$hasPayment) {
            // 無收款（且未走保固直結）→ 案件 status='unpaid'，流程結束
            return array('next_level' => null, 'status' => 'unpaid', 'error' => null);
        }

        // ---- 3) 檢查 level 3 ----
        // 只算 pending/approved 才視為「該關仍在進行中」；rejected/cancelled 視為作廢，
        // 讓第3關駁回退回第2關後，行政人員再次核准能自動重建第3關 pending。
        $stmt3 = $this->db->prepare("SELECT COUNT(*) FROM approval_flows WHERE module='case_completion' AND target_id=? AND level_order=3 AND status IN ('pending','approved')");
        $stmt3->execute(array($caseId));
        if ((int)$stmt3->fetchColumn() === 0) {
            // 還沒建 level 3 → 建立
            return $this->buildNextCompletionLevel($caseId, 3);
        }
        $stmt3p = $this->db->prepare("SELECT COUNT(*) FROM approval_flows WHERE module='case_completion' AND target_id=? AND level_order=3 AND status='pending'");
        $stmt3p->execute(array($caseId));
        if ((int)$stmt3p->fetchColumn() > 0) {
            return array('next_level' => 3, 'status' => 'pending_next', 'error' => null);
        }
        // level 3 已決定 → 檢查 balance_amount
        $okStmt3 = $this->db->prepare("SELECT COUNT(*) FROM approval_flows WHERE module='case_completion' AND target_id=? AND level_order=3 AND status='approved'");
        $okStmt3->execute(array($caseId));
        if ((int)$okStmt3->fetchColumn() === 0) {
            return array('next_level' => null, 'status' => 'no_rule', 'error' => null);
        }
        // 強制檢查尾款（即時計算：成交金額或含稅金額 - 已收款）
        $bal = $this->db->prepare("SELECT GREATEST(COALESCE(CASE WHEN total_amount > 0 THEN total_amount ELSE deal_amount END, 0) - COALESCE(total_collected, 0), 0) AS real_balance FROM cases WHERE id = ?");
        $bal->execute(array($caseId));
        $balance = (int)$bal->fetchColumn();
        if ($balance > 0) {
            return array(
                'next_level' => null,
                'status' => 'closed_blocked',
                'error' => '尾款還有 $' . number_format($balance) . '，無法結案。請先補登收款或折讓金額。',
            );
        }
        return array('next_level' => null, 'status' => 'closed', 'error' => null);
    }

    /**
     * 建立完工簽核的下一關（由 advanceCaseCompletion 呼叫）
     */
    private function buildNextCompletionLevel($caseId, $level)
    {
        $ruleRow = $this->pickCompletionRuleForCase($caseId, $level);
        if (!$ruleRow) {
            return array('next_level' => null, 'status' => 'no_rule', 'error' => null);
        }
        $approverIds = $this->resolveRuleApproverIds($ruleRow);
        if (empty($approverIds)) {
            return array('next_level' => null, 'status' => 'no_rule', 'error' => null);
        }
        // 取得原始送簽人
        $orig = $this->db->prepare("SELECT submitted_by FROM approval_flows WHERE module='case_completion' AND target_id=? AND level_order=1 ORDER BY id LIMIT 1");
        $orig->execute(array($caseId));
        $submittedBy = $orig->fetchColumn() ?: 0;
        $insertStmt = $this->db->prepare("
            INSERT INTO approval_flows (module, target_id, rule_id, level_order, approver_id, status, submitted_by)
            VALUES ('case_completion', ?, ?, ?, ?, 'pending', ?)
        ");
        foreach ($approverIds as $aid) {
            $insertStmt->execute(array($caseId, $ruleRow['id'], $level, $aid, $submittedBy));
        }
        return array('next_level' => $level, 'status' => 'pending_next', 'error' => null);
    }

    /**
     * 寫入 approval_flows.payload (JSON)
     */
    public function setFlowPayload($flowId, $payload)
    {
        $json = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : (string)$payload;
        try {
            $this->db->prepare("UPDATE approval_flows SET payload = ? WHERE id = ?")
                     ->execute(array($json, $flowId));
        } catch (Exception $e) {
            // payload 欄位可能不存在 (migration 未跑) - 靜默忽略
            error_log('setFlowPayload error: ' . $e->getMessage());
        }
    }

    /**
     * 讀取 approval_flows.payload (JSON)
     */
    public function getFlowPayload($flowId)
    {
        try {
            $stmt = $this->db->prepare("SELECT payload FROM approval_flows WHERE id = ?");
            $stmt->execute(array($flowId));
            $row = $stmt->fetchColumn();
            if (!$row) return array();
            $decoded = json_decode($row, true);
            return is_array($decoded) ? $decoded : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 取得案件完工簽核的完整 timeline (3 關所有 flows)
     */
    public function getCaseCompletionTimeline($caseId)
    {
        $stmt = $this->db->prepare("
            SELECT af.*, u.real_name AS approver_name, su.real_name AS submitter_name
            FROM approval_flows af
            LEFT JOIN users u ON af.approver_id = u.id
            LEFT JOIN users su ON af.submitted_by = su.id
            WHERE af.module = 'case_completion' AND af.target_id = ?
            ORDER BY af.level_order, af.id
        ");
        $stmt->execute(array($caseId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 簽核歷史紀錄（所有模組通用，最多 200 筆）
     * 篩選：module, status (pending/approved/rejected/cancelled), date_from, date_to, keyword
     */
    public function getApprovalHistory($filters = array())
    {
        $where = array('1=1');
        $params = array();

        if (!empty($filters['module'])) {
            $where[] = 'af.module = ?';
            $params[] = $filters['module'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'af.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['approver_id'])) {
            $where[] = 'af.approver_id = ?';
            $params[] = (int)$filters['approver_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(af.submitted_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(af.submitted_at) <= ?';
            $params[] = $filters['date_to'];
        }

        $keywordJoin = '';
        if (!empty($filters['keyword'])) {
            // 多模組關鍵字搜尋（cases / quotations / leaves 等）
            $where[] = '(
                EXISTS(SELECT 1 FROM cases c WHERE c.id = af.target_id AND af.module IN (\'case_completion\',\'quotation\') AND (c.case_number LIKE ? OR c.customer_name LIKE ? OR c.title LIKE ?))
                OR EXISTS(SELECT 1 FROM quotations q WHERE q.id = af.target_id AND af.module = \'quotation\' AND (q.quote_number LIKE ? OR q.customer_name LIKE ?))
            )';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw;
            $params[] = $kw; $params[] = $kw;
        }

        $sql = "
            SELECT af.*, u.real_name AS approver_name, su.real_name AS submitter_name
            FROM approval_flows af
            LEFT JOIN users u ON af.approver_id = u.id
            LEFT JOIN users su ON af.submitted_by = su.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY af.id DESC
            LIMIT 200
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 補上 target_info（從 cases / quotations 取單號和標題）
        $caseIds = array();
        $quoteIds = array();
        foreach ($rows as $r) {
            if ($r['module'] === 'case_completion') $caseIds[] = (int)$r['target_id'];
            elseif ($r['module'] === 'quotation') $quoteIds[] = (int)$r['target_id'];
        }
        $caseMap = array();
        if (!empty($caseIds)) {
            $ph = implode(',', array_fill(0, count($caseIds), '?'));
            $cs = $this->db->prepare("SELECT c.id, c.case_number, c.customer_name, c.title, b.name AS branch_name FROM cases c LEFT JOIN branches b ON c.branch_id = b.id WHERE c.id IN ($ph)");
            $cs->execute($caseIds);
            foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c) $caseMap[$c['id']] = $c;
        }
        $quoteMap = array();
        if (!empty($quoteIds)) {
            $ph = implode(',', array_fill(0, count($quoteIds), '?'));
            $qs = $this->db->prepare("SELECT id, quote_number, customer_name FROM quotations WHERE id IN ($ph)");
            $qs->execute($quoteIds);
            foreach ($qs->fetchAll(PDO::FETCH_ASSOC) as $q) $quoteMap[$q['id']] = $q;
        }

        foreach ($rows as &$r) {
            $tid = (int)$r['target_id'];
            if ($r['module'] === 'case_completion' && isset($caseMap[$tid])) {
                $r['target_number'] = $caseMap[$tid]['case_number'];
                $r['target_title'] = $caseMap[$tid]['customer_name'] ?: $caseMap[$tid]['title'];
                $r['target_url'] = '/cases.php?action=edit&id=' . $tid . '#sec-billing';
                $r['branch_name'] = $caseMap[$tid]['branch_name'];
            } elseif ($r['module'] === 'quotation' && isset($quoteMap[$tid])) {
                $r['target_number'] = $quoteMap[$tid]['quote_number'];
                $r['target_title'] = $quoteMap[$tid]['customer_name'];
                $r['target_url'] = '/quotations.php?action=edit&id=' . $tid;
                $r['branch_name'] = '';
            } else {
                $r['target_number'] = '#' . $tid;
                $r['target_title'] = '';
                $r['target_url'] = '';
                $r['branch_name'] = '';
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * 取得目前使用者該案件待簽核的 flow（如果有）
     */
    public function getMyPendingCompletionFlow($caseId, $userId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM approval_flows
            WHERE module='case_completion' AND target_id=? AND approver_id=? AND status='pending'
            ORDER BY level_order DESC LIMIT 1
        ");
        $stmt->execute(array($caseId, $userId));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 確保 case_completion 三條規則都存在（Level 1/2/3）
     */
    /**
     * 依案件所屬分公司挑選完工簽核規則
     * 優先順序：有設定 condition_branch_ids 且命中案件 branch_id > 未設定（通用規則）
     */
    private function pickCompletionRuleForCase($caseId, $level)
    {
        $bStmt = $this->db->prepare("SELECT branch_id FROM cases WHERE id = ?");
        $bStmt->execute(array($caseId));
        $branchId = (int)$bStmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT * FROM approval_rules
            WHERE module='case_completion' AND is_active=1 AND level_order=?
            ORDER BY id
        ");
        $stmt->execute(array($level));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) return null;

        $branchMatched = null;
        $generic = null;
        foreach ($rows as $row) {
            $cond = isset($row['condition_branch_ids']) ? trim((string)$row['condition_branch_ids']) : '';
            if ($cond === '') {
                if ($generic === null) $generic = $row;
            } else {
                $ids = array_map('intval', array_filter(explode(',', $cond)));
                if ($branchId > 0 && in_array($branchId, $ids, true)) {
                    if ($branchMatched === null) $branchMatched = $row;
                }
            }
        }
        if ($branchMatched) return $branchMatched;
        if ($generic) return $generic;
        return $rows[0];
    }

    private function ensureCaseCompletionRules()
    {
        $defaults = array(
            1 => array('name' => '完工簽核 - Level 1 工程主管', 'role' => 'eng_manager'),
            2 => array('name' => '完工簽核 - Level 2 行政人員（勾選有無收款）', 'role' => 'admin_staff'),
            3 => array('name' => '完工簽核 - Level 3 會計人員（確認入帳）', 'role' => 'accountant'),
        );
        foreach ($defaults as $level => $info) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM approval_rules WHERE module='case_completion' AND level_order=? AND is_active=1");
            $stmt->execute(array($level));
            if ((int)$stmt->fetchColumn() > 0) continue;
            $this->db->prepare("
                INSERT INTO approval_rules (module, rule_name, min_amount, max_amount, min_profit_rate, approver_role, approver_id, level_order, is_active)
                VALUES ('case_completion', ?, 0, NULL, NULL, ?, NULL, ?, 1)
            ")->execute(array($info['name'], $info['role'], $level));
        }
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

        // 案件進度改回 in_progress 並清掉結案鎖（避免 status 倒退但 is_locked 殘留）
        $this->db->prepare("UPDATE cases SET status = 'in_progress', is_locked = 0, locked_by = NULL, locked_at = NULL, unlocked_at = NULL, unlocked_by = NULL WHERE id = ?")->execute(array($caseId));
    }

    /**
     * 完工簽核 - 第3關駁回時退回第2關（不重置整個流程）
     *
     * 駁回的 flow 已由 reject() 標 'rejected'。本方法處理：
     * 1) 取消其他第3關 pending（多人擇一情境）
     * 2) 第2關最近一筆 approved 還原成 pending，讓行政人員重新確認
     *
     * 不改 case.status / is_locked / is_completed（仍在簽核流程中，只是退一關）。
     *
     * @return int|null 還原為 pending 的第2關 flow id（給呼叫端發通知用）
     */
    public function retreatCaseCompletionFromLevel3($caseId)
    {
        // 取消其餘第3關 pending（保留剛被 reject() 標 rejected 的駁回紀錄）
        $this->db->prepare("
            UPDATE approval_flows SET status = 'cancelled', decided_at = NOW()
            WHERE module = 'case_completion' AND target_id = ? AND level_order = 3 AND status = 'pending'
        ")->execute(array($caseId));

        // 第2關最近一筆 approved 還原為 pending
        $latestL2 = $this->db->prepare("
            SELECT id FROM approval_flows
            WHERE module = 'case_completion' AND target_id = ? AND level_order = 2 AND status = 'approved'
            ORDER BY decided_at DESC LIMIT 1
        ");
        $latestL2->execute(array($caseId));
        $l2Id = $latestL2->fetchColumn();
        if (!$l2Id) return null;
        $this->db->prepare("
            UPDATE approval_flows SET status = 'pending', decided_at = NULL
            WHERE id = ?
        ")->execute(array($l2Id));
        return (int)$l2Id;
    }
}
