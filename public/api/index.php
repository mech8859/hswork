<?php
/**
 * API 入口點
 * 支援 Ragic / Google Sheet 串接
 *
 * URL格式: /api/index.php?endpoint=cases&action=list
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// API 金鑰驗證
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
if (empty($apiKey)) {
    json_response(['error' => 'Missing API key'], 401);
}

$db = Database::getInstance();
$stmt = $db->prepare('SELECT * FROM api_keys WHERE api_key = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$apiKey]);
$keyRecord = $stmt->fetch();

if (!$keyRecord) {
    json_response(['error' => 'Invalid API key'], 401);
}

// 更新最後使用時間
$db->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?')
   ->execute([$keyRecord['id']]);

// 路由
$endpoint = $_GET['endpoint'] ?? '';
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

$apiModulePath = __DIR__ . '/../../modules/api/';

switch ($endpoint) {
    case 'cases':
        require $apiModulePath . 'cases_api.php';
        break;
    case 'schedules':
        require $apiModulePath . 'schedules_api.php';
        break;
    case 'staff':
        require $apiModulePath . 'staff_api.php';
        break;
    case 'sync':
        require $apiModulePath . 'sync_api.php';
        break;
    default:
        json_response([
            'status'    => 'ok',
            'version'   => '1.0.0',
            'endpoints' => ['cases', 'schedules', 'staff', 'sync'],
        ]);
}
