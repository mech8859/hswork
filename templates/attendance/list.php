<?php
function _m2hm($m) {
    if ($m === null || $m === '') return '';
    $m = (int)$m;
    if ($m <= 0) return '';
    return floor($m / 60) . ':' . str_pad($m % 60, 2, '0', STR_PAD_LEFT);
}
$_leaveLabels = array('annual'=>'特休','personal'=>'事假','sick'=>'病假','official'=>'公假');
$_otTypeLabels = array('weekday'=>'平日','rest_day'=>'休息日','holiday'=>'國定','other'=>'其他');
?>
<div class="d-flex justify-between align-center mb-2">
    <h2>MOA 考勤明細</h2>
    <div class="d-flex gap-1">
        <?php if (Auth::hasPermission('attendance.manage') || Auth::hasPermission('all')): ?>
        <a href="/moa_attendance.php?action=sync_config" class="btn btn-primary btn-sm">⚙ API 同步</a>
        <a href="/moa_attendance.php?action=import" class="btn btn-success btn-sm">+ 匯入 Excel</a>
        <a href="/moa_attendance.php?action=employees" class="btn btn-outline btn-sm">員工對照</a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-2">
    <form method="GET" action="/moa_attendance.php" class="d-flex flex-wrap align-center gap-1" style="padding:10px 14px">
        <label style="font-size:.85rem">日期</label>
        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="form-control" style="max-width:150px">
        <span>～</span>
        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="form-control" style="max-width:150px">
        <input type="text" name="name" value="<?= e($filters['name']) ?>" placeholder="姓名" class="form-control" style="max-width:120px">
        <select name="dept" class="form-control" style="max-width:160px">
            <option value="">全部部門</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= e($d) ?>" <?= $filters['dept'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
            <?php endforeach; ?>
        </select>
        <label style="font-size:.85rem;display:inline-flex;align-items:center;gap:4px;cursor:pointer">
            <input type="checkbox" name="only_abnormal" value="1" <?= $filters['only_abnormal'] ? 'checked' : '' ?>> 只看異常
        </label>
        <label style="font-size:.85rem;display:inline-flex;align-items:center;gap:4px;cursor:pointer">
            <input type="checkbox" name="unmatched" value="1" <?= $filters['unmatched'] ? 'checked' : '' ?>> 只看未對應
        </label>
        <button type="submit" class="btn btn-primary btn-sm">篩選</button>
    </form>
</div>

<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>明細（<?= count($records) ?> 筆）</span>
        <small class="text-muted">超過 1000 筆會截斷，請縮小日期範圍</small>
    </div>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr>
                <th>日期</th><th>周</th><th>部門</th><th>姓名</th>
                <th>對應人員</th>
                <th class="text-right">應/實出</th>
                <th class="text-center">簽到</th><th class="text-center">簽退</th>
                <th class="text-right">遲到</th><th class="text-right">早退</th><th class="text-right">曠職</th>
                <th>請假/加班</th>
                <th>標記</th>
            </tr></thead>
            <tbody>
                <?php if (empty($records)): ?>
                <tr><td colspan="13" class="text-center text-muted" style="padding:20px">無資料</td></tr>
                <?php else: foreach ($records as $r):
                    $rt = $r['_row_type'] ?? '';
                    $bg = '';
                    if ($rt === 'leave')        $bg = 'background:#e8f5e9;color:#555';
                    elseif ($rt === 'holiday')  $bg = 'background:#ffe0b2;color:#444';
                    elseif ($rt === 'weekend')  $bg = 'background:#f5f5f5;color:#999';
                    elseif ($rt === 'no_record')$bg = 'background:#ffebee;color:#c62828';
                    elseif ($r['is_abnormal'])  $bg = 'background:#fff3e0';
                ?>
                <tr style="<?= $bg ?>">
                    <td><?= e($r['work_date']) ?></td>
                    <td><?= e($r['weekday'] ?? '') ?></td>
                    <td><?= e($r['moa_dept'] ?? '') ?></td>
                    <td style="font-weight:600"><?= e($r['moa_name']) ?></td>
                    <td>
                        <?php if (!empty($r['hswork_name'])): ?>
                            <span style="color:#2e7d32"><?= e($r['hswork_name']) ?></span>
                        <?php else: ?>
                            <span style="color:#c62828">未對應</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right" style="font-size:.8rem;color:#666">
                        <?= e(_m2hm($r['expected_minutes'])) ?> / <?= e(_m2hm($r['actual_minutes'])) ?>
                    </td>
                    <?php
                    $_canEditAtt = Auth::hasPermission('attendance.manage') || Auth::hasPermission('all');
                    $_workStart = $r['work_start_time'] ?: '08:00:00';
                    $_isLate = $r['sign_in_time'] && $r['sign_in_time'] > $_workStart && empty($r['_synthetic']);
                    ?>
                    <td class="text-center sign-cell <?= $_canEditAtt ? 'editable' : '' ?>"
                        data-field="sign_in" data-name="<?= e($r['moa_name']) ?>" data-date="<?= e($r['work_date']) ?>"
                        data-orig="<?= $r['sign_in_time'] ? substr($r['sign_in_time'], 0, 5) : '' ?>"
                        style="<?= ($r['sign_in_time'] === null && !empty($r['sign_in_status']) && $r['sign_in_status'] === '只有單筆打卡') || $_isLate ? 'color:#c62828;font-weight:600' : '' ?>">
                        <?= $r['sign_in_time'] ? substr($r['sign_in_time'], 0, 5) : ($r['sign_in_status'] ?: '') ?>
                    </td>
                    <td class="text-center sign-cell <?= $_canEditAtt ? 'editable' : '' ?>"
                        data-field="sign_out" data-name="<?= e($r['moa_name']) ?>" data-date="<?= e($r['work_date']) ?>"
                        data-orig="<?= $r['sign_out_time'] ? substr($r['sign_out_time'], 0, 5) : '' ?>"
                        style="<?= ($r['sign_out_time'] === null && empty($r['_synthetic'])) ? 'color:#c62828;font-weight:600' : '' ?>">
                        <?= $r['sign_out_time'] ? substr($r['sign_out_time'], 0, 5) : ($r['sign_out_status'] ?: '') ?>
                    </td>
                    <?php
                    // 遲到分鐘：優先用既有值（Excel 匯入有），否則用「簽到 - 該員工上班時間」計算
                    $lateMin = $r['late_minutes'];
                    if (($lateMin === null || $lateMin === '') && !empty($r['sign_in_time']) && empty($r['_synthetic'])) {
                        $start = $r['work_start_time'] ?: '08:00:00';
                        if ($r['sign_in_time'] > $start) {
                            $sParts = explode(':', $r['sign_in_time']);
                            $tParts = explode(':', $start);
                            $signInSec = (int)$sParts[0] * 3600 + (int)$sParts[1] * 60 + (isset($sParts[2]) ? (int)$sParts[2] : 0);
                            $startSec  = (int)$tParts[0] * 3600 + (int)$tParts[1] * 60 + (isset($tParts[2]) ? (int)$tParts[2] : 0);
                            $lateMin = (int)floor(($signInSec - $startSec) / 60);
                        }
                    }
                    ?>
                    <td class="text-right" style="color:<?= $lateMin ? '#c62828' : '' ?>">
                        <?= $lateMin ? (int)$lateMin : '' ?>
                    </td>
                    <td class="text-right" style="color:<?= $r['early_leave_minutes'] ? '#c62828' : '' ?>">
                        <?= e(_m2hm($r['early_leave_minutes'])) ?>
                    </td>
                    <td class="text-right" style="color:<?= $r['absent_minutes'] ? '#c62828' : '' ?>">
                        <?= e(_m2hm($r['absent_minutes'])) ?>
                    </td>
                    <td style="white-space:nowrap">
                        <?php
                        $key = ($r['user_id'] ?? '') . '|' . $r['work_date'];
                        if (!empty($r['user_id']) && isset($leaveMap[$key])):
                            $lt = $leaveMap[$key];
                            $label = isset($_leaveLabels[$lt]) ? $_leaveLabels[$lt] : $lt;
                        ?>
                        <span class="badge" style="background:#e3f2fd;color:#0d47a1;font-size:.7rem">🏖 <?= e($label) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($r['user_id']) && isset($overtimeMap[$key])):
                            $ot = $overtimeMap[$key];
                            $hh = (float)$ot['hours'];
                        ?>
                        <span class="badge" style="background:#fff8e1;color:#e65100;font-size:.7rem" title="<?= e(isset($_otTypeLabels[$ot['type']]) ? $_otTypeLabels[$ot['type']] : $ot['type']) ?>">⏰ +<?= rtrim(rtrim(number_format($hh, 1), '0'), '.') ?>h</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['is_abnormal']): ?><span class="badge" style="background:#fff3e0;color:#e65100">異常</span><?php endif; ?>
                        <?php if ($r['has_application']): ?><span class="badge" style="background:#e3f2fd;color:#1565c0">申請</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (Auth::hasPermission('attendance.manage') || Auth::hasPermission('all')): ?>
<style>
.sign-cell.editable { cursor: pointer; }
.sign-cell.editable:hover { background: #e3f2fd !important; }
.sign-cell input.cell-time { width: 78px; padding: 2px 4px; font-size: .85rem; border: 1px solid #1976d2; border-radius: 3px; }
</style>
<script>
(function(){
    var csrf = '<?= e(Session::getCsrfToken()) ?>';
    document.querySelectorAll('td.sign-cell.editable').forEach(function(cell){
        cell.addEventListener('click', function(e){
            if (cell.querySelector('input')) return;
            var orig = cell.getAttribute('data-orig') || '';
            var input = document.createElement('input');
            input.type = 'time';
            input.className = 'cell-time';
            input.value = orig;
            cell.innerHTML = '';
            cell.appendChild(input);
            input.focus();
            var saved = false;
            input.addEventListener('keydown', function(ev){
                if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
                else if (ev.key === 'Escape') { saved = true; cell.textContent = orig; }
            });
            input.addEventListener('blur', function(){
                if (saved) return;
                var newVal = input.value;
                if (newVal === orig) { cell.textContent = orig || ''; return; }
                var fd = new FormData();
                fd.append('moa_name', cell.getAttribute('data-name'));
                fd.append('work_date', cell.getAttribute('data-date'));
                fd.append(cell.getAttribute('data-field'), newVal);
                fd.append('csrf_token', csrf);
                fetch('/moa_attendance.php?action=update_record', {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (d && d.success) {
                            cell.textContent = newVal || '—';
                            cell.setAttribute('data-orig', newVal);
                            cell.style.background = '#c8e6c9';
                            setTimeout(function(){ cell.style.background = ''; }, 800);
                        } else {
                            alert('儲存失敗：' + (d && d.error || '未知錯誤'));
                            cell.textContent = orig;
                        }
                    })
                    .catch(function(){ alert('網路錯誤'); cell.textContent = orig; });
            });
        });
    });
})();
</script>
<?php endif; ?>
