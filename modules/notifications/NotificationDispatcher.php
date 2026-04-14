<?php
/**
 * 通知分派器 - 根據 notification_settings 規則自動發送通知
 */
class NotificationDispatcher
{
    /**
     * 觸發通知
     *
     * @param string $module   模組名稱 (receipts, cases, repairs, etc.)
     * @param string $event    事件類型 (created, updated, status_changed, assigned)
     * @param array  $record   記錄資料 (must contain id, branch_id)
     * @param int    $actorId  觸發者的 user ID
     * @param array  $oldRecord 舊記錄 (optional, for comparison)
     * @return array 已發送的 notification IDs
     */
    public static function dispatch($module, $event, $record, $actorId, $oldRecord = array())
    {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT * FROM notification_settings WHERE module = ? AND event = ? AND is_active = 1 ORDER BY sort_order'
        );
        $stmt->execute(array($module, $event));
        $rules = $stmt->fetchAll();

        if (empty($rules)) {
            return array();
        }

        require_once __DIR__ . '/NotificationModel.php';
        $notifModel = new NotificationModel();
        $sentIds = array();

        foreach ($rules as $rule) {
            if (!self::matchesCondition($rule, $record)) {
                continue;
            }

            $title = self::resolveTemplate($rule['title_template'], $record, $actorId);
            $message = self::resolveTemplate($rule['message_template'], $record, $actorId);
            $link = self::resolveTemplate($rule['link_template'], $record, $actorId);
            $type = $module . '_' . $event;
            $branchId = ($rule['branch_scope'] === 'same')
                ? (isset($record['branch_id']) ? $record['branch_id'] : null)
                : null;
            $recordId = isset($record['id']) ? $record['id'] : null;

            // 查詢支援分公司（案件相關通知也發給支援分公司）
            $supportBranchIds = array();
            if ($rule['branch_scope'] === 'same' && $branchId) {
                $caseId = isset($record['case_id']) ? $record['case_id'] : (isset($record['id']) && $module === 'cases' ? $record['id'] : null);
                if ($caseId) {
                    try {
                        $sbStmt = Database::getInstance()->prepare('SELECT branch_id FROM case_branch_support WHERE case_id = ?');
                        $sbStmt->execute(array($caseId));
                        $supportBranchIds = array_column($sbStmt->fetchAll(PDO::FETCH_ASSOC), 'branch_id');
                    } catch (Exception $e) {}
                }
            }

            // 支援多目標（逗號分隔）
            $targets = explode(',', $rule['notify_target']);
            foreach ($targets as $target) {
                $target = trim($target);
                if (empty($target)) continue;

                if ($rule['notify_type'] === 'role') {
                    $ids = $notifModel->sendToRole(
                        $target,
                        $branchId,
                        $type,
                        $title,
                        $message,
                        $link,
                        $module,
                        $recordId,
                        $actorId
                    );
                    $sentIds = array_merge($sentIds, $ids);
                    // 也通知支援分公司的同角色人員
                    foreach ($supportBranchIds as $sbId) {
                        $ids2 = $notifModel->sendToRole($target, (int)$sbId, $type, $title, $message, $link, $module, $recordId, $actorId);
                        $sentIds = array_merge($sentIds, $ids2);
                    }
                } elseif ($rule['notify_type'] === 'field') {
                    $targetUserId = isset($record[$target]) ? (int)$record[$target] : 0;
                    if ($targetUserId > 0 && $targetUserId != $actorId) {
                        $id = $notifModel->send(
                            $targetUserId,
                            $type,
                            $title,
                            $message,
                            $link,
                            $module,
                            $recordId,
                            $actorId
                        );
                        $sentIds[] = $id;
                    }
                }
            }
        }

        return $sentIds;
    }

    /**
     * 檢查規則條件是否符合
     */
    private static function matchesCondition($rule, $record)
    {
        if (empty($rule['condition_field']) || $rule['condition_value'] === null) {
            return true;
        }
        $field = $rule['condition_field'];
        $value = isset($record[$field]) ? (string)$record[$field] : '';
        return $value === $rule['condition_value'];
    }

    /**
     * 替換模板中的 {placeholder}
     */
    private static function resolveTemplate($template, $record, $actorId)
    {
        if (empty($template)) {
            return '';
        }
        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($record, $actorId) {
            $key = $matches[1];
            if ($key === 'actor_name') {
                return self::getUserName($actorId);
            }
            if (isset($record[$key])) {
                if (is_numeric($record[$key]) && strpos($key, 'amount') !== false) {
                    return number_format($record[$key]);
                }
                return $record[$key];
            }
            return $matches[0];
        }, $template);
    }

    /**
     * 取得使用者名稱（同次 request 快取）
     */
    private static function getUserName($userId)
    {
        static $cache = array();
        if (!$userId) return '';
        if (isset($cache[$userId])) return $cache[$userId];
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT real_name FROM users WHERE id = ?');
        $stmt->execute(array($userId));
        $name = $stmt->fetchColumn();
        $cache[$userId] = $name ? $name : '';
        return $cache[$userId];
    }
}
