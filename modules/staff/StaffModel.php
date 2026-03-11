<?php
/**
 * 人員資料模型
 */
class StaffModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得人員清單
     */
    public function getList(array $branchIds, array $filters = []): array
    {
        $where = 'u.branch_id IN (' . implode(',', array_fill(0, count($branchIds), '?')) . ')';
        $params = $branchIds;

        if (!empty($filters['branch_id'])) {
            $where .= ' AND u.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        if (!empty($filters['role'])) {
            $where .= ' AND u.role = ?';
            $params[] = $filters['role'];
        }
        if (isset($filters['is_engineer']) && $filters['is_engineer'] !== '') {
            $where .= ' AND u.is_engineer = ?';
            $params[] = $filters['is_engineer'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (u.real_name LIKE ? OR u.username LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $stmt = $this->db->prepare("
            SELECT u.*, b.name AS branch_name
            FROM users u
            JOIN branches b ON u.branch_id = b.id
            WHERE $where
            ORDER BY u.branch_id, u.role, u.real_name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 取得單一人員
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT u.*, b.name AS branch_name
            FROM users u
            JOIN branches b ON u.branch_id = b.id
            WHERE u.id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 新增人員
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO users (branch_id, username, password_hash, real_name, role, phone, email, is_engineer, is_mobile, can_view_all_branches)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['branch_id'],
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['real_name'],
            $data['role'],
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['is_engineer'] ?? 0,
            $data['is_mobile'] ?? 1,
            $data['can_view_all_branches'] ?? 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新人員
     */
    public function update(int $id, array $data): void
    {
        $fields = 'branch_id = ?, real_name = ?, role = ?, phone = ?, email = ?, is_engineer = ?, is_mobile = ?, can_view_all_branches = ?';
        $params = [
            $data['branch_id'],
            $data['real_name'],
            $data['role'],
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['is_engineer'] ?? 0,
            $data['is_mobile'] ?? 1,
            $data['can_view_all_branches'] ?? 0,
        ];

        if (!empty($data['password'])) {
            $fields .= ', password_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $params[] = $id;
        $stmt = $this->db->prepare("UPDATE users SET $fields WHERE id = ?");
        $stmt->execute($params);
    }

    /**
     * 停用/啟用人員
     */
    public function toggleActive(int $id): void
    {
        $this->db->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
    }

    /**
     * 取得人員技能
     */
    public function getUserSkills(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT us.*, s.name AS skill_name, s.category
            FROM user_skills us
            JOIN skills s ON us.skill_id = s.id
            WHERE us.user_id = ?
            ORDER BY s.category, s.name
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * 儲存人員技能
     */
    public function saveUserSkills(int $userId, array $skills): void
    {
        $this->db->prepare('DELETE FROM user_skills WHERE user_id = ?')->execute([$userId]);
        $stmt = $this->db->prepare('INSERT INTO user_skills (user_id, skill_id, proficiency) VALUES (?, ?, ?)');
        foreach ($skills as $skillId => $proficiency) {
            $proficiency = (int)$proficiency;
            if ($proficiency < 1 || $proficiency > 5) continue;
            $stmt->execute([$userId, $skillId, $proficiency]);
        }
    }

    /**
     * 取得人員證照
     */
    public function getUserCertifications(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT uc.*, c.name AS cert_name, c.has_expiry,
                   CASE WHEN uc.expiry_date IS NOT NULL AND uc.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END AS is_expiring
            FROM user_certifications uc
            JOIN certifications c ON uc.certification_id = c.id
            WHERE uc.user_id = ?
            ORDER BY c.name
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * 新增人員證照
     */
    public function addCertification(int $userId, array $data): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO user_certifications (user_id, certification_id, cert_number, issue_date, expiry_date)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $data['certification_id'],
            $data['cert_number'] ?? null,
            $data['issue_date'] ?: null,
            $data['expiry_date'] ?: null,
        ]);
    }

    /**
     * 刪除人員證照
     */
    public function removeCertification(int $certRecordId): void
    {
        $this->db->prepare('DELETE FROM user_certifications WHERE id = ?')->execute([$certRecordId]);
    }

    /**
     * 取得工程師配對表
     */
    public function getPairs(array $branchIds): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT ep.*, ua.real_name AS user_a_name, ub.real_name AS user_b_name
            FROM engineer_pairs ep
            JOIN users ua ON ep.user_a_id = ua.id
            JOIN users ub ON ep.user_b_id = ub.id
            WHERE ua.branch_id IN ($ph)
            ORDER BY ep.compatibility DESC
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    /**
     * 取得所有工程師 (用於配對表)
     */
    public function getEngineers(array $branchIds): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT u.id, u.real_name, b.name AS branch_name
            FROM users u
            JOIN branches b ON u.branch_id = b.id
            WHERE u.branch_id IN ($ph) AND u.is_engineer = 1 AND u.is_active = 1
            ORDER BY u.branch_id, u.real_name
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    /**
     * 儲存/更新配對
     */
    public function savePair(int $userAId, int $userBId, int $compatibility, ?string $note = null): void
    {
        // 確保 a < b
        if ($userAId > $userBId) { [$userAId, $userBId] = [$userBId, $userAId]; }

        $stmt = $this->db->prepare('
            INSERT INTO engineer_pairs (user_a_id, user_b_id, compatibility, note)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE compatibility = VALUES(compatibility), note = VALUES(note)
        ');
        $stmt->execute([$userAId, $userBId, $compatibility, $note]);
    }

    /**
     * 取得所有技能
     */
    public function getAllSkills(): array
    {
        return $this->db->query('SELECT * FROM skills WHERE is_active = 1 ORDER BY category, name')->fetchAll();
    }

    /**
     * 取得所有證照類型
     */
    public function getAllCertifications(): array
    {
        return $this->db->query('SELECT * FROM certifications WHERE is_active = 1 ORDER BY name')->fetchAll();
    }

    /**
     * 取得所有據點
     */
    public function getAllBranches(): array
    {
        return $this->db->query('SELECT * FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll();
    }
}
