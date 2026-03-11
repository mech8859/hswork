/**
 * 弱電工程排程系統 - 前端共用JS
 */
document.addEventListener('DOMContentLoaded', function() {

    // --- Sidebar Toggle ---
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('open');
        });

        // 點擊內容區域關閉 sidebar (手機)
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 768 && sidebar.classList.contains('open')) {
                if (!sidebar.contains(e.target) && e.target !== menuToggle) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    // --- Auto-dismiss alerts ---
    document.querySelectorAll('.alert').forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity .3s';
            setTimeout(function() { alert.remove(); }, 300);
        }, 5000);
    });

    // --- AJAX helper ---
    window.hswork = {
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="csrf_token"]')?.value
            || '',

        async fetch(url, options = {}) {
            const defaults = {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            };
            if (options.body && !(options.body instanceof FormData)) {
                defaults.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            }
            const config = { ...defaults, ...options };
            config.headers = { ...defaults.headers, ...options.headers };
            const response = await fetch(url, config);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        },

        async post(url, data) {
            return this.fetch(url, { method: 'POST', body: data });
        },
    };
});
