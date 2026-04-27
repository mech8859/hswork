<?php $typeOptions = FinanceModel::incomeExpenseOptions(); ?>
<div class="page-sticky-head">
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div class="d-flex align-center gap-1">
        <h2>備用金管理</h2>
        <button type="button" class="btn btn-success btn-sm" onclick="toggleAddForm()">+ 新增</button>
    </div>
    <span class="text-muted"><?= isset($result['total']) ? $result['total'] : count($records) ?> 筆</span>
</div>

<!-- 新增表單 -->
<div id="addFormCard" class="card mb-2" style="display:none; background:#fafafa;">
    <form method="POST" action="/reserve_fund.php">
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
                <label>支出日期 <span style="color:red">*</span></label>
                <input type="date" max="2099-12-31" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>金額 <span style="color:red">*</span></label>
                <input type="number" name="amount" class="form-control" min="0" step="1" required placeholder="0">
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
                    <option value="<?= e($b['id']) ?>" <?= $b['id'] == (isset($defaultBranchId) ? $defaultBranchId : 21) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
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

<div class="card">
    <form method="GET" action="/reserve_fund.php" class="filter-form">
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
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="用途說明/編號/金額" autocomplete="off">
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
                <a href="/reserve_fund.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>
</div><!-- /.page-sticky-head -->

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無資料</p>
    <?php else: ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" style="cursor:pointer" onclick="location.href='/reserve_fund.php?action=edit&id=<?= e($r['id']) ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['entry_number']) ? $r['entry_number'] : '-') ?></strong>
                <span class="text-muted" style="font-size:.8rem"><?= e(!empty($r['expense_date']) ? $r['expense_date'] : '-') ?></span>
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
                <?php
                    $bal = isset($r['running_balance']) ? (float)$r['running_balance'] : 0;
                    $balColor = $bal < 0 ? 'color:var(--danger)' : '';
                ?>
                <span style="<?= $balColor ?>">餘額 $<?= number_format($bal) ?></span>
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
            <thead class="sticky-thead">
                <tr>
                    <th style="width:32px"></th>
                    <th>編號</th>
                    <th>支出日期</th>
                    <th>分公司</th>
                    <th>收支別</th>
                    <th class="text-right">支出金額</th>
                    <th class="text-right">收入金額</th>
                    <th class="text-right">餘額</th>
                    <th>用途說明</th>
                    <th>發票資訊</th>
                    <th>登記人</th>
                    <th>簽核狀態</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($filters['date_from']) && (int)$page === 1): ?>
                <tr style="background:#f1f5f9;font-weight:600">
                    <td></td>
                    <td colspan="2" style="font-size:.85rem;color:#475569">前期餘額（<?= e(date('Y-m-d', strtotime($filters['date_from'] . ' -1 day'))) ?> 以前累計）</td>
                    <td colspan="4"></td>
                    <td class="text-right"><strong style="color:<?= $openingBalance < 0 ? 'var(--danger)' : '#475569' ?>">$<?= number_format($openingBalance) ?></strong></td>
                    <td colspan="4"></td>
                </tr>
                <?php endif; ?>
                <?php foreach ($records as $r): $isStar = !empty($r['is_starred']); ?>
                <tr style="cursor:pointer" onclick="location.href='/reserve_fund.php?action=edit&id=<?= e($r['id']) ?>'">
                    <td class="text-center" onclick="event.stopPropagation()"><span class="star-toggle <?= $isStar ? 'is-on' : '' ?>" data-id="<?= (int)$r['id'] ?>" onclick="event.stopPropagation();toggleStarReserveFund(this)" title="標記">&#9733;</span></td>
                    <td style="font-size:.85rem"><?= e(!empty($r['entry_number']) ? $r['entry_number'] : '-') ?></td>
                    <td><?= e(!empty($r['expense_date']) ? $r['expense_date'] : '-') ?></td>
                    <td><?= e(!empty($r['branch_name']) ? $r['branch_name'] : '-') ?></td>
                    <td>
                        <?php if (!empty($r['type'])): ?>
                        <span class="badge <?= $r['type'] === '支出' ? 'badge-danger' : 'badge-success' ?>"><?= e($r['type']) ?></span>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td class="text-right"><?= (!empty($r['expense_amount']) && $r['expense_amount'] > 0) ? '<span style="color:var(--danger)">$' . number_format($r['expense_amount']) . '</span>' : '-' ?></td>
                    <td class="text-right"><?= (!empty($r['income_amount']) && $r['income_amount'] > 0) ? '<span style="color:var(--success)">$' . number_format($r['income_amount']) . '</span>' : '-' ?></td>
                    <?php
                        $bal = isset($r['running_balance']) ? (float)$r['running_balance'] : 0;
                        $balStyle = $bal < 0 ? 'color:var(--danger)' : '';
                    ?>
                    <td class="text-right"><span style="<?= $balStyle ?>">$<?= number_format($bal) ?></span></td>
                    <td><?= e(!empty($r['description']) ? $r['description'] : '-') ?></td>
                    <td style="font-size:.85rem"><?= e(!empty($r['invoice_info']) ? $r['invoice_info'] : '-') ?></td>
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
function toggleAddForm() {
    var el = document.getElementById('addFormCard');
    el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}
function toggleStarReserveFund(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id'); if (!id) return;
    el.classList.add('saving');
    var fd = new FormData(); fd.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/reserve_fund.php?action=toggle_star');
    xhr.onload = function() { el.classList.remove('saving'); try { var res = JSON.parse(xhr.responseText); if (res.error) { alert(res.error); return; } el.classList.toggle('is-on', !!res.starred); } catch (e) { alert('回應錯誤'); } };
    xhr.onerror = function() { el.classList.remove('saving'); alert('網路錯誤'); };
    xhr.send(fd);
}
</script>
