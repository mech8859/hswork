<?php
/**
 * 五星評價統計 Model
 */
class ReviewModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得列表（含篩選、分頁）
     */
    public function getList(array $filters = array(), $page = 1, $perPage = 50)
    {
        $where = array('1=1');
        $params = array();

        if (!empty($filters['branch_id'])) {
            $where[] = 'r.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'r.review_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'r.review_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $where[] = '(r.customer_name LIKE ? OR r.google_reviewer_name LIKE ? OR r.review_number LIKE ? OR r.reason LIKE ?)';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereSql = implode(' AND ', $where);

        // Count
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM five_star_reviews r WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Data
        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT r.*, b.name AS branch_name
                FROM five_star_reviews r
                LEFT JOIN branches b ON r.branch_id = b.id
                WHERE {$whereSql}
                ORDER BY r.review_date DESC, r.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array(
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int)ceil($total / $perPage),
        );
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM five_star_reviews WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $reviewNumber = generate_doc_number('five_star_reviews', !empty($data['review_date']) ? $data['review_date'] : null);

        $stmt = $this->db->prepare("INSERT INTO five_star_reviews
            (review_number, review_date, reason, photo_path,
             group_photo_engineer_ids, customer_name, original_customer_name,
             google_reviewer_name, engineer_ids, original_engineer_names,
             branch_id, bonus_payment_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(array(
            $reviewNumber,
            !empty($data['review_date']) ? $data['review_date'] : null,
            isset($data['reason']) ? $data['reason'] : null,
            isset($data['photo_path']) ? $data['photo_path'] : null,
            isset($data['group_photo_engineer_ids']) ? $data['group_photo_engineer_ids'] : null,
            isset($data['customer_name']) ? $data['customer_name'] : null,
            isset($data['original_customer_name']) ? $data['original_customer_name'] : null,
            isset($data['google_reviewer_name']) ? $data['google_reviewer_name'] : null,
            isset($data['engineer_ids']) ? $data['engineer_ids'] : null,
            isset($data['original_engineer_names']) ? $data['original_engineer_names'] : null,
            !empty($data['branch_id']) ? (int)$data['branch_id'] : null,
            !empty($data['bonus_payment_date']) ? $data['bonus_payment_date'] : null,
            Auth::id(),
        ));
        return (int)$this->db->lastInsertId();
    }

    public function update($id, array $data)
    {
        $stmt = $this->db->prepare("UPDATE five_star_reviews SET
            review_date = ?, reason = ?, photo_path = ?,
            group_photo_engineer_ids = ?, customer_name = ?, original_customer_name = ?,
            google_reviewer_name = ?, engineer_ids = ?, original_engineer_names = ?,
            branch_id = ?, bonus_payment_date = ?
            WHERE id = ?");
        $stmt->execute(array(
            !empty($data['review_date']) ? $data['review_date'] : null,
            isset($data['reason']) ? $data['reason'] : null,
            isset($data['photo_path']) ? $data['photo_path'] : null,
            isset($data['group_photo_engineer_ids']) ? $data['group_photo_engineer_ids'] : null,
            isset($data['customer_name']) ? $data['customer_name'] : null,
            isset($data['original_customer_name']) ? $data['original_customer_name'] : null,
            isset($data['google_reviewer_name']) ? $data['google_reviewer_name'] : null,
            isset($data['engineer_ids']) ? $data['engineer_ids'] : null,
            isset($data['original_engineer_names']) ? $data['original_engineer_names'] : null,
            !empty($data['branch_id']) ? (int)$data['branch_id'] : null,
            !empty($data['bonus_payment_date']) ? $data['bonus_payment_date'] : null,
            $id,
        ));
    }

    public function delete($id)
    {
        $this->db->prepare("DELETE FROM five_star_reviews WHERE id = ?")->execute(array($id));
    }

    /**
     * 取得可選工程人員清單（依分公司過濾）
     * 用於：清單頁的 篩選下拉
     * 條件：is_engineer = 1 或 role 為工程類 (engineer, eng_manager, eng_deputy)
     */
    public function getEngineerOptions(array $branchIds)
    {
        if (empty($branchIds)) return array();
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT u.id, u.real_name, u.branch_id, b.name AS branch_name
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE u.branch_id IN ($ph)
              AND u.is_active = 1
              AND (u.is_engineer = 1 OR u.role IN ('engineer','eng_manager','eng_deputy'))
            ORDER BY u.branch_id, u.real_name
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得「所有」可選工程人員清單（不依分公司過濾）
     * 用於：新增/編輯五星評價的表單，因為跨點施工常見，
     *      製表人即使看不到其他分公司，也要能勾到支援的師傅
     * 條件：is_engineer = 1 或 role 為工程類
     */
    public function getAllActiveEngineerOptions()
    {
        $stmt = $this->db->query("
            SELECT u.id, u.real_name, u.branch_id, b.name AS branch_name
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE u.is_active = 1
              AND (u.is_engineer = 1 OR u.role IN ('engineer','eng_manager','eng_deputy'))
            ORDER BY u.branch_id, u.real_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得分公司選單
     */
    public function getBranchOptions(array $branchIds)
    {
        if (empty($branchIds)) return array();
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("SELECT id, name FROM branches WHERE is_active = 1 AND id IN ($ph) ORDER BY id");
        $stmt->execute($branchIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllBranches()
    {
        $stmt = $this->db->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 將 JSON id 陣列展開為人員名稱陣列
     */
    public function decodeEngineerNames($jsonIds, array $nameMap)
    {
        if (empty($jsonIds)) return array();
        $ids = json_decode($jsonIds, true);
        if (!is_array($ids)) return array();
        $names = array();
        foreach ($ids as $uid) {
            $uid = (int)$uid;
            if (isset($nameMap[$uid])) $names[] = $nameMap[$uid];
        }
        return $names;
    }
}
