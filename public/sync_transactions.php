<?php
/**
 * 非廠商交易 Ragic 同步
 * - Ragic有系統沒有 → 新增
 * - Ragic有系統有 → 比對更新
 * - Ragic沒有系統有 → 刪除
 */
error_reporting(E_ALL); ini_set('display_errors',1);
set_time_limit(120);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// Load Ragic JSON
$jsonFile = '/raid/vhost/hswork.com.tw/ragic_staff_transactions.json';
if (!file_exists($jsonFile)) {
    $jsonFile = __DIR__ . '/../data/ragic_staff_transactions.json';
}
if (!file_exists($jsonFile)) {
    echo "ERROR: JSON file not found\n";
    exit;
}

$ragicData = json_decode(file_get_contents($jsonFile), true);
if (!$ragicData) {
    echo "ERROR: Invalid JSON\n";
    exit;
}
echo "Ragic records: " . count($ragicData) . "\n";

// Map target type
function mapTarget($t) {
    $map = array('員工' => 'employee', '合作夥伴' => 'partner', '黑鼠' => 'partner', '黑齒' => 'partner');
    return isset($map[$t]) ? $map[$t] : 'other';
}
function mapCategory($c) {
    $map = array('購買商品' => 'purchase');
    return isset($map[$c]) ? $map[$c] : $c;
}
function mapSettled($s) {
    return ($s === '已結清') ? 1 : 0;
}
function cleanDate($d) {
    if (!$d) return null;
    $d = str_replace('/', '-', trim($d));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
    if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $d)) {
        $parts = explode('-', $d);
        return $parts[0] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[2], 2, '0', STR_PAD_LEFT);
    }
    return null;
}

// Build Ragic index by register_no
$ragicIndex = array();
foreach ($ragicData as $r) {
    $ragicIndex[$r['reg_no']] = $r;
}

// Get existing system data
$sysRecords = $db->query("SELECT * FROM transactions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$sysIndex = array();
foreach ($sysRecords as $s) {
    $sysIndex[$s['register_no']] = $s;
}
echo "System records: " . count($sysRecords) . "\n\n";

$added = 0; $updated = 0; $deleted = 0; $unchanged = 0;
$errors = array();

// 1. Ragic有系統沒有 → 新增
// 2. Ragic有系統有 → 比對更新
foreach ($ragicIndex as $regNo => $ragic) {
    $targetType = mapTarget($ragic['target']);
    $category = mapCategory($ragic['category']);
    $regDate = cleanDate($ragic['reg_date']);
    $contactName = $ragic['name'];

    if (!isset($sysIndex[$regNo])) {
        // 新增
        try {
            $stmt = $db->prepare("INSERT INTO transactions (register_no, register_date, target_type, category, contact_name, total_unpaid, created_by) VALUES (?, ?, ?, ?, ?, 0, NULL)");
            $stmt->execute(array($regNo, $regDate, $targetType, $category, $contactName));
            $txId = $db->lastInsertId();

            foreach ($ragic['items'] as $item) {
                $db->prepare("INSERT INTO transaction_items (transaction_id, trade_date, description, product, amount, due_date, is_settled, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute(array(
                       $txId,
                       cleanDate($item['date']),
                       $item['content'],
                       $item['product'],
                       (int)$item['amount'],
                       $item['due_date'] ?: null,
                       mapSettled($item['settled']),
                       $item['note']
                   ));
            }

            // Recalculate unpaid
            $db->prepare("UPDATE transactions SET total_unpaid = (SELECT COALESCE(SUM(amount),0) FROM transaction_items WHERE transaction_id = ? AND is_settled = 0) WHERE id = ?")
               ->execute(array($txId, $txId));

            $added++;
        } catch (Exception $e) {
            $errors[] = "ADD $regNo: " . $e->getMessage();
        }
    } else {
        // 比對更新
        $sys = $sysIndex[$regNo];
        $needUpdate = false;

        // Compare main fields
        if ($sys['register_date'] != $regDate) $needUpdate = true;
        if ($sys['target_type'] != $targetType) $needUpdate = true;
        if ($sys['category'] != $category) $needUpdate = true;
        if ($sys['contact_name'] != $contactName) $needUpdate = true;

        // Compare items
        $sysItems = $db->prepare("SELECT * FROM transaction_items WHERE transaction_id = ? ORDER BY trade_date, id");
        $sysItems->execute(array($sys['id']));
        $sysItemList = $sysItems->fetchAll(PDO::FETCH_ASSOC);

        $ragicItems = $ragic['items'];

        // Simple check: count different or any field different
        if (count($sysItemList) != count($ragicItems)) {
            $needUpdate = true;
        } else {
            for ($i = 0; $i < count($ragicItems); $i++) {
                $ri = $ragicItems[$i];
                $si = $sysItemList[$i];
                if (cleanDate($ri['date']) != $si['trade_date']) { $needUpdate = true; break; }
                if ($ri['content'] != $si['description']) { $needUpdate = true; break; }
                if ((int)$ri['amount'] != (int)$si['amount']) { $needUpdate = true; break; }
                if (mapSettled($ri['settled']) != (int)$si['is_settled']) { $needUpdate = true; break; }
            }
        }

        if ($needUpdate) {
            try {
                // Update main
                $db->prepare("UPDATE transactions SET register_date = ?, target_type = ?, category = ?, contact_name = ? WHERE id = ?")
                   ->execute(array($regDate, $targetType, $category, $contactName, $sys['id']));

                // Delete old items and re-insert
                $db->prepare("DELETE FROM transaction_items WHERE transaction_id = ?")->execute(array($sys['id']));
                foreach ($ragicItems as $item) {
                    $db->prepare("INSERT INTO transaction_items (transaction_id, trade_date, description, product, amount, due_date, is_settled, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                       ->execute(array(
                           $sys['id'],
                           cleanDate($item['date']),
                           $item['content'],
                           $item['product'],
                           (int)$item['amount'],
                           $item['due_date'] ?: null,
                           mapSettled($item['settled']),
                           $item['note']
                       ));
                }

                // Recalculate
                $db->prepare("UPDATE transactions SET total_unpaid = (SELECT COALESCE(SUM(amount),0) FROM transaction_items WHERE transaction_id = ? AND is_settled = 0) WHERE id = ?")
                   ->execute(array($sys['id'], $sys['id']));

                $updated++;
            } catch (Exception $e) {
                $errors[] = "UPD $regNo: " . $e->getMessage();
            }
        } else {
            $unchanged++;
        }
    }
}

// 3. Ragic沒有系統有 → 刪除
foreach ($sysIndex as $regNo => $sys) {
    if (!isset($ragicIndex[$regNo])) {
        try {
            $db->prepare("DELETE FROM transaction_items WHERE transaction_id = ?")->execute(array($sys['id']));
            $db->prepare("DELETE FROM transactions WHERE id = ?")->execute(array($sys['id']));
            $deleted++;
        } catch (Exception $e) {
            $errors[] = "DEL $regNo: " . $e->getMessage();
        }
    }
}

echo "新增: $added\n";
echo "更新: $updated\n";
echo "不變: $unchanged\n";
echo "刪除: $deleted\n\n";

if (!empty($errors)) {
    echo "=== Errors ===\n";
    foreach ($errors as $e) echo "  $e\n";
}

echo "Done.\n";
