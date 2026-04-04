<?php
/**
 * 現金明細 - Ragic JSON 同步
 * 來源：statistical-form/28，瀏覽器抓取上傳為 ragic_cash_details.json
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') {
    die('需要管理員權限');
}
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '256M');
echo '<h2>現金明細 - Ragic 同步</h2><pre>';

$db = Database::getInstance();
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 確保 ragic_id 欄位存在
try {
    $db->exec("ALTER TABLE cash_details ADD COLUMN IF NOT EXISTS ragic_id INT UNSIGNED DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $db->exec("ALTER TABLE cash_details ADD INDEX IF NOT EXISTS idx_ragic_id (ragic_id)");
} catch (PDOException $e) {}
try {
    $db->exec("ALTER TABLE cash_details ADD INDEX IF NOT EXISTS idx_entry_number (entry_number)");
} catch (PDOException $e) {}

// 讀取 JSON
$jsonPath = __DIR__ . '/../ragic_cash_details.json';
if (!file_exists($jsonPath)) {
    die("ragic_cash_details.json 不存在\n請先從 Ragic 瀏覽器抓取資料並上傳\n");
}
$records = json_decode(file_get_contents($jsonPath), true);
if (!$records) {
    die("JSON 解析失敗\n");
}
echo "Ragic JSON 共 " . count($records) . " 筆\n";

// 載入分公司對照
$branches = $db->query("SELECT id, name FROM branches")->fetchAll(PDO::FETCH_ASSOC);
$branchMap = array();
foreach ($branches as $br) {
    $branchMap[$br['name']] = $br['id'];
}

function findBranch($name, $map) {
    if (empty($name)) return null;
    if (isset($map[$name])) return $map[$name];
    foreach ($map as $n => $id) {
        if (strpos($name, str_replace(array('分公司','據點'), '', $n)) !== false) return $id;
        if (strpos($n, str_replace(array('分公司','據點'), '', $name)) !== false) return $id;
    }
    return null;
}

function parseDate($v) {
    if (empty($v)) return null;
    $v = str_replace('/', '-', trim($v));
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : null;
}

function parseAmount($v) {
    if (empty($v) || $v === '0') return 0;
    return (int)preg_replace('/[^0-9\-]/', '', $v);
}

function cleanStr($v) {
    if (empty($v)) return null;
    $v = trim($v);
    return $v === '' ? null : $v;
}

// 取得 hswork 現有資料
$existingStmt = $db->query("SELECT id, entry_number, ragic_id FROM cash_details");
$existingMap = array();
$existingRagicMap = array();
foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($row['entry_number']) {
        $existingMap[$row['entry_number']] = $row['id'];
    }
    if ($row['ragic_id']) {
        $existingRagicMap[$row['ragic_id']] = $row['id'];
    }
}
echo "hswork 現有 " . count($existingMap) . " 筆\n\n";

$imported = 0;
$updated = 0;
$skipped = 0;
$errors = 0;
$allRagicEntryNumbers = array();

foreach ($records as $idx => $r) {
    $num = $idx + 1;
    $entryNumber = cleanStr(isset($r['現金編號']) ? $r['現金編號'] : '');
    $ragicId = isset($r['_ragicId']) ? $r['_ragicId'] : null;

    if (empty($entryNumber)) {
        $skipped++;
        continue;
    }

    $allRagicEntryNumbers[$entryNumber] = true;

    $registerDate  = parseDate(isset($r['登記日期']) ? $r['登記日期'] : '');
    $txDate        = parseDate(isset($r['交易日期']) ? $r['交易日期'] : '');
    $branchName    = cleanStr(isset($r['所屬分公司']) ? $r['所屬分公司'] : '');
    $branchId      = findBranch($branchName, $branchMap);
    $salesName     = cleanStr(isset($r['承辦業務']) ? $r['承辦業務'] : '');
    $description   = cleanStr(isset($r['明細']) ? $r['明細'] : '');
    $incomeAmount  = parseAmount(isset($r['收入金額']) ? $r['收入金額'] : '0');
    $expenseAmount = parseAmount(isset($r['支出金額']) ? $r['支出金額'] : '0');
    $uploadNumber  = cleanStr(isset($r['上傳編號']) ? $r['上傳編號'] : '');

    // 比對是否已存在
    $existId = null;
    if (isset($existingMap[$entryNumber])) {
        $existId = $existingMap[$entryNumber];
    } elseif ($ragicId && isset($existingRagicMap[$ragicId])) {
        $existId = $existingRagicMap[$ragicId];
    }

    if ($existId) {
        if ($num <= 3 || $num % 100 == 0) echo "[{$num}] {$entryNumber} → 更新\n";
        if ($execute) {
            try {
                $db->prepare("
                    UPDATE cash_details SET
                        entry_number=?, register_date=?, transaction_date=?, branch_id=?,
                        sales_name=?, description=?, income_amount=?, expense_amount=?,
                        upload_number=?, ragic_id=?
                    WHERE id=?
                ")->execute(array(
                    $entryNumber, $registerDate, $txDate, $branchId,
                    $salesName, $description, $incomeAmount, $expenseAmount,
                    $uploadNumber, $ragicId,
                    $existId
                ));
                $updated++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n";
                $errors++;
            }
        } else {
            $updated++;
        }
    } else {
        if ($num <= 5 || $num % 100 == 0) echo "[{$num}] {$entryNumber} {$description} → 新增\n";
        if ($execute) {
            try {
                $db->prepare("
                    INSERT INTO cash_details (
                        entry_number, register_date, transaction_date, branch_id,
                        sales_name, description, income_amount, expense_amount,
                        upload_number, ragic_id
                    ) VALUES (?,?,?,?,?,?,?,?,?,?)
                ")->execute(array(
                    $entryNumber, $registerDate, $txDate, $branchId,
                    $salesName, $description, $incomeAmount, $expenseAmount,
                    $uploadNumber, $ragicId
                ));
                $imported++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n";
                $errors++;
            }
        } else {
            $imported++;
        }
    }
}

// 刪除 hswork 有但 Ragic 沒有的
$deleted = 0;
foreach ($existingMap as $existNo => $existId) {
    if (!isset($allRagicEntryNumbers[$existNo]) && !empty($existNo)) {
        if ($deleted < 20) echo "[刪除] {$existNo} (ID:{$existId})\n";
        if ($execute) {
            try {
                $db->prepare("DELETE FROM cash_details WHERE id = ?")->execute(array($existId));
                $deleted++;
            } catch (PDOException $e) {
                echo "  [刪除錯誤] " . $e->getMessage() . "\n";
            }
        } else {
            $deleted++;
        }
    }
}

echo "\n==============================\n";
echo "完成！\n";
echo "  新增: {$imported} 筆\n";
echo "  更新: {$updated} 筆\n";
echo "  刪除: {$deleted} 筆\n";
echo "  跳過: {$skipped} 筆\n";
echo "  錯誤: {$errors} 筆\n";
echo '</pre>';
echo '<p><a href="/cash_details.php">返回現金明細</a></p>';
