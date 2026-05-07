<div class="d-flex justify-between align-center mb-2">
    <h2>考勤員工對照</h2>
    <div class="d-flex gap-1">
        <a href="/moa_attendance.php" class="btn btn-outline btn-sm">返回明細</a>
        <a href="/moa_attendance.php?action=import" class="btn btn-outline btn-sm">匯入</a>
    </div>
</div>

<div class="card mb-2" style="background:#fff8e1;border-left:4px solid #f9a825">
    <div style="padding:10px 14px;font-size:.88rem">
        對應規則：匯入時系統會用 MOA 的「姓名」自動比對 hswork users.real_name。<strong style="color:#c62828">未對應</strong>的人請在這裡手動指派。
    </div>
</div>

<div class="card">
    <div class="card-header">員工對照清單（<?= count($employees) ?> 人）</div>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr>
                <th>MOA 姓名</th>
                <th>MOA 員編</th>
                <th>MOA 部門</th>
                <th>對應 hswork 人員</th>
                <th style="width:100px">上班時間</th>
                <th style="width:100px">下班時間</th>
                <th style="width:60px">操作</th>
            </tr></thead>
            <tbody>
                <?php if (empty($employees)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:20px">尚無 MOA 員工資料；先到「匯入」頁上傳一份 xlsx 即會自動建立。</td></tr>
                <?php else: foreach ($employees as $e): ?>
                <tr style="<?= empty($e['user_id']) ? 'background:#ffebee' : '' ?>">
                    <form method="POST" action="/moa_attendance.php?action=employees" style="display:contents">
                        <?= csrf_field() ?>
                        <input type="hidden" name="emp_id" value="<?= (int)$e['id'] ?>">
                        <td style="font-weight:600"><?= e($e['moa_name']) ?></td>
                        <td><?= e($e['moa_employee_no'] ?? '') ?></td>
                        <td><?= e($e['moa_dept'] ?? '') ?></td>
                        <td>
                            <select name="user_id" class="form-control form-control-sm">
                                <option value="">— 未對應 —</option>
                                <?php foreach ($allUsers as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= (int)$e['user_id'] === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['real_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="time" name="work_start_time" value="<?= e($e['work_start_time'] ? substr($e['work_start_time'], 0, 5) : '') ?>" class="form-control form-control-sm" placeholder="08:00">
                        </td>
                        <td>
                            <input type="time" name="work_end_time" value="<?= e($e['work_end_time'] ? substr($e['work_end_time'], 0, 5) : '') ?>" class="form-control form-control-sm" placeholder="17:30">
                        </td>
                        <td>
                            <?php if (Auth::hasPermission('attendance.manage') || Auth::hasPermission('all')): ?>
                            <button type="submit" class="btn btn-primary btn-sm">儲存</button>
                            <?php endif; ?>
                        </td>
                    </form>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
