<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>五星評價統計</h2>
    <div class="d-flex align-center gap-1">
        <span class="text-muted"><?= isset($result['total']) ? $result['total'] : count($records) ?> 筆</span>
        <?php if ($canManage): ?>
        <a href="/reviews.php?action=create" class="btn btn-primary btn-sm">+ 新增</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="GET" action="/reviews.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= e($b['id']) ?>" <?= (!empty($filters['branch_id']) && $filters['branch_id'] == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>起始日期</label>
                <input type="date" max="2099-12-31" name="date_from" class="form-control" value="<?= e(!empty($filters['date_from']) ? $filters['date_from'] : '') ?>">
            </div>
            <div class="form-group">
                <label>結束日期</label>
                <input type="date" max="2099-12-31" name="date_to" class="form-control" value="<?= e(!empty($filters['date_to']) ? $filters['date_to'] : '') ?>">
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="編號/客戶/評價人/原因" autocomplete="off">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/reviews.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無資料</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>編號</th>
                    <th>日期</th>
                    <th>客戶名稱</th>
                    <th>Google評價人</th>
                    <th>施工人員</th>
                    <th>分公司</th>
                    <th>獎金發放日</th>
                    <th>不符原因</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <?php
                    $engNames = $model->decodeEngineerNames($r['engineer_ids'], $engineerNameMap);
                    $engText = !empty($engNames) ? implode('、', $engNames) : '-';
                    if (mb_strlen($engText) > 20) $engText = mb_substr($engText, 0, 18) . '…';
                    $reasonText = !empty($r['reason']) ? $r['reason'] : '';
                    if (mb_strlen($reasonText) > 20) $reasonText = mb_substr($reasonText, 0, 18) . '…';
                ?>
                <tr>
                    <td style="font-weight:600;color:#1a73e8"><?= e(!empty($r['review_number']) ? $r['review_number'] : '-') ?></td>
                    <td><?= e(!empty($r['review_date']) ? $r['review_date'] : '-') ?></td>
                    <td><?= e(!empty($r['customer_name']) ? $r['customer_name'] : '-') ?></td>
                    <td><?= e(!empty($r['google_reviewer_name']) ? $r['google_reviewer_name'] : '-') ?></td>
                    <td style="font-size:.85rem"><?= e($engText) ?></td>
                    <td><?= e(!empty($r['branch_name']) ? $r['branch_name'] : '-') ?></td>
                    <td><?= e(!empty($r['bonus_payment_date']) ? $r['bonus_payment_date'] : '-') ?></td>
                    <td style="font-size:.85rem;color:#e53935"><?= e($reasonText ?: '-') ?></td>
                    <td>
                        <?php if ($canManage): ?>
                        <a href="/reviews.php?action=edit&id=<?= (int)$r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../layouts/pagination.php'; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
</style>
