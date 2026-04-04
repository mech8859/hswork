<?php
/**
 * 員工/廠商交易管理 Model
 */
class TransactionModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得依人員彙總的清單
     */
    public function getGroupedList(array $filters = array())
    {
        $where = array();
        $params = array();

        if (!empty($filters['target_type'])) {
            $where[] = 't.target_type = ?';
            $params[] = $filters['target_type'];
        }

        if (!empty($filters['contact_name'])) {
            $where[] = 't.contact_name LIKE ?';
            $params[] = '%' . $filters['contact_name'] . '%';
        }

        if (!empty($filters['settled'])) {
            if ($filters['settled'] === 'unsettled') {
                $where[] = 'total_unpaid_sum > 0';
            } elseif ($filters['settled'] === 'settled') {
                $where[] = 'total_unpaid_sum = 0';
            }
        }

        // Build inner WHERE (exclude HAVING-like filters)
        $innerWhere = array();
        $innerParams = array();
        if (!empty($filters['target_type'])) {
            $innerWhere[] = 't.target_type = ?';
            $innerParams[] = $filters['target_type'];
        }
        if (!empty($filters['contact_name'])) {
            $innerWhere[] = 't.contact_name LIKE ?';
            $innerParams[] = '%' . $filters['contact_name'] . '%';
        }
        $innerWhereStr = $innerWhere ? 'WHERE ' . implode(' AND ', $innerWhere) : '';

        $havingParts = array();
        if (!empty($filters['settled'])) {
            if ($filters['settled'] === 'unsettled') {
                $havingParts[] = 'total_unpaid_sum > 0';
            } elseif ($filters['settled'] === 'settled') {
                $havingParts[] = 'total_unpaid_sum = 0';
            }
        }
        $havingStr = $havingParts ? 'HAVING ' . implode(' AND ', $havingParts) : '';

        $stmt = $this->db->prepare("
            SELECT t.contact_name,
                   MAX(t.target_type) AS target_type,
                   COUNT(DISTINCT t.id) AS tx_count,
                   (SELECT COUNT(*) FROM transaction_items ti JOIN transactions t2 ON ti.transaction_id = t2.id WHERE t2.contact_name = t.contact_name) AS item_count,
                   (SELECT COUNT(*) FROM transaction_items ti JOIN transactions t2 ON ti.transaction_id = t2.id WHERE t2.contact_name = t.contact_name AND ti.is_settled = 0) AS unsettled_count,
                   SUM(t.total_unpaid) AS total_unpaid_sum,
                   MAX(t.register_date) AS last_date
            FROM transactions t
            $innerWhereStr
            GROUP BY t.contact_name
            $havingStr
            ORDER BY total_unpaid_sum DESC, last_date DESC
        ");
        $stmt->execute($innerParams);
        return $stmt->fetchAll();
    }

    /**
     * 取得某人的所有交易
     */
    public function getByContact($contactName)
    {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   (SELECT COUNT(*) FROM transaction_items ti WHERE ti.transaction_id = t.id) AS item_count,
                   (SELECT COUNT(*) FROM transaction_items ti WHERE ti.transaction_id = t.id AND ti.is_settled = 0) AS unsettled_count
            FROM transactions t
            WHERE t.contact_name = ?
            ORDER BY t.register_date DESC, t.id DESC
        ");
        $stmt->execute(array($contactName));
        return $stmt->fetchAll();
    }

    /**
     * 取得單筆含明細
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT t.*, u.real_name AS created_by_name
            FROM transactions t
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.id = ?
        ");
        $stmt->execute(array($id));
        $record = $stmt->fetch();
        if (!$record) return null;

        $stmt2 = $this->db->prepare("
            SELECT * FROM transaction_items
            WHERE transaction_id = ?
            ORDER BY trade_date DESC, id DESC
        ");
        $stmt2->execute(array($id));
        $record['items'] = $stmt2->fetchAll();

        return $record;
    }

    /**
     * 產生登記編號
     */
    public function generateRegisterNo($date)
    {
        $dateStr = str_replace('-', '', $date);
        $prefix = 'H1-' . $dateStr;

        $stmt = $this->db->prepare("
            SELECT register_no FROM transactions
            WHERE register_no LIKE ?
            ORDER BY register_no DESC LIMIT 1
        ");
        $stmt->execute(array($prefix . '%'));
        $last = $stmt->fetchColumn();

        if ($last) {
            $seq = (int)substr($last, -3) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 新增交易
     */
    public function create(array $data)
    {
        $registerNo = $this->generateRegisterNo($data['register_date']);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO transactions (register_no, register_date, target_type, category, contact_name, total_unpaid, created_by)
                VALUES (?, ?, ?, ?, ?, 0, ?)
            ");
            $stmt->execute(array(
                $registerNo,
                $data['register_date'],
                $data['target_type'],
                $data['category'],
                $data['contact_name'],
                isset($data['created_by']) ? $data['created_by'] : null
            ));
            $txId = $this->db->lastInsertId();

            // 新增明細
            if (!empty($data['items'])) {
                $this->saveItems($txId, $data['items']);
            }

            // 重算未收金額
            $this->recalcUnpaid($txId);

            $this->db->commit();
            return $txId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 更新交易
     */
    public function update($id, array $data)
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE transactions
                SET register_date = ?, target_type = ?, category = ?, contact_name = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute(array(
                $data['register_date'],
                $data['target_type'],
                $data['category'],
                $data['contact_name'],
                $id
            ));

            // 刪除舊明細，重新寫入
            $this->db->prepare("DELETE FROM transaction_items WHERE transaction_id = ?")->execute(array($id));

            if (!empty($data['items'])) {
                $this->saveItems($id, $data['items']);
            }

            $this->recalcUnpaid($id);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 刪除交易
     */
    public function delete($id)
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM transaction_items WHERE transaction_id = ?")->execute(array($id));
            $this->db->prepare("DELETE FROM transactions WHERE id = ?")->execute(array($id));
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 結清某筆明細
     */
    public function settleItem($itemId)
    {
        $stmt = $this->db->prepare("UPDATE transaction_items SET is_settled = 1 WHERE id = ?");
        $stmt->execute(array($itemId));

        // 取得 transaction_id 重算
        $stmt2 = $this->db->prepare("SELECT transaction_id FROM transaction_items WHERE id = ?");
        $stmt2->execute(array($itemId));
        $txId = $stmt2->fetchColumn();
        if ($txId) {
            $this->recalcUnpaid($txId);
        }
    }

    /**
     * 取消結清
     */
    public function unsettleItem($itemId)
    {
        $stmt = $this->db->prepare("UPDATE transaction_items SET is_settled = 0 WHERE id = ?");
        $stmt->execute(array($itemId));

        $stmt2 = $this->db->prepare("SELECT transaction_id FROM transaction_items WHERE id = ?");
        $stmt2->execute(array($itemId));
        $txId = $stmt2->fetchColumn();
        if ($txId) {
            $this->recalcUnpaid($txId);
        }
    }

    /**
     * 儲存明細
     */
    private function saveItems($txId, array $items)
    {
        $stmt = $this->db->prepare("
            INSERT INTO transaction_items (transaction_id, trade_date, description, product, amount, due_date, is_settled, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($items as $item) {
            if (empty($item['description']) && empty($item['product']) && empty($item['amount'])) continue;
            $stmt->execute(array(
                $txId,
                !empty($item['trade_date']) ? $item['trade_date'] : null,
                isset($item['description']) ? $item['description'] : '',
                isset($item['product']) ? $item['product'] : '',
                isset($item['amount']) ? (float)$item['amount'] : 0,
                isset($item['due_date']) ? $item['due_date'] : '',
                !empty($item['is_settled']) ? 1 : 0,
                isset($item['note']) ? $item['note'] : ''
            ));
        }
    }

    /**
     * 重算未收金額
     */
    private function recalcUnpaid($txId)
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM transaction_items
            WHERE transaction_id = ? AND is_settled = 0
        ");
        $stmt->execute(array($txId));
        $unpaid = $stmt->fetchColumn();

        $this->db->prepare("UPDATE transactions SET total_unpaid = ? WHERE id = ?")->execute(array($unpaid, $txId));
    }

    /**
     * 交易對象標籤
     */
    public static function targetTypeLabel($type)
    {
        $map = array('employee' => '員工', 'partner' => '合作夥伴');
        return isset($map[$type]) ? $map[$type] : $type;
    }

    /**
     * 交易分類標籤
     */
    public static function categoryLabel($cat)
    {
        $map = array('purchase' => '購買商品', 'loan' => '員工借支');
        return isset($map[$cat]) ? $map[$cat] : $cat;
    }
}
