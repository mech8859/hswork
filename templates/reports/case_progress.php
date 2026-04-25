<?php
/**
 * 案件更新進度報表
 * 變數：$rows  (來自 ReportModel::getCaseProgressReport)
 */
function _cp_label($status) {
    if ($status === null || $status === '') return '—';
    // 先試 progress label，再試 sub_status 對照
    $lbl = CaseModel::statusLabel($status);
    return $lbl !== null && $lbl !== '' ? $lbl : '—';
}

function _cp_age($dt) {
    if (empty($dt)) return '—';
    $sec = time() - strtotime($dt);
    if ($sec < 0) $sec = 0;
    $days = (int)floor($sec / 86400);
    if ($days >= 1) return $days . ' 天';
    $hrs = (int)floor($sec / 3600);
    if ($hrs >= 1) return $hrs . ' 小時';
    $min = (int)floor($sec / 60);
    return $min . ' 分';
}
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>案件更新進度</h2>
    <a href="/reports.php" class="btn btn-outline btn-sm">← 返回報表</a>
</div>

<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>案件最後狀態變更（不含「已完工結案」）</span>
        <span class="text-muted" style="font-size:.85rem">共 <?= count($rows) ?> 筆</span>
    </div>
    <?php if (empty($rows)): ?>
        <p class="text-muted text-center" style="padding:40px">目前沒有資料</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover" id="case-progress-table">
            <thead>
                <tr>
                    <th style="white-space:nowrap">進件編號</th>
                    <th style="white-space:nowrap">進件日期</th>
                    <th>案件名稱</th>
                    <th style="white-space:nowrap">承辦業務</th>
                    <th>上次狀態</th>
                    <th>本次狀態</th>
                    <th style="white-space:nowrap">最後更新時間</th>
                    <th style="white-space:nowrap">上次更新距今</th>
                    <th style="width:60px">動作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                // 「本次狀態」：以最後一次變更後的 sub_status 為主，沒有歷史則用目前 sub_status
                $newSub = $r['new_sub_status'] !== null ? $r['new_sub_status'] : $r['current_sub_status'];
                $oldSub = $r['old_sub_status'] !== null ? $r['old_sub_status'] : null;
                $newStatus = $r['new_status'] !== null ? $r['new_status'] : $r['current_status'];
                $oldStatus = $r['old_status'] !== null ? $r['old_status'] : null;
                $lastUpdate = $r['changed_at'] ?: ($r['updated_at'] ?: $r['created_at']);
            ?>
                <tr data-case-id="<?= (int)$r['id'] ?>">
                    <td style="white-space:nowrap">
                        <a href="/cases.php?action=edit&id=<?= (int)$r['id'] ?>" style="font-weight:600">
                            <?= e($r['case_number'] ?: '-') ?>
                        </a>
                    </td>
                    <td style="white-space:nowrap"><?= !empty($r['created_at']) ? date('Y-m-d', strtotime($r['created_at'])) : '-' ?></td>
                    <td><?= e($r['title'] ?: '-') ?></td>
                    <td style="white-space:nowrap"><?= e($r['sales_name'] ?: '-') ?></td>
                    <td>
                        <?php if ($oldSub !== null): ?>
                            <span class="text-muted"><?= e($oldSub) ?></span>
                            <?php if ($oldStatus): ?>
                            <br><small class="text-muted">(<?= e(_cp_label($oldStatus)) ?>)</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($newSub !== null && $newSub !== ''): ?>
                            <strong><?= e($newSub) ?></strong>
                            <?php if ($newStatus): ?>
                            <br><small class="text-muted">(<?= e(_cp_label($newStatus)) ?>)</small>
                            <?php endif; ?>
                        <?php elseif ($newStatus): ?>
                            <strong><?= e(_cp_label($newStatus)) ?></strong>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap">
                        <?= $lastUpdate ? e(date('Y-m-d H:i', strtotime($lastUpdate))) : '-' ?>
                    </td>
                    <td style="white-space:nowrap"><?= e(_cp_age($lastUpdate)) ?></td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm cp-hide-btn"
                                data-case-id="<?= (int)$r['id'] ?>"
                                title="從報表內移除（不影響案件）">刪除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var table = document.getElementById('case-progress-table');
    if (!table) return;
    table.addEventListener('click', function(e) {
        var btn = e.target.closest && e.target.closest('.cp-hide-btn');
        if (!btn) return;
        var caseId = btn.getAttribute('data-case-id');
        if (!caseId) return;
        if (!confirm('確定要從本報表中移除此案件？\n（僅在報表內隱藏，不會刪除案件本身）')) return;

        var fd = new FormData();
        fd.append('case_id', caseId);
        btn.disabled = true;
        fetch('/reports.php?action=case_progress_hide', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function(r){ return r.json(); }).then(function(j){
            if (j && j.success) {
                var tr = btn.closest('tr');
                if (tr) tr.parentNode.removeChild(tr);
            } else {
                alert('移除失敗：' + (j && j.msg ? j.msg : '未知錯誤'));
                btn.disabled = false;
            }
        }).catch(function(err){
            alert('移除失敗：' + err);
            btn.disabled = false;
        });
    });
})();
</script>
