<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>會計科目管理 <span class="text-muted" style="font-size:1rem"><?= count($accounts) ?> 筆</span></h2>
    <?php if ($canManage): ?>
    <a href="/chart_of_accounts.php?action=create" class="btn btn-primary">+ 新增科目</a>
    <?php endif; ?>
</div>

<!-- 篩選 -->
<div class="card mb-2" style="padding:12px">
    <form method="GET" class="d-flex gap-1 flex-wrap align-center">
        <select name="level1" class="form-control" style="max-width:200px">
            <option value="">全部分類</option>
            <?php foreach ($level1Options as $l1): ?>
            <option value="<?= e($l1) ?>" <?= $level1Filter === $l1 ? 'selected' : '' ?>><?= e($l1) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="keyword" class="form-control" style="max-width:250px" placeholder="搜尋科目編號/名稱..." value="<?= e($keyword) ?>">
        <label class="checkbox-label" style="font-size:.85rem">
            <input type="checkbox" name="show_inactive" value="1" <?= $showInactive ? 'checked' : '' ?>>
            <span>顯示停用</span>
        </label>
        <button class="btn btn-primary" style="font-size:.85rem">搜尋</button>
        <a href="/chart_of_accounts.php" class="btn btn-outline" style="font-size:.85rem">清除</a>
    </form>
</div>

<!-- 科目樹狀表格 -->
<div class="card">
    <div class="table-responsive">
        <table class="table" style="font-size:.9rem">
            <thead>
                <tr>
                    <th style="width:80px">科目編號</th>
                    <th>一階科目</th>
                    <th>二階科目</th>
                    <th>三階科目</th>
                    <th>四階科目</th>
                    <th style="width:60px">狀態</th>
                    <?php if ($canManage): ?><th style="width:60px">操作</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $lastLevel1 = '';
                $lastLevel2 = '';
                foreach ($accounts as $a): 
                    $isNewL1 = $a['level1'] !== $lastLevel1;
                    $isNewL2 = $a['level2'] !== $lastLevel2;
                    $lastLevel1 = $a['level1'];
                    $lastLevel2 = $a['level2'];
                ?>
                <?php if ($isNewL1 && !$level1Filter): ?>
                <tr style="background:#e3f2fd">
                    <td colspan="<?= $canManage ? 7 : 6 ?>" style="font-weight:700;color:#1565c0;font-size:.95rem"><?= e($a['level1']) ?></td>
                </tr>
                <?php endif; ?>
                <tr style="<?= !$a['is_active'] ? 'opacity:.4;text-decoration:line-through' : '' ?>">
                    <td><strong><?= e($a['code']) ?></strong></td>
                    <td class="text-muted" style="font-size:.85rem"><?= e($a['level1']) ?></td>
                    <td class="text-muted" style="font-size:.85rem"><?= e($a['level2']) ?></td>
                    <td class="text-muted" style="font-size:.85rem"><?= e($a['level3']) ?></td>
                    <td><strong><?= e($a['name']) ?></strong></td>
                    <td><?= $a['is_active'] ? '<span style="color:green">✓</span>' : '<span style="color:red">✕</span>' ?></td>
                    <?php if ($canManage): ?>
                    <td><a href="/chart_of_accounts.php?action=edit&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline">編輯</a></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
