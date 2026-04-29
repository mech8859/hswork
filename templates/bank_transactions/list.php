<?php $bankOptions = FinanceModel::bankAccountOptions(); ?>
<div class="page-sticky-head">
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>銀行帳戶明細</h2>
    <div class="d-flex align-center gap-1">
        <span class="text-muted"><?= isset($result['total']) ? $result['total'] : count($records) ?> 筆</span>
        <a href="/bank_transactions.php?action=import" class="btn btn-outline btn-sm">📥 批次匯入</a>
        <a href="/bank_transactions.php?action=create" class="btn btn-primary btn-sm">+ 新增</a>
    </div>
</div>

<!-- 銀行帳戶彙總 -->
<?php $hideDetail = isset($_COOKIE['bank_hide_detail']) && $_COOKIE['bank_hide_detail'] === '1'; ?>
<div class="card mb-2">
    <div class="card-header d-flex justify-between align-center">
        <span>帳戶彙總</span>
        <button type="button" id="btnToggleBankDetail" class="btn btn-outline btn-sm" onclick="toggleBankDetail()" style="font-size:.8rem">
            <?= $hideDetail ? '▼ 展開明細' : '▲ 收合明細' ?>
        </button>
    </div>
    <div class="bank-summary-cards">
        <div class="bank-sum-card" style="background:linear-gradient(135deg,#1a237e,#283593);color:#fff">
            <div class="bank-sum-label">銀行總餘額</div>
            <div class="bank-sum-value">$<?= number_format($bankSummary['total_balance']) ?></div>
        </div>
        <div class="bank-sum-card" style="background:linear-gradient(135deg,#b8860b,#daa520);color:#fff">
            <div class="bank-sum-label">週轉金</div>
            <div class="bank-sum-value">$<?= number_format($bankSummary['revolving_fund']) ?></div>
        </div>
        <div class="bank-sum-card" style="background:linear-gradient(135deg,#2e7d32,#43a047);color:#fff">
            <div class="bank-sum-label">轉入合計</div>
            <div class="bank-sum-value">$<?= number_format($bankSummary['total_in']) ?></div>
        </div>
        <div class="bank-sum-card" style="background:linear-gradient(135deg,#c62828,#e53935);color:#fff">
            <div class="bank-sum-label">轉出合計</div>
            <div class="bank-sum-value">$<?= number_format($bankSummary['total_out']) ?></div>
        </div>
    </div>

    <?php if (!empty($bankSummary['accounts'])): ?>
    <div class="table-responsive mt-1" id="bankDetailTable" style="<?= $hideDetail ? 'display:none' : '' ?>">
        <table class="table" style="font-size:.9rem">
            <thead>
                <tr>
                    <th>銀行帳戶</th>
                    <th class="text-right">轉入合計</th>
                    <th class="text-right">轉出合計</th>
                    <th class="text-right">最新餘額</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bankSummary['accounts'] as $acct): ?>
                <tr>
                    <td><?= e($acct['bank_account']) ?></td>
                    <td class="text-right" style="color:var(--success)">$<?= number_format($acct['total_in']) ?></td>
                    <td class="text-right" style="color:var(--danger)">$<?= number_format($acct['total_out']) ?></td>
                    <td class="text-right"><strong>$<?= number_format($acct['balance']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;border-top:2px solid var(--gray-300)">
                    <td>合計</td>
                    <td class="text-right" style="color:var(--success)">$<?= number_format($bankSummary['total_in']) ?></td>
                    <td class="text-right" style="color:var(--danger)">$<?= number_format($bankSummary['total_out']) ?></td>
                    <td class="text-right">$<?= number_format($bankSummary['total_balance']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card" style="margin-bottom:0">
    <form method="GET" action="/bank_transactions.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>銀行帳戶</label>
                <select name="bank_account" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($bankOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= (!empty($filters['bank_account']) && $filters['bank_account'] === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
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
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="摘要/編號/銀行交易編號/金額" autocomplete="off">
            </div>
            <?php $sortVal = !empty($filters['sort']) ? $filters['sort'] : 'desc'; ?>
            <div class="form-group">
                <label>排序</label>
                <select name="sort" class="form-control">
                    <option value="desc" <?= $sortVal === 'desc' ? 'selected' : '' ?>>新→舊</option>
                    <option value="asc"  <?= $sortVal === 'asc'  ? 'selected' : '' ?>>舊→新</option>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/bank_transactions.php" class="btn btn-outline btn-sm">清除</a>
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
        <div class="staff-card" style="position:relative">
            <span class="star-toggle <?= $isStar ? 'is-on' : '' ?>"
                  data-id="<?= (int)$r['id'] ?>"
                  onclick="event.preventDefault();event.stopPropagation();toggleStar(this)"
                  style="position:absolute;top:10px;right:10px;font-size:1.3rem">&#9733;</span>
            <a href="/bank_transactions.php?action=edit&id=<?= (int)$r['id'] ?>" style="text-decoration:none;color:inherit;display:block;padding-right:28px">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['summary']) ? $r['summary'] : '-') ?></strong>
                <span class="text-muted" style="font-size:.8rem"><?= e(!empty($r['transaction_date']) ? $r['transaction_date'] : '-') ?></span>
            </div>
            <div class="staff-card-meta">
                <?php if (!empty($r['debit_amount']) && $r['debit_amount'] > 0): ?>
                <span style="color:var(--danger)">支出 $<?= number_format($r['debit_amount']) ?></span>
                <?php endif; ?>
                <?php if (!empty($r['credit_amount']) && $r['credit_amount'] > 0): ?>
                <span style="color:var(--success)">存入 $<?= number_format($r['credit_amount']) ?></span>
                <?php endif; ?>
                <span>餘額 $<?= number_format(!empty($r['balance']) ? $r['balance'] : 0) ?></span>
            </div>
            <?php if (!empty($r['description'])): ?>
            <div class="staff-card-meta"><span><?= e($r['description']) ?></span></div>
            <?php endif; ?>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead class="sticky-thead">
                <tr>
                    <th style="width:32px"></th>
                    <th>交易日期</th>
                    <th>銀行帳戶</th>
                    <th>摘要</th>
                    <th class="text-right">支出</th>
                    <th class="text-right">存入</th>
                    <th class="text-right">餘額</th>
                    <th>對象說明</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <?php $isStar = !empty($r['is_starred']); ?>
                <tr>
                    <td class="text-center">
                        <span class="star-toggle <?= $isStar ? 'is-on' : '' ?>"
                              data-id="<?= (int)$r['id'] ?>"
                              onclick="toggleStar(this)"
                              title="標記/取消標記">&#9733;</span>
                    </td>
                    <td><?= e(!empty($r['transaction_date']) ? $r['transaction_date'] : '-') ?></td>
                    <td style="font-size:.85rem"><?= e(!empty($r['bank_account']) ? $r['bank_account'] : '-') ?></td>
                    <td><?= e(!empty($r['summary']) ? $r['summary'] : '-') ?></td>
                    <td class="text-right"><?= (!empty($r['debit_amount']) && $r['debit_amount'] > 0) ? '<span style="color:var(--danger)">$' . number_format($r['debit_amount']) . '</span>' : '-' ?></td>
                    <td class="text-right"><?= (!empty($r['credit_amount']) && $r['credit_amount'] > 0) ? '<span style="color:var(--success)">$' . number_format($r['credit_amount']) . '</span>' : '-' ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['balance']) ? $r['balance'] : 0) ?></td>
                    <td><?= e(!empty($r['description']) ? $r['description'] : (!empty($r['remark']) ? $r['remark'] : '-')) ?></td>
                    <td><a href="/bank_transactions.php?action=edit&id=<?= (int)$r['id'] ?>" class="btn btn-outline btn-sm">編輯</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../layouts/pagination.php'; ?>
</div>

<style>
.bank-summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 12px; }
.bank-sum-card { padding: 16px; border-radius: var(--radius); text-align: center; }
.bank-sum-label { font-size: .85rem; opacity: .9; margin-bottom: 4px; }
.bank-sum-value { font-size: 1.4rem; font-weight: 700; }
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
@media (max-width: 767px) { .bank-sum-value { font-size: 1.1rem; } }

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
function toggleStar(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id');
    if (!id) return;
    el.classList.add('saving');
    var form = new FormData();
    form.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/bank_transactions.php?action=toggle_star');
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

// 展開/收合帳戶彙總明細
function toggleBankDetail() {
    var tbl = document.getElementById('bankDetailTable');
    var btn = document.getElementById('btnToggleBankDetail');
    if (!tbl || !btn) return;
    var hidden = tbl.style.display === 'none';
    if (hidden) {
        tbl.style.display = '';
        btn.innerHTML = '▲ 收合明細';
        document.cookie = 'bank_hide_detail=0; path=/; max-age=' + (60*60*24*365);
    } else {
        tbl.style.display = 'none';
        btn.innerHTML = '▼ 展開明細';
        document.cookie = 'bank_hide_detail=1; path=/; max-age=' + (60*60*24*365);
    }
}
</script>
