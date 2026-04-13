<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>建立盤點</h2>
    <?= back_button('/inventory.php') ?>
</div>

<div class="card" style="max-width:500px">
    <p class="text-muted mb-2">選擇倉庫後，系統會自動載入該倉庫所有有庫存的品項</p>
    <form method="POST" action="/inventory.php?action=stocktake_create">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>盤點倉庫 <span class="text-danger">*</span></label>
            <select name="warehouse_id" class="form-control" required>
                <option value="">請選擇</option>
                <?php foreach ($warehouses as $w): ?>
                <option value="<?= e($w['id']) ?>"><?= e($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>盤點人 <span class="text-danger">*</span></label>
            <select name="stocktaker_id" class="form-control" required>
                <option value="">請選擇</option>
                <?php
                $roleLabels = array('warehouse' => '倉管', 'purchaser' => '採購', 'admin_staff' => '行政', 'accountant' => '會計', 'manager' => '主管', 'boss' => '管理者');
                foreach ($staffList as $s):
                    $label = $s['name'];
                    if (!empty($s['branch_name'])) $label .= ' - ' . $s['branch_name'];
                    if (!empty($roleLabels[$s['role']])) $label .= '（' . $roleLabels[$s['role']] . '）';
                ?>
                <option value="<?= $s['id'] ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="d-flex align-center gap-1" style="cursor:pointer">
                <input type="checkbox" name="include_zero" value="1">
                <span>包含庫存數量為 0 的產品</span>
            </label>
        </div>

        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="2" placeholder="盤點說明（選填）"></textarea>
        </div>

        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-primary">建立盤點單</button>
            <a href="/inventory.php?action=stocktake_list" class="btn btn-outline">取消</a>
        </div>
    </form>
</div>
