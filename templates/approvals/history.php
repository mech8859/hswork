<?php
$statusLabels = array(
    'pending'   => array('待簽核', '#e65100'),
    'approved'  => array('已核准', '#2e7d32'),
    'rejected'  => array('已駁回', '#c62828'),
    'cancelled' => array('已取消', '#999'),
);
$moduleOptions = array(
    'case_completion'      => '完工簽核',
    'quotation'            => '報價單',
    'leaves'               => '請假單',
    'expenses'             => '支出單',
    'purchases'            => '請購單',
    'no_deposit_schedule'  => '無訂金排工',
);
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>簽核紀錄 <small style="font-size:.7em;color:#666">(最近 200 筆)</small></h2>
    <div class="d-flex gap-1">
        <a href="/approvals.php?action=pending" class="btn btn-outline btn-sm">待簽核</a>
        <?php if ($canManageRules): ?>
        <a href="/approvals.php?action=settings" class="btn btn-outline btn-sm">簽核設定</a>
        <?php endif; ?>
    </div>
</div>

<!-- 篩選 -->
<div class="card" style="padding:12px;margin-bottom:12px">
    <form method="GET" action="/approvals.php" class="form-row" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin:0">
        <input type="hidden" name="action" value="history">
        <div class="form-group" style="margin:0">
            <label style="font-size:.8rem">模組</label>
            <select name="module" class="form-control" style="min-width:140px">
                <option value="">全部</option>
                <?php foreach ($moduleOptions as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filters['module'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <label style="font-size:.8rem">狀態</label>
            <select name="status" class="form-control" style="min-width:110px">
                <option value="">全部</option>
                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>待簽核</option>
                <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>已核准</option>
                <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>已駁回</option>
                <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>已取消</option>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <label style="font-size:.8rem">送簽日起</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="form-group" style="margin:0">
            <label style="font-size:.8rem">送簽日迄</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to']) ?>">
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:180px">
            <label style="font-size:.8rem">關鍵字</label>
            <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword']) ?>" placeholder="案件編號 / 客戶名">
        </div>
        <div class="form-group" style="margin:0">
            <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
            <a href="/approvals.php?action=history" class="btn btn-outline btn-sm">清除</a>
        </div>
    </form>
</div>

<?php if (empty($history)): ?>
<div class="card">
    <p class="text-muted text-center" style="padding:40px">沒有符合條件的紀錄</p>
</div>
<?php else: ?>
<div class="card" style="padding:0">
    <div class="table-responsive">
        <table class="table" style="font-size:.85rem">
            <thead>
                <tr>
                    <th>模組</th>
                    <th>關卡</th>
                    <th>單據</th>
                    <th>客戶/標題</th>
                    <th>分公司</th>
                    <th>簽核人</th>
                    <th style="text-align:center">狀態</th>
                    <th>送簽人 / 送簽時間</th>
                    <th>決定時間</th>
                    <th>備註</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $r):
                    $statusInfo = isset($statusLabels[$r['status']]) ? $statusLabels[$r['status']] : array($r['status'], '#666');
                ?>
                <tr>
                    <td><span class="badge badge-primary"><?= e(ApprovalModel::moduleLabel($r['module'])) ?></span></td>
                    <td><small style="color:#666">第<?= (int)$r['level_order'] ?>關</small></td>
                    <td>
                        <?php if (!empty($r['target_url'])): ?>
                        <a href="<?= e($r['target_url']) ?>" target="_blank" style="color:#1565c0;font-weight:600"><?= e($r['target_number']) ?></a>
                        <?php else: ?>
                        <?= e($r['target_number']) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= e($r['target_title']) ?></td>
                    <td><?= e($r['branch_name']) ?></td>
                    <td><?= e($r['approver_name'] ?? '-') ?></td>
                    <td style="text-align:center">
                        <span style="color:<?= $statusInfo[1] ?>;font-weight:600"><?= $statusInfo[0] ?></span>
                    </td>
                    <td>
                        <?= e($r['submitter_name'] ?? '-') ?>
                        <br><small style="color:#999"><?= e(date('Y-m-d H:i', strtotime($r['created_at']))) ?></small>
                    </td>
                    <td>
                        <?php if (!empty($r['decided_at'])): ?>
                        <small style="color:#666"><?= e(date('Y-m-d H:i', strtotime($r['decided_at']))) ?></small>
                        <?php else: ?>
                        <span style="color:#ccc">-</span>
                        <?php endif; ?>
                    </td>
                    <td><small style="color:#666"><?= e($r['comment'] ?? '') ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
