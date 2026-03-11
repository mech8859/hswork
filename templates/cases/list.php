<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>案件管理</h2>
    <?php if (Auth::hasPermission('cases.manage') || Auth::hasPermission('cases.own')): ?>
    <a href="/cases.php?action=create" class="btn btn-primary btn-sm">+ 新增案件</a>
    <?php endif; ?>
</div>

<!-- 篩選 -->
<div class="card">
    <form method="GET" action="/cases.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword']) ?>" placeholder="案件名稱/編號/地址">
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>待處理</option>
                    <option value="ready" <?= $filters['status'] === 'ready' ? 'selected' : '' ?>>可排工</option>
                    <option value="scheduled" <?= $filters['status'] === 'scheduled' ? 'selected' : '' ?>>已排工</option>
                    <option value="in_progress" <?= $filters['status'] === 'in_progress' ? 'selected' : '' ?>>施工中</option>
                    <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>已完工</option>
                </select>
            </div>
            <div class="form-group">
                <label>類型</label>
                <select name="case_type" class="form-control">
                    <option value="">全部</option>
                    <option value="new_install" <?= $filters['case_type'] === 'new_install' ? 'selected' : '' ?>>新裝</option>
                    <option value="maintenance" <?= $filters['case_type'] === 'maintenance' ? 'selected' : '' ?>>保養</option>
                    <option value="repair" <?= $filters['case_type'] === 'repair' ? 'selected' : '' ?>>維修</option>
                    <option value="inspection" <?= $filters['case_type'] === 'inspection' ? 'selected' : '' ?>>勘查</option>
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
                <a href="/cases.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<!-- 案件列表 -->
<div class="card">
    <?php if (empty($result['data'])): ?>
        <p class="text-muted text-center mt-2">目前無案件資料</p>
    <?php else: ?>

    <!-- 手機版卡片 -->
    <div class="case-cards show-mobile">
        <?php foreach ($result['data'] as $row): ?>
        <div class="case-card" onclick="location.href='/cases.php?action=view&id=<?= $row['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e($row['case_number']) ?></strong>
                <span class="badge <?= CaseModel::statusBadge($row['status']) ?>"><?= e(CaseModel::statusLabel($row['status'])) ?></span>
            </div>
            <div class="case-card-title"><?= e($row['title']) ?></div>
            <div class="case-card-meta">
                <span><?= e($row['branch_name']) ?></span>
                <span><?= e(CaseModel::typeLabel($row['case_type'])) ?></span>
                <?php if ($row['sales_name']): ?><span>業務: <?= e($row['sales_name']) ?></span><?php endif; ?>
            </div>
            <?php
            $warnings = get_readiness_warnings($row);
            if (!empty($warnings)):
            ?>
            <div class="readiness-warnings">
                <?php foreach ($warnings as $w): ?>
                <span class="badge badge-warning"><?= e($w) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>案件編號</th>
                    <th>名稱</th>
                    <th>據點</th>
                    <th>類型</th>
                    <th>狀態</th>
                    <th>難度</th>
                    <th>排工條件</th>
                    <th>業務</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $row): ?>
                <tr>
                    <td><a href="/cases.php?action=view&id=<?= $row['id'] ?>"><?= e($row['case_number']) ?></a></td>
                    <td><?= e($row['title']) ?></td>
                    <td><?= e($row['branch_name']) ?></td>
                    <td><?= e(CaseModel::typeLabel($row['case_type'])) ?></td>
                    <td><span class="badge <?= CaseModel::statusBadge($row['status']) ?>"><?= e(CaseModel::statusLabel($row['status'])) ?></span></td>
                    <td><span class="stars"><?= str_repeat('&#9733;', $row['difficulty']) ?><?= str_repeat('&#9734;', 5 - $row['difficulty']) ?></span></td>
                    <td>
                        <?php
                        $warnings = get_readiness_warnings($row);
                        if (empty($warnings)): ?>
                            <span class="badge badge-success">已備齊</span>
                        <?php else: ?>
                            <?php foreach ($warnings as $w): ?>
                            <span class="badge badge-warning"><?= e($w) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= e($row['sales_name'] ?? '-') ?></td>
                    <td>
                        <a href="/cases.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 分頁 -->
    <?php if ($result['lastPage'] > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $result['lastPage']; $i++): ?>
            <?php
            $qs = $_GET;
            $qs['page'] = $i;
            $url = '/cases.php?' . http_build_query($qs);
            ?>
            <a href="<?= $url ?>" class="btn btn-sm <?= $i === $result['page'] ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 140px; margin-bottom: 0; }
.case-cards { display: flex; flex-direction: column; gap: 8px; }
.case-card {
    border: 1px solid var(--gray-200); border-radius: var(--radius);
    padding: 12px; cursor: pointer; transition: box-shadow .15s;
}
.case-card:hover { box-shadow: var(--shadow); }
.case-card-title { font-weight: 500; margin: 4px 0; }
.case-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; flex-wrap: wrap; }
.readiness-warnings { margin-top: 6px; display: flex; gap: 4px; flex-wrap: wrap; }
.pagination { display: flex; gap: 4px; justify-content: center; margin-top: 16px; flex-wrap: wrap; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) {
    .show-mobile { display: none !important; }
    .hide-mobile { display: block !important; }
}
</style>
