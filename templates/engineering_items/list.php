<div class="d-flex justify-between align-center mb-2">
    <h2>工程項次管理</h2>
    <div class="d-flex gap-1">
        <a href="/dropdown_options.php" class="btn btn-outline">← 選單管理</a>
        <a href="/engineering_items.php?action=create" class="btn btn-primary">+ 新增項次</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table" style="font-size:.9rem">
            <thead><tr><th>分類</th><th>項目名稱</th><th>單位</th><th>預設定價</th><th>預設成本</th><th>啟用</th><th>操作</th></tr></thead>
            <tbody>
                <?php
                $lastCat = '';
                foreach ($items as $item):
                    if ($item['category'] !== $lastCat):
                        $lastCat = $item['category'];
                ?>
                <tr style="background:#e3f2fd"><td colspan="7" style="font-weight:700;color:#1565c0"><?= e($lastCat) ?></td></tr>
                <?php endif; ?>
                <tr style="<?= !$item['is_active'] ? 'opacity:.4' : '' ?>">
                    <td></td>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['unit']) ?></td>
                    <td>$<?= number_format($item['default_price']) ?></td>
                    <td>$<?= number_format($item['default_cost']) ?></td>
                    <td><?= $item['is_active'] ? '✓' : '✕' ?></td>
                    <td><a href="/engineering_items.php?action=edit&id=<?= $item['id'] ?>" class="btn btn-sm btn-outline">編輯</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
