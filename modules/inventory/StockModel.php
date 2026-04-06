<?php
/**
 * 入庫單 / 出庫單 資料模型
 * 入庫單 CRUD、出庫單 CRUD、確認時更新庫存
 */
class StockModel
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

    public static function stockInStatusOptions()
    {
        return array(
            '待確認' => '待確認',
            '已確認' => '已確認',
            '已取消' => '已取消',
        );
    }

    public static function stockOutStatusOptions()
    {
        return array(
            '待確認'   => '待出庫',
            '已預扣'   => '已預扣',
            '已備貨'   => '已備貨',
            '部分出庫' => '部分出庫',
            '已確認'   => '已出庫',
            '已取消'   => '已取消',
        );
    }

    public static function statusOptions()
    {
        return array(
            'pending'   => '待確認',
            'confirmed' => '已確認',
            'cancelled' => '已取消',
        );
    }

    public static function statusLabel($status)
    {
        $map = array(
            '待確認'   => '待出庫',
            '已預扣'   => '已預扣',
            '已備貨'   => '已備貨',
            '部分出庫' => '部分出庫',
            '已確認'   => '已出庫',
            '已取消'   => '已取消',
            'pending'   => '待出庫',
            'reserved'  => '已預扣',
            'confirmed' => '已出庫',
            'cancelled' => '已取消',
        );
        return isset($map[$status]) ? $map[$status] : $status;
    }

    public static function statusBadgeColor($status)
    {
        $map = array(
            '待確認' => 'orange',
            '已預扣' => 'blue',
            '已備貨' => 'purple',
            '已確認' => 'green',
            '部分出庫' => 'blue',
            '已取消' => 'gray',
            'pending'   => 'orange',
            'reserved'  => 'blue',
            'confirmed' => 'green',
            'cancelled' => 'gray',
        );
        return isset($map[$status]) ? $map[$status] : 'gray';
    }

    public static function statusBadge($status)
    {
        $map = array(
            'pending'   => 'warning',
            'reserved'  => 'info',
            'confirmed' => 'success',
            'cancelled' => 'danger',
            '待確認'    => 'warning',
            '已備貨'    => 'info',
            '已確認'    => 'primary',
            '部分出庫'  => 'info',
            '已取消'    => 'danger',
        );
        return isset($map[$status]) ? $map[$status] : '';
    }

    public static function referenceTypeLabel($type)
    {
        $map = array(
            'delivery_order' => '出貨單',
            'manual'         => '手動出庫',
            'case'           => '案件出庫',
            'goods_receipt'  => '進貨單',
            'quotation'      => '報價單',
        );
        return isset($map[$type]) ? $map[$type] : $type;
    }

    public static function sourceTypeLabel($type)
    {
        $map = array(
            'goods_receipt'   => '進貨單',
            'manual'          => '手動入庫',
            'return_material' => '餘料入庫',
            'manual_return'   => '手動餘料入庫',
            'delivery_order'  => '出貨單',
            'case'            => '案件出庫',
        );
        return isset($map[$type]) ? $map[$type] : $type;
    }

    // ============================================================
    // 入庫單 - 列表
    // ============================================================

    /**
     * 入庫單列表
     * @param array $filters  status, warehouse_id, keyword, date_from, date_to, source_type
     * @return array
     */
    public function getStockIns($filters = array(), $page = 1, $perPage = 100)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['status'])) {
            $where .= ' AND si.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where .= ' AND si.warehouse_id = ?';
            $params[] = $filters['warehouse_id'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (si.si_number LIKE ? OR si.source_number LIKE ? OR si.customer_name LIKE ? OR si.vendor_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND si.si_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND si.si_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['source_type'])) {
            $where .= ' AND si.source_type = ?';
            $params[] = $filters['source_type'];
        }

        // 總筆數
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM stock_ins si WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT si.*, w.name AS warehouse_name,
                   u.real_name AS created_by_name
            FROM stock_ins si
            LEFT JOIN warehouses w ON si.warehouse_id = w.id
            LEFT JOIN users u ON si.created_by = u.id
            WHERE {$where}
            ORDER BY si.updated_at DESC, si.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        return array('data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => ceil($total / $perPage));
    }

    // ============================================================
    // 入庫單 - 單筆查詢
    // ============================================================

    /**
     * 取得單筆入庫單
     */
    public function getStockInById($id)
    {
        $stmt = $this->db->prepare("
            SELECT si.*, w.name AS warehouse_name,
                   u.real_name AS created_by_name,
                   cu.real_name AS confirmed_by_name
            FROM stock_ins si
            LEFT JOIN warehouses w ON si.warehouse_id = w.id
            LEFT JOIN users u ON si.created_by = u.id
            LEFT JOIN users cu ON si.confirmed_by = cu.id
            WHERE si.id = ?
        ");
        $stmt->execute(array($id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * 取得入庫單明細
     */
    public function getStockInItems($stockInId)
    {
        $stmt = $this->db->prepare("
            SELECT sii.*, p.name AS db_product_name, p.model AS db_model
            FROM stock_in_items sii
            LEFT JOIN products p ON sii.product_id = p.id
            WHERE sii.stock_in_id = ?
            ORDER BY sii.sort_order, sii.id
        ");
        $stmt->execute(array($stockInId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 入庫單 - 新增
    // ============================================================

    /**
     * 建立入庫單
     * @param array $data
     * @return int
     */
    public function createStockIn($data)
    {
        $number = generate_doc_number('stock_ins');
        $stmt = $this->db->prepare("
            INSERT INTO stock_ins
                (si_number, si_date, status, source_type, source_id, source_number,
                 warehouse_id, branch_id, branch_name, customer_name, note, total_qty, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(array(
            $number,
            !empty($data['si_date']) ? $data['si_date'] : date('Y-m-d'),
            !empty($data['status']) ? $data['status'] : '待確認',
            !empty($data['source_type']) ? $data['source_type'] : null,
            !empty($data['source_id']) ? $data['source_id'] : null,
            !empty($data['source_number']) ? $data['source_number'] : null,
            !empty($data['warehouse_id']) ? $data['warehouse_id'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['branch_name']) ? $data['branch_name'] : null,
            !empty($data['customer_name']) ? $data['customer_name'] : null,
            !empty($data['note']) ? $data['note'] : null,
            !empty($data['total_qty']) ? $data['total_qty'] : 0,
            $data['created_by'],
        ));
        $siId = $this->db->lastInsertId();

        // 儲存明細
        if (!empty($data['items'])) {
            $this->saveStockInItems($siId, $data['items']);
        }

        return $siId;
    }

    /**
     * 儲存入庫單明細
     */
    public function saveStockInItems($stockInId, $items)
    {
        $this->db->prepare("DELETE FROM stock_in_items WHERE stock_in_id = ?")
            ->execute(array($stockInId));

        if (empty($items)) return;

        $stmt = $this->db->prepare("
            INSERT INTO stock_in_items
                (stock_in_id, product_id, model, product_name, spec, unit, quantity, unit_price, note, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $sort = 0;
        foreach ($items as $item) {
            if (empty($item['product_name']) && empty($item['model'])) continue;
            $stmt->execute(array(
                $stockInId,
                !empty($item['product_id']) ? $item['product_id'] : null,
                !empty($item['model']) ? $item['model'] : null,
                !empty($item['product_name']) ? $item['product_name'] : null,
                !empty($item['spec']) ? $item['spec'] : null,
                !empty($item['unit']) ? $item['unit'] : null,
                !empty($item['quantity']) ? $item['quantity'] : 0,
                !empty($item['unit_price']) ? $item['unit_price'] : 0,
                !empty($item['note']) ? $item['note'] : null,
                $sort++,
            ));
        }
    }

    // ============================================================
    // 入庫單 - 確認（更新庫存）
    // ============================================================

    /**
     * 確認入庫單 -> 更新庫存（增加 stock_qty + available_qty）+ 建立 inventory_transaction
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public function confirmStockIn($id, $userId)
    {
        $record = $this->getStockInById($id);
        if (!$record || $record['status'] === '已確認') {
            return false;
        }

        $items = $this->getStockInItems($id);
        if (empty($items)) {
            return false;
        }

        require_once __DIR__ . '/InventoryModel.php';
        $invModel = new InventoryModel();
        $warehouseId = $record['warehouse_id'];

        if (!$warehouseId) {
            return false;
        }

        // 更新入庫單狀態
        $stmt = $this->db->prepare("
            UPDATE stock_ins SET status = '已確認', confirmed_by = ?, confirmed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute(array($userId, $id));

        // 逐筆更新庫存
        foreach ($items as $item) {
            if (!empty($item['product_id']) && $item['quantity'] > 0) {
                $invModel->adjustStock(
                    $item['product_id'],
                    $warehouseId,
                    $item['quantity'],
                    'purchase_in',
                    'stock_in',
                    $id,
                    '入庫: ' . $record['si_number'],
                    $userId
                );
            }
        }

        return true;
    }

    // ============================================================
    // 出庫單 - 列表
    // ============================================================

    /**
     * 出庫單列表
     */
    public function getStockOuts($filters = array(), $page = 1, $perPage = 100)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['status'])) {
            $where .= ' AND so.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where .= ' AND so.warehouse_id = ?';
            $params[] = $filters['warehouse_id'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (so.so_number LIKE ? OR so.source_number LIKE ? OR so.customer_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['month'])) {
            $where .= ' AND so.so_date LIKE ?';
            $params[] = $filters['month'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND so.so_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND so.so_date <= ?';
            $params[] = $filters['date_to'];
        }

        // 總筆數
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM stock_outs so WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT so.*, w.name AS warehouse_name,
                   u.real_name AS created_by_name
            FROM stock_outs so
            LEFT JOIN warehouses w ON so.warehouse_id = w.id
            LEFT JOIN users u ON so.created_by = u.id
            WHERE {$where}
            ORDER BY FIELD(so.status, '待確認', '部分出庫', '已確認', '已取消'), so.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        return array('data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => ceil($total / $perPage));
    }

    // ============================================================
    // 出庫單 - 單筆查詢
    // ============================================================

    public function getStockOutById($id)
    {
        $stmt = $this->db->prepare("
            SELECT so.*, w.name AS warehouse_name,
                   u.real_name AS created_by_name,
                   cu.real_name AS confirmed_by_name
            FROM stock_outs so
            LEFT JOIN warehouses w ON so.warehouse_id = w.id
            LEFT JOIN users u ON so.created_by = u.id
            LEFT JOIN users cu ON so.confirmed_by = cu.id
            WHERE so.id = ?
        ");
        $stmt->execute(array($id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // Also load items
        $itemStmt = $this->db->prepare("
            SELECT soi.*, p.name AS product_name, p.model AS product_model, p.unit
            FROM stock_out_items soi
            LEFT JOIN products p ON soi.product_id = p.id
            WHERE soi.stock_out_id = ?
            ORDER BY soi.sort_order, soi.id
        ");
        $itemStmt->execute(array($id));
        $row['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        return $row;
    }

    public function getStockOutItems($stockOutId)
    {
        $stmt = $this->db->prepare("
            SELECT soi.*, p.name AS db_product_name, p.model AS db_model
            FROM stock_out_items soi
            LEFT JOIN products p ON soi.product_id = p.id
            WHERE soi.stock_out_id = ?
            ORDER BY soi.sort_order, soi.id
        ");
        $stmt->execute(array($stockOutId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 出庫單 - 新增
    // ============================================================

    public function createStockOut($data)
    {
        $number = generate_doc_number('stock_outs');
        $stmt = $this->db->prepare("
            INSERT INTO stock_outs
                (so_number, so_date, status, source_type, source_id, source_number,
                 warehouse_id, customer_id, customer_name, branch_id, branch_name,
                 note, total_qty, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(array(
            $number,
            !empty($data['so_date']) ? $data['so_date'] : date('Y-m-d'),
            !empty($data['status']) ? $data['status'] : '待確認',
            !empty($data['source_type']) ? $data['source_type'] : null,
            !empty($data['source_id']) ? $data['source_id'] : null,
            !empty($data['source_number']) ? $data['source_number'] : null,
            !empty($data['warehouse_id']) ? $data['warehouse_id'] : null,
            !empty($data['customer_id']) ? $data['customer_id'] : null,
            !empty($data['customer_name']) ? $data['customer_name'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['branch_name']) ? $data['branch_name'] : null,
            !empty($data['note']) ? $data['note'] : null,
            !empty($data['total_qty']) ? $data['total_qty'] : 0,
            $data['created_by'],
        ));
        $soId = $this->db->lastInsertId();

        if (!empty($data['items'])) {
            $this->saveStockOutItems($soId, $data['items']);
        }

        return $soId;
    }

    public function saveStockOutItems($stockOutId, $items)
    {
        $this->db->prepare("DELETE FROM stock_out_items WHERE stock_out_id = ?")
            ->execute(array($stockOutId));

        if (empty($items)) return;

        $stmt = $this->db->prepare("
            INSERT INTO stock_out_items
                (stock_out_id, product_id, model, product_name, spec, unit, quantity, unit_price, note, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $sort = 0;
        foreach ($items as $item) {
            if (empty($item['product_name']) && empty($item['model'])) continue;
            $stmt->execute(array(
                $stockOutId,
                !empty($item['product_id']) ? $item['product_id'] : null,
                !empty($item['model']) ? $item['model'] : null,
                !empty($item['product_name']) ? $item['product_name'] : null,
                !empty($item['spec']) ? $item['spec'] : null,
                !empty($item['unit']) ? $item['unit'] : null,
                !empty($item['quantity']) ? $item['quantity'] : 0,
                !empty($item['unit_price']) ? $item['unit_price'] : 0,
                !empty($item['note']) ? $item['note'] : null,
                $sort++,
            ));
        }
    }

    // ============================================================
    // 出庫單 - 備品管理
    // ============================================================

    public function addSpareItem($stockOutId, $data)
    {
        $record = $this->getStockOutById($stockOutId);
        if (!$record || ($record['status'] !== '待確認' && $record['status'] !== 'pending')) {
            throw new Exception('只能在待確認狀態新增備品');
        }
        $maxSort = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM stock_out_items WHERE stock_out_id = ?");
        $maxSort->execute(array($stockOutId));
        $nextSort = (int)$maxSort->fetchColumn();

        $stmt = $this->db->prepare("
            INSERT INTO stock_out_items
                (stock_out_id, product_id, model, product_name, spec, unit, quantity, unit_price, note, sort_order, is_spare)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute(array(
            $stockOutId,
            !empty($data['product_id']) ? $data['product_id'] : null,
            !empty($data['model']) ? $data['model'] : null,
            !empty($data['product_name']) ? $data['product_name'] : null,
            !empty($data['spec']) ? $data['spec'] : null,
            !empty($data['unit']) ? $data['unit'] : '台',
            !empty($data['quantity']) ? $data['quantity'] : 1,
            !empty($data['unit_price']) ? $data['unit_price'] : 0,
            !empty($data['note']) ? $data['note'] : '備品',
            $nextSort,
        ));
        return (int)$this->db->lastInsertId();
    }

    public function removeSpareItem($stockOutId, $itemId)
    {
        $record = $this->getStockOutById($stockOutId);
        if (!$record || ($record['status'] !== '待確認' && $record['status'] !== 'pending')) {
            throw new Exception('只能在待確認狀態移除備品');
        }
        $stmt = $this->db->prepare("DELETE FROM stock_out_items WHERE id = ? AND stock_out_id = ? AND is_spare = 1");
        $stmt->execute(array($itemId, $stockOutId));
        return $stmt->rowCount() > 0;
    }

    // ============================================================
    // 出庫單 - 確認（更新庫存）
    // ============================================================

    /**
     * 確認出庫單 -> 更新庫存（減少 stock_qty + available_qty）+ 建立 inventory_transaction
     */
    /**
     * 確認出庫（全部未確認品項一次確認）
     */
    public function confirmStockOut($id, $userId)
    {
        $record = $this->getStockOutById($id);
        if (!$record) return false;
        if ($record['status'] === '已確認') return false;

        $items = isset($record['items']) ? $record['items'] : $this->getStockOutItems($id);
        if (empty($items)) return false;

        require_once __DIR__ . '/InventoryModel.php';
        $invModel = new InventoryModel();
        $warehouseId = $record['warehouse_id'];
        if (!$warehouseId) return false;

        $soNumber = !empty($record['so_number']) ? $record['so_number'] : (!empty($record['stockout_number']) ? $record['stockout_number'] : '');
        $isPrepared = ($record['status'] === '已備貨');
        $isReserved = ($record['status'] === '已預扣');

        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                if (!empty($item['is_confirmed'])) continue;
                $productId = !empty($item['product_id']) ? $item['product_id'] : null;
                $qty = !empty($item['quantity']) ? $item['quantity'] : 0;
                if (!$productId || $qty <= 0) continue;

                if ($isPrepared) {
                    $invModel->confirmPreparedStock($productId, $warehouseId, abs($qty), 'stock_out', $id, '出庫: ' . $soNumber, $userId);
                } elseif ($isReserved) {
                    $invModel->confirmReservedStock($productId, $warehouseId, abs($qty), 'stock_out', $id, '出庫: ' . $soNumber, $userId);
                } else {
                    $invModel->adjustStock($productId, $warehouseId, -1 * abs($qty), 'case_out', 'stock_out', $id, '出庫: ' . $soNumber, $userId);
                }
                $this->db->prepare("UPDATE stock_out_items SET is_confirmed = 1, confirmed_at = NOW() WHERE id = ?")->execute(array($item['id']));
            }

            $this->updateStockOutStatus($id, $userId);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 確認單一品項出庫
     */
    public function confirmStockOutItem($stockOutId, $itemId, $userId, $confirmQty = null)
    {
        $record = $this->getStockOutById($stockOutId);
        if (!$record) return false;
        if ($record['status'] === '已確認' || $record['status'] === '已取消') return false;
        // 已備貨、待確認、部分出庫 都可以確認出庫

        $warehouseId = $record['warehouse_id'];
        if (!$warehouseId) return false;

        $soNumber = !empty($record['so_number']) ? $record['so_number'] : (!empty($record['stockout_number']) ? $record['stockout_number'] : '');

        $stmt = $this->db->prepare("SELECT * FROM stock_out_items WHERE id = ? AND stock_out_id = ?");
        $stmt->execute(array($itemId, $stockOutId));
        $item = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$item || !empty($item['is_confirmed'])) return false;

        $productId = !empty($item['product_id']) ? $item['product_id'] : null;
        $originalQty = !empty($item['quantity']) ? (float)$item['quantity'] : 0;
        if (!$productId || $originalQty <= 0) return false;

        // 使用自訂出庫數量（不可超過需求數量）
        $qty = ($confirmQty !== null && $confirmQty > 0) ? min($confirmQty, $originalQty) : $originalQty;

        require_once __DIR__ . '/InventoryModel.php';
        $invModel = new InventoryModel();
        $isPrepared = ($record['status'] === '已備貨');
        $isReserved = ($record['status'] === '已預扣');

        $this->db->beginTransaction();
        try {
            if ($isPrepared) {
                // 已備貨：從 prepared_qty 轉出（stock_qty 減少，available_qty 不動）
                $invModel->confirmPreparedStock($productId, $warehouseId, abs($qty), 'stock_out', $stockOutId, '出庫: ' . $soNumber . ' (數量:' . $qty . ')', $userId);
            } elseif ($isReserved) {
                // 已預扣：從 reserved_qty 轉出（stock_qty 減少，available_qty 不動）
                $invModel->confirmReservedStock($productId, $warehouseId, abs($qty), 'stock_out', $stockOutId, '出庫: ' . $soNumber . ' (數量:' . $qty . ')', $userId);
            } else {
                // 未預扣：正常扣 stock_qty + available_qty
                $invModel->adjustStock($productId, $warehouseId, -1 * abs($qty), 'case_out', 'stock_out', $stockOutId, '出庫: ' . $soNumber . ' (數量:' . $qty . ')', $userId);
            }

            // 更新品項：如果出庫數量 < 需求數量，更新 quantity 為實際出庫量
            if ($qty < $originalQty) {
                $this->db->prepare("UPDATE stock_out_items SET quantity = ?, is_confirmed = 1, confirmed_at = NOW() WHERE id = ?")->execute(array($qty, $itemId));
            } else {
                $this->db->prepare("UPDATE stock_out_items SET is_confirmed = 1, confirmed_at = NOW() WHERE id = ?")->execute(array($itemId));
            }

            $this->updateStockOutStatus($stockOutId, $userId);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 更新出庫單狀態（依品項確認情況）
     */
    private function updateStockOutStatus($id, $userId)
    {
        $total = $this->db->prepare("SELECT COUNT(*) FROM stock_out_items WHERE stock_out_id = ?");
        $total->execute(array($id));
        $totalCount = (int)$total->fetchColumn();

        $confirmed = $this->db->prepare("SELECT COUNT(*) FROM stock_out_items WHERE stock_out_id = ? AND is_confirmed = 1");
        $confirmed->execute(array($id));
        $confirmedCount = (int)$confirmed->fetchColumn();

        if ($confirmedCount >= $totalCount) {
            $this->db->prepare("UPDATE stock_outs SET status = '已確認', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?")
                     ->execute(array($userId, $id));
        } elseif ($confirmedCount > 0) {
            $this->db->prepare("UPDATE stock_outs SET status = '部分出庫' WHERE id = ?")->execute(array($id));
        }
    }

    /**
     * 預扣庫存：扣 available_qty + 加 reserved_qty，狀態改「已預扣」
     */
    public function reserveStockOut($id, $userId)
    {
        $record = $this->getStockOutById($id);
        if (!$record) return false;
        if ($record['status'] !== '待確認') return false;

        $items = $this->getStockOutItems($id);
        if (empty($items)) return false;

        require_once __DIR__ . '/InventoryModel.php';
        $invModel = new InventoryModel();
        $whId = (int)$record['warehouse_id'];
        $soNum = $record['so_number'];

        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                if (!empty($item['is_confirmed'])) continue;
                $pid = !empty($item['product_id']) ? (int)$item['product_id'] : 0;
                $qty = (int)(isset($item['quantity']) ? $item['quantity'] : 0);
                if ($pid && $qty > 0) {
                    $invModel->reserveStock($pid, $whId, $qty, 'stock_out', $id, '預扣: ' . $soNum, $userId);
                }
            }
            $this->db->prepare("UPDATE stock_outs SET status = '已預扣', updated_by = ? WHERE id = ?")
                ->execute(array($userId, $id));
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 確認備貨：reserved_qty → prepared_qty，狀態改「已備貨」
     */
    public function prepareStockOut($id, $userId)
    {
        $record = $this->getStockOutById($id);
        if (!$record) return false;
        if ($record['status'] !== '已預扣') return false;

        $items = $this->getStockOutItems($id);
        if (empty($items)) return false;

        require_once __DIR__ . '/InventoryModel.php';
        $invModel = new InventoryModel();
        $whId = (int)$record['warehouse_id'];
        $soNum = $record['so_number'];

        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                if (!empty($item['is_confirmed'])) continue;
                $pid = !empty($item['product_id']) ? (int)$item['product_id'] : 0;
                $qty = (int)(isset($item['quantity']) ? $item['quantity'] : 0);
                if ($pid && $qty > 0) {
                    $invModel->prepareStock($pid, $whId, $qty, 'stock_out', $id, '備貨: ' . $soNum, $userId);
                }
            }
            $this->db->prepare("UPDATE stock_outs SET status = '已備貨', updated_by = ? WHERE id = ?")
                ->execute(array($userId, $id));
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 取消預扣：恢復 available_qty + 減 reserved_qty，狀態改回「待確認」
     */
    public function cancelReserve($id, $userId)
    {
        $record = $this->getStockOutById($id);
        if (!$record) return false;
        if ($record['status'] !== '已預扣') return false;

        $items = $this->getStockOutItems($id);
        require_once __DIR__ . '/InventoryModel.php';
        $invModel = new InventoryModel();
        $whId = (int)$record['warehouse_id'];
        $soNum = $record['so_number'];

        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                if (!empty($item['is_confirmed'])) continue;
                $pid = !empty($item['product_id']) ? (int)$item['product_id'] : 0;
                $qty = (int)(isset($item['quantity']) ? $item['quantity'] : 0);
                if ($pid && $qty > 0) {
                    $invModel->unreserveStock($pid, $whId, $qty, 'stock_out', $id, '取消預扣: ' . $soNum, $userId);
                }
            }
            $this->db->prepare("UPDATE stock_outs SET status = '待確認', updated_by = ? WHERE id = ?")
                ->execute(array($userId, $id));
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 取消出庫單（已出庫品項保留紀錄，未出庫品項不再處理）
     */
    public function cancelStockOut($id, $userId)
    {
        $record = $this->getStockOutById($id);
        if (!$record) return false;
        if ($record['status'] === '已取消') return false;

        $this->db->prepare("UPDATE stock_outs SET status = '已取消' WHERE id = ?")
                 ->execute(array($id));
        return true;
    }

    // ============================================================
    // 倉庫列表
    // ============================================================

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
}
