<?php
/**
 * 未繳回帳務 Model
 */
class RemittanceModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 各分公司未繳回彙總
     */
    public function getBranchSummary()
    {
        $stmt = $this->db->query("
            SELECT b.id AS branch_id,
                   b.name AS branch_name,
                   COUNT(cp.id) AS unremitted_count,
                   COALESCE(SUM(cp.amount), 0) AS unremitted_amount,
                   MIN(cp.payment_date) AS earliest_date
            FROM branches b
            LEFT JOIN cases c ON c.branch_id = b.id
            LEFT JOIN case_payments cp ON cp.case_id = c.id AND cp.is_remitted = 0
            WHERE b.is_active = 1
            GROUP BY b.id, b.name
            ORDER BY unremitted_amount DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * 某分公司的未繳回明細
     */
    public function getUnremittedByBranch($branchId)
    {
        $stmt = $this->db->prepare("
            SELECT cp.*,
                   c.title AS case_title,
                   c.case_number,
                   c.customer_name
            FROM case_payments cp
            JOIN cases c ON cp.case_id = c.id
            WHERE c.branch_id = ? AND cp.is_remitted = 0
            ORDER BY cp.payment_date ASC, cp.id ASC
        ");
        $stmt->execute(array($branchId));
        return $stmt->fetchAll();
    }

    /**
     * 某分公司的已繳回紀錄
     */
    public function getRemittedByBranch($branchId, $limit = 50)
    {
        $stmt = $this->db->prepare("
            SELECT cp.*,
                   c.title AS case_title,
                   c.case_number,
                   c.customer_name
            FROM case_payments cp
            JOIN cases c ON cp.case_id = c.id
            WHERE c.branch_id = ? AND cp.is_remitted = 1
            ORDER BY cp.remit_date DESC, cp.payment_date DESC
            LIMIT " . (int)$limit . "
        ");
        $stmt->execute(array($branchId));
        return $stmt->fetchAll();
    }

    /**
     * 批次標記已繳回
     */
    public function batchRemit(array $paymentIds, $remitDate, $note = '')
    {
        if (empty($paymentIds)) return 0;

        $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
        $params = array_merge(array($remitDate, $note), $paymentIds);

        $stmt = $this->db->prepare("
            UPDATE case_payments
            SET is_remitted = 1, remit_date = ?, remit_note = ?
            WHERE id IN ($placeholders) AND is_remitted = 0
        ");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 取消繳回（單筆）
     */
    public function cancelRemit($paymentId)
    {
        $stmt = $this->db->prepare("
            UPDATE case_payments
            SET is_remitted = 0, remit_date = NULL, remit_note = ''
            WHERE id = ?
        ");
        $stmt->execute(array($paymentId));
        return $stmt->rowCount();
    }

    /**
     * 取得分公司名稱
     */
    public function getBranchName($branchId)
    {
        $stmt = $this->db->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute(array($branchId));
        return $stmt->fetchColumn();
    }
}
