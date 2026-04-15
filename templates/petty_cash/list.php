<?php $typeOptions = FinanceModel::incomeExpenseOptions(); ?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div class="d-flex align-center gap-1">
        <h2>零用金管理</h2>
        <button type="button" class="btn btn-success btn-sm" onclick="toggleAddForm()">+ 新增</button>
    </div>
    <span class="text-muted"><?= isset($result['total']) ? $result['total'] : count($records) ?> 筆</span>
</div>

<!-- 新增表單 -->
<div id="addFormCard" class="card mb-2" style="display:none; background:#fafafa;">
    <form method="POST" action="/petty_cash.php">
        <?= csrf_field() ?>
        <div class="filter-row">
            <div class="form-group">
                <label>收支別 <span style="color:red">*</span></label>
                <select name="type" class="form-control" required>
                    <option value="支出">支出</option>
                    <option value="收入">收入</option>
                </select>
            </div>
            <div class="form-group">
                <label>收支日期 <span style="color:red">*</span></label>
                <input type="date" max="2099-12-31" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>金額 <span style="color:red">*</span></label>
                <input type="number" name="amount" class="form-control" min="0" step="1" required placeholder="0">
            </div>
            <div class="form-group">
                <label>有無發票</label>
                <select name="has_invoice" id="pcHasInvoice" class="form-control" onchange="toggleInvoiceInfo()">
                    <option value="">-</option>
                    <option value="有發票">有發票</option>
                    <option value="無發票">無發票</option>
                </select>
            </div>
            <div class="form-group" id="pcInvoiceInfoGroup" style="display:none">
                <label>發票號碼</label>
                <input type="text" name="invoice_info" class="form-control" placeholder="發票號碼">
            </div>
            <div class="form-group">
                <label>用途說明</label>
                <input type="text" name="description" class="form-control" placeholder="用途說明" autocomplete="off">
            </div>
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= e($b['id']) ?>" <?= $b['id'] == 21 ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">儲存</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="toggleAddForm()">取消</button>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($branchBalances)): ?>
<div class="d-flex flex-wrap gap-1 mb-2">
    <?php foreach ($branchBalances as $bb): ?>
    <div class="card" style="flex:1; min-width:140px; padding:.8rem 1rem; text-align:center;">
        <div class="text-muted" style="font-size:.75rem; margin-bottom:.3rem"><?= e($bb['branch_name']) ?></div>
        <div style="font-size:1.1rem; font-weight:700; color:<?= $bb['balance'] >= 0 ? 'var(--primary)' : 'var(--danger)' ?>">
            $<?= number_format($bb['balance']) ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <form method="GET" action="/petty_cash.php" class="filter-form">
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
                <label>收支別</label>
                <select name="type" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($typeOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= (!empty($filters['type']) && $filters['type'] === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
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
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="用途說明" autocomplete="off">
            </div>
            <div class="form-group">
                <label>排序</label>
                <select name="sort" class="form-control">
                    <option value="desc" <?= (empty($filters['sort']) || $filters['sort'] === 'desc') ? 'selected' : '' ?>>新→舊</option>
                    <option value="asc" <?= (!empty($filters['sort']) && $filters['sort'] === 'asc') ? 'selected' : '' ?>>舊→新</option>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/petty_cash.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無資料</p>
    <?php else: ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" style="cursor:pointer" onclick="location.href='/petty_cash.php?action=edit&id=<?= e($r['id']) ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['entry_number']) ? $r['entry_number'] : '-') ?></strong>
                <span class="text-muted" style="font-size:.8rem"><?= e(!empty($r['entry_date']) ? $r['entry_date'] : '-') ?></span>
            </div>
            <div class="staff-card-meta">
                <?php if (!empty($r['type'])): ?>
                <span class="badge <?= $r['type'] === '支出' ? 'badge-danger' : 'badge-success' ?>"><?= e($r['type']) ?></span>
                <?php endif; ?>
                <?php if (!empty($r['expense_amount']) && $r['expense_amount'] > 0): ?>
                <span style="color:var(--danger)">支出 $<?= number_format($r['expense_amount']) ?></span>
                <?php endif; ?>
                <?php if (!empty($r['income_amount']) && $r['income_amount'] > 0): ?>
                <span style="color:var(--success)">收入 $<?= number_format($r['income_amount']) ?></span>
                <?php endif; ?>
                <span><strong>餘額 $<?= number_format(isset($r['running_balance']) ? $r['running_balance'] : 0) ?></strong></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($r['description']) ? $r['description'] : '-') ?></span>
            </div>
            <div class="staff-card-meta">
                <?php if (!empty($r['branch_name'])): ?><span><?= e($r['branch_name']) ?></span><?php endif; ?>
                <?php if (!empty($r['registrar'])): ?><span><?= e($r['registrar']) ?></span><?php endif; ?>
                <?php if (!empty($r['approval_status'])): ?><span><?= e($r['approval_status']) ?></span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:32px"></th>
                    <th>編號</th>
                    <th>收支日期</th>
                    <th>收支別</th>
                    <th>有無發票</th>
                    <th class="text-right">支出金額</th>
                    <th class="text-right">收入金額</th>
                    <th class="text-right">餘額</th>
                    <th>用途說明</th>
                    <th>分公司</th>
                    <th>登記人</th>
                    <th>簽核狀態</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): $isStar = !empty($r['is_starred']); ?>
                <tr style="cursor:pointer" onclick="location.href='/petty_cash.php?action=edit&id=<?= e($r['id']) ?>'">
                    <td class="text-center" onclick="event.stopPropagation()"><span class="star-toggle <?= $isStar ? 'is-on' : '' ?>" data-id="<?= (int)$r['id'] ?>" onclick="event.stopPropagation();toggleStarPettyCash(this)" title="標記">&#9733;</span></td>
                    <td style="font-size:.85rem"><?= e(!empty($r['entry_number']) ? $r['entry_number'] : '-') ?></td>
                    <td><?= e(!empty($r['entry_date']) ? $r['entry_date'] : '-') ?></td>
                    <td>
                        <?php if (!empty($r['type'])): ?>
                        <span class="badge <?= $r['type'] === '支出' ? 'badge-danger' : 'badge-success' ?>"><?= e($r['type']) ?></span>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td><?= e(!empty($r['has_invoice']) ? $r['has_invoice'] : '-') ?></td>
                    <td class="text-right"><?= (!empty($r['expense_amount']) && $r['expense_amount'] > 0) ? '<span style="color:var(--danger)">$' . number_format($r['expense_amount']) . '</span>' : '-' ?></td>
                    <td class="text-right"><?= (!empty($r['income_amount']) && $r['income_amount'] > 0) ? '<span style="color:var(--success)">$' . number_format($r['income_amount']) . '</span>' : '-' ?></td>
                    <td class="text-right"><strong style="color:<?= (isset($r['running_balance']) && $r['running_balance'] < 0) ? 'var(--danger)' : '#333' ?>">$<?= number_format(isset($r['running_balance']) ? $r['running_balance'] : 0) ?></strong></td>
                    <td><?= e(!empty($r['description']) ? $r['description'] : '-') ?></td>
                    <td><?= e(!empty($r['branch_name']) ? $r['branch_name'] : '-') ?></td>
                    <td><?= e(!empty($r['registrar']) ? $r['registrar'] : '-') ?></td>
                    <td><?= e(!empty($r['approval_status']) ? $r['approval_status'] : '-') ?></td>
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
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
.star-toggle { display:inline-block; cursor:pointer; font-size:1.2rem; color:#d0d0d0; transition:color .15s,transform .15s; user-select:none; line-height:1; }
.star-toggle:hover { color:#f1c40f; transform:scale(1.15); }
.star-toggle.is-on { color:#f1c40f; }
.star-toggle.saving { opacity:.5; pointer-events:none; }
</style>
<script>
function toggleStarPettyCash(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id'); if (!id) return;
    el.classList.add('saving');
    var fd = new FormData(); fd.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/petty_cash.php?action=toggle_star');
    xhr.onload = function() { el.classList.remove('saving'); try { var res = JSON.parse(xhr.responseText); if (res.error) { alert(res.error); return; } el.classList.toggle('is-on', !!res.starred); } catch (e) { alert('回應錯誤'); } };
    xhr.onerror = function() { el.classList.remove('saving'); alert('網路錯誤'); };
    xhr.send(fd);
}
function toggleAddForm() {
    var el = document.getElementById('addFormCard');
    el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}
function toggleInvoiceInfo() {
    var sel = document.getElementById('pcHasInvoice');
    var grp = document.getElementById('pcInvoiceInfoGroup');
    grp.style.display = (sel && sel.value === '有發票') ? '' : 'none';
}
</script>
