<?php
/**
 * 人員資料模型
 */
class StaffModel
{
    /** @var PDO */
    private $db;

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
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where .= ' AND u.is_active = ?';
            $params[] = (int)$filters['is_active'];
        }
        if (isset($filters['emp_status']) && $filters['emp_status'] !== '') {
            if ($filters['emp_status'] === 'working') {
                $where .= " AND (u.employment_status IN ('active','probation','suspended','') OR u.employment_status IS NULL)";
            } elseif ($filters['emp_status'] === 'resigned') {
                $where .= " AND u.employment_status = 'resigned'";
            } else {
                $where .= ' AND u.employment_status = ?';
                $params[] = $filters['emp_status'];
            }
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
            ORDER BY CASE WHEN u.employee_id IS NOT NULL AND u.employee_id != '' THEN 0 ELSE 1 END, u.employee_id DESC
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
            INSERT INTO users (branch_id, username, password_hash, plain_password, real_name, role, phone, email, is_engineer, is_sales, is_mobile, can_view_all_branches, holiday_availability, night_availability,
                employee_id, department, id_number, birth_date, gender, marital_status, blood_type, education_level, job_title, address, registered_address, bank_name, bank_account, hire_date, resignation_date, employment_status, labor_insurance_company, labor_insurance_date, dependent_insurance, annual_leave_days,
                engineer_level, can_lead, repair_priority, mentor_id, mentor_start_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            $data['branch_id'],
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['password'],
            $data['real_name'],
            $data['role'],
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['is_engineer'] ?? 0,
            $data['is_sales'] ?? 0,
            $data['is_mobile'] ?? 1,
            $data['can_view_all_branches'] ?? 0,
            $data['holiday_availability'] ?? 'medium',
            $data['night_availability'] ?? 'medium',
            $data['employee_id'] ?? null,
            $data['department'] ?? null,
            $data['id_number'] ?? null,
            $data['birth_date'] ?: null,
            $data['gender'] ?: null,
            $data['marital_status'] ?: null,
            $data['blood_type'] ?: null,
            $data['education_level'] ?: null,
            $data['job_title'] ?? null,
            $data['address'] ?? null,
            $data['registered_address'] ?? null,
            $data['bank_name'] ?? null,
            $data['bank_account'] ?? null,
            $data['hire_date'] ?: null,
            $data['resignation_date'] ?: null,
            $data['employment_status'] ?? 'active',
            $data['labor_insurance_company'] ?? null,
            $data['labor_insurance_date'] ?: null,
            $data['dependent_insurance'] ?? null,
            (int)($data['annual_leave_days'] ?? 0),
            $data['engineer_level'] ?: null,
            (int)($data['can_lead'] ?? 0),
            (int)($data['repair_priority'] ?? 0),
            $data['mentor_id'] ?: null,
            $data['mentor_start_date'] ?: null,
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新人員
     */
    public function update(int $id, array $data): void
    {
        $fields = 'branch_id = ?, real_name = ?, role = ?, phone = ?, email = ?, is_engineer = ?, is_sales = ?, is_mobile = ?, can_view_all_branches = ?, holiday_availability = ?, night_availability = ?, caution_notes = ?,
            employee_id = ?, department = ?, id_number = ?, birth_date = ?, gender = ?, marital_status = ?, blood_type = ?, education_level = ?, job_title = ?, address = ?, registered_address = ?, bank_name = ?, bank_account = ?, hire_date = ?, resignation_date = ?, employment_status = ?, labor_insurance_company = ?, labor_insurance_date = ?, dependent_insurance = ?, annual_leave_days = ?,
            engineer_level = ?, can_lead = ?, repair_priority = ?, mentor_id = ?, mentor_start_date = ?';
        $params = array(
            $data['branch_id'],
            $data['real_name'],
            $data['role'],
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['is_engineer'] ?? 0,
            $data['is_sales'] ?? 0,
            $data['is_mobile'] ?? 1,
            $data['can_view_all_branches'] ?? 0,
            $data['holiday_availability'] ?? 'medium',
            $data['night_availability'] ?? 'medium',
            $data['caution_notes'] ?? null,
            $data['employee_id'] ?? null,
            $data['department'] ?? null,
            $data['id_number'] ?? null,
            $data['birth_date'] ?: null,
            $data['gender'] ?: null,
            $data['marital_status'] ?: null,
            $data['blood_type'] ?: null,
            $data['education_level'] ?: null,
            $data['job_title'] ?? null,
            $data['address'] ?? null,
            $data['registered_address'] ?? null,
            $data['bank_name'] ?? null,
            $data['bank_account'] ?? null,
            $data['hire_date'] ?: null,
            $data['resignation_date'] ?: null,
            $data['employment_status'] ?? 'active',
            $data['labor_insurance_company'] ?? null,
            $data['labor_insurance_date'] ?: null,
            $data['dependent_insurance'] ?? null,
            (int)($data['annual_leave_days'] ?? 0),
            $data['engineer_level'] ?: null,
            (int)($data['can_lead'] ?? 0),
            (int)($data['repair_priority'] ?? 0),
            $data['mentor_id'] ?: null,
            $data['mentor_start_date'] ?: null,
        );

        // 帳號修改（僅 boss 可操作）
        if (!empty($data['username'])) {
            $fields .= ', username = ?';
            $params[] = $data['username'];
        }

        if (!empty($data['password'])) {
            $fields .= ', password_hash = ?, plain_password = ?';
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            $params[] = $data['password'];
        }

        // 可查看分公司
        if (isset($data['viewable_branch_ids'])) {
            $fields .= ', viewable_branches = ?';
            $branchIds = array_map('intval', $data['viewable_branch_ids']);
            $params[] = json_encode(array_unique($branchIds));
        }

        // 個人權限設定
        if (array_key_exists('custom_permissions', $data)) {
            $fields .= ', custom_permissions = ?';
            $params[] = $data['custom_permissions'];
        }

        $params[] = $id;
        $stmt = $this->db->prepare("UPDATE users SET $fields WHERE id = ?");
        $stmt->execute($params);
    }

    /**
     * 取得可作為師傅的工程師清單
     */
    public function getMentorCandidates($excludeUserId = 0)
    {
        $stmt = $this->db->prepare("
            SELECT id, real_name, engineer_level
            FROM users
            WHERE is_engineer = 1 AND is_active = 1
              AND employment_status IN ('active','probation')
              AND employee_id IS NOT NULL AND employee_id != ''
              AND (engineer_level IN ('leader','senior','regular') OR engineer_level IS NULL)
              AND id != ?
            ORDER BY real_name
        ");
        $stmt->execute(array($excludeUserId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得師徒制即將到期（超過90天）的人員清單
     */
    public function getMentorshipExpiringList(array $branchIds)
    {
        if (empty($branchIds)) return array();
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT u.id, u.real_name, u.mentor_start_date, u.branch_id,
                   m.real_name AS mentor_name,
                   DATEDIFF(CURDATE(), u.mentor_start_date) AS days_elapsed
            FROM users u
            JOIN users m ON u.mentor_id = m.id
            WHERE u.branch_id IN ($ph)
              AND u.is_active = 1
              AND u.mentor_id IS NOT NULL
              AND u.mentor_start_date IS NOT NULL
              AND DATEDIFF(CURDATE(), u.mentor_start_date) >= 90
            ORDER BY u.mentor_start_date ASC
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 重設密碼
     */
    public function resetPassword(int $id, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->prepare('UPDATE users SET password_hash = ?, plain_password = ?, failed_login_count = 0, locked_until = NULL WHERE id = ?')
                 ->execute([$hash, $newPassword, $id]);
    }

    /**
     * 解除帳號鎖定
     */
    public function unlockAccount(int $id): void
    {
        $this->db->prepare('UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE id = ?')
                 ->execute([$id]);
        // 清除登入失敗記錄
        $user = $this->getById($id);
        if ($user) {
            $this->db->prepare('DELETE FROM login_attempts WHERE username = ?')
                     ->execute([$user['username']]);
        }
    }

    /**
     * 檢查帳號是否被鎖定
     */
    public function isLocked(array $user): bool
    {
        return !empty($user['locked_until']) && strtotime($user['locked_until']) > time();
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
            SELECT us.*, s.name AS skill_name, s.category, s.skill_group
            FROM user_skills us
            JOIN skills s ON us.skill_id = s.id
            WHERE us.user_id = ?
            ORDER BY s.skill_group, s.category, s.sort_order, s.name
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
              AND u.employment_status IN ('active','probation')
              AND u.employee_id IS NOT NULL AND u.employee_id != ''
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
        return $this->db->query('SELECT * FROM skills WHERE is_active = 1 ORDER BY skill_group, category, sort_order, name')->fetchAll();
    }

    /**
     * 取得所有技能分組（含分類）
     */
    public function getSkillGroups(): array
    {
        $skills = $this->getAllSkills();
        $groups = array();
        foreach ($skills as $sk) {
            $g = $sk['skill_group'] ?: '其他';
            $c = $sk['category'] ?: '未分類';
            if (!isset($groups[$g])) $groups[$g] = array();
            if (!isset($groups[$g][$c])) $groups[$g][$c] = array();
            $groups[$g][$c][] = $sk;
        }
        return $groups;
    }

    /**
     * 新增技能
     */
    public function createSkill(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO skills (name, category, skill_group, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->execute(array(
            $data['name'],
            $data['category'],
            $data['skill_group'],
            (int)($data['sort_order'] ?: 0)
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新技能
     */
    public function updateSkill(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE skills SET name = ?, category = ?, skill_group = ?, sort_order = ? WHERE id = ?');
        $stmt->execute(array(
            $data['name'],
            $data['category'],
            $data['skill_group'],
            (int)($data['sort_order'] ?: 0),
            $id
        ));
    }

    /**
     * 刪除技能（軟刪除）
     */
    public function deleteSkill(int $id): void
    {
        $this->db->prepare('UPDATE skills SET is_active = 0 WHERE id = ?')->execute(array($id));
    }

    /**
     * 取得單一技能
     */
    public function getSkillById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM skills WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    /**
     * 取得不重複的 skill_group 列表
     */
    public function getDistinctSkillGroups(): array
    {
        return $this->db->query('SELECT DISTINCT skill_group FROM skills WHERE is_active = 1 ORDER BY skill_group')->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * 取得不重複的 category 列表
     */
    public function getDistinctCategories(): array
    {
        return $this->db->query('SELECT DISTINCT category FROM skills WHERE is_active = 1 ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * 刪除配對
     */
    public function deletePair(int $pairId): void
    {
        $this->db->prepare('DELETE FROM engineer_pairs WHERE id = ?')->execute(array($pairId));
    }

    /**
     * 取得指定人員的配對
     */
    public function getUserPairs(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT ep.*,
                   CASE WHEN ep.user_a_id = ? THEN ub.real_name ELSE ua.real_name END AS partner_name,
                   CASE WHEN ep.user_a_id = ? THEN ub.id ELSE ua.id END AS partner_id
            FROM engineer_pairs ep
            JOIN users ua ON ep.user_a_id = ua.id
            JOIN users ub ON ep.user_b_id = ub.id
            WHERE ep.user_a_id = ? OR ep.user_b_id = ?
            ORDER BY ep.compatibility DESC
        ");
        $stmt->execute(array($userId, $userId, $userId, $userId));
        return $stmt->fetchAll();
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

    /**
     * 取得所有據點（含停用）
     */
    public function getAllBranchesIncludeInactive(): array
    {
        return $this->db->query('SELECT * FROM branches ORDER BY id')->fetchAll();
    }

    /**
     * 新增分公司
     */
    public function createBranch(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO branches (name, code, address, phone) VALUES (?, ?, ?, ?)');
        $stmt->execute(array(
            $data['name'],
            $data['code'] ?: null,
            $data['address'] ?: null,
            $data['phone'] ?: null,
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新分公司
     */
    public function updateBranch($id, array $data)
    {
        $stmt = $this->db->prepare('UPDATE branches SET name = ?, code = ?, address = ?, phone = ? WHERE id = ?');
        $stmt->execute(array(
            $data['name'],
            $data['code'] ?: null,
            $data['address'] ?: null,
            $data['phone'] ?: null,
            $id,
        ));
    }

    /**
     * 取得單一分公司
     */
    public function getBranchById($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM branches WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch() ?: null;
    }

    // ---- 廠商上課證 ----

    /**
     * 取得人員的廠商上課證
     */
    public function getVendorTrainings($userId)
    {
        $stmt = $this->db->prepare('
            SELECT vt.*,
                   CASE WHEN vt.expiry_date IS NOT NULL AND vt.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND vt.expiry_date >= CURDATE() THEN 1 ELSE 0 END AS is_expiring,
                   CASE WHEN vt.expiry_date IS NOT NULL AND vt.expiry_date < CURDATE() THEN 1 ELSE 0 END AS is_expired
            FROM vendor_trainings vt
            WHERE vt.user_id = ?
            ORDER BY vt.expiry_date DESC, vt.training_date DESC
        ');
        $stmt->execute(array($userId));
        return $stmt->fetchAll();
    }

    /**
     * 新增廠商上課證
     */
    public function addVendorTraining($userId, array $data)
    {
        $stmt = $this->db->prepare('
            INSERT INTO vendor_trainings (user_id, vendor_name, training_date, expiry_date, note)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            (int)$userId,
            $data['vendor_name'],
            $data['training_date'] ?: null,
            $data['expiry_date'] ?: null,
            $data['note'] ?: null,
        ));
    }

    /**
     * 刪除廠商上課證
     */
    public function removeVendorTraining($id)
    {
        $this->db->prepare('DELETE FROM vendor_trainings WHERE id = ?')->execute(array($id));
    }

    // ---- 證照文件上傳 ----

    /**
     * 取得人員的所有文件（LEFT JOIN doc_types 取得排序）
     */
    public function getDocuments($userId)
    {
        $stmt = $this->db->prepare('
            SELECT sd.*, COALESCE(sdt.sort_order, 999) AS type_sort
            FROM staff_documents sd
            LEFT JOIN staff_doc_types sdt ON sd.doc_type = sdt.type_key
            WHERE sd.user_id = ?
            ORDER BY type_sort, sd.sort_order, sd.uploaded_at DESC
        ');
        $stmt->execute(array((int)$userId));
        return $stmt->fetchAll();
    }

    /**
     * 取得所有啟用的文件類型
     */
    public function getDocTypes()
    {
        return $this->db->query('SELECT * FROM staff_doc_types WHERE is_active = 1 ORDER BY sort_order, type_label')->fetchAll();
    }

    /**
     * 上傳文件
     */
    public function uploadDocument($userId, $docType, $docLabel, $filePath, $fileName)
    {
        $stmt = $this->db->prepare('
            INSERT INTO staff_documents (user_id, doc_type, doc_label, file_path, file_name)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            (int)$userId,
            $docType,
            $docLabel,
            $filePath,
            $fileName
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 取得單一文件
     */
    public function getDocumentById($docId)
    {
        $stmt = $this->db->prepare('SELECT * FROM staff_documents WHERE id = ?');
        $stmt->execute(array((int)$docId));
        return $stmt->fetch() ?: null;
    }

    /**
     * 刪除文件記錄
     */
    public function deleteDocument($docId)
    {
        $doc = $this->getDocumentById($docId);
        if ($doc) {
            $this->db->prepare('DELETE FROM staff_documents WHERE id = ?')->execute(array((int)$docId));
        }
        return $doc;
    }

    // ---- 緊急聯絡人 ----

    /**
     * 取得人員的緊急聯絡人
     */
    public function getEmergencyContacts($userId)
    {
        $stmt = $this->db->prepare('SELECT * FROM staff_emergency_contacts WHERE user_id = ? ORDER BY id');
        $stmt->execute(array((int)$userId));
        return $stmt->fetchAll();
    }

    /**
     * 新增緊急聯絡人
     */
    public function addEmergencyContact($userId, array $data)
    {
        $stmt = $this->db->prepare('
            INSERT INTO staff_emergency_contacts (user_id, contact_name, relationship, home_phone, work_phone, mobile)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            (int)$userId,
            $data['contact_name'],
            $data['relationship'] ?? null,
            $data['home_phone'] ?? null,
            $data['work_phone'] ?? null,
            $data['mobile'] ?? null,
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新緊急聯絡人
     */
    public function updateEmergencyContact($id, array $data)
    {
        $stmt = $this->db->prepare('
            UPDATE staff_emergency_contacts SET contact_name = ?, relationship = ?, home_phone = ?, work_phone = ?, mobile = ? WHERE id = ?
        ');
        $stmt->execute(array(
            $data['contact_name'],
            $data['relationship'] ?? null,
            $data['home_phone'] ?? null,
            $data['work_phone'] ?? null,
            $data['mobile'] ?? null,
            (int)$id,
        ));
    }

    /**
     * 刪除緊急聯絡人
     */
    public function removeEmergencyContact($id)
    {
        $this->db->prepare('DELETE FROM staff_emergency_contacts WHERE id = ?')->execute(array((int)$id));
    }

    /**
     * 新增自訂文件類型
     */
    public function addDocType($key, $label)
    {
        // 取得最大 sort_order
        $max = $this->db->query('SELECT COALESCE(MAX(sort_order), 0) FROM staff_doc_types')->fetchColumn();
        $stmt = $this->db->prepare('INSERT INTO staff_doc_types (type_key, type_label, sort_order) VALUES (?, ?, ?)');
        $stmt->execute(array($key, $label, (int)$max + 1));
        return (int)$this->db->lastInsertId();
    }
}
