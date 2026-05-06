<?php
/**
 * VendorInvoiceModel — 廠商請款單收件匣
 */
class VendorInvoiceModel
{
    private $db;
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function statusOptions(): array
    {
        return array(
            'pending'    => '待辨識',
            'recognized' => '待確認',
            'confirmed'  => '已確認',
        );
    }

    public static function statusBadgeClass($status): string
    {
        $map = array(
            'pending'    => 'warning',
            'recognized' => 'primary',
            'confirmed'  => 'success',
        );
        return isset($map[$status]) ? $map[$status] : 'secondary';
    }

    /**
     * 列表
     */
    public function getList(string $status = '', int $page = 1, int $perPage = 50): array
    {
        $where = '1=1';
        $params = array();
        if ($status !== '') {
            $where .= ' AND vi.status = ?';
            $params[] = $status;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM vendor_invoices vi WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT vi.*, v.name AS vendor_name, u1.real_name AS uploader, u2.real_name AS confirmer
            FROM vendor_invoices vi
            LEFT JOIN vendors v ON vi.vendor_id = v.id
            LEFT JOIN users u1 ON vi.uploaded_by = u1.id
            LEFT JOIN users u2 ON vi.confirmed_by = u2.id
            WHERE $where
            ORDER BY vi.uploaded_at DESC, vi.id DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);

        return array(
            'data'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page'  => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        );
    }

    /**
     * 各 status 數量（給分頁顯示用）
     */
    public function getStatusCounts(): array
    {
        $rows = $this->db->query("SELECT status, COUNT(*) AS cnt FROM vendor_invoices GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
        $counts = array('pending' => 0, 'recognized' => 0, 'confirmed' => 0);
        foreach ($rows as $r) $counts[$r['status']] = (int)$r['cnt'];
        return $counts;
    }

    public function getById(int $id)
    {
        $stmt = $this->db->prepare("
            SELECT vi.*, v.name AS vendor_name
            FROM vendor_invoices vi
            LEFT JOIN vendors v ON vi.vendor_id = v.id
            WHERE vi.id = ?
        ");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getItems(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT vii.*, p.name AS product_name, p.model AS product_model, p.unit AS product_unit
            FROM vendor_invoice_items vii
            LEFT JOIN products p ON vii.matched_product_id = p.id
            WHERE vii.vendor_invoice_id = ?
            ORDER BY vii.line_no, vii.id
        ");
        $stmt->execute(array($invoiceId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 上傳完建立 pending 紀錄
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vendor_invoices
            (status, file_path, file_name, file_size, file_pages, uploaded_by, note)
            VALUES ('pending', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(array(
            $data['file_path'],
            $data['file_name'],
            isset($data['file_size']) ? (int)$data['file_size'] : null,
            isset($data['file_pages']) ? (int)$data['file_pages'] : null,
            isset($data['uploaded_by']) ? (int)$data['uploaded_by'] : null,
            isset($data['note']) ? $data['note'] : null,
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 寫入 AI 辨識結果（status: pending → recognized）
     * $aiData 為 ai-service 回傳的完整 JSON（含 vendor / items / total 等）
     */
    public function saveRecognized(int $id, $aiData): void
    {
        $vendorId = !empty($aiData['vendor']['matched_id']) ? (int)$aiData['vendor']['matched_id'] : null;
        $invoiceDate = !empty($aiData['date']) ? $aiData['date'] : null;
        $invoiceNumber = !empty($aiData['invoice_number']) ? $aiData['invoice_number'] : null;
        $totalAmount = isset($aiData['total']) ? (float)$aiData['total'] : null;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE vendor_invoices
                SET status = 'recognized',
                    recognized_at = NOW(),
                    recognized_data = ?,
                    vendor_id = ?,
                    invoice_date = ?,
                    invoice_number = ?,
                    total_amount = ?
                WHERE id = ?
            ");
            $stmt->execute(array(
                json_encode($aiData, JSON_UNESCAPED_UNICODE),
                $vendorId,
                $invoiceDate,
                $invoiceNumber,
                $totalAmount,
                $id,
            ));

            // 重建明細（每次辨識重來）
            $this->db->prepare("DELETE FROM vendor_invoice_items WHERE vendor_invoice_id = ?")->execute(array($id));

            $items = !empty($aiData['items']) ? $aiData['items'] : array();
            $insStmt = $this->db->prepare("
                INSERT INTO vendor_invoice_items
                (vendor_invoice_id, line_no, ai_model, ai_name, ai_qty, ai_unit, ai_unit_price, ai_amount, matched_product_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $line = 1;
            foreach ($items as $it) {
                $insStmt->execute(array(
                    $id,
                    $line++,
                    isset($it['model']) ? (string)$it['model'] : null,
                    isset($it['product_name']) ? (string)$it['product_name'] : null,
                    isset($it['quantity']) ? (float)$it['quantity'] : null,
                    isset($it['unit']) ? (string)$it['unit'] : null,
                    isset($it['unit_price']) ? (float)$it['unit_price'] : null,
                    isset($it['amount']) ? (float)$it['amount'] : null,
                    !empty($it['matched_product_id']) ? (int)$it['matched_product_id'] : null,
                ));
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 確認請款單（status: recognized → confirmed）
     * - 寫入 final_* 欄位（人工確認後的值）
     * - 更新 vendor_products.last_purchase_price/date
     * - 寫入 product_price_history
     */
    public function confirm(int $id, array $headerEdit, array $itemEdits, int $userId): void
    {
        $this->db->beginTransaction();
        try {
            $vendorId = !empty($headerEdit['vendor_id']) ? (int)$headerEdit['vendor_id'] : null;
            $invoiceDate = !empty($headerEdit['invoice_date']) ? $headerEdit['invoice_date'] : null;
            $invoiceNumber = !empty($headerEdit['invoice_number']) ? $headerEdit['invoice_number'] : null;
            $totalAmount = isset($headerEdit['total_amount']) && $headerEdit['total_amount'] !== '' ? (float)$headerEdit['total_amount'] : null;
            $note = !empty($headerEdit['note']) ? $headerEdit['note'] : null;

            // 更新主表
            $this->db->prepare("
                UPDATE vendor_invoices
                SET status = 'confirmed',
                    confirmed_at = NOW(),
                    confirmed_by = ?,
                    vendor_id = ?,
                    invoice_date = ?,
                    invoice_number = ?,
                    total_amount = ?,
                    note = ?
                WHERE id = ?
            ")->execute(array($userId, $vendorId, $invoiceDate, $invoiceNumber, $totalAmount, $note, $id));

            // 更新明細的 final_*
            $upd = $this->db->prepare("
                UPDATE vendor_invoice_items SET
                    matched_product_id = ?,
                    final_model = ?,
                    final_name = ?,
                    final_qty = ?,
                    final_unit = ?,
                    final_unit_price = ?,
                    final_amount = ?
                WHERE id = ? AND vendor_invoice_id = ?
            ");
            foreach ($itemEdits as $it) {
                $itemId = isset($it['id']) ? (int)$it['id'] : 0;
                if ($itemId <= 0) continue;
                $upd->execute(array(
                    !empty($it['matched_product_id']) ? (int)$it['matched_product_id'] : null,
                    isset($it['final_model']) ? (string)$it['final_model'] : null,
                    isset($it['final_name']) ? (string)$it['final_name'] : null,
                    isset($it['final_qty']) && $it['final_qty'] !== '' ? (float)$it['final_qty'] : null,
                    isset($it['final_unit']) ? (string)$it['final_unit'] : null,
                    isset($it['final_unit_price']) && $it['final_unit_price'] !== '' ? (float)$it['final_unit_price'] : null,
                    isset($it['final_amount']) && $it['final_amount'] !== '' ? (float)$it['final_amount'] : null,
                    $itemId,
                    $id,
                ));
            }

            // 寫 vendor_products + product_price_history
            $this->_propagatePrices($id, $vendorId, $userId);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 把確認後的明細價格寫入 vendor_products + product_price_history
     * 規則：
     *   - 必須有 matched_product_id 且有 final_unit_price
     *   - 寫 product_price_history（每張請款單一筆）
     *   - 更新 vendor_products.last_purchase_price/date（若該 vendor+product 對照存在）
     *   - products.cost 完全不動
     */
    private function _propagatePrices(int $invoiceId, $vendorId, int $userId): void
    {
        if (!$vendorId) return; // 沒廠商就不寫

        $items = $this->db->prepare("
            SELECT id, matched_product_id, final_unit_price, final_model, final_name
            FROM vendor_invoice_items
            WHERE vendor_invoice_id = ?
              AND matched_product_id IS NOT NULL
              AND final_unit_price IS NOT NULL
              AND final_unit_price > 0
        ");
        $items->execute(array($invoiceId));
        $rows = $items->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) return;

        // 取請款日期當作 price_date 的依據
        $hdr = $this->db->prepare("SELECT invoice_date FROM vendor_invoices WHERE id = ?");
        $hdr->execute(array($invoiceId));
        $invDate = $hdr->fetchColumn();
        if (empty($invDate)) $invDate = date('Y-m-d');

        // 確認 vendor_products / product_price_history 表存在
        $hasVP = $this->_tableExists('vendor_products');
        $hasPPH = $this->_tableExists('product_price_history');

        $vpUpdate = null;
        $vpSelect = null;
        if ($hasVP) {
            $vpSelect = $this->db->prepare("SELECT id, last_purchase_price FROM vendor_products WHERE vendor_id = ? AND product_id = ? LIMIT 1");
            $vpUpdate = $this->db->prepare("UPDATE vendor_products SET last_purchase_price = ?, last_purchase_date = ? WHERE id = ?");
        }

        $pphInsert = null;
        if ($hasPPH) {
            $pphInsert = $this->db->prepare("
                INSERT INTO product_price_history
                (product_id, vendor_id, old_price, new_price, change_pct, source_type, source_id, note, updated_by)
                VALUES (?, ?, ?, ?, ?, 'vendor_invoice', ?, ?, ?)
            ");
        }

        foreach ($rows as $r) {
            $productId = (int)$r['matched_product_id'];
            $newPrice = (float)$r['final_unit_price'];
            $oldPrice = null;
            $changePct = null;

            // 查 vendor_products 的舊價
            if ($vpSelect) {
                $vpSelect->execute(array($vendorId, $productId));
                $vp = $vpSelect->fetch(PDO::FETCH_ASSOC);
                if ($vp) {
                    $oldPrice = isset($vp['last_purchase_price']) ? (float)$vp['last_purchase_price'] : null;
                    if ($oldPrice && $oldPrice > 0) {
                        $changePct = round(($newPrice - $oldPrice) / $oldPrice * 100, 2);
                    }
                    $vpUpdate->execute(array($newPrice, $invDate, (int)$vp['id']));
                }
            }

            // 寫入價格變動史
            if ($pphInsert) {
                $note = '請款單 #' . $invoiceId;
                $pphInsert->execute(array(
                    $productId,
                    $vendorId,
                    $oldPrice,
                    $newPrice,
                    $changePct,
                    $invoiceId,
                    $note,
                    $userId,
                ));
            }
        }
    }

    private function _tableExists(string $table): bool
    {
        try {
            $stmt = $this->db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute(array($table));
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 退回為 pending（重新辨識用）
     */
    public function resetToPending(int $id): void
    {
        $this->db->prepare("UPDATE vendor_invoices SET status = 'pending', recognized_at = NULL, recognized_data = NULL WHERE id = ?")
                 ->execute(array($id));
    }

    /**
     * 刪除（連同明細與檔案）
     */
    public function delete(int $id): void
    {
        $rec = $this->getById($id);
        if (!$rec) return;
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM vendor_invoice_items WHERE vendor_invoice_id = ?")->execute(array($id));
            $this->db->prepare("DELETE FROM vendor_invoices WHERE id = ?")->execute(array($id));
            // 刪檔
            if (!empty($rec['file_path'])) {
                $abs = __DIR__ . '/../../public/' . $rec['file_path'];
                if (is_file($abs)) @unlink($abs);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
