<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>點工費管理</h2>
    <div class="d-flex gap-1">
        <?php if (Auth::hasPermission('inter_branch.manage')): ?>
        <a href="/inter_branch.php?action=create" class="btn btn-primary btn-sm">+ 新增點工</a>
        <a href="/inter_branch.php?action=settle&month=<?= e($filters['month']) ?>" class="btn btn-outline btn-sm">月結確認</a>
        <?php endif; ?>
    </div>
</div>

<!-- 篩選 -->
<div class="card mb-2">
    <form method="GET" class="form-row align-center">
        <div class="form-group">
            <label>月份</label>
            <input type="month" name="month" class="form-control" value="<?= e($filters['month']) ?>">
        </div>
        <div class="form-group">
            <label>據點</label>
            <select name="branch_id" class="form-control">
                <option value="">全部</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $filters['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>結算狀態</label>
            <select name="settled" class="form-control">
                <option value="">全部</option>
                <option value="0" <?= $filters['settled'] === '0' ? 'selected' : '' ?>>未結算</option>
                <option value="1" <?= $filters['settled'] === '1' ? 'selected' : '' ?>>已結算</option>
            </select>
        </div>
        <div class="form-group" style="align-self:flex-end">
            <button type="submit" class="btn btn-primary btn-sm">篩選</button>
        </div>
    </form>
</div>

<!-- 桌面版表格 -->
<div class="card hide-mobile">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>日期</th>
                    <th>支援人員</th>
                    <th>原據點</th>
                    <th>支援據點</th>
                    <th>計費</th>
                    <th>時數</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                <tr><td colspan="8" class="text-center text-muted">無記錄</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                    <tr>
                        <td><?= e($r['support_date']) ?></td>
                        <td><?= e($r['user_name']) ?></td>
                        <td><?= e($r['from_branch_name']) ?></td>
                        <td><?= e($r['to_branch_name']) ?></td>
                        <td><?= InterBranchModel::chargeTypeLabel($r['charge_type']) ?></td>
                        <td><?= $r['charge_type'] === 'hourly' ? $r['hours'] . 'h' : '-' ?></td>
                        <td>
                            <?php if ($r['settled']): ?>
                            <span class="badge badge-success">已結算</span>
                            <?php else: ?>
                            <span class="badge">未結算</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (Auth::hasPermission('inter_branch.manage') && !$r['settled']): ?>
                            <a href="/inter_branch.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                            <a href="/inter_branch.php?action=delete&id=<?= $r['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                               class="btn btn-danger btn-sm" onclick="return confirm('確定刪除?')">刪除</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 手機版卡片 -->
<div class="show-mobile">
    <?php if (empty($records)): ?>
    <div class="card"><p class="text-center text-muted">無記錄</p></div>
    <?php else: ?>
        <?php foreach ($records as $r): ?>
        <div class="card mb-1">
            <div class="d-flex justify-between align-center mb-1">
                <strong><?= e($r['user_name']) ?></strong>
                <?php if ($r['settled']): ?>
                <span class="badge badge-success">已結算</span>
                <?php else: ?>
                <span class="badge">未結算</span>
                <?php endif; ?>
            </div>
            <div class="text-muted" style="font-size:.85rem">
                <?= e($r['support_date']) ?> ｜
                <?= e($r['from_branch_name']) ?> → <?= e($r['to_branch_name']) ?> ｜
                <?= InterBranchModel::chargeTypeLabel($r['charge_type']) ?>
                <?= $r['charge_type'] === 'hourly' ? ' ' . $r['hours'] . 'h' : '' ?>
            </div>
            <?php if ($r['note']): ?>
            <div class="text-muted" style="font-size:.8rem; margin-top:4px"><?= e($r['note']) ?></div>
            <?php endif; ?>
            <?php if (Auth::hasPermission('inter_branch.manage') && !$r['settled']): ?>
            <div class="d-flex gap-1 mt-1">
                <a href="/inter_branch.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                <a href="/inter_branch.php?action=delete&id=<?= $r['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                   class="btn btn-danger btn-sm" onclick="return confirm('確定刪除?')">刪除</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 140px; }
</style>
