<?php
$activityTypes = BusinessCalendarModel::activityTypes();
$regionOptions = BusinessCalendarModel::regionOptions();
$isEdit = !empty($event);
if (!isset($canEdit)) $canEdit = true;
$readOnly = $isEdit && !$canEdit;
$statusOptions = array(
    'planned' => '計劃中',
    'completed' => '已完成',
    'cancelled' => '已取消',
);
require __DIR__ . '/../_readonly_form_helper.php';
?>

<h2><?= $isEdit ? ($readOnly ? '檢視業務行程' : '編輯業務行程') : '新增業務行程' ?></h2>

<form method="POST" class="mt-2 <?= $readOnly ? 'form-readonly' : '' ?>" id="bcForm">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">行程資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>行程日期 *</label>
                <input type="date" max="2099-12-31" name="event_date" class="form-control" value="<?= e($event['event_date'] ?? ($_GET['event_date'] ?? date('Y-m-d'))) ?>" required>
            </div>
            <div class="form-group">
                <label>業務人員 *</label>
                <select name="staff_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($salespeople as $sp): ?>
                    <option value="<?= $sp['id'] ?>" <?= ($event['staff_id'] ?? '') == $sp['id'] ? 'selected' : '' ?>><?= e($sp['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>活動類型 *</label>
                <select name="activity_type" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($activityTypes as $atKey => $atLabel): ?>
                    <option value="<?= e($atKey) ?>" <?= ($event['activity_type'] ?? '') === $atKey ? 'selected' : '' ?>><?= e($atLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>客戶名稱</label>
                <input type="text" name="customer_name" class="form-control" value="<?= e($event['customer_name'] ?? '') ?>" placeholder="客戶或公司名稱">
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="phone" class="form-control" value="<?= e($event['phone'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>地區</label>
                <select name="region" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($regionOptions as $rKey => $rLabel): ?>
                    <option value="<?= e($rKey) ?>" <?= ($event['region'] ?? '') === $rKey ? 'selected' : '' ?>><?= e($rLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:2">
                <label>地址</label>
                <input type="text" name="address" class="form-control" value="<?= e($event['address'] ?? '') ?>" placeholder="客戶地址">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>開始時間</label>
                <input type="time" name="start_time" class="form-control" value="<?= e($event['start_time'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>結束時間</label>
                <input type="time" name="end_time" class="form-control" value="<?= e($event['end_time'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="3"><?= e($event['note'] ?? '') ?></textarea>
        </div>
    </div>

    <?php if ($isEdit): ?>
    <div class="card">
        <div class="card-header">執行狀態</div>
        <div class="form-row">
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <?php foreach ($statusOptions as $sKey => $sLabel): ?>
                    <option value="<?= e($sKey) ?>" <?= ($event['status'] ?? 'planned') === $sKey ? 'selected' : '' ?>><?= e($sLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>執行結果</label>
            <textarea name="result" class="form-control" rows="3" placeholder="拜訪結果、客戶回饋..."><?= e($event['result'] ?? '') ?></textarea>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立行程' ?></button>
        <a href="/business_calendar.php" class="btn btn-outline">返回</a>
        <?php if ($isEdit && $canEdit): ?>
        <a href="/business_calendar.php?action=delete&id=<?= $event['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
           class="btn btn-danger" style="margin-left:auto"
           onclick="return confirm('確定要刪除此行程嗎？')">刪除</a>
        <?php endif; ?>
    </div>
</form>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
</style>
