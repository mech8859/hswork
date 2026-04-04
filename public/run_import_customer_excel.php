<?php
/**
 * 客戶資料 Excel 匯入（從 SQL 批次檔）
 * 清除現有客戶資料，重新匯入 16,157 筆
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
set_time_limit(600);
ini_set('memory_limit', '512M');
echo '<h2>客戶資料 Excel 匯入（批次模式）</h2><pre>';

$db = Database::getInstance();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;

if ($step === 0) {
    echo "此匯入分三步執行：\n\n";
    echo '<a href="?step=1" style="font-size:1.2em;font-weight:bold">Step 1: 匯入客戶資料（16,157 筆）</a>' . "\n";
    echo '<a href="?step=2" style="font-size:1.2em">Step 2: 匯入聯絡人</a>' . "\n";
    echo '<a href="?step=3" style="font-size:1.2em">Step 3: 比對案件 customer_name → customers</a>' . "\n";
    echo '<a href="?step=4" style="font-size:1.2em">Step 4: 業務比對（非現有業務標注備註）</a>' . "\n";
    echo '</pre>';
    exit;
}

function execSqlFile($db, $filepath) {
    if (!file_exists($filepath)) { echo "檔案不存在: {$filepath}\n"; return; }
    $sql = file_get_contents($filepath);
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));
    $success = 0; $errors = 0;
    foreach ($statements as $stmt) {
        $stmt = rtrim(trim($stmt), ';');
        if (empty($stmt) || strpos($stmt, '--') === 0) continue;
        try {
            $db->exec($stmt);
            $success++;
        } catch (PDOException $e) {
            echo "[錯誤] " . mb_substr($stmt, 0, 80) . "...\n  " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    echo "完成: {$success} 成功, {$errors} 錯誤\n";
}

if ($step === 1) {
    echo "=== Step 1: 匯入客戶資料 ===\n";
    $sqlFile = __DIR__ . '/../database/customer_import_customers_v2.sql';
    execSqlFile($db, $sqlFile);
    $count = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    echo "customers 表現有: {$count} 筆\n";
    $gcount = $db->query("SELECT COUNT(*) FROM customer_groups")->fetchColumn();
    echo "customer_groups 表現有: {$gcount} 筆\n";
    echo "\n" . '<a href="?step=2">→ 繼續 Step 2: 匯入聯絡人</a>';
} elseif ($step === 2) {
    echo "=== Step 2: 匯入聯絡人 ===\n";
    $sqlFile = __DIR__ . '/../database/customer_import_contacts_v2.sql';
    execSqlFile($db, $sqlFile);
    $count = $db->query("SELECT COUNT(*) FROM customer_contacts")->fetchColumn();
    echo "customer_contacts 表現有: {$count} 筆\n";
    echo "\n" . '<a href="?step=3">→ 繼續 Step 3: 比對案件</a>';
} elseif ($step === 3) {
    echo "=== Step 3: 比對 cases → customers ===\n";
    $matched = 0;
    $caseStmt = $db->query("SELECT id, customer_name FROM cases WHERE customer_name IS NOT NULL AND customer_name != ''");
    $cases = $caseStmt->fetchAll(PDO::FETCH_ASSOC);
    $custByName = array();
    $custStmt = $db->query("SELECT id, customer_no, name FROM customers ORDER BY id DESC");
    foreach ($custStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $n = trim($c['name']);
        if (!isset($custByName[$n])) {
            $custByName[$n] = array('id' => $c['id'], 'no' => $c['customer_no']);
        }
    }
    foreach ($cases as $case) {
        $cn = trim($case['customer_name']);
        if (isset($custByName[$cn])) {
            $db->prepare("UPDATE cases SET customer_id = ?, customer_no = ? WHERE id = ?")
               ->execute(array($custByName[$cn]['id'], $custByName[$cn]['no'], $case['id']));
            $matched++;
        }
    }
    echo "比對成功: {$matched} / " . count($cases) . " 筆案件\n";
    echo "\n" . '<a href="?step=4">→ 繼續 Step 4: 業務比對</a>';
} elseif ($step === 4) {
    echo "=== Step 4: 業務比對（非現有業務標注到備註）===\n";
    // 載入現有業務（is_sales = 1 的在職人員）
    $salesStmt = $db->query("SELECT id, real_name FROM users WHERE is_active = 1");
    $salesMap = array();
    foreach ($salesStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $salesMap[$u['real_name']] = $u['id'];
    }
    echo "現有業務: " . implode('、', array_keys($salesMap)) . "\n\n";

    // 找出 salesperson_name 不在現有業務名單的客戶
    $custStmt = $db->query("SELECT id, salesperson_name, warranty_note FROM customers WHERE salesperson_name IS NOT NULL AND salesperson_name != ''");
    $customers = $custStmt->fetchAll(PDO::FETCH_ASSOC);
    $matched = 0;
    $noted = 0;
    foreach ($customers as $c) {
        $sn = trim($c['salesperson_name']);
        if (isset($salesMap[$sn])) {
            // 比對成功，寫入 sales_id
            $db->prepare("UPDATE customers SET sales_id = ? WHERE id = ?")->execute(array($salesMap[$sn], $c['id']));
            $matched++;
        } else {
            // 非現有業務，標注到備註
            $notePrefix = '原承辦業務：' . $sn;
            $existingNote = $c['warranty_note'] ?: '';
            $newNote = $notePrefix . ($existingNote ? '；' . $existingNote : '');
            $db->prepare("UPDATE customers SET warranty_note = ?, salesperson_name = NULL, sales_id = NULL WHERE id = ?")
               ->execute(array($newNote, $c['id']));
            $noted++;
        }
    }
    echo "業務比對成功: {$matched} 筆\n";
    echo "非現有業務（已標注備註）: {$noted} 筆\n";

    // 同步 original_customer_no → legacy_customer_no
    $synced = $db->exec("UPDATE customers SET legacy_customer_no = original_customer_no WHERE original_customer_no IS NOT NULL AND original_customer_no != '' AND (legacy_customer_no IS NULL OR legacy_customer_no = '')");
    echo "\n原客戶編號同步: {$synced} 筆\n";
    echo "\n" . '<a href="/customers.php">→ 前往客戶管理</a>';
}

echo '</pre>';
exit;

// === 以下為舊版逐筆匯入（已停用）===
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 讀取 JSON
$jsonPath = __DIR__ . '/../database/customer_import.json';
if (!file_exists($jsonPath)) { die("customer_import.json 不存在\n"); }
$records = json_decode(file_get_contents($jsonPath), true);
if (!$records) { die("JSON 解析失敗\n"); }
echo "JSON 共 " . count($records) . " 筆\n\n";

// 載入分公司對照
$branches = $db->query("SELECT id, name FROM branches")->fetchAll(PDO::FETCH_ASSOC);
$branchMap = array();
foreach ($branches as $br) { $branchMap[$br['name']] = $br['id']; }

// 載入業務對照（is_sales = 1 的在職人員）
$salesStmt = $db->query("SELECT id, real_name FROM users WHERE is_active = 1");
$salesMap = array();
foreach ($salesStmt->fetchAll(PDO::FETCH_ASSOC) as $u) { $salesMap[$u['real_name']] = $u['id']; }

function findBranch($name, $map) {
    if (empty($name)) return null;
    if (isset($map[$name])) return $map[$name];
    foreach ($map as $n => $id) {
        if (strpos($name, str_replace(array('分公司','據點'), '', $n)) !== false) return $id;
    }
    return null;
}

if ($execute) {
    echo "=== 清除現有資料 ===\n";
    // 先記錄現有 cases 的 customer_name 對照
    $db->exec("TRUNCATE TABLE customer_contacts");
    $db->exec("TRUNCATE TABLE customer_groups");
    echo "已清除 customer_contacts, customer_groups\n";

    // 清除 customers 但保留 customer_deals, customer_files 的 FK 會斷掉
    // 先把 cases 的 customer_id 清掉，之後重新比對
    $db->exec("UPDATE cases SET customer_id = NULL, customer_no = NULL");
    echo "已清除 cases.customer_id/customer_no\n";

    $db->exec("DELETE FROM customers");
    $db->exec("ALTER TABLE customers AUTO_INCREMENT = 1");
    echo "已清除 customers\n\n";
}

// === 第一步：建立關聯群組 ===
echo "=== 建立關聯群組 ===\n";
$groups = array(); // group_id => {group_name, tax_id}
$groupCounter = 0;

// 收集統編群組
$taxGroups = array(); // tax_id => group_id
$nameGroups = array(); // name => [records]
$nameCount = array();

foreach ($records as $r) {
    $cname = trim($r['customer_name']);
    if (!isset($nameCount[$cname])) $nameCount[$cname] = 0;
    $nameCount[$cname]++;
}

// 分配群組
foreach ($records as &$r) {
    $tid = !empty($r['tax_id']) ? $r['tax_id'] : null;
    $cname = trim($r['customer_name']);

    if ($tid) {
        if (!isset($taxGroups[$tid])) {
            $groupCounter++;
            $taxGroups[$tid] = $groupCounter;
            $groups[$groupCounter] = array('name' => $cname, 'tax_id' => $tid);
        }
        $r['_group_id'] = $taxGroups[$tid];
    } elseif ($nameCount[$cname] > 1) {
        $key = 'name:' . $cname;
        if (!isset($nameGroups[$key])) {
            $groupCounter++;
            $nameGroups[$key] = $groupCounter;
            $groups[$groupCounter] = array('name' => $cname, 'tax_id' => null);
        }
        $r['_group_id'] = $nameGroups[$key];
    } else {
        $r['_group_id'] = null;
    }
}
unset($r);

$grouped = 0;
foreach ($records as $r) { if ($r['_group_id']) $grouped++; }
echo "關聯群組: {$groupCounter} 組, 涉及 {$grouped} 筆\n\n";

// 寫入 customer_groups
if ($execute) {
    foreach ($groups as $gid => $g) {
        $db->prepare("INSERT INTO customer_groups (id, group_name, tax_id) VALUES (?, ?, ?)")
           ->execute(array($gid, $g['name'], $g['tax_id']));
    }
    echo "已寫入 {$groupCounter} 個群組\n";
}

// === 第二步：匯入客戶 ===
echo "\n=== 匯入客戶 ===\n";
$imported = 0;
$errors = 0;

// 預備 branch mapping by source_branch
$branchBySource = array(
    '潭子' => findBranch('潭子分公司', $branchMap),
    '員林' => findBranch('員林分公司', $branchMap),
    '海線' => findBranch('清水分公司', $branchMap),
);

foreach ($records as $idx => $r) {
    $num = $idx + 1;
    if ($num <= 5 || $num % 2000 == 0) {
        echo "[{$num}] {$r['customer_no']} | {$r['case_number']} | {$r['customer_name']}\n";
    }

    $salesId = null;
    $salespersonNote = '';
    if (!empty($r['salesperson_name'])) {
        if (isset($salesMap[$r['salesperson_name']])) {
            $salesId = $salesMap[$r['salesperson_name']];
        } else {
            // 非現有業務，標注到備註
            $salespersonNote = '原承辦業務：' . $r['salesperson_name'];
        }
    }

    // 合併備註：warranty_note + salesperson note
    $noteText = $r['warranty_note'];
    if ($salespersonNote) {
        $noteText = $salespersonNote . ($noteText ? '；' . $noteText : '');
    }

    $branchId = isset($branchBySource[$r['source_branch']]) ? $branchBySource[$r['source_branch']] : null;

    if ($execute) {
        try {
            $db->prepare("
                INSERT INTO customers (
                    customer_no, case_number, name, category,
                    source_company, original_customer_no, related_group_id,
                    contact_person, phone, mobile,
                    tax_id, site_address,
                    completion_date, warranty_date, warranty_note,
                    payment_info, payment_terms, salesperson_name,
                    sales_id, line_official,
                    source_branch, import_source, is_active, created_at
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())
            ")->execute(array(
                $r['customer_no'],
                $r['case_number'],
                $r['customer_name'],
                !empty($r['category']) ? $r['category'] : 'residential',
                $r['source_company'],
                $r['original_customer_no'],
                $r['_group_id'],
                $r['contact_person'],
                $r['phone'],
                $r['mobile'],
                $r['tax_id'],
                $r['site_address'],
                $r['completion_date'],
                isset($r['warranty_date']) ? $r['warranty_date'] : null,
                $noteText ?: null,
                $r['payment_info'],
                isset($r['payment_terms']) ? $r['payment_terms'] : null,
                $salesId ? $r['salesperson_name'] : null,
                $salesId,
                $r['line_official'],
                $r['source_branch'],
                'excel_import',
            ));
            $customerId = $db->lastInsertId();

            // 寫入聯絡人
            if (!empty($r['contacts'])) {
                foreach ($r['contacts'] as $c) {
                    if (!empty($c['name']) || !empty($c['phone'])) {
                        $db->prepare("
                            INSERT INTO customer_contacts (customer_id, contact_name, phone, role)
                            VALUES (?, ?, ?, ?)
                        ")->execute(array(
                            $customerId,
                            !empty($c['name']) ? $c['name'] : '',
                            !empty($c['phone']) ? $c['phone'] : '',
                            !empty($c['role']) ? $c['role'] : '',
                        ));
                    }
                }
            }

            $imported++;
        } catch (PDOException $e) {
            if ($num <= 20 || $errors < 10) {
                echo "  [錯誤] {$r['customer_no']}: " . $e->getMessage() . "\n";
            }
            $errors++;
        }
    } else {
        $imported++;
    }
}

echo "\n客戶匯入: {$imported} 筆, 錯誤: {$errors}\n";

// === 第三步：重新比對 cases ===
if ($execute) {
    echo "\n=== 比對 cases.customer_name → customers ===\n";
    $matched = 0;
    $caseStmt = $db->query("SELECT id, customer_name FROM cases WHERE customer_name IS NOT NULL AND customer_name != ''");
    $cases = $caseStmt->fetchAll(PDO::FETCH_ASSOC);

    // 建立客戶名稱→ID/NO 對照（取最新的一筆）
    $custByName = array();
    $custStmt = $db->query("SELECT id, customer_no, name FROM customers ORDER BY id DESC");
    foreach ($custStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $n = trim($c['name']);
        if (!isset($custByName[$n])) {
            $custByName[$n] = array('id' => $c['id'], 'no' => $c['customer_no']);
        }
    }

    foreach ($cases as $case) {
        $cn = trim($case['customer_name']);
        if (isset($custByName[$cn])) {
            $db->prepare("UPDATE cases SET customer_id = ?, customer_no = ? WHERE id = ?")
               ->execute(array($custByName[$cn]['id'], $custByName[$cn]['no'], $case['id']));
            $matched++;
        }
    }
    echo "比對成功: {$matched} / " . count($cases) . " 筆案件\n";
}

echo "\n==============================\n";
echo "完成！\n";
echo '</pre>';
echo '<p><a href="/customers.php">前往客戶管理</a></p>';
