<div class="d-flex justify-between align-center mb-2">
    <h2>MOA 同步設定</h2>
    <div class="d-flex gap-1">
        <a href="/moa_attendance.php" class="btn btn-outline btn-sm">返回明細</a>
        <a href="/moa_attendance.php?action=import" class="btn btn-outline btn-sm">Excel 匯入</a>
        <a href="/moa_attendance.php?action=employees" class="btn btn-outline btn-sm">員工對照</a>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header">同步狀態</div>
    <div style="padding:14px 16px">
        <table class="table table-sm" style="margin:0">
            <tr><th style="width:140px">最後同步時間</th><td><?= e($settings['last_sync_at'] ?? '— 尚未同步 —') ?></td></tr>
            <tr><th>狀態</th><td>
                <?php if (($settings['last_sync_status'] ?? '') === 'success'): ?>
                <span style="color:#2e7d32;font-weight:600">✓ 成功</span>
                <?php elseif (($settings['last_sync_status'] ?? '') === 'failed'): ?>
                <span style="color:#c62828;font-weight:600">✗ 失敗</span>
                <?php else: ?>
                —
                <?php endif; ?>
            </td></tr>
            <tr><th>最近訊息</th><td><?= e($settings['last_sync_message'] ?? '') ?></td></tr>
            <tr><th>Cookie 設定時間</th><td><?= e($settings['cookie_set_at'] ?? '— 尚未設定 —') ?></td></tr>
        </table>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header">手動同步</div>
    <div style="padding:14px 16px">
        <form method="POST" action="/moa_attendance.php?action=sync_now" class="d-flex flex-wrap align-center gap-1">
            <?= csrf_field() ?>
            <label>日期</label>
            <input type="date" name="date_from" value="<?= e(date('Y-m-d', strtotime('-1 day'))) ?>" class="form-control" style="max-width:160px" required>
            <span>～</span>
            <input type="date" name="date_to" value="<?= e(date('Y-m-d')) ?>" class="form-control" style="max-width:160px" required>
            <button type="submit" class="btn btn-primary" onclick="return confirm('將立即向 MOA 拉取打卡記錄並寫入。確定？')">立即同步</button>
        </form>
        <p class="text-muted" style="font-size:.8rem;margin-top:8px">同步會對每位員工發一次 API 請求。建議區間不超過 7 天。</p>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header">設定</div>
    <div style="padding:14px 16px">
        <form method="POST" action="/moa_attendance.php?action=sync_config">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group" style="max-width:160px">
                    <label>MOA 企業號</label>
                    <input type="number" name="moa_company_id" value="<?= e($settings['moa_company_id']) ?>" class="form-control" required>
                </div>
                <div class="form-group" style="max-width:160px">
                    <label>MOA Org ID</label>
                    <input type="number" name="moa_org_id" value="<?= e($settings['moa_org_id']) ?>" class="form-control" required>
                    <div class="text-muted" style="font-size:.75rem">URL 路徑中的 /kaoqin/<b>200021</b>/web/</div>
                </div>
            </div>
            <div class="form-group">
                <label>MOA 登入 Cookie <span style="color:#999;font-weight:normal;font-size:.85rem">（留空表示沿用既有設定）</span></label>
                <textarea name="moa_cookie" class="form-control" rows="4" placeholder="貼上整串 cookie，例如：JSESSIONID=ABC; xxxx=yyy; ..." style="font-family:monospace;font-size:.8rem"></textarea>
                <div class="text-muted" style="font-size:.85rem;margin-top:6px;line-height:1.6">
                    <strong>怎麼取得 Cookie？</strong><br>
                    1. 在新分頁登入 https://moa.micito.net/manage/<br>
                    2. F12 開啟開發者工具 → Network → 點任一個 <code>/kaoqin/...</code> 請求<br>
                    3. Headers 區找 <code>Cookie</code> 那一行 → 整段複製貼上<br>
                    4. 約一週需重設一次（cookie 過期後 sync 會回 code=305）
                </div>
            </div>
            <button type="submit" class="btn btn-primary">儲存設定</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">自動同步（cron）</div>
    <div style="padding:14px 16px;font-size:.9rem;line-height:1.7">
        <p>排程用 URL（每日 10:00 拉前一天到今天的打卡）：</p>
        <?php
        $_cronUrl = 'https://hswork.com.tw/moa_attendance.php?action=cron_sync&token=' . e($settings['cron_token'] ?? '');
        ?>
        <input type="text" readonly value="<?= $_cronUrl ?>" class="form-control" style="font-family:monospace;font-size:.8rem;background:#f5f5f5" onclick="this.select()">
        <div class="d-flex gap-1 mt-1">
            <form method="POST" action="/moa_attendance.php?action=regen_token" onsubmit="return confirm('重新產生 token 後舊 URL 失效，需重新設定排程。確定？')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">🔄 重新產生 token</button>
            </form>
        </div>
        <p style="margin-top:14px"><strong>排程設定方式（擇一）：</strong></p>
        <ul style="margin-left:20px">
            <li><strong>cron-job.org</strong> 或其他免費排程服務 → 新增 job → 貼上 URL → 排程「每日 10:00」</li>
            <li>主機支援 cron：<pre style="background:#f5f5f5;padding:6px;border-radius:4px;font-size:.78rem;margin:4px 0">0 10 * * * curl -A "Mozilla/5.0" -s "<?= e($_cronUrl) ?>" &gt; /dev/null</pre></li>
            <li>內部 Mac/Server cron：同上</li>
        </ul>
        <p style="color:#999;font-size:.8rem">⚠ MOA cookie 若過期，自動同步會失敗（last_sync_status=failed）。建議搭配定期檢查狀態。</p>
    </div>
</div>
