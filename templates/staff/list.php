<?php $skillsOnly = !Auth::hasPermission('staff.manage') && !Auth::hasPermission('staff.view') && Auth::hasPermission('staff_skills.manage'); ?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>人員管理</h2>
    <div class="d-flex gap-1">
        <?php if (Auth::hasPermission('staff_skills.manage') || Auth::hasPermission('staff.manage')): ?>
        <a href="/staff.php?action=manage_skills" class="btn btn-outline btn-sm">技能管理</a>
        <?php endif; ?>
        <?php if (Auth::hasPermission('staff.manage')): ?>
        <?php if (Auth::user()['role'] === 'boss'): ?>
        <a href="/staff.php?action=branches" class="btn btn-outline btn-sm">分公司管理</a>
        <?php endif; ?>
        <a href="/dispatch_workers.php?type=dispatch" class="btn btn-outline btn-sm">點工人員</a>
        <a href="/dispatch_workers.php?type=vendor" class="btn btn-outline btn-sm">外包廠商</a>
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
                    <?php foreach (get_dynamic_roles() as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $filters['role'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>在職狀況</label>
                <select name="emp_status" class="form-control">
                    <option value="working" <?= ($filters['emp_status'] ?? 'working') === 'working' ? 'selected' : '' ?>>在職（含試用/留停）</option>
                    <option value="" <?= isset($filters['emp_status']) && $filters['emp_status'] === '' ? 'selected' : '' ?>>全部</option>
                    <option value="resigned" <?= ($filters['emp_status'] ?? '') === 'resigned' ? 'selected' : '' ?>>離職</option>
                    <option value="probation" <?= ($filters['emp_status'] ?? '') === 'probation' ? 'selected' : '' ?>>試用</option>
                    <option value="suspended" <?= ($filters['emp_status'] ?? '') === 'suspended' ? 'selected' : '' ?>>留職停薪</option>
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
                <strong><?= !empty($u['employee_id']) ? e($u['employee_id']) . ' ' : '' ?><?= e($u['real_name']) ?></strong>
                <span class="badge badge-primary"><?= e(role_name($u['role'])) ?></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e($u['branch_name']) ?></span>
                <?php if ($u['is_engineer']): ?><span class="badge badge-success">工程師</span><?php endif; ?>
                <?php if (!$u['is_active']): ?><span class="badge badge-danger">停用</span>
                <?php elseif (!empty($u['locked_until']) && strtotime($u['locked_until']) > time()): ?><span class="badge badge-danger">鎖定</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <?php if (!$skillsOnly): ?><th>編號</th><?php endif; ?>
                    <th>姓名</th>
                    <?php if (!$skillsOnly): ?><th>帳號</th><?php endif; ?>
                    <th>據點</th>
                    <th>角色</th>
                    <th>工程師</th>
                    <?php if (!$skillsOnly): ?><th>電話</th><?php endif; ?>
                    <?php if (!$skillsOnly): ?><th>狀態</th><?php endif; ?>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="<?= !$u['is_active'] ? 'text-muted' : '' ?>">
                    <?php if (!$skillsOnly): ?><td><?= e($u['employee_id'] ?? '-') ?></td><?php endif; ?>
                    <td><a href="/staff.php?action=view&id=<?= $u['id'] ?>"><?= e($u['real_name']) ?></a></td>
                    <?php if (!$skillsOnly): ?><td><?= e($u['username']) ?></td><?php endif; ?>
                    <td><?= e($u['branch_name']) ?></td>
                    <td><?= e(role_name($u['role'])) ?></td>
                    <td><?= $u['is_engineer'] ? '<span class="badge badge-success">是</span>' : '-' ?></td>
                    <?php if (!$skillsOnly): ?><td><?= e($u['phone'] ?? '-') ?></td><?php endif; ?>
                    <?php if (!$skillsOnly): ?><td>
                        <?php if (!$u['is_active']): ?>
                            <span class="badge badge-danger">停用</span>
                        <?php elseif (!empty($u['locked_until']) && strtotime($u['locked_until']) > time()): ?>
                            <span class="badge badge-danger">鎖定</span>
                        <?php else: ?>
                            <span class="badge badge-success">啟用</span>
                        <?php endif; ?>
                    </td><?php endif; ?>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if (Auth::hasPermission('staff.manage') || Auth::hasPermission('staff.view') || Auth::hasPermission('staff_skills.manage') || $u['id'] == Auth::id()): ?>
                            <a href="/staff.php?action=view&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">檢視</a>
                            <?php endif; ?>
                            <?php if (Auth::hasPermission('staff.manage') || Auth::hasPermission('staff_skills.manage') || $u['id'] == Auth::id()): ?>
                            <a href="/staff.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                            <?php endif; ?>
                        </div>
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
