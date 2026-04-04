<?php
/**
 * 車輛管理模型
 */
class VehicleModel
{
    /** @var PDO */
    private $db;

    private static $typeLabels = array(
        'truck'    => '貨車',
        'van'      => '廂型車',
        'business' => '業務車',
    );

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function typeLabel($type)
    {
        return isset(self::$typeLabels[$type]) ? self::$typeLabels[$type] : $type;
    }

    public static function typeLabels()
    {
        return self::$typeLabels;
    }

    /**
     * 車輛列表
     */
    public function getList(array $branchIds, array $filters = array())
    {
        $where = 'v.branch_id IN (' . implode(',', array_fill(0, count($branchIds), '?')) . ')';
        $params = $branchIds;

        if (!empty($filters['vehicle_type'])) {
            $where .= ' AND v.vehicle_type = ?';
            $params[] = $filters['vehicle_type'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (v.plate_number LIKE ? OR v.brand LIKE ? OR v.model LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (isset($filters['is_active'])) {
            $where .= ' AND v.is_active = ?';
            $params[] = (int)$filters['is_active'];
        } else {
            $where .= ' AND v.is_active = 1';
        }

        $stmt = $this->db->prepare("
            SELECT v.*, b.name AS branch_name, u.real_name AS custodian_name
            FROM vehicles v
            LEFT JOIN branches b ON v.branch_id = b.id
            LEFT JOIN users u ON v.custodian_id = u.id
            WHERE $where
            ORDER BY v.plate_number
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 取得單一車輛
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare('
            SELECT v.*, b.name AS branch_name, u.real_name AS custodian_name
            FROM vehicles v
            LEFT JOIN branches b ON v.branch_id = b.id
            LEFT JOIN users u ON v.custodian_id = u.id
            WHERE v.id = ?
        ');
        $stmt->execute(array($id));
        $vehicle = $stmt->fetch();
        if (!$vehicle) return null;

        // 載入工具
        $toolStmt = $this->db->prepare('SELECT * FROM vehicle_tools WHERE vehicle_id = ? ORDER BY tool_name');
        $toolStmt->execute(array($id));
        $vehicle['tools'] = $toolStmt->fetchAll();

        // 載入檔案
        $fileStmt = $this->db->prepare('SELECT vf.*, u.real_name AS uploader_name FROM vehicle_files vf LEFT JOIN users u ON vf.uploaded_by = u.id WHERE vf.vehicle_id = ? ORDER BY vf.created_at DESC');
        $fileStmt->execute(array($id));
        $vehicle['files'] = $fileStmt->fetchAll();

        return $vehicle;
    }

    /**
     * 新增車輛
     */
    public function create(array $data)
    {
        $stmt = $this->db->prepare('
            INSERT INTO vehicles (plate_number, vehicle_type, brand, model, year, color, custodian_id, branch_id,
                                  last_maintenance_date, maintenance_mileage, next_maintenance_date, current_mileage, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            $data['plate_number'],
            $data['vehicle_type'],
            $data['brand'] ?: null,
            $data['model'] ?: null,
            $data['year'] ?: null,
            $data['color'] ?: null,
            $data['custodian_id'] ?: null,
            $data['branch_id'],
            $data['last_maintenance_date'] ?: null,
            $data['maintenance_mileage'] ?: null,
            $data['next_maintenance_date'] ?: null,
            $data['current_mileage'] ?: null,
            $data['note'] ?: null,
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新車輛
     */
    public function update($id, array $data)
    {
        $stmt = $this->db->prepare('
            UPDATE vehicles SET plate_number = ?, vehicle_type = ?, brand = ?, model = ?, year = ?, color = ?,
                   custodian_id = ?, branch_id = ?, last_maintenance_date = ?, maintenance_mileage = ?,
                   next_maintenance_date = ?, current_mileage = ?, note = ?,
                   vehicle_number = ?, leasing_company = ?, contract_months = ?, monthly_rent = ?,
                   contract_start = ?, contract_end = ?, next_maintenance_mileage = ?,
                   maintenance_address = ?, maintenance_contact = ?, inspection_date = ?, inspection_contact = ?,
                   leasing_contact = ?, leasing_phone = ?, leasing_mobile = ?, leasing_tax_id = ?
            WHERE id = ?
        ');
        $stmt->execute(array(
            $data['plate_number'],
            $data['vehicle_type'],
            $data['brand'] ?: null,
            $data['model'] ?: null,
            $data['year'] ?: null,
            $data['color'] ?: null,
            $data['custodian_id'] ?: null,
            $data['branch_id'],
            $data['last_maintenance_date'] ?: null,
            $data['maintenance_mileage'] ?: null,
            $data['next_maintenance_date'] ?: null,
            $data['current_mileage'] ?: null,
            $data['note'] ?: null,
            $data['vehicle_number'] ?: null,
            $data['leasing_company'] ?: null,
            $data['contract_months'] ?: null,
            $data['monthly_rent'] ?: null,
            $data['contract_start'] ?: null,
            $data['contract_end'] ?: null,
            $data['next_maintenance_mileage'] ?: null,
            $data['maintenance_address'] ?: null,
            $data['maintenance_contact'] ?: null,
            $data['inspection_date'] ?: null,
            $data['inspection_contact'] ?: null,
            $data['leasing_contact'] ?: null,
            $data['leasing_phone'] ?: null,
            $data['leasing_mobile'] ?: null,
            $data['leasing_tax_id'] ?: null,
            $id,
        ));
    }

    /**
     * 刪除車輛（軟刪除）
     */
    public function deactivate($id)
    {
        $this->db->prepare('UPDATE vehicles SET is_active = 0 WHERE id = ?')->execute(array($id));
    }

    /**
     * 取得保養紀錄
     */
    public function getMaintenanceHistory($vehicleId)
    {
        $stmt = $this->db->prepare('
            SELECT vm.*, u.real_name AS created_by_name
            FROM vehicle_maintenance vm
            LEFT JOIN users u ON vm.created_by = u.id
            WHERE vm.vehicle_id = ?
            ORDER BY vm.maintenance_date DESC
        ');
        $stmt->execute(array($vehicleId));
        return $stmt->fetchAll();
    }

    /**
     * 新增保養紀錄
     */
    public function addMaintenance($vehicleId, array $data)
    {
        $stmt = $this->db->prepare('
            INSERT INTO vehicle_maintenance (vehicle_id, maintenance_date, maintenance_type, mileage, cost, description, next_date, next_mileage, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            $vehicleId,
            $data['maintenance_date'],
            $data['maintenance_type'] ?: 'regular',
            $data['mileage'] ?: null,
            $data['cost'] ?: 0,
            $data['description'] ?: null,
            $data['next_date'] ?: null,
            $data['next_mileage'] ?: null,
            Auth::id(),
        ));

        // 自動更新車輛的保養資訊
        $update = $this->db->prepare('
            UPDATE vehicles SET last_maintenance_date = ?, maintenance_mileage = ?,
                   next_maintenance_date = ?, current_mileage = ?
            WHERE id = ?
        ');
        $update->execute(array(
            $data['maintenance_date'],
            $data['mileage'] ?: null,
            $data['next_date'] ?: null,
            $data['mileage'] ?: null,
            $vehicleId,
        ));

        return (int)$this->db->lastInsertId();
    }

    /**
     * 儲存工具配備
     */
    public function saveTools($vehicleId, array $tools)
    {
        $this->db->prepare('DELETE FROM vehicle_tools WHERE vehicle_id = ?')->execute(array($vehicleId));
        $stmt = $this->db->prepare('INSERT INTO vehicle_tools (vehicle_id, tool_name, quantity, note) VALUES (?, ?, ?, ?)');
        foreach ($tools as $tool) {
            if (empty($tool['tool_name'])) continue;
            $stmt->execute(array(
                $vehicleId,
                $tool['tool_name'],
                max(1, (int)($tool['quantity'] ?: 1)),
                $tool['note'] ?: null,
            ));
        }
    }

    /**
     * 儲存車輛檔案
     */
    public function saveFile($vehicleId, $fileName, $filePath, $fileType)
    {
        $stmt = $this->db->prepare('
            INSERT INTO vehicle_files (vehicle_id, file_name, file_path, file_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute(array($vehicleId, $fileName, $filePath, $fileType, Auth::id()));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 刪除車輛檔案
     */
    public function deleteFile($fileId)
    {
        $stmt = $this->db->prepare('SELECT file_path FROM vehicle_files WHERE id = ?');
        $stmt->execute(array($fileId));
        $path = $stmt->fetchColumn();
        if ($path) {
            $fullPath = __DIR__ . '/../../public' . $path;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        $this->db->prepare('DELETE FROM vehicle_files WHERE id = ?')->execute(array($fileId));
    }

    /**
     * 取得所有使用者（保管人選單用）
     */
    public function getUsers(array $branchIds)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("SELECT id, real_name FROM users WHERE branch_id IN ($ph) AND is_active = 1 ORDER BY real_name");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    /**
     * 取得分公司列表
     */
    public function getBranches()
    {
        return $this->db->query('SELECT * FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll();
    }

    /**
     * 需保養提醒的車輛
     */
    public function getDueForMaintenance(array $branchIds, $daysAhead = 14)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = array_merge($branchIds, array(date('Y-m-d', strtotime("+$daysAhead days"))));
        $stmt = $this->db->prepare("
            SELECT v.*, b.name AS branch_name, u.real_name AS custodian_name
            FROM vehicles v
            LEFT JOIN branches b ON v.branch_id = b.id
            LEFT JOIN users u ON v.custodian_id = u.id
            WHERE v.branch_id IN ($ph) AND v.is_active = 1
              AND v.next_maintenance_date IS NOT NULL
              AND v.next_maintenance_date <= ?
            ORDER BY v.next_maintenance_date
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
