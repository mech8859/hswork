<?php
/**
 * 考勤管理：MOA 雲考勤匯入與查詢
 */
class AttendanceModel
{
    private $db;
    public function __construct() { $this->db = Database::getInstance(); }

    /**
     * 解析 MOA xlsx「考勤詳細」工作表
     * 預期欄位：日期、周、姓名、員編、部門、異常、申請、應出、實出、額外、調休、出差、簽到、簽退、遲到、早退、曠職
     * 回傳 array of associative rows（保留原文）
     */
    public function parseMoaExcel($filePath)
    {
        require_once __DIR__ . '/../../includes/ExcelReader.php';
        // 優先讀「考勤詳細」工作表，找不到就退回第一張
        $rows = ExcelReader::read($filePath, '考勤詳細');
        if (empty($rows)) {
            $rows = ExcelReader::read($filePath);
        }
        if (empty($rows)) return array();

        // 找 header row：含「簽到」與「簽退」字串那一列
        $headerIdx = -1; $colMap = array();
        $expectedHeaders = array(
            '日'=>'day','周'=>'weekday','姓名'=>'name','員編'=>'employee_no','部門'=>'dept',
            '異常'=>'is_abnormal','申請'=>'has_application','應出'=>'expected','實出'=>'actual',
            '額外'=>'extra','調休'=>'comp_off','出差'=>'business_trip',
            '簽到'=>'sign_in','簽退'=>'sign_out',
            '遲到'=>'late','早退'=>'early_leave','曠職'=>'absent',
        );
        foreach ($rows as $i => $r) {
            $hits = 0;
            foreach ($r as $cell) {
                if (in_array(trim((string)$cell), array('簽到', '簽退'), true)) $hits++;
            }
            if ($hits >= 2) {
                $headerIdx = $i;
                foreach ($r as $colIdx => $cell) {
                    $key = trim((string)$cell);
                    if (isset($expectedHeaders[$key])) {
                        $colMap[$expectedHeaders[$key]] = $colIdx;
                    }
                }
                break;
            }
        }
        if ($headerIdx < 0) return array();

        $out = array();
        $totalRows = count($rows);
        for ($r = $headerIdx + 1; $r < $totalRows; $r++) {
            $row = $rows[$r];
            $name = isset($colMap['name'], $row[$colMap['name']]) ? trim((string)$row[$colMap['name']]) : '';
            $day  = isset($colMap['day'], $row[$colMap['day']]) ? trim((string)$row[$colMap['day']]) : '';
            // 跳過 header 重複行 / 空白行
            if ($name === '' || $name === '姓名') continue;
            if ($day === '' || $day === '日') continue;

            $get = function ($k) use ($colMap, $row) {
                if (!isset($colMap[$k])) return '';
                $v = isset($row[$colMap[$k]]) ? trim((string)$row[$colMap[$k]]) : '';
                return $v;
            };

            $out[] = array(
                'day'             => $day,
                'weekday'         => $get('weekday'),
                'name'            => $name,
                'employee_no'     => $get('employee_no'),
                'dept'            => $get('dept'),
                'is_abnormal'     => $get('is_abnormal'),
                'has_application' => $get('has_application'),
                'expected'        => $get('expected'),
                'actual'          => $get('actual'),
                'extra'           => $get('extra'),
                'comp_off'        => $get('comp_off'),
                'business_trip'   => $get('business_trip'),
                'sign_in'         => $get('sign_in'),
                'sign_out'        => $get('sign_out'),
                'late'            => $get('late'),
                'early_leave'     => $get('early_leave'),
                'absent'          => $get('absent'),
            );
        }
        return $out;
    }

    /**
     * h:mm 格式（例 "5:59"）轉分鐘整數；空字串/非數字 → null
     */
    public static function hmmToMinutes($s)
    {
        $s = trim((string)$s);
        if ($s === '' || $s === '-' || $s === '未簽') return null;
        if (preg_match('/^(\d+):(\d{1,2})$/', $s, $m)) {
            return (int)$m[1] * 60 + (int)$m[2];
        }
        if (is_numeric($s)) return (int)round((float)$s * 60);
        return null;
    }

    /**
     * "07:52" → "07:52:00"，"未簽"/"-"/空 → null
     */
    public static function parseSignTime($s)
    {
        $s = trim((string)$s);
        if ($s === '' || $s === '-' || $s === '未簽') return array(null, $s);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m)) {
            $hh = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mm = $m[2];
            $ss = isset($m[3]) ? $m[3] : '00';
            return array("$hh:$mm:$ss", '正常');
        }
        return array(null, $s);
    }

    /**
     * "05月04日" + 年份 → Y-m-d
     */
    public static function parseDay($day, $yearHint = null)
    {
        $day = trim((string)$day);
        if ($day === '') return null;
        $year = $yearHint ?: (int)date('Y');
        if (preg_match('/^(\d{1,2})月(\d{1,2})日$/u', $day, $m)) {
            return sprintf('%04d-%02d-%02d', $year, $m[1], $m[2]);
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $day, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        $ts = strtotime($day);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * 將解析結果寫入 attendance_records（依 (姓名, work_date) upsert）
     * @param array $parsedRows  parseMoaExcel 的回傳
     * @param int $yearHint 從檔名或選擇的日期推測年份（避免「05月04日」橫跨年份歧義）
     * @return array stats
     */
    public function importParsedRows(array $parsedRows, $yearHint, $sourceFile = null)
    {
        $stats = array('total'=>0, 'inserted'=>0, 'updated'=>0, 'unmatched'=>0, 'skipped'=>0,
                       'date_from'=>null, 'date_to'=>null);
        if (empty($parsedRows)) return $stats;

        // 預先撈員工對照表（moa_name → user_id）
        $mapStmt = $this->db->query("SELECT moa_name, user_id FROM attendance_employees");
        $nameToUid = array();
        foreach ($mapStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $nameToUid[$m['moa_name']] = $m['user_id'];
        }
        // 預先撈 hswork users 姓名 → id（自動建對照用）
        $usersStmt = $this->db->query("SELECT id, real_name FROM users WHERE is_active = 1");
        $userByName = array();
        foreach ($usersStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            if ($u['real_name']) $userByName[$u['real_name']] = (int)$u['id'];
        }

        $upsert = $this->db->prepare("
            INSERT INTO attendance_records
                (user_id, moa_name, moa_employee_no, moa_dept, work_date, weekday,
                 is_abnormal, has_application, expected_minutes, actual_minutes,
                 extra_minutes, comp_off_minutes, business_trip_minutes,
                 sign_in_time, sign_out_time, sign_in_status, sign_out_status,
                 late_minutes, early_leave_minutes, absent_minutes, source_file)
            VALUES
                (?, ?, ?, ?, ?, ?,
                 ?, ?, ?, ?,
                 ?, ?, ?,
                 ?, ?, ?, ?,
                 ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                user_id=VALUES(user_id), moa_employee_no=VALUES(moa_employee_no),
                moa_dept=VALUES(moa_dept), weekday=VALUES(weekday),
                is_abnormal=VALUES(is_abnormal), has_application=VALUES(has_application),
                expected_minutes=VALUES(expected_minutes), actual_minutes=VALUES(actual_minutes),
                extra_minutes=VALUES(extra_minutes), comp_off_minutes=VALUES(comp_off_minutes),
                business_trip_minutes=VALUES(business_trip_minutes),
                sign_in_time=VALUES(sign_in_time), sign_out_time=VALUES(sign_out_time),
                sign_in_status=VALUES(sign_in_status), sign_out_status=VALUES(sign_out_status),
                late_minutes=VALUES(late_minutes), early_leave_minutes=VALUES(early_leave_minutes),
                absent_minutes=VALUES(absent_minutes), source_file=VALUES(source_file)
        ");

        $insertEmpStmt = $this->db->prepare("
            INSERT IGNORE INTO attendance_employees (moa_name, moa_employee_no, moa_dept, user_id)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($parsedRows as $r) {
            $stats['total']++;
            $date = self::parseDay($r['day'], $yearHint);
            if (!$date) { $stats['skipped']++; continue; }

            // 員工對應：先查既有 mapping，再 fallback 用 hswork users 姓名匹配
            $uid = null;
            if (array_key_exists($r['name'], $nameToUid)) {
                $uid = $nameToUid[$r['name']] ? (int)$nameToUid[$r['name']] : null;
                // 之前未對應、現在 hswork 有同名員工 → 自動補
                if ($uid === null && isset($userByName[$r['name']])) {
                    $uid = $userByName[$r['name']];
                    $this->db->prepare("UPDATE attendance_employees SET user_id = ? WHERE moa_name = ?")
                             ->execute(array($uid, $r['name']));
                    $nameToUid[$r['name']] = $uid;
                }
            } else {
                if (isset($userByName[$r['name']])) {
                    $uid = $userByName[$r['name']];
                }
                // 自動建立對照（即使沒對到 user 也記下 MOA 員工，方便後台手動補）
                $insertEmpStmt->execute(array($r['name'], $r['employee_no'] ?: null, $r['dept'] ?: null, $uid));
                $nameToUid[$r['name']] = $uid;
            }
            if ($uid === null) $stats['unmatched']++;

            list($signIn, $signInStatus) = self::parseSignTime($r['sign_in']);
            list($signOut, $signOutStatus) = self::parseSignTime($r['sign_out']);

            $isAbnormal = (trim((string)$r['is_abnormal']) === '是') ? 1 : 0;
            $hasApp     = (trim((string)$r['has_application']) === '是') ? 1 : 0;

            // 檢查既有列數量以區分 inserted vs updated
            $existsStmt = $this->db->prepare("SELECT 1 FROM attendance_records WHERE moa_name = ? AND work_date = ? LIMIT 1");
            $existsStmt->execute(array($r['name'], $date));
            $isUpdate = (bool)$existsStmt->fetchColumn();

            $upsert->execute(array(
                $uid, $r['name'], $r['employee_no'] ?: null, $r['dept'] ?: null, $date, $r['weekday'] ?: null,
                $isAbnormal, $hasApp,
                self::hmmToMinutes($r['expected']),
                self::hmmToMinutes($r['actual']),
                self::hmmToMinutes($r['extra']),
                self::hmmToMinutes($r['comp_off']),
                self::hmmToMinutes($r['business_trip']),
                $signIn, $signOut, $signInStatus, $signOutStatus,
                self::hmmToMinutes($r['late']),
                self::hmmToMinutes($r['early_leave']),
                self::hmmToMinutes($r['absent']),
                $sourceFile,
            ));

            if ($isUpdate) $stats['updated']++; else $stats['inserted']++;
            if ($stats['date_from'] === null || $date < $stats['date_from']) $stats['date_from'] = $date;
            if ($stats['date_to']   === null || $date > $stats['date_to'])   $stats['date_to']   = $date;
        }

        // 寫一筆匯入紀錄
        $logStmt = $this->db->prepare("
            INSERT INTO attendance_imports
                (file_name, date_from, date_to, total_rows, inserted_rows, updated_rows, unmatched_count, imported_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $logStmt->execute(array(
            $sourceFile, $stats['date_from'], $stats['date_to'],
            $stats['total'], $stats['inserted'], $stats['updated'], $stats['unmatched'],
            Auth::id(),
        ));

        return $stats;
    }

    /**
     * 查詢出勤明細
     */
    public function getRecords(array $filters = array(), $limit = 500)
    {
        $where = '1=1'; $params = array();
        if (!empty($filters['date_from'])) { $where .= ' AND ar.work_date >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to']))   { $where .= ' AND ar.work_date <= ?'; $params[] = $filters['date_to']; }
        if (!empty($filters['name']))      { $where .= ' AND ar.moa_name = ?';   $params[] = $filters['name']; }
        if (!empty($filters['dept']))      { $where .= ' AND ar.moa_dept = ?';   $params[] = $filters['dept']; }
        if (!empty($filters['only_abnormal'])) { $where .= ' AND ar.is_abnormal = 1'; }
        if (!empty($filters['unmatched']))     { $where .= ' AND ar.user_id IS NULL'; }

        $sql = "SELECT ar.*, u.real_name AS hswork_name
                FROM attendance_records ar
                LEFT JOIN users u ON ar.user_id = u.id
                WHERE $where
                ORDER BY ar.work_date DESC, ar.moa_dept, ar.moa_name
                LIMIT " . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployees()
    {
        $sql = "SELECT ae.*, u.real_name AS hswork_name
                FROM attendance_employees ae
                LEFT JOIN users u ON ae.user_id = u.id
                ORDER BY ae.moa_dept, ae.moa_name";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartments()
    {
        return $this->db->query("SELECT DISTINCT moa_dept FROM attendance_records WHERE moa_dept IS NOT NULL AND moa_dept != '' ORDER BY moa_dept")
                        ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function setEmployeeMapping($empId, $userId)
    {
        $stmt = $this->db->prepare("UPDATE attendance_employees SET user_id = ? WHERE id = ?");
        $stmt->execute(array($userId ?: null, $empId));
        // 同步既有 attendance_records 的 user_id
        $infoStmt = $this->db->prepare("SELECT moa_name FROM attendance_employees WHERE id = ?");
        $infoStmt->execute(array($empId));
        $name = $infoStmt->fetchColumn();
        if ($name) {
            $up = $this->db->prepare("UPDATE attendance_records SET user_id = ? WHERE moa_name = ?");
            $up->execute(array($userId ?: null, $name));
        }
    }

    public function getImportLogs($limit = 50)
    {
        $sql = "SELECT al.*, u.real_name AS importer_name
                FROM attendance_imports al
                LEFT JOIN users u ON al.imported_by = u.id
                ORDER BY al.imported_at DESC
                LIMIT " . (int)$limit;
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ===== MOA API 同步 =====

    public function getSettings()
    {
        $row = $this->db->query("SELECT * FROM attendance_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->db->exec("INSERT INTO attendance_settings (id, moa_company_id, moa_org_id) VALUES (1, 4545, 200021)");
            $row = $this->db->query("SELECT * FROM attendance_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        }
        return $row;
    }

    public function saveSettings($companyId, $orgId, $cookie = null)
    {
        if ($cookie !== null) {
            $stmt = $this->db->prepare("UPDATE attendance_settings SET moa_company_id = ?, moa_org_id = ?, moa_cookie = ?, cookie_set_at = NOW(), cookie_set_by = ? WHERE id = 1");
            $stmt->execute(array((int)$companyId, (int)$orgId, $cookie, Auth::id()));
        } else {
            $stmt = $this->db->prepare("UPDATE attendance_settings SET moa_company_id = ?, moa_org_id = ? WHERE id = 1");
            $stmt->execute(array((int)$companyId, (int)$orgId));
        }
    }

    /**
     * 呼叫 MOA API
     * @return array ['ok'=>bool, 'data'=>mixed, 'error'=>string]
     */
    public function moaCall($endpoint, array $payload)
    {
        $s = $this->getSettings();
        if (empty($s['moa_cookie'])) {
            return array('ok'=>false, 'error'=>'尚未設定 MOA Cookie，請至「同步設定」頁貼上 cookie');
        }
        $url = 'https://moa.micito.net' . $endpoint;
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Cookie: ' . $s['moa_cookie'],
                'Origin: https://moa.micito.net',
                'Referer: https://moa.micito.net/manage/',
                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            return array('ok'=>false, 'error'=>'CURL 失敗: ' . $err);
        }
        if ($httpCode !== 200) {
            return array('ok'=>false, 'error'=>'HTTP ' . $httpCode . '：' . substr($body, 0, 200));
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return array('ok'=>false, 'error'=>'回傳非 JSON：' . substr($body, 0, 200));
        }
        if (isset($data['code']) && $data['code'] !== '0' && $data['code'] !== 0) {
            // 305 通常代表未登入或 cookie 過期
            return array('ok'=>false, 'error'=>'MOA 回傳 code=' . $data['code'] . '（' . ($data['message'] ?? '') . '）— cookie 可能已過期，請重設');
        }
        return array('ok'=>true, 'data'=>$data);
    }

    /**
     * 同步打卡資料：抓員工列表 + 每位員工的 work/record，組成 簽到/簽退 寫入
     * @return array stats
     */
    public function syncFromApi($dateFrom, $dateTo)
    {
        $stats = array('ok'=>false, 'employees'=>0, 'days'=>0, 'records'=>0, 'inserted'=>0, 'updated'=>0, 'unmatched'=>0, 'errors'=>array());
        $s = $this->getSettings();
        $companyId = (int)$s['moa_company_id'];

        // 1) 員工列表
        $userListResp = $this->moaCall('/kaoqin/' . (int)$s['moa_org_id'] . '/web/hr/userList',
                                       array('id'=>$companyId, 'filter'=>1, 'count'=>500));
        if (!$userListResp['ok']) {
            $stats['errors'][] = '取員工失敗：' . $userListResp['error'];
            $this->writeSyncLog('failed', '員工列表：' . $userListResp['error']);
            return $stats;
        }
        $users = $userListResp['data']['list'] ?? array();
        $stats['employees'] = count($users);

        // 2) 預先撈 hswork 對照
        $hsUsersStmt = $this->db->query("SELECT id, real_name FROM users WHERE is_active = 1");
        $hsByName = array();
        foreach ($hsUsersStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            if ($u['real_name']) $hsByName[$u['real_name']] = (int)$u['id'];
        }
        $existingMapStmt = $this->db->query("SELECT moa_name, user_id FROM attendance_employees");
        $existingMap = array();
        foreach ($existingMapStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $existingMap[$m['moa_name']] = $m['user_id'];
        }

        // 3) 對員工 upsert attendance_employees
        $upsertEmp = $this->db->prepare("
            INSERT INTO attendance_employees (moa_name, moa_employee_no, moa_user_id, moa_dept, user_id)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                moa_employee_no = VALUES(moa_employee_no),
                moa_user_id = VALUES(moa_user_id),
                moa_dept = VALUES(moa_dept),
                user_id = COALESCE(attendance_employees.user_id, VALUES(user_id))
        ");
        foreach ($users as $u) {
            $name = $u['name'] ?? '';
            if (!$name) continue;
            $uid = isset($existingMap[$name]) && $existingMap[$name] ? (int)$existingMap[$name] : null;
            if ($uid === null && isset($hsByName[$name])) $uid = $hsByName[$name];
            if ($uid === null) $stats['unmatched']++;
            $upsertEmp->execute(array(
                $name,
                isset($u['userNo']) ? (string)$u['userNo'] : null,
                isset($u['userId']) ? (int)$u['userId'] : null,
                $u['deptName'] ?? null,
                $uid,
            ));
            $existingMap[$name] = $uid;
        }

        // 4) 對每位員工抓打卡記錄（指定日期區間）
        $startTs = strtotime($dateFrom . ' 00:00:00');
        $endTs   = strtotime($dateTo . ' 23:59:59');
        if (!$startTs || !$endTs) {
            $stats['errors'][] = '日期格式錯誤';
            $this->writeSyncLog('failed', '日期格式錯誤');
            return $stats;
        }

        $upsertRec = $this->db->prepare("
            INSERT INTO attendance_records
                (user_id, moa_name, moa_employee_no, moa_dept, work_date, weekday,
                 sign_in_time, sign_out_time, sign_in_status, sign_out_status,
                 sync_source, source_file, imported_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'api', NULL, NOW())
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                moa_employee_no = VALUES(moa_employee_no),
                moa_dept = VALUES(moa_dept),
                weekday = VALUES(weekday),
                sign_in_time = VALUES(sign_in_time),
                sign_out_time = VALUES(sign_out_time),
                sign_in_status = VALUES(sign_in_status),
                sign_out_status = VALUES(sign_out_status),
                sync_source = 'api',
                updated_at = NOW()
        ");

        $existsRec = $this->db->prepare("SELECT 1 FROM attendance_records WHERE moa_name = ? AND work_date = ? LIMIT 1");

        foreach ($users as $u) {
            $name = $u['name'] ?? '';
            $userId = $u['userId'] ?? null;
            if (!$name || !$userId) continue;

            $resp = $this->moaCall('/kaoqin/' . (int)$s['moa_org_id'] . '/web/work/record',
                                   array('id'=>$companyId, 'userId'=>(string)$userId, 'startDate'=>$startTs, 'endDate'=>$endTs, 'page'=>0));
            if (!$resp['ok']) {
                $stats['errors'][] = $name . '：' . $resp['error'];
                continue;
            }
            $list = $resp['data']['list'] ?? array();
            $stats['records'] += count($list);
            // 依日期分組，找最早/最晚當作 簽到/簽退
            $byDay = array();
            foreach ($list as $rec) {
                $tsDay = (int)$rec['date'];
                $secInDay = (int)$rec['time'];
                $localDate = date('Y-m-d', $tsDay + 8 * 3600); // MOA 日期欄位是 UTC midnight，台灣時區 +8
                if (!isset($byDay[$localDate])) $byDay[$localDate] = array();
                $byDay[$localDate][] = $secInDay;
            }
            foreach ($byDay as $date => $seconds) {
                sort($seconds);
                $minSec = $seconds[0];
                $maxSec = end($seconds);
                $signIn  = sprintf('%02d:%02d:%02d', floor($minSec/3600), floor($minSec/60)%60, $minSec%60);
                $signOut = ($minSec === $maxSec) ? null
                          : sprintf('%02d:%02d:%02d', floor($maxSec/3600), floor($maxSec/60)%60, $maxSec%60);
                $existsRec->execute(array($name, $date));
                $isUpdate = (bool)$existsRec->fetchColumn();

                $weekdays = array('日','一','二','三','四','五','六');
                $weekday = '周' . $weekdays[(int)date('w', strtotime($date))];

                $upsertRec->execute(array(
                    isset($existingMap[$name]) ? $existingMap[$name] : null,
                    $name,
                    isset($u['userNo']) ? (string)$u['userNo'] : null,
                    $u['deptName'] ?? null,
                    $date,
                    $weekday,
                    $signIn,
                    $signOut,
                    '正常',
                    $signOut === null ? '只有單筆打卡' : '正常',
                ));
                if ($isUpdate) $stats['updated']++; else $stats['inserted']++;
                $stats['days']++;
            }
        }

        $stats['ok'] = true;
        $msg = "員工 {$stats['employees']} 人 / 打卡 {$stats['records']} 筆 / 寫入 {$stats['days']} 員工日（新 {$stats['inserted']} 更新 {$stats['updated']}）";
        if ($stats['unmatched'] > 0) $msg .= " / 未對應姓名 {$stats['unmatched']}";
        if (count($stats['errors']) > 0) {
            $msg .= " / 錯誤 " . count($stats['errors']) . " 筆";
        }
        $this->writeSyncLog('success', $msg, $stats['employees'], $stats['days']);
        return $stats;
    }

    private function writeSyncLog($status, $message, $emps = 0, $records = 0)
    {
        $stmt = $this->db->prepare("UPDATE attendance_settings SET last_sync_at = NOW(), last_sync_status = ?, last_sync_message = ?, last_sync_employees = ?, last_sync_records = ? WHERE id = 1");
        $stmt->execute(array($status, $message, $emps, $records));
    }
}
