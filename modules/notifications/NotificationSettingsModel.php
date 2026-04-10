<?php
/**
 * 通知設定 Model - CRUD for notification_settings
 */
class NotificationSettingsModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得所有規則（可篩選模組）
     */
    public function getAll($module = null)
    {
        $sql = 'SELECT ns.*, sr.role_label AS role_label FROM notification_settings ns LEFT JOIN system_roles sr ON ns.notify_type = "role" AND ns.notify_target = sr.role_key AND sr.is_active = 1 ';
        $params = array();
        if ($module) {
            $sql .= ' WHERE ns.module = ?';
            $params[] = $module;
        }
        $sql .= ' ORDER BY ns.module, ns.sort_order, ns.id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 取得單筆規則
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM notification_settings WHERE id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch();
    }

    /**
     * 新增規則
     */
    public function create($data)
    {
        $stmt = $this->db->prepare('
            INSERT INTO notification_settings (module, event, condition_field, condition_value, notify_type, notify_target, branch_scope, title_template, message_template, link_template, is_active, sort_order, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            $data['module'],
            $data['event'],
            !empty($data['condition_field']) ? $data['condition_field'] : null,
            !empty($data['condition_value']) ? $data['condition_value'] : null,
            $data['notify_type'],
            $data['notify_target'],
            isset($data['branch_scope']) ? $data['branch_scope'] : 'same',
            $data['title_template'],
            isset($data['message_template']) ? $data['message_template'] : '',
            isset($data['link_template']) ? $data['link_template'] : '',
            isset($data['is_active']) ? (int)$data['is_active'] : 1,
            isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
            isset($data['created_by']) ? $data['created_by'] : null,
        ));
        return $this->db->lastInsertId();
    }

    /**
     * 更新規則
     */
    public function update($id, $data)
    {
        $stmt = $this->db->prepare('
            UPDATE notification_settings SET
                module = ?, event = ?, condition_field = ?, condition_value = ?,
                notify_type = ?, notify_target = ?, branch_scope = ?,
                title_template = ?, message_template = ?, link_template = ?,
                sort_order = ?
            WHERE id = ?
        ');
        $stmt->execute(array(
            $data['module'],
            $data['event'],
            !empty($data['condition_field']) ? $data['condition_field'] : null,
            !empty($data['condition_value']) ? $data['condition_value'] : null,
            $data['notify_type'],
            $data['notify_target'],
            isset($data['branch_scope']) ? $data['branch_scope'] : 'same',
            $data['title_template'],
            isset($data['message_template']) ? $data['message_template'] : '',
            isset($data['link_template']) ? $data['link_template'] : '',
            isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
            $id,
        ));
    }

    /**
     * 切換啟用/停用
     */
    public function toggleActive($id)
    {
        $stmt = $this->db->prepare('UPDATE notification_settings SET is_active = IF(is_active=1,0,1) WHERE id = ?');
        $stmt->execute(array($id));
    }

    /**
     * 刪除規則
     */
    public function delete($id)
    {
        $stmt = $this->db->prepare('DELETE FROM notification_settings WHERE id = ?');
        $stmt->execute(array($id));
    }

    /**
     * 模組註冊表
     */
    public function getModuleRegistry()
    {
        return array(
            'billing_items' => array(
                'label' => '請款項目',
                'events' => array(
                    'customer_billable_changed' => '客戶通知可請款',
                    'customer_paid_changed' => '客戶通知已付款',
                    'is_billed_changed' => '已請款',
                ),
                'condition_fields' => array(),
                'record_fields' => array(),
            ),
            'receipts' => array(
                'label' => '收款單',
                'events' => array(
                    'created' => '新建',
                    'updated' => '更新',
                    'status_changed' => '狀態變更',
                ),
                'condition_fields' => array(
                    'status' => array('label' => '狀態', 'values' => array('已收款', '已收待查資料', '預收待查', '保留款', '待收款', '已入帳', '退款', '取消', '拋轉待確認')),
                ),
                'record_fields' => array('sales_id' => '承辦業務'),
            ),
            'business_tracking' => array(
                'label' => '業務追蹤',
                'events' => array(
                    'created' => '新建',
                    'updated' => '更新',
                    'status_changed' => '狀態變更',
                    'assigned' => '指派業務',
                ),
                'condition_fields' => array(
                    'sub_status' => array('label' => '細項狀態', 'values' => array('已成交', '跨月成交', '現簽', '電話報價成交', '已報價無意願', '報價無下文', '無效', '客戶毀約')),
                    'status' => array('label' => '主狀態', 'values' => array('追蹤中', '已成交', '流標', '暫緩', '取消')),
                ),
                'record_fields' => array('sales_id' => '承辦業務'),
            ),
            'cases' => array(
                'label' => '案件管理',
                'events' => array(
                    'created' => '新建',
                    'updated' => '更新',
                    'status_changed' => '狀態變更',
                ),
                'condition_fields' => array(
                    'status' => array('label' => '狀態', 'values' => array(
                        'tracking' => '待追蹤', 'incomplete' => '未完工', 'unpaid' => '完工未收款',
                        'closed' => '已完工結案', 'lost' => '未成交', 'awaiting_dispatch' => '待安排派工查修',
                        'customer_cancel' => '客戶取消', 'breach' => '毀約',
                    )),
                ),
                'record_fields' => array('sales_id' => '承辦業務'),
            ),
            'repairs' => array(
                'label' => '維修單',
                'events' => array(
                    'created' => '新建',
                    'updated' => '更新',
                    'status_changed' => '狀態變更',
                ),
                'condition_fields' => array(
                    'status' => array('label' => '狀態', 'values' => array('待處理', '處理中', '已完成', '已結案')),
                ),
                'record_fields' => array('assigned_to' => '指派人員', 'sales_id' => '承辦業務'),
            ),
            'schedule' => array(
                'label' => '排工',
                'events' => array(
                    'created' => '新建',
                    'updated' => '更新',
                ),
                'condition_fields' => array(),
                'record_fields' => array(),
            ),
            'leaves' => array(
                'label' => '請假',
                'events' => array(
                    'created' => '新建',
                    'status_changed' => '狀態變更',
                ),
                'condition_fields' => array(
                    'status' => array('label' => '狀態', 'values' => array('pending' => '待審核', 'approved' => '已核准', 'rejected' => '已駁回')),
                ),
                'record_fields' => array('user_id' => '請假人'),
            ),
            'worklog' => array(
                'label' => '施工回報',
                'events' => array(
                    'created' => '新建',
                    'status_changed' => '狀態變更',
                ),
                'condition_fields' => array(
                    'status' => array('label' => '狀態', 'values' => array('進行中', '完工')),
                ),
                'record_fields' => array(),
            ),
            'quotations' => array(
                'label' => '報價單',
                'events' => array(
                    'created' => '新建',
                    'updated' => '更新',
                    'status_changed' => '狀態變更',
                ),
                'condition_fields' => array(
                    'status' => array('label' => '狀態', 'values' => array('draft' => '草稿', 'sent' => '已送出', 'confirmed' => '已確認', 'cancelled' => '已取消')),
                ),
                'record_fields' => array('sales_id' => '承辦業務'),
            ),
            'inter_branch' => array(
                'label' => '點工費',
                'events' => array(
                    'created' => '新建',
                    'updated' => '更新',
                ),
                'condition_fields' => array(),
                'record_fields' => array(),
            ),
            'purchases' => array(
                'label' => '請購單',
                'events' => array(
                    'created' => '新建',
                    'status_changed' => '狀態變更',
                    'submitted' => '送簽核',
                    'approved' => '已核准',
                    'rejected' => '退回',
                ),
                'condition_fields' => array(
                    'status' => array('label' => '狀態', 'values' => array('草稿', '簽核中', '已核准', '簽核完成', '退回', '已轉採購')),
                ),
                'record_fields' => array('created_by' => '建立人'),
            ),
            'purchase_orders' => array(
                'label' => '採購單',
                'events' => array(
                    'created' => '新建',
                    'status_changed' => '狀態變更',
                    'updated' => '更新',
                ),
                'condition_fields' => array(
                    'status' => array('label' => '狀態', 'values' => array('尚未進貨', '部分進貨', '確認進貨', '已取消')),
                ),
                'record_fields' => array('created_by' => '建立人'),
            ),
        );
    }

    /**
     * 取得角色列表
     */
    public function getRoles()
    {
        $stmt = $this->db->prepare('SELECT role_key, role_label FROM system_roles WHERE is_active = 1 ORDER BY sort_order, id');
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
