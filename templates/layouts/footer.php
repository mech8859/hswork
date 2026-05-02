</main>

<script>
// 動態計算 .page-sticky-head 高度，寫入 CSS 變數 --table-sticky-top，讓資料表 thead 能 sticky 在下方
(function() {
    function updateStickyOffset() {
        var head = document.querySelector('.page-sticky-head');
        if (!head) {
            document.documentElement.style.setProperty('--table-sticky-top', '56px');
            return;
        }
        var h = head.offsetHeight + 56;
        document.documentElement.style.setProperty('--table-sticky-top', h + 'px');
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateStickyOffset);
    } else {
        updateStickyOffset();
    }
    window.addEventListener('resize', updateStickyOffset);
    // 監測 sticky head 高度變化（例如切換展開/收合）
    if (window.ResizeObserver) {
        document.addEventListener('DOMContentLoaded', function() {
            var head = document.querySelector('.page-sticky-head');
            if (head) new ResizeObserver(updateStickyOffset).observe(head);
        });
    }
})();
</script>
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
    <div id="hsLbImgWrap" style="display:flex;align-items:center;justify-content:center;width:90%;height:90%;overflow:hidden"><img id="hsLbImg" src="" alt="預覽" style="max-width:100%;max-height:100%;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.5)"></div>
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

        // 手機：跳到獨立檢視頁（支援雙指縮放）
        if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
            var params = 'idx=' + idx + '&images=' + encodeURIComponent(JSON.stringify(images)) + '&back=' + encodeURIComponent(location.href);
            location.href = '/photo_view.php?' + params;
            return;
        }

        // 電腦：維持原有 lightbox
        showImage();
        document.getElementById('hsLightbox').style.display = 'flex';
        lbPzInit();
    };
    window.hsCloseImage = function() {
        lbPzDestroy();
        document.getElementById('hsLightbox').style.display = 'none';
        document.getElementById('hsLbImg').src = '';
    };
    window.hsLbNav = function(dir) {
        lbPzDestroy();
        idx += dir;
        if (idx < 0) idx = images.length - 1;
        if (idx >= images.length) idx = 0;
        showImage();
        lbPzInit();
    };

    // Panzoom 初始化/銷毀
    var lbPzInstance = null;
    function lbPzInit() {
        if (typeof Panzoom === 'undefined') return;
        var img = document.getElementById('hsLbImg');
        // 等圖片載入完成後才初始化 panzoom
        if (img.complete && img.naturalWidth > 0) {
            lbPzCreate(img);
        } else {
            img.onload = function() { lbPzCreate(img); };
        }
    }
    function lbPzCreate(img) {
        if (lbPzInstance) return;
        lbPzInstance = Panzoom(img, { maxScale: 6, minScale: 1, startScale: 1, startX: 0, startY: 0, contain: 'inside', cursor: 'default' });
        lbPzInstance.reset();
        var wrap = document.getElementById('hsLbImgWrap');
        wrap.addEventListener('wheel', lbPzInstance.zoomWithWheel);
    }
    function lbPzDestroy() {
        var img = document.getElementById('hsLbImg');
        if (img) img.onload = null;
        if (lbPzInstance) { try { lbPzInstance.reset(); lbPzInstance.destroy(); } catch(e) {} lbPzInstance = null; }
    }

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
        // 手機 + 圖片檔：跳到獨立檢視頁（支援雙指縮放）
        var isMobile = ('ontouchstart' in window || navigator.maxTouchPoints > 0);
        var imgExts = ['.jpg','.jpeg','.png','.gif','.webp','.bmp'];
        var ext = src.toLowerCase().split('?')[0];
        var isImg = false;
        for (var i = 0; i < imgExts.length; i++) { if (ext.indexOf(imgExts[i]) !== -1) { isImg = true; break; } }
        if (isMobile && isImg) {
            location.href = '/photo_view.php?src=' + encodeURIComponent(src) + '&back=' + encodeURIComponent(location.href);
            return;
        }
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

// ============================================================
// 全站日期 input：轉成 type=text 並自動格式化 YYYY/MM/DD
// 規則：使用者只能輸入數字，每滿 4/2/2 位數自動加 /，年份限 4 位
// 表單送出時自動轉回 YYYY-MM-DD（後端維持 YYYY-MM-DD 格式）
// ============================================================
(function() {
    function formatDigits(digits) {
        digits = (digits || '').replace(/\D/g, '').slice(0, 8);
        if (digits.length <= 4) return digits;
        if (digits.length <= 6) return digits.slice(0, 4) + '/' + digits.slice(4);
        return digits.slice(0, 4) + '/' + digits.slice(4, 6) + '/' + digits.slice(6);
    }
    function enhanceDateInput(input) {
        if (input.dataset.dateEnhanced) return;
        input.dataset.dateEnhanced = '1';

        // 把現有值（YYYY-MM-DD）轉成 YYYY/MM/DD 顯示
        var orig = input.value || '';
        var display = orig.replace(/-/g, '/');

        // 切到 text，保留 name/id/required/class 等屬性
        input.type = 'text';
        input.value = display;
        input.setAttribute('maxlength', '10');
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('autocomplete', 'off');
        if (!input.getAttribute('placeholder')) input.setAttribute('placeholder', 'YYYY/MM/DD');
        if (!input.getAttribute('pattern')) input.setAttribute('pattern', '\\d{4}/\\d{1,2}/\\d{1,2}');

        // 即時格式化：移除非數字，加 /
        input.addEventListener('input', function() {
            var v = input.value;
            input.value = formatDigits(v);
        });
        // 失焦時補 0：MM 個位數補 0；DD 個位數補 0
        input.addEventListener('blur', function() {
            var v = input.value;
            var parts = v.split('/');
            if (parts.length === 3) {
                if (parts[1].length === 1) parts[1] = '0' + parts[1];
                if (parts[2].length === 1) parts[2] = '0' + parts[2];
                input.value = parts.join('/');
            }
        });
    }
    function enhanceFormSubmit(form) {
        if (form.dataset.dateEnhancedSubmit) return;
        form.dataset.dateEnhancedSubmit = '1';
        form.addEventListener('submit', function() {
            form.querySelectorAll('input[data-date-enhanced]').forEach(function(inp) {
                if (inp.value) {
                    // 轉 YYYY/MM/DD → YYYY-MM-DD
                    inp.value = inp.value.replace(/\//g, '-');
                }
            });
        }, true);
    }
    function scanAndAttach(root) {
        (root || document).querySelectorAll('input[type="date"]').forEach(enhanceDateInput);
        // 為包含日期 input 的 form 掛 submit handler
        (root || document).querySelectorAll('form').forEach(function(f) {
            if (f.querySelector('input[data-date-enhanced]')) enhanceFormSubmit(f);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { scanAndAttach(); });
    } else {
        scanAndAttach();
    }
    if (window.MutationObserver) {
        var mo = new MutationObserver(function(muts) {
            muts.forEach(function(m) {
                m.addedNodes.forEach(function(n) {
                    if (n.nodeType !== 1) return;
                    if (n.matches && n.matches('input[type="date"]')) enhanceDateInput(n);
                    if (n.querySelectorAll) scanAndAttach(n);
                });
            });
        });
        if (document.body) mo.observe(document.body, {childList: true, subtree: true});
    }
})();

// ============================================================
// 新增/編輯傳票 form：按 Enter 跳下一個輸入欄（不送出）
// ============================================================
(function() {
    function isJournalForm(form) {
        if (!form) return false;
        var inp = form.querySelector('#fldVoucherDate, #fldVoucherNumber');
        return !!inp;
    }
    function getFocusable(form) {
        return Array.prototype.slice.call(form.querySelectorAll(
            'input:not([type=hidden]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])'
        )).filter(function(el) { return el.offsetParent !== null; });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        var t = e.target;
        if (!t || !t.tagName) return;
        if (t.tagName === 'TEXTAREA') return;
        if (t.type === 'submit' || t.type === 'button') return;
        var form = t.closest && t.closest('form');
        if (!isJournalForm(form)) return;
        e.preventDefault();
        var arr = getFocusable(form);
        var idx = arr.indexOf(t);
        if (idx < 0) return;
        for (var i = idx + 1; i < arr.length; i++) {
            if (arr[i].type !== 'submit' && arr[i].type !== 'button') {
                arr[i].focus();
                if (arr[i].select) try { arr[i].select(); } catch (e2) {}
                break;
            }
        }
    });
})();
</script>
</body>
</html>
