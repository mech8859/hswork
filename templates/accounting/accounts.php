<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>會計科目管理</h1>
    <?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="openAccountModal()">+ 新增科目</button>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:16px;padding:12px">
    <form method="get" action="/accounting.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="action" value="accounts">
        <div style="position:relative">
            <input type="text" name="keyword" id="acctSearch" value="<?= e($keyword) ?>" placeholder="搜尋科目編號/名稱" class="form-control" style="width:200px" autocomplete="off">
            <div id="acctSuggest" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-top:none;border-radius:0 0 6px 6px;max-height:240px;overflow-y:auto;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
        </div>
        <select name="type" id="filterType" class="form-control" style="width:150px" onchange="onTypeChange()">
            <option value="">全部總類</option>
            <?php
            $typeNumOptions = array(
                '1' => '1-資產類', '2' => '2-負債類', '3' => '3-權益類',
                '4' => '4-收入類', '5' => '5-成本類', '6' => '6-費用類',
                '7' => '7-營業外收支', '8' => '8-所得稅', '9' => '9-非營業',
            );
            foreach ($typeNumOptions as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $typeFilter === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <?php $catFilter = isset($_GET['cat']) ? $_GET['cat'] : ''; ?>
        <select name="cat" id="filterCat" class="form-control" style="width:180px">
            <option value="">全部類別</option>
        </select>
        <label style="display:flex;align-items:center;gap:4px">
            <input type="checkbox" name="show_inactive" value="1" <?= $showInactive ? 'checked' : '' ?>> 顯示停用
        </label>
        <button type="submit" class="btn btn-secondary">篩選</button>
    </form>
</div>

<!-- Accounts Table -->
<p style="padding:8px 0;margin:0;color:#666;font-size:.85rem">共 <?= count($allAccounts) ?> 筆科目</p>
<div style="overflow:auto;max-height:calc(100vh - 200px);border:1px solid var(--gray-200);border-radius:8px">
    <table class="table" style="width:100%;font-size:.8rem;white-space:nowrap">
        <thead>
            <tr style="position:sticky;top:0;z-index:10;background:#f5f5f5;box-shadow:0 1px 3px rgba(0,0,0,.1)">
                <th>科目編號</th>
                <th>科目名稱</th>
                <th>立沖屬性</th>
                <th>交易科目</th>
                <th>隸屬科目</th>
                <th>科目總類</th>
                <th>總類名稱</th>
                <th>科目類別</th>
                <th>類別名稱</th>
                <th>科目屬性</th>
                <th>專案核算</th>
                <th>餘額方向</th>
                <th>部門核算</th>
                <th>往來類型</th>
                <th>內部往來</th>
                <th>狀態</th>
                <?php if ($canManage): ?><th>操作</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($allAccounts)): ?>
            <tr><td colspan="17" style="text-align:center;padding:20px;color:#999">尚無科目資料</td></tr>
            <?php endif; ?>
            <?php foreach ($allAccounts as $acc): ?>
            <tr style="<?= !$acc['is_active'] ? 'opacity:0.5' : '' ?>">
                <td><strong><a href="javascript:void(0)" onclick='openAccountModal(<?= json_encode($acc, JSON_UNESCAPED_UNICODE) ?>)' style="color:var(--primary);text-decoration:none"><?= e($acc['code']) ?></a></strong></td>
                <td><?= e($acc['name']) ?></td>
                <td><?= e($acc['offset_type'] ?? '') ?></td>
                <td><?= e($acc['tx_type'] ?? '') ?></td>
                <td><?= e(!empty($acc['parent_code']) ? $acc['parent_code'] : '') ?></td>
                <td><?= e($acc['type_num'] ?? '') ?></td>
                <td><?= e($acc['type_name_full'] ?? '') ?></td>
                <td><?= e($acc['cat_code'] ?? '') ?></td>
                <td><?= e($acc['cat_name'] ?? '') ?></td>
                <td><?= e($acc['attr'] ?? '') ?></td>
                <td><?= e($acc['project_calc'] ?? '') ?></td>
                <td><?= $acc['normal_balance'] === 'debit' ? '借方' : '貸方' ?></td>
                <td><?= e($acc['dept_calc'] ?? '') ?></td>
                <td><?= e($acc['relate_type'] ?? '') ?></td>
                <td><?= e($acc['internal_flag'] ?? '') ?></td>
                <td><?= $acc['is_active'] ? '<span style="color:green">啟用</span>' : '<span style="color:red">停用</span>' ?></td>
                <?php if ($canManage): ?>
                <td style="white-space:nowrap">
                    <button class="btn btn-sm btn-secondary" onclick='openAccountModal(<?= json_encode($acc, JSON_UNESCAPED_UNICODE) ?>)'>編輯</button>
                    <?php if (!$acc['is_active']): ?>
                    <button class="btn btn-sm btn-danger" onclick="deleteAccount(<?= $acc['id'] ?>, '<?= e($acc['code']) ?>')">刪除</button>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// 即時搜尋建議
function deleteAccount(id, code) {
    if (!confirm('確定刪除科目 ' + code + '？此操作無法復原。')) return;
    var fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/accounting.php?action=account_delete');
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) { location.reload(); }
            else { alert(res.error || '刪除失敗'); }
        } catch(e) { alert('刪除失敗'); }
    };
    xhr.send(fd);
}

var _acctList = <?= json_encode(array_map(function($a) { return array('code' => $a['code'], 'name' => $a['name']); }, $allAccounts), JSON_UNESCAPED_UNICODE) ?>;
(function() {
    var input = document.getElementById('acctSearch');
    var box = document.getElementById('acctSuggest');
    var composing = false;

    input.addEventListener('compositionstart', function() { composing = true; });
    input.addEventListener('compositionend', function() { composing = false; doSuggest(); });
    input.addEventListener('input', function() { if (!composing) doSuggest(); });
    input.addEventListener('focus', function() { if (input.value.trim()) doSuggest(); });
    document.addEventListener('click', function(e) { if (!input.contains(e.target) && !box.contains(e.target)) box.style.display = 'none'; });

    function doSuggest() {
        var kw = input.value.trim().toLowerCase();
        if (!kw) { box.style.display = 'none'; return; }
        var html = '', count = 0;
        for (var i = 0; i < _acctList.length && count < 15; i++) {
            var a = _acctList[i];
            if (a.code.toLowerCase().indexOf(kw) !== -1 || a.name.toLowerCase().indexOf(kw) !== -1) {
                html += '<div class="sg-item" onmousedown="pickSuggest(\'' + a.code.replace(/'/g, "\\'") + ' ' + a.name.replace(/'/g, "\\'") + '\')" style="padding:6px 12px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #f0f0f0">' +
                    '<span style="font-family:monospace;color:#666">' + a.code + '</span> ' + a.name + '</div>';
                count++;
            }
        }
        if (count === 0) {
            box.style.display = 'none';
        } else {
            box.innerHTML = html;
            box.style.display = 'block';
        }
    }
})();
function pickSuggest(val) {
    var input = document.getElementById('acctSearch');
    // 取科目編號部分來搜尋
    input.value = val.split(' ')[0];
    document.getElementById('acctSuggest').style.display = 'none';
    input.closest('form').submit();
}

var _catCache = {};
var _initCatFilter = '<?= e($catFilter) ?>';
var _initTypeFilter = '<?= e($typeFilter) ?>';

function onTypeChange() {
    var typeVal = document.getElementById('filterType').value;
    loadCategories(typeVal);
}

function loadCategories(typeNum) {
    var sel = document.getElementById('filterCat');
    sel.innerHTML = '<option value="">全部類別</option>';
    if (!typeNum) return;

    if (_catCache[typeNum]) {
        fillCatOptions(_catCache[typeNum]);
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/accounting.php?action=ajax_categories&type_num=' + typeNum);
    xhr.onload = function() {
        try {
            var cats = JSON.parse(xhr.responseText);
            _catCache[typeNum] = cats;
            fillCatOptions(cats);
        } catch(e) {}
    };
    xhr.send();
}

function fillCatOptions(cats) {
    var sel = document.getElementById('filterCat');
    for (var i = 0; i < cats.length; i++) {
        var opt = document.createElement('option');
        opt.value = cats[i].cat_code;
        opt.textContent = cats[i].cat_code + '-' + cats[i].cat_name;
        if (cats[i].cat_code === _initCatFilter) opt.selected = true;
        sel.appendChild(opt);
    }
}

// 初始載入
if (_initTypeFilter) {
    loadCategories(_initTypeFilter);
}
</script>

<?php if ($canManage): ?>
<!-- Account Modal -->
<div id="accountModal" class="modal" style="display:none">
    <div class="modal-overlay" onclick="closeAccountModal()"></div>
    <div class="modal-content" id="accModalContent" style="max-width:600px;cursor:default">
        <div class="modal-header" id="accModalHeader" style="cursor:move">
            <h3 id="accountModalTitle">新增科目</h3>
            <button class="modal-close" onclick="closeAccountModal()">&times;</button>
        </div>
        <form method="post" action="/accounting.php?action=account_save">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="acc_id" value="">
            <div class="modal-body" style="display:grid;gap:10px;font-size:.9rem">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div><label>科目編號 <span style="color:red">*</span></label><input type="text" name="code" id="acc_code" class="form-control" required></div>
                    <div><label>科目名稱 <span style="color:red">*</span></label><input type="text" name="name" id="acc_name" class="form-control" required></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                    <div><label>立沖屬性</label>
                        <select name="offset_type" id="acc_offset_type" class="form-control">
                            <option value="非立沖">非立沖</option><option value="立沖科目">立沖科目</option>
                        </select>
                    </div>
                    <div><label>交易科目</label>
                        <select name="tx_type" id="acc_tx_type" class="form-control">
                            <option value="交易科目">交易科目</option><option value="統馭科目">統馭科目</option>
                        </select>
                    </div>
                    <div><label>隸屬科目</label><input type="text" name="parent_code" id="acc_parent_code" class="form-control" placeholder="如：1113000"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                    <div><label>科目總類</label>
                        <select name="type_num" id="acc_type_num" class="form-control" onchange="autoFillTypeName()">
                            <option value="1">1-資產類</option><option value="2">2-負債類</option><option value="3">3-權益類</option>
                            <option value="4">4-收入類</option><option value="5">5-成本類</option><option value="6">6-費用類</option>
                            <option value="7">7-營業外收支</option><option value="8">8-所得稅</option><option value="9">9-非營業</option>
                        </select>
                    </div>
                    <div><label>總類名稱</label><input type="text" name="type_name_full" id="acc_type_name_full" class="form-control"></div>
                    <div><label>科目類型</label>
                        <select name="account_type" id="acc_type" class="form-control" required>
                            <?php foreach ($accountTypeOptions as $k => $v): ?>
                            <option value="<?= e($k) ?>"><?= e($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php
                // 從資料庫取得不重複的科目類別
                $catRows = Database::getInstance()->query("SELECT DISTINCT cat_code, cat_name, attr FROM chart_of_accounts WHERE cat_code IS NOT NULL AND cat_code != '' ORDER BY cat_code")->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <script>
                var catMap = {
                    <?php foreach ($catRows as $cr): ?>
                    <?= json_encode($cr['cat_code']) ?>: {name: <?= json_encode($cr['cat_name'] ?: '') ?>, attr: <?= json_encode($cr['attr'] ?: '非收入費用') ?>},
                    <?php endforeach; ?>
                };
                function onCatCodeChange(sel) {
                    var v = sel.value;
                    if (catMap[v]) {
                        document.getElementById('acc_cat_name').value = catMap[v].name;
                        var attrSel = document.getElementById('acc_attr');
                        var attrVal = (catMap[v].attr || '').trim();
                        var found = false;
                        for (var i = 0; i < attrSel.options.length; i++) {
                            if (attrSel.options[i].value.trim() === attrVal) {
                                attrSel.selectedIndex = i;
                                found = true;
                                break;
                            }
                        }
                        if (!found && attrVal) {
                            // 新增缺少的選項
                            var opt = document.createElement('option');
                            opt.value = attrVal;
                            opt.textContent = attrVal;
                            attrSel.appendChild(opt);
                            attrSel.value = attrVal;
                        }
                    } else {
                        document.getElementById('acc_cat_name').value = '';
                    }
                }
                </script>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                    <div><label>科目類別</label>
                        <select name="cat_code" id="acc_cat_code" class="form-control" onchange="onCatCodeChange(this)">
                            <option value="">請選擇</option>
                            <?php foreach ($catRows as $cr): ?>
                            <option value="<?= e($cr['cat_code']) ?>"><?= e($cr['cat_code']) ?> - <?= e($cr['cat_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>類別名稱</label><input type="text" name="cat_name" id="acc_cat_name" class="form-control" placeholder="如：速動資產"></div>
                    <div><label>科目屬性</label>
                        <select name="attr" id="acc_attr" class="form-control">
                            <option value="非收入費用">非收入費用</option>
                            <option value="收入">收入</option><option value="營業收入">營業收入</option>
                            <option value="成本">成本</option><option value="營業成本">營業成本</option>
                            <option value="費用">費用</option><option value="營業費用">營業費用</option>
                            <option value="營業外收入">營業外收入</option><option value="營業外支出">營業外支出</option>
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                    <div><label>專案核算</label>
                        <select name="project_calc" id="acc_project_calc" class="form-control">
                            <option value="不核算專案">不核算專案</option><option value="核算專案">核算專案</option>
                        </select>
                    </div>
                    <div><label>餘額方向</label>
                        <select name="normal_balance" id="acc_normal" class="form-control">
                            <option value="debit">借方</option><option value="credit">貸方</option>
                        </select>
                    </div>
                    <div><label>部門核算</label>
                        <select name="dept_calc" id="acc_dept_calc" class="form-control">
                            <option value="核算部門">核算部門</option><option value="不核算">不核算</option>
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                    <div><label>往來類型</label>
                        <select name="relate_type" id="acc_relate_type" class="form-control">
                            <option value="不核算">不核算</option><option value="客戶">客戶</option><option value="廠商">廠商</option>
                        </select>
                    </div>
                    <div><label>內部往來</label>
                        <select name="internal_flag" id="acc_internal_flag" class="form-control">
                            <option value="否">否</option><option value="是">是</option>
                        </select>
                    </div>
                    <div><label>層級</label><input type="number" name="level" id="acc_level" class="form-control" value="1" min="1" max="4"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                    <div><label>排序</label><input type="number" name="sort_order" id="acc_sort" class="form-control" value="0"></div>
                    <div style="display:flex;align-items:end"><label style="display:flex;align-items:center;gap:4px"><input type="checkbox" name="is_detail" id="acc_detail" value="1" checked> 可記帳(明細科目)</label></div>
                    <div style="display:flex;align-items:end"><label style="display:flex;align-items:center;gap:4px"><input type="checkbox" name="is_active" id="acc_active" value="1" checked> 啟用</label></div>
                </div>
                <div><label>說明</label><textarea name="description" id="acc_desc" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer" style="display:flex;justify-content:space-between">
                <button type="button" class="btn btn-outline" id="btnCopyAccount" onclick="copyAsNewAccount()" style="display:none">複製為新科目</button>
                <div style="display:flex;gap:8px">
                    <button type="button" class="btn btn-secondary" onclick="closeAccountModal()">取消</button>
                    <button type="submit" class="btn btn-primary">儲存</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
var accFields = ['id','code','name','offset_type','tx_type','parent_code','type_num','type_name_full','type','cat_code','cat_name','attr','project_calc','normal','dept_calc','relate_type','internal_flag','level','sort','detail','desc'];
function openAccountModal(acc) {
    document.getElementById('accountModal').style.display = 'flex';
    if (acc) {
        document.getElementById('accountModalTitle').textContent = '編輯科目';
        document.getElementById('acc_id').value = acc.id || '';
        document.getElementById('acc_code').value = acc.code || acc.account_code || '';
        document.getElementById('acc_name').value = acc.name || acc.account_name || '';
        document.getElementById('acc_offset_type').value = acc.offset_type || '非立沖';
        document.getElementById('acc_tx_type').value = acc.tx_type || '交易科目';
        document.getElementById('acc_parent_code').value = acc.parent_code || '';
        document.getElementById('acc_type_num').value = acc.type_num || '1';
        document.getElementById('acc_type_name_full').value = acc.type_name_full || '';
        document.getElementById('acc_type').value = acc.account_type || 'asset';
        document.getElementById('acc_cat_code').value = acc.cat_code || '';
        document.getElementById('acc_cat_name').value = acc.cat_name || '';
        document.getElementById('acc_attr').value = acc.attr || '非收入費用';
        document.getElementById('acc_project_calc').value = acc.project_calc || '不核算專案';
        document.getElementById('acc_normal').value = acc.normal_balance || 'debit';
        document.getElementById('acc_dept_calc').value = acc.dept_calc || '核算部門';
        document.getElementById('acc_relate_type').value = acc.relate_type || '不核算';
        document.getElementById('acc_internal_flag').value = acc.internal_flag || '否';
        document.getElementById('acc_level').value = acc.level || 1;
        document.getElementById('acc_sort').value = acc.sort_order || 0;
        document.getElementById('acc_detail').checked = acc.is_detail == 1;
        document.getElementById('acc_active').checked = acc.is_active == 1;
        document.getElementById('acc_desc').value = acc.description || '';
        document.getElementById('btnCopyAccount').style.display = '';
    } else {
        document.getElementById('btnCopyAccount').style.display = 'none';
        document.getElementById('accountModalTitle').textContent = '新增科目';
        document.getElementById('acc_id').value = '';
        document.getElementById('acc_code').value = '';
        document.getElementById('acc_name').value = '';
        document.getElementById('acc_offset_type').value = '非立沖';
        document.getElementById('acc_tx_type').value = '交易科目';
        document.getElementById('acc_parent_code').value = '';
        document.getElementById('acc_type_num').value = '1';
        autoFillTypeName();
        document.getElementById('acc_type').value = 'asset';
        document.getElementById('acc_cat_code').value = '';
        document.getElementById('acc_cat_name').value = '';
        document.getElementById('acc_attr').value = '非收入費用';
        document.getElementById('acc_project_calc').value = '不核算專案';
        document.getElementById('acc_normal').value = 'debit';
        document.getElementById('acc_dept_calc').value = '核算部門';
        document.getElementById('acc_relate_type').value = '不核算';
        document.getElementById('acc_internal_flag').value = '否';
        document.getElementById('acc_level').value = 1;
        document.getElementById('acc_sort').value = 0;
        document.getElementById('acc_detail').checked = true;
        document.getElementById('acc_active').checked = true;
        document.getElementById('acc_desc').value = '';
    }
}
function closeAccountModal() {
    document.getElementById('accountModal').style.display = 'none';
}
function copyAsNewAccount() {
    document.getElementById('acc_id').value = '';
    document.getElementById('accountModalTitle').textContent = '新增科目（複製）';
    document.getElementById('acc_code').value = '';
    document.getElementById('acc_code').focus();
    document.getElementById('btnCopyAccount').style.display = 'none';
}

function autoFillTypeName() {
    var map = {'1':'資產類','2':'負債類','3':'權益類','4':'收入類','5':'成本類','6':'費用類','7':'營業外收支','8':'所得稅費用','9':'非營業'};
    var v = document.getElementById('acc_type_num').value;
    document.getElementById('acc_type_name_full').value = map[v] || '';
    var typeMap = {'1':'asset','2':'liability','3':'equity','4':'revenue','5':'expense','6':'expense','7':'revenue','8':'expense','9':'expense'};
    document.getElementById('acc_type').value = typeMap[v] || 'expense';
    var balMap = {'1':'debit','2':'credit','3':'credit','4':'credit','5':'debit','6':'debit','7':'debit','8':'debit','9':'debit'};
    document.getElementById('acc_normal').value = balMap[v] || 'debit';
}

// 拖曳移動 modal
(function() {
    var header = document.getElementById('accModalHeader');
    var content = document.getElementById('accModalContent');
    var isDragging = false, startX, startY, origX, origY;
    header.addEventListener('mousedown', function(e) {
        if (e.target.tagName === 'BUTTON') return;
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        var rect = content.getBoundingClientRect();
        origX = rect.left;
        origY = rect.top;
        content.style.position = 'fixed';
        content.style.margin = '0';
        content.style.left = origX + 'px';
        content.style.top = origY + 'px';
        e.preventDefault();
    });
    document.addEventListener('mousemove', function(e) {
        if (!isDragging) return;
        content.style.left = (origX + e.clientX - startX) + 'px';
        content.style.top = (origY + e.clientY - startY) + 'px';
    });
    document.addEventListener('mouseup', function() { isDragging = false; });
})();
</script>

<style>
.modal { position:fixed;top:0;left:0;right:0;bottom:0;z-index:1000;display:flex;align-items:center;justify-content:center }
.modal-overlay { position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5) }
.modal-content { position:relative;background:#fff;border-radius:8px;width:90%;max-height:90vh;overflow-y:auto }
.modal-header { display:flex;justify-content:space-between;align-items:center;padding:16px;border-bottom:1px solid #eee }
.modal-header h3 { margin:0 }
.modal-close { background:none;border:none;font-size:24px;cursor:pointer }
.modal-body { padding:16px }
.modal-footer { padding:16px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:8px }
.sg-item:hover { background:#e3f2fd !important; }
</style>
<?php endif; ?>
