<div class="d-flex justify-between align-center mb-2">
    <h2><?= $type === 'outsource' ? '外包人員管理' : '點工人員管理' ?></h2>
    <div class="d-flex gap-1">
        <a href="/dispatch_workers.php?type=vendor" class="btn btn-outline">外包廠商</a>
        <a href="/dispatch_workers.php?type=outsource" class="btn btn-outline <?= $type==='outsource'?'btn-primary':'' ?>">外包人員</a>
        <a href="/dispatch_workers.php?type=dispatch" class="btn btn-outline <?= $type==='dispatch'?'btn-primary':'' ?>">點工人員</a>
        <a href="/dispatch_workers.php?action=create&type=<?= e($type) ?>" class="btn btn-primary">+ 新增</a>
    </div>
</div>

<div class="d-flex gap-1 mb-2" style="align-items:center">
    <a href="/staff.php" class="btn btn-outline" style="font-size:.85rem">← 返回人員管理</a>
    <form method="GET" class="d-flex gap-1" style="flex:1;justify-content:flex-end">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <input type="text" name="keyword" class="form-control" style="max-width:250px" placeholder="搜尋姓名/電話..." value="<?= e($_GET['keyword'] ?? '') ?>">
        <button class="btn btn-primary" style="font-size:.85rem">搜尋</button>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>電話</th>
                    <th>狀態</th>
                    <th>專長</th>
                    <?php if ($type === 'outsource'): ?><th>所屬廠商</th><?php endif; ?>
                    <th>日薪</th>
                    <th>啟用</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($workers)): ?>
                <tr><td colspan="8" class="text-center text-muted">目前無資料</td></tr>
                <?php else: ?>
                <?php foreach ($workers as $w): ?>
                <tr style="<?= !$w['is_active'] ? 'opacity:.5' : '' ?>">
                    <td><a href="/dispatch_workers.php?action=edit&id=<?= $w['id'] ?>"><?= e($w['name']) ?></a></td>
                    <td><a href="tel:<?= e($w['phone'] ?? '') ?>"><?= e($w['phone'] ?? '-') ?></a></td>
                    <td>
                        <?php if ($w['status'] === 'primary'): ?>
                        <span class="badge badge-success">優先</span>
                        <?php else: ?>
                        <span class="badge" style="background:#fff3e0;color:#e65100">備用</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($w['specialty'] ?? '-') ?></td>
                    <?php if ($type === 'outsource'): ?><td><?= e($w['vendor_name'] ?? '-') ?></td><?php endif; ?>
                    <td><?= $w['daily_rate'] ? '$' . number_format($w['daily_rate']) : '-' ?></td>
                    <td><?= $w['is_active'] ? '<span style="color:green">✓</span>' : '<span style="color:red">✕</span>' ?></td>
                    <td>
                        <a href="/dispatch_workers.php?action=edit&id=<?= $w['id'] ?>" class="btn btn-sm btn-outline">編輯</a>
                        <a href="/dispatch_workers.php?action=skills&id=<?= $w['id'] ?>" class="btn btn-sm btn-outline" style="color:var(--warning)">技能</a>
                        <a href="/dispatch_workers.php?action=pairs&id=<?= $w['id'] ?>" class="btn btn-sm btn-outline" style="color:var(--info)">配對</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
