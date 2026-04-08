<div class="d-flex justify-between align-center flex-wrap mb-2">
    <h2>加班單檢視
        <?php if ($record['status'] === 'pending'): ?>
            <span class="badge badge-warning">待核准</span>
        <?php elseif ($record['status'] === 'approved'): ?>
            <span class="badge badge-success">已核准</span>
        <?php else: ?>
            <span class="badge badge-danger">已駁回</span>
        <?php endif; ?>
    </h2>
    <div class="d-flex gap-1 flex-wrap">
        <?php if ($record['status'] === 'pending' && ($record['user_id'] == Auth::id() || $canManage)): ?>
        <a href="/overtimes.php?action=edit&id=<?= (int)$record['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <?php endif; ?>

        <?php if ($canManage && $record['status'] === 'pending'): ?>
        <button type="button" class="btn btn-success btn-sm" onclick="otApprove()">✓ 核准</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="otShowReject()">✕ 駁回</button>
        <?php endif; ?>

        <?php if ($canManage && $record['status'] !== 'pending'): ?>
        <button type="button" class="btn btn-outline btn-sm" onclick="otResetPending()">↺ 撤回為待審核</button>
        <?php endif; ?>

        <?php
        $canDeleteSelf = ($record['user_id'] == Auth::id() && in_array($record['status'], array('pending', 'rejected')));
        ?>
        <?php if ($canDeleteSelf || $canManage): ?>
        <button type="button" class="btn btn-outline btn-sm" style="color:#c62828;border-color:#c62828" onclick="otDelete()">刪除</button>
        <?php endif; ?>

        <a href="/overtimes.php" class="btn btn-outline btn-sm">返回列表</a>
    </div>
</div>

<div class="card">
    <div class="card-header">基本資訊</div>
    <table class="table detail-table">
        <tr>
            <th style="width:120px">加班人員</th>
            <td><?= e($record['real_name']) ?> <small style="color:#888">(<?= e($record['branch_name']) ?>)</small></td>
            <th style="width:120px">加班日期</th>
            <td><?= e($record['overtime_date']) ?></td>
        </tr>
        <tr>
            <th>加班類別</th>
            <td><?= e(OvertimeModel::typeLabel($record['overtime_type'])) ?></td>
            <th>加班時數</th>
            <td><strong style="color:#1565c0;font-size:1.1rem"><?= number_format($record['hours'], 2) ?></strong> 小時</td>
        </tr>
        <tr>
            <th>開始時間</th>
            <td><?= e(substr($record['start_time'], 0, 5)) ?></td>
            <th>結束時間</th>
            <td><?= e(substr($record['end_time'], 0, 5)) ?></td>
        </tr>
        <tr>
            <th>加班事由</th>
            <td colspan="3" style="white-space:pre-line"><?= e($record['reason']) ?></td>
        </tr>
        <?php if (!empty($record['note'])): ?>
        <tr>
            <th>備註</th>
            <td colspan="3" style="white-space:pre-line"><?= e($record['note']) ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<div class="card">
    <div class="card-header">簽核資訊</div>
    <table class="table detail-table">
        <tr>
            <th style="width:120px">建立者</th>
            <td><?= e(!empty($record['created_by_name']) ? $record['created_by_name'] : '-') ?></td>
            <th style="width:120px">建立時間</th>
            <td><?= e($record['created_at']) ?></td>
        </tr>
        <tr>
            <th>核准/駁回人</th>
            <td><?= e(!empty($record['approved_by_name']) ? $record['approved_by_name'] : '-') ?></td>
            <th>處理時間</th>
            <td><?= e(!empty($record['approved_at']) ? $record['approved_at'] : '-') ?></td>
        </tr>
        <?php if ($record['status'] === 'rejected' && !empty($record['reject_reason'])): ?>
        <tr>
            <th>駁回原因</th>
            <td colspan="3" style="color:#c62828;white-space:pre-line"><?= e($record['reject_reason']) ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<!-- 表單區 -->
<form id="otApproveForm" method="POST" action="/overtimes.php?action=approve" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
</form>
<form id="otResetForm" method="POST" action="/overtimes.php?action=reset_pending" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
</form>
<form id="otDeleteForm" method="POST" action="/overtimes.php?action=delete" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
</form>

<!-- 駁回對話框 -->
<div id="otRejectModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:20px;max-width:480px;width:90%">
        <h3 style="margin-top:0">駁回加班單</h3>
        <form method="POST" action="/overtimes.php?action=reject">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
            <div style="margin-bottom:12px">
                <label style="font-size:.85rem;font-weight:600">駁回原因</label>
                <textarea name="reject_reason" class="form-control" rows="3" placeholder="請輸入駁回原因" required></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn-outline" onclick="otHideReject()">取消</button>
                <button type="submit" class="btn btn-danger">確定駁回</button>
            </div>
        </form>
    </div>
</div>

<script>
function otApprove() {
    if (confirm('確定核准此加班單？')) {
        document.getElementById('otApproveForm').submit();
    }
}
function otShowReject() { document.getElementById('otRejectModal').style.display = 'flex'; }
function otHideReject() { document.getElementById('otRejectModal').style.display = 'none'; }
function otResetPending() {
    if (confirm('確定撤回為待審核狀態？\n(原核准/駁回紀錄會被清除)')) {
        document.getElementById('otResetForm').submit();
    }
}
function otDelete() {
    if (confirm('確定刪除此加班單？此操作無法復原。')) {
        document.getElementById('otDeleteForm').submit();
    }
}
</script>

<style>
.detail-table th { background: #f9f9f9; text-align: left; padding: 8px 12px; font-weight: 600; }
.detail-table td { padding: 8px 12px; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: .8rem; font-weight: 500; vertical-align: middle; }
.badge-warning { background: #fff3e0; color: #e65100; }
.badge-success { background: #e8f5e9; color: #2e7d32; }
.badge-danger { background: #ffebee; color: #c62828; }
</style>
