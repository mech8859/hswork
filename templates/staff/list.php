<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>人員管理</h2>
    <div class="d-flex gap-1">
        <?php if (Auth::hasPermission('staff.manage')): ?>
        <a href="/staff.php?action=pairs" class="btn btn-outline btn-sm">配對表</a>
        <a href="/staff.php?action=create" class="btn btn-primary btn-sm">+ 新增人員</a>
        <?php endif; ?>
    </div>
</div>

<!-- 篩選 -->
<div class="card">
    <form method="GET" action="/staff.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword']) ?>" placeholder="姓名/帳號">
            </div>
            <div class="form-group">
                <label>角色</label>
                <select name="role" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($appConfig['roles'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $filters['role'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>工程師</label>
                <select name="is_engineer" class="form-control">
                    <option value="">全部</option>
                    <option value="1" <?= $filters['is_engineer'] === '1' ? 'selected' : '' ?>>是</option>
                    <option value="0" <?= $filters['is_engineer'] === '0' ? 'selected' : '' ?>>否</option>
                </select>
            </div>
            <?php if (count($branches) > 1): ?>
            <div class="form-group">
                <label>據點</label>
                <select name="branch_id" class="form-control">
                    <option value="">全部據點</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $filters['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/staff.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($users)): ?>
        <p class="text-muted text-center mt-2">目前無人員資料</p>
    <?php else: ?>

    <!-- 手機版 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($users as $u): ?>
        <div class="staff-card" onclick="location.href='/staff.php?action=view&id=<?= $u['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e($u['real_name']) ?></strong>
                <span class="badge badge-primary"><?= e(role_name($u['role'])) ?></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e($u['branch_name']) ?></span>
                <?php if ($u['is_engineer']): ?><span class="badge badge-success">工程師</span><?php endif; ?>
                <?php if (!$u['is_active']): ?><span class="badge badge-danger">停用</span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>帳號</th>
                    <th>據點</th>
                    <th>角色</th>
                    <th>工程師</th>
                    <th>電話</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="<?= !$u['is_active'] ? 'text-muted' : '' ?>">
                    <td><a href="/staff.php?action=view&id=<?= $u['id'] ?>"><?= e($u['real_name']) ?></a></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['branch_name']) ?></td>
                    <td><?= e(role_name($u['role'])) ?></td>
                    <td><?= $u['is_engineer'] ? '<span class="badge badge-success">是</span>' : '-' ?></td>
                    <td><?= e($u['phone'] ?? '-') ?></td>
                    <td><?= $u['is_active'] ? '<span class="badge badge-success">啟用</span>' : '<span class="badge badge-danger">停用</span>' ?></td>
                    <td>
                        <a href="/staff.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card {
    border: 1px solid var(--gray-200); border-radius: var(--radius);
    padding: 12px; cursor: pointer; transition: box-shadow .15s;
}
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) {
    .show-mobile { display: none !important; }
    .hide-mobile { display: block !important; }
}
</style>
