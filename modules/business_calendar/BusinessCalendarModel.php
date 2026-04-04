<?php
class BusinessCalendarModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得月份事件
     */
    public function getMonthEvents($year, $month, $filters = array())
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $where = "bc.event_date BETWEEN ? AND ?";
        $params = array($startDate, $endDate);

        if (!empty($filters['staff_id'])) {
            $where .= " AND bc.staff_id = ?";
            $params[] = $filters['staff_id'];
        }
        if (!empty($filters['region'])) {
            // 支援 branch_id 篩選（透過 staff 的分公司）
            $where .= " AND u.branch_id = ?";
            $params[] = $filters['region'];
        }
        if (!empty($filters['activity_type'])) {
            $where .= " AND bc.activity_type = ?";
            $params[] = $filters['activity_type'];
        }

        $sql = "SELECT bc.*, u.real_name as staff_name, u.branch_id
                FROM business_calendar bc
                LEFT JOIN users u ON bc.staff_id = u.id
                WHERE $where
                ORDER BY bc.event_date, bc.start_time, bc.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 按日期分組
        $events = array();
        foreach ($rows as $row) {
            $d = (int)date('j', strtotime($row['event_date']));
            if (!isset($events[$d])) {
                $events[$d] = array();
            }
            $events[$d][] = $row;
        }
        return $events;
    }

    /**
     * 取得列表（含分頁）
     */
    public function getList($filters = array())
    {
        $where = '1=1';
        $params = array();

        if (!empty($filters['start_date'])) {
            $where .= " AND bc.event_date >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where .= " AND bc.event_date <= ?";
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['staff_id'])) {
            $where .= " AND bc.staff_id = ?";
            $params[] = $filters['staff_id'];
        }
        if (!empty($filters['region'])) {
            $where .= " AND u.branch_id = ?";
            $params[] = $filters['region'];
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $where .= " AND (bc.customer_name LIKE ? OR bc.address LIKE ? OR bc.note LIKE ?)";
            $params = array_merge($params, array($kw, $kw, $kw));
        }

        $sql = "SELECT bc.*, u.real_name as staff_name, u.branch_id
                FROM business_calendar bc
                LEFT JOIN users u ON bc.staff_id = u.id
                WHERE $where
                ORDER BY bc.event_date DESC, bc.start_time
                LIMIT 200";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 單筆
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT bc.*, u.real_name as staff_name FROM business_calendar bc LEFT JOIN users u ON bc.staff_id = u.id WHERE bc.id = ?");
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 新增
     */
    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO business_calendar (event_date, staff_id, case_id, customer_id, customer_name,
                activity_type, phone, region, address, start_time, end_time, note, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'planned', ?)
        ");
        $stmt->execute(array(
            $data['event_date'],
            $data['staff_id'],
            $data['case_id'] ?: null,
            $data['customer_id'] ?: null,
            $data['customer_name'] ?: null,
            $data['activity_type'] ?: 'visit',
            $data['phone'] ?: null,
            $data['region'] ?: null,
            $data['address'] ?: null,
            $data['start_time'] ?: null,
            $data['end_time'] ?: null,
            $data['note'] ?: null,
            Auth::id()
        ));
        return $this->db->lastInsertId();
    }

    /**
     * 更新
     */
    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE business_calendar SET
                event_date = ?, staff_id = ?, case_id = ?, customer_id = ?, customer_name = ?,
                activity_type = ?, phone = ?, region = ?, address = ?,
                start_time = ?, end_time = ?, note = ?, status = ?, result = ?
            WHERE id = ?
        ");
        $stmt->execute(array(
            $data['event_date'],
            $data['staff_id'],
            $data['case_id'] ?: null,
            $data['customer_id'] ?: null,
            $data['customer_name'] ?: null,
            $data['activity_type'] ?: 'visit',
            $data['phone'] ?: null,
            $data['region'] ?: null,
            $data['address'] ?: null,
            $data['start_time'] ?: null,
            $data['end_time'] ?: null,
            $data['note'] ?: null,
            $data['status'] ?: 'planned',
            $data['result'] ?: null,
            $id
        ));
    }

    /**
     * 刪除
     */
    public function delete($id)
    {
        $this->db->prepare("DELETE FROM business_calendar WHERE id = ?")->execute(array($id));
    }

    /**
     * 取得業務人員
     */
    public function getSalespeople()
    {
        $stmt = $this->db->query("SELECT id, real_name FROM users WHERE role IN ('sales','sales_manager','sales_assistant','boss','manager') AND is_active = 1 ORDER BY real_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 活動類型
     */
    public static function activityTypes()
    {
        return array(
            'visit'      => '拜訪',
            'survey'     => '場勘',
            'follow_up'  => '跟催',
            'quotation'  => '報價',
            'signing'    => '簽約',
            'other'      => '其他',
        );
    }

    /**
     * 地區選項
     */
    public static function regionOptions()
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY id");
        $options = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $options[$b['id']] = $b['name'];
        }
        return $options;
    }

    /**
     * 活動類型顏色
     */
    public static function activityColor($type)
    {
        $colors = array(
            'visit'     => '#2196F3',
            'survey'    => '#FF9800',
            'follow_up' => '#9C27B0',
            'quotation' => '#4CAF50',
            'signing'   => '#F44336',
            'other'     => '#607D8B',
        );
        return isset($colors[$type]) ? $colors[$type] : '#607D8B';
    }

    /**
     * 取得某日事件數
     */
    public function getDayEventCount($date)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM business_calendar WHERE event_date = ?");
        $stmt->execute(array($date));
        return $stmt->fetchColumn();
    }
}
