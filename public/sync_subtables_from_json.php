<?php
/**
 * 從 JSON 檔同步子表格（帳款/施工回報/附件）
 * 用法：
 *   預覽模式：sync_subtables_from_json.php
 *   執行寫入：sync_subtables_from_json.php?execute=1
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

set_time_limit(600);
ini_set('memory_limit', '512M');

$db = Database::getInstance();
$dryRun = !isset($_GET['execute']);

$jsonFile = __DIR__ . '/../database/ragic_cases_20260405.json';
if (!file_exists($jsonFile)) die('JSON not found');
$ragicData = json_decode(file_get_contents($jsonFile), true);

echo "=== 子表格同步（" . ($dryRun ? '預覽模式' : '執行模式') . "）===\n\n";

$fileTypeMap = array(
    '報價單' => 'quotation',
    '報價單(檔案)' => 'quotation',
    '圖面' => 'blueprint',
    '保固書' => 'warranty',
    '檔案' => 'other',
    '檔案2' => 'other',
    '相片' => 'site_photo'
);

$stats = array(
    'pay_new' => 0, 'pay_update' => 0, 'pay_skip' => 0,
    'wl_new' => 0, 'wl_update' => 0, 'wl_skip' => 0,
    'att_new' => 0, 'att_skip' => 0,
    'case_not_found' => 0
);

foreach ($ragicData as $ragicRid => $r) {
    $caseNumber = trim(isset($r['進件編號']) ? $r['進件編號'] : '');
    if (!$caseNumber) continue;

    $hasPayments = !empty($r['_subtable_1007228']);
    $hasWorklogs = !empty($r['_subtable_1007229']);
    $hasAttach = !empty($r['_subtable_1004158']);
    if (!$hasPayments && !$hasWorklogs && !$hasAttach) continue;

    // 找案件
    $stmt = $db->prepare("SELECT id FROM cases WHERE case_number = ?");
    $stmt->execute(array($caseNumber));
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$case) { $stats['case_not_found']++; continue; }
    $caseId = $case['id'];

    // ===== 帳款交易 =====
    if ($hasPayments) {
        foreach ($r['_subtable_1007228'] as $rid => $row) {
            $ragicId = 'st1007228_' . $rid;
            $date = isset($row['帳款交易日期']) ? str_replace('/', '-', trim($row['帳款交易日期'])) : '';
            if (empty($date)) continue;

            $paymentType = isset($row['帳款類別']) && $row['帳款類別'] !== '' ? trim($row['帳款類別']) : null;
            $rawTrans = isset($row['交易內容']) && $row['交易內容'] !== '' ? trim($row['交易內容']) : null;
            $amount = isset($row['金額']) ? (int)$row['金額'] : 0;
            $rawNote = isset($row['備註']) && $row['備註'] !== '' ? trim($row['備註']) : null;

            // 交易內容超過15字視為備註，短的留在交易方式
            if ($rawTrans && mb_strlen($rawTrans) > 15) {
                $transType = null;
                $note = $rawNote ? $rawTrans . "\n" . $rawNote : $rawTrans;
            } else {
                $transType = $rawTrans;
                $note = $rawNote;
            }

            // 檢查是否已存在
            $chk = $db->prepare("SELECT id, payment_date, payment_type, transaction_type, amount, note FROM case_payments WHERE case_id = ? AND ragic_id = ?");
            $chk->execute(array($caseId, $ragicId));
            $existing = $chk->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // 比對差異
                $needUpdate = false;
                $diffs = array();
                if ($existing['payment_date'] !== $date) { $needUpdate = true; $diffs[] = "日期:{$existing['payment_date']}=>{$date}"; }
                if (($existing['payment_type'] ?: '') !== ($paymentType ?: '')) { $needUpdate = true; $diffs[] = "類別:{$existing['payment_type']}=>{$paymentType}"; }
                if (($existing['transaction_type'] ?: '') !== ($transType ?: '')) { $needUpdate = true; $diffs[] = "交易:{$existing['transaction_type']}=>{$transType}"; }
                if ((int)$existing['amount'] !== $amount) { $needUpdate = true; $diffs[] = "金額:{$existing['amount']}=>{$amount}"; }
                if (($existing['note'] ?: '') !== ($note ?: '')) { $needUpdate = true; $diffs[] = "備註不同"; }

                if ($needUpdate) {
                    if (!$dryRun) {
                        $db->prepare("UPDATE case_payments SET payment_date=?, payment_type=?, transaction_type=?, amount=?, note=? WHERE id=?")
                           ->execute(array($date, $paymentType, $transType, $amount, $note, $existing['id']));
                    }
                    echo "[帳款更新] {$caseNumber} {$ragicId}: " . implode(', ', $diffs) . "\n";
                    $stats['pay_update']++;
                } else {
                    $stats['pay_skip']++;
                }
            } else {
                // 新增
                if (!$dryRun) {
                    $db->prepare("INSERT INTO case_payments (case_id, payment_date, payment_type, transaction_type, amount, note, ragic_id, created_by) VALUES (?,?,?,?,?,?,?,?)")
                       ->execute(array($caseId, $date, $paymentType, $transType, $amount, $note, $ragicId, Auth::id()));
                }
                echo "[帳款新增] {$caseNumber}: {$date} {$paymentType} {$transType} \${$amount}\n";
                $stats['pay_new']++;
            }
        }
    }

    // ===== 施工回報 =====
    if ($hasWorklogs) {
        foreach ($r['_subtable_1007229'] as $rid => $row) {
            $ragicId = 'st1007229_' . $rid;
            $content = isset($row['施工內容']) ? trim($row['施工內容']) : '';
            $workDate = !empty($row['施工日期']) ? str_replace('/', '-', trim($row['施工日期'])) : null;
            $equipment = isset($row['使用器材']) ? trim($row['使用器材']) : null;
            $cable = isset($row['使用線材']) ? trim($row['使用線材']) : null;

            // 施工照片（array）
            $photos = array();
            if (!empty($row['施工照片'])) {
                $photoData = $row['施工照片'];
                if (is_array($photoData)) {
                    foreach ($photoData as $p) {
                        $photos[] = $p;
                    }
                } elseif (is_string($photoData) && !empty($photoData)) {
                    $photos[] = $photoData;
                }
            }
            $photoPaths = !empty($photos) ? json_encode($photos, JSON_UNESCAPED_UNICODE) : null;

            // 施工人員（array）
            $workers = array();
            if (!empty($row['施工人員'])) {
                $workerData = $row['施工人員'];
                if (is_array($workerData)) {
                    $workers = array_merge($workers, $workerData);
                } elseif (is_string($workerData)) {
                    $workers[] = $workerData;
                }
            }
            if (!empty($row['點工人員'])) {
                $dwData = $row['點工人員'];
                if (is_array($dwData)) {
                    $workers = array_merge($workers, $dwData);
                } elseif (is_string($dwData)) {
                    $workers[] = $dwData;
                }
            }
            $workersJson = !empty($workers) ? json_encode($workers, JSON_UNESCAPED_UNICODE) : null;

            // 檢查是否已存在
            $chk = $db->prepare("SELECT id, work_date, work_content, equipment_used, cable_used, photo_paths, workers FROM case_work_logs WHERE case_id = ? AND ragic_id = ?");
            $chk->execute(array($caseId, $ragicId));
            $existing = $chk->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // 比對差異
                $needUpdate = false;
                $diffs = array();
                if (($existing['work_date'] ?: '') !== ($workDate ?: '')) { $needUpdate = true; $diffs[] = "日期"; }
                if (($existing['work_content'] ?: '') !== ($content ?: '')) { $needUpdate = true; $diffs[] = "內容"; }
                if (($existing['equipment_used'] ?: '') !== ($equipment ?: '')) { $needUpdate = true; $diffs[] = "器材"; }
                if (($existing['cable_used'] ?: '') !== ($cable ?: '')) { $needUpdate = true; $diffs[] = "線材"; }
                if (($existing['photo_paths'] ?: '') !== ($photoPaths ?: '')) { $needUpdate = true; $diffs[] = "照片"; }

                if ($needUpdate) {
                    if (!$dryRun) {
                        $db->prepare("UPDATE case_work_logs SET work_date=?, work_content=?, equipment_used=?, cable_used=?, photo_paths=? WHERE id=?")
                           ->execute(array($workDate, $content, $equipment, $cable, $photoPaths, $existing['id']));
                    }
                    echo "[施工更新] {$caseNumber} {$ragicId}: " . implode(', ', $diffs) . "\n";
                    $stats['wl_update']++;
                } else {
                    $stats['wl_skip']++;
                }
            } else {
                // 新增（允許空內容，只要有日期就新增）
                if (empty($content) && empty($workDate)) { continue; }
                if (!$dryRun) {
                    $db->prepare("INSERT INTO case_work_logs (case_id, work_date, work_content, equipment_used, cable_used, photo_paths, ragic_id, created_by) VALUES (?,?,?,?,?,?,?,?)")
                       ->execute(array($caseId, $workDate, $content, $equipment, $cable, $photoPaths, $ragicId, Auth::id()));
                }
                echo "[施工新增] {$caseNumber}: {$workDate} " . mb_substr($content, 0, 30) . "\n";
                $stats['wl_new']++;
            }
        }
    }

    // ===== 附件 =====
    if ($hasAttach) {
        foreach ($r['_subtable_1004158'] as $rid => $row) {
            foreach ($fileTypeMap as $ragicField => $hsType) {
                $fileInfo = isset($row[$ragicField]) ? $row[$ragicField] : '';
                if (empty($fileInfo) || $fileInfo === '-') continue;

                // 相片是 array
                $fileList = array();
                if (is_array($fileInfo)) {
                    $fileList = $fileInfo;
                } else {
                    $fileList = array($fileInfo);
                }

                foreach ($fileList as $singleFile) {
                    if (empty($singleFile) || !is_string($singleFile)) continue;
                    $parts = explode('@', $singleFile, 2);
                    if (count($parts) != 2) continue;
                    $fileKey = $parts[0];
                    $fileName = $parts[1];

                    // 用 file_name 比對（避免重複）
                    $chk = $db->prepare("SELECT id FROM case_attachments WHERE case_id = ? AND file_name = ?");
                    $chk->execute(array($caseId, $fileName));
                    if ($chk->fetch()) { $stats['att_skip']++; continue; }

                    if (!$dryRun) {
                        $db->prepare("INSERT INTO case_attachments (case_id, file_type, file_name, file_path, file_size, uploaded_by, note) VALUES (?,?,?,?,?,?,?)")
                           ->execute(array($caseId, $hsType, $fileName, '', 0, Auth::id(), 'ragic_pending:' . $fileKey));
                    }
                    echo "[附件新增] {$caseNumber}: [{$hsType}] {$fileName}\n";
                    $stats['att_new']++;
                }
            }
        }
    }
}

echo "\n========== 結果 ==========\n";
echo "帳款: 新增 {$stats['pay_new']} / 更新 {$stats['pay_update']} / 跳過 {$stats['pay_skip']}\n";
echo "施工: 新增 {$stats['wl_new']} / 更新 {$stats['wl_update']} / 跳過 {$stats['wl_skip']}\n";
echo "附件: 新增 {$stats['att_new']} / 跳過 {$stats['att_skip']}\n";
echo "找不到案件: {$stats['case_not_found']}\n";

if ($dryRun && ($stats['pay_new'] + $stats['pay_update'] + $stats['wl_new'] + $stats['wl_update'] + $stats['att_new'] > 0)) {
    echo "\n確認無誤後，加 ?execute=1 執行寫入\n";
} elseif ($dryRun) {
    echo "\n所有資料已完全相同，無需同步。\n";
}
