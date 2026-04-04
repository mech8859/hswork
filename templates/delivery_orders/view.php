<?php $canManage = Auth::hasPermission('inventory.manage'); ?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>出貨單 <?= e($record['do_number']) ?></h2>
    <div class="d-flex gap-1 flex-wrap">
        <?php if ($canManage): ?>
            <?php if ($record['status'] === '草稿'): ?>
            <a href="/delivery_orders.php?action=edit&id=<?= $record['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
            <a href="/delivery_orders.php?action=confirm&id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:#2196F3;color:#fff" onclick="return confirm('確認出貨單？將自動產生出庫單。')">確認出貨</a>
            <a href="/delivery_orders.php?action=delete&id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-outline btn-sm" style="color:var(--danger)" onclick="return confirm('確定刪除？')">刪除</a>
            <?php endif; ?>

            <?php if ($record['status'] === '已確認'): ?>
            <a href="/delivery_orders.php?action=ship&id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:var(--primary);color:#fff" onclick="return confirm('確定標記為已出貨？')">標記出貨</a>
            <?php endif; ?>

            <?php if ($record['status'] === '已出貨'): ?>
            <a href="/delivery_orders.php?action=complete&id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:var(--success);color:#fff" onclick="return confirm('確定完成？')">完成</a>
            <?php endif; ?>
        <?php endif; ?>

        <a href="/delivery_orders.php" class="btn btn-outline btn-sm">返回列表</a>
    </div>
</div>

<!-- 基本資訊 -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>出貨單資訊</span>
        <span class="badge badge-<?= DeliveryModel::statusBadge($record['status']) ?>"><?= e(DeliveryModel::statusLabel($record['status'])) ?></span>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>出貨單號</label>
            <div class="form-static"><?= e($record['do_number']) ?></div>
        </div>
        <div class="form-group">
            <label>出貨日期</label>
            <div class="form-static"><?= e($record['do_date']) ?></div>
        </div>
        <div class="form-group">
            <label>出貨倉庫</label>
            <div class="form-static"><?= e(isset($record['warehouse_name']) ? $record['warehouse_name'] : '-') ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>案件/客戶</label>
            <div class="form-static"><?= e(!empty($record['case_name']) ? $record['case_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>關聯案件</label>
            <div class="form-static">
                <?php if (!empty($record['case_id'])): ?>
                <a href="/cases.php?action=view&id=<?= $record['case_id'] ?>"><?= e(isset($record['case_number']) ? $record['case_number'] : '#' . $record['case_id']) ?> <?= e(isset($record['case_title']) ? $record['case_title'] : '') ?></a>
                <?php else: ?>
                -
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>收貨人</label>
            <div class="form-static"><?= e(!empty($record['receiver_name']) ? $record['receiver_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>送貨地址</label>
            <div class="form-static"><?= e(!empty($record['delivery_address']) ? $record['delivery_address'] : '-') ?></div>
        </div>
    </div>
    <?php if (!empty($record['note'])): ?>
    <div class="form-group">
        <label>備註</label>
        <div class="form-static"><?= nl2br(e($record['note'])) ?></div>
    </div>
    <?php endif; ?>
    <div class="form-row" style="margin-top:8px">
        <div class="form-group">
            <label>建立者</label>
            <div class="form-static"><?= e(isset($record['created_by_name']) ? $record['created_by_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>建立時間</label>
            <div class="form-static"><?= e(isset($record['created_at']) ? $record['created_at'] : '-') ?></div>
        </div>
        <?php if (!empty($record['confirmed_by_name'])): ?>
        <div class="form-group">
            <label>確認者</label>
            <div class="form-static"><?= e($record['confirmed_by_name']) ?></div>
        </div>
        <div class="form-group">
            <label>確認時間</label>
            <div class="form-static"><?= e(isset($record['confirmed_at']) ? $record['confirmed_at'] : '-') ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 出貨明細 -->
<div class="card">
    <div class="card-header">出貨明細</div>
    <?php if (empty($record['items'])): ?>
        <p class="text-muted text-center mt-2">無明細</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th style="width:40px">#</th><th>品名</th><th>型號</th><th>規格</th><th>單位</th><th class="text-right">數量</th><th>備註</th>
            </tr></thead>
            <tbody>
                <?php $totalQty = 0; ?>
                <?php foreach ($record['items'] as $idx => $item):
                    $pname = !empty($item['product_name']) ? $item['product_name'] : (isset($item['db_product_name']) ? $item['db_product_name'] : '-');
                    $pmodel = !empty($item['model']) ? $item['model'] : (isset($item['db_model']) ? $item['db_model'] : '-');
                ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= e($pname) ?></td>
                    <td><?= e($pmodel) ?></td>
                    <td><?= e(!empty($item['spec']) ? $item['spec'] : '-') ?></td>
                    <td><?= e(!empty($item['unit']) ? $item['unit'] : (isset($item['db_unit']) ? $item['db_unit'] : '-')) ?></td>
                    <td class="text-right"><?= (int)$item['quantity'] ?></td>
                    <td><?= e(!empty($item['note']) ? $item['note'] : '') ?></td>
                </tr>
                <?php $totalQty += (int)$item['quantity']; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right"><strong>合計數量</strong></td>
                    <td class="text-right"><strong><?= $totalQty ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.form-static { padding: 6px 0; color: var(--gray-700); }
</style>
