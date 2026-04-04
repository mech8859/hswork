<?php
/**
 * 出貨單模型
 */
class DeliveryModel
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

    public static function statusOptions()
    {
        return array(
            '草稿'   => '草稿',
            '待確認' => '待確認',
            '已確認' => '已確認',
            '已出貨' => '已出貨',
            '已完成' => '已完成',
            '已取消' => '已取消',
        );
    }

    public static function statusLabel($status)
    {
        $opts = self::statusOptions();
        return isset($opts[$status]) ? $opts[$status] : $status;
    }

    public static function statusBadgeColor($status)
    {
        $map = array(
            '草稿'   => 'orange',
            '待確認' => 'blue',
            '已確認' => 'teal',
            '已出貨' => 'blue',
            '已完成' => 'green',
            '已取消' => 'gray',
        );
        return isset($map[$status]) ? $map[$status] : 'gray';
    }

    public static function statusBadge($status)
    {
        $map = array(
            '草稿'   => 'warning',
            '待確認' => 'info',
            '已確認' => 'info',
            '已出貨' => 'primary',
            '已完成' => 'success',
            '已取消' => 'danger',
        );
        return isset($map[$status]) ? $map[$status] : '';
    }

    // ============================================================
    // 列表
    // ============================================================

    /**
     * 出貨單列表
     * @param array $filters  status, keyword, month, warehouse_id
     * @return array
     */
    public function getList($filters = array())
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['status'])) {
            $where .= ' AND d.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (d.do_number LIKE ? OR d.case_name LIKE ? OR d.receiver_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['month'])) {
            $where .= ' AND d.do_date LIKE ?';
            $params[] = $filters['month'] . '%';
        }
        if (!empty($filters['warehouse_id'])) {
            $where .= ' AND d.warehouse_id = ?';
            $params[] = $filters['warehouse_id'];
        }

        $stmt = $this->db->prepare("
            SELECT d.*, w.name AS warehouse_name,
                   u.real_name AS created_by_name
            FROM delivery_orders d
            LEFT JOIN warehouses w ON d.warehouse_id = w.id
            LEFT JOIN users u ON d.created_by = u.id
            WHERE {$where}
            ORDER BY d.id DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 單筆
    // ============================================================

    /**
     * 取得出貨單（含明細）
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT d.*, w.name AS warehouse_name,
                   u.real_name AS created_by_name,
                   cu.real_name AS confirmed_by_name,
                   c.case_number, c.title AS case_title
            FROM delivery_orders d
            LEFT JOIN warehouses w ON d.warehouse_id = w.id
            LEFT JOIN users u ON d.created_by = u.id
            LEFT JOIN users cu ON d.confirmed_by = cu.id
            LEFT JOIN cases c ON d.case_id = c.id
            WHERE d.id = ?
        ");
        $stmt->execute(array($id));
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return null;

        // 載入明細
        $itemStmt = $this->db->prepare("
            SELECT di.*, p.name AS db_product_name, p.model AS db_model, p.unit AS db_unit, p.price AS db_price
            FROM delivery_order_items di
            LEFT JOIN products p ON di.product_id = p.id
            WHERE di.delivery_order_id = ?
            ORDER BY di.sort_order, di.id
        ");
        $itemStmt->execute(array($id));
        $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        return $order;
    }

    // ============================================================
    // 新增/更新
    // ============================================================

    /**
     * 新增出貨單
     */
    public function create($data)
    {
        $number = $this->generateNumber();
        $stmt = $this->db->prepare("
            INSERT INTO delivery_orders
                (do_number, do_date, case_id, case_name,
                 delivery_address, receiver_name, warehouse_id, status, note, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, '草稿', ?, ?, NOW())
        ");
        $stmt->execute(array(
            $number,
            !empty($data['do_date']) ? $data['do_date'] : date('Y-m-d'),
            !empty($data['case_id']) ? $data['case_id'] : null,
            !empty($data['case_name']) ? $data['case_name'] : null,
            !empty($data['delivery_address']) ? $data['delivery_address'] : null,
            !empty($data['receiver_name']) ? $data['receiver_name'] : null,
            !empty($data['warehouse_id']) ? $data['warehouse_id'] : null,
            !empty($data['note']) ? $data['note'] : null,
            Auth::id(),
        ));
        $orderId = (int)$this->db->lastInsertId();

        // 儲存明細
        if (!empty($data['items'])) {
            $this->saveItems($orderId, $data['items']);
        }

        return $orderId;
    }

    /**
     * 更新出貨單
     */
    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE delivery_orders SET
                do_date = ?, case_id = ?, case_name = ?,
                delivery_address = ?, receiver_name = ?, warehouse_id = ?, note = ?,
                updated_at = NOW()
            WHERE id = ? AND status = '草稿'
        ");
        $stmt->execute(array(
            !empty($data['do_date']) ? $data['do_date'] : date('Y-m-d'),
            !empty($data['case_id']) ? $data['case_id'] : null,
            !empty($data['case_name']) ? $data['case_name'] : null,
            !empty($data['delivery_address']) ? $data['delivery_address'] : null,
            !empty($data['receiver_name']) ? $data['receiver_name'] : null,
            !empty($data['warehouse_id']) ? $data['warehouse_id'] : null,
            !empty($data['note']) ? $data['note'] : null,
            $id,
        ));

        // 重新儲存明細
        if (isset($data['items'])) {
            $this->saveItems($id, $data['items']);
        }
    }

    /**
     * 儲存出貨明細（delete-all-then-insert）
     */
    private function saveItems($orderId, $items)
    {
        $this->db->prepare('DELETE FROM delivery_order_items WHERE delivery_order_id = ?')->execute(array($orderId));

        $stmt = $this->db->prepare("
            INSERT INTO delivery_order_items
                (delivery_order_id, product_id, product_name, model, spec, unit, quantity, note, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $totalQty = 0;
        $sortOrder = 0;
        foreach ($items as $item) {
            if (empty($item['product_name']) && empty($item['product_id'])) continue;
            $qty = (float)(isset($item['quantity']) ? $item['quantity'] : 0);
            if ($qty <= 0) $qty = 1;

            $stmt->execute(array(
                $orderId,
                !empty($item['product_id']) ? $item['product_id'] : null,
                !empty($item['product_name']) ? $item['product_name'] : '',
                !empty($item['model']) ? $item['model'] : null,
                !empty($item['spec']) ? $item['spec'] : null,
                !empty($item['unit']) ? $item['unit'] : '個',
                $qty,
                !empty($item['note']) ? $item['note'] : null,
                $sortOrder++,
            ));
            $totalQty += $qty;
        }

        // 更新合計數量
        $this->db->prepare('UPDATE delivery_orders SET total_qty = ? WHERE id = ?')
            ->execute(array($totalQty, $orderId));
    }

    // ============================================================
    // 狀態操作
    // ============================================================

    /**
     * 更新狀態
     */
    public function updateStatus($id, $status)
    {
        $this->db->prepare('UPDATE delivery_orders SET status = ?, updated_at = NOW() WHERE id = ?')
            ->execute(array($status, $id));
    }

    /**
     * 確認出貨單 -> 自動產生出庫單
     */
    public function confirm($id)
    {
        $order = $this->getById($id);
        if (!$order || $order['status'] !== '草稿') {
            return false;
        }

        $this->db->beginTransaction();
        try {
            // 更新出貨單狀態
            $this->db->prepare("UPDATE delivery_orders SET status = '已確認', confirmed_by = ?, confirmed_at = NOW(), updated_at = NOW() WHERE id = ?")
                ->execute(array(Auth::id(), $id));

            // 產生出庫單
            $stockoutNumber = generate_doc_number('stock_outs');
            $soStmt = $this->db->prepare("
                INSERT INTO stock_outs
                    (so_number, so_date, warehouse_id, source_type, source_id, source_number, status, note, created_by, created_at)
                VALUES (?, ?, ?, 'delivery_order', ?, ?, '待確認', ?, ?, NOW())
            ");
            $soStmt->execute(array(
                $stockoutNumber,
                date('Y-m-d'),
                $order['warehouse_id'],
                $id,
                $order['do_number'],
                '出貨單 ' . $order['do_number'] . ' 自動產生',
                Auth::id(),
            ));
            $stockOutId = (int)$this->db->lastInsertId();

            // 出庫明細
            $soItemStmt = $this->db->prepare("
                INSERT INTO stock_out_items (stock_out_id, product_id, model, product_name, spec, unit, quantity, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $sort = 0;
            $totalQty = 0;
            foreach ($order['items'] as $item) {
                if (empty($item['product_id'])) continue;
                $soItemStmt->execute(array(
                    $stockOutId,
                    $item['product_id'],
                    !empty($item['model']) ? $item['model'] : null,
                    !empty($item['product_name']) ? $item['product_name'] : null,
                    !empty($item['spec']) ? $item['spec'] : null,
                    !empty($item['unit']) ? $item['unit'] : null,
                    $item['quantity'],
                    $sort++,
                ));
                $totalQty += (float)$item['quantity'];
            }
            // Update stock_out total_qty
            $this->db->prepare("UPDATE stock_outs SET total_qty = ? WHERE id = ?")
                ->execute(array($totalQty, $stockOutId));

            $this->db->commit();
            return $stockOutId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 出貨
     */
    public function ship($id)
    {
        $order = $this->getById($id);
        if (!$order || $order['status'] !== '已確認') {
            return false;
        }
        $this->updateStatus($id, '已出貨');
        return true;
    }

    /**
     * 完成
     */
    public function complete($id)
    {
        $order = $this->getById($id);
        if (!$order || $order['status'] !== '已出貨') {
            return false;
        }
        $this->updateStatus($id, '已完成');
        return true;
    }

    /**
     * 刪除（僅草稿）
     */
    public function delete($id)
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM delivery_order_items WHERE delivery_order_id = ? AND EXISTS (SELECT 1 FROM delivery_orders WHERE id = ? AND status = '草稿')")
                ->execute(array($id, $id));
            $this->db->prepare("DELETE FROM delivery_orders WHERE id = ? AND status = '草稿'")
                ->execute(array($id));
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ============================================================
    // 從報價單建立
    // ============================================================

    /**
     * 從報價單預填出貨單資料
     * @return array 預填資料
     */
    public function createFromQuotation($quotationId)
    {
        require_once __DIR__ . '/../quotations/QuotationModel.php';
        $qModel = new QuotationModel();
        $quote = $qModel->getById($quotationId);
        if (!$quote) return null;

        $items = array();
        foreach ($quote['sections'] as $sec) {
            foreach ($sec['items'] as $item) {
                $items[] = array(
                    'product_id' => $item['product_id'],
                    'product_name' => $item['item_name'],
                    'model' => isset($item['model_number']) ? $item['model_number'] : '',
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                );
            }
        }

        return array(
            'case_id' => $quote['case_id'],
            'case_name' => $quote['customer_name'],
            'delivery_address' => !empty($quote['site_address']) ? $quote['site_address'] : '',
            'items' => $items,
        );
    }

    // ============================================================
    // 輔助
    // ============================================================

    /**
     * 產生出貨單號
     */
    public function generateNumber()
    {
        return generate_doc_number('delivery_orders');
    }

    /**
     * 取得倉庫列表
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
     * 取得分公司
     */
    public function getBranches()
    {
        return $this->db->query('SELECT id, name FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得案件選項
     */
    public function getCaseOptions()
    {
        $stmt = $this->db->query("
            SELECT id, case_number, title FROM cases
            WHERE status NOT IN ('completed','cancelled')
            ORDER BY id DESC
            LIMIT 200
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得報價單選項（已接受）
     */
    public function getQuotationOptions()
    {
        $stmt = $this->db->query("
            SELECT id, quotation_number, customer_name, total_amount
            FROM quotations
            WHERE status IN ('customer_accepted','accepted','approved','sent')
            ORDER BY id DESC
            LIMIT 200
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
