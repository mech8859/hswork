<?php
/**
 * 編輯鎖定警示條 + 心跳
 * 用法：在 form template 內 require __DIR__ . '/../layouts/editing_lock_warning.php';
 * 需先在 controller 設定 $otherEditors（從 EditingLock::getOthers() 取得）
 * 也可選擇定義 $editingLockModule、$editingLockRecordId 啟動心跳
 */
?>
<div id="editingLockWarning" style="display:<?= empty($otherEditors) ? 'none' : 'flex' ?>;align-items:center;gap:8px;padding:10px 14px;border-radius:8px;background:#fff3cd;border:1px solid #ffc107;margin-bottom:12px;font-size:.9rem">
    <span style="font-size:1.1em">&#9888;</span>
    <span><strong id="editingLockNames"><?php
        if (!empty($otherEditors)) {
            $names = array();
            foreach ($otherEditors as $oe) { $names[] = e($oe['user_name']); }
            echo implode('、', $names);
        }
    ?></strong> 也正在編輯此單據，儲存時請留意可能的覆寫衝突。</span>
</div>

<?php if (!empty($editingLockModule) && !empty($editingLockRecordId)): ?>
<script>
(function() {
    var MOD = <?= json_encode($editingLockModule) ?>;
    var RID = <?= (int)$editingLockRecordId ?>;
    var warningEl = document.getElementById('editingLockWarning');
    var namesEl = document.getElementById('editingLockNames');

    function updateWarning(others) {
        if (!warningEl || !namesEl) return;
        if (others && others.length) {
            var names = others.map(function(o) { return o.user_name; }).join('、');
            namesEl.textContent = names;
            warningEl.style.display = 'flex';
        } else {
            warningEl.style.display = 'none';
        }
    }

    function heartbeat() {
        var fd = new FormData();
        fd.append('module', MOD);
        fd.append('record_id', RID);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/editing_lock.php?action=heartbeat');
        xhr.onload = function() {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) updateWarning(res.others || []);
            } catch (e) {}
        };
        xhr.send(fd);
    }

    // 開始：30 秒一次
    var hbTimer = setInterval(heartbeat, 30000);
    // 立即跑一次（避免初始狀態與後端不同步）
    setTimeout(heartbeat, 1500);

    // 離開頁面時釋放（用 sendBeacon 不阻塞）
    function release() {
        try {
            if (navigator.sendBeacon) {
                var fd = new FormData();
                fd.append('module', MOD);
                fd.append('record_id', RID);
                navigator.sendBeacon('/editing_lock.php?action=release', fd);
            }
        } catch (e) {}
    }
    window.addEventListener('pagehide', release);
    window.addEventListener('beforeunload', release);
})();
</script>
<?php endif; ?>
