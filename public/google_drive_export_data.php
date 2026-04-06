<?php
/**
 * 匯出客戶+案件資料為 CSV，上傳到 Google Drive
 * 用法:
 *   ?type=customers   ← 匯出客戶
 *   ?type=cases       ← 匯出案件
 *   ?type=all         ← 全部匯出（預設）
 *   &download=1       ← 下載到本機（不上傳 Drive）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
// 支援 key 認證（自動排程用）或登入認證
$autoKey = isset($_GET['key']) ? $_GET['key'] : '';
if ($autoKey === 'hswork_backup_2026_secret') {
    // 自動排程，免登入
} else {
    Auth::requireLogin();
    if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
}

require_once __DIR__ . '/../includes/GoogleDrive.php';
@set_time_limit(300);
@ini_set('memory_limit', '256M');

$db = Database::getInstance();
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$download = !empty($_GET['download']);
$date = date('Y-m-d');
$exportDir = __DIR__ . '/../data/exports';
if (!is_dir($exportDir)) mkdir($exportDir, 0755, true);

$foldersFile = __DIR__ . '/../data/google_drive_folders.json';
$folders = file_exists($foldersFile) ? json_decode(file_get_contents($foldersFile), true) : null;

if (!$download) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre>';
    echo "=== 資料匯出到 Google Drive ===\n";
    echo "日期: {$date}\n\n";
}

// ========== 客戶資料 ==========
if ($type === 'customers' || $type === 'all') {
    $file = $exportDir . "/customers_{$date}.csv";

    $customerHeaders = array(
        'customer_no' => '客戶編號',
        'name' => '客戶名稱',
        'category' => '客戶分類',
        'contact_person' => '聯絡人',
        'phone' => '市話',
        'mobile' => '手機',
        'email' => 'Email',
        'fax' => '傳真',
        'line_id' => 'LINE ID',
        'tax_id' => '統一編號',
        'site_address' => '施工地址',
        'site_city' => '施工縣市',
        'site_district' => '施工區域',
        'billing_address' => '帳單地址',
        'invoice_title' => '發票抬頭',
        'invoice_email' => '發票Email',
        'payment_method' => '付款方式',
        'payment_terms' => '付款條件',
        'case_number' => '進件編號',
        'source_company' => '進件分公司',
        'original_customer_no' => '原客戶編號',
        'legacy_customer_no' => '舊系統編號',
        'salesperson_name' => '業務姓名',
        'completion_date' => '完工日期',
        'warranty_date' => '保固日期',
        'warranty_note' => '保固備註',
        'note' => '備註',
        'is_active' => '狀態',
        'is_blacklisted' => '黑名單',
        'blacklist_reason' => '黑名單原因',
        'created_at' => '建立日期',
        'updated_at' => '修改日期',
    );

    $cols = array_keys($customerHeaders);
    $stmt = $db->query("SELECT " . implode(',', $cols) . " FROM customers ORDER BY customer_no");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($download) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers_' . $date . '.csv"');
        $fp = fopen('php://output', 'w');
    } else {
        $fp = fopen($file, 'w');
    }

    // BOM for Excel UTF-8
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, array_values($customerHeaders));
    foreach ($rows as $row) {
        $row['is_active'] = $row['is_active'] ? '啟用' : '停用';
        $row['is_blacklisted'] = $row['is_blacklisted'] ? '是' : '否';
        fputcsv($fp, array_values($row));
    }

    if ($download) {
        fclose($fp);
        exit;
    }
    fclose($fp);

    $size = round(filesize($file) / 1024, 1);
    echo "客戶: " . count($rows) . " 筆, {$size} KB\n";

    // 上傳到 Drive
    if ($folders) {
        $drive = new GoogleDrive();
        try {
            // 找或建 exports 子資料夾
            $exportFolderId = null;
            $list = $drive->listFiles($folders['root']);
            foreach ($list as $f) {
                if ($f['name'] === 'exports' && (!empty($f['mimeType']) && strpos($f['mimeType'], 'folder') !== false)) {
                    $exportFolderId = $f['id'];
                    break;
                }
            }
            if (!$exportFolderId) {
                $exportFolderId = $drive->createFolder('exports', $folders['root']);
            }
            $result = $drive->uploadFile($file, basename($file), $exportFolderId);
            echo "  → Drive 上傳成功 (ID: {$result['id']})\n";
        } catch (Exception $e) {
            echo "  → Drive 上傳失敗: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}

// ========== 案件資料 ==========
if ($type === 'cases' || $type === 'all') {
    $file = $exportDir . "/cases_{$date}.csv";

    $caseHeaders = array(
        'case_number' => '進件編號',
        'title' => '案件名稱',
        'branch_name' => '所屬分公司',
        'case_type' => '案別',
        'status' => '案件進度',
        'sub_status' => '狀態',
        'customer_no' => '客戶編號',
        'customer_name' => '客戶名稱',
        'customer_category' => '客戶分類',
        'contact_person' => '聯絡人',
        'customer_phone' => '市話',
        'customer_mobile' => '手機',
        'contact_line_id' => 'LINE ID',
        'address' => '施工地址',
        'construction_area' => '施工區域',
        'system_type' => '系統別',
        'description' => '案件說明',
        'company' => '進件公司',
        'case_source' => '案件來源',
        'sales_name' => '業務',
        'difficulty' => '難易度',
        'urgency' => '急迫性',
        'estimated_hours' => '預估工時',
        'total_visits' => '預估施工次數',
        'is_large_project' => '大型案件',
        'quote_amount' => '報價金額',
        'deal_date' => '成交日期',
        'deal_amount' => '成交金額(未稅)',
        'is_tax_included' => '含稅',
        'tax_amount' => '稅金',
        'total_amount' => '含稅金額',
        'deposit_amount' => '訂金',
        'deposit_method' => '訂金方式',
        'deposit_payment_date' => '訂金日期',
        'balance_amount' => '尾款',
        'completion_amount' => '完工金額',
        'total_collected' => '總收款金額',
        'planned_start_date' => '預計施工日',
        'planned_end_date' => '預計完工日',
        'completion_date' => '完工日期',
        'survey_date' => '場勘日期',
        'is_completed' => '已完工',
        'notes' => '備註',
        'created_at' => '建立日期',
        'updated_at' => '修改日期',
    );

    $caseTypeMap = array('new_install'=>'新案','addition'=>'追加','old_repair'=>'舊客戶維修','new_repair'=>'新客戶維修','maintenance'=>'維護保養','other'=>'其他');
    $progressMap = array('tracking'=>'待追蹤','incomplete'=>'未完工','unpaid'=>'完工未收款','completed_pending'=>'已完工待簽核','closed'=>'已完工結案','lost'=>'未成交','maint_case'=>'保養案件','breach'=>'毀約','scheduled'=>'已排工','needs_reschedule'=>'需再安排','awaiting_dispatch'=>'待派工查修','customer_cancel'=>'客戶取消');

    $stmt = $db->query("
        SELECT c.case_number, c.title, b.name as branch_name, c.case_type, c.status, c.sub_status,
               c.customer_no, c.customer_name, c.customer_category,
               c.contact_person, c.customer_phone, c.customer_mobile, c.contact_line_id,
               c.address, c.construction_area, c.system_type, c.description,
               c.company, c.case_source, u.real_name as sales_name,
               c.difficulty, c.urgency, c.estimated_hours, c.total_visits, c.is_large_project,
               c.quote_amount, c.deal_date, c.deal_amount, c.is_tax_included,
               c.tax_amount, c.total_amount,
               c.deposit_amount, c.deposit_method, c.deposit_payment_date,
               c.balance_amount, c.completion_amount, c.total_collected,
               c.planned_start_date, c.planned_end_date, c.completion_date, c.survey_date,
               c.is_completed, c.notes, c.created_at, c.updated_at
        FROM cases c
        LEFT JOIN branches b ON c.branch_id = b.id
        LEFT JOIN users u ON c.sales_id = u.id
        ORDER BY c.case_number DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($download) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cases_' . $date . '.csv"');
        $fp = fopen('php://output', 'w');
    } else {
        $fp = fopen($file, 'w');
    }

    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, array_values($caseHeaders));
    foreach ($rows as $row) {
        $row['case_type'] = isset($caseTypeMap[$row['case_type']]) ? $caseTypeMap[$row['case_type']] : $row['case_type'];
        $row['status'] = isset($progressMap[$row['status']]) ? $progressMap[$row['status']] : $row['status'];
        $row['is_large_project'] = $row['is_large_project'] ? '是' : '否';
        $row['is_completed'] = $row['is_completed'] ? '是' : '否';
        fputcsv($fp, array_values($row));
    }

    if ($download) {
        fclose($fp);
        exit;
    }
    fclose($fp);

    $size = round(filesize($file) / 1024, 1);
    echo "案件: " . count($rows) . " 筆, {$size} KB\n";

    if ($folders) {
        $drive = new GoogleDrive();
        try {
            $exportFolderId = null;
            $list = $drive->listFiles($folders['root']);
            foreach ($list as $f) {
                if ($f['name'] === 'exports' && (!empty($f['mimeType']) && strpos($f['mimeType'], 'folder') !== false)) {
                    $exportFolderId = $f['id'];
                    break;
                }
            }
            if (!$exportFolderId) {
                $exportFolderId = $drive->createFolder('exports', $folders['root']);
            }
            $result = $drive->uploadFile($file, basename($file), $exportFolderId);
            echo "  → Drive 上傳成功 (ID: {$result['id']})\n";
        } catch (Exception $e) {
            echo "  → Drive 上傳失敗: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}

if (!$download) {
    echo "=== 完成！===\n";
    echo "檔案位置: Google Drive > hswork > exports\n";
    echo '</pre>';
}
