<?php
/**
 * 維修單模型
 */
class RepairModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function statusLabel($status)
    {
        $map = array('draft' => '草稿', 'completed' => '已完成', 'invoiced' => '已開票');
        return isset($map[$status]) ? $map[$status] : $status;
    }

    public static function statusBadge($status)
    {
        $map = array('draft' => 'warning', 'completed' => 'success', 'invoiced' => 'primary');
        return isset($map[$status]) ? $map[$status] : '';
    }

    public function getList(array $branchIds, array $filters = array())
    {
        $where = 'r.branch_id IN (' . implode(',', array_fill(0, count($branchIds), '?')) . ')';
        $params = $branchIds;

        if (!empty($filters['month'])) {
            $where .= ' AND r.repair_date LIKE ?';
            $params[] = $filters['month'] . '%';
        }
        if (!empty($filters['status'])) {
            $where .= ' AND r.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (r.customer_name LIKE ? OR r.repair_number LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $stmt = $this->db->prepare("
            SELECT r.*, b.name AS branch_name, eng.real_name AS engineer_name
            FROM repairs r
            JOIN branches b ON r.branch_id = b.id
            LEFT JOIN users eng ON r.engineer_id = eng.id
            WHERE $where
            ORDER BY r.repair_date DESC, r.id DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare('
            SELECT r.*, b.name AS branch_name, eng.real_name AS engineer_name,
                   cr.real_name AS creator_name
            FROM repairs r
            JOIN branches b ON r.branch_id = b.id
            LEFT JOIN users eng ON r.engineer_id = eng.id
            LEFT JOIN users cr ON r.created_by = cr.id
            WHERE r.id = ?
        ');
        $stmt->execute(array($id));
        $repair = $stmt->fetch();
        if (!$repair) return null;

        // 載入項目
        $itemStmt = $this->db->prepare('SELECT * FROM repair_items WHERE repair_id = ? ORDER BY id');
        $itemStmt->execute(array($id));
        $repair['items'] = $itemStmt->fetchAll();

        return $repair;
    }

    public function create(array $data)
    {
        $repairNumber = $this->generateRepairNumber();
        $stmt = $this->db->prepare('
            INSERT INTO repairs (repair_number, branch_id, customer_name, customer_phone, customer_address, engineer_id, repair_date, note, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            $repairNumber,
            $data['branch_id'],
            $data['customer_name'],
            $data['customer_phone'] ?: null,
            $data['customer_address'] ?: null,
            $data['engineer_id'] ?: null,
            $data['repair_date'],
            $data['note'] ?: null,
            Auth::id(),
        ));
        return (int)$this->db->lastInsertId();
    }

    public function update($id, array $data)
    {
        $stmt = $this->db->prepare('
            UPDATE repairs SET customer_name = ?, customer_phone = ?, customer_address = ?,
                   engineer_id = ?, repair_date = ?, note = ?
            WHERE id = ?
        ');
        $stmt->execute(array(
            $data['customer_name'],
            $data['customer_phone'] ?: null,
            $data['customer_address'] ?: null,
            $data['engineer_id'] ?: null,
            $data['repair_date'],
            $data['note'] ?: null,
            $id,
        ));
    }

    public function delete($id)
    {
        $this->db->prepare("DELETE FROM repairs WHERE id = ? AND status = 'draft'")->execute(array($id));
    }

    public function updateStatus($id, $status)
    {
        $this->db->prepare('UPDATE repairs SET status = ? WHERE id = ?')->execute(array($status, $id));
    }

    public function saveItems($repairId, array $items)
    {
        $this->db->prepare('DELETE FROM repair_items WHERE repair_id = ?')->execute(array($repairId));
        $stmt = $this->db->prepare('INSERT INTO repair_items (repair_id, description, quantity, unit_price, amount) VALUES (?, ?, ?, ?, ?)');
        $total = 0;
        foreach ($items as $item) {
            if (empty($item['description'])) continue;
            $qty = max(1, (int)$item['quantity']);
            $price = (int)$item['unit_price'];
            $amount = $qty * $price;
            $stmt->execute(array($repairId, $item['description'], $qty, $price, $amount));
            $total += $amount;
        }
        $this->db->prepare('UPDATE repairs SET total_amount = ? WHERE id = ?')->execute(array($total, $repairId));
    }

    public function getEngineers(array $branchIds)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("SELECT id, real_name FROM users WHERE branch_id IN ($ph) AND is_engineer = 1 AND is_active = 1 AND employment_status IN ('active','probation') AND employee_id IS NOT NULL AND employee_id != '' ORDER BY real_name");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    public function getBranches()
    {
        return $this->db->query('SELECT * FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll();
    }

    /**
     * 取得維修單回報列表
     */
    public function getReports($repairId)
    {
        $stmt = $this->db->prepare('
            SELECT rr.*, u.real_name AS reporter_name
            FROM repair_reports rr
            LEFT JOIN users u ON rr.user_id = u.id
            WHERE rr.repair_id = ?
            ORDER BY rr.created_at DESC
        ');
        $stmt->execute(array($repairId));
        $reports = $stmt->fetchAll();

        // 載入每則回報的照片
        foreach ($reports as &$r) {
            $photoStmt = $this->db->prepare('SELECT * FROM repair_photos WHERE report_id = ? ORDER BY id');
            $photoStmt->execute(array($r['id']));
            $r['photos'] = $photoStmt->fetchAll();
        }
        return $reports;
    }

    /**
     * 取得維修單全部照片
     */
    public function getPhotos($repairId)
    {
        $stmt = $this->db->prepare('SELECT * FROM repair_photos WHERE repair_id = ? ORDER BY uploaded_at DESC');
        $stmt->execute(array($repairId));
        return $stmt->fetchAll();
    }

    /**
     * 新增維修回報
     */
    public function addReport($repairId, $reportText)
    {
        $stmt = $this->db->prepare('
            INSERT INTO repair_reports (repair_id, user_id, report_text)
            VALUES (?, ?, ?)
        ');
        $stmt->execute(array($repairId, Auth::id(), $reportText));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 儲存維修照片
     */
    public function savePhoto($repairId, $reportId, $filePath, $caption)
    {
        $stmt = $this->db->prepare('
            INSERT INTO repair_photos (repair_id, report_id, file_path, caption)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute(array($repairId, $reportId, $filePath, $caption ?: null));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 刪除維修照片
     */
    public function deletePhoto($photoId)
    {
        $stmt = $this->db->prepare('SELECT file_path FROM repair_photos WHERE id = ?');
        $stmt->execute(array($photoId));
        $path = $stmt->fetchColumn();
        if ($path) {
            $fullPath = __DIR__ . '/../../public' . $path;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        $this->db->prepare('DELETE FROM repair_photos WHERE id = ?')->execute(array($photoId));
    }

    private function generateRepairNumber()
    {
        $prefix = 'R-' . date('Ymd') . '-';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM repairs WHERE repair_number LIKE ?");
        $stmt->execute(array($prefix . '%'));
        $count = (int)$stmt->fetchColumn() + 1;
        return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
