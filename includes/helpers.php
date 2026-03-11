<?php
/**
 * 共用輔助函式
 */

/**
 * HTML 跳脫輸出
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return Session::verifyCsrf($token);
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
 * 取得角色中文名稱
 */
function role_name(string $role): string
{
    $config = require __DIR__ . '/../config/app.php';
    return $config['roles'][$role] ?? $role;
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
function get_readiness_warnings(array $readiness): array
{
    $warnings = [];
    if (empty($readiness['has_quotation']))        $warnings[] = '缺少報價單';
    if (empty($readiness['has_site_photos']))       $warnings[] = '缺少現場照片';
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
