<?php
/**
 * 同步 Ragic 五星評價統計 → hswork five_star_reviews
 *
 * Ragic 表單：https://ap15.ragic.com/hstcc/statistical-form/11
 * 用 review_number 為唯一鍵避免重複，支援 INSERT / UPDATE
 *
 * 使用方式：
 *   https://hswork.com.tw/sync_reviews.php         (預覽 - 前 5 筆)
 *   https://hswork.com.tw/sync_reviews.php?go=1    (實際執行)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(600);
ini_set('memory_limit', '256M');

$db = Database::getInstance();
$execute = isset($_GET['go']) && $_GET['go'] === '1';

$API_KEY = 'dGhmNTRyZk9uMlRUS2c3MjhhQytMZjlZdCtQc1lUMVJHYzVCNlA0dFFVZm1tREk0MFVxU0JibnRmNGV3TElEMA==';
$API_URL = 'https://ap15.ragic.com/hstcc/statistical-form/11?api&limit=2000';

echo "=== Ragic 五星評價統計同步 ===\n";
echo $execute ? "模式：實際執行\n\n" : "模式：預覽（加 ?go=1 實際執行）\n\n";

// ---- 加 ragic_id 欄位（若無）----
try {
    $db->exec("ALTER TABLE five_star_reviews ADD COLUMN ragic_id VARCHAR(20) DEFAULT NULL COMMENT 'Ragic 原始 ID' AFTER id");
    $db->exec("ALTER TABLE five_star_reviews ADD UNIQUE KEY uk_ragic_id (ragic_id)");
    echo "[Migration] 已新增 ragic_id 欄位\n\n";
} catch (Exception $e) {
    // 欄位已存在，跳過
}

// ---- Step 1: 從 Ragic 抓資料 ----
echo "[1] 從 Ragic 抓資料...\n";
$ch = curl_init($API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "API 錯誤: HTTP {$httpCode}\n";
    exit;
}
$ragicData = json_decode($response, true);
if (!$ragicData) {
    echo "JSON 解析失敗\n";
    exit;
}
echo "取得 " . count($ragicData) . " 筆\n\n";

// ---- Step 2: 建立對照表 ----
// 2a. 人員姓名 → user_id
$userStmt = $db->query("SELECT id, real_name FROM users WHERE is_active = 1");
$nameToUserId = array();
foreach ($userStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
    $nameToUserId[trim($u['real_name'])] = (int)$u['id'];
}

// 2b. 分公司名稱 → branch_id（支援模糊比對）
$branchStmt = $db->query("SELECT id, name FROM branches WHERE is_active = 1");
$branchList = $branchStmt->fetchAll(PDO::FETCH_ASSOC);
$branchMap = array();
foreach ($branchList as $b) {
    $branchMap[trim($b['name'])] = (int)$b['id'];
}

function resolveBranchId($ragicBranchName, $branchMap, $branchList) {
    $n = trim($ragicBranchName);
    if ($n === '') return null;
    // 1. 完全比對
    if (isset($branchMap[$n])) return $branchMap[$n];
    // 2. 取前兩字模糊比對（清水分公司 → 清水電子鎖）
    $prefix = mb_substr($n, 0, 2);
    foreach ($branchList as $b) {
        if (mb_strpos($b['name'], $prefix) !== false) return (int)$b['id'];
    }
    return null;
}

function cleanDate($d) {
    if (empty($d)) return null;
    $d = str_replace('/', '-', trim($d));
    if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}/', $d, $m)) {
        // 確保格式是 YYYY-MM-DD
        $parts = explode('-', $m[0]);
        return sprintf('%04d-%02d-%02d', (int)$parts[0], (int)$parts[1], (int)$parts[2]);
    }
    return null;
}

function resolveUserIds($names, $nameToUserId, &$unmatched) {
    if (!is_array($names)) return array();
    $ids = array();
    foreach ($names as $name) {
        $name = trim($name);
        if ($name === '') continue;
        if (isset($nameToUserId[$name])) {
            $ids[] = $nameToUserId[$name];
        } else {
            $unmatched[] = $name;
        }
    }
    return $ids;
}

// ---- Step 3: 處理每筆 ----
$stats = array('added' => 0, 'updated' => 0, 'unchanged' => 0, 'error' => 0, 'branch_not_found' => 0);
$unmatchedEngineers = array();
$unmatchedBranches = array();

$createdBy = Auth::id();

echo "[2] 開始處理...\n\n";
$count = 0;
foreach ($ragicData as $ragicId => $rec) {
    $count++;
    $reviewNumber = isset($rec['五星評價編號']) ? trim($rec['五星評價編號']) : '';
    if ($reviewNumber === '') {
        echo "  [SKIP] ragicId={$ragicId} 無編號\n";
        $stats['error']++;
        continue;
    }

    $reviewDate = cleanDate(isset($rec['日期']) ? $rec['日期'] : '');
    $reason = isset($rec['不符獎金原因']) ? trim($rec['不符獎金原因']) : '';
    $photo = isset($rec['照片']) ? trim($rec['照片']) : '';
    $customerName = isset($rec['客戶名稱']) ? trim($rec['客戶名稱']) : '';
    $originalCustomerName = isset($rec['原客戶名稱(後刪)']) ? trim($rec['原客戶名稱(後刪)']) : '';
    $googleReviewer = isset($rec['Google評價人名稱']) ? preg_replace('/^[\r\n\s]+|[\r\n\s]+$/u', '', $rec['Google評價人名稱']) : '';
    $originalEngineers = isset($rec['原施工人員(後刪)']) ? trim($rec['原施工人員(後刪)']) : '';
    $branchName = isset($rec['所屬分公司']) ? trim($rec['所屬分公司']) : '';
    $bonusDate = cleanDate(isset($rec['獎金發放日期']) ? $rec['獎金發放日期'] : '');

    $branchId = resolveBranchId($branchName, $branchMap, $branchList);
    if ($branchName !== '' && !$branchId) {
        $unmatchedBranches[$branchName] = true;
        $stats['branch_not_found']++;
    }

    $groupPhotoNames = isset($rec['施工人員合影']) ? $rec['施工人員合影'] : array();
    $engineerNames = isset($rec['施工人員']) ? $rec['施工人員'] : array();

    $groupPhotoIds = resolveUserIds($groupPhotoNames, $nameToUserId, $unmatchedEngineers);
    $engineerIds = resolveUserIds($engineerNames, $nameToUserId, $unmatchedEngineers);

    // 無對應到的人員 → 併入 original_engineer_names（只處理施工人員欄位）
    $unmatchedInThisRow = array();
    if (is_array($engineerNames)) {
        foreach ($engineerNames as $en) {
            $en = trim($en);
            if ($en !== '' && !isset($nameToUserId[$en])) {
                $unmatchedInThisRow[] = $en;
            }
        }
    }
    if (!empty($unmatchedInThisRow)) {
        $extra = implode('、', $unmatchedInThisRow);
        $originalEngineers = $originalEngineers !== '' ? ($originalEngineers . '；' . $extra) : $extra;
    }

    $groupPhotoJson = !empty($groupPhotoIds) ? json_encode($groupPhotoIds) : null;
    $engineerJson = !empty($engineerIds) ? json_encode($engineerIds) : null;

    // 檢查是否已存在（用 ragic_id 為主鍵）
    $existStmt = $db->prepare("SELECT id, review_date, reason, photo_path, group_photo_engineer_ids, customer_name, original_customer_name, google_reviewer_name, engineer_ids, original_engineer_names, branch_id, bonus_payment_date FROM five_star_reviews WHERE ragic_id = ?");
    $existStmt->execute(array((string)$ragicId));
    $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // 比對是否有異動
        $needUpdate = (
            (string)$existing['review_date'] !== (string)($reviewDate ?: '') ||
            (string)$existing['reason'] !== (string)$reason ||
            (string)$existing['photo_path'] !== (string)$photo ||
            (string)$existing['group_photo_engineer_ids'] !== (string)($groupPhotoJson ?: '') ||
            (string)$existing['customer_name'] !== (string)$customerName ||
            (string)$existing['original_customer_name'] !== (string)$originalCustomerName ||
            (string)$existing['google_reviewer_name'] !== (string)$googleReviewer ||
            (string)$existing['engineer_ids'] !== (string)($engineerJson ?: '') ||
            (string)$existing['original_engineer_names'] !== (string)$originalEngineers ||
            (int)$existing['branch_id'] !== (int)($branchId ?: 0) ||
            (string)$existing['bonus_payment_date'] !== (string)($bonusDate ?: '')
        );

        if ($needUpdate) {
            if ($execute) {
                $upd = $db->prepare("UPDATE five_star_reviews SET
                    review_number = ?, review_date = ?, reason = ?, photo_path = ?,
                    group_photo_engineer_ids = ?, customer_name = ?, original_customer_name = ?,
                    google_reviewer_name = ?, engineer_ids = ?, original_engineer_names = ?,
                    branch_id = ?, bonus_payment_date = ?
                    WHERE id = ?");
                $upd->execute(array(
                    $reviewNumber, $reviewDate, $reason ?: null, $photo ?: null,
                    $groupPhotoJson, $customerName ?: null, $originalCustomerName ?: null,
                    $googleReviewer ?: null, $engineerJson, $originalEngineers ?: null,
                    $branchId, $bonusDate, (int)$existing['id']
                ));
            }
            $stats['updated']++;
            if ($count <= 5 || $stats['updated'] <= 3) {
                echo "  [UPD] {$reviewNumber} {$customerName}\n";
            }
        } else {
            $stats['unchanged']++;
        }
    } else {
        if ($execute) {
            $ins = $db->prepare("INSERT INTO five_star_reviews
                (ragic_id, review_number, review_date, reason, photo_path,
                 group_photo_engineer_ids, customer_name, original_customer_name,
                 google_reviewer_name, engineer_ids, original_engineer_names,
                 branch_id, bonus_payment_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            try {
                $ins->execute(array(
                    (string)$ragicId, $reviewNumber, $reviewDate, $reason ?: null, $photo ?: null,
                    $groupPhotoJson, $customerName ?: null, $originalCustomerName ?: null,
                    $googleReviewer ?: null, $engineerJson, $originalEngineers ?: null,
                    $branchId, $bonusDate, $createdBy
                ));
            } catch (Exception $e) {
                // 可能 review_number 重複（UNIQUE KEY），改走 UPDATE 並加 ragic_id
                try {
                    $upd = $db->prepare("UPDATE five_star_reviews SET
                        ragic_id = ?, review_date = ?, reason = ?, photo_path = ?,
                        group_photo_engineer_ids = ?, customer_name = ?, original_customer_name = ?,
                        google_reviewer_name = ?, engineer_ids = ?, original_engineer_names = ?,
                        branch_id = ?, bonus_payment_date = ?
                        WHERE review_number = ?");
                    $upd->execute(array(
                        (string)$ragicId, $reviewDate, $reason ?: null, $photo ?: null,
                        $groupPhotoJson, $customerName ?: null, $originalCustomerName ?: null,
                        $googleReviewer ?: null, $engineerJson, $originalEngineers ?: null,
                        $branchId, $bonusDate, $reviewNumber
                    ));
                    $stats['updated']++;
                    continue;
                } catch (Exception $e2) {
                    echo "  [ERR] {$reviewNumber}: " . $e2->getMessage() . "\n";
                    $stats['error']++;
                    continue;
                }
            }
        }
        $stats['added']++;
        if ($stats['added'] <= 5) {
            echo "  [ADD] {$reviewNumber} {$customerName} ({$branchName})\n";
        }
    }
}

echo "\n=== 結果 ===\n";
echo "新增: {$stats['added']}\n";
echo "更新: {$stats['updated']}\n";
echo "不變: {$stats['unchanged']}\n";
echo "錯誤: {$stats['error']}\n";
echo "分公司無法對應: {$stats['branch_not_found']}\n";

if (!empty($unmatchedBranches)) {
    echo "\n=== 未對應分公司（請至 branches 表確認）===\n";
    foreach (array_keys($unmatchedBranches) as $b) echo "  - {$b}\n";
}

if (!empty($unmatchedEngineers)) {
    $unique = array_unique($unmatchedEngineers);
    echo "\n=== 未對應人員（已寫入原施工人員欄位）===\n";
    foreach ($unique as $name) echo "  - {$name}\n";
}

echo "\nDone.\n";
