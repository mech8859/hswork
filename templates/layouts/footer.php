</main>

<script src="/js/app.js"></script>
<script>
// PWA Service Worker 註冊
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js').catch(function() {});
}
</script>
<script>
// Enter 鍵跳下一格，不送出表單（搜尋框除外）
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    if (e.isComposing || e.keyCode === 229) return; // Mac 注音/中文輸入法組字中，不攔截
    var el = e.target;
    if (el.tagName === 'TEXTAREA') return; // textarea 允許換行
    if (el.tagName === 'BUTTON' || el.type === 'submit') return; // 按鈕允許送出
    if (el.tagName !== 'INPUT' && el.tagName !== 'SELECT') return;

    // 搜尋框按 Enter 直接送出表單（name 含 keyword 或 search，或表單只有少量輸入欄位）
    var form = el.closest('form');
    if (!form) return;
    if (el.name === 'keyword' || el.name === 'search' || el.name === 'contact_name' || el.type === 'search' || el.placeholder && el.placeholder.indexOf('搜尋') !== -1) {
        form.submit();
        return;
    }

    e.preventDefault();

    // 找同表單內的下一個可輸入元素
    var inputs = Array.from(form.querySelectorAll('input:not([type="hidden"]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled])'));
    var idx = inputs.indexOf(el);
    if (idx >= 0 && idx < inputs.length - 1) {
        inputs[idx + 1].focus();
    }
});
</script>
<script>
// ===== 通知系統 =====
var notifOpen = false;
function toggleNotifDropdown() {
    notifOpen = !notifOpen;
    document.getElementById('notifDropdown').style.display = notifOpen ? 'block' : 'none';
    if (notifOpen) loadNotifications();
}
function loadNotifications() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/notifications.php?action=all');
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (!res.success) return;
            updateNotifBadge(res.count);
            var list = document.getElementById('notifList');
            if (!res.data || res.data.length === 0) {
                list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--gray-400)">沒有新通知</div>';
                return;
            }
            var html = '';
            for (var i = 0; i < res.data.length; i++) {
                var n = res.data[i];
                var timeAgo = formatTimeAgo(n.created_at);
                var link = (n.link || '').replace(/'/g, "\\'");
                html += '<div class="notif-item" data-id="' + n.id + '" style="position:relative;padding:12px 32px 12px 16px;border-bottom:1px solid var(--gray-100);cursor:pointer;background:' + (n.is_read ? '#fff' : '#f0f7ff') + '">';
                html += '<div onclick="clickNotif(' + n.id + ',\'' + link + '\')" style="font-weight:' + (n.is_read ? 'normal' : '600') + ';font-size:.9rem">' + escHtml(n.title) + '</div>';
                if (n.message) html += '<div onclick="clickNotif(' + n.id + ',\'' + link + '\')" style="color:var(--gray-500);font-size:.8rem;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escHtml(n.message) + '</div>';
                html += '<div style="color:var(--gray-400);font-size:.75rem;margin-top:4px">' + timeAgo + (n.sender_name ? ' · ' + escHtml(n.sender_name) : '') + '</div>';
                html += '<button onclick="event.stopPropagation();deleteNotif(' + n.id + ')" style="position:absolute;top:8px;right:8px;background:none;border:none;color:var(--gray-400);cursor:pointer;font-size:1rem;line-height:1;padding:2px" title="刪除">&times;</button>';
                html += '</div>';
            }
            list.innerHTML = html;
        } catch(e) {}
    };
    xhr.send();
}
function clickNotif(id, link) {
    var fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', getCSRF());
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/notifications.php?action=read');
    xhr.onload = function() {
        if (link) window.open(link, '_blank');
        loadNotifications();
        refreshNotifCount();
    };
    xhr.send(fd);
}
function deleteNotif(id) {
    var fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', getCSRF());
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/notifications.php?action=delete');
    xhr.onload = function() { loadNotifications(); refreshNotifCount(); };
    xhr.send(fd);
}
function deleteAllNotif() {
    if (!confirm('確定刪除全部通知？')) return;
    var fd = new FormData();
    fd.append('csrf_token', getCSRF());
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/notifications.php?action=delete_all');
    xhr.onload = function() { loadNotifications(); refreshNotifCount(); };
    xhr.send(fd);
}
function markAllNotifRead() {
    var fd = new FormData();
    fd.append('csrf_token', getCSRF());
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/notifications.php?action=read_all');
    xhr.onload = function() { loadNotifications(); refreshNotifCount(); };
    xhr.send(fd);
}
function refreshNotifCount() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/notifications.php?action=unread');
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) updateNotifBadge(res.count);
        } catch(e) {}
    };
    xhr.send();
}
function updateNotifBadge(count) {
    var badge = document.getElementById('notifBadge');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'inline-block';
    } else {
        badge.style.display = 'none';
    }
}
function getCSRF() {
    var el = document.querySelector('input[name="csrf_token"]');
    return el ? el.value : '';
}
function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
function formatTimeAgo(dateStr) {
    var d = new Date(dateStr.replace(/-/g, '/'));
    var now = new Date();
    var diff = Math.floor((now - d) / 1000);
    if (diff < 60) return '剛剛';
    if (diff < 3600) return Math.floor(diff / 60) + ' 分鐘前';
    if (diff < 86400) return Math.floor(diff / 3600) + ' 小時前';
    if (diff < 604800) return Math.floor(diff / 86400) + ' 天前';
    return dateStr.substring(0, 10);
}
// 頁面載入和定時刷新
if (document.getElementById('notifBadge')) {
    refreshNotifCount();
    setInterval(refreshNotifCount, 60000);
}
// 點外面關閉
document.addEventListener('click', function(e) {
    if (notifOpen && !e.target.closest('#notifBell') && !e.target.closest('#notifDropdown')) {
        notifOpen = false;
        document.getElementById('notifDropdown').style.display = 'none';
    }
});
</script>
<script>
// ===== 全站圖片壓縮 =====
// 壓縮單張圖片：compressImage(file, maxSize, quality) → Promise<File>
function compressImage(file, maxSize, quality) {
    maxSize = maxSize || 1600;
    quality = quality || 0.7;
    return new Promise(function(resolve) {
        // 非圖片直接回傳（含 HEIC 等不支援格式）
        if (!file.type.match(/^image\/(jpeg|jpg|png|gif|webp|bmp)$/i)) {
            resolve(file);
            return;
        }
        // 小於 300KB 不壓縮
        if (file.size < 300 * 1024) {
            resolve(file);
            return;
        }
        // 超時保護：5 秒內壓不完就用原檔
        var done = false;
        var timer = setTimeout(function() { if (!done) { done = true; resolve(file); } }, 5000);
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = new Image();
            img.onload = function() {
                var w = img.width, h = img.height;
                // 不需要縮小的也壓 quality
                if (w > maxSize || h > maxSize) {
                    if (w > h) { h = Math.round(h * maxSize / w); w = maxSize; }
                    else { w = Math.round(w * maxSize / h); h = maxSize; }
                }
                var canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);
                canvas.toBlob(function(blob) {
                    if (done) return;
                    done = true; clearTimeout(timer);
                    if (!blob) { resolve(file); return; }
                    // 壓縮後更大就用原檔
                    if (blob.size >= file.size) { resolve(file); return; }
                    var compressed = new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg', lastModified: Date.now() });
                    resolve(compressed);
                }, 'image/jpeg', quality);
            };
            img.onerror = function() { if (!done) { done = true; clearTimeout(timer); resolve(file); } };
            img.src = e.target.result;
        };
        reader.onerror = function() { if (!done) { done = true; clearTimeout(timer); resolve(file); } };
        reader.readAsDataURL(file);
    });
}
// 壓縮多張圖片：compressImages(fileList) → Promise<File[]>
function compressImages(files) {
    var promises = [];
    for (var i = 0; i < files.length; i++) {
        promises.push(compressImage(files[i]));
    }
    return Promise.all(promises);
}
// 壓縮 FormData 中的圖片欄位：compressFormData(formData, fieldNames) → Promise<FormData>
function compressFormData(fd, fieldNames) {
    if (!fieldNames) fieldNames = ['file', 'photos[]', 'image', 'images[]', 'photos', 'files[]', 'attachment[]'];
    var tasks = [];
    // FormData 無法直接遍歷修改，收集後重建
    var entries = [];
    var iter = fd.entries();
    var entry;
    while (!(entry = iter.next()).done) {
        entries.push({ key: entry.value[0], value: entry.value[1] });
    }
    return Promise.all(entries.map(function(e) {
        if (e.value instanceof File && e.value.type.match(/^image\//i)) {
            return compressImage(e.value).then(function(f) { e.value = f; return e; });
        }
        return Promise.resolve(e);
    })).then(function(results) {
        var newFd = new FormData();
        results.forEach(function(e) { newFd.append(e.key, e.value); });
        return newFd;
    });
}
</script>
<?php if (!empty($extraJs)): ?>
    <?php foreach ($extraJs as $js): ?>
        <script src="<?= e($js) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
<script>
// 全域發票號碼驗證（2碼英文 + 8碼數字 = 10碼）
function formatInvoiceNumber(el) {
    // 自動轉大寫，移除非英數字元
    el.value = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 10);
}
function validateInvoiceNumber(el) {
    var v = el.value.trim();
    if (!v) return;
    var valid = /^[A-Z]{2}\d{8}$/.test(v);
    if (!valid) {
        el.style.borderColor = '#e53935';
        if (v.length > 0 && v.length < 10) {
            el.setCustomValidity('發票號碼格式：2碼英文 + 8碼數字，共10碼');
        } else if (v.length === 10 && !/^[A-Z]{2}/.test(v)) {
            el.setCustomValidity('前2碼必須是英文字母');
        } else if (v.length === 10 && !/\d{8}$/.test(v)) {
            el.setCustomValidity('後8碼必須是數字');
        } else {
            el.setCustomValidity('發票號碼格式：2碼英文 + 8碼數字，共10碼');
        }
        el.reportValidity();
    } else {
        el.style.borderColor = '';
        el.setCustomValidity('');
    }
}

// 全域金額千分位格式化
// 對所有 .fmt-number 的 input，顯示千分位，送出時還原純數字
(function(){
    function addCommas(n) {
        if (n === '' || n === null || n === undefined) return '';
        var num = String(n).replace(/,/g, '');
        if (isNaN(num) || num === '') return n;
        var parts = num.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.join('.');
    }
    function stripCommas(s) { return String(s).replace(/,/g, ''); }

    // 頁面載入時格式化所有金額欄位
    document.addEventListener('DOMContentLoaded', function(){
        var inputs = document.querySelectorAll('input[type="number"]');
        for (var i = 0; i < inputs.length; i++) {
            var inp = inputs[i];
            // 只格式化金額相關（排除 quantity、tax_rate 等小數字）
            var name = inp.name || '';
            if (name.indexOf('qty') !== -1 || name.indexOf('rate') !== -1 || name.indexOf('quantity') !== -1) continue;
            var val = inp.value;
            if (val && Math.abs(Number(val)) >= 1000) {
                inp.type = 'text';
                inp.setAttribute('inputmode', 'numeric');
                inp.dataset.isAmount = '1';
                inp.value = addCommas(val);
                inp.addEventListener('focus', function(){ this.value = stripCommas(this.value); });
                inp.addEventListener('blur', function(){ this.value = addCommas(this.value); });
            }
        }
        // 表單送出前還原所有千分位
        var forms = document.querySelectorAll('form');
        for (var j = 0; j < forms.length; j++) {
            forms[j].addEventListener('submit', function(){
                var amtInputs = this.querySelectorAll('input[data-is-amount="1"]');
                for (var k = 0; k < amtInputs.length; k++) {
                    amtInputs[k].value = stripCommas(amtInputs[k].value);
                }
            });
        }
    });
})();
</script>

<!-- ===== 全站通用 Lightbox & 檔案 Modal（PWA 內部檢視）===== -->
<div id="hsLightbox" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);z-index:99999;align-items:center;justify-content:center;cursor:pointer">
    <span id="hsLbClose" style="position:absolute;top:16px;right:16px;color:#fff;font-size:2.5rem;cursor:pointer;width:48px;height:48px;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.4);border-radius:50%;line-height:1;z-index:100000">&times;</span>
    <span id="hsLbPrev" style="position:absolute;top:50%;left:10px;transform:translateY(-50%);color:#fff;font-size:2.5rem;cursor:pointer;padding:16px 12px;background:rgba(0,0,0,.4);border-radius:8px;user-select:none;z-index:100000">&lsaquo;</span>
    <span id="hsLbNext" style="position:absolute;top:50%;right:10px;transform:translateY(-50%);color:#fff;font-size:2.5rem;cursor:pointer;padding:16px 12px;background:rgba(0,0,0,.4);border-radius:8px;user-select:none;z-index:100000">&rsaquo;</span>
    <img id="hsLbImg" src="" alt="預覽" style="max-width:90%;max-height:90%;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.5)">
    <span id="hsLbCounter" style="position:absolute;bottom:20px;left:50%;transform:translateX(-50%);color:#fff;font-size:.9rem;background:rgba(0,0,0,.4);padding:4px 12px;border-radius:12px;z-index:100000"></span>
</div>
<div id="hsFileModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:#fff;z-index:99999;flex-direction:column">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:#1a73e8;color:#fff;flex-shrink:0">
        <span id="hsFileTitle" style="font-weight:600;font-size:.95rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;margin-right:12px"></span>
        <div style="display:flex;gap:8px;align-items:center">
            <a id="hsFileDownload" href="" download style="background:rgba(255,255,255,.2);color:#fff;padding:6px 14px;border-radius:6px;text-decoration:none;font-size:.85rem">下載</a>
            <span onclick="hsCloseFile()" style="color:#fff;font-size:1.8rem;cursor:pointer;width:36px;height:36px;display:flex;align-items:center;justify-content:center;line-height:1">&times;</span>
        </div>
    </div>
    <iframe id="hsFileFrame" src="" frameborder="0" style="flex:1;width:100%;border:0"></iframe>
</div>
<script>
// ===== 全站通用 Lightbox / File Modal =====
// 用法：
//   <a onclick="hsOpenFile('/path/to/file.pdf','檔名.pdf')">檔名.pdf</a>
//   <img onclick="hsOpenImage(this.src)"> 或 onclick="hsOpenImage('/path/to.jpg')"
// 自動收集頁面上所有 .hs-photo 元素或同 group 圖片做切換
(function() {
    var images = [], idx = 0;

    window.hsOpenImage = function(src, group) {
        // 收集同類圖片：優先用 group 屬性，否則收所有 .hs-photo / [onclick*="hsOpenImage"]
        images = [];
        var sel = group ? '[data-hs-group="' + group + '"]' : '.hs-photo, img[onclick*="hsOpenImage"], a[onclick*="hsOpenImage"]';
        document.querySelectorAll(sel).forEach(function(el) {
            var s = '';
            if (el.tagName === 'IMG') s = el.src;
            var oc = el.getAttribute('onclick') || '';
            var m = oc.match(/hsOpenImage\(['"]([^'"]+)['"]/);
            if (m) s = m[1];
            if (s && images.indexOf(s) === -1) images.push(s);
        });
        if (images.length === 0) images = [src];
        idx = images.indexOf(src);
        if (idx < 0) idx = 0;
        showImage();
        document.getElementById('hsLightbox').style.display = 'flex';
    };
    window.hsCloseImage = function() {
        document.getElementById('hsLightbox').style.display = 'none';
        document.getElementById('hsLbImg').src = '';
    };
    window.hsLbNav = function(dir) {
        idx += dir;
        if (idx < 0) idx = images.length - 1;
        if (idx >= images.length) idx = 0;
        showImage();
    };
    function showImage() {
        document.getElementById('hsLbImg').src = images[idx];
        var c = document.getElementById('hsLbCounter');
        var p = document.getElementById('hsLbPrev'), n = document.getElementById('hsLbNext');
        if (images.length > 1) {
            c.textContent = (idx + 1) + ' / ' + images.length;
            c.style.display = 'block';
            p.style.display = 'flex';
            n.style.display = 'flex';
        } else {
            c.style.display = 'none';
            p.style.display = 'none';
            n.style.display = 'none';
        }
    }

    window.hsOpenFile = function(src, name) {
        document.getElementById('hsFileTitle').textContent = name || '檔案';
        document.getElementById('hsFileDownload').href = src;
        document.getElementById('hsFileFrame').src = src;
        document.getElementById('hsFileModal').style.display = 'flex';
    };
    window.hsCloseFile = function() {
        document.getElementById('hsFileModal').style.display = 'none';
        document.getElementById('hsFileFrame').src = '';
    };

    // 綁定關閉/切換按鈕
    var lb = document.getElementById('hsLightbox');
    lb.addEventListener('click', function(e) { if (e.target === lb) hsCloseImage(); });
    document.getElementById('hsLbClose').addEventListener('click', function(e) { e.stopPropagation(); hsCloseImage(); });
    document.getElementById('hsLbPrev').addEventListener('click', function(e) { e.stopPropagation(); hsLbNav(-1); });
    document.getElementById('hsLbNext').addEventListener('click', function(e) { e.stopPropagation(); hsLbNav(1); });
    document.getElementById('hsLbImg').addEventListener('click', function(e) { e.stopPropagation(); });

    // 鍵盤
    document.addEventListener('keydown', function(e) {
        if (lb.style.display === 'flex') {
            if (e.key === 'Escape') hsCloseImage();
            if (e.key === 'ArrowLeft') hsLbNav(-1);
            if (e.key === 'ArrowRight') hsLbNav(1);
            return;
        }
        if (document.getElementById('hsFileModal').style.display === 'flex' && e.key === 'Escape') hsCloseFile();
    });

    // 觸控滑動
    var sx = 0, sy = 0;
    lb.addEventListener('touchstart', function(e) { sx = e.changedTouches[0].screenX; sy = e.changedTouches[0].screenY; }, {passive:true});
    lb.addEventListener('touchend', function(e) {
        var dx = e.changedTouches[0].screenX - sx;
        var dy = e.changedTouches[0].screenY - sy;
        if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;
        if (Math.abs(dx) > Math.abs(dy)) {
            if (dx > 0) hsLbNav(-1); else hsLbNav(1);
        } else {
            hsCloseImage();
        }
    }, {passive:true});
})();
</script>
</body>
</html>
