<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= e($user['real_name']) ?></h2>
        <span class="badge badge-primary"><?= e(role_name($user['role'])) ?></span>
        <span class="text-muted"><?= e($user['branch_name']) ?></span>
        <?php if ($user['is_engineer']): ?><span class="badge badge-success">工程師</span><?php endif; ?>
        <?php if (!$user['is_active']): ?><span class="badge badge-danger">已停用</span><?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <?php if (Auth::hasPermission('staff.manage')): ?>
        <a href="/staff.php?action=edit&id=<?= $user['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <a href="/staff.php?action=skills&id=<?= $user['id'] ?>" class="btn btn-outline btn-sm">技能設定</a>
        <?php endif; ?>
        <a href="/staff.php" class="btn btn-outline btn-sm">返回列表</a>
    </div>
</div>

<!-- 基本資料 -->
<div class="card">
    <div class="card-header">基本資料</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">帳號</span><span class="detail-value"><?= e($user['username']) ?></span></div>
        <div class="detail-item"><span class="detail-label">電話</span><span class="detail-value"><?= e($user['phone'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">Email</span><span class="detail-value"><?= e($user['email'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">最後登入</span><span class="detail-value"><?= format_datetime($user['last_login_at']) ?: '從未登入' ?></span></div>
    </div>
</div>

<!-- 技能 -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>技能</span>
        <?php if (Auth::hasPermission('staff.manage')): ?>
        <a href="/staff.php?action=skills&id=<?= $user['id'] ?>" class="btn btn-outline btn-sm">編輯技能</a>
        <?php endif; ?>
    </div>
    <?php if (empty($userSkills)): ?>
        <p class="text-muted">尚未設定技能</p>
    <?php else: ?>
    <div class="skills-display">
        <?php
        $lastCat = '';
        foreach ($userSkills as $sk):
            if ($sk['category'] !== $lastCat):
                $lastCat = $sk['category'];
        ?>
        <div class="skill-category-label"><?= e($sk['category']) ?></div>
        <?php endif; ?>
        <div class="skill-display-item">
            <span><?= e($sk['skill_name']) ?></span>
            <span class="stars"><?= str_repeat('&#9733;', $sk['proficiency']) ?><?= str_repeat('&#9734;', 5 - $sk['proficiency']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 證照 -->
<div class="card">
    <div class="card-header">證照 / 工作證</div>
    <?php if (!empty($userCerts)): ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>證照名稱</th><th>證號</th><th>發證日</th><th>到期日</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($userCerts as $uc): ?>
                <tr>
                    <td><?= e($uc['cert_name']) ?></td>
                    <td><?= e($uc['cert_number'] ?: '-') ?></td>
                    <td><?= format_date($uc['issue_date']) ?: '-' ?></td>
                    <td>
                        <?php if ($uc['expiry_date']): ?>
                            <span class="<?= $uc['is_expiring'] ? 'text-danger' : '' ?>">
                                <?= format_date($uc['expiry_date']) ?>
                                <?= $uc['is_expiring'] ? ' (即將到期!)' : '' ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (Auth::hasPermission('staff.manage')): ?>
                        <a href="/staff.php?action=remove_cert&cert_id=<?= $uc['id'] ?>&user_id=<?= $user['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                           class="btn btn-danger btn-sm" onclick="return confirm('確定刪除此證照記錄?')">刪除</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-muted">尚無證照記錄</p>
    <?php endif; ?>

    <?php if (Auth::hasPermission('staff.manage')): ?>
    <div class="mt-2">
        <div class="card-header">新增證照</div>
        <form method="POST" action="/staff.php?action=add_cert" class="mt-1">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>證照類型</label>
                    <select name="certification_id" class="form-control" required>
                        <option value="">請選擇</option>
                        <?php
                        $db = Database::getInstance();
                        $certTypes = $db->query('SELECT * FROM certifications WHERE is_active = 1 ORDER BY name')->fetchAll();
                        foreach ($certTypes as $ct):
                        ?>
                        <option value="<?= $ct['id'] ?>"><?= e($ct['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>證照號碼</label>
                    <input type="text" name="cert_number" class="form-control">
                </div>
                <div class="form-group">
                    <label>發證日期</label>
                    <input type="date" name="issue_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>到期日期</label>
                    <input type="date" name="expiry_date" class="form-control">
                </div>
                <div class="form-group" style="align-self:flex-end">
                    <button type="submit" class="btn btn-primary btn-sm">新增</button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<style>
.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: .8rem; color: var(--gray-500); }
.skills-display { display: flex; flex-direction: column; gap: 2px; }
.skill-category-label { font-weight: 600; color: var(--primary); margin-top: 8px; font-size: .85rem; }
.skill-display-item { display: flex; justify-content: space-between; padding: 4px 0; font-size: .9rem; }
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 140px; }
@media (max-width: 767px) { .detail-grid { grid-template-columns: 1fr; } }
</style>
