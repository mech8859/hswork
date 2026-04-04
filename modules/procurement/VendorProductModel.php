<?php
/**
 * 廠商產品對照表 Model
 */
class VendorProductModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 列表查詢
     */
    public function getList($filters = array())
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['vendor_id'])) {
            $where .= ' AND vp.vendor_id = ?';
            $params[] = $filters['vendor_id'];
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $where .= ' AND (vp.vendor_model LIKE ? OR vp.vendor_name LIKE ? OR p.name LIKE ? OR p.model LIKE ?)';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (isset($filters['mapped']) && $filters['mapped'] !== '') {
            if ($filters['mapped'] === '1') {
                $where .= ' AND vp.product_id IS NOT NULL';
            } else {
                $where .= ' AND vp.product_id IS NULL';
            }
        }

        $stmt = $this->db->prepare("
            SELECT vp.*,
                   v.name AS vendor_name_display, v.short_name AS vendor_short_name,
                   p.name AS product_name, p.model AS product_model, p.brand AS product_brand
            FROM vendor_products vp
            LEFT JOIN vendors v ON vp.vendor_id = v.id
            LEFT JOIN products p ON vp.product_id = p.id
            WHERE {$where}
            ORDER BY v.name, vp.vendor_model
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得單筆
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT vp.*,
                   v.name AS vendor_name_display,
                   p.name AS product_name, p.model AS product_model
            FROM vendor_products vp
            LEFT JOIN vendors v ON vp.vendor_id = v.id
            LEFT JOIN products p ON vp.product_id = p.id
            WHERE vp.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 用廠商型號查對應產品（精確比對）
     */
    public function getByVendorModel($vendorId, $vendorModel)
    {
        $stmt = $this->db->prepare("
            SELECT vp.*, p.name AS product_name, p.model AS product_model, p.id AS matched_product_id
            FROM vendor_products vp
            LEFT JOIN products p ON vp.product_id = p.id
            WHERE vp.vendor_id = ? AND vp.vendor_model = ? AND vp.is_active = 1
            LIMIT 1
        ");
        $stmt->execute(array($vendorId, $vendorModel));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * AJAX 搜尋（給進貨單用）
     */
    public function searchForReceipt($vendorId, $keyword, $limit = 20)
    {
        $kw = '%' . $keyword . '%';
        $stmt = $this->db->prepare("
            SELECT vp.id, vp.vendor_model, vp.vendor_name, vp.last_purchase_price,
                   vp.product_id,
                   p.name AS product_name, p.model AS product_model
            FROM vendor_products vp
            LEFT JOIN products p ON vp.product_id = p.id
            WHERE vp.vendor_id = ? AND vp.is_active = 1
              AND (vp.vendor_model LIKE ? OR vp.vendor_name LIKE ?)
            ORDER BY vp.vendor_model
            LIMIT ?
        ");
        $stmt->execute(array($vendorId, $kw, $kw, $limit));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 新增對照
     */
    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO vendor_products (vendor_id, product_id, vendor_model, vendor_name, vendor_price, last_purchase_price, last_purchase_date, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(array(
            $data['vendor_id'],
            !empty($data['product_id']) ? $data['product_id'] : null,
            $data['vendor_model'],
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
            !empty($data['vendor_price']) ? $data['vendor_price'] : null,
            !empty($data['last_purchase_price']) ? $data['last_purchase_price'] : null,
            !empty($data['last_purchase_date']) ? $data['last_purchase_date'] : null,
            !empty($data['note']) ? $data['note'] : null,
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新對照
     */
    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE vendor_products SET
                product_id = ?, vendor_model = ?, vendor_name = ?,
                vendor_price = ?, last_purchase_price = ?, last_purchase_date = ?, note = ?
            WHERE id = ?
        ");
        $stmt->execute(array(
            !empty($data['product_id']) ? $data['product_id'] : null,
            $data['vendor_model'],
            !empty($data['vendor_name']) ? $data['vendor_name'] : null,
            !empty($data['vendor_price']) ? $data['vendor_price'] : null,
            !empty($data['last_purchase_price']) ? $data['last_purchase_price'] : null,
            !empty($data['last_purchase_date']) ? $data['last_purchase_date'] : null,
            !empty($data['note']) ? $data['note'] : null,
            $id
        ));
    }

    /**
     * 刪除
     */
    public function delete($id)
    {
        $this->db->prepare("DELETE FROM vendor_products WHERE id = ?")->execute(array($id));
    }

    /**
     * 進貨單確認時同步對照表
     */
    public function syncFromGoodsReceipt($goodsReceiptId)
    {
        // 取進貨單 header
        $gr = $this->db->prepare("SELECT vendor_id, gr_date FROM goods_receipts WHERE id = ?");
        $gr->execute(array($goodsReceiptId));
        $grRow = $gr->fetch(PDO::FETCH_ASSOC);
        if (!$grRow || !$grRow['vendor_id']) return 0;

        $vendorId = $grRow['vendor_id'];
        $grDate = $grRow['gr_date'];

        // 取品項
        $items = $this->db->prepare("
            SELECT model, product_name, product_id, unit_price
            FROM goods_receipt_items
            WHERE goods_receipt_id = ? AND model IS NOT NULL AND model != ''
        ");
        $items->execute(array($goodsReceiptId));
        $rows = $items->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($rows as $row) {
            // UPSERT: 存在就更新最近進價，不存在就新增
            $existing = $this->db->prepare("SELECT id FROM vendor_products WHERE vendor_id = ? AND vendor_model = ?");
            $existing->execute(array($vendorId, $row['model']));
            $ex = $existing->fetch(PDO::FETCH_ASSOC);

            if ($ex) {
                // 更新最近進價和日期
                $upd = $this->db->prepare("
                    UPDATE vendor_products SET last_purchase_price = ?, last_purchase_date = ?,
                           vendor_name = COALESCE(vendor_name, ?),
                           product_id = COALESCE(product_id, ?)
                    WHERE id = ?
                ");
                $upd->execute(array(
                    $row['unit_price'],
                    $grDate,
                    $row['product_name'],
                    $row['product_id'] ?: null,
                    $ex['id']
                ));
            } else {
                // 新增
                $ins = $this->db->prepare("
                    INSERT INTO vendor_products (vendor_id, product_id, vendor_model, vendor_name, last_purchase_price, last_purchase_date)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ins->execute(array(
                    $vendorId,
                    $row['product_id'] ?: null,
                    $row['model'],
                    $row['product_name'],
                    $row['unit_price'],
                    $grDate
                ));
            }
            $count++;
        }
        return $count;
    }

    /**
     * 取得廠商列表（有對照資料的）
     */
    public function getVendorsWithMappings()
    {
        $stmt = $this->db->query("
            SELECT v.id, v.name, v.short_name, COUNT(vp.id) AS mapping_count,
                   SUM(CASE WHEN vp.product_id IS NOT NULL THEN 1 ELSE 0 END) AS mapped_count
            FROM vendors v
            JOIN vendor_products vp ON v.id = vp.vendor_id AND vp.is_active = 1
            WHERE v.is_active = 1
            GROUP BY v.id
            ORDER BY v.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 統計
     */
    public function getStats()
    {
        $row = $this->db->query("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN product_id IS NOT NULL THEN 1 ELSE 0 END) AS mapped,
                   SUM(CASE WHEN product_id IS NULL THEN 1 ELSE 0 END) AS unmapped
            FROM vendor_products WHERE is_active = 1
        ")->fetch(PDO::FETCH_ASSOC);
        return $row;
    }
}
