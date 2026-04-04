-- Migration 027: 採購庫存模組
-- vendors, warehouses, inventory, requisitions, purchase_orders, warehouse_transfers + 子表 + 異動紀錄

-- 1. 廠商資料
CREATE TABLE IF NOT EXISTS vendors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_code VARCHAR(30) DEFAULT NULL COMMENT '廠商編號',
  name VARCHAR(100) NOT NULL COMMENT '廠商名稱',
  short_name VARCHAR(50) DEFAULT NULL COMMENT '廠商簡稱',
  tax_id VARCHAR(20) DEFAULT NULL COMMENT '統一編號',
  category VARCHAR(50) DEFAULT NULL COMMENT '廠商類別',
  service_items TEXT DEFAULT NULL COMMENT '廠商服務項目',
  contact_person VARCHAR(50) DEFAULT NULL COMMENT '聯絡窗口',
  phone VARCHAR(30) DEFAULT NULL,
  fax VARCHAR(30) DEFAULT NULL,
  email VARCHAR(100) DEFAULT NULL,
  postal_code VARCHAR(10) DEFAULT NULL COMMENT '郵遞區號',
  city_district VARCHAR(50) DEFAULT NULL COMMENT '縣市及鄉鎮地區',
  street_address VARCHAR(200) DEFAULT NULL COMMENT '街道地址',
  address VARCHAR(300) DEFAULT NULL COMMENT '完整地址',
  payment_method VARCHAR(30) DEFAULT NULL COMMENT '付款方式',
  payment_terms VARCHAR(50) DEFAULT NULL COMMENT '付款條件',
  settlement_day VARCHAR(20) DEFAULT NULL COMMENT '結帳日',
  invoice_method VARCHAR(30) DEFAULT NULL COMMENT '發票方式',
  invoice_type VARCHAR(30) DEFAULT NULL COMMENT '發票種類',
  header1 VARCHAR(100) DEFAULT NULL COMMENT '抬頭1',
  tax_id1 VARCHAR(20) DEFAULT NULL COMMENT '統一編號(發票用)',
  header2 VARCHAR(100) DEFAULT NULL COMMENT '抬頭2',
  tax_id2 VARCHAR(20) DEFAULT NULL COMMENT '統一編號2',
  invoice_type2 VARCHAR(30) DEFAULT NULL COMMENT '發票種類2',
  note TEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_by VARCHAR(50) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vendor_code (vendor_code),
  INDEX idx_vendor_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='廠商資料';

-- 2. 倉庫
CREATE TABLE IF NOT EXISTS warehouses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NOT NULL COMMENT '倉庫代碼',
  name VARCHAR(50) NOT NULL COMMENT '倉庫名稱',
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='倉庫';

-- 種子資料
INSERT INTO warehouses (branch_id, code, name) VALUES
(1, 'WH-TZ', '潭子倉庫'),
(3, 'WH-YL', '員林倉庫'),
(2, 'WH-QS', '清水倉庫'),
(4, 'WH-DQ', '東區電子鎖倉庫'),
(5, 'WH-QD', '清水電子鎖倉庫');

-- 3. 庫存明細
CREATE TABLE IF NOT EXISTS inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  available_qty INT DEFAULT 0 COMMENT '可用數量',
  stock_qty INT DEFAULT 0 COMMENT '庫存數量',
  reserved_qty INT DEFAULT 0 COMMENT '已備貨數量',
  loaned_qty INT DEFAULT 0 COMMENT '借出數量',
  display_qty INT DEFAULT 0 COMMENT '展示數量',
  audit_qty INT DEFAULT NULL COMMENT '盤點數量',
  locked TINYINT(1) DEFAULT 0 COMMENT '鎖定',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_product_warehouse (product_id, warehouse_id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='庫存明細';

-- 4. 請購單
CREATE TABLE IF NOT EXISTS requisitions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requisition_number VARCHAR(30) NOT NULL COMMENT 'PR-yyyymmdd-nnn',
  requisition_date DATE NOT NULL,
  requester_name VARCHAR(50) DEFAULT NULL COMMENT '請購人員',
  branch_id INT UNSIGNED DEFAULT NULL COMMENT '所屬分公司',
  sales_name VARCHAR(50) DEFAULT NULL COMMENT '負責業務',
  urgency VARCHAR(20) DEFAULT '一般件' COMMENT '緊急程度',
  case_name VARCHAR(100) DEFAULT NULL COMMENT '案名',
  quotation_number VARCHAR(30) DEFAULT NULL COMMENT '報價確認單號',
  vendor_name VARCHAR(100) DEFAULT NULL COMMENT '廠商',
  expected_date DATE DEFAULT NULL COMMENT '期望到貨日',
  status VARCHAR(20) DEFAULT '簽核中' COMMENT '簽核中/簽核完成/退回/已轉採購/取消',
  approval_user VARCHAR(50) DEFAULT NULL COMMENT '覆核人員',
  approval_date DATE DEFAULT NULL,
  approval_note TEXT,
  next_approver VARCHAR(50) DEFAULT NULL COMMENT '下一位簽核人',
  note TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_req_number (requisition_number),
  INDEX idx_req_date (requisition_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='請購單';

-- 5. 請購明細
CREATE TABLE IF NOT EXISTS requisition_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requisition_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED DEFAULT NULL,
  model VARCHAR(100) DEFAULT NULL COMMENT '商品型號',
  product_name VARCHAR(255) DEFAULT NULL COMMENT '商品名稱',
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  purpose VARCHAR(200) DEFAULT NULL COMMENT '用途說明',
  approved_qty DECIMAL(10,2) DEFAULT NULL COMMENT '覆核數量',
  sort_order INT DEFAULT 0,
  FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='請購單明細';

-- 6. 採購單
CREATE TABLE IF NOT EXISTS purchase_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  po_number VARCHAR(30) NOT NULL COMMENT 'PUR-yyyymmdd-nnn',
  po_date DATE NOT NULL,
  status VARCHAR(20) DEFAULT '尚未進貨' COMMENT '尚未進貨/確認進貨/確認付款/取消',
  purchaser_name VARCHAR(50) DEFAULT NULL COMMENT '採購人員',
  requisition_id INT UNSIGNED DEFAULT NULL COMMENT '來自請購單',
  requisition_number VARCHAR(30) DEFAULT NULL COMMENT '來自請購單號',
  receiving_date DATE DEFAULT NULL COMMENT '進貨日期',
  case_name VARCHAR(100) DEFAULT NULL,
  branch_id INT UNSIGNED DEFAULT NULL,
  sales_name VARCHAR(50) DEFAULT NULL COMMENT '負責業務',
  urgency VARCHAR(20) DEFAULT '一般件',
  -- 廠商快照
  vendor_id INT UNSIGNED DEFAULT NULL,
  vendor_code VARCHAR(30) DEFAULT NULL,
  vendor_name VARCHAR(100) DEFAULT NULL,
  vendor_tax_id VARCHAR(20) DEFAULT NULL,
  vendor_contact VARCHAR(50) DEFAULT NULL,
  vendor_phone VARCHAR(30) DEFAULT NULL,
  vendor_fax VARCHAR(30) DEFAULT NULL,
  vendor_email VARCHAR(100) DEFAULT NULL,
  vendor_address VARCHAR(300) DEFAULT NULL,
  -- 付款
  payment_method VARCHAR(30) DEFAULT NULL,
  payment_terms VARCHAR(50) DEFAULT NULL,
  invoice_method VARCHAR(30) DEFAULT NULL,
  invoice_type VARCHAR(30) DEFAULT NULL,
  payment_date DATE DEFAULT NULL,
  is_paid TINYINT(1) DEFAULT 0,
  paid_amount DECIMAL(12,0) DEFAULT 0,
  -- 銀行
  bank_code VARCHAR(10) DEFAULT NULL,
  bank_name VARCHAR(50) DEFAULT NULL,
  bank_branch VARCHAR(50) DEFAULT NULL,
  account_name VARCHAR(50) DEFAULT NULL,
  account_number VARCHAR(30) DEFAULT NULL,
  -- 金額
  subtotal DECIMAL(12,0) DEFAULT 0,
  tax_type VARCHAR(20) DEFAULT '營業稅' COMMENT '課稅別',
  tax_rate DECIMAL(4,2) DEFAULT 5.00,
  tax_amount DECIMAL(12,0) DEFAULT 0,
  shipping_fee DECIMAL(12,0) DEFAULT 0,
  total_amount DECIMAL(12,0) DEFAULT 0,
  this_amount DECIMAL(12,0) DEFAULT 0 COMMENT '本單金額',
  discount_untaxed DECIMAL(12,0) DEFAULT NULL COMMENT '優惠價未稅',
  discount_taxed DECIMAL(12,0) DEFAULT NULL COMMENT '優惠價含稅',
  -- 流程
  use_payment_flow TINYINT(1) DEFAULT 0 COMMENT '走請款流程',
  convert_to_receiving TINYINT(1) DEFAULT 0 COMMENT '是否轉進貨',
  is_cancelled TINYINT(1) DEFAULT 0 COMMENT '取消退款',
  refund_date DATE DEFAULT NULL,
  delivery_location VARCHAR(100) DEFAULT NULL COMMENT '交貨地點',
  required_date DATE DEFAULT NULL COMMENT '要求到貨日',
  promised_date DATE DEFAULT NULL COMMENT '承諾到貨日',
  note TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_po_number (po_number),
  INDEX idx_po_date (po_date),
  FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='採購單';

-- 7. 採購明細
CREATE TABLE IF NOT EXISTS purchase_order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED DEFAULT NULL,
  model VARCHAR(100) DEFAULT NULL,
  product_name VARCHAR(255) DEFAULT NULL COMMENT '商品名稱',
  spec VARCHAR(200) DEFAULT NULL COMMENT '規格',
  unit_price DECIMAL(12,2) DEFAULT 0,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  amount DECIMAL(12,0) DEFAULT 0,
  delivery_date DATE DEFAULT NULL COMMENT '交貨日期',
  received_qty DECIMAL(10,2) DEFAULT 0 COMMENT '已收數量',
  sort_order INT DEFAULT 0,
  FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='採購單明細';

-- 8. 調撥單
CREATE TABLE IF NOT EXISTS warehouse_transfers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_number VARCHAR(30) NOT NULL COMMENT 'ST-yyyymmdd-nnn',
  transfer_date DATE NOT NULL COMMENT '出貨日期',
  from_branch_id INT UNSIGNED DEFAULT NULL,
  to_branch_id INT UNSIGNED DEFAULT NULL,
  from_warehouse_id INT UNSIGNED DEFAULT NULL,
  to_warehouse_id INT UNSIGNED DEFAULT NULL,
  from_warehouse_name VARCHAR(50) DEFAULT NULL,
  to_warehouse_name VARCHAR(50) DEFAULT NULL,
  status VARCHAR(20) DEFAULT '待出貨' COMMENT '待出貨/已出貨/已到貨/完成/取消',
  update_inventory TINYINT(1) DEFAULT 0,
  shipper_name VARCHAR(50) DEFAULT NULL COMMENT '出貨人員',
  receiver_name VARCHAR(50) DEFAULT NULL COMMENT '進貨人員',
  total_amount DECIMAL(12,0) DEFAULT 0,
  note TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_transfer_number (transfer_number),
  INDEX idx_transfer_date (transfer_date),
  FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='倉庫調撥';

-- 9. 調撥明細
CREATE TABLE IF NOT EXISTS warehouse_transfer_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED DEFAULT NULL,
  model VARCHAR(100) DEFAULT NULL,
  product_name VARCHAR(255) DEFAULT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) DEFAULT 0,
  amount DECIMAL(12,0) DEFAULT 0,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (transfer_id) REFERENCES warehouse_transfers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='調撥單明細';

-- 10. 庫存異動紀錄
CREATE TABLE IF NOT EXISTS inventory_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  type VARCHAR(20) NOT NULL COMMENT 'purchase_in/transfer_out/transfer_in/adjust',
  quantity DECIMAL(10,2) NOT NULL COMMENT '正數增加/負數減少',
  reference_type VARCHAR(30) DEFAULT NULL COMMENT 'purchase_order/warehouse_transfer/manual',
  reference_id INT UNSIGNED DEFAULT NULL,
  note VARCHAR(200) DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_inv_product (product_id),
  INDEX idx_inv_warehouse (warehouse_id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='庫存異動紀錄';
