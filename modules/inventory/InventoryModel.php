<?php
/**
 * 庫存管理資料模型
 * 庫存查詢、庫存異動、異動記錄、倉庫管理、盤點、安全庫存
 */
class InventoryModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ============================================================
    // 靜態選項
    // ============================================================

    public static function transactionTypeOptions()
    {
        return array(
            'purchase_in'  => '採購入庫',
            'manual_in'    => '手動入庫',
            'manual_out'   => '手動出庫',
            'transfer_in'  => '調撥入庫',
            'transfer_out' => '調撥出庫',
            'adjust'       => '盤點調整',
            'return_in'    => '退貨入庫',
            'case_out'     => '案件出庫',
            'reserve'      => '預扣庫存',
            'unreserve'    => '取消預扣',
            'prepare'      => '備貨確認',
        );
    }

    public static function transactionTypeLabel($type)
    {
        $opts = self::transactionTypeOptions();
        return isset($opts[$type]) ? $opts[$type] : $type;
    }

    // ============================================================
    // 庫存查詢
    // ============================================================

    /**
     * 庫存列表（含篩選）
     * @param array $filters  warehouse_id, category_id, keyword, has_stock, low_stock
     */
    public function getInventoryList($filters = array())
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['warehouse_id'])) {
            $where .= ' AND i.warehouse_id = ?';
            $params[] = $filters['warehouse_id'];
        }
        if (!empty($filters['category_id'])) {
            $catIds = array((int)$filters['category_id']);
            $queue = array((int)$filters['category_id']);
            while (!empty($queue)) {
                $pid = array_shift($queue);
                $subStmt = $this->db->prepare('SELECT id FROM product_categories WHERE parent_id = ?');
                $subStmt->execute(array($pid));
                foreach ($subStmt->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                    $catIds[] = (int)$cid;
                    $queue[] = (int)$cid;
                }
            }
            $ph = implode(',', array_fill(0, count($catIds), '?'));
            $where .= " AND p.category_id IN ($ph)";
            $params = array_merge($params, $catIds);
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (p.name LIKE ? OR p.model LIKE ? OR p.vendor_model LIKE ? OR p.brand LIKE ? OR p.supplier LIKE ? OR pc.name LIKE ? OR pc2.name LIKE ? OR pc3.name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            for ($ki = 0; $ki < 8; $ki++) { $params[] = $kw; }
        }
        if (!empty($filters['has_stock'])) {
            $where .= ' AND i.stock_qty > 0';
        }
        if (!empty($filters['low_stock'])) {
            $where .= ' AND i.min_qty > 0 AND i.stock_qty <= i.min_qty';
        }

        // 先取總數
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM inventory i
            LEFT JOIN products p ON i.product_id = p.id
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            LEFT JOIN product_categories pc2 ON pc.parent_id = pc2.id
            LEFT JOIN product_categories pc3 ON pc2.parent_id = pc3.id
            LEFT JOIN warehouses w ON i.warehouse_id = w.id
            WHERE {$where}
        ");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        // 分頁
        $perPage = !empty($filters['per_page']) ? (int)$filters['per_page'] : 100;
        $page = !empty($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare("
            SELECT i.*, p.name AS product_name, p.model AS product_model,
                   p.category_id, p.unit, p.cost,
                   pc.name AS category_name,
                   pc.parent_id AS cat_parent_id,
                   pc2.name AS cat_parent_name,
                   pc2.parent_id AS cat_grandparent_id,
                   pc3.name AS cat_grandparent_name,
                   w.name AS warehouse_name
            FROM inventory i
            LEFT JOIN products p ON i.product_id = p.id
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            LEFT JOIN product_categories pc2 ON pc.parent_id = pc2.id
            LEFT JOIN product_categories pc3 ON pc2.parent_id = pc3.id
            LEFT JOIN warehouses w ON i.warehouse_id = w.id
            WHERE {$where}
            ORDER BY
                CASE
                    WHEN i.stock_qty > 0 AND COALESCE(pc3.name, pc2.name, pc.name) NOT IN ('線材&相關配件','監控相關配件') THEN 0
                    WHEN i.stock_qty > 0 AND COALESCE(pc3.name, pc2.name, pc.name) IN ('線材&相關配件','監控相關配件') THEN 1
                    ELSE 2
                END,
                i.stock_qty DESC, p.name
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array(
            'data' => $rows,
            'total' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($totalCount / $perPage)
        );
    }

    /**
     * 取得單一產品在所有倉庫的庫存
     */
    public function getProductInventory($productId)
    {
        $stmt = $this->db->prepare("
            SELECT i.*, w.name AS warehouse_name,
                   p.name AS product_name, p.model AS product_model,
                   p.cost, p.price AS sell_price, p.retail_price,
                   p.unit, p.category_id,
                   pc.name AS category_name,
                   pc.parent_id AS cat_parent_id,
                   pc2.name AS cat_parent_name,
                   pc2.parent_id AS cat_grandparent_id,
                   pc3.name AS cat_grandparent_name
            FROM inventory i
            LEFT JOIN warehouses w ON i.warehouse_id = w.id
            LEFT JOIN products p ON i.product_id = p.id
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            LEFT JOIN product_categories pc2 ON pc.parent_id = pc2.id
            LEFT JOIN product_categories pc3 ON pc2.parent_id = pc3.id
            WHERE i.product_id = ?
            ORDER BY w.name
        ");
        $stmt->execute(array($productId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 各倉庫庫存彙總
     */
    public function getWarehouseSummary()
    {
        $stmt = $this->db->query("
            SELECT w.id, w.name AS warehouse_name,
                   COALESCE(SUM(i.stock_qty), 0) AS total_stock_qty,
                   COALESCE(SUM(i.available_qty), 0) AS total_available_qty,
                   COUNT(DISTINCT i.product_id) AS product_count,
                   COALESCE(SUM(i.stock_qty * p.cost), 0) AS total_cost_value
            FROM warehouses w
            LEFT JOIN inventory i ON w.id = i.warehouse_id
            LEFT JOIN products p ON i.product_id = p.id
            WHERE w.is_active = 1
            GROUP BY w.id, w.name
            ORDER BY w.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 全域合計（不受分頁限制，受篩選條件影響）
     */
    public function getGrandTotal($filters = array())
    {
        $where = array('1=1');
        $params = array();
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'i.warehouse_id = ?';
            $params[] = $filters['warehouse_id'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = ?';
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(p.name LIKE ? OR p.model LIKE ?)';
            $params[] = '%' . $filters['keyword'] . '%';
            $params[] = '%' . $filters['keyword'] . '%';
        }
        if (!empty($filters['has_stock'])) {
            $where[] = 'i.stock_qty > 0';
        }
        $sql = "SELECT COALESCE(SUM(i.stock_qty), 0) AS total_qty, COALESCE(SUM(i.stock_qty * COALESCE(p.cost, 0)), 0) AS total_cost
                FROM inventory i
                LEFT JOIN products p ON i.product_id = p.id
                WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 取得單筆庫存記錄（含產品與倉庫資訊）
     */
    public function getInventoryDetail($id)
    {
        $stmt = $this->db->prepare("
            SELECT i.*, p.name AS product_name, p.model AS product_model,
                   p.category_id, p.unit, w.name AS warehouse_name
            FROM inventory i
            LEFT JOIN products p ON i.product_id = p.id
            LEFT JOIN warehouses w ON i.warehouse_id = w.id
            WHERE i.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 低庫存警示清單
     */
    public function getLowStockItems()
    {
        $stmt = $this->db->query("
            SELECT i.*, p.name AS product_name, p.model AS product_model, p.unit,
                   w.name AS warehouse_name
            FROM inventory i
            LEFT JOIN products p ON i.product_id = p.id
            LEFT JOIN warehouses w ON i.warehouse_id = w.id
            WHERE i.min_qty > 0 AND i.stock_qty <= i.min_qty
            ORDER BY (i.stock_qty - i.min_qty) ASC, p.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 低庫存數量（for badge）
     */
    public function getLowStockCount()
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM inventory WHERE min_qty > 0 AND stock_qty <= min_qty");
        return (int)$stmt->fetchColumn();
    }

    // ============================================================
    // 庫存異動
    // ============================================================

    /**
     * 調整庫存數量並寫入異動記錄
     */
    public function adjustStock($productId, $warehouseId, $quantity, $type, $refType, $refId, $note, $userId)
    {
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) $this->db->beginTransaction();
        try {
            $existing = $this->getInventoryByProductWarehouse($productId, $warehouseId);

            if ($existing) {
                $stmt = $this->db->prepare("
                    UPDATE inventory
                    SET stock_qty = stock_qty + ?, available_qty = available_qty + ?
                    WHERE product_id = ? AND warehouse_id = ?
                ");
                $stmt->execute(array($quantity, $quantity, $productId, $warehouseId));
                $inventoryId = $existing['id'];
                $qtyAfter = $existing['stock_qty'] + $quantity;
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO inventory (product_id, warehouse_id, stock_qty, available_qty)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute(array($productId, $warehouseId, $quantity, $quantity));
                $inventoryId = $this->db->lastInsertId();
                $qtyAfter = $quantity;
            }

            // 寫入異動記錄
            $stmtTx = $this->db->prepare("
                INSERT INTO inventory_transactions
                    (product_id, warehouse_id, type, quantity, qty_after, reference_type, reference_id, note, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtTx->execute(array(
                $productId,
                $warehouseId,
                $type,
                $quantity,
                $qtyAfter,
                !empty($refType) ? $refType : null,
                !empty($refId) ? $refId : null,
                !empty($note) ? $note : null,
                $userId,
            ));

            if ($ownTransaction) $this->db->commit();
            return $inventoryId;
        } catch (Exception $e) {
            if ($ownTransaction) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 預扣庫存：available_qty 減少, reserved_qty 增加（不動 stock_qty）
     */
    public function reserveStock($productId, $warehouseId, $qty, $refType, $refId, $note, $userId)
    {
        $this->db->prepare("
            UPDATE inventory SET available_qty = available_qty - ?, reserved_qty = reserved_qty + ?
            WHERE product_id = ? AND warehouse_id = ?
        ")->execute(array($qty, $qty, $productId, $warehouseId));

        $existing = $this->getInventoryByProductWarehouse($productId, $warehouseId);
        $qtyAfter = $existing ? $existing['stock_qty'] : 0;

        $this->db->prepare("
            INSERT INTO inventory_transactions (product_id, warehouse_id, type, quantity, qty_after, reference_type, reference_id, note, created_by, created_at)
            VALUES (?, ?, 'reserve', ?, ?, ?, ?, ?, ?, NOW())
        ")->execute(array($productId, $warehouseId, -$qty, $qtyAfter, $refType, $refId, $note, $userId));
    }

    /**
     * 取消預扣：available_qty 增加, reserved_qty 減少
     */
    public function unreserveStock($productId, $warehouseId, $qty, $refType, $refId, $note, $userId)
    {
        $this->db->prepare("
            UPDATE inventory SET available_qty = available_qty + ?, reserved_qty = GREATEST(reserved_qty - ?, 0)
            WHERE product_id = ? AND warehouse_id = ?
        ")->execute(array($qty, $qty, $productId, $warehouseId));

        $existing = $this->getInventoryByProductWarehouse($productId, $warehouseId);
        $qtyAfter = $existing ? $existing['stock_qty'] : 0;

        $this->db->prepare("
            INSERT INTO inventory_transactions (product_id, warehouse_id, type, quantity, qty_after, reference_type, reference_id, note, created_by, created_at)
            VALUES (?, ?, 'unreserve', ?, ?, ?, ?, ?, ?, NOW())
        ")->execute(array($productId, $warehouseId, $qty, $qtyAfter, $refType, $refId, $note, $userId));
    }

    /**
     * 預扣轉出庫：reserved_qty 減少, stock_qty 減少（available_qty 不動，因為已在預扣時扣過）
     */
    public function confirmReservedStock($productId, $warehouseId, $qty, $refType, $refId, $note, $userId)
    {
        $this->db->prepare("
            UPDATE inventory SET stock_qty = stock_qty - ?, reserved_qty = GREATEST(reserved_qty - ?, 0)
            WHERE product_id = ? AND warehouse_id = ?
        ")->execute(array($qty, $qty, $productId, $warehouseId));

        $existing = $this->getInventoryByProductWarehouse($productId, $warehouseId);
        $qtyAfter = $existing ? $existing['stock_qty'] : 0;

        $this->db->prepare("
            INSERT INTO inventory_transactions (product_id, warehouse_id, type, quantity, qty_after, reference_type, reference_id, note, created_by, created_at)
            VALUES (?, ?, 'case_out', ?, ?, ?, ?, ?, ?, NOW())
        ")->execute(array($productId, $warehouseId, -$qty, $qtyAfter, $refType, $refId, $note, $userId));
    }

    /**
     * 確認備貨：reserved_qty → prepared_qty（預扣轉備貨，不動 available_qty 和 stock_qty）
     */
    public function prepareStock($productId, $warehouseId, $qty, $refType, $refId, $note, $userId)
    {
        $this->db->prepare("
            UPDATE inventory SET reserved_qty = GREATEST(reserved_qty - ?, 0), prepared_qty = prepared_qty + ?
            WHERE product_id = ? AND warehouse_id = ?
        ")->execute(array($qty, $qty, $productId, $warehouseId));

        $existing = $this->getInventoryByProductWarehouse($productId, $warehouseId);
        $qtyAfter = $existing ? $existing['stock_qty'] : 0;

        $this->db->prepare("
            INSERT INTO inventory_transactions (product_id, warehouse_id, type, quantity, qty_after, reference_type, reference_id, note, created_by, created_at)
            VALUES (?, ?, 'prepare', ?, ?, ?, ?, ?, ?, NOW())
        ")->execute(array($productId, $warehouseId, $qty, $qtyAfter, $refType, $refId, $note, $userId));
    }

    /**
     * 取消備貨：prepared_qty 減少, available_qty 增加（與 prepareStock 反向）
     * 用於編輯模式下從已備貨狀態刪除未出貨品項
     */
    public function unprepareStock($productId, $warehouseId, $qty, $refType, $refId, $note, $userId)
    {
        $this->db->prepare("
            UPDATE inventory SET available_qty = available_qty + ?, prepared_qty = GREATEST(prepared_qty - ?, 0)
            WHERE product_id = ? AND warehouse_id = ?
        ")->execute(array($qty, $qty, $productId, $warehouseId));

        $existing = $this->getInventoryByProductWarehouse($productId, $warehouseId);
        $qtyAfter = $existing ? $existing['stock_qty'] : 0;

        $this->db->prepare("
            INSERT INTO inventory_transactions (product_id, warehouse_id, type, quantity, qty_after, reference_type, reference_id, note, created_by, created_at)
            VALUES (?, ?, 'unprepare', ?, ?, ?, ?, ?, ?, NOW())
        ")->execute(array($productId, $warehouseId, $qty, $qtyAfter, $refType, $refId, $note, $userId));
    }

    /**
     * 已備貨轉出庫：prepared_qty 減少, stock_qty 減少（available_qty 不動）
     */
    public function confirmPreparedStock($productId, $warehouseId, $qty, $refType, $refId, $note, $userId)
    {
        $this->db->prepare("
            UPDATE inventory SET stock_qty = stock_qty - ?, prepared_qty = GREATEST(prepared_qty - ?, 0)
            WHERE product_id = ? AND warehouse_id = ?
        ")->execute(array($qty, $qty, $productId, $warehouseId));

        $existing = $this->getInventoryByProductWarehouse($productId, $warehouseId);
        $qtyAfter = $existing ? $existing['stock_qty'] : 0;

        $this->db->prepare("
            INSERT INTO inventory_transactions (product_id, warehouse_id, type, quantity, qty_after, reference_type, reference_id, note, created_by, created_at)
            VALUES (?, ?, 'case_out', ?, ?, ?, ?, ?, ?, NOW())
        ")->execute(array($productId, $warehouseId, -$qty, $qtyAfter, $refType, $refId, $note, $userId));
    }

    /**
     * 更新安全庫存量
     */
    public function updateMinQty($inventoryId, $minQty)
    {
        $stmt = $this->db->prepare("UPDATE inventory SET min_qty = ? WHERE id = ?");
        $stmt->execute(array((int)$minQty, $inventoryId));
    }

    /**
     * 批次更新安全庫存量
     * @param array $items  array of ['id' => inventoryId, 'min_qty' => value]
     */
    public function batchUpdateMinQty($items)
    {
        $stmt = $this->db->prepare("UPDATE inventory SET min_qty = ? WHERE id = ?");
        foreach ($items as $item) {
            $stmt->execute(array((int)$item['min_qty'], (int)$item['id']));
        }
    }

    /**
     * 取得特定產品+倉庫的庫存記錄
     */
    public function getInventoryByProductWarehouse($productId, $warehouseId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM inventory WHERE product_id = ? AND warehouse_id = ?
        ");
        $stmt->execute(array($productId, $warehouseId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    // ============================================================
    // 異動記錄
    // ============================================================

    /**
     * 異動記錄查詢
     * @param array $filters  product_id, warehouse_id, type, date_from, date_to, keyword
     * @param int   $limit
     */
    public function getTransactions($filters = array(), $limit = 200)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['product_id'])) {
            $where .= ' AND t.product_id = ?';
            $params[] = $filters['product_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where .= ' AND t.warehouse_id = ?';
            $params[] = $filters['warehouse_id'];
        }
        if (!empty($filters['type'])) {
            $where .= ' AND t.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND t.created_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND t.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (p.name LIKE ? OR p.model LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $limitInt = (int)$limit;
        $stmt = $this->db->prepare("
            SELECT t.*, p.name AS product_name, p.model AS product_model,
                   w.name AS warehouse_name
            FROM inventory_transactions t
            LEFT JOIN products p ON t.product_id = p.id
            LEFT JOIN warehouses w ON t.warehouse_id = w.id
            WHERE {$where}
            ORDER BY t.id DESC
            LIMIT {$limitInt}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 庫存異動匯總（依產品 GROUP BY）
     */
    public function getMovementSummary($filters = array())
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['warehouse_id'])) {
            $where .= ' AND t.warehouse_id = ?';
            $params[] = $filters['warehouse_id'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND t.created_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND t.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (p.name LIKE ? OR p.model LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $stmt = $this->db->prepare("
            SELECT t.product_id,
                   p.name AS product_name, p.model AS product_model,
                   SUM(CASE WHEN t.type IN ('purchase_in','manual_in','transfer_in','return_in') THEN t.quantity ELSE 0 END) AS total_in,
                   SUM(CASE WHEN t.type IN ('manual_out','transfer_out','case_out') THEN ABS(t.quantity) ELSE 0 END) AS total_out,
                   SUM(CASE WHEN t.type = 'adjust' THEN t.quantity ELSE 0 END) AS total_adjust,
                   COUNT(*) AS txn_count
            FROM inventory_transactions t
            LEFT JOIN products p ON t.product_id = p.id
            WHERE {$where}
            GROUP BY t.product_id, p.name, p.model
            HAVING total_in != 0 OR total_out != 0 OR total_adjust != 0
            ORDER BY p.name
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 附加現有庫存
        foreach ($rows as &$r) {
            $invStmt = $this->db->prepare('SELECT COALESCE(SUM(stock_qty), 0) FROM inventory WHERE product_id = ?' . (!empty($filters['warehouse_id']) ? ' AND warehouse_id = ?' : ''));
            $invParams = array($r['product_id']);
            if (!empty($filters['warehouse_id'])) $invParams[] = $filters['warehouse_id'];
            $invStmt->execute($invParams);
            $r['current_stock'] = (int)$invStmt->fetchColumn();
            $r['net_change'] = (int)$r['total_in'] - (int)$r['total_out'] + (int)$r['total_adjust'];
        }

        return $rows;
    }

    // ============================================================
    // 盤點管理
    // ============================================================

    /**
     * 產生盤點單號
     */
    public function generateStocktakeNumber()
    {
        $prefix = 'INV-' . date('Ymd') . '-';
        $stmt = $this->db->prepare("
            SELECT stocktake_number FROM stocktakes
            WHERE stocktake_number LIKE ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(array($prefix . '%'));
        $last = $stmt->fetchColumn();
        if ($last) {
            $seq = (int)substr($last, -3) + 1;
        } else {
            $seq = 1;
        }
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 建立盤點單
     */
    public function createStocktake($warehouseId, $note, $userId, $includeZero = false)
    {
        $this->db->beginTransaction();
        try {
            $number = $this->generateStocktakeNumber();

            $stmt = $this->db->prepare("
                INSERT INTO stocktakes (stocktake_number, stocktake_date, warehouse_id, status, note, created_by, created_at)
                VALUES (?, CURDATE(), ?, '盤點中', ?, ?, NOW())
            ");
            $stmt->execute(array($number, $warehouseId, $note, $userId));
            $stocktakeId = $this->db->lastInsertId();

            // 載入該倉庫庫存品項
            $qtyCondition = $includeZero ? '' : ' AND i.stock_qty != 0';
            $invStmt = $this->db->prepare("
                SELECT i.product_id, i.stock_qty
                FROM inventory i
                WHERE i.warehouse_id = ?{$qtyCondition}
                ORDER BY i.product_id
            ");
            $invStmt->execute(array($warehouseId));
            $items = $invStmt->fetchAll(PDO::FETCH_ASSOC);

            $insertStmt = $this->db->prepare("
                INSERT INTO stocktake_items (stocktake_id, product_id, system_qty)
                VALUES (?, ?, ?)
            ");
            foreach ($items as $item) {
                $insertStmt->execute(array($stocktakeId, $item['product_id'], $item['stock_qty']));
            }

            $this->db->commit();
            return $stocktakeId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 盤點單列表
     */
    public function getStocktakeList($filters = array())
    {
        $where = '1=1';
        $params = array();
        if (!empty($filters['warehouse_id'])) {
            $where .= ' AND s.warehouse_id = ?';
            $params[] = $filters['warehouse_id'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND s.status = ?';
            $params[] = $filters['status'];
        }
        $stmt = $this->db->prepare("
            SELECT s.*, w.name AS warehouse_name,
                   (SELECT COUNT(*) FROM stocktake_items WHERE stocktake_id = s.id) AS item_count,
                   (SELECT COUNT(*) FROM stocktake_items WHERE stocktake_id = s.id AND actual_qty IS NOT NULL) AS counted_count,
                   (SELECT COUNT(*) FROM stocktake_items WHERE stocktake_id = s.id AND diff_qty != 0) AS diff_count
            FROM stocktakes s
            LEFT JOIN warehouses w ON s.warehouse_id = w.id
            WHERE {$where}
            ORDER BY s.id DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得盤點單
     */
    public function getStocktake($id)
    {
        $stmt = $this->db->prepare("
            SELECT s.*, w.name AS warehouse_name
            FROM stocktakes s
            LEFT JOIN warehouses w ON s.warehouse_id = w.id
            WHERE s.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 取得盤點明細
     */
    public function getStocktakeItems($stocktakeId)
    {
        $stmt = $this->db->prepare("
            SELECT si.*, p.name AS product_name, p.model AS product_model, p.unit
            FROM stocktake_items si
            LEFT JOIN products p ON si.product_id = p.id
            WHERE si.stocktake_id = ?
            ORDER BY p.name
        ");
        $stmt->execute(array($stocktakeId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 更新盤點實際數量
     * @param array $items  array of ['id' => itemId, 'actual_qty' => value]
     */
    public function updateStocktakeItems($items)
    {
        $stmt = $this->db->prepare("
            UPDATE stocktake_items
            SET actual_qty = ?, diff_qty = actual_qty - system_qty, note = ?
            WHERE id = ?
        ");
        foreach ($items as $item) {
            $actualQty = ($item['actual_qty'] !== '' && $item['actual_qty'] !== null) ? (int)$item['actual_qty'] : null;
            $note = isset($item['note']) ? $item['note'] : null;
            if ($actualQty !== null) {
                $diffQty = $actualQty - (int)$item['system_qty'];
                $stmt2 = $this->db->prepare("
                    UPDATE stocktake_items SET actual_qty = ?, diff_qty = ?, note = ? WHERE id = ?
                ");
                $stmt2->execute(array($actualQty, $diffQty, $note, $item['id']));
            }
        }
    }

    /**
     * 完成盤點 — 將差異寫入庫存
     */
    public function completeStocktake($stocktakeId, $userId)
    {
        $stocktake = $this->getStocktake($stocktakeId);
        if (!$stocktake || $stocktake['status'] !== '盤點中') {
            return false;
        }

        $items = $this->getStocktakeItems($stocktakeId);
        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                if ($item['actual_qty'] === null || (int)$item['diff_qty'] === 0) {
                    continue;
                }
                $diff = (int)$item['diff_qty'];
                $this->adjustStock(
                    $item['product_id'],
                    $stocktake['warehouse_id'],
                    $diff,
                    'adjust',
                    'stocktake',
                    $stocktakeId,
                    '盤點調整 ' . $stocktake['stocktake_number'],
                    $userId
                );
            }

            $stmt = $this->db->prepare("
                UPDATE stocktakes SET status = '已完成', completed_by = ?, completed_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute(array($userId, $stocktakeId));

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 取消盤點
     */
    public function cancelStocktake($stocktakeId)
    {
        $stmt = $this->db->prepare("UPDATE stocktakes SET status = '已取消', updated_at = NOW() WHERE id = ? AND status = '盤點中'");
        $stmt->execute(array($stocktakeId));
        return $stmt->rowCount() > 0;
    }

    /**
     * 提交盤點簽核
     */
    public function submitStocktake($stocktakeId)
    {
        $stmt = $this->db->prepare("UPDATE stocktakes SET status = '待簽核', updated_at = NOW() WHERE id = ? AND status = '盤點中'");
        $stmt->execute(array($stocktakeId));
        return $stmt->rowCount() > 0;
    }

    /**
     * 駁回盤點（回到盤點中）
     */
    public function rejectStocktake($stocktakeId)
    {
        $stmt = $this->db->prepare("UPDATE stocktakes SET status = '盤點中', updated_at = NOW() WHERE id = ? AND status = '待簽核'");
        $stmt->execute(array($stocktakeId));
        return $stmt->rowCount() > 0;
    }

    // ============================================================
    // 倉庫管理
    // ============================================================

    /**
     * 取得所有倉庫（含停用）
     */
    public function getAllWarehouses()
    {
        $stmt = $this->db->query("
            SELECT w.*, b.name AS branch_name
            FROM warehouses w
            LEFT JOIN branches b ON w.branch_id = b.id
            ORDER BY w.is_active DESC, w.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得所有啟用倉庫（含分店名稱）
     */
    public function getWarehouses()
    {
        $stmt = $this->db->query("
            SELECT w.*, b.name AS branch_name
            FROM warehouses w
            LEFT JOIN branches b ON w.branch_id = b.id
            WHERE w.is_active = 1
            ORDER BY w.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得單一倉庫
     */
    public function getWarehouse($id)
    {
        $stmt = $this->db->prepare("
            SELECT w.*, b.name AS branch_name
            FROM warehouses w
            LEFT JOIN branches b ON w.branch_id = b.id
            WHERE w.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 建立倉庫
     */
    public function createWarehouse($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO warehouses (branch_id, code, name, is_active, created_at)
            VALUES (?, ?, ?, 1, NOW())
        ");
        $stmt->execute(array($data['branch_id'], $data['code'], $data['name']));
        return $this->db->lastInsertId();
    }

    /**
     * 更新倉庫
     */
    public function updateWarehouse($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE warehouses SET branch_id = ?, code = ?, name = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute(array(
            $data['branch_id'], $data['code'], $data['name'],
            isset($data['is_active']) ? (int)$data['is_active'] : 1,
            $id
        ));
    }

    /**
     * 取得所有分公司（for select）
     */
    public function getBranches()
    {
        $stmt = $this->db->query("SELECT id, name FROM branches ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 產品搜尋（for AJAX）
    // ============================================================

    /**
     * 搜尋產品（for 入庫/出庫選擇用）
     */
    public function searchProducts($keyword, $limit = 20)
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.name, p.model, p.unit, p.cost, p.price,
                   pc.name AS category_name,
                   COALESCE(inv.stock, 0) AS stock
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            LEFT JOIN (SELECT product_id, CAST(SUM(stock_qty) AS SIGNED) AS stock FROM inventory GROUP BY product_id) inv ON inv.product_id = p.id
            WHERE (p.name LIKE ? OR p.model LIKE ?) AND p.is_active = 1
            ORDER BY p.name
            LIMIT ?
        ");
        $kw = '%' . $keyword . '%';
        $stmt->execute(array($kw, $kw, (int)$limit));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 產品分類（複用 products 模組）
    // ============================================================

    /**
     * 取得產品分類列表
     */
    public function getCategories()
    {
        $stmt = $this->db->query("SELECT id, name FROM product_categories ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 匯出
    // ============================================================

    /**
     * 匯出庫存CSV
     */
    public function exportCsv($filters = array())
    {
        $filters['per_page'] = 99999; // 匯出不分頁
        $result = $this->getInventoryList($filters);
        $records = $result['data'];
        $output = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, array('倉庫', '分類', '商品名稱', '型號', '單位', '可用數量', '庫存數量', '已備貨', '借出', '展示', '安全庫存', '成本單價', '庫存金額'));

        foreach ($records as $r) {
            $mainCat = '';
            if (!empty($r['cat_grandparent_name'])) {
                $mainCat = $r['cat_grandparent_name'] . ' > ' . $r['cat_parent_name'] . ' > ' . $r['category_name'];
            } elseif (!empty($r['cat_parent_name'])) {
                $mainCat = $r['cat_parent_name'] . ' > ' . $r['category_name'];
            } elseif (!empty($r['category_name'])) {
                $mainCat = $r['category_name'];
            }
            $stockQty = isset($r['stock_qty']) ? (int)$r['stock_qty'] : 0;
            $cost = isset($r['cost']) ? (float)$r['cost'] : 0;
            fputcsv($output, array(
                isset($r['warehouse_name']) ? $r['warehouse_name'] : '',
                $mainCat,
                isset($r['product_name']) ? $r['product_name'] : '',
                isset($r['product_model']) ? $r['product_model'] : '',
                isset($r['unit']) ? $r['unit'] : '',
                isset($r['available_qty']) ? (int)$r['available_qty'] : 0,
                $stockQty,
                isset($r['reserved_qty']) ? (int)$r['reserved_qty'] : 0,
                isset($r['loaned_qty']) ? (int)$r['loaned_qty'] : 0,
                isset($r['display_qty']) ? (int)$r['display_qty'] : 0,
                isset($r['min_qty']) ? (int)$r['min_qty'] : 0,
                $cost,
                $stockQty * $cost,
            ));
        }
        fclose($output);
    }
}
