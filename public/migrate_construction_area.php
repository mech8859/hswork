<?php
/**
 * Migration: Add construction_area to cases table
 * Temporary file - delete after execution
 */
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=vhost158992;charset=utf8mb4',
        'vhost158992',
        'Kas199306',
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    // Check if column already exists
    $cols = $pdo->query("SHOW COLUMNS FROM cases LIKE 'construction_area'")->fetchAll();
    if (count($cols) > 0) {
        echo "SKIP: construction_area column already exists.\n";
    } else {
        $pdo->exec("ALTER TABLE cases ADD COLUMN construction_area VARCHAR(30) DEFAULT NULL COMMENT '施工區域' AFTER address");
        echo "OK: construction_area column added to cases table.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
