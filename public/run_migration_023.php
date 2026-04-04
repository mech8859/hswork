<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();

echo "<h3>Migration 023: dropdown_options + cases columns</h3>";

// 1. Create dropdown_options table
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `dropdown_options` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `category` VARCHAR(50) NOT NULL,
          `label` VARCHAR(200) NOT NULL,
          `sort_order` INT NOT NULL DEFAULT 0,
          `is_active` TINYINT(1) NOT NULL DEFAULT 1,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_cat_active (`category`, `is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ dropdown_options table created<br>";
} catch (Exception $e) {
    echo "dropdown_options: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// 2. Add columns to cases (ignore if exists)
$cols = array(
    "ADD COLUMN `system_type` VARCHAR(100) DEFAULT NULL",
    "ADD COLUMN `quote_amount` DECIMAL(12,0) DEFAULT NULL",
    "ADD COLUMN `notes` TEXT DEFAULT NULL",
);
foreach ($cols as $c) {
    try {
        $db->exec("ALTER TABLE `cases` {$c}");
        echo "✓ {$c}<br>";
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column') !== false) {
            echo "⊘ Column already exists, skip<br>";
        } else {
            echo "✗ " . htmlspecialchars($msg) . "<br>";
        }
    }
}

// 3. Seed dropdown options
$check = $db->query("SELECT COUNT(*) FROM dropdown_options")->fetchColumn();
if ($check > 0) {
    echo "<br>dropdown_options already has {$check} rows, skipping seed.<br>";
} else {
    echo "<br>Seeding dropdown_options...<br>";
    $seedFile = __DIR__ . '/../database/seed_dropdown_options.sql';
    if (file_exists($seedFile)) {
        $sql = file_get_contents($seedFile);
        // Split by semicolon but keep INSERT statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $count = 0;
        foreach ($statements as $stmt) {
            if (empty($stmt) || strpos($stmt, '--') === 0) continue;
            // Skip pure comment lines
            $lines = explode("\n", $stmt);
            $clean = array();
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '' && strpos($trimmed, '--') !== 0) {
                    $clean[] = $line;
                }
            }
            $cleanSql = implode("\n", $clean);
            if (empty(trim($cleanSql))) continue;
            try {
                $db->exec($cleanSql);
                $count++;
            } catch (Exception $e) {
                echo "Seed error: " . htmlspecialchars($e->getMessage()) . "<br>";
            }
        }
        echo "✓ Seeded {$count} statements<br>";
    } else {
        echo "seed_dropdown_options.sql not found<br>";
    }
}

$total = $db->query("SELECT COUNT(*) FROM dropdown_options")->fetchColumn();
echo "<br><b>dropdown_options total: {$total} rows</b><br>";
echo "<br><a href='/cases.php'>案件管理</a> | <a href='/dropdown_options.php'>選單管理</a>";
