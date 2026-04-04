<div class="d-flex justify-between align-center mb-2">
    <h2>外包廠商管理</h2>
    <div class="d-flex gap-1">
        <a href="/dispatch_workers.php?type=dispatch" class="btn btn-outline">點工人員</a>
        <a href="/dispatch_workers.php?type=outsource" class="btn btn-outline">外包人員</a>
        <a href="/dispatch_workers.php?type=vendor" class="btn btn-outline btn-primary">外包廠商</a>
        <a href="/dispatch_workers.php?type=vendor&action=create" class="btn btn-primary">+ 新增廠商</a>
    </div>
</div>
<div class="mb-2"><a href="/staff.php" class="btn btn-outline" style="font-size:.85rem">← 返回人員管理</a></div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>廠商名稱</th><th>聯繫人</th><th>電話</th><th>統編</th><th>人員數</th><th>啟用</th><th>操作</th></tr></thead>
            <tbody>
                <?php if (empty($vendors)): ?>
                <tr><td colspan="7" class="text-center text-muted">目前無資料</td></tr>
                <?php else: ?>
                <?php foreach ($vendors as $v): ?>
                <tr style="<?= !$v['is_active'] ? 'opacity:.5' : '' ?>">
                    <td><?= e($v['name']) ?></td>
                    <td><?= e($v['contact_person'] ?? '-') ?></td>
                    <td><?= e($v['phone'] ?? '-') ?></td>
                    <td><?= e($v['tax_id'] ?? '-') ?></td>
                    <td><?= $v['worker_count'] ?></td>
                    <td><?= $v['is_active'] ? '<span style="color:green">✓</span>' : '<span style="color:red">✕</span>' ?></td>
                    <td><a href="/dispatch_workers.php?type=vendor&action=edit&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline">編輯</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
