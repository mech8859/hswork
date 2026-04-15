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
        if (!empty($filters['vendor_name'])) {
            $where .= ' AND si.vendor_name LIKE ?';
            $params[] = '%' . $filters['vendor_name'] . '%';
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
                 warehouse_id, branch_id, branch_name, customer_name, vendor_name, note, total_qty, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
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
        // 注意：用 db_ 前綴 alias 避免與 stock_out_items 自身欄位 product_name/unit 衝突
        // （以前用 p.name AS product_name 會在 product_id=null 時覆蓋 soi 的實際值為 NULL）
        $itemStmt = $this->db->prepare("
            SELECT soi.*, p.name AS db_product_name, p.model AS db_model, p.unit AS db_unit
            FROM stock_out_items soi
            LEFT JOIN products p ON soi.product_id = p.id
            WHERE soi.stock_out_id = ?
            ORDER BY soi.sort_order, soi.id
        ");
        $itemStmt->execute(array($id));
        $row['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        return $row;
    }

    /**
     * 取得從此出庫單建立的所有餘料入庫單
     * @return array [{id, si_number, si_date, status, item_count, ...}, ...]
     */
    public function getReturnStockInsByStockOut($stockOutId)
    {
        $stmt = $this->db->prepare("
            SELECT si.id, si.si_number, si.si_date, si.status, si.note,
                   (SELECT COUNT(*) FROM stock_in_items WHERE stock_in_id = si.id) AS item_count,
                   (SELECT COALESCE(SUM(quantity),0) FROM stock_in_items WHERE stock_in_id = si.id) AS total_qty
            FROM stock_ins si
            WHERE si.source_type = 'manual_return' AND si.source_id = ?
            ORDER BY si.si_date DESC, si.id DESC
        ");
        $stmt->execute(array($stockOutId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 統計每個 product_id 已退回的數量（從此出庫單衍生的入庫單）
     * 排除已取消的入庫單
     * @return array [product_id => total_returned_qty]
     */
    public function getReturnedQtyMap($stockOutId)
    {
        $stmt = $this->db->prepare("
            SELECT sii.product_id, COALESCE(SUM(sii.quantity),0) AS qty
            FROM stock_in_items sii
            JOIN stock_ins si ON sii.stock_in_id = si.id
            WHERE si.source_type = 'manual_return'
              AND si.source_id = ?
              AND si.status != '已取消'
              AND sii.product_id IS NOT NULL
            GROUP BY sii.product_id
        ");
        $stmt->execute(array($stockOutId));
        $map = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['product_id']] = (int)$row['qty'];
        }
        return $map;
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
    // 出庫單 - 編輯功能輔助方法
    // ============================================================

    /**
     * 判斷出庫單未確認品項目前的庫存狀態
     * 用於編輯時決定如何反向/正向操作庫存
     *
     * @return string 'pending' | 'reserved' | 'prepared'
     */
    public function getStockOutItemsState($stockOutId)
    {
        $record = $this->getStockOutById($stockOutId);
        if (!$record) return 'pending';

        $status = $record['status'];
        if ($status === '待確認' || $status === 'pending') return 'pending';
        if ($status === '已預扣') return 'reserved';
        if ($status === '已備貨') return 'prepared';

        // 部分出庫：查最後一筆 non-case_out transaction 推論前狀態
        $stmt = $this->db->prepare("
            SELECT type FROM inventory_transactions
            WHERE reference_type = 'stock_out' AND reference_id = ?
              AND type IN ('reserve', 'unreserve', 'prepare', 'unprepare')
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(array($stockOutId));
        $lastType = $stmt->fetchColumn();

        if ($lastType === 'prepare') return 'prepared';
        if ($lastType === 'reserve') return 'reserved';
        // unreserve / unprepare / 無 → pending
        return 'pending';
    }

    /**
     * 編輯出庫單明細（批次處理刪除/修改/新增）
     *
     * @param int $stockOutId
     * @param array $changes ['deleted' => [itemId...], 'updated' => [{id, quantity, unit_price, note}...], 'added' => [{product_id, product_name, model, unit, quantity, unit_price, note}...]]
     * @param int $userId
     * @return array ['deleted'=>n, 'updated'=>n, 'added'=>n]
     */
    public function editStockOutItems($stockOutId, array $changes, $userId)
    {
        $record = $this->getStockOutById($stockOutId);
        if (!$record) throw new Exception('出庫單不存在');

        $allowedStatuses = array('待確認', 'pending', '已預扣', '已備貨', '部分出庫');
        if (!in_array($record['status'], $allowedStatuses, true)) {
            throw new Exception('此狀態不允許編輯：' . $record['status']);
        }

        $itemState = $this->getStockOutItemsState($stockOutId);
        $warehouseId = $record['warehouse_id'];
        if (!$warehouseId) throw new Exception('出庫單缺少倉庫資訊');
        $soNumber = !empty($record['so_number']) ? $record['so_number'] : '';

        require_once __DIR__ . '/InventoryModel.php';
        $invModel = new InventoryModel();

        $this->db->beginTransaction();
        try {
            $results = array('deleted' => 0, 'updated' => 0, 'added' => 0);

            // ---- 1. 刪除 ----
            if (!empty($changes['deleted']) && is_array($changes['deleted'])) {
                foreach ($changes['deleted'] as $delId) {
                    $delId = (int)$delId;
                    if ($delId <= 0) continue;

                    $stmt = $this->db->prepare("SELECT * FROM stock_out_items WHERE id = ? AND stock_out_id = ?");
                    $stmt->execute(array($delId, $stockOutId));
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$item) continue;

                    // 已出貨的列不可刪
                    $shippedQty = isset($item['shipped_qty']) ? (float)$item['shipped_qty'] : 0;
                    if ($shippedQty > 0) {
                        throw new Exception('已出貨的品項不可刪除：' . ($item['product_name'] ?: '(未命名)'));
                    }

                    $pid = !empty($item['product_id']) ? (int)$item['product_id'] : 0;
                    $qty = (float)$item['quantity'];

                    // 依狀態反向庫存
                    if ($pid && $qty > 0) {
                        if ($itemState === 'reserved') {
                            $invModel->unreserveStock($pid, $warehouseId, $qty, 'stock_out', $stockOutId, '編輯刪除: ' . $soNumber, $userId);
                        } elseif ($itemState === 'prepared') {
                            $invModel->unprepareStock($pid, $warehouseId, $qty, 'stock_out', $stockOutId, '編輯刪除: ' . $soNumber, $userId);
                        }
                    }

                    $this->db->prepare("DELETE FROM stock_out_items WHERE id = ?")->execute(array($delId));
                    $results['deleted']++;
                }
            }

            // ---- 2. 修改 ----
            if (!empty($changes['updated']) && is_array($changes['updated'])) {
                foreach ($changes['updated'] as $upd) {
                    $itemId = isset($upd['id']) ? (int)$upd['id'] : 0;
                    if ($itemId <= 0) continue;

                    $stmt = $this->db->prepare("SELECT * FROM stock_out_items WHERE id = ? AND stock_out_id = ?");
                    $stmt->execute(array($itemId, $stockOutId));
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$item) continue;

                    $shippedQty = isset($item['shipped_qty']) ? (float)$item['shipped_qty'] : 0;
                    if ($shippedQty > 0) {
                        throw new Exception('已出貨的品項不可修改：' . ($item['product_name'] ?: '(未命名)'));
                    }

                    $pid = !empty($item['product_id']) ? (int)$item['product_id'] : 0;
                    $oldQty = (float)$item['quantity'];
                    $newQty = isset($upd['quantity']) ? (float)$upd['quantity'] : $oldQty;
                    if ($newQty <= 0) throw new Exception('數量必須大於 0');

                    $delta = $newQty - $oldQty;

                    // 依狀態調整庫存
                    if ($delta != 0 && $pid) {
                        if ($itemState === 'reserved') {
                            if ($delta > 0) {
                                $invModel->reserveStock($pid, $warehouseId, $delta, 'stock_out', $stockOutId, '編輯增量: ' . $soNumber, $userId);
                            } else {
                                $invModel->unreserveStock($pid, $warehouseId, abs($delta), 'stock_out', $stockOutId, '編輯減量: ' . $soNumber, $userId);
                            }
                        } elseif ($itemState === 'prepared') {
                            if ($delta > 0) {
                                // 先 reserve 再 prepare
                                $invModel->reserveStock($pid, $warehouseId, $delta, 'stock_out', $stockOutId, '編輯增量: ' . $soNumber, $userId);
                                $invModel->prepareStock($pid, $warehouseId, $delta, 'stock_out', $stockOutId, '編輯增量: ' . $soNumber, $userId);
                            } else {
                                $invModel->unprepareStock($pid, $warehouseId, abs($delta), 'stock_out', $stockOutId, '編輯減量: ' . $soNumber, $userId);
                            }
                        }
                    }

                    $unitPrice = isset($upd['unit_price']) ? (float)$upd['unit_price'] : (float)$item['unit_price'];
                    $note = isset($upd['note']) ? $upd['note'] : $item['note'];
                    $this->db->prepare("UPDATE stock_out_items SET quantity = ?, unit_price = ?, note = ? WHERE id = ?")
                             ->execute(array($newQty, $unitPrice, $note, $itemId));
                    $results['updated']++;
                }
            }

            // ---- 3. 新增 ----
            if (!empty($changes['added']) && is_array($changes['added'])) {
                $maxSort = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM stock_out_items WHERE stock_out_id = ?");
                $maxSort->execute(array($stockOutId));
                $nextSort = (int)$maxSort->fetchColumn();

                $insertStmt = $this->db->prepare("
                    INSERT INTO stock_out_items
                        (stock_out_id, product_id, model, product_name, unit, quantity, shipped_qty, unit_price, note, sort_order, is_confirmed)
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 0)
                ");

                foreach ($changes['added'] as $new) {
                    $pid = !empty($new['product_id']) ? (int)$new['product_id'] : 0;
                    $qty = isset($new['quantity']) ? (float)$new['quantity'] : 0;
                    if ($qty <= 0) throw new Exception('新增品項數量必須大於 0');

                    $productName = isset($new['product_name']) ? trim($new['product_name']) : '';
                    $model = isset($new['model']) ? trim($new['model']) : '';
                    $unit = isset($new['unit']) ? trim($new['unit']) : '';
                    $unitPrice = isset($new['unit_price']) ? (float)$new['unit_price'] : 0;
                    $note = isset($new['note']) ? trim($new['note']) : '';

                    if ($productName === '' && $model === '' && $pid <= 0) {
                        throw new Exception('新增品項至少需要品名或型號');
                    }

                    $insertStmt->execute(array(
                        $stockOutId,
                        $pid ?: null,
                        $model ?: null,
                        $productName ?: null,
                        $unit ?: null,
                        $qty,
                        $unitPrice,
                        $note ?: null,
                        $nextSort++,
                    ));

                    // 依狀態正向庫存（新品項比照當前狀態預扣/備貨）
                    if ($pid && $qty > 0) {
                        if ($itemState === 'reserved') {
                            $invModel->reserveStock($pid, $warehouseId, $qty, 'stock_out', $stockOutId, '編輯新增: ' . $soNumber, $userId);
                        } elseif ($itemState === 'prepared') {
                            $invModel->reserveStock($pid, $warehouseId, $qty, 'stock_out', $stockOutId, '編輯新增: ' . $soNumber, $userId);
                            $invModel->prepareStock($pid, $warehouseId, $qty, 'stock_out', $stockOutId, '編輯新增: ' . $soNumber, $userId);
                        }
                    }

                    $results['added']++;
                }
            }

            // 重新計算 total_qty
            $this->db->prepare("
                UPDATE stock_outs
                SET total_qty = (SELECT COALESCE(SUM(quantity), 0) FROM stock_out_items WHERE stock_out_id = ?),
                    updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute(array($stockOutId, $userId, $stockOutId));

            // 更新狀態（若有可能觸發狀態變更）
            $this->updateStockOutStatus($stockOutId, $userId);

            $this->db->commit();
            return $results;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
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
     * 確認出庫（全部未完成品項一次確認到剩餘需求量）
     *
     * 新語意（Migration 111 之後）：
     *   - 每個品項只出「需求 - 已出」的剩餘量
     *   - 累加 shipped_qty，不覆寫 quantity
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
                $productId = !empty($item['product_id']) ? $item['product_id'] : null;
                $needQty = !empty($item['quantity']) ? (float)$item['quantity'] : 0;
                $alreadyShipped = isset($item['shipped_qty']) ? (float)$item['shipped_qty'] : 0;
                $remaining = $needQty - $alreadyShipped;
                if (!$productId || $needQty <= 0) continue;
                if ($remaining <= 0) continue; // 已完全出貨

                if ($isPrepared) {
                    $invModel->confirmPreparedStock($productId, $warehouseId, abs($remaining), 'stock_out', $id, '出庫: ' . $soNumber, $userId);
                } elseif ($isReserved) {
                    $invModel->confirmReservedStock($productId, $warehouseId, abs($remaining), 'stock_out', $id, '出庫: ' . $soNumber, $userId);
                } else {
                    $invModel->adjustStock($productId, $warehouseId, -1 * abs($remaining), 'case_out', 'stock_out', $id, '出庫: ' . $soNumber, $userId);
                }

                // 累加 shipped_qty 到需求量 (剩餘全出)
                $newShipped = $alreadyShipped + $remaining;
                $this->db->prepare("UPDATE stock_out_items SET shipped_qty = ?, is_confirmed = 1, confirmed_at = NOW() WHERE id = ?")
                         ->execute(array($newShipped, $item['id']));
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
     *
     * 新語意（Migration 111 之後）：
     *   - quantity     = 業務需求量（固定，不被覆寫）
     *   - shipped_qty  = 累計已出貨量（每次確認出貨累加）
     *   - is_confirmed = shipped_qty >= quantity 時為 1
     *
     * 支援單品項多次部分出貨（例如需求 5，先出 2，再出 3）
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
        if (!$item) return false;

        $productId = !empty($item['product_id']) ? $item['product_id'] : null;
        $needQty = !empty($item['quantity']) ? (float)$item['quantity'] : 0;
        $alreadyShipped = isset($item['shipped_qty']) ? (float)$item['shipped_qty'] : 0;
        $remaining = $needQty - $alreadyShipped;
        if (!$productId || $needQty <= 0) return false;
        if ($remaining <= 0) return false; // 已完全出貨

        // 本次出貨數量：不可超過剩餘需求量
        $qty = ($confirmQty !== null && $confirmQty > 0) ? min($confirmQty, $remaining) : $remaining;

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

            // 累加 shipped_qty，不覆寫 quantity
            $newShipped = $alreadyShipped + $qty;
            $isDone = ($newShipped >= $needQty) ? 1 : 0;
            $confirmedAt = $isDone ? 'NOW()' : 'confirmed_at'; // 只在完成時才更新 confirmed_at
            if ($isDone) {
                $this->db->prepare("UPDATE stock_out_items SET shipped_qty = ?, is_confirmed = 1, confirmed_at = NOW() WHERE id = ?")
                         ->execute(array($newShipped, $itemId));
            } else {
                $this->db->prepare("UPDATE stock_out_items SET shipped_qty = ?, is_confirmed = 0 WHERE id = ?")
                         ->execute(array($newShipped, $itemId));
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
     * 更新出庫單狀態（依品項出貨進度）
     *
     * 邏輯（Migration 111 之後）：
     *   - 全部品項 shipped_qty >= quantity → 已確認（已出庫）
     *   - 有任何品項 shipped_qty > 0 但未全部完成 → 部分出庫
     *   - 全部 shipped_qty == 0 → 不動（維持原狀態 待確認/已預扣/已備貨）
     */
    private function updateStockOutStatus($id, $userId)
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(quantity), 0) AS total_need,
                COALESCE(SUM(shipped_qty), 0) AS total_shipped,
                COALESCE(SUM(CASE WHEN shipped_qty > 0 THEN 1 ELSE 0 END), 0) AS any_shipped_count,
                COALESCE(SUM(CASE WHEN shipped_qty >= quantity THEN 1 ELSE 0 END), 0) AS fully_shipped_count
            FROM stock_out_items
            WHERE stock_out_id = ?
        ");
        $stmt->execute(array($id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['total_count'] === 0) return;

        $totalCount       = (int)$row['total_count'];
        $fullyShippedCnt  = (int)$row['fully_shipped_count'];
        $anyShippedCnt    = (int)$row['any_shipped_count'];

        if ($fullyShippedCnt >= $totalCount) {
            // 所有品項都完成 → 已確認
            // so_date 保留使用者設定的預計出庫日（只在為 NULL 時補今天）
            // 確認時間另存於 confirmed_at，不污染 so_date
            $this->db->prepare("UPDATE stock_outs SET status = '已確認', so_date = IFNULL(so_date, CURDATE()), confirmed_by = ?, confirmed_at = NOW() WHERE id = ?")
                     ->execute(array($userId, $id));
        } elseif ($anyShippedCnt > 0) {
            // 有出但未完成 → 部分出庫
            $this->db->prepare("UPDATE stock_outs SET status = '部分出庫' WHERE id = ?")->execute(array($id));
        }
        // else: 全都 shipped_qty = 0 → 維持原狀（待確認/已預扣/已備貨）
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
    // ADMIN 工具區（測試期專用 - 完成後可移除）
    // 標記：ADMIN_TOOL_BLOCK_START / ADMIN_TOOL_BLOCK_END
    // ============================================================
    // ADMIN_TOOL_BLOCK_START

    /**
     * ADMIN: 出庫單刪除前防呆檢查
     * 任一條件成立都不允許刪除（避免下游連結遺失）
     * @return array 空陣列=可刪；非空=拒絕原因清單
     */
    public function checkStockOutDeletable($id)
    {
        $reasons = array();
        $record = $this->getStockOutById($id);
        if (!$record) {
            $reasons[] = '出庫單不存在';
            return $reasons;
        }

        // 1) 是否已開過餘料入庫單（manual_return 子單）
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM stock_ins WHERE source_type = 'manual_return' AND source_id = ?");
        $stmt->execute(array($id));
        if ((int)$stmt->fetchColumn() > 0) {
            $reasons[] = '此出庫單已開過餘料入庫單，請先處理子單據';
        }

        // 2) 是否有 inventory_transactions 引用
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM inventory_transactions WHERE reference_type = 'stock_out' AND reference_id = ?");
        $stmt->execute(array($id));
        if ((int)$stmt->fetchColumn() > 0) {
            $reasons[] = '此出庫單已產生庫存異動紀錄，刪除會造成庫存不一致（請改用盤點單調整）';
        }

        // 3) 是否有任何品項已實際出貨 (shipped_qty > 0)
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(shipped_qty), 0) FROM stock_out_items WHERE stock_out_id = ?");
        $stmt->execute(array($id));
        if ((int)$stmt->fetchColumn() > 0) {
            $reasons[] = '此出庫單已有品項實際出貨 (shipped_qty > 0)';
        }

        // 4) 是否有對應的自動分錄（保守起見全擋）
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM journal_entries WHERE source_module = 'stock_out' AND source_id = ?");
            $stmt->execute(array($id));
            if ((int)$stmt->fetchColumn() > 0) {
                $reasons[] = '此出庫單已產生會計分錄，請先處理分錄';
            }
        } catch (Exception $e) { /* journal_entries 表不存在則略 */ }

        return $reasons;
    }

    /**
     * ADMIN: 硬刪出庫單（呼叫前必須先 checkStockOutDeletable）
     */
    public function deleteStockOutHard($id)
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM stock_out_items WHERE stock_out_id = ?")->execute(array($id));
            $this->db->prepare("DELETE FROM stock_outs WHERE id = ?")->execute(array($id));
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * ADMIN: 修改出庫單客戶基本資訊
     * @param int $id
     * @param array $data 允許 customer_name + customer_id（由 autocomplete 帶入）
     */
    public function updateStockOutBasic($id, $data)
    {
        $sets = array();
        $params = array();
        if (array_key_exists('customer_name', $data)) {
            $sets[] = 'customer_name = ?';
            $params[] = !empty($data['customer_name']) ? $data['customer_name'] : null;
        }
        if (array_key_exists('customer_id', $data)) {
            $sets[] = 'customer_id = ?';
            $params[] = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
        }
        if (empty($sets)) return false;
        $params[] = $id;
        $sql = "UPDATE stock_outs SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
        $this->db->prepare($sql)->execute($params);
        return true;
    }

    /**
     * ADMIN: 入庫單刪除前防呆檢查
     */
    public function checkStockInDeletable($id)
    {
        $reasons = array();
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM stock_ins WHERE id = ?");
        $stmt->execute(array($id));
        if ((int)$stmt->fetchColumn() === 0) {
            $reasons[] = '入庫單不存在';
            return $reasons;
        }

        // 1) 是否有 inventory_transactions 引用
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM inventory_transactions WHERE reference_type = 'stock_in' AND reference_id = ?");
        $stmt->execute(array($id));
        if ((int)$stmt->fetchColumn() > 0) {
            $reasons[] = '此入庫單已產生庫存異動紀錄，刪除會造成庫存不一致（請改用盤點單調整）';
        }

        // 2) 是否有任何品項已實際入庫
        try {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(received_qty), 0) FROM stock_in_items WHERE stock_in_id = ?");
            $stmt->execute(array($id));
            if ((int)$stmt->fetchColumn() > 0) {
                $reasons[] = '此入庫單已有品項實際入庫 (received_qty > 0)';
            }
        } catch (Exception $e) { /* 欄位不存在略 */ }

        // 3) 對應自動分錄
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM journal_entries WHERE source_module = 'stock_in' AND source_id = ?");
            $stmt->execute(array($id));
            if ((int)$stmt->fetchColumn() > 0) {
                $reasons[] = '此入庫單已產生會計分錄，請先處理分錄';
            }
        } catch (Exception $e) {}

        return $reasons;
    }

    /**
     * ADMIN: 硬刪入庫單
     */
    public function deleteStockInHard($id)
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM stock_in_items WHERE stock_in_id = ?")->execute(array($id));
            $this->db->prepare("DELETE FROM stock_ins WHERE id = ?")->execute(array($id));
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * ADMIN: 修改入庫單廠商/客戶基本資訊
     * 注意：stock_ins 表只有 vendor_name / customer_name 欄位，無 vendor_id / customer_id
     * @param int $id
     * @param array $data 允許 vendor_name, customer_name
     */
    public function updateStockInBasic($id, $data)
    {
        $sets = array();
        $params = array();
        if (array_key_exists('vendor_name', $data)) {
            $sets[] = 'vendor_name = ?';
            $params[] = !empty($data['vendor_name']) ? $data['vendor_name'] : null;
        }
        if (array_key_exists('customer_name', $data)) {
            $sets[] = 'customer_name = ?';
            $params[] = !empty($data['customer_name']) ? $data['customer_name'] : null;
        }
        if (empty($sets)) return false;
        $params[] = $id;
        $sql = "UPDATE stock_ins SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
        $this->db->prepare($sql)->execute($params);
        return true;
    }

    // ADMIN_TOOL_BLOCK_END

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
