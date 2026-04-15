<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>傳票 <?= e($entry['voucher_number']) ?></h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <?php if (!empty($prevId)): ?>
        <a href="/accounting.php?action=journal_view&id=<?= $prevId ?>" class="btn btn-outline btn-sm" title="上一筆">&laquo; 上一筆</a>
        <?php endif; ?>
        <?php if (!empty($nextId)): ?>
        <a href="/accounting.php?action=journal_view&id=<?= $nextId ?>" class="btn btn-outline btn-sm" title="下一筆">下一筆 &raquo;</a>
        <?php endif; ?>
        <a href="/accounting.php?action=journal_create" class="btn btn-primary">+ 新增傳票</a>
        <?php
        $backUrl = '/accounting.php?action=journals';
        $backLabel = '返回列表';
        $ref = isset($_GET['ref']) ? $_GET['ref'] : '';
        $refParams = isset($_GET['ref_params']) ? $_GET['ref_params'] : '';
        if ($ref === 'ledger') {
            $backUrl = '/accounting.php?action=ledger' . ($refParams ? '&' . $refParams : '');
            $backLabel = '返回總帳查詢';
        } elseif ($ref === 'offset_ledger') {
            $backUrl = '/accounting.php?action=offset_ledger' . ($refParams ? '&' . $refParams : '');
            $backLabel = '返回立沖帳查詢';
        } elseif ($ref === 'trial_balance') {
            $backUrl = '/accounting.php?action=trial_balance' . ($refParams ? '&' . $refParams : '');
            $backLabel = '返回試算表';
        } elseif ($ref === 'reconciliation') {
            $backUrl = '/accounting.php?action=reconciliation' . ($refParams ? '&' . $refParams : '');
            $backLabel = '返回立沖明細表';
        } elseif ($ref === 'journal_reports') {
            $refTab = isset($_GET['ref_tab']) ? $_GET['ref_tab'] : '';
            $tabLabels = array(
                'daily_voucher' => '返回傳票日報表',
                'journal' => '返回日記帳',
                'daily_summary' => '返回日計表',
                'cash_book' => '返回現金簿',
                'general_ledger' => '返回總分類帳',
                'sub_ledger' => '返回明細分類帳',
            );
            $backUrl = '/accounting.php?action=journal_reports' . ($refTab ? '&tab=' . $refTab : '') . ($refParams ? '&' . $refParams : '');
            $backLabel = isset($tabLabels[$refTab]) ? $tabLabels[$refTab] : '返回傳票報表';
        }
        ?>
        <a href="<?= e($backUrl) ?>" class="btn btn-secondary"><?= $backLabel ?></a>
        <?php if ($canManage && $entry['status'] === 'draft'): ?>
        <a href="/accounting.php?action=journal_edit&id=<?= $entry['id'] ?>" class="btn btn-secondary">編輯</a>
        <?php endif; ?>
        <?php if ($canManage): ?>
        <a href="/accounting.php?action=journal_copy&id=<?= $entry['id'] ?>" class="btn btn-outline">複製</a>
        <?php endif; ?>
        <?php if ($canManage && $entry['status'] === 'posted'): ?>
        <form method="post" action="/accounting.php?action=journal_unpost" style="display:inline" onsubmit="return confirm('確定取消過帳？傳票將回到草稿狀態')">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $entry['id'] ?>">
            <button type="submit" class="btn btn-outline" style="color:var(--danger);border-color:var(--danger)">取消過帳</button>
        </form>
        <?php endif; ?>
        <?php if ($canManage && $entry['status'] === 'draft'): ?>
        <form method="post" action="/accounting.php?action=journal_delete" style="display:inline" onsubmit="return confirm('確定刪除此傳票？')">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $entry['id'] ?>">
            <button type="submit" class="btn btn-danger">刪除</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php
$statusColors = array('draft' => '#f0ad4e', 'posted' => '#5cb85c', 'voided' => '#d9534f');
$statusColor = isset($statusColors[$entry['status']]) ? $statusColors[$entry['status']] : '#999';
?>

<!-- Header Info -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
        <div>
            <div style="font-size:0.85em;color:#666">傳票號碼</div>
            <div style="font-weight:bold;font-size:1.1em"><?= e($entry['voucher_number']) ?></div>
        </div>
        <div>
            <div style="font-size:0.85em;color:#666">傳票日期</div>
            <div><?= e(format_date($entry['voucher_date'])) ?></div>
        </div>
        <div>
            <div style="font-size:0.85em;color:#666">類型</div>
            <div><?= isset($voucherTypeOptions[$entry['voucher_type']]) ? e($voucherTypeOptions[$entry['voucher_type']]) : e($entry['voucher_type']) ?></div>
        </div>
        <div>
            <div style="font-size:0.85em;color:#666">狀態</div>
            <div><span style="color:<?= $statusColor ?>;font-weight:bold;font-size:1.1em"><?= isset($statusOptions[$entry['status']]) ? e($statusOptions[$entry['status']]) : e($entry['status']) ?></span></div>
        </div>
        <div>
            <div style="font-size:0.85em;color:#666">建立者</div>
            <div><?= e($entry['created_by_name']) ?></div>
        </div>
        <?php if ($entry['posted_by_name']): ?>
        <div>
            <div style="font-size:0.85em;color:#666">過帳者</div>
            <div><?= e($entry['posted_by_name']) ?> (<?= e(format_datetime($entry['posted_at'])) ?>)</div>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($entry['description']): ?>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #eee">
        <div style="font-size:0.85em;color:#666">摘要</div>
        <div><?= e($entry['description']) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($entry['attachment'])):
        $attachments = json_decode($entry['attachment'], true);
        if (!is_array($attachments)) $attachments = array($entry['attachment']);
    ?>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #eee">
        <div style="font-size:0.85em;color:#666">附件</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
            <?php foreach ($attachments as $ai => $aPath): if (!$aPath) continue; ?>
            <a href="<?= e($aPath) ?>" target="_blank" class="btn btn-outline btn-sm">📎 附件<?= count($attachments) > 1 ? ($ai+1) : '' ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($entry['status'] === 'voided' && $entry['void_reason']): ?>
    <div style="margin-top:12px;padding:8px;background:#fff3cd;border-radius:4px">
        <strong>作廢原因：</strong><?= e($entry['void_reason']) ?>
        <?php if ($entry['voided_by_name']): ?>
        <br><small>作廢者: <?= e($entry['voided_by_name']) ?> (<?= e(format_datetime($entry['voided_at'])) ?>)</small>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Lines -->
<div class="card" style="padding:16px;margin-bottom:16px;overflow-x:auto">
    <h3 style="margin-top:0">分錄明細</h3>
    <div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th style="width:36px">#</th>
                <th style="width:90px">科目編號</th>
                <th>科目名稱</th>
                <th>部門中心</th>
                <th>往來類型</th>
                <th>往來編號</th>
                <th>往來對象</th>
                <th style="width:100px;text-align:right">借方金額</th>
                <th style="width:100px;text-align:right">貸方金額</th>
                <th>立沖</th>
                <th style="text-align:right">未沖額</th>
                <th style="text-align:right">本次沖帳</th>
                <th>摘要</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $relTypeLabels = array('customer' => '客戶', 'vendor' => '廠商', 'other' => '其他');
            $offsetLabels = array(0 => '', 1 => '立帳', 2 => '沖帳');
            $n = 1;
            foreach ($entry['lines'] as $line):
                // 跳過空白行（借貸方金額皆為0）
                if ((float)$line['debit_amount'] == 0 && (float)$line['credit_amount'] == 0) continue;
            ?>
            <tr>
                <td><?= $n++ ?></td>
                <td><strong><?= e($line['account_code']) ?></strong></td>
                <td><?= e($line['account_name']) ?></td>
                <td><?= e($line['cost_center_name']) ?></td>
                <td><?= isset($relTypeLabels[$line['relation_type']]) ? $relTypeLabels[$line['relation_type']] : '' ?></td>
                <td><?= e(!empty($line['relation_display_code']) ? $line['relation_display_code'] : ($line['relation_id'] ?: '')) ?></td>
                <td title="<?= e($line['relation_name'] ?: '') ?>"><?= e($line['relation_name'] ? mb_substr($line['relation_name'], 0, 6) : '') ?></td>
                <td style="text-align:right"><?= (float)$line['debit_amount'] > 0 ? number_format((float)$line['debit_amount']) : '' ?></td>
                <td style="text-align:right"><?= (float)$line['credit_amount'] > 0 ? number_format((float)$line['credit_amount']) : '' ?></td>
                <td><?= isset($offsetLabels[(int)$line['offset_flag']]) ? $offsetLabels[(int)$line['offset_flag']] : '' ?></td>
                <?php
                    $amt = max((float)$line['debit_amount'], (float)$line['credit_amount']);
                    $offsetAmt = (float)$line['offset_amount'];
                    $unoffset = $amt - $offsetAmt;
                ?>
                <td style="text-align:right"><?= (int)$line['offset_flag'] > 0 && $unoffset > 0 ? number_format($unoffset) : '' ?></td>
                <td style="text-align:right"><?= $offsetAmt > 0 ? number_format($offsetAmt) : '' ?></td>
                <td style="font-size:0.9em;color:#666"><?php
                    if ((int)$line['offset_flag'] === 2 && !empty($line['offset_voucher_number'])) {
                        // 沖帳摘要：顯示原立帳傳票號+往來對象
                        $desc = '沖 ' . $line['offset_voucher_number'];
                        if (!empty($line['offset_relation_name'])) {
                            $desc .= ' ' . $line['offset_relation_name'];
                        }
                        echo e($desc);
                    } else {
                        echo e($line['description']);
                    }
                ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;background:#f8f9fa">
                <td colspan="7" style="text-align:right">合計</td>
                <td style="text-align:right"><?= number_format($entry['total_debit']) ?></td>
                <td style="text-align:right"><?= number_format($entry['total_credit']) ?></td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
    </div>
</div>

<!-- Actions -->
<?php if ($canManage): ?>
<div class="card" style="padding:16px;display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($entry['status'] === 'draft'): ?>
    <form method="post" action="/accounting.php?action=journal_post" onsubmit="return confirm('確定要過帳此傳票嗎？過帳後將無法編輯。')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $entry['id'] ?>">
        <button type="submit" class="btn btn-primary">過帳</button>
    </form>
    <form method="post" action="/accounting.php?action=journal_delete" onsubmit="return confirm('確定要刪除此傳票嗎？此操作無法復原。')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $entry['id'] ?>">
        <button type="submit" class="btn btn-danger">刪除</button>
    </form>
    <?php endif; ?>

    <?php if ($entry['status'] === 'posted'): ?>
    <button type="button" class="btn btn-danger" onclick="document.getElementById('voidModal').style.display='flex'">作廢</button>
    <?php endif; ?>
</div>

<!-- Void Modal -->
<?php if ($entry['status'] === 'posted'): ?>
<div id="voidModal" class="modal" style="display:none">
    <div class="modal-overlay" onclick="document.getElementById('voidModal').style.display='none'"></div>
    <div class="modal-content" style="max-width:400px">
        <div class="modal-header">
            <h3>作廢傳票</h3>
            <button class="modal-close" onclick="document.getElementById('voidModal').style.display='none'">&times;</button>
        </div>
        <form method="post" action="/accounting.php?action=journal_void">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $entry['id'] ?>">
            <div class="modal-body">
                <p>確定要作廢傳票 <strong><?= e($entry['voucher_number']) ?></strong> 嗎？</p>
                <p style="color:#666;font-size:0.9em">傳票將標記為作廢，相關立沖記錄將自動反轉。</p>
                <label>作廢原因</label>
                <textarea name="reason" class="form-control" rows="3" placeholder="請輸入作廢原因"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('voidModal').style.display='none'">取消</button>
                <button type="submit" class="btn btn-danger">確定作廢</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
.modal { position:fixed;top:0;left:0;right:0;bottom:0;z-index:1000;display:flex;align-items:center;justify-content:center }
.modal-overlay { position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5) }
.modal-content { position:relative;background:#fff;border-radius:8px;width:90%;max-height:90vh;overflow-y:auto }
.modal-header { display:flex;justify-content:space-between;align-items:center;padding:16px;border-bottom:1px solid #eee }
.modal-header h3 { margin:0 }
.modal-close { background:none;border:none;font-size:24px;cursor:pointer }
.modal-body { padding:16px }
.modal-footer { padding:16px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:8px }
</style>
