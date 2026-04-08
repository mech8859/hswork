<?php
/**
 * 進貨單資料模型
 * 進貨單 CRUD、從採購單建立、確認進貨 → 自動建立入庫單
 */
class GoodsReceiptModel
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
            '已取消' => '已取消',
        );
    }

    public static function statusBadgeColor($status)
    {
        $map = array(
            '草稿'   => 'gray',
            '待確認' => 'orange',
            '已確認' => 'green',
            '已取消' => 'gray',
        );
        return isset($map[$status]) ? $map[$status] : 'gray';
    }

    // ============================================================
    // 編號生成
    // ============================================================

    public function generateNumber()
    {
        return generate_doc_number('goods_receipts');
    }

    // ============================================================
    // 列表查詢
    // ============================================================

    /**
     * 進貨單列表（含篩選）
     * @param array $filters  status, vendor_name, keyword, date_from, date_to, warehouse_id
     * @return array
     */
    public function getList($filters = array(), $page = 1, $perPage = 100)
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['status'])) {
            $where .= ' AND gr.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['vendor_name'])) {
            $where .= ' AND gr.vendor_name LIKE ?';
            $params[] = '%' . $filters['vendor_name'] . '%';
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (gr.gr_number LIKE ? OR gr.vendor_name LIKE ? OR gr.po_number LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND gr.gr_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND gr.gr_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where .= ' AND gr.warehouse_id = ?';
            $params[] = $filters['warehouse_id'];
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM goods_receipts gr WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT gr.*, w.name AS warehouse_name,
                   u.real_name AS created_by_name
            FROM goods_receipts gr
            LEFT JOIN warehouses w ON gr.warehouse_id = w.id
            LEFT JOIN users u ON gr.created_by = u.id
            WHERE {$where}
            ORDER BY gr.gr_date DESC, gr.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        return array('data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => ceil($total / $perPage));
    }

    // ============================================================
    // 單筆查詢
    // ============================================================

    /**
     * 取得進貨單（含明細）
     * @param int $id
     * @return array|null
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT gr.*, w.name AS warehouse_name,
                   u.real_name AS created_by_name,
                   cu.real_name AS confirmed_by_name
            FROM goods_receipts gr
            LEFT JOIN warehouses w ON gr.warehouse_id = w.id
            LEFT JOIN users u ON gr.created_by = u.id
            LEFT JOIN users cu ON gr.confirmed_by = cu.id
            WHERE gr.id = ?
        ");
        $stmt->execute(array($id));
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        return $record ? $record : null;
    }

    /**
     * 取得進貨單明細
     */
    public function getItems($goodsReceiptId)
    {
        $stmt = $this->db->prepare("
            SELECT gi.*, p.name AS db_product_name, p.model AS db_model
            FROM goods_receipt_items gi
            LEFT JOIN products p ON gi.product_id = p.id
            WHERE gi.goods_receipt_id = ?
            ORDER BY gi.sort_order, gi.id
        ");
        $stmt->execute(array($goodsReceiptId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 新增
    // ============================================================

    /**
     * 建立進貨單
     * @param array $data
     * @return int  新建 ID
     */
    public function create($data)
    {
        $number = $this->generateNumber();
        $stmt = $this->db->prepare("
            INSERT INTO goods_receipts
                (gr_number, gr_date, status, po_id, po_number, vendor_id, vendor_name,
                 warehouse_id, branch_id, branch_name, receiver_name, note, total_qty, total_amount,
                 paid_amount, paid_date, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(array(
            $number,
            !empty($data['gr_date']) ? $data['gr_date'] : date('Y-m-d'),
            !empty($data['status']) ? $data['status'] : '草稿',
            !empty($data['po_id']) ? $data['po_id'] : null,
            !empty($data['po_number']) ? $data['po_number'] : null,
            !empty($data['vendor_id']) ? $data['vendor_id'] : null,
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
            !empty($data['warehouse_id']) ? $data['warehouse_id'] : null,
            !empty($data['branch_id']) ? $data['branch_id'] : null,
            !empty($data['branch_name']) ? $data['branch_name'] : null,
            !empty($data['receiver_name']) ? $data['receiver_name'] : null,
            !empty($data['note']) ? $data['note'] : null,
            !empty($data['total_qty']) ? $data['total_qty'] : 0,
            !empty($data['total_amount']) ? $data['total_amount'] : 0,
            !empty($data['paid_amount']) ? $data['paid_amount'] : null,
            !empty($data['paid_date']) ? $data['paid_date'] : null,
            $data['created_by'],
        ));
        $grId = $this->db->lastInsertId();

        // 儲存明細
        if (!empty($data['items'])) {
            $this->saveItems($grId, $data['items']);
        }

        return $grId;
    }

    // ============================================================
    // 更新
    // ============================================================

    /**
     * 更新進貨單
     * @param int $id
     * @param array $data
     */
    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE goods_receipts SET
                gr_date = ?, status = ?, po_id = ?, po_number = ?,
                vendor_id = ?, vendor_name = ?, warehouse_id = ?,
                receiver_name = ?, note = ?, total_qty = ?, total_amount = ?,
                updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute(array(
            !empty($data['gr_date']) ? $data['gr_date'] : date('Y-m-d'),
            !empty($data['status']) ? $data['status'] : '草稿',
            !empty($data['po_id']) ? $data['po_id'] : null,
            !empty($data['po_number']) ? $data['po_number'] : null,
            !empty($data['vendor_id']) ? $data['vendor_id'] : null,
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
            !empty($data['warehouse_id']) ? $data['warehouse_id'] : null,
            !empty($data['receiver_name']) ? $data['receiver_name'] : null,
            !empty($data['note']) ? $data['note'] : null,
            !empty($data['total_qty']) ? $data['total_qty'] : 0,
            !empty($data['total_amount']) ? $data['total_amount'] : 0,
            !empty($data['updated_by']) ? $data['updated_by'] : null,
            $id,
        ));

        // 重新儲存明細
        if (isset($data['items'])) {
            $this->saveItems($id, $data['items']);
        }
    }

    /**
     * 儲存進貨單明細（先刪後插）
     */
    public function saveItems($goodsReceiptId, $items)
    {
        $this->db->prepare("DELETE FROM goods_receipt_items WHERE goods_receipt_id = ?")
            ->execute(array($goodsReceiptId));

        if (empty($items)) return;

        $stmt = $this->db->prepare("
            INSERT INTO goods_receipt_items
                (goods_receipt_id, product_id, model, product_name, spec, unit,
                 po_qty, received_qty, unit_price, amount, note, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $sort = 0;
        foreach ($items as $item) {
            if (empty($item['product_name']) && empty($item['model'])) continue;
            $receivedQty = !empty($item['received_qty']) ? $item['received_qty'] : 0;
            $unitPrice = !empty($item['unit_price']) ? $item['unit_price'] : 0;
            $stmt->execute(array(
                $goodsReceiptId,
                !empty($item['product_id']) ? $item['product_id'] : null,
                !empty($item['model']) ? $item['model'] : null,
                !empty($item['product_name']) ? $item['product_name'] : null,
                !empty($item['spec']) ? $item['spec'] : null,
                !empty($item['unit']) ? $item['unit'] : null,
                !empty($item['po_qty']) ? $item['po_qty'] : 0,
                $receivedQty,
                $unitPrice,
                round($receivedQty * $unitPrice),
                !empty($item['note']) ? $item['note'] : null,
                $sort++,
            ));
        }
    }

    // ============================================================
    // 狀態變更
    // ============================================================

    /**
     * 更新狀態
     */
    public function updateStatus($id, $status)
    {
        $stmt = $this->db->prepare("UPDATE goods_receipts SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute(array($status, $id));
    }

    /**
     * 確認進貨單 → 自動建立入庫單 → 更新庫存
     * @param int $id
     * @param int $userId
     * @return int|false  stock_in ID or false
     */
    public function confirm($id, $userId)
    {
        $record = $this->getById($id);
        if (!$record || $record['status'] === '已確認') {
            return false;
        }

        $items = $this->getItems($id);
        if (empty($items)) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            // 更新進貨單狀態
            $stmt = $this->db->prepare("
                UPDATE goods_receipts SET status = '已確認', confirmed_by = ?, confirmed_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute(array($userId, $id));

            // 建立入庫單
            require_once __DIR__ . '/../inventory/StockModel.php';
            $stockModel = new StockModel();

            $siData = array(
                'si_date'       => date('Y-m-d'),
                'source_type'   => 'goods_receipt',
                'source_id'     => $id,
                'source_number' => $record['gr_number'],
                'warehouse_id'  => $record['warehouse_id'],
                'branch_id'     => !empty($record['branch_id']) ? $record['branch_id'] : null,
                'branch_name'   => !empty($record['branch_name']) ? $record['branch_name'] : null,
                'vendor_name'   => !empty($record['vendor_name']) ? $record['vendor_name'] : null,
                'note'          => '由進貨單 ' . $record['gr_number'] . ' 自動建立',
                'created_by'    => $userId,
                'items'         => array(),
            );

            $totalQty = 0;
            foreach ($items as $item) {
                if ($item['received_qty'] > 0) {
                    $siData['items'][] = array(
                        'product_id'   => $item['product_id'],
                        'model'        => $item['model'],
                        'product_name' => $item['product_name'],
                        'spec'         => $item['spec'],
                        'unit'         => $item['unit'],
                        'quantity'     => $item['received_qty'],
                        'unit_price'   => $item['unit_price'],
                    );
                    $totalQty += $item['received_qty'];
                }
            }
            $siData['total_qty'] = $totalQty;

            $stockInId = $stockModel->createStockIn($siData);

            // 自動確認入庫單（更新庫存）
            $stockModel->confirmStockIn($stockInId, $userId);

            // 同步廠商產品對照表
            try {
                require_once __DIR__ . '/VendorProductModel.php';
                $vpModel = new VendorProductModel();
                $vpModel->syncFromGoodsReceipt($id);
            } catch (Exception $vpEx) {
                // 對照同步失敗不影響主流程
            }

            $this->db->commit();
            return $stockInId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ============================================================
    // 刪除
    // ============================================================

    /**
     * 刪除進貨單（僅草稿狀態可刪除）
     */
    public function delete($id)
    {
        $record = $this->getById($id);
        if (!$record || $record['status'] !== '草稿') {
            return false;
        }
        $this->db->prepare("DELETE FROM goods_receipt_items WHERE goods_receipt_id = ?")->execute(array($id));
        $this->db->prepare("DELETE FROM goods_receipts WHERE id = ?")->execute(array($id));
        return true;
    }

    // ============================================================
    // ADMIN 工具區（測試期專用 - 完成後可移除）
    // 標記：ADMIN_TOOL_BLOCK_START / ADMIN_TOOL_BLOCK_END
    // ============================================================
    // ADMIN_TOOL_BLOCK_START

    /**
     * ADMIN: 進貨單刪除前防呆檢查
     * @return array 空陣列=可刪；非空=拒絕原因清單
     */
    public function checkDeletable($id)
    {
        $reasons = array();
        $record = $this->getById($id);
        if (!$record) {
            $reasons[] = '進貨單不存在';
            return $reasons;
        }
        $grNumber = $record['gr_number'];

        // 1) 是否被應付帳款引用 (payable_purchase_details.purchase_number)
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM payable_purchase_details WHERE purchase_number = ?");
            $stmt->execute(array($grNumber));
            if ((int)$stmt->fetchColumn() > 0) {
                $reasons[] = '此進貨單已被應付帳款明細引用 (purchase_number=' . $grNumber . ')';
            }
        } catch (Exception $e) {}

        // 2) 是否被進項發票引用 (purchase_invoices.reference_type='goods_receipt')
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM purchase_invoices WHERE reference_type = 'goods_receipt' AND reference_id = ?");
            $stmt->execute(array($id));
            if ((int)$stmt->fetchColumn() > 0) {
                $reasons[] = '此進貨單已被進項發票引用';
            }
        } catch (Exception $e) {}

        // 3) 是否被入庫單引用 (stock_ins.source_type='goods_receipt')
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM stock_ins WHERE source_type = 'goods_receipt' AND source_id = ?");
            $stmt->execute(array($id));
            if ((int)$stmt->fetchColumn() > 0) {
                $reasons[] = '此進貨單已轉成入庫單，請先處理入庫單';
            }
        } catch (Exception $e) {}

        // 4) 是否有 inventory_transactions 引用
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM inventory_transactions WHERE reference_type = 'goods_receipt' AND reference_id = ?");
            $stmt->execute(array($id));
            if ((int)$stmt->fetchColumn() > 0) {
                $reasons[] = '此進貨單已產生庫存異動紀錄（請改用盤點單調整）';
            }
        } catch (Exception $e) {}

        // 5) 是否被退貨單引用 (returns.gr_id)
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM returns WHERE gr_id = ?");
            $stmt->execute(array($id));
            if ((int)$stmt->fetchColumn() > 0) {
                $reasons[] = '此進貨單已被退貨單引用';
            }
        } catch (Exception $e) {}

        // 6) 對應自動分錄
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM journal_entries WHERE source_module = 'goods_receipt' AND source_id = ?");
            $stmt->execute(array($id));
            if ((int)$stmt->fetchColumn() > 0) {
                $reasons[] = '此進貨單已產生會計分錄，請先處理分錄';
            }
        } catch (Exception $e) {}

        return $reasons;
    }

    /**
     * ADMIN: 硬刪進貨單
     */
    public function deleteHard($id)
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM goods_receipt_items WHERE goods_receipt_id = ?")->execute(array($id));
            $this->db->prepare("DELETE FROM goods_receipts WHERE id = ?")->execute(array($id));
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * ADMIN: 修改進貨單廠商
     * @param int $id
     * @param array $data 必須含 vendor_name + vendor_id (autocomplete 帶入)
     */
    public function updateBasic($id, $data)
    {
        if (empty($data['vendor_name']) || empty($data['vendor_id'])) {
            throw new Exception('廠商必須從廠商管理選擇，不可空白');
        }
        $this->db->prepare("UPDATE goods_receipts SET vendor_name = ?, vendor_id = ?, updated_at = NOW() WHERE id = ?")
                 ->execute(array($data['vendor_name'], (int)$data['vendor_id'], $id));
        return true;
    }

    // ADMIN_TOOL_BLOCK_END

    // ============================================================
    // 從採購單建立
    // ============================================================

    /**
     * 從採購單預填進貨單資料
     * @param int $poId
     * @return array  header + items
     */
    public function createFromPO($poId)
    {
        require_once __DIR__ . '/ProcurementModel.php';
        $procModel = new ProcurementModel();
        $po = $procModel->getPurchaseOrder($poId);
        if (!$po) return null;

        $poItems = $procModel->getPurchaseOrderItems($poId);

        // Determine warehouse from branch
        $warehouseId = null;
        if (!empty($po['branch_id'])) {
            require_once __DIR__ . '/../inventory/InventoryModel.php';
            $invModel = new InventoryModel();
            $whs = $invModel->getWarehouses();
            foreach ($whs as $wh) {
                if ($wh['branch_id'] == $po['branch_id']) {
                    $warehouseId = $wh['id'];
                    break;
                }
            }
        }

        $header = array(
            'po_id'         => $po['id'],
            'po_number'     => $po['po_number'],
            'vendor_id'     => $po['vendor_id'],
            'vendor_name'   => $po['vendor_name'],
            'warehouse_id'  => $warehouseId,
            'branch_id'     => !empty($po['branch_id']) ? $po['branch_id'] : null,
            'branch_name'   => !empty($po['branch_name']) ? $po['branch_name'] : null,
            'paid_amount'   => !empty($po['paid_amount']) ? $po['paid_amount'] : 0,
            'payment_date'  => !empty($po['payment_date']) ? $po['payment_date'] : null,
            'purchaser_name'=> !empty($po['purchaser_name']) ? $po['purchaser_name'] : null,
            'case_name'     => !empty($po['case_name']) ? $po['case_name'] : null,
            'requisition_number' => !empty($po['requisition_number']) ? $po['requisition_number'] : null,
        );

        $items = array();
        foreach ($poItems as $item) {
            $items[] = array(
                'product_id'   => !empty($item['product_id']) ? $item['product_id'] : null,
                'model'        => !empty($item['model']) ? $item['model'] : '',
                'product_name' => !empty($item['product_name']) ? $item['product_name'] : '',
                'spec'         => !empty($item['spec']) ? $item['spec'] : '',
                'unit'         => !empty($item['unit']) ? $item['unit'] : '',
                'po_qty'       => !empty($item['quantity']) ? $item['quantity'] : 0,
                'received_qty' => !empty($item['quantity']) ? $item['quantity'] : 0,
                'unit_price'   => !empty($item['unit_price']) ? $item['unit_price'] : 0,
                'amount'       => !empty($item['amount']) ? $item['amount'] : 0,
            );
        }

        return array('header' => $header, 'items' => $items);
    }

    // ============================================================
    // 輔助
    // ============================================================

    /**
     * 取得未完成的採購單（for 下拉選擇）
     */
    public function getPendingPOs()
    {
        $stmt = $this->db->query("
            SELECT id, po_number, po_date, vendor_name, total_amount
            FROM purchase_orders
            WHERE status IN ('尚未進貨', '確認進貨') AND is_cancelled = 0
            ORDER BY id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
