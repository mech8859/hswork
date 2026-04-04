<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;

echo "<h3>Migration 020 - Step {$step}</h3>";

if ($step === 0) {
    // customers already done, create related tables
    try {
    $db->exec("CREATE TABLE IF NOT EXISTS customer_deals (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NOT NULL,
        case_id INT UNSIGNED DEFAULT NULL,
        site_address VARCHAR(200) DEFAULT NULL,
        deal_amount DECIMAL(12,2) DEFAULT NULL,
        completion_date DATE DEFAULT NULL,
        warranty_date DATE DEFAULT NULL,
        quotation_file VARCHAR(200) DEFAULT NULL,
        contract_file VARCHAR(200) DEFAULT NULL,
        note TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cd_c (customer_id),
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK: customer_deals<br>";
    } catch (Exception $e) { echo "ERR: " . $e->getMessage() . "<br>"; }

    try {
    $db->exec("CREATE TABLE IF NOT EXISTS customer_transactions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NOT NULL,
        transaction_date DATE NOT NULL,
        description VARCHAR(200) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        note TEXT,
        image_path VARCHAR(200) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ct_c (customer_id),
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK: customer_transactions<br>";
    } catch (Exception $e) { echo "ERR: " . $e->getMessage() . "<br>"; }

    echo "<br><a href='?step=1'>Next: Step 1 (business_calendar + case_visits)</a>";

} elseif ($step === 1) {
    try {
    $db->exec("CREATE TABLE IF NOT EXISTS business_calendar (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_date DATE NOT NULL,
        staff_id INT UNSIGNED NOT NULL,
        case_id INT UNSIGNED DEFAULT NULL,
        customer_id INT UNSIGNED DEFAULT NULL,
        customer_name VARCHAR(100) DEFAULT NULL,
        activity_type ENUM('visit','survey','follow_up','quotation','signing','other') NOT NULL DEFAULT 'visit',
        phone VARCHAR(30) DEFAULT NULL,
        region VARCHAR(20) DEFAULT NULL,
        address VARCHAR(200) DEFAULT NULL,
        start_time TIME DEFAULT NULL,
        end_time TIME DEFAULT NULL,
        note TEXT,
        status ENUM('planned','completed','cancelled') NOT NULL DEFAULT 'planned',
        result TEXT,
        created_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_bc_d (event_date),
        INDEX idx_bc_s (staff_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK: business_calendar<br>";
    } catch (Exception $e) { echo "ERR: " . $e->getMessage() . "<br>"; }

    try {
    $db->exec("CREATE TABLE IF NOT EXISTS case_visits (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NOT NULL,
        visit_date DATE NOT NULL,
        visit_type ENUM('phone','visit','survey','other') NOT NULL DEFAULT 'visit',
        staff_id INT UNSIGNED DEFAULT NULL,
        note TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cv_c (case_id),
        FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK: case_visits<br>";
    } catch (Exception $e) { echo "ERR: " . $e->getMessage() . "<br>"; }

    echo "<br><a href='?step=2'>Next: Step 2 (branches expand)</a>";

} elseif ($step === 2) {
    // branches expand
    $cols = array('branch_type', 'company');
    foreach ($cols as $col) {
        try {
            $db->query("SELECT {$col} FROM branches LIMIT 1");
            echo "SKIP: branches.{$col}<br>";
        } catch (Exception $e) {
            $ddl = ($col === 'branch_type')
                ? "ALTER TABLE branches ADD COLUMN branch_type ENUM('branch','department','store') NOT NULL DEFAULT 'branch'"
                : "ALTER TABLE branches ADD COLUMN company VARCHAR(50) DEFAULT NULL";
            try { $db->exec($ddl); echo "OK: branches.{$col}<br>"; }
            catch (Exception $e2) { echo "ERR: " . $e2->getMessage() . "<br>"; }
        }
    }

    // new branches
    $nb = array(
        array('name' => '中區專案部', 'bt' => 'department', 'co' => '禾順監視數位'),
        array('name' => '中區技術組', 'bt' => 'department', 'co' => '禾順監視數位'),
        array('name' => '清水門市', 'bt' => 'store', 'co' => '理創(政遠企業)'),
        array('name' => '東區門市', 'bt' => 'store', 'co' => '理創(政遠企業)'),
    );
    foreach ($nb as $b) {
        $chk = $db->prepare("SELECT id FROM branches WHERE name = ?");
        $chk->execute(array($b['name']));
        if ($chk->fetch()) {
            echo "SKIP: " . $b['name'] . "<br>";
        } else {
            $ins = $db->prepare("INSERT INTO branches (name, branch_type, company, is_active) VALUES (?, ?, ?, 1)");
            $ins->execute(array($b['name'], $b['bt'], $b['co']));
            echo "OK: " . $b['name'] . "<br>";
        }
    }
    $db->exec("UPDATE branches SET company = '禾順監視數位' WHERE company IS NULL OR company = ''");
    echo "OK: branches updated<br>";

    echo "<br><a href='?step=3'>Next: Step 3 (cases columns part 1)</a>";

} elseif ($step === 3) {
    $cc = array(
        'case_type' => 'VARCHAR(30) DEFAULT NULL',
        'case_source' => 'VARCHAR(30) DEFAULT NULL',
        'company' => 'VARCHAR(50) DEFAULT NULL',
        'customer_name' => 'VARCHAR(100) DEFAULT NULL',
        'customer_phone' => 'VARCHAR(30) DEFAULT NULL',
        'customer_mobile' => 'VARCHAR(30) DEFAULT NULL',
        'customer_id' => 'INT UNSIGNED DEFAULT NULL',
        'contact_person' => 'VARCHAR(50) DEFAULT NULL',
        'city' => 'VARCHAR(20) DEFAULT NULL',
        'district' => 'VARCHAR(20) DEFAULT NULL',
        'stage' => 'TINYINT UNSIGNED NOT NULL DEFAULT 1',
        'sub_status' => 'VARCHAR(30) DEFAULT NULL',
        'sales_id' => 'INT UNSIGNED DEFAULT NULL',
        'site_progress' => 'VARCHAR(50) DEFAULT NULL',
        'completed_date' => 'DATE DEFAULT NULL',
        'sales_note' => 'TEXT',
        'lost_reason' => 'VARCHAR(100) DEFAULT NULL',
        'deal_date' => 'DATE DEFAULT NULL',
    );
    foreach ($cc as $col => $def) {
        try {
            $db->query("SELECT `{$col}` FROM cases LIMIT 1");
            echo "SKIP: cases.{$col}<br>";
        } catch (Exception $e) {
            try {
                $db->exec("ALTER TABLE cases ADD COLUMN `{$col}` {$def}");
                echo "OK: cases.{$col}<br>";
            } catch (Exception $e2) {
                echo "ERR: cases.{$col} " . $e2->getMessage() . "<br>";
            }
        }
    }
    echo "<br><a href='?step=4'>Next: Step 4 (cases columns part 2)</a>";

} elseif ($step === 4) {
    $cc = array(
        'deal_amount' => 'DECIMAL(12,2) DEFAULT NULL',
        'tax_included' => 'TINYINT(1) DEFAULT 0',
        'tax_amount' => 'DECIMAL(12,2) DEFAULT NULL',
        'total_amount' => 'DECIMAL(12,2) DEFAULT NULL',
        'deposit_amount' => 'DECIMAL(12,2) DEFAULT NULL',
        'deposit_date' => 'DATE DEFAULT NULL',
        'deposit_method' => 'VARCHAR(30) DEFAULT NULL',
        'balance_amount' => 'DECIMAL(12,2) DEFAULT NULL',
        'invoice_title' => 'VARCHAR(100) DEFAULT NULL',
        'tax_id_number' => 'VARCHAR(20) DEFAULT NULL',
        'settlement_date' => 'DATE DEFAULT NULL',
        'settlement_method' => 'VARCHAR(30) DEFAULT NULL',
        'settlement_confirmed' => 'TINYINT(1) DEFAULT 0',
        'est_start_date' => 'DATE DEFAULT NULL',
        'est_end_date' => 'DATE DEFAULT NULL',
        'est_workers' => 'INT DEFAULT NULL',
        'est_days' => 'INT DEFAULT NULL',
    );
    foreach ($cc as $col => $def) {
        try {
            $db->query("SELECT `{$col}` FROM cases LIMIT 1");
            echo "SKIP: cases.{$col}<br>";
        } catch (Exception $e) {
            try {
                $db->exec("ALTER TABLE cases ADD COLUMN `{$col}` {$def}");
                echo "OK: cases.{$col}<br>";
            } catch (Exception $e2) {
                echo "ERR: cases.{$col} " . $e2->getMessage() . "<br>";
            }
        }
    }

    // indexes
    try { $db->exec("ALTER TABLE cases ADD INDEX idx_cases_stage (stage)"); echo "OK: idx_cases_stage<br>"; } catch (Exception $e) { echo "SKIP: idx_cases_stage<br>"; }
    try { $db->exec("ALTER TABLE cases ADD INDEX idx_cases_customer_id (customer_id)"); echo "OK: idx_cases_customer_id<br>"; } catch (Exception $e) { echo "SKIP: idx_cases_customer_id<br>"; }
    try { $db->exec("ALTER TABLE cases ADD INDEX idx_cases_sales_id (sales_id)"); echo "OK: idx_cases_sales_id<br>"; } catch (Exception $e) { echo "SKIP: idx_cases_sales_id<br>"; }

    echo "<h3>ALL DONE!</h3>";
    echo '<a href="/customers.php">客戶管理</a> | <a href="/business_calendar.php">業務行事曆</a> | <a href="/cases.php">案件管理</a>';
}
