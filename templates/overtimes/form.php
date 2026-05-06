<?php
$isEdit = !empty($record);
$_ot_canOverrideHours = isset($canOverrideHours) ? $canOverrideHours : false;
$_ot_isResubmit = $isEdit && !empty($record['status']) && $record['status'] === 'rejected';
?>
<div class="d-flex justify-between align-center mb-2">
    <h2><?= $_ot_isResubmit ? '修改後重送' : ($isEdit ? '編輯加班單' : '申請加班') ?></h2>
    <a href="/overtimes.php" class="btn btn-outline btn-sm">返回列表</a>
</div>

<?php if ($_ot_isResubmit): ?>
<div style="background:#fff3e0;border:1px solid #ffb74d;color:#6a4800;padding:10px 14px;border-radius:6px;margin-bottom:12px">
    <strong>⚠ 此單原為駁回狀態</strong>
    <?php if (!empty($record['reject_reason'])): ?>
    <div style="margin-top:6px;padding:6px 10px;background:#fff;border-left:3px solid #c62828;font-size:.9rem;white-space:pre-line">
        <strong style="color:#c62828">駁回原因：</strong><?= e($record['reject_reason']) ?>
    </div>
    <?php endif; ?>
    <div style="margin-top:6px;font-size:.85rem">儲存後將自動重新提交簽核。</div>
</div>
<?php endif; ?>

<form method="POST" action="/overtimes.php?action=<?= $isEdit ? 'edit&id=' . (int)$record['id'] : 'create' ?>" class="card">
    <?= csrf_field() ?>

    <div class="form-row">
        <?php if ($canView && !empty($users)): ?>
        <div class="form-group">
            <label>加班人員 <span style="color:#c62828">*</span></label>
            <select name="user_id" class="form-control" required>
                <option value="">請選擇</option>
                <?php
                $selUid = $isEdit ? $record['user_id'] : Auth::id();
                foreach ($users as $u):
                ?>
                <option value="<?= $u['id'] ?>" <?= $selUid == $u['id'] ? 'selected' : '' ?>><?= e($u['real_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
        <div class="form-group">
            <label>加班人員</label>
            <input type="text" class="form-control" value="<?= e(Auth::user()['real_name'] ?? '') ?>" readonly style="background:#f5f5f5">
            <input type="hidden" name="user_id" value="<?= (int)Auth::id() ?>">
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label>加班日期 <span style="color:#c62828">*</span></label>
            <input type="date" name="overtime_date" class="form-control" required
                   value="<?= e($isEdit ? $record['overtime_date'] : date('Y-m-d')) ?>" max="2099-12-31">
        </div>

        <div class="form-group">
            <label>加班類別 <span style="color:#c62828">*</span></label>
            <select name="overtime_type" class="form-control" required>
                <?php foreach (OvertimeModel::typeOptions() as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= ($isEdit && $record['overtime_type'] === $k) ? 'selected' : ($k === 'weekday' && !$isEdit ? 'selected' : '') ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>開始時間 <span style="color:#c62828">*</span></label>
            <input type="time" name="start_time" id="otStart" class="form-control" required
                   value="<?= e($isEdit ? substr($record['start_time'], 0, 5) : '18:00') ?>" oninput="calcOtHours()">
        </div>
        <div class="form-group">
            <label>結束時間 <span style="color:#c62828">*</span></label>
            <input type="time" name="end_time" id="otEnd" class="form-control" required
                   value="<?= e($isEdit ? substr($record['end_time'], 0, 5) : '20:00') ?>" oninput="calcOtHours()">
        </div>
        <div class="form-group">
            <label>加班時數 <small style="color:#888">(<?= $_ot_canOverrideHours ? '可手動修改' : '自動計算' ?>)</small></label>
            <input type="number" step="0.25" min="0" name="hours" id="otHours" class="form-control"
                   value="<?= e($isEdit ? $record['hours'] : '2.00') ?>" <?= $_ot_canOverrideHours ? '' : 'readonly' ?>>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group" style="flex:1">
            <label>加班事由 <span style="color:#c62828">*</span></label>
            <textarea name="reason" class="form-control" rows="3" required placeholder="請說明加班原因"><?= e($isEdit && !empty($record['reason']) ? $record['reason'] : '') ?></textarea>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group" style="flex:1">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="2" placeholder="選填"><?= e($isEdit && !empty($record['note']) ? $record['note'] : '') ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '送出申請' ?></button>
        <a href="/overtimes.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
function calcOtHours() {
    var s = document.getElementById('otStart').value;
    var e = document.getElementById('otEnd').value;
    if (!s || !e) return;
    var sd = new Date('2000-01-01T' + s + ':00');
    var ed = new Date('2000-01-01T' + e + ':00');
    if (ed <= sd) ed.setDate(ed.getDate() + 1); // 跨日
    var diff = (ed - sd) / 1000 / 3600;
    document.getElementById('otHours').value = diff.toFixed(2);
}
</script>
