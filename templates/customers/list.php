<?php $categoryOptions = CustomerModel::categoryOptions(); ?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>客戶管理 <span style="font-size:.6em;color:#888;font-weight:normal">資料總筆數：<?= number_format($dashboardStats['total'] ?? 0) ?></span></h2>
    <?php if (Auth::hasPermission('customers.manage')): ?>
    <a href="/customers.php?action=create" class="btn btn-primary btn-sm">+ 新增客戶</a>
    <?php endif; ?>
</div>

<!-- 搜尋列 -->
<div class="card mb-2">
    <form method="GET" action="/customers.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group" style="flex:2;min-width:200px">
                <label>搜尋客戶</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword']) ?>" placeholder="客戶名/編號/電話/統編/地址/備註" autofocus>
            </div>
            <div class="form-group">
                <label>客戶分類</label>
                <select name="category" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($categoryOptions as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>承辦業務</label>
                <select name="sales_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($salespeople as $sp): ?>
                    <option value="<?= $sp['id'] ?>" <?= $filters['sales_id'] == $sp['id'] ? 'selected' : '' ?>><?= e($sp['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>分公司</label>
                <select name="source_branch" class="form-control">
                    <option value="">全部</option>
                    <option value="潭子" <?= ($filters['source_branch'] ?? '') === '潭子' ? 'selected' : '' ?>>潭子</option>
                    <option value="員林" <?= ($filters['source_branch'] ?? '') === '員林' ? 'selected' : '' ?>>員林</option>
                    <option value="海線" <?= ($filters['source_branch'] ?? '') === '海線' ? 'selected' : '' ?>>海線</option>
                </select>
            </div>
        </div>
        <div class="filter-row" style="margin-top:8px">
            <div class="form-group">
                <label>完工日期（起）</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>完工日期（迄）</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/customers.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($customers)): ?>
<!-- 搜尋結果 -->
<div class="card mb-2">
    <div class="card-header">搜尋結果 (<?= number_format($totalCount) ?> 筆<?= $totalPages > 1 ? '，第 ' . $currentPageNum . '/' . $totalPages . ' 頁' : '' ?>)</div>

    <div class="staff-cards show-mobile">
        <?php foreach ($customers as $c): ?>
        <div class="staff-card" onclick="location.href='/customers.php?action=view&id=<?= $c['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong>
                    <?= e($c['name']) ?>
                    <?php if (!empty($c['file_count'])): ?><span style="color:#2196F3;font-size:.75em">📎<?= $c['file_count'] ?></span><?php endif; ?>
                    <?php if (!empty($c['is_blacklisted'])): ?><span class="badge" style="background:var(--danger);color:#fff;font-size:.65em">黑名單</span><?php endif; ?>
                    <?php if (!empty($c['has_cases']) && empty($c['has_deal'])): ?><span class="badge" style="background:#999;color:#fff;font-size:.65em">未成交</span><?php endif; ?>
                </strong>
                <span class="badge"><?= e(isset($categoryOptions[$c['category']]) ? $categoryOptions[$c['category']] : '-') ?></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e($c['customer_no'] ?: '') ?></span>
                <span><?= e($c['contact_person'] ?: '-') ?></span>
                <span><?= e($c['phone'] ?: $c['mobile'] ?: '-') ?></span>
            </div>
            <?php if (!empty($c['full_address'])): ?>
            <div class="staff-card-meta"><span><?= e($c['full_address']) ?></span></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead><tr>
                <th>客戶編號</th><th>客戶名稱</th><th>聯絡人</th><th>電話</th><th>施工地址</th><th>完工日期</th><th>保固日期</th><th>分類</th><th>操作</th>
            </tr></thead>
            <tbody>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td><a href="/customers.php?action=view&id=<?= $c['id'] ?>"><?= e($c['customer_no'] ?: '-') ?></a></td>
                    <td>
                        <?= e($c['name']) ?>
                        <?php if (!empty($c['file_count'])): ?>
                        <span title="<?= $c['file_count'] ?> 個附件" style="color:#2196F3;font-size:.8em;cursor:help">📎<?= $c['file_count'] ?></span>
                        <?php endif; ?>
                        <?php if (!empty($c['is_blacklisted'])): ?>
                        <span class="badge" style="background:var(--danger);color:#fff;font-size:.7em;vertical-align:middle">黑名單</span>
                        <?php endif; ?>
                        <?php if (!empty($c['has_cases']) && empty($c['has_deal'])): ?>
                        <span class="badge" style="background:#999;color:#fff;font-size:.7em;vertical-align:middle">未成交</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($c['contact_person'] ?: '-') ?></td>
                    <td><?= e($c['phone'] ?: ($c['mobile'] ?: '-')) ?></td>
                    <td><?= e($c['full_address'] ?: '-') ?></td>
                    <td><?= e($c['completion_date'] ?: '-') ?></td>
                    <td><?= e($c['warranty_date'] ?: '-') ?></td>
                    <td><?= e(isset($categoryOptions[$c['category']]) ? $categoryOptions[$c['category']] : '-') ?></td>
                    <td>
                        <a href="/customers.php?action=view&id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">檢視</a>
                        <?php if (Auth::hasPermission('customers.manage')): ?>
                        <a href="/customers.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination-bar">
        <?php
        // Build base URL for pagination
        $pageParams = array();
        foreach ($filters as $fk => $fv) {
            if ($fv !== '' && $fv !== null) $pageParams[] = $fk . '=' . urlencode($fv);
        }
        $baseUrl = '/customers.php?' . implode('&', $pageParams);
        ?>
        <?php if ($currentPageNum > 1): ?>
        <a href="<?= $baseUrl ?>&page=<?= $currentPageNum - 1 ?>" class="btn btn-outline btn-sm">&laquo; 上一頁</a>
        <?php endif; ?>

        <?php
        $startP = max(1, $currentPageNum - 3);
        $endP = min($totalPages, $currentPageNum + 3);
        if ($startP > 1): ?>
            <a href="<?= $baseUrl ?>&page=1" class="btn btn-outline btn-sm">1</a>
            <?php if ($startP > 2): ?><span class="page-dots">...</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $startP; $p <= $endP; $p++): ?>
        <?php if ($p == $currentPageNum): ?>
            <span class="btn btn-primary btn-sm"><?= $p ?></span>
        <?php else: ?>
            <a href="<?= $baseUrl ?>&page=<?= $p ?>" class="btn btn-outline btn-sm"><?= $p ?></a>
        <?php endif; ?>
        <?php endfor; ?>

        <?php if ($endP < $totalPages): ?>
            <?php if ($endP < $totalPages - 1): ?><span class="page-dots">...</span><?php endif; ?>
            <a href="<?= $baseUrl ?>&page=<?= $totalPages ?>" class="btn btn-outline btn-sm"><?= $totalPages ?></a>
        <?php endif; ?>

        <?php if ($currentPageNum < $totalPages): ?>
        <a href="<?= $baseUrl ?>&page=<?= $currentPageNum + 1 ?>" class="btn btn-outline btn-sm">下一頁 &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($hasSearch): ?>
<div class="card mb-2">
    <p class="text-muted text-center" style="padding:24px">查無符合條件的客戶</p>
</div>
<?php endif; ?>

<?php if (!$hasSearch && !empty($dashboardStats)): ?>
<!-- 儀表板 -->
<div class="cust-stats-row mb-2">
    <div class="cust-stat-card">
        <div class="cust-stat-icon">💰</div>
        <div class="cust-stat-value"><?= number_format($dashboardStats['with_deals']) ?></div>
        <div class="cust-stat-label">成交客戶</div>
    </div>
    <a href="/customers.php?has_relations=1" class="cust-stat-card" style="text-decoration:none;color:inherit;cursor:pointer">
        <div class="cust-stat-icon">🔗</div>
        <div class="cust-stat-value"><?= number_format($dashboardStats['with_relations']) ?></div>
        <div class="cust-stat-label">有關聯客戶</div>
    </a>
    <?php if (!empty($dashboardStats['blacklisted'])): ?>
    <div class="cust-stat-card" style="border-color:var(--danger)">
        <div class="cust-stat-icon">⚠️</div>
        <div class="cust-stat-value" style="color:var(--danger)"><?= $dashboardStats['blacklisted'] ?></div>
        <div class="cust-stat-label">黑名單</div>
    </div>
    <?php endif; ?>
    <div class="cust-stat-card">
        <div class="cust-stat-icon">🏢</div>
        <div class="cust-stat-value"><?= count($dashboardStats['by_source']) ?></div>
        <div class="cust-stat-label">資料來源</div>
    </div>
</div>

<div class="cust-dashboard-grid">
    <!-- 分類統計 -->
    <div class="card">
        <div class="card-header">客戶分類</div>
        <?php if (empty($dashboardStats['by_category'])): ?>
            <p class="text-muted text-center" style="padding:16px">尚無分類資料</p>
        <?php else: ?>
        <div class="cust-bar-list">
            <?php
            $maxCat = max(array_column($dashboardStats['by_category'], 'cnt'));
            foreach ($dashboardStats['by_category'] as $cat):
                $pct = $maxCat > 0 ? round($cat['cnt'] / $maxCat * 100) : 0;
                $catLabel = isset($categoryOptions[$cat['category']]) ? $categoryOptions[$cat['category']] : $cat['category'];
            ?>
            <div class="cust-bar-item">
                <div class="cust-bar-label">
                    <a href="/customers.php?category=<?= urlencode($cat['category']) ?>"><?= e($catLabel) ?></a>
                    <span><?= number_format($cat['cnt']) ?></span>
                </div>
                <div class="cust-bar-bg"><div class="cust-bar-fill" style="width:<?= $pct ?>%"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 最近成交 -->
    <div class="card">
        <div class="card-header">最近成交客戶</div>
        <?php if (empty($dashboardStats['recent'])): ?>
            <p class="text-muted text-center" style="padding:16px">尚無客戶</p>
        <?php else: ?>
        <div class="cust-recent-list">
            <?php foreach ($dashboardStats['recent'] as $r): ?>
            <a href="/customers.php?action=view&id=<?= $r['id'] ?>" class="cust-recent-item">
                <div class="cust-recent-name"><?= e($r['name']) ?></div>
                <div class="cust-recent-meta">
                    <span><?= e($r['customer_no'] ?: '-') ?></span>
                    <span><?= $r['created_at'] ? date('m/d', strtotime($r['created_at'])) : '-' ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 年度統計 -->
    <div class="card">
        <div class="card-header">年度成交客戶</div>
        <?php if (empty($dashboardStats['by_year'])): ?>
            <p class="text-muted text-center" style="padding:16px">尚無年度資料</p>
        <?php else: ?>
        <div class="cust-bar-list">
            <?php
            $maxYr = max(array_column($dashboardStats['by_year'], 'cnt'));
            foreach ($dashboardStats['by_year'] as $yr):
                $pct = $maxYr > 0 ? round($yr['cnt'] / $maxYr * 100) : 0;
                $isExcel = (strpos($yr['yr'], 'Excel') !== false);
                $barColor = $isExcel ? '#999' : ($yr['yr'] === '2026' ? 'var(--primary,#2196F3)' : 'var(--success,#4CAF50)');
                $label = $isExcel ? '2026年 (Excel匯入)' : e($yr['yr']) . ' 年' . ($yr['yr'] === '2026' ? ' (案件成交)' : '');
            ?>
            <?php
                if ($isExcel) {
                    $yearLink = '/customers.php?excel_2026=1';
                } elseif ($yr['yr'] === '2026') {
                    $yearLink = '/cases.php?status=closed&date_from=2026-01-01&date_to=2026-12-31';
                } else {
                    $yearLink = '/customers.php?date_from=' . $yr['yr'] . '-01-01&date_to=' . $yr['yr'] . '-12-31';
                }
            ?>
            <a href="<?= $yearLink ?>" class="cust-bar-item" style="text-decoration:none;color:inherit;cursor:pointer">
                <div class="cust-bar-label">
                    <span><?= $label ?></span>
                    <span><?= number_format($yr['cnt']) ?></span>
                </div>
                <div class="cust-bar-bg"><div class="cust-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 分公司統計 -->
    <div class="card">
        <div class="card-header">各分公司客戶</div>
        <?php if (empty($dashboardStats['by_branch'])): ?>
            <p class="text-muted text-center" style="padding:16px">尚無資料</p>
        <?php else: ?>
        <div class="cust-bar-list">
            <?php
            $maxBr = max(array_column($dashboardStats['by_branch'], 'cnt'));
            foreach ($dashboardStats['by_branch'] as $br):
                $pct = $maxBr > 0 ? round($br['cnt'] / $maxBr * 100) : 0;
            ?>
            <div class="cust-bar-item">
                <div class="cust-bar-label">
                    <span><?= e($br['branch']) ?></span>
                    <span><?= number_format($br['cnt']) ?></span>
                </div>
                <div class="cust-bar-bg"><div class="cust-bar-fill" style="width:<?= $pct ?>%;background:#48BB78"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 來源統計 -->
    <div class="card">
        <div class="card-header">資料來源</div>
        <?php if (empty($dashboardStats['by_source'])): ?>
            <p class="text-muted text-center" style="padding:16px">尚無來源資料</p>
        <?php else: ?>
        <div class="cust-bar-list">
            <?php
            $maxSrc = max(array_column($dashboardStats['by_source'], 'cnt'));
            foreach ($dashboardStats['by_source'] as $src):
                $pct = $maxSrc > 0 ? round($src['cnt'] / $maxSrc * 100) : 0;
                $srcLabel = $src['source'];
                if ($srcLabel === '手動建立') $srcLabel = '🖊️ 手動建立';
                elseif (strpos($srcLabel, 'erp_') === 0) $srcLabel = '📥 ERP ' . str_replace('erp_', '', $srcLabel);
            ?>
            <div class="cust-bar-item">
                <div class="cust-bar-label">
                    <a href="/customers.php?import_source=<?= urlencode($src['source'] === '手動建立' ? '' : $src['source']) ?>"><?= e($srcLabel) ?></a>
                    <span><?= number_format($src['cnt']) ?></span>
                </div>
                <div class="cust-bar-bg"><div class="cust-bar-fill" style="width:<?= $pct ?>%;background:var(--info,#2196F3)"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }

.pagination-bar { display: flex; justify-content: center; align-items: center; gap: 4px; padding: 12px; flex-wrap: wrap; }
.page-dots { color: var(--gray-400); padding: 0 4px; }

.cust-stats-row { display: flex; gap: 12px; flex-wrap: wrap; }
.cust-stat-card { background: #fff; border-radius: 8px; padding: 16px 20px; flex: 1; min-width: 120px; box-shadow: 0 1px 3px rgba(0,0,0,.08); text-align: center; cursor: pointer; transition: box-shadow .2s, transform .2s; }
a.cust-stat-card:hover, .cust-stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.15); transform: translateY(-2px); }
.cust-stat-icon { font-size: 1.5rem; margin-bottom: 4px; }
.cust-stat-value { font-size: 1.6rem; font-weight: 700; color: var(--gray-800); }
.cust-stat-label { font-size: .8rem; color: var(--gray-500); }

.cust-dashboard-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }

.cust-bar-list { padding: 12px; }
.cust-bar-item { margin-bottom: 10px; }
.cust-bar-label { display: flex; justify-content: space-between; font-size: .85rem; margin-bottom: 3px; }
.cust-bar-label a { color: var(--primary); text-decoration: none; }
.cust-bar-label a:hover { text-decoration: underline; }
.cust-bar-bg { background: var(--gray-100, #f3f4f6); border-radius: 4px; height: 8px; overflow: hidden; }
.cust-bar-fill { background: var(--primary); height: 100%; border-radius: 4px; transition: width .3s; }

.cust-recent-list { padding: 4px 12px; }
.cust-recent-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--gray-100, #f3f4f6); text-decoration: none; color: inherit; }
.cust-recent-item:last-child { border-bottom: none; }
.cust-recent-item:hover { background: var(--gray-50, #f9fafb); }
.cust-recent-name { font-weight: 500; font-size: .9rem; }
.cust-recent-meta { display: flex; gap: 12px; font-size: .8rem; color: var(--gray-500); }

.staff-cards { display: flex; flex-direction: column; gap: 8px; padding: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: box-shadow .15s; }
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }

@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
@media (max-width: 767px) {
    .cust-stats-row { flex-direction: column; }
    .cust-stat-card { min-width: auto; }
    .cust-dashboard-grid { grid-template-columns: 1fr; }
}
</style>
