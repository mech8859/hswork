<?php
$progressOptions = CaseModel::progressOptions();
$caseTypeOptions = CaseModel::caseTypeOptions();
$subStatusOptions = CaseModel::subStatusOptions();
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>案件管理</h2>
    <?php if (Auth::hasPermission('cases.manage') || Auth::hasPermission('cases.own')): ?>
    <a href="/cases.php?action=create" class="btn btn-primary btn-sm">+ 新增案件</a>
    <?php endif; ?>
</div>

<!-- 快捷篩選按鈕 -->
<div class="filter-pills mb-1">
    <!-- 1. 案別 -->
    <div class="pill-group">
        <span class="pill-label">案別</span>
        <?php
        $baseQS1 = array();
        if ($filters['status']) $baseQS1['status'] = $filters['status'];
        if ($filters['sub_status']) $baseQS1['sub_status'] = $filters['sub_status'];
        if ($filters['keyword']) $baseQS1['keyword'] = $filters['keyword'];
        if ($filters['branch_id']) $baseQS1['branch_id'] = $filters['branch_id'];
        if ($filters['sales_id']) $baseQS1['sales_id'] = $filters['sales_id'];
        if (!empty($filters['date_from'])) $baseQS1['date_from'] = $filters['date_from'];
        if (!empty($filters['date_to'])) $baseQS1['date_to'] = $filters['date_to'];
        ?>
        <a href="/cases.php<?= $baseQS1 ? '?' . http_build_query($baseQS1) : '' ?>" class="pill <?= empty($filters['case_type']) ? 'pill-active' : '' ?>">全部</a>
        <?php foreach ($caseTypeOptions as $tv => $tl):
            $qs1 = array_merge($baseQS1, array('case_type' => $tv));
        ?>
        <a href="/cases.php?<?= http_build_query($qs1) ?>" class="pill <?= $filters['case_type'] === $tv ? 'pill-active' : '' ?>"><?= e($tl) ?></a>
        <?php endforeach; ?>
    </div>
    <!-- 2. 狀態 -->
    <div class="pill-group">
        <span class="pill-label">狀態</span>
        <?php
        $baseQS2 = array();
        if ($filters['status']) $baseQS2['status'] = $filters['status'];
        if ($filters['case_type']) $baseQS2['case_type'] = $filters['case_type'];
        if ($filters['keyword']) $baseQS2['keyword'] = $filters['keyword'];
        if ($filters['branch_id']) $baseQS2['branch_id'] = $filters['branch_id'];
        if ($filters['sales_id']) $baseQS2['sales_id'] = $filters['sales_id'];
        if (!empty($filters['date_from'])) $baseQS2['date_from'] = $filters['date_from'];
        if (!empty($filters['date_to'])) $baseQS2['date_to'] = $filters['date_to'];
        ?>
        <select class="pill-select" onchange="if(this.value){var qs=<?= htmlspecialchars(json_encode((object)$baseQS2), ENT_QUOTES) ?>;qs.sub_status=this.value;location.href='/cases.php?'+new URLSearchParams(qs).toString();}else{location.href='/cases.php?'+new URLSearchParams(<?= htmlspecialchars(json_encode((object)$baseQS2), ENT_QUOTES) ?>).toString();}">
            <option value="">全部</option>
            <?php foreach ($subStatusOptions as $sk => $sv): ?>
            <option value="<?= e($sk) ?>" <?= $filters['sub_status'] === $sk ? 'selected' : '' ?>><?= e($sv) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <!-- 3. 案件進度 -->
    <div class="pill-group">
        <span class="pill-label">進度</span>
        <?php
        $baseQS3 = array();
        if ($filters['case_type']) $baseQS3['case_type'] = $filters['case_type'];
        if ($filters['sub_status']) $baseQS3['sub_status'] = $filters['sub_status'];
        if ($filters['keyword']) $baseQS3['keyword'] = $filters['keyword'];
        if ($filters['branch_id']) $baseQS3['branch_id'] = $filters['branch_id'];
        if ($filters['sales_id']) $baseQS3['sales_id'] = $filters['sales_id'];
        if (!empty($filters['date_from'])) $baseQS3['date_from'] = $filters['date_from'];
        if (!empty($filters['date_to'])) $baseQS3['date_to'] = $filters['date_to'];
        ?>
        <a href="/cases.php<?= $baseQS3 ? '?' . http_build_query($baseQS3) : '' ?>" class="pill <?= empty($filters['status']) ? 'pill-active' : '' ?>">全部</a>
        <?php foreach ($progressOptions as $sv => $sl):
            $qs3 = array_merge($baseQS3, array('status' => $sv));
        ?>
        <a href="/cases.php?<?= http_build_query($qs3) ?>" class="pill <?= ($filters['status'] === $sv || in_array($sv, explode(',', $filters['status'] ?: ''))) ? 'pill-active' : '' ?>"><?= e($sl) ?></a>
        <?php endforeach; ?>
    </div>
    <!-- 4. 業務 -->
    <div class="pill-group">
        <span class="pill-label">業務</span>
        <?php
        $baseQS4 = array();
        if ($filters['status']) $baseQS4['status'] = $filters['status'];
        if ($filters['case_type']) $baseQS4['case_type'] = $filters['case_type'];
        if ($filters['sub_status']) $baseQS4['sub_status'] = $filters['sub_status'];
        if ($filters['keyword']) $baseQS4['keyword'] = $filters['keyword'];
        if ($filters['branch_id']) $baseQS4['branch_id'] = $filters['branch_id'];
        if (!empty($filters['date_from'])) $baseQS4['date_from'] = $filters['date_from'];
        if (!empty($filters['date_to'])) $baseQS4['date_to'] = $filters['date_to'];
        ?>
        <?php
        $selectedSales = array_filter(explode(',', $filters['sales_id']));
        $salesLabel = empty($selectedSales) ? '全部' : count($selectedSales) . '人';
        ?>
        <div class="sales-multi-wrap" style="position:relative;display:inline-block">
            <button type="button" class="pill-select" onclick="toggleSalesDropdown()" id="salesDropBtn" style="cursor:pointer;min-width:80px;text-align:left">
                <?= e($salesLabel) ?> ▾
            </button>
            <div id="salesDropdown" style="display:none;position:absolute;top:100%;left:0;z-index:200;background:#fff;border:1px solid #ddd;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:180px;max-height:320px;overflow-y:auto;padding:4px 0">
                <div style="padding:4px 10px;border-bottom:1px solid #eee">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.85rem;font-weight:600;margin:0">
                        <input type="checkbox" id="salesCheckAll" onchange="toggleSalesAll(this)"> 全選/取消
                    </label>
                </div>
                <?php foreach ($salesUsers as $su): ?>
                <div style="padding:3px 10px">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.85rem;margin:0;white-space:nowrap">
                        <input type="checkbox" class="sales-check" value="<?= $su['id'] ?>" <?= in_array($su['id'], $selectedSales) ? 'checked' : '' ?>> <?= e($su['real_name']) ?>
                    </label>
                </div>
                <?php endforeach; ?>
                <div style="padding:6px 10px;border-top:1px solid #eee;display:flex;gap:6px">
                    <button type="button" class="btn btn-primary btn-sm" style="flex:1;font-size:.8rem" onclick="applySalesFilter()">套用</button>
                    <button type="button" class="btn btn-outline btn-sm" style="flex:1;font-size:.8rem" onclick="clearSalesFilter()">清除</button>
                </div>
            </div>
        </div>
        <script>
        function toggleSalesDropdown() {
            var dd = document.getElementById('salesDropdown');
            dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.sales-multi-wrap')) document.getElementById('salesDropdown').style.display = 'none';
        });
        function toggleSalesAll(el) {
            var checks = document.querySelectorAll('.sales-check');
            for (var i = 0; i < checks.length; i++) checks[i].checked = el.checked;
        }
        function applySalesFilter() {
            var checks = document.querySelectorAll('.sales-check:checked');
            var ids = [];
            for (var i = 0; i < checks.length; i++) ids.push(checks[i].value);
            var qs = <?= json_encode((object)$baseQS4, JSON_UNESCAPED_UNICODE) ?>;
            if (ids.length > 0) qs.sales_id = ids.join(',');
            location.href = '/cases.php?' + new URLSearchParams(qs).toString();
        }
        function clearSalesFilter() {
            var qs = <?= json_encode((object)$baseQS4, JSON_UNESCAPED_UNICODE) ?>;
            location.href = '/cases.php?' + new URLSearchParams(qs).toString();
        }
        function syncSalesBeforeSearch() {
            var checks = document.querySelectorAll('.sales-check:checked');
            var ids = [];
            for (var i = 0; i < checks.length; i++) ids.push(checks[i].value);
            var el = document.getElementById('searchSalesId');
            if (el) el.value = ids.join(',');
        }
        </script>
    </div>
</div>

<!-- 進階篩選 -->
<div class="card">
    <form method="GET" action="/cases.php" class="filter-form">
        <div class="filter-row">
            <?php if (count($branches) > 1): ?>
            <div class="form-group">
                <select name="branch_id" class="form-control">
                    <option value="">全部據點</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $filters['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group" style="flex:2">
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword']) ?>" placeholder="案件/地址/客戶/電話/業務，$金額搜帳款" autocomplete="off">
            </div>
            <div class="form-group">
                <input type="date" name="date_from" class="form-control" value="<?= e(isset($filters['date_from']) ? $filters['date_from'] : '') ?>" placeholder="起始日期" title="進件日期起">
            </div>
            <div style="align-self:center;padding-top:4px">~</div>
            <div class="form-group">
                <input type="date" name="date_to" class="form-control" value="<?= e(isset($filters['date_to']) ? $filters['date_to'] : '') ?>" placeholder="結束日期" title="進件日期迄">
            </div>
            <?php if ($filters['status']): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
            <?php if ($filters['case_type']): ?><input type="hidden" name="case_type" value="<?= e($filters['case_type']) ?>"><?php endif; ?>
            <?php if ($filters['sub_status']): ?><input type="hidden" name="sub_status" value="<?= e($filters['sub_status']) ?>"><?php endif; ?>
            <input type="hidden" name="sales_id" id="searchSalesId" value="<?= e($filters['sales_id']) ?>">
            <div class="form-group" style="align-self:flex-end;flex:0;display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm" onclick="syncSalesBeforeSearch()">搜尋</button>
                <a href="/cases.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
    <div style="display:flex;align-items:center;gap:12px;margin-top:8px;padding:8px 0;border-top:1px solid var(--gray-200)">
        <span style="font-size:.9rem;font-weight:600">共 <?= $result['total'] ?> 筆</span>
        <?php
        $showAmount = !empty($filters['status']) && in_array($filters['status'], array('incomplete','unpaid'));
        if ($showAmount):
        ?>
        <span style="font-size:.9rem;color:var(--primary);font-weight:600">尾款合計：$<?= number_format($result['totalBalance']) ?></span>
        <span style="font-size:.85rem;color:var(--gray-500)">成交金額合計：$<?= number_format($result['totalDeal']) ?></span>
        <?php endif; ?>
    </div>
</div>

<!-- 案件列表 -->
<div class="card">
    <?php if (empty($result['data'])): ?>
        <p class="text-muted text-center mt-2">目前無案件資料</p>
    <?php else: ?>

    <!-- 手機版卡片 -->
    <div class="case-cards show-mobile">
        <?php foreach ($result['data'] as $row): ?>
        <div class="case-card" onclick="location.href='/cases.php?action=edit&id=<?= $row['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e($row['case_number'] ?: '') ?></strong>
                <span class="badge <?= CaseModel::statusBadge($row['status'] ?: '') ?>"><?= e(CaseModel::statusLabel($row['status'] ?: '')) ?></span>
            </div>
            <div class="case-card-title">
                <?= e($row['customer_name'] ?: $row['title'] ?: '') ?>
                <?php if (!empty($row['is_blacklisted'])): ?><span class="badge" style="background:#e53e3e;color:#fff;font-size:.6em">黑名單</span><?php endif; ?>
                <?php if (!empty($row['customer_id']) && empty($row['customer_has_deal'])): ?><span class="badge" style="background:#999;color:#fff;font-size:.6em">未成交</span><?php endif; ?>
            </div>
            <div class="case-card-meta">
                <span><?= e($row['branch_name'] ?: '') ?></span>
                <span><?= e(CaseModel::typeLabel($row['case_type'] ?: '')) ?></span>
                <?php if (!empty($row['sub_status'])): ?><span><?= e($row['sub_status']) ?></span><?php endif; ?>
                <?php if (!empty($row['sales_name'])): ?><span>業務: <?= e($row['sales_name']) ?></span><?php endif; ?>
                <?php if (!empty($row['created_at'])): ?><span><?= date('Y/m/d', strtotime($row['created_at'])) ?></span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th style="white-space:nowrap">進件編號</th>
                    <th style="white-space:nowrap;min-width:80px">進件日期</th>
                    <th style="max-width:250px;min-width:120px">客戶名稱</th>
                    <th>據點</th>
                    <th>案別</th>
                    <th>進度</th>
                    <th>狀態</th>
                    <th>業務</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $row): ?>
                <tr>
                    <td><a href="/cases.php?action=edit&id=<?= $row['id'] ?>"><?= e($row['case_number'] ?: '') ?></a></td>
                    <td style="white-space:nowrap"><?= !empty($row['created_at']) ? date('Y/m/d', strtotime($row['created_at'])) : '-' ?><?php if (!empty($row['updated_at']) && $row['updated_at'] !== $row['created_at']): ?><br><span style="font-size:.7rem;color:#aaa"><?= date('m/d H:i', strtotime($row['updated_at'])) ?></span><?php endif; ?></td>
                    <td>
                        <a href="/cases.php?action=edit&id=<?= $row['id'] ?>"><?= e($row['customer_name'] ?: $row['title'] ?: '') ?></a>
                        <?php if (!empty($row['is_blacklisted'])): ?><span class="badge" style="background:#e53e3e;color:#fff;font-size:.65em">黑名單</span><?php endif; ?>
                        <?php if (!empty($row['customer_id']) && empty($row['customer_has_deal'])): ?><span class="badge" style="background:#999;color:#fff;font-size:.65em">未成交</span><?php endif; ?>
                        <?php if (!empty($filters['keyword'])):
                            $details = array();
                            if (!empty($row['address'])) $details[] = $row['address'];
                            $phones = array();
                            if (!empty($row['customer_phone'])) $phones[] = $row['customer_phone'];
                            if (!empty($row['customer_mobile'])) $phones[] = $row['customer_mobile'];
                            if ($phones) $details[] = implode(' / ', $phones);
                            if ($details): ?>
                        <div style="font-size:.75rem;color:#888;margin-top:2px;line-height:1.3"><?= e(implode(' | ', $details)) ?></div>
                        <?php endif; endif; ?>
                    </td>
                    <td><?= e($row['branch_name'] ?: '') ?></td>
                    <td><?= e(CaseModel::typeLabel($row['case_type'] ?: '')) ?></td>
                    <td><span class="badge <?= CaseModel::statusBadge($row['status'] ?: '') ?>"><?= e(CaseModel::statusLabel($row['status'] ?: '')) ?></span></td>
                    <td><?= e($row['sub_status'] ?: '-') ?></td>
                    <td><?= e($row['sales_name'] ?: '-') ?></td>
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
.filter-pills { display: flex; flex-direction: column; gap: 8px; position: relative; z-index: 10; }
.pill-group { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pill-label { font-size: .8rem; color: var(--gray-500); font-weight: 600; min-width: 32px; }
.pill {
    display: inline-block; padding: 4px 12px; border-radius: 16px; font-size: .8rem;
    background: var(--gray-100); color: var(--gray-700); text-decoration: none;
    transition: all .15s;
}
.pill:hover { background: var(--gray-200); }
.pill-active { background: var(--primary); color: #fff; }
.pill-active:hover { background: var(--primary); }
.pill-select {
    padding: 4px 8px; border-radius: 16px; font-size: .8rem;
    border: 1px solid var(--gray-300); background: var(--gray-100); color: var(--gray-700);
    cursor: pointer; outline: none; appearance: auto;
}
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
.pagination { display: flex; gap: 4px; justify-content: center; margin-top: 16px; flex-wrap: wrap; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) {
    .show-mobile { display: none !important; }
    .hide-mobile { display: block !important; }
}
</style>
