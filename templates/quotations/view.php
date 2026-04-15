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
            <?php if ($canEditQuote && $quote['status'] !== 'approved'): ?>
            <a href="/quotations.php?action=edit&id=<?= $quote['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
            <a href="/quotations.php?action=submit_approval&id=<?= $quote['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:#2196F3;color:#fff" onclick="return confirm('確定送出簽核？')">送簽核</a>
            <?php endif; ?>

            <?php if ($quote['status'] === 'approved'): ?>
            <a href="/quotations.php?action=edit&id=<?= $quote['id'] ?>" class="btn btn-primary btn-sm" onclick="return confirm('編輯後將退回草稿，需重新送簽核，確定？')">編輯</a>
            <a href="/quotations.php?action=status&id=<?= $quote['id'] ?>&status=sent&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:var(--primary);color:#fff" onclick="return confirm('確定送出給客戶？')">送客戶</a>
            <?php endif; ?>

            <?php if (in_array($quote['status'], array('sent', 'customer_accepted'))): ?>
            <a href="/quotations.php?action=request_revision&id=<?= $quote['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:#FF9800;color:#fff" onclick="return confirm('申請變更將需要主管簽核，確定？')">申請變更</a>
            <?php endif; ?>

            <?php if ($quote['status'] === 'sent'): ?>
            <a href="/quotations.php?action=status&id=<?= $quote['id'] ?>&status=customer_accepted&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:var(--success);color:#fff" onclick="return confirm('客戶已接受？')">客戶已接受</a>
            <a href="/quotations.php?action=status&id=<?= $quote['id'] ?>&status=customer_rejected&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:var(--danger);color:#fff" onclick="return confirm('客戶已拒絕？')">客戶已拒絕</a>
            <a href="/quotations.php?action=status&id=<?= $quote['id'] ?>&status=revision_needed&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:#FF9800;color:#fff" onclick="return confirm('客戶要求修改？')">待修改</a>
            <?php endif; ?>

            <?php if ($quote['status'] === 'customer_accepted'): ?>
            <?php if (!empty($relatedStockOuts)): ?>
                <?php $_latestSo = $relatedStockOuts[0]; ?>
                <a href="/stock_outs.php?action=view&id=<?= $_latestSo['id'] ?>" class="btn btn-sm" style="background:var(--success);color:#fff" title="點選前往出庫單 <?= e($_latestSo['so_number']) ?>">✓ 出庫單已建立</a>
                <?php if (!empty($_GET['show_force_stock_out'])): ?>
                <a href="/quotations.php?action=create_stock_out&id=<?= $quote['id'] ?>&force=1&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:var(--danger);color:#fff" onclick="return confirm('已有出庫單，確定要再次建立？')">確認再次建立出庫單</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="/quotations.php?action=create_stock_out&id=<?= $quote['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:#FF9800;color:#fff" onclick="return confirm('從此報價單建立出庫單？')">建立出庫單</a>
            <?php endif; ?>
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

<?php require __DIR__ . '/../layouts/editing_lock_warning.php'; ?>

<!-- 已建立的出庫單 -->
<?php if (!empty($relatedStockOuts)): ?>
<div class="card mb-2" style="border-left:4px solid var(--success)">
    <div class="card-header" style="padding:8px 12px;font-size:.85rem;display:flex;align-items:center;gap:8px">
        <span>📦 已建立出庫單</span>
        <span class="text-muted" style="font-size:.8rem;font-weight:normal">(共 <?= count($relatedStockOuts) ?> 筆)</span>
    </div>
    <div style="padding:8px 12px">
        <?php foreach ($relatedStockOuts as $_so): ?>
        <div style="padding:4px 0;font-size:.9rem;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <a href="/stock_outs.php?action=view&id=<?= $_so['id'] ?>" style="font-weight:600;color:var(--primary)"><?= e($_so['so_number']) ?></a>
            <span class="badge" style="background:var(--gray-100);color:var(--gray-700)"><?= e($_so['status']) ?></span>
            <span class="text-muted" style="font-size:.8rem"><?= e($_so['so_date']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

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

<!-- 預計使用線材與配件（僅管理者，統計分析用） -->
<?php
$matCostTotal = 0;
if ($canManage && !empty($quote['case_id'])) {
    $viewCaseModel = new CaseModel();
    $viewEstMaterials = $viewCaseModel->getMaterialEstimates($quote['case_id']);
?>
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>預計使用線材與配件（統計分析用）</span>
        <a href="/cases.php?action=edit&id=<?= (int)$quote['case_id'] ?>#tab-estimate" class="btn btn-outline btn-sm" style="font-size:.75rem">去案件編輯</a>
    </div>
    <?php if (empty($viewEstMaterials)): ?>
    <div style="padding:20px;text-align:center;color:#999;font-size:.9rem">尚無預計材料 — 請到案件編輯頁「預計材料」頁籤新增</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table" style="font-size:.85rem;margin:0">
            <thead><tr>
                <th>品名</th><th>型號</th><th>單位</th><th style="text-align:right">預估數量</th><th style="text-align:right">單位成本</th><th style="text-align:right">成本小計</th>
            </tr></thead>
            <tbody>
            <?php foreach ($viewEstMaterials as $vem):
                $unitCost = 0;
                if (!empty($vem['product_id'])) {
                    $pCostStmt = Database::getInstance()->prepare("SELECT cost, pack_qty, cost_per_unit FROM products WHERE id = ?");
                    $pCostStmt->execute(array($vem['product_id']));
                    $pCostRow = $pCostStmt->fetch(PDO::FETCH_ASSOC);
                    if ($pCostRow) {
                        if (!empty($pCostRow['cost_per_unit'])) {
                            $unitCost = (float)$pCostRow['cost_per_unit'];
                        } elseif (!empty($pCostRow['pack_qty']) && $pCostRow['pack_qty'] > 0) {
                            $unitCost = (float)$pCostRow['cost'] / (float)$pCostRow['pack_qty'];
                        } else {
                            $unitCost = (float)$pCostRow['cost'];
                        }
                    }
                }
                $lineCost = $unitCost * (float)$vem['estimated_qty'];
                $matCostTotal += $lineCost;
            ?>
            <tr>
                <td><?= e($vem['material_name']) ?></td>
                <td><?= e($vem['model_number'] ?: '-') ?></td>
                <td><?= e($vem['unit'] ?: '-') ?></td>
                <td style="text-align:right"><?= $vem['estimated_qty'] ?></td>
                <td style="text-align:right"><?= $unitCost > 0 ? '$' . number_format($unitCost) : '-' ?></td>
                <td style="text-align:right"><?= $lineCost > 0 ? '$' . number_format($lineCost) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600;border-top:2px solid var(--gray-300)">
                    <td colspan="5" style="text-align:right">成本合計</td>
                    <td style="text-align:right">$<?= number_format($matCostTotal) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php
}
?>

<!-- 內部成本（僅管理者可見） -->
<?php if ($canManage): ?>
<div class="card">
    <div class="card-header">內部成本分析</div>
    <?php
    $viewLaborCost = (int)($quote['labor_cost_total'] ?: 0);
    $viewCableCost = (int)($quote['cable_cost'] ?: 0);
    // 若 cable_cost 未存但有線材預估，即時計算顯示
    if ($viewCableCost == 0 && isset($matCostTotal) && $matCostTotal > 0) {
        $viewCableCost = (int)$matCostTotal;
    }
    $viewMaterialCost = (int)$quote['total_cost'] - $viewLaborCost - (int)($quote['cable_cost'] ?: 0);
    ?>
    <?php
    // 即時計算施工時數與人力成本（若DB未存但有天數人數）
    $viewDays = (float)($quote['labor_days'] ?: 0);
    $viewPeople = (int)($quote['labor_people'] ?: 0);
    $viewHours = (float)($quote['labor_hours'] ?: 0);
    if (!$viewHours && $viewDays > 0 && $viewPeople > 0) {
        $viewHours = $viewDays * $viewPeople * 8;
    }
    if ($viewLaborCost == 0 && $viewHours > 0) {
        $hrStmt = Database::getInstance()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'labor_hourly_cost' LIMIT 1");
        $hrStmt->execute();
        $hrVal = $hrStmt->fetchColumn();
        $viewHourlyCost = ($hrVal !== false && $hrVal !== null) ? (int)$hrVal : 560;
        $viewLaborCost = (int)round($viewHours * $viewHourlyCost);
    }
    // 營運成本
    $_vOpModeStmt = Database::getInstance()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'operation_cost_mode' LIMIT 1");
    $_vOpModeStmt->execute();
    $_vOpMode = $_vOpModeStmt->fetchColumn() ?: 'labor_ratio';
    $_vOpRateStmt = Database::getInstance()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'operation_cost_rate' LIMIT 1");
    $_vOpRateStmt->execute();
    $_vOpRate = (float)($_vOpRateStmt->fetchColumn() ?: 128);
    if ($_vOpMode === 'labor_ratio') {
        $viewOpCost = round($viewLaborCost * $_vOpRate / 100);
    } else {
        $viewOpCost = round((float)$quote['subtotal'] * $_vOpRate / 100);
    }
    // 含營運成本的真實利潤
    $viewRealTotalCost = (int)$quote['total_cost'] + $viewOpCost;
    $viewRealProfit = (float)$quote['subtotal'] - $viewRealTotalCost;
    $viewRealProfitRate = (float)$quote['subtotal'] > 0 ? round($viewRealProfit / (float)$quote['subtotal'] * 100, 1) : 0;
    ?>
    <div class="info-grid">
        <div><span class="info-label">施工天數</span><span><?= $viewDays ?: '-' ?></span></div>
        <div><span class="info-label">施工人數</span><span><?= $viewPeople ?: '-' ?></span></div>
        <div><span class="info-label">施工時數</span><span><?= $viewHours ? $viewHours : '-' ?></span></div>
        <div><span class="info-label">人力成本</span><span>$<?= number_format($viewLaborCost) ?></span></div>
        <div><span class="info-label">器材成本</span><span>$<?= number_format($viewMaterialCost) ?></span></div>
        <div><span class="info-label">線材成本</span><span>$<?= number_format($viewCableCost) ?></span></div>
        <div><span class="info-label">營運成本 <small style="color:#999">(人力×<?= $_vOpRate ?>%)</small></span><span style="color:#e65100">$<?= number_format($viewOpCost) ?></span></div>
        <div><span class="info-label">總成本</span><span><strong>$<?= number_format($viewRealTotalCost) ?></strong></span></div>
        <div><span class="info-label">利潤</span><span style="color:<?= $viewRealProfit >= 0 ? '#137333' : '#c5221f' ?>"><strong>$<?= number_format($viewRealProfit) ?></strong></span></div>
        <div><span class="info-label">利潤率</span><span style="color:<?= $viewRealProfitRate >= 0 ? '#137333' : '#c5221f' ?>"><strong><?= $viewRealProfitRate ?>%</strong></span></div>
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
