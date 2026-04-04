<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>廠商管理 <small class="text-muted">(<?= count($records) ?>)</small></h2>
    <a href="/vendors.php?action=create" class="btn btn-primary btn-sm">+ 新增廠商</a>
</div>

<div class="card">
    <form method="GET" action="/vendors.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="廠商名稱/聯絡人">
            </div>
            <div class="form-group">
                <label>類別</label>
                <select name="category" class="form-control">
                    <option value="">全部類別</option>
                    <?php
                    $categories = array();
                    foreach ($records as $_r) {
                        if (!empty($_r['category']) && !in_array($_r['category'], $categories)) {
                            $categories[] = $_r['category'];
                        }
                    }
                    sort($categories);
                    foreach ($categories as $_cat): ?>
                    <option value="<?= e($_cat) ?>" <?= (!empty($filters['category']) && $filters['category'] === $_cat) ? 'selected' : '' ?>><?= e($_cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/vendors.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無廠商資料</p>
    <?php else: ?>
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" onclick="location.href='/vendors.php?action=edit&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['name']) ? $r['name'] : '-') ?></strong>
                <?php if (!empty($r['category'])): ?>
                <span class="badge"><?= e($r['category']) ?></span>
                <?php endif; ?>
            </div>
            <div class="staff-card-meta">
                <?php if (!empty($r['phone'])): ?>
                <span><?= e($r['phone']) ?></span>
                <?php endif; ?>
                <?php if (!empty($r['contact_person'])): ?>
                <span><?= e($r['contact_person']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($r['address'])): ?>
            <div class="staff-card-meta">
                <span><?= e($r['address']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>編號</th>
                    <th>廠商名稱</th>
                    <th>簡稱</th>
                    <th>類別</th>
                    <th>統一編號</th>
                    <th>聯絡窗口</th>
                    <th>電話</th>
                    <th>付款方式</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><?= e(!empty($r['vendor_code']) ? $r['vendor_code'] : $r['id']) ?></td>
                    <td><a href="/vendors.php?action=edit&id=<?= $r['id'] ?>"><?= e(!empty($r['name']) ? $r['name'] : '-') ?></a></td>
                    <td><?= e(!empty($r['short_name']) ? $r['short_name'] : '') ?></td>
                    <td><?= e(!empty($r['category']) ? $r['category'] : '') ?></td>
                    <td><?= e(!empty($r['tax_id']) ? $r['tax_id'] : '') ?></td>
                    <td><?= e(!empty($r['contact_person']) ? $r['contact_person'] : '') ?></td>
                    <td><?= e(!empty($r['phone']) ? $r['phone'] : '') ?></td>
                    <td><?= e(!empty($r['payment_method']) ? $r['payment_method'] : '') ?></td>
                    <td>
                        <a href="/vendors.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                        <a href="/vendors.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('確定要停用此廠商嗎？')">停用</a>
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
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: box-shadow .15s; }
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.badge { display: inline-block; padding: 2px 8px; font-size: .75rem; border-radius: 10px; background: var(--primary-light, #e8f0fe); color: var(--primary, #1a73e8); }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
