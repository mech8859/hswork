<?php
/**
 * 共用輔助函式
 */

/**
 * 密碼強度驗證：8碼以上，含大寫、小寫、數字
 */
function validate_password($pw)
{
    if (strlen($pw) < 8) return '密碼至少 8 碼';
    if (!preg_match('/[A-Z]/', $pw)) return '密碼需包含至少一個大寫英文';
    if (!preg_match('/[a-z]/', $pw)) return '密碼需包含至少一個小寫英文';
    if (!preg_match('/[0-9]/', $pw)) return '密碼需包含至少一個數字';
    return null;
}

/**
 * HTML 跳脫輸出
 */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * 產生 CSRF hidden input
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(Session::getCsrfToken()) . '">';
}

/**
 * 驗證 CSRF token
 */
function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return Session::verifyCsrf($token);
}

/**
 * 返回按鈕：自動偵測來源頁面，返回原處
 * @param string $defaultUrl 預設返回網址（無來源時的 fallback）
 * @param string $defaultLabel 預設按鈕文字
 * @param string $class 按鈕 CSS class
 * @return string HTML
 */
function back_button($defaultUrl, $defaultLabel = '返回列表', $class = 'btn btn-outline btn-sm')
{
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $from = isset($_GET['from']) ? $_GET['from'] : '';
    $backUrl = $defaultUrl;
    $backLabel = $defaultLabel;

    // 優先用 GET 參數
    if ($from !== '') {
        $backUrl = $from;
        // 根據 URL 判斷標籤
        if (strpos($from, 'reports.php') !== false) {
            $backLabel = '返回報表';
        } elseif (strpos($from, 'schedule.php') !== false) {
            $backLabel = '返回排工';
        }
    } elseif ($referer !== '' && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
        // Referer 是同站的，用 referer
        $path = parse_url($referer, PHP_URL_PATH);
        $query = parse_url($referer, PHP_URL_QUERY);
        $backUrl = $path . ($query ? '?' . $query : '');
        // 不是同模組的才改標籤
        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (basename($path) !== basename($currentPath)) {
            if (strpos($path, 'reports.php') !== false) {
                $backLabel = '返回報表';
            } elseif (strpos($path, 'schedule.php') !== false) {
                $backLabel = '返回排工';
            } elseif (strpos($path, 'index.php') !== false || $path === '/') {
                $backLabel = '返回首頁';
            }
        }
    }

    // 如果是從別的頁面用 target="_blank" 開的，關閉分頁即可回到原頁
    $isNewTab = ($referer !== '' && strpos($referer, $_SERVER['HTTP_HOST']) !== false
                 && basename(parse_url($referer, PHP_URL_PATH)) !== basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));

    if ($isNewTab) {
        return '<a href="' . e($backUrl) . '" class="' . e($class) . '" onclick="if(window.opener||window.history.length<=1){window.close();return false;}">' . e($backLabel) . '</a>';
    }
    return '<a href="' . e($backUrl) . '" class="' . e($class) . '">' . e($backLabel) . '</a>';
}

/**
 * 重導向
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * JSON 回應
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 取得所有角色（優先從 DB，fallback 到 config）
 * @return array  role_key => role_label
 */
function get_dynamic_roles()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $db = Database::getInstance();
        $stmt = $db->query('SELECT role_key, role_label FROM system_roles WHERE is_active = 1 ORDER BY sort_order, role_key');
        $roles = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $roles[$row['role_key']] = $row['role_label'];
        }
        if (!empty($roles)) {
            $cache = $roles;
            return $cache;
        }
    } catch (Exception $e) {
        // table might not exist yet
    }
    $config = require __DIR__ . '/../config/app.php';
    $cache = $config['roles'];
    return $cache;
}

/**
 * 取得角色中文名稱
 */
function role_name(string $role): string
{
    $roles = get_dynamic_roles();
    return isset($roles[$role]) ? $roles[$role] : $role;
}

/**
 * 格式化日期
 */
function format_date(?string $date, string $format = 'Y/m/d'): string
{
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * 格式化日期時間
 */
function format_datetime(?string $datetime, string $format = 'Y/m/d H:i'): string
{
    if (!$datetime) return '';
    return date($format, strtotime($datetime));
}

/**
 * 檢查是否為 AJAX 請求
 */
function is_ajax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * 取得排工條件缺少項目的提示訊息
 */
function get_readiness_warnings(array $readiness, $caseType = 'new_install'): array
{
    $warnings = [];
    // 報價單和現場照片只有新案才強制要求
    if ($caseType === 'new_install') {
        if (empty($readiness['has_quotation']))        $warnings[] = '缺少報價單';
        if (empty($readiness['has_site_photos']) && empty($readiness['no_photo_allowed']))
            $warnings[] = '缺少現場照片';
    }
    if (empty($readiness['has_amount_confirmed']))  $warnings[] = '金額尚未確認';
    if (empty($readiness['has_site_info']))         $warnings[] = '現場資料未備齊';
    return $warnings;
}

/**
 * 產生案件編號
 */
function generate_case_number(string $branchCode): string
{
    return $branchCode . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
}

/**
 * 統一自動編號產生
 * 格式: {prefix}{sep}{date}{sep}{sequence}
 * 支援: 純數字, 含年份, 含年月, 含年月日, 含前綴
 *
 * @param string $module 模組 key (cases, quotations, receivables 等)
 * @return string 產生的編號
 */
function generate_doc_number($module, $forDate = null)
{
    $db = Database::getInstance();

    // 別名映射
    $aliasMap = array('payments_out' => 'payments');
    $seqModule = isset($aliasMap[$module]) ? $aliasMap[$module] : $module;

    // 取得設定（使用 FOR UPDATE 鎖定避免併發）
    $ownTransaction = !$db->inTransaction();
    if ($ownTransaction) $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT * FROM number_sequences WHERE module = ? FOR UPDATE");
        $stmt->execute(array($seqModule));
        $seq = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$seq) {
            if ($ownTransaction) $db->rollBack();
            // 沒設定就用 fallback
            return strtoupper($module) . '-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        }

        $prefix = $seq['prefix'];
        $dateFormat = $seq['date_format'];
        $sep = $seq['separator'];
        $digits = (int)$seq['seq_digits'];

        // 使用指定日期或今天
        $ts = $forDate ? strtotime($forDate) : time();

        // 計算 reset key（依日期格式）
        $resetKey = '';
        if (!empty($dateFormat)) {
            $resetKey = date($dateFormat, $ts);
        } else {
            $resetKey = 'ALL'; // 無日期 → 永不重設
        }

        // 依指定日期查已有幾筆（不用 last_sequence，改用實際資料計算）
        $tableName = $module;
        $numberCol = 'voucher_number';
        // 不同模組的表名和欄位名對應
        $tableMap = array(
            'cases' => array('cases', 'case_number'),
            'customers' => array('customers', 'customer_no'),
            'journal_entries' => array('journal_entries', 'voucher_number'),
            'purchase_orders' => array('purchase_orders', 'po_number'),
            'requisitions' => array('requisitions', 'req_number'),
            'stock_outs' => array('stock_outs', 'so_number'),
            'stock_ins' => array('stock_ins', 'si_number'),
            'receivables' => array('receivables', 'invoice_number'),
            'receipts' => array('receipts', 'receipt_number'),
            'payables' => array('payables', 'payable_number'),
            'payments' => array('payments_out', 'payment_number'),
            'payments_out' => array('payments_out', 'payment_number'),
            'bank_transactions' => array('bank_transactions', 'transaction_number'),
            'five_star_reviews' => array('five_star_reviews', 'review_number'),
        );
        if (isset($tableMap[$module])) {
            $tableName = $tableMap[$module][0];
            $numberCol = $tableMap[$module][1];
        }

        $lpParts2 = array();
        if ($prefix !== '') $lpParts2[] = $prefix;
        if (!empty($dateFormat)) $lpParts2[] = date($dateFormat, $ts);
        $likePrefix = implode($sep, $lpParts2);
        try {
            // 取最大序號（從號碼尾部數字）
            $maxStmt = $db->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(`{$numberCol}`, '{$sep}', -1) AS UNSIGNED)) FROM `{$tableName}` WHERE `{$numberCol}` LIKE ?");
            $maxStmt->execute(array($likePrefix . '%'));
            $maxSeq = (int)$maxStmt->fetchColumn();
            $nextSeq = $maxSeq + 1;
        } catch (Exception $ex) {
            if ($seq['last_reset_key'] !== $resetKey) {
                $nextSeq = 1;
            } else {
                $nextSeq = (int)$seq['last_sequence'] + 1;
            }
        }

        // 更新序列
        $db->prepare("UPDATE number_sequences SET last_sequence = ?, last_reset_key = ?, updated_at = NOW() WHERE id = ?")
           ->execute(array($nextSeq, $resetKey, $seq['id']));

        if ($ownTransaction) $db->commit();

        // 組合編號
        $parts = array();
        if ($prefix !== '') {
            $parts[] = $prefix;
        }
        if (!empty($dateFormat)) {
            $parts[] = date($dateFormat, $ts);
        }
        if ($digits > 0) {
            $parts[] = str_pad($nextSeq, $digits, '0', STR_PAD_LEFT);
        }

        return implode($sep, $parts);

    } catch (Exception $e) {
        if ($ownTransaction && $db->inTransaction()) $db->rollBack();
        // 記錄例外以便診斷
        error_log('generate_doc_number failed for module=' . $module . ': ' . $e->getMessage());
        // fallback：再試一次用 LIKE 直接查 table，避免再拿到通用 fallback
        try {
            $fbSeqStmt = $db->prepare("SELECT prefix, date_format, separator, seq_digits FROM number_sequences WHERE module = ?");
            $fbSeqStmt->execute(array($seqModule));
            $fbSeq = $fbSeqStmt->fetch(PDO::FETCH_ASSOC);
            if ($fbSeq) {
                $fbPrefix = $fbSeq['prefix'];
                $fbDateFmt = $fbSeq['date_format'];
                $fbSep = $fbSeq['separator'];
                $fbDigits = (int)$fbSeq['seq_digits'];
                $fbTs = $forDate ? strtotime($forDate) : time();
                $fbMap = array(
                    'bank_transactions' => array('bank_transactions', 'transaction_number'),
                    'cases' => array('cases', 'case_number'),
                    'customers' => array('customers', 'customer_no'),
                    'receivables' => array('receivables', 'invoice_number'),
                    'receipts' => array('receipts', 'receipt_number'),
                    'payables' => array('payables', 'payable_number'),
                );
                if (isset($fbMap[$module])) {
                    $fbTbl = $fbMap[$module][0];
                    $fbCol = $fbMap[$module][1];
                    $fbLpParts = array();
                    if ($fbPrefix !== '') $fbLpParts[] = $fbPrefix;
                    if (!empty($fbDateFmt)) $fbLpParts[] = date($fbDateFmt, $fbTs);
                    $fbLike = implode($fbSep, $fbLpParts);
                    $fbMaxStmt = $db->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(`{$fbCol}`, '{$fbSep}', -1) AS UNSIGNED)) FROM `{$fbTbl}` WHERE `{$fbCol}` LIKE ?");
                    $fbMaxStmt->execute(array($fbLike . '%'));
                    $fbNext = (int)$fbMaxStmt->fetchColumn() + 1;
                    $fbParts = array();
                    if ($fbPrefix !== '') $fbParts[] = $fbPrefix;
                    if (!empty($fbDateFmt)) $fbParts[] = date($fbDateFmt, $fbTs);
                    if ($fbDigits > 0) $fbParts[] = str_pad($fbNext, $fbDigits, '0', STR_PAD_LEFT);
                    return implode($fbSep, $fbParts);
                }
            }
        } catch (Exception $e2) {
            error_log('generate_doc_number fallback also failed: ' . $e2->getMessage());
        }
        // 最終 fallback：通用格式
        return strtoupper($module) . '-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    }
}

/**
 * 預覽編號格式（不真的產生序號）
 */
function preview_doc_number($prefix, $dateFormat, $sep, $digits)
{
    $parts = array();
    if ($prefix !== '') {
        $parts[] = $prefix;
    }
    if (!empty($dateFormat)) {
        $parts[] = date($dateFormat);
    }
    if ((int)$digits > 0) {
        $parts[] = str_pad(1, (int)$digits, '0', STR_PAD_LEFT);
    }
    return implode($sep, $parts);
}

/**
 * 查看下一個編號（不消耗序號）
 */
function peek_next_doc_number($module, $forDate = null)
{
    // 別名映射
    $aliasMap = array('payments_out' => 'payments');
    $seqModule = isset($aliasMap[$module]) ? $aliasMap[$module] : $module;

    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM number_sequences WHERE module = ?");
    $stmt->execute(array($seqModule));
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$seq) return '';

    $prefix = $seq['prefix'];
    $dateFormat = $seq['date_format'];
    $sep = $seq['separator'];
    $digits = (int)$seq['seq_digits'];

    $ts = $forDate ? strtotime($forDate) : time();

    // 用實際資料 COUNT 計算下一個序號
    $tableMap = array(
        'cases' => array('cases', 'case_number'),
        'customers' => array('customers', 'customer_no'),
        'journal_entries' => array('journal_entries', 'voucher_number'),
        'purchase_orders' => array('purchase_orders', 'po_number'),
        'requisitions' => array('requisitions', 'req_number'),
        'stock_outs' => array('stock_outs', 'so_number'),
        'stock_ins' => array('stock_ins', 'si_number'),
        'receivables' => array('receivables', 'invoice_number'),
        'receipts' => array('receipts', 'receipt_number'),
        'payables' => array('payables', 'payable_number'),
        'payments' => array('payments_out', 'payment_number'),
        'payments_out' => array('payments_out', 'payment_number'),
        'bank_transactions' => array('bank_transactions', 'transaction_number'),
    );
    $lpParts = array();
    if ($prefix !== '') $lpParts[] = $prefix;
    if (!empty($dateFormat)) $lpParts[] = date($dateFormat, $ts);
    $likePrefix = implode($sep, $lpParts);
    $nextSeq = 1;
    if (isset($tableMap[$module])) {
        try {
            $tbl = $tableMap[$module][0];
            $col = $tableMap[$module][1];
            $maxStmt = $db->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(`{$col}`, '{$sep}', -1) AS UNSIGNED)) FROM `{$tbl}` WHERE `{$col}` LIKE ?");
            $maxStmt->execute(array($likePrefix . '%'));
            $nextSeq = (int)$maxStmt->fetchColumn() + 1;
        } catch (Exception $e) {}
    }

    $parts = array();
    if ($prefix !== '') $parts[] = $prefix;
    if (!empty($dateFormat)) $parts[] = date($dateFormat, $ts);
    if ($digits > 0) {
        $parts[] = str_pad($nextSeq, $digits, '0', STR_PAD_LEFT);
    }
    return implode($sep, $parts);
}

/**
 * 備份檔案到 Google Drive（雙寫用）
 * 失敗不影響主流程，只記 error_log
 * @param string $localPath 本機檔案完整路徑
 * @param string $type 類型（customers, cases, case_payments, staff, repairs, worklogs, etc.）
 * @param string $subFolder 子資料夾（如客戶ID、案件ID）
 * @param string|null $fileName 檔名（null 用原檔名）
 */
function backup_to_drive($localPath, $type, $subFolder, $fileName = null)
{
    try {
        require_once __DIR__ . '/GoogleDrive.php';
        $drive = new GoogleDrive();
        if ($drive->isAuthorized()) {
            $drive->backupFile($localPath, $type, strval($subFolder), $fileName);
        }
    } catch (Exception $e) {
        error_log('backup_to_drive error: ' . $e->getMessage());
    }
}

/**
 * 自動將案件結案：當以下條件全部成立
 *  - 完工簽核 L2（行政人員）已核准
 *  - is_completed=1 且 completion_date 不為空
 *  - settlement_confirmed=1 且 settlement_date 不為空
 *  - 應收基底 > 0 且 balance_amount=0
 *  - 案件目前不是 closed
 * 通過則 status='closed', sub_status='已完工結案'
 * @return bool true=已自動結案
 */
function tryAutoCloseCase($caseId)
{
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT status, is_completed, completion_date, settlement_confirmed, settlement_date, balance_amount, deal_amount, total_amount FROM cases WHERE id = ?");
    $stmt->execute(array($caseId));
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) return false;
    if ($c['status'] === 'closed') return false;
    if ((int)$c['is_completed'] !== 1 || empty($c['completion_date'])) return false;
    if ((int)$c['settlement_confirmed'] !== 1 || empty($c['settlement_date'])) return false;
    $base = (int)$c['total_amount'] > 0 ? (int)$c['total_amount'] : (int)$c['deal_amount'];
    if ($base <= 0) return false;
    if ((int)$c['balance_amount'] !== 0) return false;
    $l2 = $db->prepare("SELECT COUNT(*) FROM approval_flows WHERE module='case_completion' AND target_id=? AND level_order=2 AND status='approved'");
    $l2->execute(array($caseId));
    if ((int)$l2->fetchColumn() === 0) return false;

    $db->prepare("UPDATE cases SET status = 'closed' WHERE id = ?")->execute(array($caseId));
    return true;
}
