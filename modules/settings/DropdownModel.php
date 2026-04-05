<?php
/**
 * 下拉選單選項管理 Model
 */
class DropdownModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得某分類的所有啟用選項
     */
    public function getOptions($category)
    {
        $stmt = $this->db->prepare(
            'SELECT id, label FROM dropdown_options WHERE category = ? AND is_active = 1 ORDER BY sort_order, label'
        );
        $stmt->execute(array($category));
        return $stmt->fetchAll();
    }

    /**
     * 取得某分類的所有選項（含停用，管理用）
     */
    public function getAllOptions($category)
    {
        if ($category === 'payment_main_category') {
            // 只取主分類（parent_id IS NULL）
            $stmt = $this->db->prepare(
                'SELECT * FROM dropdown_options WHERE category = ? AND parent_id IS NULL ORDER BY sort_order, id'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM dropdown_options WHERE category = ? ORDER BY sort_order, label'
            );
        }
        $stmt->execute(array($category));
        return $stmt->fetchAll();
    }

    /**
     * 取得某主分類的子分類
     */
    public function getSubOptions($parentId)
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM dropdown_options WHERE parent_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute(array($parentId));
        return $stmt->fetchAll();
    }

    /**
     * 新增子分類
     */
    public function addSubOption($category, $parentId, $label)
    {
        $maxSort = $this->db->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM dropdown_options WHERE parent_id = ?");
        $maxSort->execute(array($parentId));
        $nextSort = $maxSort->fetchColumn();

        $stmt = $this->db->prepare(
            'INSERT INTO dropdown_options (category, parent_id, label, sort_order, is_active) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute(array($category, $parentId, $label, $nextSort));
        return $this->db->lastInsertId();
    }

    /**
     * 是否為層級分類
     */
    public function isHierarchical($category)
    {
        return $category === 'payment_main_category';
    }

    /**
     * 取得所有分類
     */
    public function getCategories()
    {
        return array(
            'customer_demand'       => '客戶需求',
            'system_type'           => '系統別',
            'deposit_method'        => '訂金支付方式',
            'case_type'             => '案別',
            'case_progress'         => '案件進度',
            'case_status'           => '狀態',
            'case_company'          => '進件公司',
            'case_source'           => '案件來源',
            'payment_main_category' => '付款單分類',
        );
    }

    /**
     * 新增選項
     */
    public function addOption($category, $label)
    {
        $maxSort = $this->db->prepare('SELECT MAX(sort_order) FROM dropdown_options WHERE category = ?');
        $maxSort->execute(array($category));
        $nextSort = (int)$maxSort->fetchColumn() + 1;

        $stmt = $this->db->prepare(
            'INSERT INTO dropdown_options (category, label, sort_order) VALUES (?, ?, ?)'
        );
        $stmt->execute(array($category, $label, $nextSort));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新選項
     */
    public function updateOption($id, $label)
    {
        $stmt = $this->db->prepare('UPDATE dropdown_options SET label = ? WHERE id = ?');
        $stmt->execute(array($label, $id));
    }

    /**
     * 停用選項（不刪除，保留歷史資料完整性）
     */
    public function deactivateOption($id)
    {
        $stmt = $this->db->prepare('UPDATE dropdown_options SET is_active = 0 WHERE id = ?');
        $stmt->execute(array($id));
    }

    /**
     * 啟用選項
     */
    public function activateOption($id)
    {
        $stmt = $this->db->prepare('UPDATE dropdown_options SET is_active = 1 WHERE id = ?');
        $stmt->execute(array($id));
    }

    /**
     * 更新排序
     */
    public function updateSortOrder($id, $sortOrder)
    {
        $stmt = $this->db->prepare('UPDATE dropdown_options SET sort_order = ? WHERE id = ?');
        $stmt->execute(array($sortOrder, $id));
    }

    /**
     * 取得單一選項
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM dropdown_options WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch();
    }

    // ============================================================
    // 案件相關 key-value 選項 (case_type, case_progress, case_status)
    // ============================================================

    /**
     * 取得指定分類的啟用選項 (key => label)
     * 適用於有 option_key 的分類
     */
    public function getOptionsByCategory($category)
    {
        $stmt = $this->db->prepare(
            'SELECT option_key, label FROM dropdown_options WHERE category = ? AND is_active = 1 ORDER BY sort_order, label'
        );
        $stmt->execute(array($category));
        $result = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $row['option_key'] ? $row['option_key'] : $row['label'];
            $result[$key] = $row['label'];
        }
        return $result;
    }

    /**
     * 新增有 option_key 的選項
     */
    public function addKeyedOption($category, $key, $label)
    {
        $maxSort = $this->db->prepare('SELECT MAX(sort_order) FROM dropdown_options WHERE category = ?');
        $maxSort->execute(array($category));
        $nextSort = (int)$maxSort->fetchColumn() + 1;

        $stmt = $this->db->prepare(
            'INSERT INTO dropdown_options (category, option_key, label, sort_order) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute(array($category, $key, $label, $nextSort));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新選項 (含排序)
     */
    public function updateOptionFull($id, $label, $sortOrder)
    {
        $stmt = $this->db->prepare('UPDATE dropdown_options SET label = ?, sort_order = ? WHERE id = ?');
        $stmt->execute(array($label, (int)$sortOrder, $id));
    }

    /**
     * 切換選項啟用/停用
     */
    public function toggleOption($id)
    {
        $opt = $this->getById($id);
        if (!$opt) return false;
        $newActive = $opt['is_active'] ? 0 : 1;
        $stmt = $this->db->prepare('UPDATE dropdown_options SET is_active = ? WHERE id = ?');
        $stmt->execute(array($newActive, $id));
        return true;
    }

    /**
     * 判斷分類是否使用 key-value 模式
     */
    public function isKeyedCategory($category)
    {
        $keyed = array('case_type', 'case_progress', 'case_status');
        return in_array($category, $keyed);
    }

    /**
     * 檢查 dropdown_options 表是否有 option_key 欄位
     */
    public function hasOptionKeyColumn()
    {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM dropdown_options LIKE 'option_key'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // ============================================================
    // 角色管理
    // ============================================================

    /**
     * 取得所有角色（含停用，管理用）
     */
    public function getAllRoles()
    {
        return $this->db->query(
            'SELECT * FROM system_roles ORDER BY sort_order, role_key'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得所有啟用的角色 (key => label)
     */
    public function getActiveRoles()
    {
        $stmt = $this->db->query(
            'SELECT role_key, role_label FROM system_roles WHERE is_active = 1 ORDER BY sort_order, role_key'
        );
        $roles = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $roles[$row['role_key']] = $row['role_label'];
        }
        return $roles;
    }

    /**
     * 新增角色
     */
    public function addRole($roleKey, $roleLabel)
    {
        $maxSort = $this->db->query('SELECT MAX(sort_order) FROM system_roles');
        $nextSort = (int)$maxSort->fetchColumn() + 1;

        $stmt = $this->db->prepare(
            'INSERT INTO system_roles (role_key, role_label, is_system, sort_order) VALUES (?, ?, 0, ?)'
        );
        $stmt->execute(array($roleKey, $roleLabel, $nextSort));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新角色標籤
     */
    public function updateRole($id, $roleLabel)
    {
        $stmt = $this->db->prepare('UPDATE system_roles SET role_label = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute(array($roleLabel, $id));
    }

    /**
     * 更新角色代碼和標籤
     */
    public function updateRoleFull($id, $roleKey, $roleLabel)
    {
        $stmt = $this->db->prepare('UPDATE system_roles SET role_key = ?, role_label = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute(array($roleKey, $roleLabel, $id));
    }

    /**
     * 停用角色（不刪除）
     */
    public function deactivateRole($id)
    {
        $stmt = $this->db->prepare('UPDATE system_roles SET is_active = 0, updated_at = NOW() WHERE id = ?');
        $stmt->execute(array($id));
    }

    /**
     * 啟用角色
     */
    public function activateRole($id)
    {
        $stmt = $this->db->prepare('UPDATE system_roles SET is_active = 1, updated_at = NOW() WHERE id = ?');
        $stmt->execute(array($id));
    }

    /**
     * 取得單一角色
     */
    public function getRoleById($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM system_roles WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 檢查角色是否有使用者
     */
    public function getRoleUserCount($roleKey)
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE role = ? AND is_active = 1');
        $stmt->execute(array($roleKey));
        return (int)$stmt->fetchColumn();
    }

    /**
     * 檢查角色代碼是否已存在
     */
    public function roleKeyExists($roleKey, $excludeId = 0)
    {
        $sql = 'SELECT COUNT(*) FROM system_roles WHERE role_key = ?';
        $params = array($roleKey);
        if ($excludeId > 0) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ============================================================
    // 單號設定
    // ============================================================

    public function getNumberSequences()
    {
        return $this->db->query('SELECT * FROM number_sequences ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateNumberSequence($id, $data)
    {
        $stmt = $this->db->prepare(
            'UPDATE number_sequences SET prefix = ?, date_format = ?, `separator` = ?, seq_digits = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute(array(
            $data['prefix'],
            $data['date_format'],
            $data['separator'],
            (int)$data['seq_digits'],
            $id,
        ));
    }

    // ============================================================
    // 系統設定 (system_settings)
    // ============================================================

    /**
     * 取得某群組的所有設定
     */
    public function getSettings($group)
    {
        $stmt = $this->db->prepare('SELECT setting_key, setting_value FROM system_settings WHERE setting_group = ?');
        $stmt->execute(array($group));
        $result = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }

    /**
     * 取得單一設定值
     */
    public function getSetting($key, $default = '')
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $stmt->execute(array($key));
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }

    /**
     * 批次儲存設定
     */
    public function saveSettings($data, $group)
    {
        $stmt = $this->db->prepare('INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($data as $key => $value) {
            $stmt->execute(array($key, $value, $group));
        }
    }
}
