<style>
@media print {
    .no-print { display: none !important; }
    body { font-size: 10px; margin: 0; padding: 0; }
    .card { box-shadow: none !important; border: 1px solid #ddd; padding: 4px !important; }
    .sidebar, .top-nav { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    h2 { font-size: 14px !important; margin: 4px 0 !important; }
    /* 手機卡片隱藏，桌面表格顯示 */
    .show-mobile { display: none !important; }
    .hide-mobile { display: block !important; }
    table { page-break-inside: auto; font-size: 10px; width: 100%; }
    table td, table th { padding: 2px 4px !important; line-height: 1.3; }
    tr { page-break-inside: avoid; }
    thead { display: table-header-group; }
    /* input 在列印時隱藏 */
    table input.form-control { border: none !important; background: none !important; box-shadow: none !important; padding: 0 !important; font-size: 10px; width: auto !important; display: inline !important; }
    table textarea.form-control { border: none !important; background: none !important; box-shadow: none !important; padding: 0 !important; font-size: 10px; height: auto !important; }
}
</style>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>盤點 - <?= e($stocktake['stocktake_number']) ?></h2>
    <div class="d-flex gap-1 align-center">
        <?php
            $statusMap = array('盤點中' => 'badge-warning', '待簽核' => 'badge-primary', '已完成' => 'badge-success', '已取消' => 'badge-muted');
            $statusClass = isset($statusMap[$stocktake['status']]) ? $statusMap[$stocktake['status']] : 'badge-muted';
        ?>
        <span class="badge <?= $statusClass ?>" style="font-size:.85rem"><?= e($stocktake['status']) ?></span>
        <button type="button" class="btn btn-outline btn-sm no-print" onclick="window.print()">列印</button>
        <?= back_button('/inventory.php') ?>
    </div>
</div>

<div class="card mb-2">
    <div class="product-info-grid">
        <div class="product-info-item">
            <span class="product-info-label">倉庫</span>
            <span><?= e(!empty($stocktake['warehouse_name']) ? $stocktake['warehouse_name'] : '-') ?></span>
        </div>
        <div class="product-info-item">
            <span class="product-info-label">日期</span>
            <span><?= e($stocktake['stocktake_date']) ?></span>
        </div>
        <div class="product-info-item">
            <span class="product-info-label">盤點人</span>
            <span><?= e(!empty($stocktake['stocktaker_name']) ? $stocktake['stocktaker_name'] : '-') ?></span>
        </div>
        <div class="product-info-item">
            <span class="product-info-label">品項數</span>
            <span><?= count($stocktakeItems) ?></span>
        </div>
        <?php if (!empty($stocktake['note'])): ?>
        <div class="product-info-item">
            <span class="product-info-label">備註</span>
            <span><?= e($stocktake['note']) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $isEditable = ($stocktake['status'] === '盤點中' && $canManage); ?>

<div class="card">
    <?php if ($isEditable): ?>
    <form method="POST" action="/inventory.php?action=stocktake_edit&id=<?= e($stocktake['id']) ?>" id="stocktakeForm">
        <?= csrf_field() ?>
        <input type="hidden" name="post_action" id="postAction" value="save">
    <?php endif; ?>

    <?php if (empty($stocktakeItems)): ?>
        <p class="text-muted text-center mt-2">此倉庫目前無庫存品項</p>
    <?php else: ?>

    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($stocktakeItems as $item): ?>
        <?php
            $hasDiff = ($item['actual_qty'] !== null && (int)$item['diff_qty'] !== 0);
            $borderColor = $hasDiff ? 'var(--danger)' : 'var(--gray-200)';
        ?>
        <div class="staff-card" style="border-left:3px solid <?= $borderColor ?>">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($item['product_name']) ? $item['product_name'] : '-') ?></strong>
            </div>
            <?php if (!empty($item['product_model'])): ?>
            <div style="font-size:.85rem;color:var(--gray-500)"><?= e($item['product_model']) ?></div>
            <?php endif; ?>
            <div class="staff-card-meta" style="flex-wrap:wrap;margin-top:8px">
                <span>系統 <strong><?= (int)$item['system_qty'] ?></strong></span>
                <?php if ($isEditable): ?>
                <span style="display:flex;align-items:center;gap:4px">
                    實際
                    <input type="number" name="items[<?= e($item['id']) ?>][actual_qty]" value="<?= ($item['actual_qty'] !== null) ? (int)$item['actual_qty'] : '' ?>" class="form-control" style="width:70px;padding:4px 6px" min="0">
                    <input type="hidden" name="items[<?= e($item['id']) ?>][system_qty]" value="<?= (int)$item['system_qty'] ?>">
                </span>
                <?php else: ?>
                <span>實際 <strong><?= ($item['actual_qty'] !== null) ? (int)$item['actual_qty'] : '-' ?></strong></span>
                <?php endif; ?>
                <?php if ($item['actual_qty'] !== null): ?>
                <span style="color:<?= ((int)$item['diff_qty'] !== 0) ? 'var(--danger)' : 'var(--success)' ?>;font-weight:600">
                    差異 <?= ((int)$item['diff_qty'] > 0 ? '+' : '') . (int)$item['diff_qty'] ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>商品名稱</th>
                    <th>型號</th>
                    <th>單位</th>
                    <th class="text-right">系統數量</th>
                    <th class="text-right">實際數量</th>
                    <th class="text-right">差異</th>
                    <th>備註</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stocktakeItems as $item): ?>
                <?php $hasDiff = ($item['actual_qty'] !== null && (int)$item['diff_qty'] !== 0); ?>
                <tr style="<?= $hasDiff ? 'background:#fff5f5' : '' ?>">
                    <td style="font-weight:600"><?= e(!empty($item['product_name']) ? $item['product_name'] : '-') ?></td>
                    <td style="font-size:.85rem"><?= e(!empty($item['product_model']) ? $item['product_model'] : '-') ?></td>
                    <td><?= e(!empty($item['unit']) ? $item['unit'] : '-') ?></td>
                    <td class="text-right"><?= (int)$item['system_qty'] ?></td>
                    <td class="text-right">
                        <?php if ($isEditable): ?>
                        <input type="number" name="items[<?= e($item['id']) ?>][actual_qty]" value="<?= ($item['actual_qty'] !== null) ? (int)$item['actual_qty'] : '' ?>" class="form-control" style="width:80px;text-align:right;padding:4px 6px;display:inline-block" min="0">
                        <input type="hidden" name="items[<?= e($item['id']) ?>][system_qty]" value="<?= (int)$item['system_qty'] ?>">
                        <?php else: ?>
                        <?= ($item['actual_qty'] !== null) ? (int)$item['actual_qty'] : '-' ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <?php if ($item['actual_qty'] !== null): ?>
                        <span style="color:<?= ((int)$item['diff_qty'] !== 0) ? 'var(--danger)' : 'var(--success)' ?>;font-weight:600">
                            <?= ((int)$item['diff_qty'] > 0 ? '+' : '') . (int)$item['diff_qty'] ?>
                        </span>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isEditable): ?>
                        <input type="text" name="items[<?= e($item['id']) ?>][note]" value="<?= e(!empty($item['note']) ? $item['note'] : '') ?>" class="form-control" style="width:120px;padding:4px 6px" placeholder="備註">
                        <?php else: ?>
                        <?= e(!empty($item['note']) ? $item['note'] : '-') ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($isEditable): ?>
    <div class="d-flex gap-1 mt-2" style="flex-wrap:wrap">
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('postAction').value='save'">儲存盤點</button>
        <button type="submit" class="btn btn-success" onclick="if(!confirm('確認提交盤點？')){event.preventDefault();return;}document.getElementById('postAction').value='complete'">提交簽核</button>
        <button type="button" class="btn btn-outline" style="color:var(--danger)" onclick="if(confirm('確認取消此盤點？')){document.getElementById('cancelForm').submit();}">取消盤點</button>
    </div>
    </form>
    <form id="cancelForm" method="POST" action="/inventory.php?action=stocktake_cancel" style="display:none">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($stocktake['id']) ?>">
    </form>
    <?php endif; ?>

    <?php
    // 簽核狀態顯示
    if ($stocktake['status'] === '待簽核' || $stocktake['status'] === '已完成'):
        require_once __DIR__ . '/../../modules/approvals/ApprovalModel.php';
        $approvalModel = new ApprovalModel();
        $flowStatus = $approvalModel->getFlowStatus('stocktakes', $stocktake['id']);
        $isApprover = false;
        $myFlowId = 0;
        foreach ($flowStatus['flows'] as $fl) {
            if ((int)$fl['approver_id'] === Auth::id() && $fl['status'] === 'pending') {
                $isApprover = true;
                $myFlowId = (int)$fl['id'];
            }
        }
    ?>
    <?php if (!empty($flowStatus['flows'])): ?>
    <div class="card mt-2">
        <div class="card-header">簽核紀錄</div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>簽核人</th><th>狀態</th><th>意見</th><th>時間</th></tr></thead>
                <tbody>
                <?php foreach ($flowStatus['flows'] as $fl): ?>
                <tr>
                    <td><?= e($fl['approver_name'] ?? '-') ?></td>
                    <td>
                        <?php if ($fl['status'] === 'pending'): ?>
                        <span class="badge badge-warning">待簽核</span>
                        <?php elseif ($fl['status'] === 'approved'): ?>
                        <span class="badge badge-success">已核准</span>
                        <?php elseif ($fl['status'] === 'rejected'): ?>
                        <span class="badge" style="background:#ffebee;color:#c62828">已駁回</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($fl['comment'] ?? '') ?></td>
                    <td><?= $fl['decided_at'] ? e(substr($fl['decided_at'], 0, 16)) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isApprover && $stocktake['status'] === '待簽核'): ?>
    <div class="card mt-2" style="border-left:4px solid var(--primary)">
        <div class="card-header">簽核操作</div>
        <div style="padding:16px">
            <div class="form-group">
                <label>簽核意見</label>
                <textarea id="approvalComment" class="form-control" rows="2" placeholder="選填"></textarea>
            </div>
            <div class="d-flex gap-1">
                <form method="POST" action="/inventory.php?action=stocktake_approve" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($stocktake['id']) ?>">
                    <input type="hidden" name="flow_id" value="<?= $myFlowId ?>">
                    <input type="hidden" name="comment" id="approveComment" value="">
                    <button type="submit" class="btn btn-success" onclick="document.getElementById('approveComment').value=document.getElementById('approvalComment').value;return confirm('確認核准此盤點？庫存將依差異調整')">核准</button>
                </form>
                <form method="POST" action="/inventory.php?action=stocktake_reject" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($stocktake['id']) ?>">
                    <input type="hidden" name="flow_id" value="<?= $myFlowId ?>">
                    <input type="hidden" name="comment" id="rejectComment" value="">
                    <button type="submit" class="btn btn-outline" style="color:var(--danger)" onclick="document.getElementById('rejectComment').value=document.getElementById('approvalComment').value;return confirm('確認駁回此盤點？')">駁回</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.product-info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
.product-info-item { display: flex; flex-direction: column; }
.product-info-label { font-size: .75rem; color: var(--gray-500); margin-bottom: 2px; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 600; }
.badge-success { background: #e6f9ee; color: var(--success); }
.badge-warning { background: #fff8e1; color: #f57f17; }
.badge-muted { background: var(--gray-100); color: var(--gray-500); }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
