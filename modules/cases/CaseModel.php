<?php
/**
 * 案件資料模型
 */
class CaseModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得案件清單 (含分頁/篩選)
     */
    public function getList(array $branchIds, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = 'c.branch_id IN (' . implode(',', array_fill(0, count($branchIds), '?')) . ')';
        $params = $branchIds;

        if (!empty($filters['status'])) {
            $where .= ' AND c.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['case_type'])) {
            $where .= ' AND c.case_type = ?';
            $params[] = $filters['case_type'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (c.title LIKE ? OR c.case_number LIKE ? OR c.address LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['branch_id'])) {
            $where .= ' AND c.branch_id = ?';
            $params[] = $filters['branch_id'];
        }

        // 計算總數
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM cases c WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT c.*, b.name AS branch_name,
                   u.real_name AS sales_name,
                   cr.has_quotation, cr.has_site_photos, cr.has_amount_confirmed, cr.has_site_info
            FROM cases c
            JOIN branches b ON c.branch_id = b.id
            LEFT JOIN users u ON c.sales_id = u.id
            LEFT JOIN case_readiness cr ON cr.case_id = c.id
            WHERE $where
            ORDER BY c.updated_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);

        return [
            'data'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * 取得單一案件完整資料
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT c.*, b.name AS branch_name, b.code AS branch_code,
                   u.real_name AS sales_name
            FROM cases c
            JOIN branches b ON c.branch_id = b.id
            LEFT JOIN users u ON c.sales_id = u.id
            WHERE c.id = ?
        ');
        $stmt->execute([$id]);
        $case = $stmt->fetch();
        if (!$case) return null;

        // 排工條件
        $stmt = $this->db->prepare('SELECT * FROM case_readiness WHERE case_id = ?');
        $stmt->execute([$id]);
        $case['readiness'] = $stmt->fetch() ?: [];

        // 聯絡人
        $stmt = $this->db->prepare('SELECT * FROM case_contacts WHERE case_id = ? ORDER BY id');
        $stmt->execute([$id]);
        $case['contacts'] = $stmt->fetchAll();

        // 現場環境
        $stmt = $this->db->prepare('SELECT * FROM case_site_conditions WHERE case_id = ?');
        $stmt->execute([$id]);
        $case['site_conditions'] = $stmt->fetch() ?: [];

        // 所需技能
        $stmt = $this->db->prepare('
            SELECT crs.*, s.name AS skill_name, s.category
            FROM case_required_skills crs
            JOIN skills s ON crs.skill_id = s.id
            WHERE crs.case_id = ?
        ');
        $stmt->execute([$id]);
        $case['required_skills'] = $stmt->fetchAll();

        // 附件
        $stmt = $this->db->prepare('
            SELECT ca.*, u.real_name AS uploader_name
            FROM case_attachments ca
            LEFT JOIN users u ON ca.uploaded_by = u.id
            WHERE ca.case_id = ?
            ORDER BY ca.created_at DESC
        ');
        $stmt->execute([$id]);
        $case['attachments'] = $stmt->fetchAll();

        // 收款
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE case_id = ? ORDER BY created_at');
        $stmt->execute([$id]);
        $case['payments'] = $stmt->fetchAll();

        return $case;
    }

    /**
     * 新增案件
     */
    public function create(array $data): int
    {
        $stmtBranch = $this->db->prepare('SELECT code FROM branches WHERE id = ?');
        $stmtBranch->execute([$data['branch_id']]);
        $branchCode = $stmtBranch->fetchColumn();

        $caseNumber = generate_case_number($branchCode);

        $stmt = $this->db->prepare('
            INSERT INTO cases (branch_id, case_number, title, case_type, status, difficulty,
                             estimated_hours, total_visits, max_engineers, address, description,
                             ragic_id, sales_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['branch_id'],
            $caseNumber,
            $data['title'],
            $data['case_type'] ?? 'new_install',
            $data['status'] ?? 'pending',
            $data['difficulty'] ?? 3,
            $data['estimated_hours'] ?: null,
            $data['total_visits'] ?? 1,
            $data['max_engineers'] ?? 4,
            $data['address'] ?? null,
            $data['description'] ?? null,
            $data['ragic_id'] ?: null,
            $data['sales_id'] ?: null,
            Auth::id(),
        ]);

        $caseId = (int)$this->db->lastInsertId();

        // 建立排工條件
        $this->db->prepare('INSERT INTO case_readiness (case_id) VALUES (?)')->execute([$caseId]);

        // 建立現場環境
        $this->db->prepare('INSERT INTO case_site_conditions (case_id) VALUES (?)')->execute([$caseId]);

        return $caseId;
    }

    /**
     * 更新案件
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('
            UPDATE cases SET
                title = ?, case_type = ?, status = ?, difficulty = ?,
                estimated_hours = ?, total_visits = ?, max_engineers = ?,
                address = ?, description = ?, ragic_id = ?, sales_id = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $data['title'],
            $data['case_type'] ?? 'new_install',
            $data['status'] ?? 'pending',
            $data['difficulty'] ?? 3,
            $data['estimated_hours'] ?: null,
            $data['total_visits'] ?? 1,
            $data['max_engineers'] ?? 4,
            $data['address'] ?? null,
            $data['description'] ?? null,
            $data['ragic_id'] ?: null,
            $data['sales_id'] ?: null,
            $id,
        ]);
    }

    /**
     * 更新排工條件
     */
    public function updateReadiness(int $caseId, array $data): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO case_readiness (case_id, has_quotation, has_site_photos, has_amount_confirmed, has_site_info, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                has_quotation = VALUES(has_quotation),
                has_site_photos = VALUES(has_site_photos),
                has_amount_confirmed = VALUES(has_amount_confirmed),
                has_site_info = VALUES(has_site_info),
                notes = VALUES(notes)
        ');
        $stmt->execute([
            $caseId,
            $data['has_quotation'] ?? 0,
            $data['has_site_photos'] ?? 0,
            $data['has_amount_confirmed'] ?? 0,
            $data['has_site_info'] ?? 0,
            $data['readiness_notes'] ?? null,
        ]);
    }

    /**
     * 更新現場環境
     */
    public function updateSiteConditions(int $caseId, array $data): void
    {
        $structureType = !empty($data['structure_type']) ? (is_array($data['structure_type']) ? implode(',', $data['structure_type']) : $data['structure_type']) : null;
        $conduitType = !empty($data['conduit_type']) ? (is_array($data['conduit_type']) ? implode(',', $data['conduit_type']) : $data['conduit_type']) : null;

        $stmt = $this->db->prepare('
            INSERT INTO case_site_conditions (case_id, structure_type, conduit_type, floor_count, has_elevator, has_ladder_needed, special_requirements)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                structure_type = VALUES(structure_type),
                conduit_type = VALUES(conduit_type),
                floor_count = VALUES(floor_count),
                has_elevator = VALUES(has_elevator),
                has_ladder_needed = VALUES(has_ladder_needed),
                special_requirements = VALUES(special_requirements)
        ');
        $stmt->execute([
            $caseId,
            $structureType,
            $conduitType,
            $data['floor_count'] ?: null,
            $data['has_elevator'] ?? 0,
            $data['has_ladder_needed'] ?? 0,
            $data['special_requirements'] ?? null,
        ]);
    }

    /**
     * 儲存案件聯絡人 (先清除再新增)
     */
    public function saveContacts(int $caseId, array $contacts): void
    {
        $this->db->prepare('DELETE FROM case_contacts WHERE case_id = ?')->execute([$caseId]);
        $stmt = $this->db->prepare('
            INSERT INTO case_contacts (case_id, contact_name, contact_phone, contact_role, note)
            VALUES (?, ?, ?, ?, ?)
        ');
        foreach ($contacts as $c) {
            if (empty($c['contact_name'])) continue;
            $stmt->execute([
                $caseId,
                $c['contact_name'],
                $c['contact_phone'] ?? null,
                $c['contact_role'] ?? null,
                $c['contact_note'] ?? null,
            ]);
        }
    }

    /**
     * 儲存案件所需技能
     */
    public function saveRequiredSkills(int $caseId, array $skills): void
    {
        $this->db->prepare('DELETE FROM case_required_skills WHERE case_id = ?')->execute([$caseId]);
        $stmt = $this->db->prepare('
            INSERT INTO case_required_skills (case_id, skill_id, min_proficiency)
            VALUES (?, ?, ?)
        ');
        foreach ($skills as $skillId => $proficiency) {
            if ($proficiency < 1) continue;
            $stmt->execute([$caseId, $skillId, $proficiency]);
        }
    }

    /**
     * 取得所有技能清單
     */
    public function getAllSkills(): array
    {
        return $this->db->query('SELECT * FROM skills WHERE is_active = 1 ORDER BY category, name')->fetchAll();
    }

    /**
     * 取得所有據點
     */
    public function getAllBranches(): array
    {
        return $this->db->query('SELECT * FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll();
    }

    /**
     * 取得業務人員清單
     */
    public function getSalesUsers(array $branchIds): array
    {
        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT id, real_name, branch_id FROM users
            WHERE branch_id IN ($placeholders)
              AND role IN ('sales','sales_manager','boss')
              AND is_active = 1
            ORDER BY real_name
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    /**
     * 狀態中文
     */
    public static function statusLabel(string $status): string
    {
        $map = [
            'pending'     => '待處理',
            'ready'       => '可排工',
            'scheduled'   => '已排工',
            'in_progress' => '施工中',
            'completed'   => '已完工',
            'cancelled'   => '已取消',
        ];
        return $map[$status] ?? $status;
    }

    /**
     * 狀態 badge class
     */
    public static function statusBadge(string $status): string
    {
        $map = [
            'pending'     => 'warning',
            'ready'       => 'info',
            'scheduled'   => 'primary',
            'in_progress' => 'warning',
            'completed'   => 'success',
            'cancelled'   => 'danger',
        ];
        return 'badge-' . ($map[$status] ?? 'primary');
    }

    /**
     * 案件類型中文
     */
    public static function typeLabel(string $type): string
    {
        $map = [
            'new_install'  => '新裝',
            'maintenance'  => '保養',
            'repair'       => '維修',
            'inspection'   => '勘查',
            'other'        => '其他',
        ];
        return $map[$type] ?? $type;
    }
}
