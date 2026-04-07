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

        if (!empty($data['id'])) {
            $stmt = $this->db->prepare("
                UPDATE approval_rules SET
                    module = ?, rule_name = ?, min_amount = ?, max_amount = ?,
                    min_profit_rate = ?, condition_type = ?, product_ids = ?, product_category_id = ?,
                    case_types = ?,
                    approver_role = ?, approver_id = ?, extra_approver_ids = ?,
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
                $caseTypesStr,
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
                    min_profit_rate, condition_type, product_ids, product_category_id,
                    case_types, approver_role, approver_id, extra_approver_ids, level_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $caseTypesStr,
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
            WHERE is_active = 1 AND role IN ('boss','sales_manager','eng_manager','eng_deputy','admin_staff','accountant')
            ORDER BY FIELD(role,'boss','sales_manager','eng_manager','eng_deputy','admin_staff','accountant'), real_name
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
     * 完工簽核 - 工程主管核准後推進到 level 2（會計）
     * 多人擇一簽核：當該 level 至少一筆 approved 且無 pending 時，視為通過該關
     * @return bool true=還有下一關待簽, false=全部簽完
     */
    public function advanceCaseCompletion($caseId)
    {
        // 檢查 level 1 是否還有 pending（多人擇一：只要無 pending 就視為已決定）
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM approval_flows
            WHERE module = 'case_completion' AND target_id = ? AND level_order = 1 AND status = 'pending'
        ");
        $stmt->execute(array($caseId));
        if ((int)$stmt->fetchColumn() > 0) return true; // level 1 還有人待簽

        // 確認 level 1 至少有一筆 approved（避免全部 cancelled 也推進）
        $okStmt = $this->db->prepare("
            SELECT COUNT(*) FROM approval_flows
            WHERE module = 'case_completion' AND target_id = ? AND level_order = 1 AND status = 'approved'
        ");
        $okStmt->execute(array($caseId));
        if ((int)$okStmt->fetchColumn() === 0) return false; // 沒人核准就不推進

        // 檢查 level 2 是否已建立
        $stmt2 = $this->db->prepare("
            SELECT COUNT(*) FROM approval_flows
            WHERE module = 'case_completion' AND target_id = ? AND level_order = 2
        ");
        $stmt2->execute(array($caseId));
        if ((int)$stmt2->fetchColumn() > 0) {
            // level 2 已存在 → 同樣以 pending 判斷
            $stmt3 = $this->db->prepare("
                SELECT COUNT(*) FROM approval_flows
                WHERE module = 'case_completion' AND target_id = ? AND level_order = 2 AND status = 'pending'
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

        $approverIds = $this->resolveRuleApproverIds($rule2);
        if (empty($approverIds)) return false; // 找不到簽核人 → 直接結案

        // 取得原始送簽人
        $origStmt = $this->db->prepare("
            SELECT submitted_by FROM approval_flows
            WHERE module = 'case_completion' AND target_id = ? AND level_order = 1
            ORDER BY id LIMIT 1
        ");
        $origStmt->execute(array($caseId));
        $submittedBy = $origStmt->fetchColumn() ?: 0;

        $insertStmt = $this->db->prepare("
            INSERT INTO approval_flows (module, target_id, rule_id, level_order, approver_id, status, submitted_by)
            VALUES ('case_completion', ?, ?, 2, ?, 'pending', ?)
        ");
        foreach ($approverIds as $aid) {
            $insertStmt->execute(array($caseId, $rule2['id'], $aid, $submittedBy));
        }

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
