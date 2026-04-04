<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: application/json; charset=utf-8');
$db = Database::getInstance();

// 1. 傳票摘要（按月/成本中心）
$summary = $db->query("
    SELECT DATE_FORMAT(je.voucher_date, '%Y-%m') AS month,
           cc.name AS cost_center,
           COUNT(DISTINCT je.id) AS voucher_count,
           SUM(jl.debit_amount) AS total_debit,
           SUM(jl.credit_amount) AS total_credit
    FROM journal_entries je
    JOIN journal_entry_lines jl ON je.id = jl.journal_entry_id
    LEFT JOIN cost_centers cc ON jl.cost_center_id = cc.id
    WHERE je.status = 'posted'
    GROUP BY month, cc.name
    ORDER BY month, cc.name
")->fetchAll(PDO::FETCH_ASSOC);

// 2. 按科目總類統計
$byCategory = $db->query("
    SELECT coa.account_category AS category,
           coa.category_name,
           cc.name AS cost_center,
           SUM(jl.debit_amount) AS total_debit,
           SUM(jl.credit_amount) AS total_credit
    FROM journal_entry_lines jl
    JOIN journal_entries je ON jl.journal_entry_id = je.id
    JOIN chart_of_accounts coa ON jl.account_id = coa.id
    LEFT JOIN cost_centers cc ON jl.cost_center_id = cc.id
    WHERE je.status = 'posted'
    GROUP BY coa.account_category, coa.category_name, cc.name
    ORDER BY coa.account_category, cc.name
")->fetchAll(PDO::FETCH_ASSOC);

// 3. 按科目明細（前50大）
$byAccount = $db->query("
    SELECT coa.code, coa.name AS account_name,
           cc.name AS cost_center,
           SUM(jl.debit_amount) AS total_debit,
           SUM(jl.credit_amount) AS total_credit,
           COUNT(*) AS line_count
    FROM journal_entry_lines jl
    JOIN journal_entries je ON jl.journal_entry_id = je.id
    JOIN chart_of_accounts coa ON jl.account_id = coa.id
    LEFT JOIN cost_centers cc ON jl.cost_center_id = cc.id
    WHERE je.status = 'posted'
    GROUP BY coa.code, coa.name, cc.name
    ORDER BY (SUM(jl.debit_amount) + SUM(jl.credit_amount)) DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

// 4. 成本中心列表
$costCenters = $db->query("SELECT id, name FROM cost_centers WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// 5. 每日傳票統計
$daily = $db->query("
    SELECT je.voucher_date,
           cc.name AS cost_center,
           COUNT(DISTINCT je.id) AS voucher_count,
           SUM(jl.debit_amount) AS total_debit,
           SUM(jl.credit_amount) AS total_credit
    FROM journal_entries je
    JOIN journal_entry_lines jl ON je.id = jl.journal_entry_id
    LEFT JOIN cost_centers cc ON jl.cost_center_id = cc.id
    WHERE je.status = 'posted'
    GROUP BY je.voucher_date, cc.name
    ORDER BY je.voucher_date
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(array(
    'summary' => $summary,
    'by_category' => $byCategory,
    'by_account' => $byAccount,
    'cost_centers' => $costCenters,
    'daily' => $daily,
), JSON_UNESCAPED_UNICODE);
