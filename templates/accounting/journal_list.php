<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>傳票管理 <small style="font-size:.55em;color:#666;font-weight:normal">共 <?= number_format($totalCount) ?> 筆</small></h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/accounting.php?action=ledger" class="btn btn-secondary">總帳查詢</a>
        <a href="/accounting.php?action=trial_balance" class="btn btn-secondary">試算表</a>
        <?php if ($canManage): ?>
        <a href="/accounting.php?action=journal_create" class="btn btn-primary">+ 新增傳票</a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:16px">
    <div class="card" style="padding:12px;text-align:center">
        <div style="font-size:1.5em;font-weight:bold;color:var(--warning)"><?= $stats['draft'] ?></div>
        <div style="font-size:0.85em;color:#666">草稿</div>
    </div>
    <div class="card" style="padding:12px;text-align:center">
        <div style="font-size:1.5em;font-weight:bold;color:var(--success)"><?= $stats['posted'] ?></div>
        <div style="font-size:0.85em;color:#666">已過帳</div>
    </div>
    <div class="card" style="padding:12px;text-align:center">
        <div style="font-size:1.5em;font-weight:bold;color:var(--danger)"><?= $stats['voided'] ?></div>
        <div style="font-size:0.85em;color:#666">已作廢</div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:16px;padding:12px">
    <form method="get" action="/accounting.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="action" value="journals">
        <input type="text" name="keyword" value="<?= e($filters['keyword']) ?>" placeholder="搜尋傳票號/備註" class="form-control" style="width:180px">
        <select name="status" class="form-control" style="width:120px">
            <option value="">全部狀態</option>
            <?php foreach ($statusOptions as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="voucher_type" class="form-control" style="width:130px">
            <option value="">全部類型</option>
            <?php foreach ($voucherTypeOptions as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $filters['voucher_type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="created_by" class="form-control" style="width:140px">
            <option value="">全部建立者</option>
            <?php foreach ($creators as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (string)$filters['created_by'] === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['real_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="form-control" style="width:140px">
        <span>~</span>
        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="form-control" style="width:140px">
        <select name="sort" class="form-control" style="width:110px" title="排序">
            <option value="desc" <?= $filters['sort'] === 'desc' ? 'selected' : '' ?>>新 → 舊</option>
            <option value="asc"  <?= $filters['sort'] === 'asc'  ? 'selected' : '' ?>>舊 → 新</option>
        </select>
        <button type="submit" class="btn btn-secondary">篩選</button>
    </form>
</div>

<!-- Journal Entries Table -->
<div class="card" style="overflow-x:auto">
    <table class="data-table" style="width:100%; table-layout:fixed;">
        <colgroup>
            <col style="width:32px">
            <col style="width:110px">
            <col style="width:180px">
            <col>
            <col style="width:110px">
            <col style="width:110px">
            <col style="width:80px">
            <col style="width:80px">
        </colgroup>
        <thead>
            <tr>
                <th></th>
                <th>日期</th>
                <th>傳票號碼</th>
                <th>備註</th>
                <th style="text-align:right">借方合計</th>
                <th style="text-align:right">貸方合計</th>
                <th>狀態</th>
                <th>建立者</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($entries)): ?>
            <tr><td colspan="8" style="text-align:center;padding:20px;color:#999">尚無傳票資料</td></tr>
            <?php endif; ?>
            <?php foreach ($entries as $e2): ?>
            <?php
                $statusClass = '';
                if ($e2['status'] === 'draft') $statusClass = 'color:var(--warning)';
                elseif ($e2['status'] === 'posted') $statusClass = 'color:var(--success)';
                elseif ($e2['status'] === 'voided') $statusClass = 'color:var(--danger);text-decoration:line-through';
                $isStar = !empty($e2['is_starred']);
            ?>
            <tr style="<?= $e2['status'] === 'voided' ? 'opacity:0.6' : '' ?>">
                <td class="text-center"><span class="star-toggle <?= $isStar ? 'is-on' : '' ?>" data-id="<?= (int)$e2['id'] ?>" onclick="toggleStarJournal(this)" title="標記">&#9733;</span></td>
                <td><?= e(format_date($e2['voucher_date'])) ?></td>
                <td style="white-space:nowrap"><a href="/accounting.php?action=journal_view&id=<?= $e2['id'] ?>" style="font-weight:bold"><?= e($e2['voucher_number']) ?></a></td>
                <td><?= e(mb_substr($e2['description'], 0, 50)) ?><?= mb_strlen($e2['description']) > 50 ? '...' : '' ?></td>
                <td style="text-align:right"><?= number_format($e2['total_debit']) ?></td>
                <td style="text-align:right"><?= number_format($e2['total_credit']) ?></td>
                <td><span style="<?= $statusClass ?>;font-weight:bold"><?= isset($statusOptions[$e2['status']]) ? e($statusOptions[$e2['status']]) : e($e2['status']) ?></span></td>
                <td><?= e($e2['created_by_name']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1):
    // 建構當前篩選的 URL 參數（不含 page）
    $qs = $_GET;
    unset($qs['page']);
    $baseUrl = '/accounting.php?' . http_build_query($qs);
?>
<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin:12px 0;font-size:.9rem">
    <?php if ($page > 1): ?>
    <a href="<?= e($baseUrl) ?>&page=1" class="btn btn-sm btn-outline">« 首頁</a>
    <a href="<?= e($baseUrl) ?>&page=<?= $page - 1 ?>" class="btn btn-sm btn-outline">‹ 上一頁</a>
    <?php endif; ?>
    <span style="color:#666;padding:0 8px">第 <?= $page ?> / <?= $totalPages ?> 頁（共 <?= number_format($totalCount) ?> 筆）</span>
    <?php if ($page < $totalPages): ?>
    <a href="<?= e($baseUrl) ?>&page=<?= $page + 1 ?>" class="btn btn-sm btn-outline">下一頁 ›</a>
    <a href="<?= e($baseUrl) ?>&page=<?= $totalPages ?>" class="btn btn-sm btn-outline">尾頁 »</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
.star-toggle { display:inline-block; cursor:pointer; font-size:1.2rem; color:#d0d0d0; transition:color .15s,transform .15s; user-select:none; line-height:1; }
.star-toggle:hover { color:#f1c40f; transform:scale(1.15); }
.star-toggle.is-on { color:#f1c40f; }
.star-toggle.saving { opacity:.5; pointer-events:none; }
</style>
<script>
function toggleStarJournal(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id'); if (!id) return;
    el.classList.add('saving');
    var fd = new FormData(); fd.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/accounting.php?action=toggle_star_journal');
    xhr.onload = function() { el.classList.remove('saving'); try { var res = JSON.parse(xhr.responseText); if (res.error) { alert(res.error); return; } el.classList.toggle('is-on', !!res.starred); } catch (e) { alert('回應錯誤'); } };
    xhr.onerror = function() { el.classList.remove('saving'); alert('網路錯誤'); };
    xhr.send(fd);
}
</script>
