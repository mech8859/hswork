<?php
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);

try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗: ' . $e->getMessage());
}

echo '<h2>自動建立缺少的客戶資料</h2>';

// 找出未配對且有 customer_name 的案件，依 customer_name 分組
$stmt = $db->query("
    SELECT customer_name,
           COUNT(*) as case_count,
           GROUP_CONCAT(DISTINCT COALESCE(address,'') SEPARATOR '|||') as addresses,
           GROUP_CONCAT(DISTINCT COALESCE(contact_person,'') SEPARATOR '|||') as contacts,
           GROUP_CONCAT(DISTINCT COALESCE(customer_phone,'') SEPARATOR '|||') as phones,
           GROUP_CONCAT(DISTINCT COALESCE(customer_mobile,'') SEPARATOR '|||') as mobiles,
           GROUP_CONCAT(DISTINCT COALESCE(billing_title,'') SEPARATOR '|||') as titles,
           GROUP_CONCAT(DISTINCT COALESCE(billing_tax_id,'') SEPARATOR '|||') as tax_ids
    FROM cases
    WHERE customer_id IS NULL
      AND customer_name IS NOT NULL
      AND customer_name != ''
    GROUP BY customer_name
    ORDER BY case_count DESC
");
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<p>需建立客戶: ' . count($groups) . ' 個不同名稱</p>';

// 取得目前最大自動編號
$maxNo = $db->query("SELECT MAX(CAST(SUBSTRING(customer_no, 4) AS UNSIGNED)) FROM customers WHERE customer_no LIKE 'CU-%'")->fetchColumn();
$nextNum = ($maxNo ?: 0) + 1;

// 準備 INSERT
$insertCust = $db->prepare("
    INSERT INTO customers (customer_no, name, contact_person, phone, mobile,
                           site_address, invoice_title, tax_id, category,
                           import_source, created_by, is_active)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'residential', '案件匯入', 1, 1)
");

$updateCase = $db->prepare("UPDATE cases SET customer_id = ? WHERE customer_name = ? AND customer_id IS NULL");

$created = 0;
$linked = 0;

foreach ($groups as $g) {
    $name = trim($g['customer_name']);
    if (!$name) continue;

    // 再次確認客戶表沒有
    $exists = $db->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
    $exists->execute(array($name));
    $existingId = $exists->fetchColumn();

    if ($existingId) {
        // 已存在就直接配對
        $updateCase->execute(array($existingId, $name));
        $linked += $updateCase->rowCount();
        continue;
    }

    // 取第一個非空值
    $addr = '';
    $addrs = array_filter(explode('|||', $g['addresses']));
    if ($addrs) $addr = reset($addrs);

    $contact = '';
    $contactList = array_filter(explode('|||', $g['contacts']));
    if ($contactList) $contact = reset($contactList);

    $phone = '';
    $phoneList = array_filter(explode('|||', $g['phones']));
    if ($phoneList) $phone = reset($phoneList);

    $mobile = '';
    $mobileList = array_filter(explode('|||', $g['mobiles']));
    if ($mobileList) $mobile = reset($mobileList);

    $title = '';
    $titleList = array_filter(explode('|||', $g['titles']));
    if ($titleList) $title = reset($titleList);

    $taxId = '';
    $taxList = array_filter(explode('|||', $g['tax_ids']));
    if ($taxList) $taxId = reset($taxList);

    // 產生編號
    $custNo = sprintf('CU-%06d', $nextNum++);

    // 建立客戶
    $insertCust->execute(array(
        $custNo,
        $name,
        $contact ?: null,
        $phone ?: null,
        $mobile ?: null,
        $addr ?: null,
        $title ?: null,
        $taxId ?: null
    ));
    $newId = $db->lastInsertId();
    $created++;

    // 配對案件
    $updateCase->execute(array($newId, $name));
    $linked += $updateCase->rowCount();
}

echo "<p style='color:green'>新建客戶: {$created} 筆</p>";
echo "<p style='color:green'>配對案件: {$linked} 筆</p>";

// 最終統計
$stats = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END) as has_id,
    SUM(CASE WHEN customer_id IS NULL THEN 1 ELSE 0 END) as no_id
    FROM cases")->fetch(PDO::FETCH_ASSOC);

$custTotal = $db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn();

echo '<h3>最終統計</h3>';
echo '<table border="1" cellpadding="6">';
echo '<tr><td>客戶總數</td><td>' . number_format($custTotal) . '</td></tr>';
echo '<tr><td>案件已配對</td><td>' . $stats['has_id'] . ' / ' . $stats['total'] . '</td></tr>';
echo '<tr><td>案件未配對</td><td>' . $stats['no_id'] . '</td></tr>';
echo '</table>';

echo '<p><a href="customers.php">返回客戶管理</a></p>';
