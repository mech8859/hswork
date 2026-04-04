<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>銀行對帳</h1>
    <div style="display:flex;gap:8px">
        <a href="/accounting.php?action=journals" class="btn btn-secondary">傳票管理</a>
        <a href="/accounting.php?action=income_statement" class="btn btn-secondary">損益表</a>
        <a href="/accounting.php?action=balance_sheet" class="btn btn-secondary">資產負債表</a>
    </div>
</div>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px">
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:0.85em;color:#666">銀行餘額</div>
        <div style="font-size:1.4em;font-weight:bold;color:#1976d2"><?= number_format($summary['bank_balance']) ?></div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:0.85em;color:#666">帳面餘額</div>
        <div style="font-size:1.4em;font-weight:bold;color:#388e3c"><?= number_format($summary['book_balance']) ?></div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:0.85em;color:#666">差異</div>
        <div style="font-size:1.4em;font-weight:bold;color:<?= abs($summary['difference']) < 1 ? '#388e3c' : '#d32f2f' ?>"><?= number_format($summary['difference']) ?></div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:0.85em;color:#666">未對帳筆數</div>
        <div style="font-size:1.4em;font-weight:bold;color:#e65100"><?= number_format($summary['unreconciled_count']) ?></div>
    </div>
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:0.85em;color:#666">已對帳筆數</div>
        <div style="font-size:1.4em;font-weight:bold;color:#388e3c"><?= number_format($summary['reconciled_count']) ?></div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <form method="get" action="/accounting.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="action" value="reconciliation">
        <div>
            <label style="font-size:0.85em">銀行帳戶</label>
            <select name="bank_account" class="form-control" style="width:180px">
                <option value="">全部</option>
                <?php foreach ($bankAccounts as $ba): ?>
                <option value="<?= e($ba) ?>" <?= $filters['bank_account'] === $ba ? 'selected' : '' ?>><?= e($ba) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.85em">起始日期</label>
            <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="form-control" style="width:150px">
        </div>
        <div>
            <label style="font-size:0.85em">結束日期</label>
            <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="form-control" style="width:150px">
        </div>
        <button type="submit" class="btn btn-primary">查詢</button>
        <?php if ($canManage): ?>
        <form method="post" action="/accounting.php?action=reconciliation_auto_match" style="display:inline;margin-left:8px">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-success" onclick="return confirm('確定要執行自動對帳？')">自動對帳</button>
        </form>
        <?php endif; ?>
    </form>
</div>

<!-- Split View -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <!-- Left: Bank Transactions -->
    <div class="card" style="overflow-x:auto">
        <div style="padding:12px;background:#e3f2fd;border-bottom:1px solid #ddd;font-weight:bold">
            銀行交易明細 (未對帳 <?= count($bankTxs) ?> 筆)
        </div>
        <table class="data-table" style="width:100%;font-size:0.85em">
            <thead>
                <tr>
                    <th style="width:30px"><input type="radio" disabled></th>
                    <th>日期</th>
                    <th>摘要</th>
                    <th style="text-align:right">存入</th>
                    <th style="text-align:right">支出</th>
                    <th style="text-align:right">餘額</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($bankTxs)): ?>
                <tr><td colspan="6" style="text-align:center;color:#999;padding:24px">無未對帳銀行交易</td></tr>
            <?php else: ?>
                <?php foreach ($bankTxs as $bt): ?>
                <tr class="bank-tx-row" data-id="<?= $bt['id'] ?>" data-credit="<?= $bt['credit_amount'] ?>" data-debit="<?= $bt['debit_amount'] ?>" style="cursor:pointer">
                    <td><input type="radio" name="bank_tx" value="<?= $bt['id'] ?>" class="bank-radio"></td>
                    <td><?= e(isset($bt['transaction_date']) ? substr($bt['transaction_date'], 5) : '') ?></td>
                    <td title="<?= e($bt['description']) ?>"><?= e(mb_substr($bt['summary'], 0, 20)) ?></td>
                    <td style="text-align:right;color:#388e3c"><?= $bt['credit_amount'] > 0 ? number_format($bt['credit_amount']) : '' ?></td>
                    <td style="text-align:right;color:#d32f2f"><?= $bt['debit_amount'] > 0 ? number_format($bt['debit_amount']) : '' ?></td>
                    <td style="text-align:right"><?= number_format($bt['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Right: System Transactions -->
    <div class="card" style="overflow-x:auto">
        <div style="padding:12px;background:#e8f5e9;border-bottom:1px solid #ddd;font-weight:bold">
            系統交易 (未對帳 <?= count($sysTxs) ?> 筆)
        </div>
        <table class="data-table" style="width:100%;font-size:0.85em">
            <thead>
                <tr>
                    <th style="width:30px"><input type="radio" disabled></th>
                    <th>類型</th>
                    <th>單號</th>
                    <th>日期</th>
                    <th>對象</th>
                    <th style="text-align:right">金額</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($sysTxs)): ?>
                <tr><td colspan="6" style="text-align:center;color:#999;padding:24px">無未對帳系統交易</td></tr>
            <?php else: ?>
                <?php foreach ($sysTxs as $st): ?>
                <tr class="sys-tx-row" data-type="<?= e($st['sys_type']) ?>" data-id="<?= $st['id'] ?>" data-amount="<?= $st['sys_amount'] ?>" style="cursor:pointer">
                    <td><input type="radio" name="sys_tx" value="<?= e($st['sys_type']) ?>_<?= $st['id'] ?>" class="sys-radio"></td>
                    <td>
                        <?php if ($st['sys_type'] === 'receipt'): ?>
                            <span style="color:#388e3c">收款</span>
                        <?php else: ?>
                            <span style="color:#d32f2f">付款</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($st['sys_number']) ?></td>
                    <td><?= e(isset($st['sys_date']) ? substr($st['sys_date'], 5) : '') ?></td>
                    <td><?= e(mb_substr($st['sys_party'], 0, 12)) ?></td>
                    <td style="text-align:right;font-weight:bold"><?= number_format($st['sys_amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Manual Match Button -->
<?php if ($canManage): ?>
<div style="text-align:center;margin-top:16px">
    <form method="post" action="/accounting.php?action=reconciliation_manual_match" id="manualMatchForm">
        <?= csrf_field() ?>
        <input type="hidden" name="bank_tx_id" id="matchBankTxId" value="">
        <input type="hidden" name="sys_type" id="matchSysType" value="">
        <input type="hidden" name="sys_id" id="matchSysId" value="">
        <button type="submit" class="btn btn-primary" id="matchBtn" disabled style="padding:10px 40px;font-size:1.1em">
            確認配對
        </button>
        <div id="matchInfo" style="margin-top:8px;color:#666;font-size:0.9em"></div>
    </form>
</div>
<?php endif; ?>

<script>
(function() {
    var selectedBank = null;
    var selectedSys = null;

    // Click on bank row
    document.querySelectorAll('.bank-tx-row').forEach(function(row) {
        row.addEventListener('click', function() {
            document.querySelectorAll('.bank-tx-row').forEach(function(r) { r.style.background = ''; });
            row.style.background = '#e3f2fd';
            row.querySelector('.bank-radio').checked = true;
            selectedBank = {
                id: row.getAttribute('data-id'),
                credit: parseFloat(row.getAttribute('data-credit')),
                debit: parseFloat(row.getAttribute('data-debit'))
            };
            updateMatchBtn();
        });
    });

    // Click on system row
    document.querySelectorAll('.sys-tx-row').forEach(function(row) {
        row.addEventListener('click', function() {
            document.querySelectorAll('.sys-tx-row').forEach(function(r) { r.style.background = ''; });
            row.style.background = '#e8f5e9';
            row.querySelector('.sys-radio').checked = true;
            selectedSys = {
                type: row.getAttribute('data-type'),
                id: row.getAttribute('data-id'),
                amount: parseFloat(row.getAttribute('data-amount'))
            };
            updateMatchBtn();
        });
    });

    function updateMatchBtn() {
        var btn = document.getElementById('matchBtn');
        var info = document.getElementById('matchInfo');
        if (!btn) return;

        if (selectedBank && selectedSys) {
            document.getElementById('matchBankTxId').value = selectedBank.id;
            document.getElementById('matchSysType').value = selectedSys.type;
            document.getElementById('matchSysId').value = selectedSys.id;
            btn.disabled = false;

            // Check amount match
            var bankAmount = selectedSys.type === 'receipt' ? selectedBank.credit : selectedBank.debit;
            var diff = Math.abs(bankAmount - selectedSys.amount);
            if (diff < 1) {
                info.innerHTML = '<span style="color:#388e3c">金額相符</span>';
            } else {
                info.innerHTML = '<span style="color:#d32f2f">金額差異: ' + diff.toLocaleString() + '</span>';
            }
        } else {
            btn.disabled = true;
            if (info) info.innerHTML = '請先選擇左方銀行交易與右方系統交易';
        }
    }

    document.getElementById('manualMatchForm').addEventListener('submit', function(e) {
        if (!selectedBank || !selectedSys) {
            e.preventDefault();
            alert('請先選擇要配對的交易');
        }
    });
})();
</script>
