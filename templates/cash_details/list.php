<div class="page-sticky-head">
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div class="d-flex align-center gap-1">
        <h2>現金明細</h2>
        <button type="button" class="btn btn-success btn-sm" onclick="toggleAddForm()">+ 新增</button>
    </div>
    <span class="text-muted"><?= isset($result['total']) ? $result['total'] : count($records) ?> 筆</span>
</div>

<!-- 新增表單 -->
<div id="addFormCard" class="card mb-2" style="display:none; background:#fafafa;">
    <form method="POST" action="/cash_details.php">
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
                <label>交易日期 <span style="color:red">*</span></label>
                <input type="date" max="2099-12-31" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>金額 <span style="color:red">*</span></label>
                <input type="number" name="amount" class="form-control" min="0" step="1" required placeholder="0">
            </div>
            <div class="form-group">
                <label>明細/說明</label>
                <input type="text" name="description" class="form-control" placeholder="明細/說明" autocomplete="off">
            </div>
            <div class="form-group">
                <label>承辦業務</label>
                <select name="sales_name" class="form-control">
                    <option value="">請選擇</option>
                    <?php if (!empty($staffList)): foreach ($staffList as $s): ?>
                    <option value="<?= e($s['real_name']) ?>"><?= e($s['real_name']) ?></option>
                    <?php endforeach; endif; ?>
                </select>
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

<?php
$latestBalance = 0;
if (!empty($records)) {
    // records 已按日期排序，第一筆就是最新的
    $latestBalance = isset($records[0]['running_balance']) ? (float)$records[0]['running_balance'] : 0;
}
?>
<div class="card mb-2" style="padding:1rem 1.5rem; text-align:center;">
    <div class="text-muted" style="font-size:.85rem; margin-bottom:.3rem">現金餘額</div>
    <div style="font-size:1.6rem; font-weight:700; color:<?= $latestBalance >= 0 ? 'var(--primary)' : 'var(--danger)' ?>">
        $<?= number_format($latestBalance) ?>
    </div>
</div>

<div class="card">
    <form method="GET" action="/cash_details.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">全部</option>
                    <option value="__blank__" <?= (!empty($filters['branch_id']) && $filters['branch_id'] === '__blank__') ? 'selected' : '' ?>>空白（未設定）</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= e($b['id']) ?>" <?= (!empty($filters['branch_id']) && $filters['branch_id'] == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>收支別</label>
                <select name="type" class="form-control">
                    <option value="">全部</option>
                    <option value="收入" <?= (!empty($filters['type']) && $filters['type'] === '收入') ? 'selected' : '' ?>>收入</option>
                    <option value="支出" <?= (!empty($filters['type']) && $filters['type'] === '支出') ? 'selected' : '' ?>>支出</option>
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
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="明細/編號(CD-)/金額" autocomplete="off">
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
                <a href="/cash_details.php" class="btn btn-outline btn-sm">清除</a>
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
        <?php $isStar = !empty($r['is_starred']); ?>
        <div class="staff-card" style="cursor:pointer;position:relative;padding-right:36px" onclick="location.href='/cash_details.php?action=edit&id=<?= e($r['id']) ?>'">
            <span class="star-toggle <?= $isStar ? 'is-on' : '' ?>"
                  data-id="<?= (int)$r['id'] ?>"
                  onclick="event.stopPropagation();toggleStar(this)"
                  style="position:absolute;top:10px;right:10px;font-size:1.3rem">&#9733;</span>
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['entry_number']) ? $r['entry_number'] : '-') ?></strong>
                <span class="text-muted" style="font-size:.8rem"><?= e(!empty($r['transaction_date']) ? $r['transaction_date'] : '-') ?></span>
            </div>
            <div class="staff-card-meta">
                <?php if (!empty($r['income_amount']) && $r['income_amount'] > 0): ?>
                <span style="color:var(--success)">收入 $<?= number_format($r['income_amount']) ?></span>
                <?php endif; ?>
                <?php if (!empty($r['expense_amount']) && $r['expense_amount'] > 0): ?>
                <span style="color:var(--danger)">支出 $<?= number_format($r['expense_amount']) ?></span>
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
                <?php if (!empty($r['sales_name'])): ?><span><?= e($r['sales_name']) ?></span><?php endif; ?>
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
                    <th>交易日期</th>
                    <th>分公司</th>
                    <th class="text-right">收入</th>
                    <th class="text-right">支出</th>
                    <th class="text-right">餘額</th>
                    <th>承辦業務</th>
                    <th>明細</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($filters['date_from']) && (int)$page === 1): ?>
                <tr style="background:#f1f5f9;font-weight:600">
                    <td></td>
                    <td colspan="2" style="font-size:.85rem;color:#475569">前期餘額（<?= e(date('Y-m-d', strtotime($filters['date_from'] . ' -1 day'))) ?> 以前累計）</td>
                    <td colspan="3"></td>
                    <td class="text-right"><strong style="color:<?= $openingBalance < 0 ? 'var(--danger)' : '#475569' ?>">$<?= number_format($openingBalance) ?></strong></td>
                    <td colspan="2"></td>
                </tr>
                <?php endif; ?>
                <?php foreach ($records as $r): ?>
                <?php $isStar = !empty($r['is_starred']); ?>
                <tr style="cursor:pointer" onclick="location.href='/cash_details.php?action=edit&id=<?= e($r['id']) ?>'">
                    <td class="text-center" onclick="event.stopPropagation()">
                        <span class="star-toggle <?= $isStar ? 'is-on' : '' ?>"
                              data-id="<?= (int)$r['id'] ?>"
                              onclick="toggleStar(this)"
                              title="標記/取消標記">&#9733;</span>
                    </td>
                    <td style="font-size:.85rem"><?= e(!empty($r['entry_number']) ? $r['entry_number'] : '-') ?></td>
                    <td><?= e(!empty($r['transaction_date']) ? $r['transaction_date'] : '-') ?></td>
                    <td><?= e(!empty($r['branch_name']) ? $r['branch_name'] : '-') ?></td>
                    <td class="text-right"><?= (!empty($r['income_amount']) && $r['income_amount'] > 0) ? '<span style="color:var(--success)">$' . number_format($r['income_amount']) . '</span>' : '-' ?></td>
                    <td class="text-right"><?= (!empty($r['expense_amount']) && $r['expense_amount'] > 0) ? '<span style="color:var(--danger)">$' . number_format($r['expense_amount']) . '</span>' : '-' ?></td>
                    <?php
                        $bal = isset($r['running_balance']) ? (float)$r['running_balance'] : 0;
                        $balStyle = $bal < 0 ? 'color:var(--danger)' : '';
                    ?>
                    <td class="text-right"><span style="<?= $balStyle ?>">$<?= number_format($bal) ?></span></td>
                    <td><?= e(!empty($r['sales_name']) ? $r['sales_name'] : '-') ?></td>
                    <td><?= e(!empty($r['description']) ? $r['description'] : '-') ?></td>
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

/* 星號記號 */
.star-toggle {
    display: inline-block;
    cursor: pointer;
    font-size: 1.2rem;
    color: #d0d0d0;
    transition: color .15s, transform .15s;
    user-select: none;
    line-height: 1;
}
.star-toggle:hover { color: #f1c40f; transform: scale(1.15); }
.star-toggle.is-on { color: #f1c40f; }
.star-toggle.is-on:hover { color: #e0a800; }
.star-toggle.saving { opacity: .5; pointer-events: none; }
</style>
<script>
function toggleAddForm() {
    var el = document.getElementById('addFormCard');
    el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}

function toggleStar(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id');
    if (!id) return;
    el.classList.add('saving');
    var form = new FormData();
    form.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cash_details.php?action=toggle_star');
    xhr.onload = function() {
        el.classList.remove('saving');
        if (xhr.status !== 200) { alert('儲存失敗'); return; }
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.error) { alert(res.error); return; }
            if (res.starred) {
                el.classList.add('is-on');
            } else {
                el.classList.remove('is-on');
            }
        } catch (e) { alert('回應錯誤'); }
    };
    xhr.onerror = function() {
        el.classList.remove('saving');
        alert('網路錯誤');
    };
    xhr.send(form);
}
</script>
