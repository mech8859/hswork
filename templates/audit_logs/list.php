<?php
$actionLabels = array(
    // 基本
    'login' => '登入', 'logout' => '登出',
    'create' => '新增', 'update' => '編輯', 'delete' => '刪除',
    'view' => '查看', 'export' => '匯出', 'import' => '匯入',
    'status' => '狀態變更',
    'lock' => '上鎖', 'unlock' => '解鎖',
    'cancel' => '取消', 'cancel_reserve' => '取消預約',
    'complete' => '完成', 'confirm' => '確認', 'unconfirm' => '取消確認',
    'void' => '作廢', 'ship' => '出貨', 'prepare' => '備料', 'reserve' => '預約',
    'link_to_case' => '連結案件', 'toggle_star' => '切換星標',
    // 簽核
    'submit_approval' => '送簽核',
    'submit_no_deposit_approval' => '送無訂金簽核',
    'resubmit_approval' => '重送簽核',
    'reset_pending' => '重置待簽',
    'approve' => '核准', 'auto_approve' => '自動核准',
    'reject' => '退回',
    'request_revision' => '要求變更',
    'revision_approved' => '變更核准',
    'revision_auto_approved' => '變更自動核准',
    'revision_rejected' => '變更退回',
    'revision_to_draft' => '變更退草稿',
    // 配對 / 對帳
    'confirm_match' => '確認配對',
    'batch_match' => '批次配對',
    'batch_lock_clean' => '批次解鎖清除',
    'bulk_fix_stuck_reject' => '批次修退回卡單',
    'bulk_rebind' => '批次重綁',
    'bulk_update' => '批次更新',
    'manual_fix' => '手動修復',
    'generate_payment' => '產生付款',
    // 帳號管理
    'change_password' => '修改密碼',
    'force_change_password' => '強制改密碼',
    'sync_permissions' => '同步權限',
    'admin_delete' => '管理員刪除',
    'admin_edit_basic' => '管理員編輯',
    'auto_disable' => '自動停用',
    'auto_status' => '自動更新狀態',
    // 報價/品項
    'edit_items' => '編輯品項',
    'update_date' => '更新日期',
    'update_item_note' => '更新品項備註',
    'update_support_branches' => '更新協辦分公司',
    // 規則 / 系統
    'rule_create' => '建規則',
    'rule_fix' => '修規則',
    'migration' => '資料遷移',
);
$actionColors = array(
    'login' => '#4CAF50', 'logout' => '#9E9E9E',
    'create' => '#2196F3', 'update' => '#FF9800', 'delete' => '#f44336',
    'approve' => '#16a34a', 'auto_approve' => '#16a34a', 'revision_approved' => '#16a34a', 'revision_auto_approved' => '#16a34a',
    'reject' => '#dc2626', 'revision_rejected' => '#dc2626',
    'submit_approval' => '#0ea5e9', 'submit_no_deposit_approval' => '#0ea5e9', 'resubmit_approval' => '#0ea5e9',
    'request_revision' => '#f59e0b', 'revision_to_draft' => '#f59e0b',
    'lock' => '#6b7280', 'unlock' => '#a855f7',
    'cancel' => '#ef4444', 'void' => '#ef4444',
    'confirm' => '#16a34a', 'unconfirm' => '#9E9E9E', 'confirm_match' => '#16a34a',
    'complete' => '#16a34a',
);
$moduleLabels = array(
    'auth' => '系統登入', 'cases' => '案件管理', 'schedule' => '工程行事曆',
    'staff' => '人員管理', 'customers' => '客戶管理', 'quotations' => '報價管理',
    'repairs' => '維修單', 'worklog' => '施工回報', 'leaves' => '請假管理',
    'receivables' => '應收帳款', 'receipts' => '收款單', 'payables' => '應付帳款',
    'payments_out' => '付款單', 'bank_transactions' => '銀行明細',
    'inventory' => '庫存管理', 'vehicles' => '車輛管理', 'products' => '產品目錄',
);
$fieldLabels = array(
    // 案件
    'title' => '案件名稱', 'case_type' => '案別', 'status' => '狀態', 'sub_status' => '進度狀態',
    'customer_name' => '客戶名稱', 'customer_id' => '客戶ID', 'contact_person' => '聯絡人',
    'address' => '施工地址', 'description' => '客戶需求', 'system_type' => '系統別',
    'difficulty' => '難易度', 'estimated_hours' => '預估工時', 'notes' => '備註',
    'sales_id' => '承辦業務', 'branch_id' => '據點', 'urgency' => '急迫性',
    'total_visits' => '預估施工次數', 'max_engineers' => '最多施工人數',
    'planned_start_date' => '預計施工日', 'planned_end_date' => '預計完工日',
    // 帳務
    'quote_amount' => '報價金額', 'deal_amount' => '成交金額', 'total_amount' => '含稅金額',
    'tax_amount' => '稅金', 'deposit_amount' => '訂金', 'balance_amount' => '尾款',
    'is_tax_included' => '含稅', 'deposit_method' => '訂金方式',
    // 報價單
    'quotation_number' => '報價單號', 'format' => '報價格式', 'quote_date' => '報價日期',
    'valid_date' => '有效日期', 'valid_until' => '有效日期', 'subtotal' => '未稅合計',
    'payment_terms' => '收款條件', 'invoice_title' => '發票抬頭', 'invoice_tax_id' => '統編',
    'contact_phone' => '連絡電話', 'site_name' => '案場名稱', 'site_address' => '施工地址',
    'hide_model_on_print' => '隱藏型號',
    // 人員
    'real_name' => '姓名', 'role' => '角色', 'email' => 'Email', 'phone' => '電話',
    'mobile' => '手機', 'is_active' => '啟用狀態', 'username' => '帳號',
    'is_engineer' => '工程師', 'is_mobile' => '手機介面',
    'can_view_all_branches' => '可查看全部分公司', 'custom_permissions' => '自訂權限',
    // 財務
    'entry_date' => '登記日期', 'expense_date' => '支出日期', 'transaction_date' => '交易日期',
    'register_date' => '登記日期', 'has_invoice' => '有無發票', 'invoice_info' => '發票資訊',
    'expense_amount' => '支出金額', 'income_amount' => '收入金額', 'registrar' => '登記人',
    'entry_number' => '編號', 'sales_name' => '承辦業務', 'type' => '收支別',
    'upload_number' => '上傳編號', 'approval_date' => '簽核日期', 'user_name' => '使用者',
    // 客戶
    'name' => '名稱', 'category' => '分類', 'tax_id' => '統編', 'fax' => '傳真',
    // 維修單
    'repair_date' => '維修日期', 'engineer_id' => '工程師', 'repair_status' => '維修狀態',
    'customer_phone' => '客戶電話', 'customer_address' => '客戶地址',
    // 請假
    'leave_type' => '假別', 'start_date' => '起始日', 'end_date' => '結束日', 'reason' => '事由',
    // 產品
    'price' => '售價', 'cost' => '成本', 'unit' => '單位', 'model' => '型號',
    // 庫存
    'quantity' => '數量', 'warehouse_id' => '倉庫',
    // 簽核
    'approval_status' => '簽核狀態', 'approved_by' => '核准人', 'rejected_reason' => '駁回原因',
    'revision_note' => '修改說明', 'profit_margin' => '利潤率',
);

// 值翻譯對照表（狀態、布林、角色等列舉值）
$valueLabels = array(
    // 報價單狀態
    'draft' => '草稿', 'pending_approval' => '待簽核', 'approved' => '已核准',
    'rejected_internal' => '內部駁回', 'revision_needed' => '需修改',
    'sent' => '已送客戶', 'accepted' => '已接受', 'rejected' => '已拒絕',
    'expired' => '已過期',
    // 案件狀態
    'pending' => '待追蹤', 'in_progress' => '未完工', 'completed' => '已完工結案',
    'cancelled' => '客戶取消', 'scheduled' => '已排工', 'active' => '進行中',
    'closed' => '已結案',
    // 案件進度
    'tracking' => '待追蹤', 'incomplete' => '未完工', 'unpaid' => '未收款',
    'lost' => '失敗', 'maint_case' => '轉維護', 'breach' => '違約',
    'awaiting_dispatch' => '待派工', 'customer_cancel' => '客戶取消',
    // 維修單狀態
    'invoiced' => '已開票',
    // 請假狀態
    'pending_leave' => '待審核',
    // 簽核狀態
    'pending_review' => '待審核',
    // 布林值
    '1' => '是', '0' => '否',
    // 報價格式
    'standard' => '標準', 'detailed' => '詳細', 'simple' => '簡易',
    // 角色
    'admin' => '管理員', 'manager' => '主管', 'engineer' => '工程師',
    'sales' => '業務', 'accounting' => '會計',
    // 難易度
    'easy' => '簡單', 'normal' => '普通', 'hard' => '困難',
    // 急迫性
    'low' => '低', 'medium' => '中', 'high' => '高', 'urgent' => '急件',
);

/**
 * 翻譯欄位值：若在 valueLabels 中有對應則翻譯，否則原樣顯示
 */
function translateAuditValue($value, $field, $valueLabels)
{
    if ($value === null || $value === '') {
        return '';
    }
    $val = (string)$value;
    if (isset($valueLabels[$val])) {
        return $valueLabels[$val];
    }
    return $val;
}
?>

<div class="d-flex justify-between align-center mb-2">
    <h2>操作日誌</h2>
</div>

<!-- 線上用戶 -->
<?php if (!empty($onlineUsers)): ?>
<div class="card mb-2" style="padding:12px">
    <div style="font-weight:600;margin-bottom:8px">🟢 目前線上 (<?= count($onlineUsers) ?> 人)</div>
    <div class="d-flex gap-1 flex-wrap">
        <?php foreach ($onlineUsers as $ou): ?>
        <span class="badge" style="background:#e8f5e9;color:#2e7d32;padding:6px 12px;font-size:.85rem">
            <?= e($ou['real_name']) ?>
            <span style="font-size:.75rem;opacity:.7"><?= e(basename($ou['last_active_page'] ?? '')) ?></span>
        </span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 篩選 -->
<div class="card mb-2" style="padding:12px">
    <form method="GET" class="d-flex gap-1 flex-wrap align-center">
        <select name="user_id" class="form-control" style="max-width:150px">
            <option value="">全部人員</option>
            <?php foreach ($allUsers as $u): ?>
            <option value="<?= $u['id'] ?>" <?= ($filters['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['real_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="module" class="form-control" style="max-width:150px">
            <option value="">全部模組</option>
            <?php foreach ($moduleLabels as $mk => $ml): ?>
            <option value="<?= $mk ?>" <?= ($filters['module'] ?? '') === $mk ? 'selected' : '' ?>><?= $ml ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_action" class="form-control" style="max-width:120px">
            <option value="">全部動作</option>
            <?php foreach ($actionLabels as $ak => $al): ?>
            <option value="<?= $ak ?>" <?= ($filters['filter_action'] ?? '') === $ak ? 'selected' : '' ?>><?= $al ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" max="2099-12-31" name="date_from" class="form-control" style="max-width:150px" value="<?= e($filters['date_from'] ?? '') ?>">
        <span>~</span>
        <input type="date" max="2099-12-31" name="date_to" class="form-control" style="max-width:150px" value="<?= e($filters['date_to'] ?? '') ?>">
        <input type="text" name="keyword" class="form-control" style="max-width:150px" placeholder="關鍵字..." value="<?= e($filters['keyword'] ?? '') ?>">
        <button class="btn btn-primary" style="font-size:.85rem">搜尋</button>
        <a href="/audit_logs.php" class="btn btn-outline" style="font-size:.85rem">清除</a>
    </form>
</div>

<!-- 日誌列表 -->
<div class="card">
    <div style="font-size:.85rem;color:var(--gray-500);padding:4px 0">共 <?= number_format($result['total']) ?> 筆</div>
    <div class="table-responsive">
        <table class="table" style="font-size:.85rem">
            <thead>
                <tr><th style="width:140px">時間</th><th style="width:80px">人員</th><th style="width:100px">模組</th><th style="width:60px">動作</th><th>對象</th><th>變更內容</th><th style="width:100px">IP</th></tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                <tr><td colspan="7" class="text-center text-muted">無紀錄</td></tr>
                <?php else: ?>
                <?php foreach ($result['data'] as $log): ?>
                <tr>
                    <td style="white-space:nowrap"><?= e(substr($log['created_at'], 0, 16)) ?></td>
                    <td><strong><?= e($log['user_name']) ?></strong></td>
                    <td><?= isset($moduleLabels[$log['module']]) ? $moduleLabels[$log['module']] : e($log['module']) ?></td>
                    <td>
                        <span class="badge" style="background:<?= $actionColors[$log['action']] ?? '#9E9E9E' ?>;color:#fff">
                            <?= $actionLabels[$log['action']] ?? e($log['action']) ?>
                        </span>
                    </td>
                    <td><?= e($log['target_title'] ?: '-') ?></td>
                    <td style="max-width:300px">
                        <?php if ($log['changes']):
                            $changes = json_decode($log['changes'], true);
                            if (is_array($changes)):
                                foreach (array_slice($changes, 0, 3) as $field => $ch): ?>
                                    <div style="font-size:.8rem">
                                        <span class="text-muted"><?= isset($fieldLabels[$field]) ? $fieldLabels[$field] : e($field) ?>:</span>
                                        <span style="color:#c62828;text-decoration:line-through"><?= e(translateAuditValue(is_array($ch) ? ($ch['from'] ?? '') : '', $field, $valueLabels)) ?></span>
                                        → <span style="color:#2e7d32"><?= e(translateAuditValue(is_array($ch) ? ($ch['to'] ?? '') : $ch, $field, $valueLabels)) ?></span>
                                    </div>
                                <?php endforeach;
                                if (count($changes) > 3): ?>
                                    <span class="text-muted" style="font-size:.75rem">+<?= count($changes) - 3 ?> 更多</span>
                                <?php endif;
                            endif;
                        endif; ?>
                    </td>
                    <td style="font-size:.75rem;color:var(--gray-400)"><?= e($log['ip_address'] ?: '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 分頁 -->
    <?php
    $totalPages = ceil($result['total'] / $result['perPage']);
    if ($totalPages > 1):
    ?>
    <div class="d-flex justify-center gap-1 mt-2">
        <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
        <a href="?<?= http_build_query(array_merge($filters, array('page' => $p))) ?>"
           class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
