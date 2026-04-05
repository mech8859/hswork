<?php
$canManage = Auth::hasPermission('quotations.manage');
$canEditQuote = QuotationModel::canEdit($quote['status']);
// 簽核狀態
$approvalStatus = array('flows' => array(), 'overall' => 'none');
try {
    require_once __DIR__ . '/../../modules/approvals/ApprovalModel.php';
    $approvalModel = new ApprovalModel();
    $approvalStatus = $approvalModel->getFlowStatus('quotations', $quote['id']);
} catch (Exception $e) {}
$isApprover = false;
foreach ($approvalStatus['flows'] as $af) {
    if ($af['approver_id'] == Auth::id() && $af['status'] === 'pending') $isApprover = true;
}
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>報價單 <?= e($quote['quotation_number']) ?></h2>
    <div class="d-flex gap-1 flex-wrap">
        <?php if (in_array($quote['status'], array('approved', 'sent', 'customer_accepted'))): ?>
        <a href="/quotations.php?action=print&id=<?= $quote['id'] ?>" class="btn btn-outline btn-sm" target="_blank">🖨 列印</a>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <?php if ($canEditQuote): ?>
            <a href="/quotations.php?action=edit&id=<?= $quote['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
            <a href="/quotations.php?action=submit_approval&id=<?= $quote['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:#2196F3;color:#fff" onclick="return confirm('確定送出簽核？')">送簽核</a>
            <?php endif; ?>

            <?php if ($quote['status'] === 'approved'): ?>
            <a href="/quotations.php?action=status&id=<?= $quote['id'] ?>&status=sent&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:var(--primary);color:#fff" onclick="return confirm('確定送出給客戶？')">送客戶</a>
            <?php endif; ?>

            <?php if ($quote['status'] === 'sent'): ?>
            <a href="/quotations.php?action=status&id=<?= $quote['id'] ?>&status=customer_accepted&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:var(--success);color:#fff" onclick="return confirm('客戶已接受？')">客戶已接受</a>
            <a href="/quotations.php?action=status&id=<?= $quote['id'] ?>&status=customer_rejected&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:var(--danger);color:#fff" onclick="return confirm('客戶已拒絕？')">客戶已拒絕</a>
            <a href="/quotations.php?action=status&id=<?= $quote['id'] ?>&status=revision_needed&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:#FF9800;color:#fff" onclick="return confirm('客戶要求修改？')">待修改</a>
            <?php endif; ?>

            <?php if ($quote['status'] === 'customer_accepted'): ?>
            <?php if (!empty($_GET['show_force_stock_out'])): ?>
            <a href="/quotations.php?action=create_stock_out&id=<?= $quote['id'] ?>&force=1&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:var(--danger);color:#fff" onclick="return confirm('已有出庫單，確定要再次建立？')">確認再次建立出庫單</a>
            <?php endif; ?>
            <a href="/quotations.php?action=create_stock_out&id=<?= $quote['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:#FF9800;color:#fff" onclick="return confirm('從此報價單建立出庫單？')">建立出庫單</a>
            <?php endif; ?>

            <a href="/quotations.php?action=duplicate&id=<?= $quote['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-outline btn-sm" onclick="return confirm('確定複製？')">複製</a>

            <?php if ($quote['status'] === 'draft'): ?>
            <a href="/quotations.php?action=delete&id=<?= $quote['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-outline btn-sm" style="color:var(--danger)" onclick="return confirm('確定刪除？')">刪除</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($isApprover): ?>
        <!-- 簽核人操作按鈕 -->
        <?php foreach ($approvalStatus['flows'] as $af):
            if ($af['approver_id'] == Auth::id() && $af['status'] === 'pending'):
        ?>
        <form method="POST" action="/approvals.php?action=approve" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="flow_id" value="<?= $af['id'] ?>">
            <input type="hidden" name="module" value="quotations">
            <input type="hidden" name="target_id" value="<?= $quote['id'] ?>">
            <input type="hidden" name="redirect" value="/quotations.php?action=view&id=<?= $quote['id'] ?>">
            <button type="submit" class="btn btn-sm" style="background:var(--success);color:#fff" onclick="return confirm('確定核准此報價單？')">✓ 核准</button>
        </form>
        <form method="POST" action="/approvals.php?action=reject" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="flow_id" value="<?= $af['id'] ?>">
            <input type="hidden" name="module" value="quotations">
            <input type="hidden" name="target_id" value="<?= $quote['id'] ?>">
            <input type="hidden" name="redirect" value="/quotations.php?action=view&id=<?= $quote['id'] ?>">
            <input type="hidden" name="comment" value="">
            <button type="submit" class="btn btn-sm" style="background:var(--danger);color:#fff" onclick="var c=prompt('退回原因：');if(!c)return false;this.form.querySelector('input[name=comment]').value=c;return true;">✗ 退回</button>
        </form>
        <?php endif; endforeach; ?>
        <?php endif; ?>

        <?= back_button('/quotations.php') ?>
    </div>
</div>

<!-- 簽核紀錄 -->
<?php if (!empty($approvalStatus['flows'])): ?>
<div class="card mb-2" style="border-left:4px solid <?= $approvalStatus['overall'] === 'approved' ? 'var(--success)' : ($approvalStatus['overall'] === 'rejected' ? 'var(--danger)' : '#2196F3') ?>">
    <div class="card-header" style="padding:8px 12px;font-size:.85rem">簽核紀錄</div>
    <?php foreach ($approvalStatus['flows'] as $af): ?>
    <div style="padding:6px 12px;font-size:.85rem;border-bottom:1px solid var(--gray-100)">
        <strong><?= e($af['approver_name']) ?></strong>
        <?php if ($af['status'] === 'pending'): ?>
            <span class="badge badge-info">待簽核</span>
        <?php elseif ($af['status'] === 'approved'): ?>
            <span class="badge badge-success">已核准</span>
            <span class="text-muted"><?= e(substr($af['decided_at'], 0, 16)) ?></span>
        <?php else: ?>
            <span class="badge badge-danger">已退回</span>
            <span class="text-muted"><?= e(substr($af['decided_at'], 0, 16)) ?></span>
        <?php endif; ?>
        <?php if ($af['comment']): ?>
            <span style="color:#666"> — <?= e($af['comment']) ?></span>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- 基本資訊 -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>基本資訊</span>
        <span class="badge badge-<?= QuotationModel::statusBadge($quote['status']) ?>"><?= e(QuotationModel::statusLabel($quote['status'])) ?></span>
    </div>
    <div class="info-grid">
        <div><span class="info-label">客戶名稱</span><span><?= e($quote['customer_name']) ?></span></div>
        <div><span class="info-label">連絡對象</span><span><?= e($quote['contact_person'] ?: '-') ?></span></div>
        <div><span class="info-label">連絡電話</span><span><?= e($quote['contact_phone'] ?: '-') ?></span></div>
        <div><span class="info-label">案場名稱</span><span><?= e($quote['site_name'] ?: '-') ?></span></div>
        <div><span class="info-label">施工地址</span><span><?= e($quote['site_address'] ?: '-') ?></span></div>
        <div><span class="info-label">報價格式</span><span><?= QuotationModel::formatLabel($quote['format']) ?></span></div>
        <div><span class="info-label">報價日期</span><span><?= e($quote['quote_date']) ?></span></div>
        <div><span class="info-label">有效日期</span><span><?= e($quote['valid_date']) ?></span></div>
        <div><span class="info-label">承辦業務</span><span><?= e($quote['sales_name'] ?: '-') ?></span></div>
        <div><span class="info-label">發票抬頭</span><span><?= e($quote['invoice_title'] ?: '-') ?></span></div>
        <div><span class="info-label">統編</span><span><?= e($quote['invoice_tax_id'] ?: '-') ?></span></div>
        <?php if ($quote['case_id']): ?>
        <div><span class="info-label">關聯案件</span><span><a href="/cases.php?action=view&id=<?= $quote['case_id'] ?>">查看案件</a></span></div>
        <?php endif; ?>
    </div>
</div>

<!-- 報價內容 -->
<div class="card">
    <div class="card-header">報價內容</div>
    <?php
    $chineseNums = array('一','二','三','四','五','六','七','八','九','十');
    foreach ($quote['sections'] as $sIdx => $sec):
    ?>
    <?php if ($quote['format'] === 'project'): ?>
    <div style="font-weight:600;padding:8px 12px;background:var(--gray-100);margin-top:<?= $sIdx > 0 ? '8px' : '0' ?>">
        <?= isset($chineseNums[$sIdx]) ? $chineseNums[$sIdx] : ($sIdx + 1) ?>、<?= e($sec['title'] ?: '未命名區段') ?>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table" style="font-size:.85rem;margin:0">
            <thead><tr>
                <th style="width:30px">序</th>
                <th>品名/型號</th>
                <th style="width:70px">數量</th>
                <th style="width:50px">單位</th>
                <th style="width:90px" class="text-right">單價</th>
                <th style="width:90px" class="text-right">小計</th>
                <th>備註</th>
                <?php if ($canManage): ?><th style="width:80px" class="text-right">成本</th><?php endif; ?>
            </tr></thead>
            <tbody>
                <?php foreach ($sec['items'] as $iIdx => $item): ?>
                <tr>
                    <td><?= $iIdx + 1 ?></td>
                    <td><?= e($item['item_name']) ?><?php if (!empty($item['model_number'])): ?> / <span style="color:#1565c0"><?= e($item['model_number']) ?></span><?php endif; ?></td>
                    <td><?= rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') ?></td>
                    <td><?= e($item['unit']) ?></td>
                    <td class="text-right"><?= number_format($item['unit_price']) ?></td>
                    <td class="text-right"><?= number_format($item['amount']) ?></td>
                    <td><?= e($item['remark'] ?: '') ?></td>
                    <?php if ($canManage): ?>
                    <td class="text-right"><?= number_format($item['cost_amount']) ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($quote['format'] === 'project'): ?>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right"><strong>小計</strong></td>
                    <td class="text-right"><strong><?= number_format($sec['subtotal']) ?></strong></td>
                    <td colspan="<?= $canManage ? 2 : 1 ?>"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php endforeach; ?>

    <div style="text-align:right;padding:12px;border-top:2px solid var(--gray-200)">
        <?php if (empty($quote['tax_free'])): ?>
        <div style="margin-bottom:4px">未稅合計：<strong>$<?= number_format($quote['subtotal']) ?></strong></div>
        <div style="margin-bottom:4px">營業稅 (<?= rtrim(rtrim(number_format($quote['tax_rate'], 2), '0'), '.') ?>%)：<strong>$<?= number_format($quote['tax_amount']) ?></strong></div>
        <div style="font-size:1.2rem;color:var(--primary)">合計新台幣：<strong>$<?= number_format($quote['total_amount']) ?></strong></div>
        <?php else: ?>
        <div style="font-size:1.2rem;color:var(--primary)">合計新台幣(未稅)：<strong>$<?= number_format($quote['subtotal']) ?></strong></div>
        <?php endif; ?>
        <?php if (!empty($quote['has_discount']) && !empty($quote['discount_amount'])): ?>
        <div style="font-size:1.2rem;color:var(--danger);margin-top:4px">優惠價：<strong>$<?= number_format($quote['discount_amount']) ?></strong></div>
        <?php endif; ?>
    </div>
</div>

<!-- 內部成本（僅管理者可見） -->
<?php if ($canManage): ?>
<div class="card">
    <div class="card-header">內部成本分析</div>
    <div class="info-grid">
        <div><span class="info-label">施工天數</span><span><?= $quote['labor_days'] ?: '-' ?></span></div>
        <div><span class="info-label">施工人數</span><span><?= $quote['labor_people'] ?: '-' ?></span></div>
        <div><span class="info-label">施工時數</span><span><?= $quote['labor_hours'] ?: '-' ?></span></div>
        <div><span class="info-label">人力成本</span><span>$<?= number_format($quote['labor_cost_total'] ?: 0) ?></span></div>
        <div><span class="info-label">器材成本</span><span>$<?= number_format($quote['total_cost'] - ($quote['labor_cost_total'] ?: 0)) ?></span></div>
        <div><span class="info-label">總成本</span><span>$<?= number_format($quote['total_cost']) ?></span></div>
        <div><span class="info-label">利潤</span><span style="color:<?= $quote['profit_amount'] >= 0 ? '#137333' : '#c5221f' ?>">$<?= number_format($quote['profit_amount']) ?></span></div>
        <div><span class="info-label">利潤率</span><span style="color:<?= $quote['profit_rate'] >= 0 ? '#137333' : '#c5221f' ?>"><?= $quote['profit_rate'] ?>%</span></div>
    </div>
</div>
<?php endif; ?>

<!-- 附加資訊 -->
<?php if ($quote['payment_terms'] || $quote['notes']): ?>
<div class="card">
    <div class="card-header">附加資訊</div>
    <?php if ($quote['payment_terms']): ?>
    <div style="margin-bottom:8px"><strong>收款條件：</strong><?= nl2br(e($quote['payment_terms'])) ?></div>
    <?php endif; ?>
    <?php if ($quote['notes']): ?>
    <div><strong>附註說明：</strong><?= nl2br(e($quote['notes'])) ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
.info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; padding: 8px 12px; }
.info-label { display: block; font-size: .75rem; color: var(--gray-500); margin-bottom: 2px; }
</style>
