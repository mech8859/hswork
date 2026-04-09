// 區域導航滾動高亮
(function() {
    var links = document.querySelectorAll('.sec-link');
    var sections = [];
    links.forEach(function(l) {
        var id = l.getAttribute('href').substring(1);
        var el = document.getElementById(id);
        if (el) sections.push({ el: el, link: l });
    });
    if (!sections.length) return;
    var onScroll = function() {
        var scrollY = window.scrollY + 80;
        var current = sections[0];
        for (var i = 0; i < sections.length; i++) {
            if (sections[i].el.offsetTop <= scrollY) current = sections[i];
        }
        links.forEach(function(l) { l.classList.remove('active'); });
        current.link.classList.add('active');
    };
    window.addEventListener('scroll', onScroll);
    // 平滑滾動
    links.forEach(function(l) {
        l.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('href').substring(1);
            var el = document.getElementById(id);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();

var lightboxImages = [];
var lightboxIndex = 0;
function openLightbox(src) {
    // 收集頁面上所有圖片（用 onclick 含 openLightbox 的）
    lightboxImages = [];
    var allImgs = document.querySelectorAll('.atc-thumb, .wl-photo-thumb, .drawing-thumb, .current-image');
    allImgs.forEach(function(img) {
        var oc = img.getAttribute('onclick') || '';
        var match = oc.match(/openLightbox\(['"]([^'"]+)['"]/);
        if (match && lightboxImages.indexOf(match[1]) === -1) {
            lightboxImages.push(match[1]);
        }
    });
    if (lightboxImages.length === 0) lightboxImages = [src];
    lightboxIndex = lightboxImages.indexOf(src);
    if (lightboxIndex < 0) lightboxIndex = 0;
    showLightboxImage();
    document.getElementById('lightboxOverlay').classList.add('active');
}
function showLightboxImage() {
    document.getElementById('lightboxImg').src = lightboxImages[lightboxIndex];
    var counter = document.getElementById('lightboxCounter');
    if (lightboxImages.length > 1) {
        counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxImages.length;
        counter.style.display = 'block';
    } else {
        counter.style.display = 'none';
    }
    // 隱藏/顯示箭頭
    document.querySelector('.lightbox-prev').style.display = lightboxImages.length > 1 ? 'block' : 'none';
    document.querySelector('.lightbox-next').style.display = lightboxImages.length > 1 ? 'block' : 'none';
}
function lightboxNav(dir) {
    lightboxIndex += dir;
    if (lightboxIndex < 0) lightboxIndex = lightboxImages.length - 1;
    if (lightboxIndex >= lightboxImages.length) lightboxIndex = 0;
    showLightboxImage();
}
function closeLightbox() { document.getElementById('lightboxOverlay').classList.remove('active'); document.getElementById('lightboxImg').src = ''; }
document.addEventListener('keydown', function(e) {
    var overlay = document.getElementById('lightboxOverlay');
    if (!overlay || !overlay.classList.contains('active')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') lightboxNav(-1);
    if (e.key === 'ArrowRight') lightboxNav(1);
});

// 觸控滑動：左右切換照片，上下關閉
(function() {
    var touchStartX = 0, touchStartY = 0, touchEndX = 0, touchEndY = 0;
    document.addEventListener('DOMContentLoaded', function() {
        var overlay = document.getElementById('lightboxOverlay');
        if (!overlay) return;
        overlay.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, {passive: true});
        overlay.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;
            var dx = touchEndX - touchStartX;
            var dy = touchEndY - touchStartY;
            var absDx = Math.abs(dx);
            var absDy = Math.abs(dy);
            // 滑動距離夠（>50px）才算手勢
            if (absDx < 50 && absDy < 50) return;
            if (absDx > absDy) {
                // 水平滑動：切換照片
                if (dx > 0) lightboxNav(-1); // 右滑 → 上一張
                else lightboxNav(1);          // 左滑 → 下一張
            } else {
                // 垂直滑動：關閉
                closeLightbox();
            }
        }, {passive: true});
    });
})();
// 瀏覽器返回鍵也關閉 lightbox
window.addEventListener('popstate', function() {
    var overlay = document.getElementById('lightboxOverlay');
    if (overlay && overlay.classList.contains('active')) closeLightbox();
});

var contactIndex = CASE_DATA.contactCount;
function addContact() {
    var html = '<div class="contact-row" data-index="' + contactIndex + '">' +
        '<div class="form-row">' +
        '<div class="form-group"><label>姓名</label><input type="text" name="contacts[' + contactIndex + '][contact_name]" class="form-control"></div>' +
        '<div class="form-group"><label>電話</label><input type="text" name="contacts[' + contactIndex + '][contact_phone]" class="form-control"></div>' +
        '<div class="form-group"><label>角色</label><input type="text" name="contacts[' + contactIndex + '][contact_role]" class="form-control" placeholder="屋主/管委會/工地主任"></div>' +
        '<div class="form-group" style="align-self:flex-end"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.contact-row\').remove()">刪除</button></div>' +
        '</div></div>';
    document.getElementById('contactsContainer').insertAdjacentHTML('beforeend', html);
    contactIndex++;
}
function toggleLadderSize() {
    document.getElementById('ladderSizeWrap').style.display = document.getElementById('chkLadder').checked ? 'flex' : 'none';
}
function toggleHighCeiling() {
    var wrap = document.getElementById('highCeilingWrap');
    wrap.style.display = document.getElementById('chkHighCeiling').checked ? 'inline-flex' : 'none';
    if (!document.getElementById('chkHighCeiling').checked) wrap.querySelector('input[name="high_ceiling_height"]').value = '';
}
function toggleScissorLift() {
    document.getElementById('scissorLiftWrap').style.display = document.getElementById('chkScissorLift').checked ? 'flex' : 'none';
}
function toggleSafetyEquipment() {
    document.getElementById('safetyEquipmentWrap').style.display = document.getElementById('chkSafetyToggle').checked ? 'flex' : 'none';
}
function onScissorPresetChange(radio) {
    var input = document.getElementById('scissorLiftHeightInput');
    if (radio.value === 'custom') {
        input.value = '';
        input.focus();
    } else {
        input.value = radio.value;
    }
}
function toggleSkillLevel(cb) {
    var sel = cb.closest('.skill-item').querySelector('.skill-level');
    if (cb.checked) { sel.style.display = 'inline-block'; if (sel.value === '0') sel.value = '1'; }
    else { sel.style.display = 'none'; sel.value = '0'; }
}
function uploadFiles(input, fileType) {
    if (!input.files.length) return;
    doUploadAttachments(input.files, fileType, input.parentElement, function() { input.value = ''; });
}
function doUploadAttachments(files, fileType, addBtn, doneCb) {
    var csrfToken = document.querySelector('input[name="csrf_token"]').value;
    var origText = addBtn.querySelector('span').textContent;
    addBtn.querySelector('span').textContent = '壓縮中...';
    compressImages(Array.prototype.slice.call(files)).then(function(compressed) {
    var uploaded = 0, total = compressed.length;
    addBtn.querySelector('span').textContent = '上傳中 0/' + total + '...';
    for (var i = 0; i < compressed.length; i++) {
        (function(file) {
            if (file.size > 20 * 1024 * 1024) { alert(file.name + ' 超過 20MB'); uploaded++; if (uploaded >= total) { addBtn.querySelector('span').textContent = origText; if (doneCb) doneCb(); } return; }
            var fd = new FormData(); fd.append('file', file); fd.append('file_type', fileType); fd.append('csrf_token', csrfToken);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/cases.php?action=upload_attachment&id=' + CASE_DATA.caseId);
            xhr.onload = function() {
                uploaded++;
                addBtn.querySelector('span').textContent = '上傳中 ' + uploaded + '/' + total + '...';
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            var imgExts = ['jpg','jpeg','png','gif','webp','bmp'];
                            var ext = res.file_name.split('.').pop().toLowerCase();
                            var html;
                            if (imgExts.indexOf(ext) !== -1) {
                                html = '<div class="atc-file atc-file-img" id="att-' + res.id + '"><img src="' + res.file_path + '" class="atc-thumb" onclick="openLightbox(\'' + res.file_path + '\')" alt="' + res.file_name + '"><button type="button" class="atc-del" onclick="deleteAttachment(' + res.id + ',\'' + fileType + '\')">✕</button></div>';
                            } else {
                                html = '<div class="atc-file" id="att-' + res.id + '"><a href="' + res.file_path + '" target="_blank" class="atc-filename">📄 ' + res.file_name + '</a><button type="button" class="atc-del" onclick="deleteAttachment(' + res.id + ',\'' + fileType + '\')">✕</button></div>';
                            }
                            document.getElementById('atc-files-' + fileType).insertAdjacentHTML('beforeend', html);
                            updateCount(fileType, 1);
                        } else { alert(res.error || '上傳失敗'); }
                    } catch(e) { alert('上傳失敗'); }
                } else {
                    alert('上傳失敗 (HTTP ' + xhr.status + ')');
                }
                if (uploaded >= total) { addBtn.querySelector('span').textContent = origText; if (doneCb) doneCb(); }
            };
            xhr.onerror = function() { uploaded++; alert('網路錯誤'); if (uploaded >= total) { addBtn.querySelector('span').textContent = origText; if (doneCb) doneCb(); } };
            xhr.send(fd);
        })(compressed[i]);
    }
    });
}

// 拖曳上傳：bind 所有 .attach-type-card[data-file-type]
(function() {
    function bindCard(card) {
        var fileType = card.getAttribute('data-file-type');
        var addBtn = card.querySelector('.atc-add-btn');
        if (!fileType || !addBtn) return;
        ['dragenter','dragover'].forEach(function(ev){
            card.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); card.classList.add('atc-drag-over'); });
        });
        card.addEventListener('dragleave', function(e) {
            if (card.contains(e.relatedTarget)) return;
            card.classList.remove('atc-drag-over');
        });
        card.addEventListener('drop', function(e) {
            e.preventDefault(); e.stopPropagation();
            card.classList.remove('atc-drag-over');
            var files = e.dataTransfer && e.dataTransfer.files;
            if (files && files.length) doUploadAttachments(files, fileType, addBtn);
        });
    }
    function bindAll() {
        document.querySelectorAll('.attach-type-card[data-file-type]').forEach(bindCard);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindAll);
    } else {
        bindAll();
    }
    // 防止整頁被瀏覽器當成檔案開啟
    ['dragover','drop'].forEach(function(ev){
        window.addEventListener(ev, function(e){
            if (e.target.closest && e.target.closest('.attach-type-card')) return;
            e.preventDefault();
        });
    });
})();
function updateCount(fileType, delta) {
    var el = document.getElementById('atc-count-' + fileType);
    if (el) el.textContent = parseInt(el.textContent || '0') + delta;
}
function addNewAttachType() {
    var label = prompt('請輸入新的附件分類名稱：');
    if (!label || !label.trim()) return;
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('label', label.trim());
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=add_attach_type', true);
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) { location.reload(); }
            else { alert(res.error || '新增失敗'); }
        } catch(e) { alert('新增失敗'); }
    };
    xhr.send(fd);
}
function confirmDeleteCase(id, caseNumber) {
    if (!confirm('確定要刪除案件 ' + caseNumber + '？\n\n此操作無法復原，將同時刪除所有附件、聯絡人、排工紀錄等關聯資料。')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/cases.php?action=delete';
    var idInput = document.createElement('input');
    idInput.type = 'hidden'; idInput.name = 'id'; idInput.value = id;
    var csrfInput = document.createElement('input');
    csrfInput.type = 'hidden'; csrfInput.name = 'csrf_token'; csrfInput.value = document.querySelector('input[name="csrf_token"]').value;
    form.appendChild(idInput);
    form.appendChild(csrfInput);
    document.body.appendChild(form);
    form.submit();
}
function deleteAttachment(id, fileType) {
    if (!confirm('確定刪除此附件?')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=delete_attachment');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) { var el = document.getElementById('att-' + id); if (el) el.remove(); if (fileType) updateCount(fileType, -1); }
                else { alert(res.error || '刪除失敗'); }
            } catch(e) { alert('刪除失敗'); }
        }
    };
    xhr.send('attachment_id=' + id + '&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
}

function toggleNoPhoto(checked) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=toggle_no_photo');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) { location.reload(); }
    };
    xhr.send('case_id=' + CASE_DATA.caseId + '&no_photo=' + (checked ? '1' : '0') + '&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
}
function togglePaymentForm() {
    var f = document.getElementById('payment-add-form');
    var willOpen = f.style.display === 'none';
    f.style.display = willOpen ? 'block' : 'none';
    if (willOpen) {
        // 開啟新增表單時重置稅額手動標記
        payTaxManual = false;
    }
}

// 新增表單：未稅金額變更 → 自動算 5% 稅 + 總金額
var payTaxManual = false;
function onPayUntaxedChange() {
    var u = parseFloat(document.getElementById('pay_untaxed_amount').value) || 0;
    if (!payTaxManual) {
        document.getElementById('pay_tax_amount').value = Math.round(u * 0.05);
    }
    calcPayTotal();
}
function onPayTaxChange() {
    payTaxManual = true; // 用戶手動改過稅額後不再自動算
    calcPayTotal();
}
function calcPayTotal() {
    var u = parseFloat(document.getElementById('pay_untaxed_amount').value) || 0;
    var t = parseFloat(document.getElementById('pay_tax_amount').value) || 0;
    document.getElementById('pay_amount').value = u + t;
}

// 編輯 modal：同樣邏輯
var pdTaxManual = false;
function onPdUntaxedChange() {
    var u = parseFloat(document.getElementById('pd_untaxed_amount').value) || 0;
    if (!pdTaxManual) {
        document.getElementById('pd_tax_amount').value = Math.round(u * 0.05);
    }
    calcPdTotal();
}
function onPdTaxChange() {
    pdTaxManual = true;
    calcPdTotal();
}
function calcPdTotal() {
    var u = parseFloat(document.getElementById('pd_untaxed_amount').value) || 0;
    var t = parseFloat(document.getElementById('pd_tax_amount').value) || 0;
    document.getElementById('pd_amount').value = u + t;
}

function saveCasePayment() {
    var date = document.getElementById('pay_date').value;
    var amount = document.getElementById('pay_amount').value;
    if (!date || !amount) { alert('請填寫日期和金額'); return; }

    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('case_id', CASE_DATA.caseId);
    fd.append('payment_date', date);
    fd.append('payment_type', document.getElementById('pay_type').value);
    fd.append('transaction_type', document.getElementById('pay_method').value);
    fd.append('amount', amount);
    fd.append('untaxed_amount', document.getElementById('pay_untaxed_amount').value || 0);
    fd.append('tax_amount', document.getElementById('pay_tax_amount').value || 0);
    fd.append('receipt_number', document.getElementById('pay_receipt_number').value);
    fd.append('note', document.getElementById('pay_note').value);
    var img = document.getElementById('pay_image');
    for (var fi = 0; fi < img.files.length; fi++) {
        fd.append('images[]', img.files[fi]);
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=add_payment');
    xhr.onload = function() {
        if (xhr.status !== 200) {
            alert('儲存失敗 HTTP ' + xhr.status + '\n\n' + xhr.responseText.substring(0, 500));
            return;
        }
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) {
                location.reload();
            } else {
                alert('儲存失敗：' + (res.error || '未知錯誤'));
            }
        } catch (e) {
            alert('回應解析失敗：\n' + xhr.responseText.substring(0, 500));
        }
    };
    xhr.onerror = function() { alert('網路錯誤'); };
    xhr.send(fd);
}

function deleteCasePayment(id) {
    if (!confirm('確定刪除此帳款紀錄？')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=delete_payment');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() { location.reload(); };
    xhr.send('payment_id=' + id + '&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
}

var wlSelectedFiles = [];
function previewWlPhotos(input) {
    var container = document.getElementById('wl_photo_previews');
    for (var i = 0; i < input.files.length; i++) {
        var file = input.files[i];
        wlSelectedFiles.push(file);
        var idx = wlSelectedFiles.length - 1;
        var reader = new FileReader();
        reader.onload = (function(index) {
            return function(e) {
                var div = document.createElement('div');
                div.className = 'wl-preview-item';
                div.id = 'wl-prev-' + index;
                div.innerHTML = '<img src="' + e.target.result + '">' +
                    '<button type="button" class="wl-preview-del" onclick="removeWlPhoto(' + index + ')">✕</button>';
                container.appendChild(div);
            };
        })(idx);
        reader.readAsDataURL(file);
    }
    input.value = '';
}
function removeWlPhoto(idx) {
    wlSelectedFiles[idx] = null;
    var el = document.getElementById('wl-prev-' + idx);
    if (el) el.remove();
}

function toggleWorklogForm() {
    var f = document.getElementById('worklog-add-form');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

function saveWorklog() {
    var date = document.getElementById('wl_date').value;
    var content = document.getElementById('wl_content').value;
    if (!date || !content) { alert('請填寫施工日期和施工內容'); return; }

    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('case_id', CASE_DATA.caseId);
    fd.append('work_date', date);
    fd.append('work_content', content);
    fd.append('equipment_used', document.getElementById('wl_equipment').value);
    fd.append('cable_used', document.getElementById('wl_cable').value);

    var arrival = document.getElementById('wl_arrival').value;
    var departure = document.getElementById('wl_departure').value;
    if (arrival) fd.append('arrival_time', arrival);
    if (departure) fd.append('departure_time', departure);

    var photoFiles = [];
    for (var i = 0; i < wlSelectedFiles.length; i++) {
        if (wlSelectedFiles[i]) photoFiles.push(wlSelectedFiles[i]);
    }

    // 顯示 loading 提示
    var saveBtn = document.querySelector('[onclick*="saveWorklog"]');
    var origText = saveBtn ? saveBtn.textContent : '';
    if (saveBtn) { saveBtn.textContent = photoFiles.length > 0 ? '壓縮照片中...' : '儲存中...'; saveBtn.disabled = true; }

    var doSend = function(compressed) {
        for (var j = 0; j < compressed.length; j++) {
            fd.append('photos[]', compressed[j]);
        }
        if (saveBtn) saveBtn.textContent = '上傳中...';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/cases.php?action=add_worklog');
        xhr.onload = function() {
            if (saveBtn) { saveBtn.textContent = '完成！'; }
            location.reload();
        };
        xhr.onerror = function() {
            alert('上傳失敗，請重試');
            if (saveBtn) { saveBtn.textContent = origText; saveBtn.disabled = false; }
        };
        xhr.send(fd);
    };

    if (photoFiles.length > 0) {
        compressImages(photoFiles).then(doSend);
    } else {
        doSend([]);
    }
}

function deleteWorklog(id) {
    if (!confirm('確定刪除此施工回報？')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=delete_worklog');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() { location.reload(); };
    xhr.send('worklog_id=' + id + '&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
}

// ===== 表單驗證 =====
function validateCaseForm() {
    var phone = document.getElementById('customerPhoneInput').value.trim();
    var mobile = document.getElementById('customerMobileInput').value.trim();
    if (!phone && !mobile) {
        alert('請填寫市話或手機，至少填一個');
        document.getElementById('customerPhoneInput').focus();
        return false;
    }
    return true;
}

// ===== 客戶搜尋 =====
var customerSearchTimer = null;
var lastCustomerKeyword = '';
function onCustomerKeyup(e) {
    // 跳過 IME 組字中的按鍵 (keyCode 229 = IME processing)
    if (e.isComposing || e.keyCode === 229) return;
    var val = document.getElementById('customerNameInput').value;
    if (val === lastCustomerKeyword) return;
    lastCustomerKeyword = val;
    // 清除客戶關聯
    document.getElementById('customerId').value = '';
    var info = document.getElementById('customerInfo');
    if (info) info.textContent = '';
    searchCustomer(val);
}
function searchCustomer(keyword) {
    clearTimeout(customerSearchTimer);
    var dd = document.getElementById('customerDropdown');
    if (keyword.length < 2) { dd.style.display = 'none'; return; }
    customerSearchTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/cases.php?action=ajax_search_customer&keyword=' + encodeURIComponent(keyword));
        xhr.onload = function() {
            var data = JSON.parse(xhr.responseText);
            if (!data.length) {
                dd.innerHTML = '<div class="customer-dropdown-item" style="color:#999">無符合客戶，請按「+ 新增客戶」建立</div>';
                dd.style.display = 'block';
                return;
            }
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var c = data[i];
                var contacts = c.contacts || [];
                var contactText = '';
                for (var j = 0; j < contacts.length; j++) {
                    if (j > 0) contactText += '、';
                    contactText += contacts[j].contact_name + (contacts[j].phone ? ' ' + contacts[j].phone : '');
                }
                var blacklistBadge = c.is_blacklisted == 1 ? '<span style="background:#e53e3e;color:#fff;padding:1px 6px;border-radius:3px;font-size:.7em;margin-left:4px">⚠ 黑名單</span>' : '';
                var itemStyle = c.is_blacklisted == 1 ? 'border-left:3px solid #e53e3e;background:#fff5f5;' : '';
                html += '<div class="customer-dropdown-item" style="' + itemStyle + '" onclick="selectCustomer(' + JSON.stringify(c).replace(/"/g, '&quot;') + ')">' +
                    '<div style="font-weight:600">' + escHtml(c.name) + blacklistBadge + '</div>' +
                    '<div style="font-size:.75rem;color:#888">' +
                        (c.phone ? c.phone + ' ' : '') +
                        (c.tax_id ? '統編:' + c.tax_id + ' ' : '') +
                        (contactText ? '聯絡人:' + contactText : '') +
                    '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
        };
        xhr.send();
    }, 300);
}

function escHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
}

function selectCustomer(c) {
    // 黑名單警告
    if (c.is_blacklisted == 1) {
        var reason = c.blacklist_reason ? '\n原因：' + c.blacklist_reason : '';
        if (!confirm('⚠️ 警告：此客戶已列入黑名單！' + reason + '\n\n確定要選擇此客戶嗎？')) {
            return;
        }
    }
    document.getElementById('customerId').value = c.id;
    document.getElementById('customerNameInput').value = c.name;
    document.getElementById('customerDropdown').style.display = 'none';

    // 更新客戶編號顯示
    var noDisp = document.getElementById('customerNoDisplay');
    if (noDisp && c.customer_no) noDisp.value = c.customer_no;

    // 帶入施工地址（如果為空）
    var addrInput = document.querySelector('input[name="address"]');
    if (addrInput && !addrInput.value && c.site_address) {
        addrInput.value = c.site_address;
    }

    // 帶入案件名稱（如果為空）
    var titleInput = document.querySelector('input[name="title"]');
    if (titleInput && !titleInput.value) titleInput.value = c.name;

    // 帶入聯絡人/電話/手機/LINE（如果為空）
    var cpInput = document.getElementById('contactPersonInput');
    if (cpInput && !cpInput.value && c.contact_person) cpInput.value = c.contact_person;
    var phInput = document.getElementById('customerPhoneInput');
    if (phInput && !phInput.value && c.phone) phInput.value = c.phone;
    var mbInput = document.getElementById('customerMobileInput');
    if (mbInput && !mbInput.value && c.mobile) mbInput.value = c.mobile;
    var liInput = document.getElementById('contactLineInput');
    if (liInput && !liInput.value && c.line_official) liInput.value = c.line_official;
    var coInput = document.getElementById('companyInput');
    if (coInput && !coInput.value && c.source_company) coInput.value = c.source_company;

    // 帶入聯絡人
    var contacts = c.contacts || [];
    if (contacts.length > 0) {
        var container = document.getElementById('contactsContainer');
        // 只在沒有已填聯絡人時帶入
        var existingNames = container.querySelectorAll('input[name*="contact_name"]');
        var hasExisting = false;
        for (var i = 0; i < existingNames.length; i++) {
            if (existingNames[i].value.trim()) { hasExisting = true; break; }
        }
        if (!hasExisting) {
            container.innerHTML = '';
            contactIndex = 0;
            for (var j = 0; j < contacts.length; j++) {
                var ct = contacts[j];
                var html = '<div class="contact-row" data-index="' + contactIndex + '">' +
                    '<div class="form-row">' +
                    '<div class="form-group"><label>姓名</label><input type="text" name="contacts[' + contactIndex + '][contact_name]" class="form-control" value="' + escHtml(ct.contact_name) + '"></div>' +
                    '<div class="form-group"><label>電話</label><input type="text" name="contacts[' + contactIndex + '][contact_phone]" class="form-control" value="' + escHtml(ct.phone || '') + '"></div>' +
                    '<div class="form-group"><label>角色</label><input type="text" name="contacts[' + contactIndex + '][contact_role]" class="form-control" value="' + escHtml(ct.role || '') + '" placeholder="屋主/管委會/工地主任"></div>' +
                    '<div class="form-group" style="align-self:flex-end"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.contact-row\').remove()">刪除</button></div>' +
                    '</div></div>';
                container.insertAdjacentHTML('beforeend', html);
                contactIndex++;
            }
        }
    }

    // 顯示已關聯提示
    var info = document.getElementById('customerInfo');
    if (!info) {
        var inp = document.getElementById('customerNameInput');
        var small = document.createElement('small');
        small.className = 'text-muted';
        small.id = 'customerInfo';
        inp.parentNode.appendChild(small);
        info = small;
    }
    var label = '已關聯客戶 ' + c.name + (c.customer_no ? ' (' + c.customer_no + ')' : '');
    info.innerHTML = '<a href="customers.php?action=view&id=' + c.id + '" style="color:#007bff;text-decoration:underline">' + label + '</a>';
}

// 點擊外面關閉下拉
document.addEventListener('click', function(e) {
    if (!e.target.closest('#customerNameInput') && !e.target.closest('#customerDropdown')) {
        document.getElementById('customerDropdown').style.display = 'none';
    }
});

// 清除客戶關聯已整合到 onCustomerKeyup

// ===== 統編關聯客戶建議 =====
// 條件：案件尚未關聯客戶 + 已填統一編號 + 客戶資料中存在相同統編
// → 在客戶名稱下方顯示「設定關聯客戶」提示，點選後可手動關聯
var taxIdLookupCache = { taxId: null, data: null };
function checkTaxIdLink() {
    var cidEl = document.getElementById('customerId');
    var taxEl = document.getElementById('billingTaxIdInput');
    var info = document.getElementById('customerInfo');
    if (!cidEl || !taxEl) return;
    if (!info) {
        var nameInp = document.getElementById('customerNameInput');
        if (nameInp && nameInp.parentNode) {
            info = document.createElement('small');
            info.id = 'customerInfo';
            info.className = 'text-muted';
            info.style.cssText = 'position:absolute;bottom:-18px;left:0;font-size:.75rem;z-index:2';
            nameInp.parentNode.appendChild(info);
        } else {
            return;
        }
    }
    var cidVal = (cidEl.value || '').trim();
    if (cidVal && cidVal !== '0') return;
    var taxId = (taxEl.value || '').trim();
    if (!taxId) {
        if (info.getAttribute('data-suggest') === '1') {
            info.innerHTML = '';
            info.removeAttribute('data-suggest');
        }
        return;
    }
    if (taxIdLookupCache.taxId === taxId && taxIdLookupCache.data) {
        renderTaxIdSuggest(taxIdLookupCache.data);
        return;
    }
    var caseId = (typeof CASE_DATA !== 'undefined' && CASE_DATA.caseId) ? CASE_DATA.caseId : 0;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/cases.php?action=ajax_lookup_by_tax_id&tax_id=' + encodeURIComponent(taxId) + '&exclude_case_id=' + caseId);
    xhr.onload = function() {
        try {
            var data = JSON.parse(xhr.responseText);
            taxIdLookupCache = { taxId: taxId, data: data };
            renderTaxIdSuggest(data);
        } catch (e) {}
    };
    xhr.send();
}
function renderTaxIdSuggest(data) {
    var info = document.getElementById('customerInfo');
    if (!info) return;
    var nCust = (data.customers || []).length;
    var nCases = (data.cases || []).length;
    if (nCust === 0 && nCases === 0) {
        if (info.getAttribute('data-suggest') === '1') {
            info.innerHTML = '';
            info.removeAttribute('data-suggest');
        }
        return;
    }
    info.setAttribute('data-suggest', '1');
    var label = '🔗 設定關聯客戶（找到 ' + nCust + ' 位相同統編客戶' + (nCases > 0 ? '，已用於 ' + nCases + ' 筆案件' : '') + '）';
    info.innerHTML = '<a href="javascript:void(0)" onclick="openTaxIdLinkModal()" style="color:#e65100;text-decoration:underline;font-weight:600;cursor:pointer">' + label + '</a>';
}
function openTaxIdLinkModal() {
    // 確保 modal 存在
    var modal = document.getElementById('taxIdLinkModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'taxIdLinkModal';
        modal.className = 'modal-overlay';
        modal.style.display = 'none';
        modal.onclick = function(e) { if (e.target === modal) closeTaxIdLinkModal(); };
        modal.innerHTML =
            '<div class="modal-content" style="max-width:760px">' +
              '<div class="modal-header">' +
                '<h3 style="margin:0">設定關聯客戶（依統一編號）</h3>' +
                '<button type="button" onclick="closeTaxIdLinkModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer">&times;</button>' +
              '</div>' +
              '<div class="modal-body"><div id="taxIdLinkBody" style="font-size:.88rem">載入中...</div></div>' +
              '<div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeTaxIdLinkModal()">關閉</button></div>' +
            '</div>';
        document.body.appendChild(modal);
    }
    var data = taxIdLookupCache.data;
    var body = document.getElementById('taxIdLinkBody');
    if (!data) { body.textContent = '請先輸入統一編號'; }
    else { body.innerHTML = buildTaxIdLinkHtml(data); }
    modal.style.display = 'flex';
}
function closeTaxIdLinkModal() {
    var m = document.getElementById('taxIdLinkModal');
    if (m) m.style.display = 'none';
}
function buildTaxIdLinkHtml(data) {
    var customers = data.customers || [];
    var cases = data.cases || [];
    var caseCount = {}, caseSamples = {}, unlinkedCases = [];
    for (var j = 0; j < cases.length; j++) {
        var k = cases[j];
        var cid = +k.customer_id;
        if (cid > 0) {
            caseCount[cid] = (caseCount[cid] || 0) + 1;
            if (!caseSamples[cid]) caseSamples[cid] = [];
            if (caseSamples[cid].length < 3) caseSamples[cid].push(k.case_number);
        } else {
            unlinkedCases.push(k);
        }
    }
    var html = '';
    if (customers.length === 0) {
        html += '<div style="padding:10px;background:#fff8e1;border:1px solid #ffe082;border-radius:6px;font-size:.85rem">客戶資料中沒有此統編。請按「+ 新增客戶」建立。</div>';
    } else {
        html += '<div style="font-weight:600;margin-bottom:6px">找到 ' + customers.length + ' 位相同統編客戶，請選擇要關聯的客戶</div>';
        html += '<div style="border:1px solid #e0e0e0;border-radius:6px;overflow:hidden">';
        for (var i = 0; i < customers.length; i++) {
            var c = customers[i];
            var blacklist = c.is_blacklisted == 1 ? '<span style="background:#e53e3e;color:#fff;padding:1px 6px;border-radius:3px;font-size:.7em;margin-left:4px">⚠ 黑名單</span>' : '';
            var cnt = caseCount[+c.id] || 0;
            var caseBadge = cnt > 0
                ? '<span style="background:#e3f2fd;color:#1565c0;padding:1px 8px;border-radius:10px;font-size:.72rem;margin-left:6px">已用於 ' + cnt + ' 筆案件' + (caseSamples[+c.id] ? '（' + caseSamples[+c.id].join('、') + (cnt > 3 ? '…' : '') + '）' : '') + '</span>'
                : '<span style="background:#f5f5f5;color:#999;padding:1px 8px;border-radius:10px;font-size:.72rem;margin-left:6px">尚未用於其他案件</span>';
            html += '<div style="padding:10px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center">' +
                '<div style="flex:1;min-width:0">' +
                    '<div style="font-weight:600">' + escHtml(c.name) + blacklist + caseBadge + '</div>' +
                    '<div style="font-size:.78rem;color:#888;margin-top:2px">' +
                        (c.customer_no ? '客戶編號:' + escHtml(c.customer_no) + '　' : '') +
                        (c.tax_id ? '統編:' + escHtml(c.tax_id) + '　' : '') +
                        (c.phone ? '電話:' + escHtml(c.phone) + '　' : '') +
                        (c.contact_person ? '聯絡人:' + escHtml(c.contact_person) : '') +
                    '</div>' +
                '</div>' +
                '<button type="button" class="btn btn-primary btn-sm" data-cust-id="' + (+c.id) + '" style="white-space:nowrap;margin-left:10px" onclick="linkCustomerFromTaxId(this.getAttribute(\'data-cust-id\'))">關聯此客戶</button>' +
            '</div>';
        }
        html += '</div>';
    }
    if (unlinkedCases.length > 0) {
        html += '<div style="margin-top:12px;padding:8px 10px;background:#fff8e1;border:1px solid #ffe082;border-radius:6px;font-size:.8rem;color:#666">另有 ' + unlinkedCases.length + ' 筆相同統編案件尚未關聯客戶：' +
            unlinkedCases.slice(0, 5).map(function(x){ return escHtml(x.case_number); }).join('、') +
            (unlinkedCases.length > 5 ? '…' : '') + '</div>';
    }
    return html;
}
// 只關聯客戶（設定 customer_id + 帶入客戶編號），不覆蓋案件其他欄位
function linkCustomerOnly(c) {
    if (!c || !c.id) return;
    if (c.is_blacklisted == 1) {
        var reason = c.blacklist_reason ? '\n原因：' + c.blacklist_reason : '';
        if (!confirm('⚠️ 警告：此客戶已列入黑名單！' + reason + '\n\n確定要關聯此客戶嗎？')) return;
    }
    document.getElementById('customerId').value = c.id;
    var noDisp = document.getElementById('customerNoDisplay');
    if (noDisp) noDisp.value = c.customer_no || '';
    var info = document.getElementById('customerInfo');
    if (info) {
        info.removeAttribute('data-suggest');
        var label = '已關聯客戶 ' + (c.name || '') + (c.customer_no ? ' (' + c.customer_no + ')' : '');
        info.innerHTML = '<a href="customers.php?action=view&id=' + c.id + '" style="color:#007bff;text-decoration:underline;cursor:pointer">' + label + '</a>';
    }
    closeTaxIdLinkModal();
}
function linkCustomerFromTaxId(customerId) {
    customerId = +customerId;
    if (!customerId) return;
    var custs = (taxIdLookupCache.data && taxIdLookupCache.data.customers) || [];
    for (var i = 0; i < custs.length; i++) {
        if (+custs[i].id === customerId) { linkCustomerOnly(custs[i]); return; }
    }
    alert('找不到對應客戶資料');
}
function _initTaxIdLink() {
    checkTaxIdLink();
    var taxEl = document.getElementById('billingTaxIdInput');
    if (taxEl) {
        taxEl.addEventListener('blur', checkTaxIdLink);
        taxEl.addEventListener('change', checkTaxIdLink);
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _initTaxIdLink);
} else {
    _initTaxIdLink();
}

// ===== 「+ 新增客戶」按鈕：只有狀態為成交時才顯示 =====
var DEAL_STATUSES = ['已成交','跨月成交','現簽','電話報價成交'];
function toggleNewCustomerBtn() {
    var sel = document.getElementById('subStatusSelect');
    var btn = document.getElementById('btnNewCustomer');
    if (!sel || !btn) return;
    btn.style.display = DEAL_STATUSES.indexOf(sel.value) !== -1 ? '' : 'none';
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', toggleNewCustomerBtn);
} else {
    toggleNewCustomerBtn();
}

// ===== 新增客戶 Modal =====
function _val(id) { var el = document.getElementById(id); return el ? el.value : ''; }
function _setIfEmpty(id, val) { var el = document.getElementById(id); if (el && !el.value && val) el.value = val; }
function openNewCustomerModal() {
    // 從案件表單帶入資料
    _setIfEmpty('modalCustomerName', _val('customerNameInput'));
    _setIfEmpty('modalContactPerson', _val('contactPersonInput'));
    _setIfEmpty('modalPhone', _val('customerPhoneInput'));
    _setIfEmpty('modalMobile', _val('customerMobileInput'));
    _setIfEmpty('modalLineId', _val('contactLineInput'));
    var emailEl = document.querySelector('input[name="customer_email"]');
    _setIfEmpty('modalEmail', emailEl ? emailEl.value : '');
    var titleEl = document.querySelector('input[name="billing_title"]');
    _setIfEmpty('modalInvoiceTitle', titleEl ? titleEl.value : '');
    var taxEl = document.getElementById('billingTaxIdInput');
    _setIfEmpty('modalTaxId', taxEl ? taxEl.value : '');
    var addrEl = document.querySelector('input[name="address"]');
    _setIfEmpty('modalAddress', addrEl ? addrEl.value : '');
    // 承辦業務 select
    var caseSales = document.querySelector('select[name="sales_id"]');
    var modalSales = document.getElementById('modalSalesId');
    if (caseSales && modalSales && !modalSales.value && caseSales.value) {
        modalSales.value = caseSales.value;
    }
    document.getElementById('newCustomerModal').style.display = 'flex';
    if (!_val('modalCustomerName')) document.getElementById('modalCustomerName').focus();
}
function closeNewCustomerModal() {
    document.getElementById('newCustomerModal').style.display = 'none';
}
function saveNewCustomer() {
    var name = document.getElementById('modalCustomerName').value.trim();
    if (!name) { alert('請輸入客戶名稱'); return; }

    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('name', name);
    fd.append('contact_person', _val('modalContactPerson'));
    fd.append('phone', _val('modalPhone'));
    fd.append('mobile', _val('modalMobile'));
    fd.append('line_id', _val('modalLineId'));
    fd.append('email', _val('modalEmail'));
    fd.append('invoice_title', _val('modalInvoiceTitle'));
    fd.append('tax_id', _val('modalTaxId'));
    fd.append('sales_id', _val('modalSalesId'));
    fd.append('address', _val('modalAddress'));

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=ajax_create_customer');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if (res.success) {
            closeNewCustomerModal();
            // 只關聯客戶，不覆蓋案件其他欄位
            if (typeof linkCustomerOnly === 'function') {
                linkCustomerOnly(res.customer);
            } else {
                selectCustomer(res.customer);
            }
        } else {
            alert(res.error || '建立客戶失敗');
        }
    };
    xhr.send(fd);
}

// === 帳務資訊自動計算 ===
(function() {
    var taxSelect = document.querySelector('select[name="is_tax_included"]');
    var dealInput = document.querySelector('input[name="deal_amount"]');
    var taxInput = document.querySelector('input[name="tax_amount"]');
    var totalInput = document.querySelector('input[name="total_amount"]');
    var balanceInput = document.getElementById('balanceInput');
    var totalCollectedDisplay = document.getElementById('totalCollectedDisplay');

    if (!taxSelect || !dealInput || !taxInput || !totalInput) return;

    // 成交金額有值時，是否含稅才必填
    var taxMark = document.querySelector('.tax-required-mark');
    dealInput.addEventListener('input', function() {
        var hasAmount = this.value && parseInt(this.value) > 0;
        taxSelect.required = hasAmount;
        if (taxMark) taxMark.style.display = hasAmount ? '' : 'none';
    });

    function parseNum(v) { return parseInt(String(v).replace(/,/g, '')) || 0; }
    function fmtNum(n) { return n ? n.toLocaleString('en-US') : ''; }
    function setNum(el, n) { if (!el) return; el.value = fmtNum(n); el.dataset.raw = n || ''; }
    function recalcFinance() {
        var deal = parseNum(dealInput.value);
        var taxVal = taxSelect.value;
        var taxRow = document.getElementById('taxRow');
        var collected = totalCollectedDisplay ? parseNum(totalCollectedDisplay.value) : 0;

        if (taxVal === '含稅(需開發票)') {
            var tax = Math.round(deal * 0.05);
            var total = deal + tax;
            setNum(taxInput, tax);
            setNum(totalInput, total);
            if (taxRow) taxRow.style.display = '';
            setNum(balanceInput, deal > 0 ? (total - collected) : 0);
        } else if (taxVal === '含稅(免開發票)') {
            var total = deal;
            var untaxed = Math.round(deal / 1.05);
            var tax = deal - untaxed;
            setNum(taxInput, tax);
            setNum(totalInput, total);
            if (taxRow) taxRow.style.display = '';
            setNum(balanceInput, deal > 0 ? (total - collected) : 0);
        } else {
            setNum(taxInput, 0);
            setNum(totalInput, 0);
            if (taxRow) taxRow.style.display = 'none';
            setNum(balanceInput, deal > 0 ? (deal - collected) : 0);
        }
    }

    taxSelect.addEventListener('change', recalcFinance);
    dealInput.addEventListener('input', recalcFinance);
})();

// ===== 帳務交易 Modal =====
function openPaymentDetail(id) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/cases.php?action=get_payment&id=' + id);
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if (!res.success) { alert(res.error || '載入失敗'); return; }
        var d = res.data;
        document.getElementById('pd_id').value = d.id;
        document.getElementById('pd_date').value = d.payment_date || '';
        document.getElementById('pd_amount').value = d.amount || 0;
        document.getElementById('pd_untaxed_amount').value = d.untaxed_amount || 0;
        document.getElementById('pd_tax_amount').value = d.tax_amount || 0;
        // 開啟編輯時，若舊資料已有稅額（不等於未稅*5%），視為手動值
        var u0 = parseFloat(d.untaxed_amount) || 0;
        var t0 = parseFloat(d.tax_amount) || 0;
        pdTaxManual = (t0 > 0 && Math.abs(t0 - Math.round(u0 * 0.05)) > 1);
        document.getElementById('pd_receipt_number').value = d.receipt_number || '';
        document.getElementById('pd_note').value = d.note || '';

        // Set selects
        var typeEl = document.getElementById('pd_type');
        typeEl.value = d.payment_type || '';
        if (!typeEl.value && d.payment_type) {
            for (var i = 0; i < typeEl.options.length; i++) {
                if (typeEl.options[i].value === d.payment_type) { typeEl.selectedIndex = i; break; }
            }
        }
        var methodEl = document.getElementById('pd_method');
        methodEl.value = d.transaction_type || '';
        if (!methodEl.value && d.transaction_type) {
            for (var i = 0; i < methodEl.options.length; i++) {
                if (methodEl.options[i].value === d.transaction_type) { methodEl.selectedIndex = i; break; }
            }
        }

        // Show current images (support JSON array or single path)
        var imgDiv = document.getElementById('pd_current_image');
        var images = [];
        if (d.image_path) {
            try { images = JSON.parse(d.image_path); } catch(e) { images = [d.image_path]; }
            if (!Array.isArray(images)) images = [d.image_path];
        }
        if (images.length > 0) {
            var imgHtml = '';
            for (var ii = 0; ii < images.length; ii++) {
                if (!images[ii]) continue;
                imgHtml += '<img src="/' + images[ii] + '" class="current-image" style="margin:2px" onclick="event.stopPropagation();openLightbox(\'/' + images[ii] + '\')">';
            }
            imgDiv.innerHTML = imgHtml;
        } else {
            imgDiv.innerHTML = '<span class="text-muted">無憑證圖片</span>';
        }
        document.getElementById('pd_image').value = '';

        document.getElementById('paymentDetailModal').style.display = 'flex';
    };
    xhr.send();
}

function closePaymentDetail() {
    document.getElementById('paymentDetailModal').style.display = 'none';
}

function savePaymentEdit() {
    var id = document.getElementById('pd_id').value;
    if (!id) return;
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('payment_id', id);
    fd.append('payment_date', document.getElementById('pd_date').value);
    fd.append('payment_type', document.getElementById('pd_type').value);
    fd.append('transaction_type', document.getElementById('pd_method').value);
    fd.append('amount', document.getElementById('pd_amount').value);
    fd.append('untaxed_amount', document.getElementById('pd_untaxed_amount').value || 0);
    fd.append('tax_amount', document.getElementById('pd_tax_amount').value || 0);
    fd.append('receipt_number', document.getElementById('pd_receipt_number').value);
    fd.append('note', document.getElementById('pd_note').value);
    var imgFiles = document.getElementById('pd_image').files;
    for (var fi = 0; fi < imgFiles.length; fi++) {
        fd.append('images[]', imgFiles[fi]);
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=edit_payment');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if (res.success) { location.reload(); }
        else { alert(res.error || '儲存失敗'); }
    };
    xhr.send(fd);
}

// ===== 施工回報 Modal =====
function openWorklogDetail(id) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/cases.php?action=get_worklog&id=' + id);
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if (!res.success) { alert(res.error || '載入失敗'); return; }
        var d = res.data;
        document.getElementById('wd_id').value = d.id;
        document.getElementById('wd_date').value = d.work_date || '';
        document.getElementById('wd_content').value = d.work_content || '';
        document.getElementById('wd_equipment').value = d.equipment_used || '';
        document.getElementById('wd_cable').value = d.cable_used || '';

        // Show existing photos
        var photoDiv = document.getElementById('wd_current_photos');
        photoDiv.innerHTML = '';
        if (d.photo_paths) {
            try {
                var photos = JSON.parse(d.photo_paths);
                if (Array.isArray(photos)) {
                    photos.forEach(function(p) {
                        var img = document.createElement('img');
                        img.src = '/' + p;
                        img.onclick = function(e) { e.stopPropagation(); openLightbox('/' + p); };
                        photoDiv.appendChild(img);
                    });
                }
            } catch(e) {}
        }
        if (!photoDiv.innerHTML) {
            photoDiv.innerHTML = '<span class="text-muted">無施工照片</span>';
        }
        document.getElementById('wd_photos').value = '';

        document.getElementById('worklogDetailModal').style.display = 'flex';
    };
    xhr.send();
}

function closeWorklogDetail() {
    document.getElementById('worklogDetailModal').style.display = 'none';
}

function goWorklogDetail() {
    var id = document.getElementById('wd_id').value;
    if (id) window.location = '/worklog.php?action=edit_manual&id=' + id + '&from_case=' + CASE_DATA.caseId;
}

function saveWorklogEdit() {
    var id = document.getElementById('wd_id').value;
    if (!id) return;
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('worklog_id', id);
    fd.append('work_date', document.getElementById('wd_date').value);
    fd.append('work_content', document.getElementById('wd_content').value);
    fd.append('equipment_used', document.getElementById('wd_equipment').value);
    fd.append('cable_used', document.getElementById('wd_cable').value);
    var files = document.getElementById('wd_photos').files;
    var photoFiles = Array.prototype.slice.call(files);

    var saveBtn = document.querySelector('[onclick*="saveWorklogEdit"]');
    var origText = saveBtn ? saveBtn.textContent : '';
    if (saveBtn) { saveBtn.textContent = photoFiles.length > 0 ? '壓縮照片中...' : '儲存中...'; saveBtn.disabled = true; }

    var doSend = function(compressed) {
        for (var j = 0; j < compressed.length; j++) {
            fd.append('photos[]', compressed[j]);
        }
        if (saveBtn) saveBtn.textContent = '上傳中...';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/cases.php?action=edit_worklog');
        xhr.onload = function() {
            var res = JSON.parse(xhr.responseText);
            if (res.success) { location.reload(); }
            else { alert(res.error || '儲存失敗'); if (saveBtn) { saveBtn.textContent = origText; saveBtn.disabled = false; } }
        };
        xhr.onerror = function() {
            alert('上傳失敗，請重試');
            if (saveBtn) { saveBtn.textContent = origText; saveBtn.disabled = false; }
        };
        xhr.send(fd);
    };

    if (photoFiles.length > 0) {
        compressImages(photoFiles).then(doSend);
    } else {
        doSend([]);
    }
}

// 施工區域 - 縣市鄉鎮連動
(function() {
    var countySelect = document.getElementById('constructionCounty');
    var districtSelect = document.getElementById('constructionDistrict');
    var hiddenInput = document.getElementById('constructionAreaHidden');
    if (!countySelect || !districtSelect || !hiddenInput) return;

    // 優先排序：台中、彰化、南投、苗栗、新竹
    var priority = ['臺中市', '彰化縣', '南投縣', '苗栗縣', '新竹縣', '新竹市'];
    var allCounties = Object.keys(twDistricts);
    var sorted = [];
    for (var p = 0; p < priority.length; p++) {
        if (allCounties.indexOf(priority[p]) !== -1) sorted.push(priority[p]);
    }
    for (var a = 0; a < allCounties.length; a++) {
        if (sorted.indexOf(allCounties[a]) === -1) sorted.push(allCounties[a]);
    }

    for (var i = 0; i < sorted.length; i++) {
        var opt = document.createElement('option');
        opt.value = sorted[i];
        opt.textContent = sorted[i];
        countySelect.appendChild(opt);
    }

    // 解析現有值（處理台/臺差異）
    var currentVal = hiddenInput.value || '';
    var currentCounty = '';
    var currentDistrict = '';
    if (currentVal) {
        // 正規化：台→臺
        var normalizedVal = currentVal.replace(/台/g, '臺');
        for (var j = 0; j < sorted.length; j++) {
            if (normalizedVal.indexOf(sorted[j]) === 0) {
                currentCounty = sorted[j];
                currentDistrict = normalizedVal.substring(sorted[j].length);
                break;
            }
        }
        if (currentCounty) {
            countySelect.value = currentCounty;
            fillDistricts(currentCounty, currentDistrict);
        }
    }

    countySelect.onchange = function() {
        fillDistricts(this.value, '');
        updateArea();
    };
    districtSelect.onchange = function() {
        updateArea();
    };

    function fillDistricts(county, preselect) {
        districtSelect.innerHTML = '<option value="">選擇鄉鎮區</option>';
        if (!county || !twDistricts[county]) return;
        var dists = twDistricts[county];
        for (var k = 0; k < dists.length; k++) {
            var opt = document.createElement('option');
            opt.value = dists[k].d;
            opt.textContent = dists[k].d;
            if (preselect && dists[k].d === preselect) opt.selected = true;
            districtSelect.appendChild(opt);
        }
    }

    function updateArea() {
        var c = countySelect.value || '';
        var d = districtSelect.value || '';
        hiddenInput.value = c + d;
        // 自動帶入施工地址
        if (c && d) {
            var addrInput = document.querySelector('input[name="address"]');
            if (addrInput) {
                addrInput.value = c + d;
                addrInput.focus();
            }
        }
    }
})();
// 全域函數供 onchange 使用
function updateDistricts() {
    var evt = new Event('change');
    document.getElementById('constructionCounty').dispatchEvent(evt);
}
function updateConstructionArea() {
    var evt = new Event('change');
    document.getElementById('constructionDistrict').dispatchEvent(evt);
}

// 指定施工時間
function updatePlannedTime() {
    var h = document.querySelector('select[name="planned_start_hour"]').value;
    var m = document.querySelector('select[name="planned_start_min"]').value;
    document.getElementById('plannedStartTime').value = (h && m) ? h + ':' + m : '';
}

// ===== 預計使用線材與配件 =====
var estMatIndex = document.querySelectorAll('.est-material-row').length;
var estSearchTimer = null;

function addEstMaterial() {
    var idx = estMatIndex++;
    var html = '<tr class="est-material-row" data-idx="' + idx + '">' +
        '<td style="position:relative">' +
        '<input type="text" name="est_materials[' + idx + '][material_name]" class="form-control est-name-input" placeholder="搜尋產品..." autocomplete="off" oninput="searchEstProduct(this,' + idx + ')">' +
        '<input type="hidden" name="est_materials[' + idx + '][product_id]" value="">' +
        '<div class="est-suggestions" id="est-sug-' + idx + '"></div>' +
        '</td>' +
        '<td><input type="text" name="est_materials[' + idx + '][model_number]" class="form-control" placeholder="型號"></td>' +
        '<td><input type="text" name="est_materials[' + idx + '][unit]" class="form-control" placeholder="單位"></td>' +
        '<td><input type="number" name="est_materials[' + idx + '][estimated_qty]" class="form-control" min="0" step="0.1"></td>' +
        '<td><button type="button" class="btn btn-sm" style="background:#e53935;color:#fff;padding:4px 8px" onclick="this.closest(\'tr\').remove()">✕</button></td>' +
        '</tr>';
    var container = document.getElementById('estMaterialsContainer');
    if (container) container.insertAdjacentHTML('beforeend', html);
}

function searchEstProduct(input, idx) {
    clearTimeout(estSearchTimer);
    var q = input.value.trim();
    var sugDiv = document.getElementById('est-sug-' + idx);
    if (!sugDiv) return;
    if (q.length < 1) { sugDiv.innerHTML = ''; sugDiv.style.display = 'none'; return; }
    estSearchTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/cases.php?action=search_products&q=' + encodeURIComponent(q));
        xhr.onload = function() {
            try {
                var products = JSON.parse(xhr.responseText);
                if (!products.length) { sugDiv.innerHTML = '<div class="est-sug-item" style="color:#999">無搜尋結果</div>'; sugDiv.style.display = 'block'; return; }
                var html = '';
                for (var i = 0; i < products.length; i++) {
                    var p = products[i];
                    html += '<div class="est-sug-item" onclick="selectEstProduct(' + idx + ',' + p.id + ',\'' + escHtml(p.name) + '\',\'' + escHtml(p.model_number || '') + '\',\'' + escHtml(p.unit || '') + '\')">';
                    html += '<strong>' + escHtml(p.name) + '</strong>';
                    if (p.model_number) html += ' <span style="color:#888">(' + escHtml(p.model_number) + ')</span>';
                    if (p.unit) html += ' <span style="color:#666;font-size:.8rem">' + escHtml(p.unit) + '</span>';
                    html += '</div>';
                }
                sugDiv.innerHTML = html;
                sugDiv.style.display = 'block';
            } catch(e) { sugDiv.innerHTML = ''; sugDiv.style.display = 'none'; }
        };
        xhr.send();
    }, 300);
}

function selectEstProduct(idx, productId, name, model, unit) {
    var row = document.querySelector('tr.est-material-row[data-idx="' + idx + '"], .est-material-row[data-idx="' + idx + '"]');
    if (!row) return;
    row.querySelector('input[name*="[material_name]"]').value = name;
    row.querySelector('input[name*="[product_id]"]').value = productId;
    row.querySelector('input[name*="[model_number]"]').value = model;
    row.querySelector('input[name*="[unit]"]').value = unit;
    var sugDiv = document.getElementById('est-sug-' + idx);
    if (sugDiv) { sugDiv.innerHTML = ''; sugDiv.style.display = 'none'; }
}

function escHtml(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// 點擊其他地方關閉建議列表
document.addEventListener('click', function(e) {
    if (!e.target.closest('.est-name-input') && !e.target.closest('.est-suggestions')) {
        var allSug = document.querySelectorAll('.est-suggestions');
        for (var i = 0; i < allSug.length; i++) { allSug[i].style.display = 'none'; }
    }
});
