<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

// 1. Fix inventory table: ADD min_qty
try {
    $db->exec("ALTER TABLE inventory ADD COLUMN min_qty INT DEFAULT 0");
    $results[] = "inventory.min_qty 欄位已新增";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        $results[] = "inventory.min_qty 已存在，跳過";
    } else {
        $results[] = "inventory.min_qty 錯誤: " . $e->getMessage();
    }
}

// 2. Fix inventory_transactions: ADD qty_after
try {
    $db->exec("ALTER TABLE inventory_transactions ADD COLUMN qty_after DECIMAL(10,2) DEFAULT NULL");
    $results[] = "inventory_transactions.qty_after 欄位已新增";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        $results[] = "inventory_transactions.qty_after 已存在，跳過";
    } else {
        $results[] = "inventory_transactions.qty_after 錯誤: " . $e->getMessage();
    }
}

// 3. goods_receipts table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS goods_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gr_number VARCHAR(50) NOT NULL,
        gr_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT '草稿' COMMENT '草稿/待確認/已確認/已取消',
        po_id INT DEFAULT NULL COMMENT '關聯採購單ID',
        po_number VARCHAR(50) DEFAULT NULL,
        vendor_id INT DEFAULT NULL,
        vendor_name VARCHAR(200) DEFAULT NULL,
        warehouse_id INT DEFAULT NULL,
        receiver_name VARCHAR(100) DEFAULT NULL COMMENT '收貨人',
        note TEXT DEFAULT NULL,
        total_qty DECIMAL(10,2) DEFAULT 0,
        total_amount DECIMAL(12,2) DEFAULT 0,
        confirmed_by INT DEFAULT NULL,
        confirmed_at DATETIME DEFAULT NULL,
        created_by INT NOT NULL,
        updated_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        UNIQUE KEY uk_gr_number (gr_number),
        KEY idx_status (status),
        KEY idx_po_id (po_id),
        KEY idx_gr_date (gr_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "goods_receipts 資料表已建立";
} catch (PDOException $e) {
    $results[] = "goods_receipts 錯誤: " . $e->getMessage();
}

// 4. goods_receipt_items table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS goods_receipt_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        goods_receipt_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        model VARCHAR(200) DEFAULT NULL,
        product_name VARCHAR(500) DEFAULT NULL,
        spec VARCHAR(500) DEFAULT NULL,
        unit VARCHAR(50) DEFAULT NULL,
        po_qty DECIMAL(10,2) DEFAULT 0 COMMENT '採購數量',
        received_qty DECIMAL(10,2) DEFAULT 0 COMMENT '本次收貨數量',
        unit_price DECIMAL(12,2) DEFAULT 0,
        amount DECIMAL(12,2) DEFAULT 0,
        note VARCHAR(500) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        KEY idx_goods_receipt_id (goods_receipt_id),
        KEY idx_product_id (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "goods_receipt_items 資料表已建立";
} catch (PDOException $e) {
    $results[] = "goods_receipt_items 錯誤: " . $e->getMessage();
}

// 5. stock_ins table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS stock_ins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        si_number VARCHAR(50) NOT NULL,
        si_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT '待確認' COMMENT '待確認/已確認/已取消',
        source_type VARCHAR(50) DEFAULT NULL COMMENT 'goods_receipt/manual',
        source_id INT DEFAULT NULL,
        source_number VARCHAR(50) DEFAULT NULL,
        warehouse_id INT DEFAULT NULL,
        note TEXT DEFAULT NULL,
        total_qty DECIMAL(10,2) DEFAULT 0,
        confirmed_by INT DEFAULT NULL,
        confirmed_at DATETIME DEFAULT NULL,
        created_by INT NOT NULL,
        updated_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        UNIQUE KEY uk_si_number (si_number),
        KEY idx_status (status),
        KEY idx_source (source_type, source_id),
        KEY idx_si_date (si_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "stock_ins 資料表已建立";
} catch (PDOException $e) {
    $results[] = "stock_ins 錯誤: " . $e->getMessage();
}

// 6. stock_in_items table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS stock_in_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stock_in_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        model VARCHAR(200) DEFAULT NULL,
        product_name VARCHAR(500) DEFAULT NULL,
        spec VARCHAR(500) DEFAULT NULL,
        unit VARCHAR(50) DEFAULT NULL,
        quantity DECIMAL(10,2) DEFAULT 0,
        unit_price DECIMAL(12,2) DEFAULT 0,
        note VARCHAR(500) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        KEY idx_stock_in_id (stock_in_id),
        KEY idx_product_id (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "stock_in_items 資料表已建立";
} catch (PDOException $e) {
    $results[] = "stock_in_items 錯誤: " . $e->getMessage();
}

// 7. delivery_orders + delivery_order_items tables
try {
    $db->exec("CREATE TABLE IF NOT EXISTS delivery_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        do_number VARCHAR(50) NOT NULL,
        do_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT '草稿',
        case_id INT DEFAULT NULL,
        case_name VARCHAR(200) DEFAULT NULL,
        warehouse_id INT DEFAULT NULL,
        delivery_address VARCHAR(500) DEFAULT NULL,
        receiver_name VARCHAR(100) DEFAULT NULL,
        note TEXT DEFAULT NULL,
        total_qty DECIMAL(10,2) DEFAULT 0,
        confirmed_by INT DEFAULT NULL,
        confirmed_at DATETIME DEFAULT NULL,
        created_by INT NOT NULL,
        updated_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        UNIQUE KEY uk_do_number (do_number),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "delivery_orders 資料表已建立";
} catch (PDOException $e) {
    $results[] = "delivery_orders 錯誤: " . $e->getMessage();
}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS delivery_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_order_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        model VARCHAR(200) DEFAULT NULL,
        product_name VARCHAR(500) DEFAULT NULL,
        spec VARCHAR(500) DEFAULT NULL,
        unit VARCHAR(50) DEFAULT NULL,
        quantity DECIMAL(10,2) DEFAULT 0,
        note VARCHAR(500) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        KEY idx_delivery_order_id (delivery_order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "delivery_order_items 資料表已建立";
} catch (PDOException $e) {
    $results[] = "delivery_order_items 錯誤: " . $e->getMessage();
}

// 8. stock_outs + stock_out_items tables
try {
    $db->exec("CREATE TABLE IF NOT EXISTS stock_outs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        so_number VARCHAR(50) NOT NULL,
        so_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT '待確認' COMMENT '待確認/已確認/已取消',
        source_type VARCHAR(50) DEFAULT NULL COMMENT 'delivery_order/manual/case',
        source_id INT DEFAULT NULL,
        source_number VARCHAR(50) DEFAULT NULL,
        warehouse_id INT DEFAULT NULL,
        note TEXT DEFAULT NULL,
        total_qty DECIMAL(10,2) DEFAULT 0,
        confirmed_by INT DEFAULT NULL,
        confirmed_at DATETIME DEFAULT NULL,
        created_by INT NOT NULL,
        updated_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        UNIQUE KEY uk_so_number (so_number),
        KEY idx_status (status),
        KEY idx_source (source_type, source_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "stock_outs 資料表已建立";
} catch (PDOException $e) {
    $results[] = "stock_outs 錯誤: " . $e->getMessage();
}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS stock_out_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stock_out_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        model VARCHAR(200) DEFAULT NULL,
        product_name VARCHAR(500) DEFAULT NULL,
        spec VARCHAR(500) DEFAULT NULL,
        unit VARCHAR(50) DEFAULT NULL,
        quantity DECIMAL(10,2) DEFAULT 0,
        unit_price DECIMAL(12,2) DEFAULT 0,
        note VARCHAR(500) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        KEY idx_stock_out_id (stock_out_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "stock_out_items 資料表已建立";
} catch (PDOException $e) {
    $results[] = "stock_out_items 錯誤: " . $e->getMessage();
}

// 9. returns + return_items tables
try {
    $db->exec("CREATE TABLE IF NOT EXISTS returns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_number VARCHAR(50) NOT NULL,
        return_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT '草稿',
        return_type VARCHAR(20) DEFAULT 'vendor' COMMENT 'vendor=退貨給廠商, customer=客戶退貨',
        vendor_id INT DEFAULT NULL,
        vendor_name VARCHAR(200) DEFAULT NULL,
        warehouse_id INT DEFAULT NULL,
        po_id INT DEFAULT NULL,
        gr_id INT DEFAULT NULL,
        note TEXT DEFAULT NULL,
        total_qty DECIMAL(10,2) DEFAULT 0,
        total_amount DECIMAL(12,2) DEFAULT 0,
        confirmed_by INT DEFAULT NULL,
        confirmed_at DATETIME DEFAULT NULL,
        created_by INT NOT NULL,
        updated_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        UNIQUE KEY uk_return_number (return_number),
        KEY idx_status (status),
        KEY idx_return_type (return_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "returns 資料表已建立";
} catch (PDOException $e) {
    $results[] = "returns 錯誤: " . $e->getMessage();
}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS return_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        model VARCHAR(200) DEFAULT NULL,
        product_name VARCHAR(500) DEFAULT NULL,
        spec VARCHAR(500) DEFAULT NULL,
        unit VARCHAR(50) DEFAULT NULL,
        quantity DECIMAL(10,2) DEFAULT 0,
        unit_price DECIMAL(12,2) DEFAULT 0,
        amount DECIMAL(12,2) DEFAULT 0,
        reason VARCHAR(500) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        KEY idx_return_id (return_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = "return_items 資料表已建立";
} catch (PDOException $e) {
    $results[] = "return_items 錯誤: " . $e->getMessage();
}

// Insert number sequences for new modules
$seqModules = array(
    array('goods_receipts', '進貨單', 'GR'),
    array('stock_ins', '入庫單', 'SI'),
    array('stock_outs', '出庫單', 'SO'),
    array('delivery_orders', '出貨單', 'DO'),
    array('returns', '退貨單', 'RT'),
);
foreach ($seqModules as $seq) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM number_sequences WHERE module = ?");
        $stmt->execute(array($seq[0]));
        if ((int)$stmt->fetchColumn() === 0) {
            $db->prepare("INSERT INTO number_sequences (module, module_label, prefix, date_format, `separator`, seq_digits) VALUES (?, ?, ?, 'Ymd', '-', 3)")
               ->execute(array($seq[0], $seq[1], $seq[2]));
            $results[] = "{$seq[0]} 編號序列已建立";
        } else {
            $results[] = "{$seq[0]} 編號序列已存在，跳過";
        }
    } catch (PDOException $e) {
        $results[] = "{$seq[0]} 序列錯誤: " . $e->getMessage();
    }
}

echo "<h2>Migration 048 - 進貨單/入庫單/出貨單/出庫單/退貨單</h2><ul>";
foreach ($results as $r) {
    $color = (strpos($r, '錯誤') !== false) ? 'red' : 'green';
    echo "<li style='color:{$color}'>{$r}</li>";
}
echo "</ul><p><a href='/goods_receipts.php'>← 進貨單</a> | <a href='/stock_ins.php'>入庫單</a> | <a href='/inventory.php'>庫存管理</a></p>";
