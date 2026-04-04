<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// Check table structure first
echo "<pre>";
$cols = $pdo->query("DESCRIBE number_sequences")->fetchAll(PDO::FETCH_COLUMN);
echo "Columns: " . implode(', ', $cols) . "\n\n";

try {
    $stmt = $pdo->prepare("INSERT INTO number_sequences (module, module_label, prefix, date_format, `separator`, seq_digits, last_sequence, last_reset_key) VALUES (?, ?, ?, ?, ?, ?, 0, '')");
    $stmt->execute(array('vendors', '廠商編號', 'VD', 'Ymd', '-', 3));
    echo "OK: 廠商編號 added\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "SKIP: already exists\n";
    } else {
        echo "ERR: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n</pre>";
