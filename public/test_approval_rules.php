<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$stmt = $db->query("SELECT * FROM approval_rules WHERE module IN ('purchases','purchase_orders') ORDER BY module, level_order");
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "請購單/採購單簽核規則: " . count($rules) . " 筆\n\n";
foreach ($rules as $r) {
    echo "id={$r['id']} module={$r['module']} name={$r['rule_name']} min={$r['min_amount']} max={$r['max_amount']} ";
    echo "role={$r['approver_role']} approver_id={$r['approver_id']} active={$r['is_active']} ";
    echo "condition={$r['condition_type']} products={$r['product_ids']} cat={$r['product_category_id']}\n";
}
