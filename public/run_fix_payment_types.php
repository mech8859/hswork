<?php
/**
 * 帳款交易整理腳本
 * 將混合在 transaction_type 中的「類別+方式」拆分到正確欄位
 *
 * 用法：
 *   ?case=2026-1643    ← 只處理單筆案件（測試用）
 *   ?mode=preview      ← 預覽模式，不實際修改（預設）
 *   ?mode=execute      ← 實際執行修改
 *   ?case=2026-1643&mode=execute  ← 執行單筆
 *   ?mode=execute      ← 全部執行
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');

$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

$targetCase = isset($_GET['case']) ? trim($_GET['case']) : '';
$mode = isset($_GET['mode']) ? trim($_GET['mode']) : 'preview';

// 帳款類別（什麼款項）
$TYPES = array('訂金', '定金', '尾款', '全款', '全額', '工程款', '查修費用', '出勤費', '前期請款', '中期請款', '初驗款');
// 交易方式（怎麼付的）
$METHODS = array('匯款', '現金', '支票', '刷卡', '轉帳');

/**
 * 解析交易內容，拆分為 payment_type 和 transaction_type
 */
function parseTransaction($category, $content) {
    global $TYPES, $METHODS;

    $content = trim(str_replace(array("\r\n", "\n", "\r"), '', $content));
    $foundType = null;
    $foundMethod = null;

    // 如果 category 已有值，只需從 content 提取 method
    if (!empty($category)) {
        foreach ($METHODS as $m) {
            if (mb_strpos($content, $m) !== false) {
                $foundMethod = $m;
                break;
            }
        }
        return array($category, $foundMethod ? $foundMethod : $content);
    }

    // category 為空，需要從 content 拆分
    if (empty($content)) {
        return array(null, null);
    }

    // 特殊處理：百分比開頭（30%訂金）
    if (preg_match('/^\d+%/', $content)) {
        foreach ($TYPES as $t) {
            if (mb_strpos($content, $t) !== false) {
                $foundType = ($t === '定金') ? '訂金' : $t;
                break;
            }
        }
        if (!$foundType) $foundType = '訂金'; // 百分比通常是訂金
        foreach ($METHODS as $m) {
            if (mb_strpos($content, $m) !== false) {
                $foundMethod = $m;
                break;
            }
        }
        return array($foundType, $foundMethod);
    }

    // 特殊處理：現簽 = 現金
    if (mb_strpos($content, '現簽') !== false) {
        $foundMethod = '現金';
        foreach ($TYPES as $t) {
            if ($t !== '現簽' && mb_strpos($content, $t) !== false) {
                $foundType = ($t === '定金') ? '訂金' : $t;
                break;
            }
        }
        return array($foundType, $foundMethod);
    }

    // 一般拆分：找 type 和 method
    foreach ($TYPES as $t) {
        if (mb_strpos($content, $t) !== false) {
            $foundType = ($t === '定金' || $t === '全額') ? '訂金' : $t;
            if ($t === '全額') $foundType = '全款';
            break;
        }
    }
    foreach ($METHODS as $m) {
        if (mb_strpos($content, $m) !== false) {
            $foundMethod = $m;
            break;
        }
    }

    // 如果都沒找到，保留原值不動
    if (!$foundType && !$foundMethod) {
        return array(null, $content);
    }

    return array($foundType, $foundMethod);
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>帳款交易整理</title>';
echo '<style>body{font-family:sans-serif;padding:16px;max-width:1200px;margin:0 auto} table{border-collapse:collapse;width:100%} th,td{border:1px solid #ddd;padding:6px 10px;text-align:left;font-size:13px} th{background:#f5f5f5} .changed{background:#e8f5e9} .skip{background:#fff8e1} .err{background:#ffebee} .badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:12px} .b-green{background:#c8e6c9;color:#2e7d32} .b-orange{background:#ffe0b2;color:#e65100} .b-red{background:#ffcdd2;color:#c62828} h2{margin-bottom:4px}</style>';
echo '</head><body>';
echo '<h2>帳款交易整理' . ($mode === 'execute' ? ' <span class="badge b-green">執行模式</span>' : ' <span class="badge b-orange">預覽模式</span>') . '</h2>';
if ($targetCase) echo '<p>目標案件: <b>' . htmlspecialchars($targetCase) . '</b></p>';
echo '<p><a href="?mode=preview">預覽全部</a> | <a href="?mode=execute" onclick="return confirm(\'確定要執行全部修改？\')">執行全部</a></p>';

// 查詢帳款交易
$sql = 'SELECT cp.id, cp.case_id, cp.payment_date, cp.payment_type, cp.transaction_type, cp.amount, cp.ragic_id, c.case_number
        FROM case_payments cp
        JOIN cases c ON cp.case_id = c.id';
$params = array();
if ($targetCase) {
    $sql .= ' WHERE c.case_number = ?';
    $params[] = $targetCase;
}
$sql .= ' ORDER BY c.case_number DESC, cp.payment_date';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

echo '<p>共 ' . count($rows) . ' 筆帳款交易</p>';

echo '<table><tr><th>案件</th><th>日期</th><th>金額</th><th>原類別</th><th>原方式</th><th>→ 新類別</th><th>→ 新方式</th><th>狀態</th></tr>';

$countChanged = 0;
$countSkipped = 0;
$countError = 0;

foreach ($rows as $row) {
    $origType = $row['payment_type'];
    $origMethod = $row['transaction_type'];

    list($newType, $newMethod) = parseTransaction($origType, $origMethod);

    // 判斷是否需要修改
    $needUpdate = false;
    $finalType = $origType;
    $finalMethod = $origMethod;

    // 如果原本 payment_type 為空，且解析出了 type
    if (empty($origType) && !empty($newType)) {
        $finalType = $newType;
        $needUpdate = true;
    }

    // 如果 transaction_type 可以被清理成純方式
    if (!empty($newMethod) && $newMethod !== $origMethod) {
        $finalMethod = $newMethod;
        $needUpdate = true;
    }

    if ($needUpdate) {
        $countChanged++;
        $class = 'changed';
        $status = '<span class="badge b-green">修改</span>';

        if ($mode === 'execute') {
            try {
                $upd = $db->prepare('UPDATE case_payments SET payment_type = ?, transaction_type = ? WHERE id = ?');
                $upd->execute(array($finalType, $finalMethod, $row['id']));
                $status = '<span class="badge b-green">已修改</span>';

                // 更新案件的 deposit_amount / deposit_method
                updateTotalCollected($row['case_id'], $db);
            } catch (Exception $e) {
                $status = '<span class="badge b-red">失敗: ' . htmlspecialchars($e->getMessage()) . '</span>';
                $countError++;
            }
        }
    } else {
        $countSkipped++;
        $class = 'skip';
        $status = '<span class="badge b-orange">不變</span>';
    }

    echo '<tr class="' . $class . '">';
    echo '<td>' . htmlspecialchars($row['case_number']) . '</td>';
    echo '<td>' . htmlspecialchars($row['payment_date']) . '</td>';
    echo '<td style="text-align:right">$' . number_format($row['amount']) . '</td>';
    echo '<td>' . htmlspecialchars($origType ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars($origMethod ?: '-') . '</td>';
    echo '<td><b>' . htmlspecialchars($finalType ?: '-') . '</b></td>';
    echo '<td><b>' . htmlspecialchars($finalMethod ?: '-') . '</b></td>';
    echo '<td>' . $status . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '<h3>統計</h3>';
echo '<p>需修改: <b>' . $countChanged . '</b> 筆 | 不需修改: <b>' . $countSkipped . '</b> 筆';
if ($countError) echo ' | <span class="badge b-red">失敗: ' . $countError . '</span>';
echo '</p>';
echo '</body></html>';

/**
 * 更新案件的 total_collected / deposit_amount / deposit_method
 */
function updateTotalCollected($caseId, $db) {
    $stmt = $db->prepare('SELECT SUM(amount) as total FROM case_payments WHERE case_id = ?');
    $stmt->execute(array($caseId));
    $total = $stmt->fetchColumn() ?: 0;

    $stmt = $db->prepare('SELECT SUM(amount) as dep FROM case_payments WHERE case_id = ? AND payment_type = ?');
    $stmt->execute(array($caseId, '訂金'));
    $deposit = $stmt->fetchColumn() ?: 0;

    $stmt = $db->prepare('SELECT transaction_type, payment_date FROM case_payments WHERE case_id = ? AND payment_type = ? ORDER BY payment_date DESC LIMIT 1');
    $stmt->execute(array($caseId, '訂金'));
    $latest = $stmt->fetch();

    $depMethod = $latest ? $latest['transaction_type'] : null;
    $depDate = $latest ? $latest['payment_date'] : null;

    $upd = $db->prepare('UPDATE cases SET total_collected = ?, deposit_amount = ?, deposit_method = ?, deposit_payment_date = ? WHERE id = ?');
    $upd->execute(array($total, $deposit, $depMethod, $depDate, $caseId));
}
