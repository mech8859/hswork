<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>可上工日期登錄</h2>
    <a href="/dispatch_workers.php" class="btn btn-outline btn-sm">返回點工列表</a>
</div>

<!-- 選擇人員 + 新增 -->
<div class="card mb-2">
    <div class="card-header">登錄可上工日期</div>
    <form method="POST" class="form-row" style="padding:16px;align-items:flex-end;gap:12px">
        <?= csrf_field() ?>
        <input type="hidden" name="add" value="1">
        <div class="form-group" style="flex:1;min-width:180px;margin:0">
            <label>點工人員 *</label>
            <select name="dispatch_worker_id" id="workerSelect" class="form-control" required onchange="switchWorker(this.value)">
                <option value="">請選擇</option>
                <?php foreach ($workers as $w): ?>
                <option value="<?= $w['id'] ?>" <?= $selectedWorker == $w['id'] ? 'selected' : '' ?>>
                    <?= e($w['name']) ?>
                    <?php if ($w['specialty']): ?>(<?= e($w['specialty']) ?>)<?php endif; ?>
                    <?php if ($w['vendor']): ?>[<?= e($w['vendor']) ?>]<?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:0 0 180px;margin:0">
            <label>可上工日期 *</label>
            <input type="date" name="available_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group" style="flex:0 0 auto;margin:0">
            <button type="submit" class="btn btn-primary btn-sm">新增登錄</button>
        </div>
    </form>
</div>

<!-- 已登錄列表 -->
<?php if ($selectedWorker): ?>
<div class="card">
    <div class="card-header">
        <?php
        $workerName = '';
        foreach ($workers as $w) { if ($w['id'] == $selectedWorker) { $workerName = $w['name']; break; } }
        ?>
        <?= e($workerName) ?> - 已登錄可上工日期（<?= count($records) ?> 筆）
    </div>
    <?php if (empty($records)): ?>
    <p class="text-muted" style="padding:16px">尚無登錄紀錄</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>可上工日期</th>
                    <th>星期</th>
                    <th>登錄人</th>
                    <th>登錄時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $dayNames = array('日','一','二','三','四','五','六');
                foreach ($records as $r):
                    $dow = (int)date('w', strtotime($r['available_date']));
                ?>
                <tr>
                    <td><strong><?= e($r['available_date']) ?></strong></td>
                    <td>週<?= $dayNames[$dow] ?></td>
                    <td><?= e($r['registered_by_name'] ?? '-') ?></td>
                    <td><?= e(substr($r['created_at'], 0, 16)) ?></td>
                    <td>
                        <form method="POST" style="display:inline" onsubmit="return confirm('確定刪除？')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete" value="1">
                            <input type="hidden" name="availability_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="worker_id" value="<?= $selectedWorker ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:#e53935">刪除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php elseif (!empty($workers)): ?>
<div class="card">
    <p class="text-muted" style="padding:16px">請先選擇點工人員</p>
</div>
<?php endif; ?>

<script>
function switchWorker(id) {
    if (id) {
        window.location.href = '/dispatch_workers.php?action=availability&worker_id=' + id;
    }
}
</script>
