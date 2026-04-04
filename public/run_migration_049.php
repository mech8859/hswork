<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

// ============================================================
// 1. chart_of_accounts - Add ERP columns to existing table
// ============================================================
$coaColumns = array(
    array('account_type', "VARCHAR(20) DEFAULT NULL COMMENT 'asset,liability,equity,revenue,expense'"),
    array('parent_id', "INT UNSIGNED DEFAULT NULL"),
    array('level', "TINYINT DEFAULT 1"),
    array('is_detail', "TINYINT(1) DEFAULT 1 COMMENT '1=可記帳明細科目'"),
    array('normal_balance', "VARCHAR(10) DEFAULT 'debit' COMMENT 'debit or credit'"),
    array('description', "VARCHAR(500) DEFAULT NULL"),
    array('sort_order', "INT DEFAULT 0"),
    array('account_code', "VARCHAR(20) DEFAULT NULL COMMENT 'ERP standard code'"),
    array('account_name', "VARCHAR(100) DEFAULT NULL COMMENT 'ERP standard name'"),
);
foreach ($coaColumns as $col) {
    try {
        $db->exec("ALTER TABLE chart_of_accounts ADD COLUMN {$col[0]} {$col[1]}");
        $results[] = "chart_of_accounts.{$col[0]} added";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            $results[] = "chart_of_accounts.{$col[0]} exists, skip";
        } else {
            $results[] = "chart_of_accounts.{$col[0]} error: " . $e->getMessage();
        }
    }
}

// Add index on parent_id
try {
    $db->exec("ALTER TABLE chart_of_accounts ADD INDEX idx_parent_id (parent_id)");
    $results[] = "chart_of_accounts idx_parent_id added";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        $results[] = "chart_of_accounts idx_parent_id exists, skip";
    } else {
        $results[] = "chart_of_accounts idx_parent_id: " . $e->getMessage();
    }
}

// ============================================================
// 2. cost_centers
// ============================================================
try {
    $db->exec("CREATE TABLE IF NOT EXISTS cost_centers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(20) DEFAULT 'branch' COMMENT 'branch,project,department',
        branch_id INT DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        UNIQUE KEY uk_code (code),
        KEY idx_type (type),
        KEY idx_branch_id (branch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "cost_centers table created";
} catch (PDOException $e) {
    $results[] = "cost_centers error: " . $e->getMessage();
}

// ============================================================
// 3. journal_entries
// ============================================================
try {
    $db->exec("CREATE TABLE IF NOT EXISTS journal_entries (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        voucher_number VARCHAR(50) NOT NULL,
        voucher_date DATE NOT NULL,
        voucher_type VARCHAR(20) DEFAULT 'general' COMMENT 'general,receipt,payment,transfer,adjustment',
        description VARCHAR(500) DEFAULT NULL,
        source_module VARCHAR(50) DEFAULT NULL COMMENT 'manual,receivable,payable,petty_cash,bank',
        source_id INT DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'draft' COMMENT 'draft,posted,voided',
        total_debit DECIMAL(14,2) DEFAULT 0,
        total_credit DECIMAL(14,2) DEFAULT 0,
        posted_by INT DEFAULT NULL,
        posted_at DATETIME DEFAULT NULL,
        voided_by INT DEFAULT NULL,
        voided_at DATETIME DEFAULT NULL,
        void_reason VARCHAR(500) DEFAULT NULL,
        created_by INT NOT NULL,
        updated_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        UNIQUE KEY uk_voucher_number (voucher_number),
        KEY idx_voucher_date (voucher_date),
        KEY idx_status (status),
        KEY idx_source (source_module, source_id),
        KEY idx_voucher_type (voucher_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "journal_entries table created";
} catch (PDOException $e) {
    $results[] = "journal_entries error: " . $e->getMessage();
}

// ============================================================
// 4. journal_entry_lines
// ============================================================
try {
    $db->exec("CREATE TABLE IF NOT EXISTS journal_entry_lines (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        journal_entry_id INT UNSIGNED NOT NULL,
        account_id INT UNSIGNED NOT NULL,
        cost_center_id INT UNSIGNED DEFAULT NULL,
        debit_amount DECIMAL(14,2) DEFAULT 0,
        credit_amount DECIMAL(14,2) DEFAULT 0,
        description VARCHAR(500) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        KEY idx_journal_entry_id (journal_entry_id),
        KEY idx_account_id (account_id),
        KEY idx_cost_center_id (cost_center_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "journal_entry_lines table created";
} catch (PDOException $e) {
    $results[] = "journal_entry_lines error: " . $e->getMessage();
}

// ============================================================
// 5. purchase_invoices
// ============================================================
try {
    $db->exec("CREATE TABLE IF NOT EXISTS purchase_invoices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(50) NOT NULL,
        invoice_date DATE NOT NULL,
        vendor_id INT DEFAULT NULL,
        vendor_name VARCHAR(200) DEFAULT NULL,
        vendor_tax_id VARCHAR(20) DEFAULT NULL,
        invoice_type VARCHAR(20) DEFAULT 'taxable' COMMENT 'taxable,tax_exempt,zero_rate',
        amount_untaxed DECIMAL(14,2) DEFAULT 0,
        tax_amount DECIMAL(14,2) DEFAULT 0,
        total_amount DECIMAL(14,2) DEFAULT 0,
        tax_rate DECIMAL(5,2) DEFAULT 5.00,
        reference_type VARCHAR(50) DEFAULT NULL,
        reference_id INT DEFAULT NULL,
        deduction_type VARCHAR(20) DEFAULT 'none' COMMENT 'none,input_tax,fixed_asset',
        period VARCHAR(10) DEFAULT NULL COMMENT 'YYYY-MM',
        status VARCHAR(20) DEFAULT 'draft' COMMENT 'draft,confirmed,voided',
        note TEXT DEFAULT NULL,
        journal_entry_id INT UNSIGNED DEFAULT NULL,
        created_by INT NOT NULL,
        updated_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        KEY idx_invoice_date (invoice_date),
        KEY idx_vendor_id (vendor_id),
        KEY idx_status (status),
        KEY idx_period (period),
        KEY idx_reference (reference_type, reference_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "purchase_invoices table created";
} catch (PDOException $e) {
    $results[] = "purchase_invoices error: " . $e->getMessage();
}

// ============================================================
// 6. sales_invoices
// ============================================================
try {
    $db->exec("CREATE TABLE IF NOT EXISTS sales_invoices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(50) NOT NULL,
        invoice_date DATE NOT NULL,
        customer_name VARCHAR(200) DEFAULT NULL,
        customer_tax_id VARCHAR(20) DEFAULT NULL,
        invoice_type VARCHAR(20) DEFAULT 'taxable' COMMENT 'taxable,tax_exempt,zero_rate',
        amount_untaxed DECIMAL(14,2) DEFAULT 0,
        tax_amount DECIMAL(14,2) DEFAULT 0,
        total_amount DECIMAL(14,2) DEFAULT 0,
        tax_rate DECIMAL(5,2) DEFAULT 5.00,
        reference_type VARCHAR(50) DEFAULT NULL,
        reference_id INT DEFAULT NULL,
        period VARCHAR(10) DEFAULT NULL COMMENT 'YYYY-MM',
        status VARCHAR(20) DEFAULT 'draft' COMMENT 'draft,confirmed,voided',
        note TEXT DEFAULT NULL,
        journal_entry_id INT UNSIGNED DEFAULT NULL,
        created_by INT NOT NULL,
        updated_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        KEY idx_invoice_date (invoice_date),
        KEY idx_status (status),
        KEY idx_period (period),
        KEY idx_reference (reference_type, reference_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "sales_invoices table created";
} catch (PDOException $e) {
    $results[] = "sales_invoices error: " . $e->getMessage();
}

// ============================================================
// 7. Populate account_type, normal_balance, level, parent_id
//    based on existing code field in chart_of_accounts
// ============================================================
try {
    // Determine account_type from code prefix
    $db->exec("UPDATE chart_of_accounts SET account_type = 'asset' WHERE code LIKE '1%' AND account_type IS NULL");
    $db->exec("UPDATE chart_of_accounts SET account_type = 'liability' WHERE code LIKE '2%' AND account_type IS NULL");
    $db->exec("UPDATE chart_of_accounts SET account_type = 'equity' WHERE code LIKE '3%' AND account_type IS NULL");
    $db->exec("UPDATE chart_of_accounts SET account_type = 'revenue' WHERE code LIKE '4%' AND account_type IS NULL");
    $db->exec("UPDATE chart_of_accounts SET account_type = 'expense' WHERE (code LIKE '5%' OR code LIKE '6%') AND account_type IS NULL");
    $db->exec("UPDATE chart_of_accounts SET account_type = 'revenue' WHERE code LIKE '7%' AND account_type IS NULL");

    // Normal balance
    $db->exec("UPDATE chart_of_accounts SET normal_balance = 'debit' WHERE account_type IN ('asset','expense') AND normal_balance IS NULL");
    $db->exec("UPDATE chart_of_accounts SET normal_balance = 'credit' WHERE account_type IN ('liability','equity','revenue') AND normal_balance IS NULL");

    // Set account_code = code, account_name = name where not set
    $db->exec("UPDATE chart_of_accounts SET account_code = code WHERE account_code IS NULL");
    $db->exec("UPDATE chart_of_accounts SET account_name = name WHERE account_name IS NULL");

    // Level based on code length
    $db->exec("UPDATE chart_of_accounts SET level = CHAR_LENGTH(code)");
    // Simplified: 4-digit = level 1, 6-digit = level 2, 8-digit = level 3, etc.
    // Actually level is based on hierarchy. Let's use: 1-digit=1, 2-digit=2, etc.
    // Better: set based on the code structure: 1xxx = level 1, if parent exists, etc.
    // For now, detect from code length:
    $db->exec("UPDATE chart_of_accounts SET level = 1 WHERE CHAR_LENGTH(code) <= 4");
    $db->exec("UPDATE chart_of_accounts SET level = 2 WHERE CHAR_LENGTH(code) = 5 OR CHAR_LENGTH(code) = 6");
    $db->exec("UPDATE chart_of_accounts SET level = 3 WHERE CHAR_LENGTH(code) = 7 OR CHAR_LENGTH(code) = 8");
    $db->exec("UPDATE chart_of_accounts SET level = 4 WHERE CHAR_LENGTH(code) > 8");

    // is_detail = 1 for the most detailed accounts (no children)
    $db->exec("UPDATE chart_of_accounts SET is_detail = 1");

    $results[] = "chart_of_accounts data updated (account_type, normal_balance, level)";
} catch (PDOException $e) {
    $results[] = "chart_of_accounts data update error: " . $e->getMessage();
}

// ============================================================
// 8. Seed default Taiwan standard accounts if table is empty or missing key accounts
// ============================================================
$seedAccounts = array(
    // Assets (1xxx)
    array('1100', '現金', 'asset', null, 1, 1, 'debit', '現金及約當現金', 10),
    array('1102', '銀行存款', 'asset', null, 1, 1, 'debit', '銀行往來帳戶', 20),
    array('1103', '零用金', 'asset', null, 1, 1, 'debit', '各分處零用金', 30),
    array('1111', '應收票據', 'asset', null, 1, 1, 'debit', '', 40),
    array('1131', '應收帳款', 'asset', null, 1, 1, 'debit', '工程及維修應收帳款', 50),
    array('1141', '其他應收款', 'asset', null, 1, 1, 'debit', '', 60),
    array('1151', '存貨', 'asset', null, 1, 1, 'debit', '庫存材料及商品', 70),
    array('1161', '預付款項', 'asset', null, 1, 1, 'debit', '', 80),
    array('1171', '進項稅額', 'asset', null, 1, 1, 'debit', '營業稅進項稅額', 85),
    array('1172', '留抵稅額', 'asset', null, 1, 1, 'debit', '留抵稅額', 86),
    array('1211', '土地', 'asset', null, 1, 1, 'debit', '', 100),
    array('1221', '房屋及建築', 'asset', null, 1, 1, 'debit', '', 110),
    array('1231', '機械設備', 'asset', null, 1, 1, 'debit', '', 120),
    array('1241', '運輸設備', 'asset', null, 1, 1, 'debit', '車輛', 130),
    array('1251', '辦公設備', 'asset', null, 1, 1, 'debit', '電腦及辦公器具', 140),
    array('1261', '累計折舊-房屋', 'asset', null, 1, 1, 'credit', '', 150),
    array('1262', '累計折舊-機械', 'asset', null, 1, 1, 'credit', '', 160),
    array('1263', '累計折舊-運輸', 'asset', null, 1, 1, 'credit', '', 170),
    array('1264', '累計折舊-辦公', 'asset', null, 1, 1, 'credit', '', 180),

    // Liabilities (2xxx)
    array('2101', '應付帳款', 'liability', null, 1, 1, 'credit', '廠商應付款', 200),
    array('2111', '應付票據', 'liability', null, 1, 1, 'credit', '', 210),
    array('2121', '預收款項', 'liability', null, 1, 1, 'credit', '', 220),
    array('2131', '應付費用', 'liability', null, 1, 1, 'credit', '應付薪資、勞健保等', 230),
    array('2132', '應付薪資', 'liability', null, 1, 1, 'credit', '', 235),
    array('2133', '應付勞健保', 'liability', null, 1, 1, 'credit', '', 236),
    array('2141', '其他應付款', 'liability', null, 1, 1, 'credit', '', 240),
    array('2151', '銷項稅額', 'liability', null, 1, 1, 'credit', '營業稅銷項稅額', 245),
    array('2152', '應付營業稅', 'liability', null, 1, 1, 'credit', '', 246),
    array('2161', '代扣所得稅', 'liability', null, 1, 1, 'credit', '', 250),
    array('2171', '暫收款', 'liability', null, 1, 1, 'credit', '', 260),
    array('2201', '長期借款', 'liability', null, 1, 1, 'credit', '銀行長期借款', 300),
    array('2211', '短期借款', 'liability', null, 1, 1, 'credit', '', 290),

    // Equity (3xxx)
    array('3101', '股本', 'equity', null, 1, 1, 'credit', '實收資本', 400),
    array('3201', '資本公積', 'equity', null, 1, 1, 'credit', '', 410),
    array('3301', '保留盈餘', 'equity', null, 1, 1, 'credit', '累積盈虧', 420),
    array('3311', '法定盈餘公積', 'equity', null, 1, 1, 'credit', '', 430),
    array('3401', '本期損益', 'equity', null, 1, 1, 'credit', '', 450),

    // Revenue (4xxx)
    array('4101', '工程收入', 'revenue', null, 1, 1, 'credit', '弱電工程收入', 500),
    array('4102', '維修收入', 'revenue', null, 1, 1, 'credit', '維修服務收入', 510),
    array('4103', '保養收入', 'revenue', null, 1, 1, 'credit', '定期保養收入', 520),
    array('4104', '設備銷售收入', 'revenue', null, 1, 1, 'credit', '設備買賣收入', 530),
    array('4201', '其他營業收入', 'revenue', null, 1, 1, 'credit', '', 540),
    array('4301', '營業折讓', 'revenue', null, 1, 1, 'debit', '銷貨折讓', 550),

    // COGS (5xxx)
    array('5101', '工程成本', 'expense', null, 1, 1, 'debit', '工程直接成本', 600),
    array('5102', '直接人工', 'expense', null, 1, 1, 'debit', '施工人員薪資', 610),
    array('5103', '直接材料', 'expense', null, 1, 1, 'debit', '工程用料', 620),
    array('5104', '外包費用', 'expense', null, 1, 1, 'debit', '外包工程費', 630),
    array('5105', '施工費', 'expense', null, 1, 1, 'debit', '', 640),
    array('5106', '點工費', 'expense', null, 1, 1, 'debit', '跨店點工費', 645),
    array('5201', '其他營業成本', 'expense', null, 1, 1, 'debit', '', 650),

    // Operating Expenses (6xxx)
    array('6101', '薪資費用', 'expense', null, 1, 1, 'debit', '行管人員薪資', 700),
    array('6102', '勞健保費', 'expense', null, 1, 1, 'debit', '勞保健保費用', 710),
    array('6103', '租金費用', 'expense', null, 1, 1, 'debit', '辦公室租金', 720),
    array('6104', '水電費', 'expense', null, 1, 1, 'debit', '', 730),
    array('6105', '交通費', 'expense', null, 1, 1, 'debit', '出差交通費', 740),
    array('6106', '通訊費', 'expense', null, 1, 1, 'debit', '電話網路費', 750),
    array('6107', '文具用品', 'expense', null, 1, 1, 'debit', '辦公用品', 760),
    array('6108', '修繕費', 'expense', null, 1, 1, 'debit', '', 770),
    array('6109', '折舊費', 'expense', null, 1, 1, 'debit', '', 780),
    array('6110', '保險費', 'expense', null, 1, 1, 'debit', '', 790),
    array('6111', '交際費', 'expense', null, 1, 1, 'debit', '', 800),
    array('6112', '廣告費', 'expense', null, 1, 1, 'debit', '', 810),
    array('6113', '雜費', 'expense', null, 1, 1, 'debit', '其他營業費用', 820),
    array('6114', '伙食費', 'expense', null, 1, 1, 'debit', '', 825),
    array('6115', '職工福利', 'expense', null, 1, 1, 'debit', '', 830),
    array('6116', '稅捐', 'expense', null, 1, 1, 'debit', '', 835),
    array('6117', '訓練費', 'expense', null, 1, 1, 'debit', '', 840),
    array('6118', '書報費', 'expense', null, 1, 1, 'debit', '', 845),
    array('6119', '加油費', 'expense', null, 1, 1, 'debit', '車輛加油費', 850),
    array('6120', '停車費', 'expense', null, 1, 1, 'debit', '', 855),
    array('6121', '郵電費', 'expense', null, 1, 1, 'debit', '', 860),
    array('6122', '勞務費', 'expense', null, 1, 1, 'debit', '', 865),

    // Non-operating (7xxx)
    array('7101', '利息收入', 'revenue', null, 1, 1, 'credit', '', 900),
    array('7102', '利息支出', 'expense', null, 1, 1, 'debit', '', 910),
    array('7103', '匯兌損益', 'revenue', null, 1, 1, 'credit', '', 920),
    array('7104', '處分資產損益', 'revenue', null, 1, 1, 'credit', '', 930),
    array('7105', '其他收入', 'revenue', null, 1, 1, 'credit', '', 940),
    array('7106', '其他支出', 'expense', null, 1, 1, 'debit', '', 950),

    // Tax
    array('8101', '所得稅費用', 'expense', null, 1, 1, 'debit', '', 990),
);

$seedStmt = $db->prepare("INSERT INTO chart_of_accounts (code, name, account_type, parent_id, level, is_detail, normal_balance, description, sort_order, account_code, account_name, level1) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE account_type = VALUES(account_type), normal_balance = VALUES(normal_balance), description = VALUES(description), sort_order = VALUES(sort_order), account_code = VALUES(account_code), account_name = VALUES(account_name), is_detail = VALUES(is_detail), level = VALUES(level)");

$seeded = 0;
foreach ($seedAccounts as $sa) {
    // Derive level1 from code prefix
    $codePrefix = substr($sa[0], 0, 1);
    $level1Map = array(
        '1' => '資產', '2' => '負債', '3' => '權益',
        '4' => '收入', '5' => '成本', '6' => '費用',
        '7' => '營業外收支', '8' => '所得稅',
    );
    $level1 = isset($level1Map[$codePrefix]) ? $level1Map[$codePrefix] : '';

    try {
        $seedStmt->execute(array(
            $sa[0], $sa[1], $sa[2], $sa[3], $sa[4], $sa[5], $sa[6], $sa[7], $sa[8],
            $sa[0], $sa[1], $level1
        ));
        $seeded++;
    } catch (PDOException $e) {
        // skip duplicates
    }
}
$results[] = "Seeded {$seeded} standard accounts";

// ============================================================
// 9. Seed cost_centers from branches
// ============================================================
try {
    $branches = $db->query("SELECT id, code, name FROM branches WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    $ccStmt = $db->prepare("INSERT INTO cost_centers (code, name, type, branch_id) VALUES (?, ?, 'branch', ?) ON DUPLICATE KEY UPDATE name = VALUES(name), branch_id = VALUES(branch_id)");
    $ccCount = 0;
    foreach ($branches as $br) {
        $ccCode = !empty($br['code']) ? $br['code'] : 'BR' . $br['id'];
        $ccStmt->execute(array($ccCode, $br['name'], $br['id']));
        $ccCount++;
    }
    $results[] = "Seeded {$ccCount} cost centers from branches";
} catch (PDOException $e) {
    $results[] = "Cost centers seed error: " . $e->getMessage();
}

// ============================================================
// 10. Number sequences
// ============================================================
$seqModules = array(
    array('journal_entries', '傳票', 'JV'),
    array('purchase_invoices', '進項發票', 'PI'),
    array('sales_invoices', '銷項發票', 'SI'),
);
foreach ($seqModules as $seq) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM number_sequences WHERE module = ?");
        $stmt->execute(array($seq[0]));
        if ((int)$stmt->fetchColumn() === 0) {
            $db->prepare("INSERT INTO number_sequences (module, module_label, prefix, date_format, `separator`, seq_digits) VALUES (?, ?, ?, 'Ymd', '-', 3)")
               ->execute(array($seq[0], $seq[1], $seq[2]));
            $results[] = "{$seq[0]} sequence created";
        } else {
            $results[] = "{$seq[0]} sequence exists, skip";
        }
    } catch (PDOException $e) {
        $results[] = "{$seq[0]} sequence error: " . $e->getMessage();
    }
}

// Output
echo "<h2>Migration 049 - ERP Accounting Foundation</h2><ul>";
foreach ($results as $r) {
    $color = (strpos($r, 'error') !== false || strpos($r, 'Error') !== false) ? 'red' : 'green';
    echo "<li style='color:{$color}'>" . htmlspecialchars($r) . "</li>";
}
echo "</ul><p><a href='/accounting.php'>Go to Accounting</a></p>";
