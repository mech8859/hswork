<?php
/**
 * 退貨單資料模型
 */
class ReturnModel
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

    public static function returnTypeOptions()
    {
        return array(
            'customer_return' => '客退',
            'vendor_return'   => '退供應商',
        );
    }

    public static function returnTypeLabel($type)
    {
        $map = self::returnTypeOptions();
        return isset($map[$type]) ? $map[$type] : $type;
    }

    public static function statusOptions()
    {
        return array(
            'draft'     => '草稿',
            'confirmed' => '已確認',
            'cancelled' => '已取消',
        );
    }

    public static function statusLabel($status)
    {
        $map = self::statusOptions();
        return isset($map[$status]) ? $map[$status] : $status;
    }

    // ============================================================
    // 編號產生
    // ============================================================

    public function generateNumber()
    {
        // Try number_sequences first
        try {
            return generate_doc_number('returns');
        } catch (Exception $e) {
            // fallback
        }
        $date = date('Ymd');
        $like = 'RT-' . $date . '-%';
        $stmt = $this->db->prepare("SELECT return_number FROM returns WHERE return_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(array($like));
        $last = $stmt->fetchColumn();
        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $seq = (int)end($parts) + 1;
        }
        return 'RT-' . $date . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    // ============================================================
    // 列表
    // ============================================================

    public function getList($filters = array())
    {
        $where = array('1=1');
        $params = array();

        if (!empty($filters['return_type'])) {
            $where[] = 'r.return_type = ?';
            $params[] = $filters['return_type'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'r.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(r.return_number LIKE ? OR r.customer_name LIKE ? OR r.vendor_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'r.return_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'r.return_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'r.warehouse_id = ?';
            $params[] = $filters['warehouse_id'];
        }

        $sql = "SELECT r.*, w.name AS warehouse_name, u.real_name AS created_by_name, b.name AS branch_name
                FROM returns r
                LEFT JOIN warehouses w ON r.warehouse_id = w.id
                LEFT JOIN users u ON r.created_by = u.id
                LEFT JOIN branches b ON r.branch_id = b.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 單筆查詢（含明細）
    // ============================================================

    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT r.*, w.name AS warehouse_name, u.real_name AS created_by_name
            FROM returns r
            LEFT JOIN warehouses w ON r.warehouse_id = w.id
            LEFT JOIN users u ON r.created_by = u.id
            WHERE r.id = ?
        ");
        $stmt->execute(array($id));
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) return null;

        $record['items'] = $this->getItems($id);
        return $record;
    }

    public function getItems($returnId)
    {
        $stmt = $this->db->prepare("
            SELECT ri.*, p.model AS product_model
            FROM return_items ri
            LEFT JOIN products p ON ri.product_id = p.id
            WHERE ri.return_id = ?
            ORDER BY ri.id
        ");
        $stmt->execute(array($returnId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 新增
    // ============================================================

    public function create($data)
    {
        $this->db->beginTransaction();
        try {
            $number = $this->generateNumber();

            $stmt = $this->db->prepare("
                INSERT INTO returns (return_number, return_date, return_type, branch_id, warehouse_id,
                    reference_type, reference_id, customer_name, vendor_name,
                    status, total_amount, reason, note, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute(array(
                $number,
                !empty($data['return_date']) ? $data['return_date'] : date('Y-m-d'),
                !empty($data['return_type']) ? $data['return_type'] : 'customer_return',
                !empty($data['branch_id']) ? $data['branch_id'] : null,
                !empty($data['warehouse_id']) ? $data['warehouse_id'] : null,
                !empty($data['reference_type']) ? $data['reference_type'] : null,
                !empty($data['reference_id']) ? $data['reference_id'] : null,
                !empty($data['customer_name']) ? $data['customer_name'] : null,
                !empty($data['vendor_name']) ? $data['vendor_name'] : null,
                !empty($data['total_amount']) ? $data['total_amount'] : 0,
                !empty($data['reason']) ? $data['reason'] : null,
                !empty($data['note']) ? $data['note'] : null,
                !empty($data['created_by']) ? $data['created_by'] : null,
            ));
            $id = $this->db->lastInsertId();

            if (!empty($data['items'])) {
                $this->saveItems($id, $data['items']);
            }

            $this->db->commit();
            return $id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ============================================================
    // 更新
    // ============================================================

    public function update($id, $data)
    {
        $record = $this->getById($id);
        if (!$record || $record['status'] !== 'draft') {
            throw new Exception('只能編輯草稿狀態的退貨單');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE returns SET
                    return_date = ?, return_type = ?, branch_id = ?, warehouse_id = ?,
                    reference_type = ?, reference_id = ?,
                    customer_name = ?, vendor_name = ?,
                    total_amount = ?, reason = ?, note = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute(array(
                !empty($data['return_date']) ? $data['return_date'] : $record['return_date'],
                !empty($data['return_type']) ? $data['return_type'] : $record['return_type'],
                !empty($data['branch_id']) ? $data['branch_id'] : null,
                !empty($data['warehouse_id']) ? $data['warehouse_id'] : $record['warehouse_id'],
                !empty($data['reference_type']) ? $data['reference_type'] : null,
                !empty($data['reference_id']) ? $data['reference_id'] : null,
                !empty($data['customer_name']) ? $data['customer_name'] : null,
                !empty($data['vendor_name']) ? $data['vendor_name'] : null,
                !empty($data['total_amount']) ? $data['total_amount'] : 0,
                !empty($data['reason']) ? $data['reason'] : null,
                !empty($data['note']) ? $data['note'] : null,
                $id,
            ));

            if (isset($data['items'])) {
                $this->saveItems($id, $data['items']);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ============================================================
    // 確認退貨
    // ============================================================

    public function confirm($id, $userId)
    {
        $record = $this->getById($id);
        if (!$record) {
            throw new Exception('退貨單不存在');
        }
        if ($record['status'] !== 'draft') {
            throw new Exception('只能確認草稿狀態的退貨單');
        }

        $this->db->beginTransaction();
        try {
            // Update status
            $stmt = $this->db->prepare("UPDATE returns SET status = 'confirmed', updated_at = NOW() WHERE id = ?");
            $stmt->execute(array($id));

            // Adjust inventory
            if (!empty($record['warehouse_id']) && !empty($record['items'])) {
                require_once __DIR__ . '/../inventory/InventoryModel.php';
                $invModel = new InventoryModel();

                foreach ($record['items'] as $item) {
                    if (empty($item['product_id']) || $item['quantity'] <= 0) {
                        continue;
                    }

                    if ($record['return_type'] === 'customer_return') {
                        // Customer return => stock comes back in (positive qty)
                        $invModel->adjustStock(
                            $item['product_id'],
                            $record['warehouse_id'],
                            $item['quantity'],
                            'return_in',
                            'return',
                            $id,
                            '客退入庫: ' . $record['return_number'],
                            $userId
                        );
                    } else {
                        // Vendor return => stock goes out (negative qty)
                        $invModel->adjustStock(
                            $item['product_id'],
                            $record['warehouse_id'],
                            -$item['quantity'],
                            'return_out',
                            'return',
                            $id,
                            '退供應商出庫: ' . $record['return_number'],
                            $userId
                        );
                    }
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ============================================================
    // 刪除（僅限草稿）
    // ============================================================

    public function delete($id)
    {
        $record = $this->getById($id);
        if (!$record) {
            throw new Exception('退貨單不存在');
        }
        if ($record['status'] !== 'draft') {
            throw new Exception('只能刪除草稿狀態的退貨單');
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM return_items WHERE return_id = ?")->execute(array($id));
            $this->db->prepare("DELETE FROM returns WHERE id = ?")->execute(array($id));
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ============================================================
    // 明細儲存
    // ============================================================

    private function saveItems($returnId, $items)
    {
        // Delete existing
        $this->db->prepare("DELETE FROM return_items WHERE return_id = ?")->execute(array($returnId));

        $stmt = $this->db->prepare("
            INSERT INTO return_items (return_id, product_id, product_name, quantity, unit_price, amount, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            if (empty($item['product_name']) && empty($item['product_id'])) {
                continue;
            }
            $qty = !empty($item['quantity']) ? (float)$item['quantity'] : 0;
            $price = !empty($item['unit_price']) ? (float)$item['unit_price'] : 0;
            $amount = !empty($item['amount']) ? (float)$item['amount'] : $qty * $price;

            $stmt->execute(array(
                $returnId,
                !empty($item['product_id']) ? $item['product_id'] : null,
                !empty($item['product_name']) ? $item['product_name'] : null,
                $qty,
                $price,
                $amount,
                !empty($item['reason']) ? $item['reason'] : null,
            ));
        }
    }

    // ============================================================
    // 輔助
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
