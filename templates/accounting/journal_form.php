<?php
// 複製模式：用 prefillEntry 預填但當作新增
$src = isset($prefillEntry) ? $prefillEntry : $entry;
$isCopy = isset($prefillEntry) && !$entry;
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1><?= $entry ? '編輯傳票 - ' . e($entry['voucher_number']) : ($isCopy ? '複製傳票' : '新增傳票') ?></h1>
    <?= back_button('/accounting.php') ?>
</div>

<form method="post" id="journalForm" enctype="multipart/form-data" action="/accounting.php?action=<?= $entry ? 'journal_edit&id=' . $entry['id'] : 'journal_create' ?>"
    <?= csrf_field() ?>

    <div class="card" style="padding:16px;margin-bottom:16px">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
            <div>
                <label>傳票號碼</label>
                <input type="text" id="fldVoucherNumber" class="form-control" value="<?= $entry ? e($entry['voucher_number']) : e($nextNumber) ?>" readonly style="background:#f5f5f5">
            </div>
            <div>
                <label>傳票日期 <span style="color:red">*</span></label>
                <input type="date" name="voucher_date" id="fldVoucherDate" class="form-control" value="<?= $src ? e($src['voucher_date']) : date('Y-m-d') ?>" required <?= $entry ? '' : 'onchange="updateVoucherNumber()"' ?>>
            </div>
            <div>
                <label>傳票類型</label>
                <select name="voucher_type" class="form-control">
                    <?php foreach ($voucherTypeOptions as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= ($src && $src['voucher_type'] === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
            <div>
                <label>備註</label>
                <input type="text" name="description" class="form-control" value="<?= $src ? e($src['description']) : '' ?>" placeholder="傳票備註">
            </div>
            <div>
                <label>附件</label>
                <?php if ($entry && !empty($entry['attachment'])):
                    $attachments = json_decode($entry['attachment'], true);
                    if (!is_array($attachments)) $attachments = array($entry['attachment']);
                ?>
                <div style="margin-bottom:4px;display:flex;gap:4px;flex-wrap:wrap">
                    <?php foreach ($attachments as $ai => $aPath): if (!$aPath) continue; ?>
                    <a href="<?= e($aPath) ?>" target="_blank" class="btn btn-outline btn-sm">📎 附件<?= count($attachments) > 1 ? ($ai+1) : '' ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <input type="file" name="attachment[]" class="form-control" style="padding:6px" multiple>
            </div>
        </div>
    </div>

    <!-- Journal Lines -->
    <div class="card" style="padding:16px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="margin:0">分錄明細</h3>
            <button type="button" class="btn btn-primary btn-sm" onclick="addLine()">+ 新增行</button>
        </div>
        <div style="overflow-x:auto">
            <table class="data-table" style="width:100%" id="linesTable">
                <thead>
                    <tr>
                        <th style="width:24px"></th>
                        <th style="width:36px">#</th>
                        <th style="min-width:180px">會計科目 <span style="color:red">*</span></th>
                        <th style="width:110px">部門中心</th>
                        <th style="width:80px">往來類型</th>
                        <th style="width:70px">往來編號</th>
                        <th style="width:90px">往來對象</th>
                        <th style="width:100px;text-align:right">借方金額</th>
                        <th style="width:100px;text-align:right">貸方金額</th>
                        <th style="width:70px">立沖</th>
                        <th style="width:90px;text-align:right">未沖額</th>
                        <th style="width:90px;text-align:right">本次沖帳</th>
                        <th style="min-width:90px">摘要</th>
                        <th style="width:36px"></th>
                    </tr>
                </thead>
                <tbody id="linesBody">
                </tbody>
                <tfoot>
                    <tr style="font-weight:bold;background:#f8f9fa">
                        <td colspan="7" style="text-align:right">合計</td>
                        <td style="text-align:right" id="totalDebit">0</td>
                        <td style="text-align:right" id="totalCredit">0</td>
                        <td colspan="4">
                            <span id="balanceStatus" style="font-size:0.9em"></span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px">
        <a href="/accounting.php?action=journals" class="btn btn-secondary">取消</a>
        <button type="submit" class="btn btn-primary" id="submitBtn">儲存傳票</button>
    </div>
</form>

<script>
// 防呆：移除千位逗號避免 parseInt('1,143') → 1
// 用法：_jfNum(el.value) 或 _jfNum(el)
function _jfNum(v) {
    if (v && typeof v === 'object' && 'value' in v) v = v.value;
    return parseInt(String(v || '0').replace(/,/g, '')) || 0;
}
function updateVoucherNumber() {
    var date = document.getElementById('fldVoucherDate').value;
    if (!date) return;
    fetch('/accounting.php?action=ajax_voucher_number&date=' + date)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.number) document.getElementById('fldVoucherNumber').value = d.number;
        });
}

var accounts = <?= json_encode($accounts, JSON_UNESCAPED_UNICODE) ?>;
var costCenters = <?= json_encode($costCenters, JSON_UNESCAPED_UNICODE) ?>;
var customers = <?= json_encode(isset($customers) ? $customers : array(), JSON_UNESCAPED_UNICODE) ?>;
var vendors = <?= json_encode(isset($vendors) ? $vendors : array(), JSON_UNESCAPED_UNICODE) ?>;
var lineIndex = 0;

// === 會計科目選擇彈窗 ===
var _acctPickerTarget = null; // 目前要填入的欄位 uid

function buildAccountSelect(name, selectedId) {
    var uid = 'acct_' + Math.random().toString(36).substr(2, 6);
    var selectedText = '';
    if (selectedId) {
        for (var i = 0; i < accounts.length; i++) {
            if (accounts[i].id == selectedId) {
                selectedText = accounts[i].code + ' ' + accounts[i].name;
                break;
            }
        }
    }
    var html = '<div class="acct-picker">' +
        '<input type="hidden" name="' + name + '" id="' + uid + '_val" value="' + (selectedId || '') + '" required>' +
        '<div class="acct-display" id="' + uid + '_txt" onclick="openAcctModal(\'' + uid + '\')">' +
        (selectedText || '<span style="color:#999">點擊選擇科目</span>') +
        '</div></div>';
    return html;
}

function openAcctModal(uid) {
    _acctPickerTarget = uid;
    var modal = document.getElementById('acctModal');
    var search = document.getElementById('acctModalSearch');
    modal.style.display = 'flex';
    search.value = '';
    renderAcctList('');
    setTimeout(function() { search.focus(); }, 100);
}

function closeAcctModal() {
    document.getElementById('acctModal').style.display = 'none';
    _acctPickerTarget = null;
}

function renderAcctList(keyword) {
    var tbody = document.getElementById('acctModalBody');
    var kw = (keyword || '').toLowerCase().trim();
    var html = '';
    var count = 0;
    for (var i = 0; i < accounts.length; i++) {
        var a = accounts[i];
        var matchCode = a.code.toLowerCase().indexOf(kw) !== -1;
        var matchName = a.name.toLowerCase().indexOf(kw) !== -1;
        if (kw && !matchCode && !matchName) continue;
        var cls = a.is_parent ? ' style="color:#999;font-style:italic"' : ' style="cursor:pointer"';
        var clickAttr = a.is_parent ? '' : ' onclick="pickAcct(' + a.id + ',\'' + a.code.replace(/'/g, "\\'") + ' ' + a.name.replace(/'/g, "\\'") + '\')"';
        html += '<tr class="acct-row"' + cls + clickAttr + '>' +
            '<td style="white-space:nowrap;font-family:monospace">' + a.code + '</td>' +
            '<td>' + a.name + '</td>' +
            '<td style="color:#aaa;font-size:.8em">' + (a.category_name || '') + '</td>' +
            '</tr>';
        count++;
    }
    if (count === 0) {
        html = '<tr><td colspan="3" style="text-align:center;padding:20px;color:#999">無符合的科目</td></tr>';
    }
    tbody.innerHTML = html;
    document.getElementById('acctModalCount').textContent = '共 ' + count + ' 筆';
}

function pickAcct(id, label) {
    if (!_acctPickerTarget) return;
    var uid = _acctPickerTarget;
    document.getElementById(uid + '_val').value = id;
    document.getElementById(uid + '_txt').innerHTML = label;
    document.getElementById(uid + '_txt').style.color = '';
    closeAcctModal();

    // 找科目資料並套用立沖規則
    var acct = null;
    for (var i = 0; i < accounts.length; i++) {
        if (accounts[i].id == id) { acct = accounts[i]; break; }
    }
    if (!acct) return;

    // 找行 index
    var hiddenInput = document.getElementById(uid + '_val');
    var tr = hiddenInput.closest('tr');
    if (!tr) return;
    var idx = tr.id.replace('line_', '');
    // 科目切換時重置自動設定標記
    if (typeof offsetAutoSet !== 'undefined') delete offsetAutoSet[idx];
    applyOffsetRules(idx, acct);
}

// 根據科目立沖屬性控制該行
function applyOffsetRules(idx, acct) {
    var relTypeSel = document.querySelector('select[data-idx="' + idx + '"]');
    var idDisplay = document.getElementById('rel_id_display_' + idx);
    var nameDisplay = document.getElementById('rel_name_display_' + idx);
    var offsetFlagSel = document.getElementById('offset_flag_' + idx);
    var offsetAmtInput = document.getElementById('offset_amt_' + idx);

    if (!relTypeSel || !offsetFlagSel) return;

    var isOffset = (acct.offset_type === '立沖科目');

    if (isOffset) {
        // 立沖科目：啟用往來 + 立沖欄位
        relTypeSel.disabled = false;
        offsetFlagSel.disabled = false;
        offsetAmtInput.disabled = false;

        // 依科目 relate_type 預設往來類型
        var rt = acct.relate_type || '';
        if (rt === '客戶') {
            relTypeSel.value = 'customer';
        } else if (rt === '廠商') {
            relTypeSel.value = 'vendor';
        } else if (rt && rt !== '不核算') {
            relTypeSel.value = 'other';
        }
        onRelTypeChange(parseInt(idx));

        // 預設立帳
        if (offsetFlagSel.value === '0') {
            offsetFlagSel.value = '1';
        }
        // 監聽立沖切換
        offsetFlagSel.onchange = function() { onOffsetFlagChange(idx); };
    } else {
        // 非立沖：往來類型不核算，禁用
        relTypeSel.value = '';
        relTypeSel.disabled = true;
        if (idDisplay) idDisplay.innerHTML = '<span style="color:#ccc">--</span>';
        if (nameDisplay) nameDisplay.innerHTML = '<span style="color:#ccc">--</span>';
        document.getElementById('rel_id_' + idx).value = '';
        document.getElementById('rel_name_' + idx).value = '';

        // 立沖欄位禁用
        offsetFlagSel.value = '0';
        offsetFlagSel.disabled = true;
        offsetAmtInput.value = '';
        offsetAmtInput.disabled = true;
    }
}

function onAcctSearch() {
    var kw = document.getElementById('acctModalSearch').value;
    renderAcctList(kw);
}

// === 拖曳移動彈窗 ===
(function() {
    var header = null, panel = null, startX, startY, origX, origY, dragging = false;

    function onReady() {
        // 會計科目彈窗
        header = document.getElementById('acctModalHeader');
        if (!header) return;
        panel = header.parentElement;

        // 往來對象彈窗也加拖曳
        var relHeader = document.getElementById('relModalHeader');
        if (relHeader) {
            var relPanel = relHeader.parentElement;
            relHeader.addEventListener('mousedown', function(e) { genericDragStart(e, relPanel); });
            relHeader.addEventListener('touchstart', function(e) { genericDragStartTouch(e, relPanel); }, {passive: false});
        }
        header.addEventListener('mousedown', dragStart);
        header.addEventListener('touchstart', dragStartTouch, {passive: false});
    }

    // 通用拖曳（往來對象彈窗用）
    var _dragPanel = null;
    function genericDragStart(e, p) {
        if (e.target.tagName === 'BUTTON') return;
        _dragPanel = p; dragging = true;
        var rect = p.getBoundingClientRect();
        startX = e.clientX; startY = e.clientY; origX = rect.left; origY = rect.top;
        p.style.position = 'fixed'; p.style.left = origX + 'px'; p.style.top = origY + 'px'; p.style.margin = '0';
        document.addEventListener('mousemove', genericDragMove);
        document.addEventListener('mouseup', genericDragEnd);
        e.preventDefault();
    }
    function genericDragStartTouch(e, p) {
        if (e.target.tagName === 'BUTTON') return;
        var t = e.touches[0]; _dragPanel = p; dragging = true;
        var rect = p.getBoundingClientRect();
        startX = t.clientX; startY = t.clientY; origX = rect.left; origY = rect.top;
        p.style.position = 'fixed'; p.style.left = origX + 'px'; p.style.top = origY + 'px'; p.style.margin = '0';
        document.addEventListener('touchmove', genericDragMoveTouch, {passive: false});
        document.addEventListener('touchend', genericDragEnd);
        e.preventDefault();
    }
    function genericDragMove(e) { if (!dragging || !_dragPanel) return; _dragPanel.style.left = (origX + e.clientX - startX) + 'px'; _dragPanel.style.top = (origY + e.clientY - startY) + 'px'; }
    function genericDragMoveTouch(e) { if (!dragging || !_dragPanel) return; var t = e.touches[0]; _dragPanel.style.left = (origX + t.clientX - startX) + 'px'; _dragPanel.style.top = (origY + t.clientY - startY) + 'px'; e.preventDefault(); }
    function genericDragEnd() { dragging = false; _dragPanel = null; document.removeEventListener('mousemove', genericDragMove); document.removeEventListener('mouseup', genericDragEnd); document.removeEventListener('touchmove', genericDragMoveTouch); document.removeEventListener('touchend', genericDragEnd); }

    function dragStart(e) {
        if (e.target.tagName === 'BUTTON') return;
        dragging = true;
        var rect = panel.getBoundingClientRect();
        startX = e.clientX; startY = e.clientY;
        origX = rect.left; origY = rect.top;
        panel.style.position = 'fixed';
        panel.style.left = origX + 'px';
        panel.style.top = origY + 'px';
        panel.style.margin = '0';
        document.addEventListener('mousemove', dragMove);
        document.addEventListener('mouseup', dragEnd);
        e.preventDefault();
    }

    function dragStartTouch(e) {
        if (e.target.tagName === 'BUTTON') return;
        var t = e.touches[0];
        dragging = true;
        var rect = panel.getBoundingClientRect();
        startX = t.clientX; startY = t.clientY;
        origX = rect.left; origY = rect.top;
        panel.style.position = 'fixed';
        panel.style.left = origX + 'px';
        panel.style.top = origY + 'px';
        panel.style.margin = '0';
        document.addEventListener('touchmove', dragMoveTouch, {passive: false});
        document.addEventListener('touchend', dragEnd);
        e.preventDefault();
    }

    function dragMove(e) {
        if (!dragging) return;
        panel.style.left = (origX + e.clientX - startX) + 'px';
        panel.style.top = (origY + e.clientY - startY) + 'px';
    }

    function dragMoveTouch(e) {
        if (!dragging) return;
        var t = e.touches[0];
        panel.style.left = (origX + t.clientX - startX) + 'px';
        panel.style.top = (origY + t.clientY - startY) + 'px';
        e.preventDefault();
    }

    function dragEnd() {
        dragging = false;
        document.removeEventListener('mousemove', dragMove);
        document.removeEventListener('mouseup', dragEnd);
        document.removeEventListener('touchmove', dragMoveTouch);
        document.removeEventListener('touchend', dragEnd);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }
})();

function buildCcSelect(name, selectedId) {
    var html = '<select name="' + name + '" class="form-control" style="width:100%">';
    html += '<option value="">--</option>';
    for (var i = 0; i < costCenters.length; i++) {
        var c = costCenters[i];
        var sel = (selectedId && c.id == selectedId) ? ' selected' : '';
        html += '<option value="' + c.id + '"' + sel + '>' + c.name + '</option>';
    }
    html += '</select>';
    return html;
}

function addLine(data) {
    var idx = lineIndex++;
    var accountId = data ? data.account_id : '';
    var ccId = data ? data.cost_center_id : '';
    var relType = data ? (data.relation_type || '') : '';
    var relId = data ? (data.relation_id || '') : '';
    var relName = data ? (data.relation_name || '') : '';
    var debit = data && parseFloat(data.debit_amount) > 0 ? parseInt(data.debit_amount) : '';
    var credit = data && parseFloat(data.credit_amount) > 0 ? parseInt(data.credit_amount) : '';
    var desc = data ? (data.description || '') : '';
    var offsetFlag = data ? (data.offset_flag || 0) : 0;
    var offsetAmt = data && parseFloat(data.offset_amount) > 0 ? parseInt(data.offset_amount) : '';

    var relTypeSelect = '<select name="lines[' + idx + '][relation_type]" class="form-control rel-type-sel" data-idx="' + idx + '" onchange="onRelTypeChange(' + idx + ')" style="font-size:.85rem;padding:4px">' +
        '<option value="">--</option>' +
        '<option value="customer"' + (relType === 'customer' ? ' selected' : '') + '>客戶</option>' +
        '<option value="vendor"' + (relType === 'vendor' ? ' selected' : '') + '>廠商</option>' +
        '<option value="other"' + (relType === 'other' ? ' selected' : '') + '>其他</option>' +
        '</select>';

    var relIdDisplay = relId || '<span style="color:#999">--</span>';
    var relNameShort = relName ? relName.substring(0, 6) : '';
    var relNameDisplay = relNameShort || '<span style="color:#999">--</span>';

    var relIdCell = '<div class="rel-cell" id="rel_cell_' + idx + '">' +
        '<input type="hidden" name="lines[' + idx + '][relation_id]" id="rel_id_' + idx + '" value="' + relId + '">' +
        '<input type="hidden" name="lines[' + idx + '][relation_name]" id="rel_name_' + idx + '" value="' + relName.replace(/"/g, '&quot;') + '">' +
        '<div class="acct-display" id="rel_id_display_' + idx + '" onclick="openRelModal(' + idx + ')" style="font-size:.85rem;min-height:30px">' + relIdDisplay + '</div>' +
        '</div>';

    var relNameCell = '<div id="rel_name_display_' + idx + '" style="font-size:.85rem;overflow:hidden;white-space:nowrap;text-overflow:ellipsis" title="' + relName.replace(/"/g, '&quot;') + '">' + relNameDisplay + '</div>';

    var tr = document.createElement('tr');
    tr.id = 'line_' + idx;
    tr.draggable = true;
    tr.innerHTML = '<td class="drag-handle" style="cursor:grab;text-align:center;color:#aaa;font-size:1.1rem;user-select:none" title="拖曳排序">&#9776;</td>' +
        '<td class="line-num">' + (idx + 1) + '</td>' +
        '<td>' + buildAccountSelect('lines[' + idx + '][account_id]', accountId) + '</td>' +
        '<td>' + buildCcSelect('lines[' + idx + '][cost_center_id]', ccId) + '</td>' +
        '<td>' + relTypeSelect + '</td>' +
        '<td>' + relIdCell + '</td>' +
        '<td>' + relNameCell + '</td>' +
        '<td><input type="number" name="lines[' + idx + '][debit_amount]" class="form-control debit-input" style="text-align:right;font-size:.85rem" step="1" min="0" value="' + debit + '" oninput="onDebitInput(this)" onfocus="this.select()"></td>' +
        '<td><input type="number" name="lines[' + idx + '][credit_amount]" class="form-control credit-input" style="text-align:right;font-size:.85rem" step="1" min="0" value="' + credit + '" oninput="onCreditInput(this)" onfocus="this.select()"></td>' +
        '<td><select name="lines[' + idx + '][offset_flag]" class="form-control offset-flag-sel" id="offset_flag_' + idx + '" style="font-size:.8rem;padding:4px" disabled>' +
            '<option value="0">--</option><option value="1"' + (offsetFlag == 1 ? ' selected' : '') + '>立帳</option><option value="2"' + (offsetFlag == 2 ? ' selected' : '') + '>沖帳</option></select></td>' +
        '<td><span id="unoffset_amt_' + idx + '" style="display:block;text-align:right;font-size:.85rem;color:#999;padding:6px 0">--</span></td>' +
        '<td><input type="number" name="lines[' + idx + '][offset_amount]" class="form-control offset-amt-input" id="offset_amt_' + idx + '" style="text-align:right;font-size:.85rem" step="1" min="0" value="' + offsetAmt + '" disabled></td>' +
        '<td><input type="text" name="lines[' + idx + '][description]" class="form-control" value="' + desc.replace(/"/g, '&quot;') + '" style="font-size:.85rem"></td>' +
        '<td style="white-space:nowrap"><button type="button" class="btn btn-sm btn-outline" onclick="insertLineBefore(' + idx + ')" title="插入行" style="padding:2px 6px;font-size:.75rem;margin-right:2px">+</button><button type="button" class="btn btn-sm btn-danger" onclick="removeLine(' + idx + ')" title="刪除">&times;</button></td>';
    document.getElementById('linesBody').appendChild(tr);

    // 如果往來類型是 other，改成文字輸入
    if (relType === 'other') {
        var nameDisplayEl = document.getElementById('rel_name_display_' + idx);
        showOtherInput(idx, relName, nameDisplayEl, relId);
    }
    calcTotals();
}

// 往來類型切換
function onRelTypeChange(idx) {
    var sel = document.querySelector('select[data-idx="' + idx + '"]');
    var val = sel.value;
    var cell = document.getElementById('rel_cell_' + idx);
    document.getElementById('rel_id_' + idx).value = '';
    document.getElementById('rel_name_' + idx).value = '';

    var idDisplay = document.getElementById('rel_id_display_' + idx);
    var nameDisplay = document.getElementById('rel_name_display_' + idx);

    if (val === 'other') {
        idDisplay.style.display = 'none';
        showOtherInput(idx, '', nameDisplay);
    } else if (val === 'customer' || val === 'vendor') {
        idDisplay.innerHTML = '<span style="color:#999">點擊</span>';
        idDisplay.style.display = '';
        nameDisplay.innerHTML = '<span style="color:#999">--</span>';
        var existInput = cell.querySelector('.rel-other-input');
        if (existInput) existInput.remove();
    } else {
        idDisplay.innerHTML = '<span style="color:#999">--</span>';
        idDisplay.style.display = '';
        nameDisplay.innerHTML = '<span style="color:#999">--</span>';
        var existInput = cell.querySelector('.rel-other-input');
        if (existInput) existInput.remove();
    }
}

function showOtherInput(idx, val, nameDisplayEl, existingRelId) {
    var cell = document.getElementById('rel_cell_' + idx);
    var existInput = cell.querySelector('.rel-other-input');
    if (existInput) existInput.remove();
    // 往來編號欄放文字輸入（編號）
    var idDisplay = document.getElementById('rel_id_display_' + idx);
    idDisplay.style.display = 'none';
    var initId = existingRelId || document.getElementById('rel_id_' + idx).value || '';
    var idInput = document.createElement('input');
    idInput.type = 'text';
    idInput.className = 'form-control rel-other-input';
    idInput.style.fontSize = '.85rem';
    idInput.placeholder = '編號';
    idInput.value = initId;
    idInput.oninput = function() {
        document.getElementById('rel_id_' + idx).value = this.value;
    };
    // 離開編號欄時自動查詢是否已有對應名稱
    idInput.addEventListener('blur', function() {
        checkOtherRelation(idx, this.value);
    });
    cell.appendChild(idInput);

    // 往來對象欄放文字輸入（名稱）
    if (nameDisplayEl) {
        nameDisplayEl.innerHTML = '';
        var nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.className = 'form-control rel-other-name-input';
        nameInput.style.fontSize = '.85rem';
        nameInput.placeholder = '名稱';
        nameInput.value = val || '';
        nameInput.oninput = function() {
            document.getElementById('rel_name_' + idx).value = this.value;
        };
        nameDisplayEl.appendChild(nameInput);
    }

    // 如果有初始編號，自動查詢
    if (initId && !val) {
        checkOtherRelation(idx, initId);
    }
}

// 查詢「其他」往來編號是否已有對應名稱，有的話自動帶入並鎖定
function checkOtherRelation(idx, relId) {
    if (!relId || relId.trim() === '') return;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/accounting.php?action=ajax_check_other_relation&relation_id=' + encodeURIComponent(relId.trim()));
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            var nameDisplay = document.getElementById('rel_name_display_' + idx);
            var nameInput = nameDisplay ? nameDisplay.querySelector('.rel-other-name-input') : null;
            if (res.found && res.relation_name) {
                // 自動帶入名稱並鎖定
                document.getElementById('rel_name_' + idx).value = res.relation_name;
                if (nameInput) {
                    nameInput.value = res.relation_name;
                    nameInput.readOnly = true;
                    nameInput.style.background = '#f5f5f5';
                    nameInput.title = '編號 ' + relId + ' 已綁定此名稱';
                }
            } else {
                // 沒有對應，解除鎖定
                if (nameInput) {
                    nameInput.readOnly = false;
                    nameInput.style.background = '';
                    nameInput.title = '';
                }
            }
        } catch(e) {}
    };
    xhr.send();
}

// 開啟往來對象選擇彈窗
var _relPickerIdx = null;
function openRelModal(idx) {
    var sel = document.querySelector('select[data-idx="' + idx + '"]');
    var relType = sel.value;
    if (!relType || relType === 'other') return;

    _relPickerIdx = idx;
    var modal = document.getElementById('relModal');
    var title = document.getElementById('relModalTitle');
    var search = document.getElementById('relModalSearch');
    title.textContent = relType === 'customer' ? '選擇客戶' : '選擇廠商';
    modal.style.display = 'flex';
    search.value = '';
    renderRelList(relType, '');
    setTimeout(function() { search.focus(); }, 100);
}

function closeRelModal() {
    document.getElementById('relModal').style.display = 'none';
    _relPickerIdx = null;
}

function renderRelList(type, keyword) {
    var list = type === 'customer' ? customers : vendors;
    var tbody = document.getElementById('relModalBody');
    var kw = (keyword || '').toLowerCase().trim();
    var html = '';
    var count = 0;
    for (var i = 0; i < list.length; i++) {
        var item = list[i];
        var code = item.tax_id_number || item.tax_id || '';
        var idStr = String(item.id);
        if (kw && item.name.toLowerCase().indexOf(kw) === -1 && code.toLowerCase().indexOf(kw) === -1 && idStr.indexOf(kw) === -1) continue;
        html += '<tr class="acct-row" style="cursor:pointer" onclick="pickRel(' + item.id + ',\'' + item.name.replace(/'/g, "\\'") + '\')">' +
            '<td style="font-family:monospace">' + (item.id) + '</td>' +
            '<td>' + item.name + '</td>' +
            '<td style="color:#aaa">' + code + '</td></tr>';
        count++;
    }
    if (count === 0) html = '<tr><td colspan="3" style="text-align:center;padding:20px;color:#999">無符合資料</td></tr>';
    tbody.innerHTML = html;
    document.getElementById('relModalCount').textContent = '共 ' + count + ' 筆';
}

function pickRel(id, name) {
    if (_relPickerIdx === null) return;
    document.getElementById('rel_id_' + _relPickerIdx).value = id;
    document.getElementById('rel_name_' + _relPickerIdx).value = name;
    document.getElementById('rel_id_display_' + _relPickerIdx).innerHTML = id;
    var short = name.length > 6 ? name.substring(0, 6) : name;
    document.getElementById('rel_name_display_' + _relPickerIdx).innerHTML = short;
    document.getElementById('rel_name_display_' + _relPickerIdx).title = name;
    closeRelModal();
}

function onRelSearch() {
    var sel = document.querySelector('select[data-idx="' + _relPickerIdx + '"]');
    var type = sel ? sel.value : 'customer';
    renderRelList(type, document.getElementById('relModalSearch').value);
}

// 處理中文輸入法（會計科目 + 往來對象搜尋）
(function() {
    var acctComposing = false, relComposing = false;

    function initSearchIME() {
        var acctSearch = document.getElementById('acctModalSearch');
        if (acctSearch) {
            acctSearch.addEventListener('compositionstart', function() { acctComposing = true; });
            acctSearch.addEventListener('compositionend', function() { acctComposing = false; onAcctSearch(); });
            acctSearch.addEventListener('input', function() { if (!acctComposing) onAcctSearch(); });
        }

        var relSearch = document.getElementById('relModalSearch');
        if (relSearch) {
            relSearch.addEventListener('compositionstart', function() { relComposing = true; });
            relSearch.addEventListener('compositionend', function() { relComposing = false; onRelSearch(); });
            relSearch.addEventListener('input', function() { if (!relComposing) onRelSearch(); });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSearchIME);
    } else {
        initSearchIME();
    }
})();

function insertLineBefore(refIdx) {
    var refRow = document.getElementById('line_' + refIdx);
    if (!refRow) return;
    addLine();
    var newRow = document.getElementById('line_' + (lineIndex - 1));
    if (newRow && refRow.parentNode) {
        refRow.parentNode.insertBefore(newRow, refRow);
        renumberLines();
    }
}

function removeLine(idx) {
    var row = document.getElementById('line_' + idx);
    if (row) row.remove();
    renumberLines();
    calcTotals();
}

// 同列借貸互斥
function onDebitInput(el) {
    var val = _jfNum(el);
    var tr = el.closest('tr');
    if (val > 0) {
        var creditInput = tr.querySelector('.credit-input');
        if (creditInput) creditInput.value = '';
    }
    // 借貸切換時清除標記，讓立沖重新判斷
    var idx = tr.id.replace('line_', '');
    delete offsetAutoSet[idx];
    calcTotals();
    autoSetOffsetFlag(el, 'debit');

    // 沖帳時：本次沖帳自動同步借方金額
    syncOffsetAmount(idx);
}
function onCreditInput(el) {
    var val = _jfNum(el);
    var tr = el.closest('tr');
    if (val > 0) {
        var debitInput = tr.querySelector('.debit-input');
        if (debitInput) debitInput.value = '';
    }
    // 借貸切換時清除標記，讓立沖重新判斷
    var idx = tr.id.replace('line_', '');
    delete offsetAutoSet[idx];
    calcTotals();
    autoSetOffsetFlag(el, 'credit');

    // 沖帳時：本次沖帳自動同步貸方金額
    syncOffsetAmount(idx);
}

// 自動判斷立帳/沖帳
// 規則：資產類(1) 借方=立帳 貸方=沖帳
//       負債類(2) 貸方=立帳 借方=沖帳
//       權益類(3) 貸方=立帳 借方=沖帳
var offsetAutoSet = {}; // 記錄已自動設定過的行，避免重複彈窗

function autoSetOffsetFlag(el, direction) {
    var tr = el.closest('tr');
    if (!tr) return;
    var idx = tr.id.replace('line_', '');

    var accountIdInput = document.querySelector('input[name="lines[' + idx + '][account_id]"]');
    if (!accountIdInput || !accountIdInput.value) return;

    var acct = null;
    for (var i = 0; i < accounts.length; i++) {
        if (accounts[i].id == accountIdInput.value) { acct = accounts[i]; break; }
    }
    if (!acct || acct.offset_type !== '立沖科目') return;

    var offsetFlagSel = document.getElementById('offset_flag_' + idx);
    if (!offsetFlagSel) return;

    var val = _jfNum(el);
    if (val <= 0) return;

    var typeNum = parseInt(acct.type_num) || 0;
    var flag = 0;

    if (typeNum === 1) {
        // 資產類：借方=立帳，貸方=沖帳
        flag = (direction === 'debit') ? 1 : 2;
    } else if (typeNum === 2 || typeNum === 3) {
        // 負債類/權益類：貸方=立帳，借方=沖帳
        flag = (direction === 'credit') ? 1 : 2;
    }

    if (flag > 0) {
        offsetAutoSet[idx] = true;
        offsetFlagSel.value = String(flag);
        // 立沖科目的立沖旗標由借貸方自動決定，鎖定不可修改
        offsetFlagSel.disabled = true;

        if (flag === 2) {
            // 沖帳：自動彈出選擇立帳單
            onOffsetFlagChange(parseInt(idx));
        } else {
            // 立帳：沖額清空
            var amtInput = document.getElementById('offset_amt_' + idx);
            if (amtInput) { amtInput.value = ''; amtInput.disabled = true; }
            var unoffsetSpan = document.getElementById('unoffset_amt_' + idx);
            if (unoffsetSpan) unoffsetSpan.textContent = '--';
        }
    }
}

// 同步沖帳金額：本次沖帳 = 該行的借方或貸方金額（取有值的那邊）
// 如果金額超過未沖額，標記錯誤
function syncOffsetAmount(idx) {
    var offsetFlagSel = document.getElementById('offset_flag_' + idx);
    if (!offsetFlagSel || offsetFlagSel.value !== '2') return; // 只有沖帳才同步

    var amtInput = document.getElementById('offset_amt_' + idx);
    if (!amtInput) return;

    var maxStr = amtInput.getAttribute('data-max');
    if (!maxStr) return; // 尚未選擇立帳單

    var max = parseInt(maxStr) || 0;
    var tr = document.getElementById('line_' + idx);
    if (!tr) return;

    var debitInput = tr.querySelector('.debit-input');
    var creditInput = tr.querySelector('.credit-input');
    var entryAmt = Math.max(_jfNum(debitInput), _jfNum(creditInput));

    if (entryAmt > max) {
        amtInput.value = '';
        amtInput.style.border = '2px solid #e53e3e';
        // 標記超額
        amtInput.setAttribute('data-over', '1');
    } else {
        amtInput.value = entryAmt;
        amtInput.style.border = '';
        amtInput.removeAttribute('data-over');
    }
}

function calcTotals() {
    var debits = document.querySelectorAll('.debit-input');
    var credits = document.querySelectorAll('.credit-input');
    var totalD = 0, totalC = 0;
    for (var i = 0; i < debits.length; i++) totalD += _jfNum(debits[i]);
    for (var i = 0; i < credits.length; i++) totalC += _jfNum(credits[i]);

    document.getElementById('totalDebit').textContent = totalD.toLocaleString();
    document.getElementById('totalCredit').textContent = totalC.toLocaleString();

    var diff = Math.abs(totalD - totalC);
    var status = document.getElementById('balanceStatus');
    if (diff === 0 && totalD > 0) {
        status.innerHTML = '<span style="color:green">&#10003; 借貸平衡</span>';
        document.getElementById('submitBtn').disabled = false;
    } else if (totalD === 0 && totalC === 0) {
        status.innerHTML = '';
        document.getElementById('submitBtn').disabled = true;
    } else {
        status.innerHTML = '<span style="color:red">&#10007; 差額: ' + diff.toLocaleString() + '</span>';
        document.getElementById('submitBtn').disabled = true;
    }
}

// ===== 拖曳排序 =====
function renumberLines() {
    var rows = document.querySelectorAll('#linesBody tr');
    for (var i = 0; i < rows.length; i++) {
        var numCell = rows[i].querySelector('.line-num');
        if (numCell) numCell.textContent = i + 1;
    }
}

(function() {
    var dragRow = null;
    var tbody = null;

    function init() {
        tbody = document.getElementById('linesBody');
        if (!tbody) return;

        tbody.addEventListener('dragstart', function(e) {
            var tr = e.target.closest('tr');
            if (!tr || !tbody.contains(tr)) return;
            dragRow = tr;
            tr.style.opacity = '0.4';
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');
        });

        tbody.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var tr = e.target.closest('tr');
            if (!tr || !tbody.contains(tr) || tr === dragRow) return;
            var rect = tr.getBoundingClientRect();
            var mid = rect.top + rect.height / 2;
            if (e.clientY < mid) {
                tbody.insertBefore(dragRow, tr);
            } else {
                tbody.insertBefore(dragRow, tr.nextSibling);
            }
        });

        tbody.addEventListener('dragend', function(e) {
            if (dragRow) {
                dragRow.style.opacity = '';
                dragRow = null;
            }
            renumberLines();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

// Initialize with existing lines or empty rows
<?php if ($src && !empty($src['lines'])): ?>
<?php foreach ($src['lines'] as $line): ?>
addLine(<?= json_encode($line, JSON_UNESCAPED_UNICODE) ?>);
<?php endforeach; ?>
<?php else: ?>
addLine();
addLine();
<?php endif; ?>

// nothing here

document.getElementById('journalForm').addEventListener('submit', function(ev) {
    // 驗證成本中心必填
    var ccSelects = document.querySelectorAll('select[name*="cost_center_id"]');
    for (var i = 0; i < ccSelects.length; i++) {
        if (!ccSelects[i].value || ccSelects[i].value === '') {
            var row = ccSelects[i].closest('tr');
            if (row && row.style.display !== 'none') {
                ev.preventDefault();
                alert('每行分錄的成本中心為必填');
                ccSelects[i].focus();
                return false;
            }
        }
    }

    // Re-check balance
    var totalD = 0, totalC = 0;
    var debits = document.querySelectorAll('.debit-input');
    var credits = document.querySelectorAll('.credit-input');
    for (var i = 0; i < debits.length; i++) totalD += _jfNum(debits[i]);
    for (var i = 0; i < credits.length; i++) totalC += _jfNum(credits[i]);
    if (Math.abs(totalD - totalC) > 0.01) {
        ev.preventDefault();
        alert('借方合計與貸方合計不相等，請修正後再儲存');
        return false;
    }
    if (totalD <= 0) {
        ev.preventDefault();
        alert('請至少輸入一筆金額');
        return false;
    }

    // 驗證沖帳無餘額錯誤（標記 data-offset-error 的行不可儲存）
    var offsetFlagSels = document.querySelectorAll('.offset-flag-sel');
    for (var i = 0; i < offsetFlagSels.length; i++) {
        if (offsetFlagSels[i].getAttribute('data-offset-error') === '1') {
            var tr = offsetFlagSels[i].closest('tr');
            var lineNum = Array.prototype.indexOf.call(document.querySelectorAll('#linesBody tr'), tr) + 1;
            ev.preventDefault();
            alert('第 ' + lineNum + ' 行為立沖科目但無可沖銷的立帳記錄，請移除此行或修正往來對象');
            return false;
        }
    }

    // 驗證沖帳金額不可超過未沖額
    var offsetAmtInputs = document.querySelectorAll('input[id^="offset_amt_"]');
    for (var i = 0; i < offsetAmtInputs.length; i++) {
        if (offsetAmtInputs[i].getAttribute('data-over') === '1') {
            var tr = offsetAmtInputs[i].closest('tr');
            var lineNum = Array.prototype.indexOf.call(document.querySelectorAll('#linesBody tr'), tr) + 1;
            var max = parseInt(offsetAmtInputs[i].getAttribute('data-max')) || 0;
            ev.preventDefault();
            alert('第 ' + lineNum + ' 行的金額超過未沖額 ' + max.toLocaleString() + '，請調整金額');
            return false;
        }
    }

    // 驗證立沖科目的往來類型與往來編號必填
    var rows = document.querySelectorAll('#linesBody tr');
    for (var i = 0; i < rows.length; i++) {
        if (rows[i].style.display === 'none') continue;
        var offsetFlagSel = rows[i].querySelector('.offset-flag-sel');
        if (offsetFlagSel && (offsetFlagSel.value === '1' || offsetFlagSel.value === '2')) {
            // 這是立沖科目，往來類型和往來編號必填
            var relTypeSel = rows[i].querySelector('.rel-type-sel');
            var relIdInput = rows[i].querySelector('input[name*="[relation_id]"]');
            var lineNum = i + 1;

            if (!relTypeSel || !relTypeSel.value) {
                ev.preventDefault();
                alert('第 ' + lineNum + ' 行為立沖科目，往來類型為必填');
                if (relTypeSel) relTypeSel.focus();
                return false;
            }
            if (relTypeSel.value !== 'other' && (!relIdInput || !relIdInput.value)) {
                ev.preventDefault();
                alert('第 ' + lineNum + ' 行為立沖科目，往來編號為必填');
                return false;
            }
            if (relTypeSel.value === 'other') {
                if (!relIdInput || !relIdInput.value) {
                    ev.preventDefault();
                    alert('第 ' + lineNum + ' 行往來類型為「其他」，往來編號為必填');
                    return false;
                }
                var relNameInput = rows[i].querySelector('input[name*="[relation_name]"]');
                if (!relNameInput || !relNameInput.value.trim()) {
                    ev.preventDefault();
                    alert('第 ' + lineNum + ' 行往來類型為「其他」，往來對象為必填');
                    return false;
                }
            }

            // 沖帳行必須已選擇立帳單
            if (offsetFlagSel.value === '2') {
                var ledgerIdInput = rows[i].querySelector('input[name*="[offset_ledger_id]"]');
                if (!ledgerIdInput || !ledgerIdInput.value) {
                    ev.preventDefault();
                    alert('第 ' + lineNum + ' 行為沖帳，但尚未選擇要沖銷的立帳單');
                    return false;
                }
            }
        }
    }

    // 送出前解除所有 disabled 和 readOnly，確保欄位能送出
    var allDisabled = this.querySelectorAll('[disabled]');
    for (var j = 0; j < allDisabled.length; j++) {
        allDisabled[j].disabled = false;
    }
    var allReadonly = this.querySelectorAll('[readonly]');
    for (var j = 0; j < allReadonly.length; j++) {
        allReadonly[j].readOnly = false;
    }
});

// ===== 立沖選擇邏輯 =====
var currentOffsetIdx = null;

function onOffsetFlagChange(idx) {
    var sel = document.getElementById('offset_flag_' + idx);
    var amtInput = document.getElementById('offset_amt_' + idx);
    var unoffsetSpan = document.getElementById('unoffset_amt_' + idx);

    if (sel.value === '2') {
        // 沖帳：彈出選擇立帳單
        currentOffsetIdx = idx;
        var relType = document.getElementById('rel_type_' + idx) || document.querySelector('select[data-idx="' + idx + '"]');
        var relId = document.getElementById('rel_id_' + idx);
        var accountId = document.querySelector('input[name="lines[' + idx + '][account_id]"]');
        var costCenterId = document.querySelector('select[name="lines[' + idx + '][cost_center_id]"]');

        // 沖帳前驗證：往來類型與往來編號必填
        if (!relType || !relType.value) {
            alert('請先選擇往來類型');
            sel.value = '0';
            return;
        }
        if (!relId || !relId.value) {
            alert('請先填寫往來編號');
            sel.value = '0';
            return;
        }

        var relName = document.getElementById('rel_name_' + idx);

        var params = '?action=ajax_open_ledgers';
        params += '&relation_type=' + encodeURIComponent(relType.value);
        params += '&relation_id=' + encodeURIComponent(relId.value);
        if (relName && relName.value) params += '&relation_name=' + encodeURIComponent(relName.value);
        if (accountId && accountId.value) params += '&account_id=' + accountId.value;
        if (costCenterId && costCenterId.value) params += '&cost_center_id=' + costCenterId.value;

        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/accounting.php' + params);
        xhr.onload = function() {
            var data = JSON.parse(xhr.responseText);
            showOffsetLedgerModal(data, idx);
        };
        xhr.send();
    } else if (sel.value === '1') {
        // 立帳：沖額清空，未沖額 = 金額
        amtInput.value = '';
        amtInput.disabled = true;
        if (unoffsetSpan) unoffsetSpan.textContent = '--';
        // 清除關聯
        var hiddenLedger = document.querySelector('input[name="lines[' + idx + '][offset_ledger_id]"]');
        if (hiddenLedger) hiddenLedger.value = '';
    } else {
        amtInput.value = '';
        amtInput.disabled = true;
        if (unoffsetSpan) unoffsetSpan.textContent = '--';
    }
}

// 暫存 modal 的 ledger 資料
var _modalLedgers = [];

function showOffsetLedgerModal(ledgers, idx) {
    if (ledgers.length === 0) {
        alert('找不到可沖銷的立帳記錄（已無金額可沖）。請確認往來對象和科目，或移除此行。');
        // 不可自動改為立帳，回歸無立沖狀態並標記錯誤
        var flagSel = document.getElementById('offset_flag_' + idx);
        flagSel.value = '0';
        flagSel.style.border = '2px solid #e53e3e';
        flagSel.setAttribute('data-offset-error', '1');
        return;
    }

    _modalLedgers = ledgers;

    // 取得該行金額
    var tr = document.getElementById('line_' + idx);
    var dI = tr ? tr.querySelector('.debit-input') : null;
    var cI = tr ? tr.querySelector('.credit-input') : null;
    var entryAmt = Math.max(_jfNum(dI), _jfNum(cI));

    var html = '<div style="padding:16px">';
    html += '<h3 id="offsetModalHeader" style="margin:0 0 12px;cursor:move;user-select:none">選擇要沖銷的立帳單 <span style="font-size:.85rem;color:#666;font-weight:normal">（可複選，拖曳標題移動）</span></h3>';
    html += '<table style="width:100%;border-collapse:collapse;font-size:.9rem">';
    html += '<thead><tr style="background:#f0f0f0">';
    html += '<th style="padding:8px;width:36px"><input type="checkbox" id="offsetChkAll" onchange="toggleAllOffsetChk(this)"></th>';
    html += '<th style="padding:8px;text-align:left">傳票號碼</th><th style="padding:8px;text-align:left">日期</th><th style="padding:8px;text-align:left">科目</th><th style="padding:8px;text-align:left">部門</th><th style="padding:8px;text-align:right">原始金額</th><th style="padding:8px;text-align:right">未沖額</th>';
    html += '</tr></thead><tbody>';

    for (var i = 0; i < ledgers.length; i++) {
        var l = ledgers[i];
        html += '<tr style="border-bottom:1px solid #eee">';
        html += '<td style="padding:8px;text-align:center"><input type="checkbox" class="offset-chk" data-idx="' + i + '" onchange="updateOffsetSelection()"></td>';
        html += '<td style="padding:8px">' + (l.voucher_number || '-') + '</td>';
        html += '<td style="padding:8px">' + (l.voucher_date || '-') + '</td>';
        html += '<td style="padding:8px">' + (l.account_code || '') + ' ' + (l.account_name || '') + '</td>';
        html += '<td style="padding:8px">' + (l.cost_center_name || '-') + '</td>';
        html += '<td style="padding:8px;text-align:right">' + parseInt(l.original_amount).toLocaleString() + '</td>';
        html += '<td style="padding:8px;text-align:right;font-weight:bold;color:#e53e3e">' + parseInt(l.remaining_amount).toLocaleString() + '</td>';
        html += '</tr>';
    }
    html += '</tbody></table>';

    // 底部：已選統計 + 確認按鈕
    html += '<div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-top:1px solid #eee">';
    html += '<div id="offsetSelSummary" style="font-size:.9rem;color:#666">已選 <strong>0</strong> 筆，合計 <strong>0</strong> 元' + (entryAmt > 0 ? '（本次金額：' + entryAmt.toLocaleString() + '）' : '') + '</div>';
    html += '<div><button type="button" class="btn btn-primary" id="offsetConfirmBtn" onclick="confirmMultiOffset()" disabled>確認選擇</button></div>';
    html += '</div>';
    html += '</div>';

    var modal = document.getElementById('offsetLedgerModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'offsetLedgerModal';
        modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:10000;background:rgba(0,0,0,.5);display:flex;justify-content:center;align-items:center';
        document.body.appendChild(modal);
    }
    modal.innerHTML = '<div id="offsetModalPanel" style="background:#fff;border-radius:8px;max-width:800px;width:90%;max-height:80vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.25)">' + html + '</div>';
    modal.style.display = 'flex';

    // 拖曳移動
    var _omDrag = false, _omSX, _omSY, _omOX, _omOY;
    var omHeader = document.getElementById('offsetModalHeader');
    var omPanel = document.getElementById('offsetModalPanel');
    if (omHeader && omPanel) {
        omHeader.addEventListener('mousedown', function(e) {
            if (e.target.tagName === 'INPUT') return;
            _omDrag = true;
            var r = omPanel.getBoundingClientRect();
            _omSX = e.clientX; _omSY = e.clientY; _omOX = r.left; _omOY = r.top;
            omPanel.style.position = 'fixed'; omPanel.style.left = _omOX + 'px'; omPanel.style.top = _omOY + 'px'; omPanel.style.margin = '0';
            document.addEventListener('mousemove', omMove);
            document.addEventListener('mouseup', omEnd);
            e.preventDefault();
        });
        omHeader.addEventListener('touchstart', function(e) {
            if (e.target.tagName === 'INPUT') return;
            var t = e.touches[0]; _omDrag = true;
            var r = omPanel.getBoundingClientRect();
            _omSX = t.clientX; _omSY = t.clientY; _omOX = r.left; _omOY = r.top;
            omPanel.style.position = 'fixed'; omPanel.style.left = _omOX + 'px'; omPanel.style.top = _omOY + 'px'; omPanel.style.margin = '0';
            document.addEventListener('touchmove', omMoveT, {passive: false});
            document.addEventListener('touchend', omEnd);
            e.preventDefault();
        }, {passive: false});
    }
    function omMove(e) { if (!_omDrag) return; omPanel.style.left = (_omOX + e.clientX - _omSX) + 'px'; omPanel.style.top = (_omOY + e.clientY - _omSY) + 'px'; }
    function omMoveT(e) { if (!_omDrag) return; var t = e.touches[0]; omPanel.style.left = (_omOX + t.clientX - _omSX) + 'px'; omPanel.style.top = (_omOY + t.clientY - _omSY) + 'px'; e.preventDefault(); }
    function omEnd() { _omDrag = false; document.removeEventListener('mousemove', omMove); document.removeEventListener('mouseup', omEnd); document.removeEventListener('touchmove', omMoveT); document.removeEventListener('touchend', omEnd); }
}

// 全選/取消全選
function toggleAllOffsetChk(master) {
    var chks = document.querySelectorAll('.offset-chk');
    for (var i = 0; i < chks.length; i++) {
        chks[i].checked = master.checked;
    }
    updateOffsetSelection();
}

// 更新已選統計
function updateOffsetSelection() {
    var chks = document.querySelectorAll('.offset-chk');
    var count = 0, total = 0;
    for (var i = 0; i < chks.length; i++) {
        if (chks[i].checked) {
            count++;
            var li = parseInt(chks[i].getAttribute('data-idx'));
            total += parseInt(_modalLedgers[li].remaining_amount) || 0;
        }
    }

    // 取得原始行金額
    var idx = currentOffsetIdx;
    var tr = document.getElementById('line_' + idx);
    var dI = tr ? tr.querySelector('.debit-input') : null;
    var cI = tr ? tr.querySelector('.credit-input') : null;
    var entryAmt = Math.max(_jfNum(dI), _jfNum(cI));

    var summary = document.getElementById('offsetSelSummary');
    var warn = '';
    if (count > 0 && total < entryAmt) {
        warn = ' <span style="color:#e53e3e">（不足，差額 ' + (entryAmt - total).toLocaleString() + '）</span>';
    }
    if (summary) {
        summary.innerHTML = '已選 <strong>' + count + '</strong> 筆，合計 <strong>' + total.toLocaleString() + '</strong> 元' + (entryAmt > 0 ? '（本次金額：' + entryAmt.toLocaleString() + '）' : '') + warn;
    }

    var btn = document.getElementById('offsetConfirmBtn');
    if (btn) btn.disabled = (count === 0);

    // 更新全選 checkbox
    var allChk = document.getElementById('offsetChkAll');
    if (allChk) allChk.checked = (count === chks.length && count > 0);
}

// FIFO 金額分配
function distributeOffsetAmounts(entryAmount, ledgers) {
    var result = [];
    var remaining = entryAmount;
    for (var i = 0; i < ledgers.length; i++) {
        if (remaining <= 0) break;
        var alloc = Math.min(parseInt(ledgers[i].remaining_amount) || 0, remaining);
        if (alloc > 0) {
            result.push({
                ledger_id: ledgers[i].id,
                remaining_amount: parseInt(ledgers[i].remaining_amount),
                voucher_number: ledgers[i].voucher_number || '',
                allocated: alloc
            });
            remaining -= alloc;
        }
    }
    return result;
}

// 確認複選沖帳
function confirmMultiOffset() {
    var idx = currentOffsetIdx;
    var tr = document.getElementById('line_' + idx);
    if (!tr) return;

    // 收集勾選的 ledgers（依 modal 順序，已按日期排序）
    var chks = document.querySelectorAll('.offset-chk');
    var selected = [];
    for (var i = 0; i < chks.length; i++) {
        if (chks[i].checked) {
            var li = parseInt(chks[i].getAttribute('data-idx'));
            selected.push(_modalLedgers[li]);
        }
    }
    if (selected.length === 0) return;

    // 清除沖帳錯誤標記
    var flagSel = document.getElementById('offset_flag_' + idx);
    if (flagSel) {
        flagSel.removeAttribute('data-offset-error');
        flagSel.style.border = '';
    }

    // 取得原始行資訊
    var dI = tr.querySelector('.debit-input');
    var cI = tr.querySelector('.credit-input');
    var entryAmt = Math.max(_jfNum(dI), _jfNum(cI));
    var isDebit = (_jfNum(dI) > 0);

    var accountIdInput = tr.querySelector('input[name*="[account_id]"]');
    var ccSelect = tr.querySelector('select[name*="[cost_center_id]"]');
    var relTypeSel = tr.querySelector('.rel-type-sel');
    var relIdInput = document.getElementById('rel_id_' + idx);
    var relNameInput = document.getElementById('rel_name_' + idx);

    var accountId = accountIdInput ? accountIdInput.value : '';
    var ccId = ccSelect ? ccSelect.value : '';
    var relType = relTypeSel ? relTypeSel.value : '';
    var relId = relIdInput ? relIdInput.value : '';
    var relName = relNameInput ? relNameInput.value : '';

    // 分配金額
    var dist = distributeOffsetAmounts(entryAmt, selected);
    if (dist.length === 0) return;

    // 先刪除之前自動產生的沖帳行
    var autoRows = document.querySelectorAll('tr[data-auto-offset-source="' + idx + '"]');
    for (var i = 0; i < autoRows.length; i++) {
        autoRows[i].parentNode.removeChild(autoRows[i]);
    }

    // 第一筆：套用到原始行
    var first = dist[0];
    var amtInput = document.getElementById('offset_amt_' + idx);
    var unoffsetSpan = document.getElementById('unoffset_amt_' + idx);

    // 設定 offset_ledger_id
    var hiddenInput = tr.querySelector('input[name*="[offset_ledger_id]"]');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'lines[' + idx + '][offset_ledger_id]';
        amtInput.parentNode.appendChild(hiddenInput);
    }
    hiddenInput.value = first.ledger_id;

    // 更新原始行金額為第一筆分配額
    if (isDebit) {
        dI.value = first.allocated;
    } else {
        cI.value = first.allocated;
    }
    amtInput.setAttribute('data-max', first.remaining_amount);
    amtInput.value = first.allocated;
    amtInput.disabled = true;
    amtInput.removeAttribute('data-over');
    if (unoffsetSpan) unoffsetSpan.textContent = first.remaining_amount.toLocaleString();

    // 第一筆也設定摘要：沖 原傳票號 往來對象
    var descInput = tr.querySelector('input[name="lines[' + idx + '][description]"]');
    if (descInput) {
        var relNameVal = relName || '';
        descInput.value = '沖 ' + first.voucher_number + (relNameVal ? ' ' + relNameVal : '');
    }

    // 標記已設定
    offsetAutoSet[idx] = true;

    // 第 2~N 筆：自動產生新行
    for (var i = 1; i < dist.length; i++) {
        var d = dist[i];
        var lineData = {
            account_id: accountId,
            cost_center_id: ccId,
            relation_type: relType,
            relation_id: relId,
            relation_name: relName,
            debit_amount: isDebit ? d.allocated : 0,
            credit_amount: isDebit ? 0 : d.allocated,
            offset_flag: 2,
            offset_amount: d.allocated,
            description: '沖 ' + d.voucher_number + (relName ? ' ' + relName : '')
        };
        addLine(lineData);

        // 取得剛建立的行 index
        var newIdx = lineIndex - 1;
        var newTr = document.getElementById('line_' + newIdx);
        if (newTr) {
            // 標記為自動產生行
            newTr.setAttribute('data-auto-offset-source', String(idx));
            newTr.style.backgroundColor = '#fffde7';

            // 設定 offset_ledger_id
            var newAmtInput = document.getElementById('offset_amt_' + newIdx);
            var newHidden = document.createElement('input');
            newHidden.type = 'hidden';
            newHidden.name = 'lines[' + newIdx + '][offset_ledger_id]';
            newHidden.value = d.ledger_id;
            if (newAmtInput) newAmtInput.parentNode.appendChild(newHidden);

            // 設定未沖額和鎖定
            if (newAmtInput) {
                newAmtInput.setAttribute('data-max', d.remaining_amount);
                newAmtInput.value = d.allocated;
                newAmtInput.disabled = true;
            }
            var newUnoffset = document.getElementById('unoffset_amt_' + newIdx);
            if (newUnoffset) newUnoffset.textContent = d.remaining_amount.toLocaleString();

            // 鎖定立沖
            var newFlagSel = document.getElementById('offset_flag_' + newIdx);
            if (newFlagSel) {
                newFlagSel.value = '2';
                newFlagSel.disabled = true;
            }
            offsetAutoSet[newIdx] = true;

            // 鎖定金額輸入
            var newDebit = newTr.querySelector('.debit-input');
            var newCredit = newTr.querySelector('.credit-input');
            if (newDebit) newDebit.readOnly = true;
            if (newCredit) newCredit.readOnly = true;
        }
    }

    calcTotals();
    closeOffsetModal();
}

function closeOffsetModal() {
    var modal = document.getElementById('offsetLedgerModal');
    if (modal) modal.style.display = 'none';
    // 立沖由借貸方自動決定，取消時不改變立沖旗標
}
</script>

<!-- 會計科目選擇彈窗 -->
<div id="acctModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(0,0,0,.5);justify-content:center;align-items:center">
    <div style="background:#fff;border-radius:8px;width:90%;max-width:600px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.25)">
        <div id="acctModalHeader" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #e0e0e0;cursor:move;user-select:none">
            <h3 style="margin:0;font-size:1.1rem">選擇會計科目</h3>
            <button type="button" onclick="closeAcctModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#666;line-height:1">&times;</button>
        </div>
        <div style="padding:12px 20px;border-bottom:1px solid #e0e0e0;display:flex;align-items:center;gap:12px">
            <input type="text" id="acctModalSearch" class="form-control" placeholder="輸入科目編號或名稱搜尋..." style="flex:1">
            <span id="acctModalCount" style="color:#999;font-size:.85rem;white-space:nowrap"></span>
        </div>
        <div style="overflow-y:auto;flex:1">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:#f8f9fa;position:sticky;top:0">
                        <th style="padding:8px 12px;text-align:left;width:120px;border-bottom:2px solid #e0e0e0">科目編號</th>
                        <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #e0e0e0">科目名稱</th>
                        <th style="padding:8px 12px;text-align:left;width:100px;border-bottom:2px solid #e0e0e0">分類</th>
                    </tr>
                </thead>
                <tbody id="acctModalBody"></tbody>
            </table>
        </div>
    </div>
</div>
<!-- 往來對象選擇彈窗 -->
<div id="relModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(0,0,0,.5);justify-content:center;align-items:center">
    <div id="relModalPanel" style="background:#fff;border-radius:8px;width:90%;max-width:550px;max-height:70vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.25)">
        <div id="relModalHeader" style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid #e0e0e0;cursor:move;user-select:none">
            <h3 id="relModalTitle" style="margin:0;font-size:1.1rem">選擇</h3>
            <button type="button" onclick="closeRelModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#666;line-height:1">&times;</button>
        </div>
        <div style="padding:10px 20px;border-bottom:1px solid #e0e0e0;display:flex;gap:10px;align-items:center">
            <input type="text" id="relModalSearch" class="form-control" placeholder="輸入編號或名稱搜尋..." style="flex:1">
            <span id="relModalCount" style="color:#999;font-size:.85rem;white-space:nowrap"></span>
        </div>
        <div style="overflow-y:auto;flex:1">
            <table style="width:100%;border-collapse:collapse;font-size:.9rem">
                <thead><tr style="background:#f8f9fa;position:sticky;top:0">
                    <th style="padding:8px 12px;text-align:left;width:60px;border-bottom:2px solid #e0e0e0">編號</th>
                    <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #e0e0e0">名稱</th>
                    <th style="padding:8px 12px;text-align:left;width:110px;border-bottom:2px solid #e0e0e0">統編</th>
                </tr></thead>
                <tbody id="relModalBody"></tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* 拖曳移動 */
#acctModalHeader:active { cursor:grabbing; }
#relModalHeader:active { cursor:grabbing; }
.acct-display { padding:6px 10px; border:1px solid #ddd; border-radius:4px; background:#fff; cursor:pointer; min-height:34px; display:flex; align-items:center; font-size:.9rem; }
.acct-display:hover { border-color:#2196F3; background:#f8fbff; }
.acct-row:not([style*="italic"]):hover { background:#e3f2fd !important; }
.acct-row td { padding:8px 12px; border-bottom:1px solid #f0f0f0; }
#acctModal table { font-size:.9rem; }
</style>
