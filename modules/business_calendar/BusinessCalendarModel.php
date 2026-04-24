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
        // 限制可查看的分公司範圍（透過承辦業務的分公司）
        if (isset($filters['branch_ids']) && is_array($filters['branch_ids'])) {
            if (empty($filters['branch_ids'])) {
                // 沒有任何可查看的分公司
                return array();
            }
            $ph = implode(',', array_fill(0, count($filters['branch_ids']), '?'));
            $where .= " AND u.branch_id IN ($ph)";
            $params = array_merge($params, array_map('intval', $filters['branch_ids']));
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
        // 限制可查看的分公司範圍
        if (isset($filters['branch_ids']) && is_array($filters['branch_ids'])) {
            if (empty($filters['branch_ids'])) return array();
            $ph = implode(',', array_fill(0, count($filters['branch_ids']), '?'));
            $where .= " AND u.branch_id IN ($ph)";
            $params = array_merge($params, array_map('intval', $filters['branch_ids']));
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
        $stmt = $this->db->prepare("
            SELECT bc.*, u.real_name as staff_name,
                   c.description as case_customer_demand
            FROM business_calendar bc
            LEFT JOIN users u ON bc.staff_id = u.id
            LEFT JOIN cases c ON bc.case_id = c.id
            WHERE bc.id = ?
        ");
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

        // 備註回寫到案件的 sales_note
        $caseId = !empty($data['case_id']) ? (int)$data['case_id'] : 0;
        if ($caseId > 0 && array_key_exists('note', $data)) {
            $this->db->prepare("UPDATE cases SET sales_note = ? WHERE id = ?")
                ->execute(array($data['note'] ?: null, $caseId));
        }
    }

    /**
     * 刪除
     */
    public function delete($id)
    {
        $this->db->prepare("DELETE FROM business_calendar WHERE id = ?")->execute(array($id));
    }

    /**
     * 取得案件的現場照片（file_type='site_photo'），供業務行事曆顯示用
     */
    public function getCaseSitePhotos($caseId)
    {
        $stmt = $this->db->prepare("SELECT id, file_name, file_path, file_size, uploaded_by, created_at FROM case_attachments WHERE case_id = ? AND file_type = 'site_photo' ORDER BY created_at DESC");
        $stmt->execute(array($caseId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 上傳現場照片到案件（寫入 case_attachments 表）
     * 回傳：array('ok'=>bool, 'message'=>string, 'id'=>int|null, 'path'=>string|null)
     */
    public function uploadCaseSitePhoto($caseId, $fileField)
    {
        if (empty($fileField) || !isset($fileField['tmp_name']) || !is_uploaded_file($fileField['tmp_name'])) {
            return array('ok'=>false, 'message'=>'無檔案上傳');
        }
        if (!empty($fileField['error']) && $fileField['error'] !== UPLOAD_ERR_OK) {
            return array('ok'=>false, 'message'=>'上傳失敗 (error='.$fileField['error'].')');
        }
        $allowed = array('image/jpeg','image/png','image/gif','image/webp','image/heic','image/heif');
        $mime = mime_content_type($fileField['tmp_name']);
        if (!in_array($mime, $allowed)) {
            $ext = strtolower(pathinfo($fileField['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('jpg','jpeg','png','gif','webp','heic','heif','bmp'))) {
                return array('ok'=>false, 'message'=>'只接受圖片檔');
            }
        }
        $maxSize = 20 * 1024 * 1024;
        if (!empty($fileField['size']) && $fileField['size'] > $maxSize) {
            return array('ok'=>false, 'message'=>'檔案超過 20MB');
        }

        // 用 DOCUMENT_ROOT 確保兩端路徑一致（local=public/ server=www/）
        $webRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : (__DIR__ . '/../../public');
        $dir = $webRoot . '/uploads/cases/' . (int)$caseId;
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $ext = strtolower(pathinfo($fileField['name'], PATHINFO_EXTENSION));
        if (!$ext) $ext = 'jpg';
        $baseName = 'site_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
        $destFull = $dir . '/' . $baseName;
        if (!move_uploaded_file($fileField['tmp_name'], $destFull)) {
            return array('ok'=>false, 'message'=>'檔案移動失敗');
        }

        $relPath = '/uploads/cases/' . (int)$caseId . '/' . $baseName;
        $ins = $this->db->prepare("INSERT INTO case_attachments (case_id, file_type, file_name, file_path, file_size, uploaded_by) VALUES (?, 'site_photo', ?, ?, ?, ?)");
        $ins->execute(array((int)$caseId, $fileField['name'], $relPath, (int)$fileField['size'], Auth::id()));
        return array('ok'=>true, 'message'=>'已上傳', 'id'=>(int)$this->db->lastInsertId(), 'path'=>$relPath);
    }

    /**
     * 刪除業務行程關聯的現場照片（僅限 file_type='site_photo'）
     */
    public function deleteCaseSitePhoto($attachmentId, $caseId)
    {
        $stmt = $this->db->prepare("SELECT file_path FROM case_attachments WHERE id = ? AND case_id = ? AND file_type = 'site_photo'");
        $stmt->execute(array((int)$attachmentId, (int)$caseId));
        $path = $stmt->fetchColumn();
        if (!$path) return array('ok'=>false, 'message'=>'找不到附件');
        $webRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : (__DIR__ . '/../../public');
        $full = $webRoot . $path;
        if (file_exists($full)) @unlink($full);
        $this->db->prepare("DELETE FROM case_attachments WHERE id = ?")->execute(array((int)$attachmentId));
        return array('ok'=>true);
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
     * 業務姓名對應顏色（行事曆側邊色條用）
     * 未列出的業務以姓名 hash 分配備用色，不重複。
     */
    public static function staffColor($name)
    {
        $map = array(
            '彭博璋'   => '#1565C0', // 深藍
            '陳宏璇'   => '#F9A825', // 金黃
            '詹佳航'   => '#EF6C00', // 橘
            '許進鑫'   => '#212121', // 黑
            '小甫偷用' => '#757575', // 灰
            '張永霖'   => '#2E7D32', // 綠
            '陳政隆'   => '#039BE5', // 亮藍
            '張軒瑜'   => '#6A1B9A', // 紫
            '邱漢澤'   => '#9E9D24', // 橄欖黃
        );
        if ($name !== '' && $name !== null && isset($map[$name])) {
            return $map[$name];
        }
        $fallback = array(
            '#D32F2F', '#00897B', '#5D4037', '#C2185B', '#455A64',
            '#E91E63', '#00ACC1', '#8E24AA', '#558B2F', '#FB8C00',
            '#3949AB', '#AD1457', '#00695C', '#4E342E', '#424242',
        );
        if ($name === '' || $name === null) return '#9E9E9E';
        $h = abs(crc32((string)$name));
        return $fallback[$h % count($fallback)];
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
