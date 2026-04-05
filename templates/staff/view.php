<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= e($user['real_name']) ?></h2>
        <span class="badge badge-primary"><?= e(role_name($user['role'])) ?></span>
        <span class="text-muted"><?= e($user['branch_name']) ?></span>
        <?php if ($user['is_engineer']): ?><span class="badge badge-success">工程師</span><?php endif; ?>
        <?php if (!$user['is_active']): ?><span class="badge badge-danger">已停用</span><?php endif; ?>
        <?php if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()): ?>
            <span class="badge badge-danger">已鎖定</span>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <?php
        $isBoss = Auth::hasPermission('all');
        $canManageStaff = Auth::hasPermission('staff.manage');
        $canManageSkills = Auth::hasPermission('staff_skills.manage') || $canManageStaff;
        $skillsOnly = !$canManageStaff && !Auth::hasPermission('staff.view') && $canManageSkills;
        $isSelf = ($user['id'] == Auth::id());
        ?>
        <?php if ($isSelf): ?>
        <a href="/staff.php?action=change_password" class="btn btn-outline btn-sm">修改密碼</a>
        <?php endif; ?>
        <?php if ($canManageStaff || $canManageSkills): ?>
        <a href="/staff.php?action=edit&id=<?= $user['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <?php endif; ?>
        <?php if ($isBoss): ?>
        <a href="/staff.php?action=reset_password&id=<?= $user['id'] ?>" class="btn btn-outline btn-sm">重設密碼</a>
        <?php endif; ?>
        <?php if ($canManageSkills): ?>
        <a href="/staff.php?action=skills&id=<?= $user['id'] ?>" class="btn btn-outline btn-sm">技能設定</a>
        <a href="/staff.php?action=pairs&id=<?= $user['id'] ?>" class="btn btn-outline btn-sm">配對表</a>
        <?php endif; ?>
        <?php if ($isBoss): ?>
        <a href="/staff.php?action=permissions&id=<?= $user['id'] ?>" class="btn btn-outline btn-sm">權限設定</a>
        <?php if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()): ?>
        <a href="/staff.php?action=unlock&id=<?= $user['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
           class="btn btn-danger btn-sm" onclick="return confirm('確定解除此帳號的鎖定?')">解除鎖定</a>
        <?php endif; ?>
        <?php if (!$isSelf): ?>
        <a href="/staff.php?action=toggle&id=<?= $user['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
           class="btn <?= $user['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-sm"
           onclick="return confirm('確定<?= $user['is_active'] ? '停用' : '啟用' ?>此帳號?')"><?= $user['is_active'] ? '停用帳號' : '啟用帳號' ?></a>
        <?php endif; ?>
        <?php endif; ?>
        <?= back_button('/staff.php') ?>
    </div>
</div>

<!-- 基本資料 -->
<div class="card">
    <div class="card-header">基本資料</div>
    <div class="detail-grid">
        <?php if (!$skillsOnly): ?>
        <div class="detail-item"><span class="detail-label">帳號</span><span class="detail-value"><?= e($user['username']) ?></span></div>
        <?php if (Auth::user()['role'] === 'boss' && !empty($user['plain_password'])): ?>
        <div class="detail-item">
            <span class="detail-label">密碼</span>
            <span class="detail-value">
                <span id="pwdMask">••••••</span>
                <span id="pwdPlain" style="display:none"><?= e($user['plain_password']) ?></span>
                <button type="button" onclick="var m=document.getElementById('pwdMask'),p=document.getElementById('pwdPlain');if(m.style.display!=='none'){m.style.display='none';p.style.display='inline';this.textContent='🙈'}else{m.style.display='inline';p.style.display='none';this.textContent='👁'}" style="background:none;border:none;cursor:pointer;font-size:1rem;padding:0 4px">👁</button>
            </span>
        </div>
        <?php endif; ?>
        <div class="detail-item"><span class="detail-label">電話</span><span class="detail-value"><?= e($user['phone'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">Email</span><span class="detail-value"><?= e($user['email'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">最後登入</span><span class="detail-value"><?= format_datetime($user['last_login_at']) ?: '從未登入' ?></span></div>
        <div class="detail-item">
            <span class="detail-label">帳號狀態</span>
            <span class="detail-value">
                <?php if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()): ?>
                    <span class="text-danger">鎖定中 (至 <?= format_datetime($user['locked_until']) ?>，失敗 <?= (int)$user['failed_login_count'] ?> 次)</span>
                <?php elseif (($user['failed_login_count'] ?? 0) > 0): ?>
                    <span class="text-danger">已失敗 <?= (int)$user['failed_login_count'] ?> 次</span>
                <?php else: ?>
                    <span class="text-success">正常</span>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($user['is_engineer']): ?>
<!-- 施工配合度 -->
<div class="card">
    <div class="card-header">施工配合度</div>
    <div class="detail-grid">
        <?php
        $avMap = ['high' => '高', 'medium' => '中', 'low' => '低'];
        $avColor = ['high' => 'text-success', 'medium' => '', 'low' => 'text-danger'];
        ?>
        <div class="detail-item">
            <span class="detail-label">假日施工配合度</span>
            <span class="detail-value <?= $avColor[$user['holiday_availability'] ?? 'medium'] ?>"><?= $avMap[$user['holiday_availability'] ?? 'medium'] ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">夜間施工配合度</span>
            <span class="detail-value <?= $avColor[$user['night_availability'] ?? 'medium'] ?>"><?= $avMap[$user['night_availability'] ?? 'medium'] ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 注意事項 -->
<?php if (!empty($user['caution_notes'])): ?>
<div class="card">
    <div class="card-header">⚠ 注意事項</div>
    <div style="padding:4px 0;color:#e65100;font-weight:600"><?= nl2br(e($user['caution_notes'])) ?></div>
</div>
<?php endif; ?>

<!-- 技能 -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>技能</span>
        <?php if ($canManageSkills): ?>
        <a href="/staff.php?action=skills&id=<?= $user['id'] ?>" class="btn btn-outline btn-sm">技能設定</a>
        <?php endif; ?>
    </div>
    <?php if (empty($userSkills)): ?>
        <p class="text-muted">尚未設定技能</p>
    <?php else: ?>
    <div class="skills-display">
        <?php
        $lastGroup = '';
        $lastCat = '';
        foreach ($userSkills as $sk):
            $group = isset($sk['skill_group']) ? $sk['skill_group'] : '';
            if ($group && $group !== $lastGroup):
                $lastGroup = $group;
                $lastCat = '';
        ?>
        <div class="skill-group-label"><?= e($group) ?></div>
        <?php endif; ?>
        <?php
            if ($sk['category'] !== $lastCat):
                $lastCat = $sk['category'];
        ?>
        <div class="skill-category-label"><?= e($sk['category']) ?></div>
        <?php endif; ?>
        <div class="skill-display-item">
            <span><?= e($sk['skill_name']) ?></span>
            <span class="stars"><?= str_repeat('&#9733;', $sk['proficiency']) ?><?= str_repeat('&#9734;', 5 - $sk['proficiency']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (!$skillsOnly): ?>
<!-- 證照 -->
<div class="card">
    <div class="card-header">證照 / 工作證</div>
    <?php if (!empty($userCerts)): ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>證照名稱</th><th>證號</th><th>發證日</th><th>到期日</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($userCerts as $uc): ?>
                <tr>
                    <td><?= e($uc['cert_name']) ?></td>
                    <td><?= e($uc['cert_number'] ?: '-') ?></td>
                    <td><?= format_date($uc['issue_date']) ?: '-' ?></td>
                    <td>
                        <?php if ($uc['expiry_date']): ?>
                            <span class="<?= $uc['is_expiring'] ? 'text-danger' : '' ?>">
                                <?= format_date($uc['expiry_date']) ?>
                                <?= $uc['is_expiring'] ? ' (即將到期!)' : '' ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (Auth::hasPermission('staff.manage')): ?>
                        <a href="/staff.php?action=remove_cert&cert_id=<?= $uc['id'] ?>&user_id=<?= $user['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                           class="btn btn-danger btn-sm" onclick="return confirm('確定刪除此證照記錄?')">刪除</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-muted">尚無證照記錄</p>
    <?php endif; ?>

    <?php if (Auth::hasPermission('staff.manage')): ?>
    <div class="mt-2">
        <div class="card-header">新增證照</div>
        <form method="POST" action="/staff.php?action=add_cert" class="mt-1">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>證照類型</label>
                    <select name="certification_id" class="form-control" required>
                        <option value="">請選擇</option>
                        <?php
                        $db = Database::getInstance();
                        $certTypes = $db->query('SELECT * FROM certifications WHERE is_active = 1 ORDER BY name')->fetchAll();
                        foreach ($certTypes as $ct):
                        ?>
                        <option value="<?= $ct['id'] ?>"><?= e($ct['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>證照號碼</label>
                    <input type="text" name="cert_number" class="form-control">
                </div>
                <div class="form-group">
                    <label>發證日期</label>
                    <input type="date" max="2099-12-31" name="issue_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>到期日期</label>
                    <input type="date" max="2099-12-31" name="expiry_date" class="form-control">
                </div>
                <div class="form-group" style="align-self:flex-end">
                    <button type="submit" class="btn btn-primary btn-sm">新增</button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- 廠商上課證 -->
<div class="card">
    <div class="card-header">廠商上課證</div>
    <?php if (!empty($vendorTrainings)): ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>客戶/廠商</th><th>上課日期</th><th>有效期限</th><th>備註</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($vendorTrainings as $vt): ?>
                <tr>
                    <td><?= e($vt['vendor_name']) ?></td>
                    <td><?= format_date($vt['training_date']) ?: '-' ?></td>
                    <td>
                        <?php if ($vt['expiry_date']): ?>
                            <?php if ($vt['is_expired']): ?>
                                <span class="text-danger"><?= format_date($vt['expiry_date']) ?> (已過期)</span>
                            <?php elseif ($vt['is_expiring']): ?>
                                <span class="text-danger"><?= format_date($vt['expiry_date']) ?> (即將到期!)</span>
                            <?php else: ?>
                                <?= format_date($vt['expiry_date']) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= e($vt['note'] ?: '-') ?></td>
                    <td>
                        <?php if (Auth::hasPermission('staff.manage')): ?>
                        <a href="/staff.php?action=remove_vendor_training&vt_id=<?= $vt['id'] ?>&user_id=<?= $user['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                           class="btn btn-danger btn-sm" onclick="return confirm('確定刪除此上課證記錄?')">刪除</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-muted">尚無廠商上課證記錄</p>
    <?php endif; ?>

    <?php if (Auth::hasPermission('staff.manage')): ?>
    <div class="mt-2">
        <div class="card-header">新增廠商上課證</div>
        <form method="POST" action="/staff.php?action=add_vendor_training" class="mt-1">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>客戶/廠商名稱 *</label>
                    <input type="text" name="vendor_name" class="form-control" required placeholder="例：台積電">
                </div>
                <div class="form-group">
                    <label>上課日期</label>
                    <input type="date" max="2099-12-31" name="training_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>有效期限</label>
                    <input type="date" max="2099-12-31" name="expiry_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>備註</label>
                    <input type="text" name="note" class="form-control" placeholder="選填">
                </div>
                <div class="form-group" style="align-self:flex-end">
                    <button type="submit" class="btn btn-primary btn-sm">新增</button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- 證照文件上傳 -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>證照文件</span>
    </div>
    <div class="doc-grid">
        <?php
        $csrfToken = Session::getCsrfToken();
        if (!empty($docTypes)):
            foreach ($docTypes as $dt):
                $typeKey = $dt['type_key'];
                $typeLabel = $dt['type_label'];
                $doc = isset($docMap[$typeKey]) ? $docMap[$typeKey] : null;
                $hasFile = ($doc && !empty($doc['file_path']));
                $isPdf = $hasFile && (strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION)) === 'pdf');
        ?>
        <div class="doc-item" data-type="<?= e($typeKey) ?>" data-label="<?= e($typeLabel) ?>">
            <div class="doc-label"><?= e($typeLabel) ?></div>
            <div class="doc-content">
                <?php if ($hasFile): ?>
                <div class="doc-thumb-wrap" data-doc-id="<?= $doc['id'] ?>">
                    <?php if ($isPdf): ?>
                    <a href="<?= e($doc['file_path']) ?>" target="_blank" class="doc-thumb doc-pdf-icon" title="<?= e($doc['file_name']) ?>">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#e53935" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><text x="7" y="18" font-size="6" fill="#e53935" stroke="none" font-weight="bold">PDF</text></svg>
                    </a>
                    <?php else: ?>
                    <a href="<?= e($doc['file_path']) ?>" class="doc-thumb doc-lightbox-trigger" data-full="<?= e($doc['file_path']) ?>" title="<?= e($doc['file_name']) ?>">
                        <img src="<?= e($doc['file_path']) ?>" alt="<?= e($typeLabel) ?>">
                    </a>
                    <?php endif; ?>
                    <?php if ($canManageStaff): ?>
                    <button type="button" class="doc-delete-btn" data-doc-id="<?= $doc['id'] ?>" title="刪除">&times;</button>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <?php if ($canManageStaff): ?>
                    <label class="doc-upload-area">
                        <input type="file" class="doc-file-input" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" style="display:none">
                        <span class="doc-upload-icon">+</span>
                        <span class="doc-upload-text">上傳</span>
                    </label>
                    <?php else: ?>
                    <div class="doc-empty">-</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
            endforeach;
        endif;
        ?>
    </div>

    <?php if (Auth::hasPermission('all')): ?>
    <div class="mt-2" style="border-top:1px solid var(--gray-200);padding-top:12px">
        <button type="button" id="addDocTypeBtn" class="btn btn-outline btn-sm">+ 自訂文件類型</button>
        <div id="addDocTypeForm" style="display:none;margin-top:8px">
            <div class="d-flex gap-1 align-center">
                <input type="text" id="newDocTypeLabel" class="form-control" placeholder="文件類型名稱" style="max-width:250px">
                <button type="button" id="saveDocTypeBtn" class="btn btn-primary btn-sm">新增</button>
                <button type="button" id="cancelDocTypeBtn" class="btn btn-outline btn-sm">取消</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; // !$skillsOnly — 證照/廠商上課證/文件區塊 ?>

<!-- Lightbox -->
<div id="docLightbox" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);z-index:9999;cursor:pointer;justify-content:center;align-items:center">
    <img id="docLightboxImg" style="max-width:90%;max-height:90%;object-fit:contain;border-radius:4px" src="" alt="">
    <button type="button" style="position:absolute;top:16px;right:24px;background:none;border:none;color:#fff;font-size:2rem;cursor:pointer">&times;</button>
</div>

<script>
(function(){
    var userId = <?= (int)$user['id'] ?>;
    var csrfToken = '<?= e($csrfToken) ?>';

    // File upload
    document.querySelectorAll('.doc-file-input').forEach(function(input){
        input.addEventListener('change', function(){
            if (!this.files || !this.files[0]) return;
            var file = this.files[0];
            var item = this.closest('.doc-item');
            var docType = item.getAttribute('data-type');
            var docLabel = item.getAttribute('data-label');
            var contentDiv = item.querySelector('.doc-content');

            // Show loading
            contentDiv.innerHTML = '<div class="doc-upload-area" style="pointer-events:none"><span class="doc-upload-text">上傳中...</span></div>';

            var fd = new FormData();
            fd.append('file', file);
            fd.append('user_id', userId);
            fd.append('doc_type', docType);
            fd.append('doc_label', docLabel);
            fd.append('csrf_token', csrfToken);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/staff.php?action=upload_doc');
            xhr.onload = function(){
                try {
                    var res = JSON.parse(xhr.responseText);
                } catch(e) {
                    alert('上傳失敗：回應格式錯誤');
                    location.reload();
                    return;
                }
                if (!res.success) {
                    alert('上傳失敗：' + (res.message || '未知錯誤'));
                    location.reload();
                    return;
                }
                // Rebuild thumb
                var html = '<div class="doc-thumb-wrap" data-doc-id="' + res.doc_id + '">';
                if (res.is_pdf) {
                    html += '<a href="' + res.file_path + '" target="_blank" class="doc-thumb doc-pdf-icon" title="' + res.file_name + '">';
                    html += '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#e53935" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><text x="7" y="18" font-size="6" fill="#e53935" stroke="none" font-weight="bold">PDF</text></svg>';
                    html += '</a>';
                } else {
                    html += '<a href="' + res.file_path + '" class="doc-thumb doc-lightbox-trigger" data-full="' + res.file_path + '" title="' + res.file_name + '">';
                    html += '<img src="' + res.file_path + '" alt="' + docLabel + '">';
                    html += '</a>';
                }
                html += '<button type="button" class="doc-delete-btn" data-doc-id="' + res.doc_id + '" title="刪除">&times;</button>';
                html += '</div>';
                contentDiv.innerHTML = html;
                bindEvents();
            };
            xhr.onerror = function(){
                alert('上傳失敗：網路錯誤');
                location.reload();
            };
            xhr.send(fd);
        });
    });

    // Delete
    function bindEvents() {
        document.querySelectorAll('.doc-delete-btn').forEach(function(btn){
            btn.onclick = function(){
                if (!confirm('確定刪除此文件？')) return;
                var docId = this.getAttribute('data-doc-id');
                var wrap = this.closest('.doc-thumb-wrap');
                var item = this.closest('.doc-item');
                var fd = new FormData();
                fd.append('doc_id', docId);
                fd.append('csrf_token', csrfToken);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/staff.php?action=delete_doc');
                xhr.onload = function(){
                    try { var res = JSON.parse(xhr.responseText); } catch(e) { location.reload(); return; }
                    if (res.success) {
                        var contentDiv = item.querySelector('.doc-content');
                        contentDiv.innerHTML = '<label class="doc-upload-area"><input type="file" class="doc-file-input" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" style="display:none"><span class="doc-upload-icon">+</span><span class="doc-upload-text">上傳</span></label>';
                        // Re-bind file input
                        contentDiv.querySelector('.doc-file-input').addEventListener('change', function(){
                            var fakeEvent = new Event('change');
                            // Trigger by re-dispatching won't work; just reload
                            location.reload();
                        });
                        // Better: re-bind properly
                        bindFileInputs();
                    } else {
                        alert('刪除失敗：' + (res.message || ''));
                    }
                };
                xhr.send(fd);
            };
        });

        // Lightbox
        document.querySelectorAll('.doc-lightbox-trigger').forEach(function(a){
            a.onclick = function(e){
                e.preventDefault();
                var src = this.getAttribute('data-full');
                document.getElementById('docLightboxImg').src = src;
                document.getElementById('docLightbox').style.display = 'flex';
            };
        });
    }

    function bindFileInputs() {
        document.querySelectorAll('.doc-file-input').forEach(function(input){
            input.onchange = function(){
                if (!this.files || !this.files[0]) return;
                var file = this.files[0];
                var item = this.closest('.doc-item');
                var docType = item.getAttribute('data-type');
                var docLabel = item.getAttribute('data-label');
                var contentDiv = item.querySelector('.doc-content');
                contentDiv.innerHTML = '<div class="doc-upload-area" style="pointer-events:none"><span class="doc-upload-text">上傳中...</span></div>';
                var fd = new FormData();
                fd.append('file', file);
                fd.append('user_id', userId);
                fd.append('doc_type', docType);
                fd.append('doc_label', docLabel);
                fd.append('csrf_token', csrfToken);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/staff.php?action=upload_doc');
                xhr.onload = function(){ location.reload(); };
                xhr.onerror = function(){ alert('上傳失敗'); location.reload(); };
                xhr.send(fd);
            };
        });
    }

    bindEvents();

    // Lightbox close
    var lb = document.getElementById('docLightbox');
    if (lb) {
        lb.onclick = function(){ this.style.display = 'none'; };
    }

    // Add custom doc type
    var addBtn = document.getElementById('addDocTypeBtn');
    var addForm = document.getElementById('addDocTypeForm');
    var saveBtn = document.getElementById('saveDocTypeBtn');
    var cancelBtn = document.getElementById('cancelDocTypeBtn');
    if (addBtn) {
        addBtn.onclick = function(){ addForm.style.display = 'block'; addBtn.style.display = 'none'; document.getElementById('newDocTypeLabel').focus(); };
    }
    if (cancelBtn) {
        cancelBtn.onclick = function(){ addForm.style.display = 'none'; addBtn.style.display = ''; };
    }
    if (saveBtn) {
        saveBtn.onclick = function(){
            var label = document.getElementById('newDocTypeLabel').value.trim();
            if (!label) { alert('請輸入名稱'); return; }
            var fd = new FormData();
            fd.append('type_label', label);
            fd.append('csrf_token', csrfToken);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/staff.php?action=add_doc_type');
            xhr.onload = function(){
                try { var res = JSON.parse(xhr.responseText); } catch(e) { alert('失敗'); return; }
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.message || '新增失敗');
                }
            };
            xhr.send(fd);
        };
    }
})();
</script>

<style>
.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: .8rem; color: var(--gray-500); }
.skills-display { display: flex; flex-direction: column; gap: 2px; }
.skill-group-label { font-weight: 700; color: var(--gray-900); margin-top: 12px; padding: 6px 0 2px; border-bottom: 2px solid var(--primary); font-size: .9rem; }
.skill-group-label:first-child { margin-top: 0; }
.skill-category-label { font-weight: 600; color: var(--primary); margin-top: 8px; font-size: .85rem; }
.skill-display-item { display: flex; justify-content: space-between; padding: 4px 0; font-size: .9rem; }
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 140px; }
@media (max-width: 767px) { .detail-grid { grid-template-columns: 1fr; } }

/* Document grid */
.doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
.doc-item { display: flex; flex-direction: column; align-items: center; text-align: center; }
.doc-item .doc-label { font-size: .8rem; color: var(--gray-600); margin-bottom: 6px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.doc-content { width: 80px; height: 80px; position: relative; }
.doc-thumb-wrap { position: relative; width: 80px; height: 80px; }
.doc-thumb { display: block; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid var(--gray-200); background: var(--gray-50); display: flex; align-items: center; justify-content: center; }
.doc-thumb img { width: 100%; height: 100%; object-fit: cover; }
.doc-pdf-icon { background: #fff5f5; }
.doc-delete-btn { position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; border-radius: 50%; background: #e53935; color: #fff; border: none; font-size: 14px; line-height: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,.3); }
.doc-delete-btn:hover { background: #c62828; }
.doc-upload-area { width: 80px; height: 80px; border: 2px dashed var(--gray-300); border-radius: 6px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: border-color .2s; }
.doc-upload-area:hover { border-color: var(--primary); }
.doc-upload-icon { font-size: 1.5rem; color: var(--gray-400); line-height: 1; }
.doc-upload-text { font-size: .7rem; color: var(--gray-400); margin-top: 2px; }
.doc-empty { width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; color: var(--gray-300); font-size: 1.2rem; }
@media (max-width: 767px) { .doc-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); } }
</style>
