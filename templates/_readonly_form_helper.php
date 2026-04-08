<?php
/**
 * 唯讀表單共用 helper
 *
 * 使用方式：
 *   <?php $readOnly = $isEdit && !$canEdit; require __DIR__ . '/../_readonly_form_helper.php'; ?>
 *   <form class="<?= $readOnly ? 'form-readonly' : '' ?>" ...>
 *
 * 注意：
 *   - input[type=text/number/date/time/email/tel] 與 textarea → readonly 屬性（JS 可讀寫，計算 JS 不會壞）
 *   - select / checkbox / radio → JS preventDefault（值仍會被 JS 讀取）
 *   - button[type=submit] → 隱藏
 *   - 「新增列/刪除列」等動態按鈕（依 class 命名）→ 隱藏
 *   - hidden input 不影響
 */
?>
<?php if (!empty($readOnly)): ?>
<div class="card mb-2" style="background:#fff3cd;border-left:4px solid #ff9800;padding:12px 16px">
    <strong style="color:#e65100">⚠ 您只有檢視權限</strong>
    <span style="color:#666;font-size:.85rem;margin-left:8px">可瀏覽資料但無法修改</span>
</div>
<style>
.form-readonly input:not([type=hidden]),
.form-readonly textarea {
    background: #f5f5f5 !important;
    cursor: not-allowed;
}
.form-readonly select {
    background: #f5f5f5 !important;
    cursor: not-allowed;
}
.form-readonly button[type="submit"],
.form-readonly input[type="submit"] {
    display: none !important;
}
/* 隱藏編輯型按鈕（依 class 慣例命名）*/
.form-readonly .btn-add-row,
.form-readonly .btn-remove-row,
.form-readonly .btn-delete-item,
.form-readonly .btn-add-item,
.form-readonly .add-row-btn,
.form-readonly .remove-row-btn {
    display: none !important;
}
</style>
<script>
(function() {
    function applyReadonly() {
        var forms = document.querySelectorAll('.form-readonly');
        for (var f = 0; f < forms.length; f++) {
            var form = forms[f];
            // 1. input/textarea 加 readonly 屬性（JS 仍可讀寫，計算邏輯不會壞）
            var inps = form.querySelectorAll('input:not([type=hidden]):not([type=button]):not([type=submit]):not([type=reset]):not([type=checkbox]):not([type=radio]):not([type=file]), textarea');
            for (var i = 0; i < inps.length; i++) {
                inps[i].setAttribute('readonly', 'readonly');
            }
            // 2. select 用 mousedown preventDefault 阻止下拉（key event 也阻止）
            var selects = form.querySelectorAll('select');
            for (var s = 0; s < selects.length; s++) {
                selects[s].addEventListener('mousedown', function(e) { e.preventDefault(); this.blur(); });
                selects[s].addEventListener('keydown', function(e) { e.preventDefault(); });
                selects[s].setAttribute('tabindex', '-1');
            }
            // 3. checkbox/radio click preventDefault
            var checks = form.querySelectorAll('input[type=checkbox], input[type=radio]');
            for (var c = 0; c < checks.length; c++) {
                checks[c].addEventListener('click', function(e) { e.preventDefault(); });
            }
            // 4. file input 直接 disable（不能上傳）
            var files = form.querySelectorAll('input[type=file]');
            for (var fi = 0; fi < files.length; fi++) {
                files[fi].disabled = true;
            }
            // 5. 隱藏儲存類按鈕
            var btns = form.querySelectorAll('button[type=submit], input[type=submit]');
            for (var b = 0; b < btns.length; b++) {
                btns[b].style.display = 'none';
            }
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyReadonly);
    } else {
        applyReadonly();
    }
})();
</script>
<?php endif; ?>
