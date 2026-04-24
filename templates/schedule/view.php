<?php
$statusLabels = array('planned'=>'已規劃','confirmed'=>'已確認','in_progress'=>'施工中','completed'=>'已完工','cancelled'=>'已取消');
$statusBadge = array('planned'=>'primary','confirmed'=>'info','in_progress'=>'warning','completed'=>'success','cancelled'=>'danger');

// 取得案件相關資訊
$caseNote = '';
$casePhotos = array();
$caseDrawings = array();
$customerPhone = '';
$customerMobile = '';
$customerContact = '';
$caseQuote = null;
if ($schedule['case_id']) {
    $db = Database::getInstance();
    $nStmt = $db->prepare("
        SELECT c.notes, c.sales_note, c.description, c.customer_name, c.construction_note,
               c.planned_start_date, c.planned_end_date, c.urgency,
               c.work_time_start, c.work_time_end, c.customer_break_time,
               c.has_time_restriction, c.allow_night_work, c.is_flexible, c.is_large_project,
               cu.phone as cu_phone, cu.mobile as cu_mobile, cu.contact_person as cu_contact, cu.fax
        FROM cases c
        LEFT JOIN customers cu ON c.customer_id = cu.id
        WHERE c.id = ?
    ");
    $nStmt->execute(array($schedule['case_id']));
    $caseInfo = $nStmt->fetch(PDO::FETCH_ASSOC);
    $constructionNote = '';
    // 從 case_contacts 取全部聯絡人（案件管理填的優先，其次才是客戶資料）
    $ccStmt = $db->prepare("SELECT contact_name, contact_phone, contact_role FROM case_contacts WHERE case_id = ? ORDER BY id");
    $ccStmt->execute(array($schedule['case_id']));
    $caseContacts = $ccStmt->fetchAll(PDO::FETCH_ASSOC);
    $firstCaseContact = !empty($caseContacts) ? $caseContacts[0] : null;
    if ($caseInfo) {
        $caseNote = $caseInfo['notes'] ?: $caseInfo['description'] ?: '';
        $caseSalesNote = $caseInfo['sales_note'] ?: '';
        // 優先使用案件聯絡人（第一筆），若無才 fallback 到客戶資料
        $customerContact = $firstCaseContact ? $firstCaseContact['contact_name'] : ($caseInfo['cu_contact'] ?: '');
        $customerPhone = $firstCaseContact ? ($firstCaseContact['contact_phone'] ?: '') : ($caseInfo['cu_phone'] ?: '');
        $customerMobile = $caseInfo['cu_mobile'] ?: '';
        $constructionNote = $caseInfo['construction_note'] ?: '';
    }
    // 現場環境
    $siteStmt = $db->prepare("SELECT * FROM case_site_conditions WHERE case_id = ? LIMIT 1");
    $siteStmt->execute(array($schedule['case_id']));
    $siteCond = $siteStmt->fetch(PDO::FETCH_ASSOC);
    // 案件附件：動態抓全部分類（排除預計使用線材），每類最多 20 筆
    require_once __DIR__ . '/../../modules/cases/CaseModel.php';
    $schAttachTypes = CaseModel::attachTypeOptions();
    unset($schAttachTypes['wire_plan']);
    $schGroupedAtt = array();
    foreach ($schAttachTypes as $tk => $_) { $schGroupedAtt[$tk] = array(); }
    $allAttStmt = $db->prepare("SELECT file_type, file_path, file_name FROM case_attachments WHERE case_id = ? ORDER BY id");
    $allAttStmt->execute(array($schedule['case_id']));
    foreach ($allAttStmt->fetchAll(PDO::FETCH_ASSOC) as $att) {
        $t = $att['file_type'];
        if (!isset($schGroupedAtt[$t])) continue; // 不在分類清單（含 wire_plan）就跳過
        if (count($schGroupedAtt[$t]) >= 20) continue;
        $schGroupedAtt[$t][] = $att;
    }
    // 報價單（系統產生的）
    $qStmt = $db->prepare("SELECT quotation_number, total_amount, status FROM quotations WHERE case_id = ? ORDER BY id DESC LIMIT 1");
    $qStmt->execute(array($schedule['case_id']));
    $caseQuote = $qStmt->fetch(PDO::FETCH_ASSOC);
}
?>

<?php
// 判斷是否可參加
$currentUserId = Auth::id();
$currentUserEngIds = array_column($schedule['engineers'], 'user_id');
$isInSchedule = in_array($currentUserId, $currentUserEngIds);
$currentUser = Auth::user();
$isEngineerUser = !empty($currentUser['is_engineer']);
$canJoin = $isEngineerUser && !$isInSchedule && !in_array($schedule['status'], array('cancelled', 'completed'));
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2>排工詳情</h2>
        <span class="badge badge-<?= $statusBadge[$schedule['status']] ?? 'primary' ?>"><?= e($statusLabels[$schedule['status']] ?? $schedule['status']) ?></span>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <?php if ($canJoin): ?>
        <button type="button" class="btn btn-sm" style="background:#4CAF50;color:#fff" onclick="joinSchedule(<?= $schedule['id'] ?>)" id="joinBtn">&#x1F91D; 參加此排工</button>
        <?php endif; ?>
        <?php if ($isInSchedule): ?>
        <a href="/worklog.php?action=report&id=<?= $myWorklog['id'] ?>&from_schedule=<?= $schedule['id'] ?>" class="btn btn-sm" style="background:#FF9800;color:#fff">📝 施工回報</a>
        <?php endif; ?>
        <?php if (Auth::hasPermission('schedule.manage')): ?>
        <a href="/schedule.php?action=edit&id=<?= $schedule['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <a href="/schedule.php?action=delete&id=<?= $schedule['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
           class="btn btn-danger btn-sm" onclick="return confirm('確定刪除此排工?')">刪除</a>
        <?php endif; ?>
        <?= back_button('/schedule.php') ?>
    </div>
</div>

<!-- 排工資料 -->
<div class="card">
    <div class="card-header">排工資料</div>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">案件</span>
            <span class="detail-value"><a href="/cases.php?action=edit&id=<?= $schedule['case_id'] ?>&from=schedule"><?= e($schedule['case_number']) ?> - <?= e($schedule['case_title']) ?></a></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">施工日期</span>
            <span class="detail-value"><?= format_date($schedule['schedule_date']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">施工地址</span>
            <span class="detail-value"><?= e($schedule['address'] ?: '-') ?></span>
        </div>
        <?php if (!empty($schedule['address'])): ?>
        <div class="detail-item" style="grid-column: span 2">
            <span class="detail-label">地圖</span>
            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($schedule['address']) ?>" target="_blank" style="font-size:.8rem;margin-bottom:4px">Google 地圖 ↗</a>
            <iframe src="https://maps.google.com/maps?q=<?= urlencode($schedule['address']) ?>&output=embed&hl=zh-TW" style="width:100%;max-width:480px;height:200px;border:1px solid var(--gray-200);border-radius:6px" allowfullscreen loading="lazy"></iframe>
        </div>
        <?php endif; ?>
        <div class="detail-item">
            <span class="detail-label">第幾次施工</span>
            <span class="detail-value"><?= $schedule['visit_number'] ?> / <?= $schedule['total_visits'] ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">車輛</span>
            <span class="detail-value"><?= $schedule['plate_number'] ? e($schedule['plate_number']) . ' (' . e($schedule['vehicle_type']) . ')' : '未指派' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">據點</span>
            <span class="detail-value"><?= e($schedule['branch_name']) ?></span>
        </div>
    </div>
    <?php if ($schedule['note']): ?>
    <div style="padding:8px 12px"><span class="detail-label">備註</span><p style="margin:2px 0"><?= nl2br(e($schedule['note'])) ?></p></div>
    <?php endif; ?>
</div>

<!-- 客戶/聯絡資訊 -->
<div class="card">
    <div class="card-header">客戶資訊</div>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">客戶名稱</span>
            <span class="detail-value"><?= e($schedule['case_title'] ?: '-') ?></span>
        </div>
        <?php if (!empty($caseContacts)): ?>
        <?php foreach ($caseContacts as $i => $cc): ?>
        <div class="detail-item">
            <span class="detail-label">聯絡人<?= count($caseContacts) > 1 ? ' ' . ($i + 1) : '' ?><?= !empty($cc['contact_role']) ? ' (' . e($cc['contact_role']) . ')' : '' ?></span>
            <span class="detail-value">
                <?= e($cc['contact_name']) ?>
                <?php if (!empty($cc['contact_phone'])): ?>
                <a href="tel:<?= e($cc['contact_phone']) ?>" style="margin-left:8px"><?= e($cc['contact_phone']) ?></a>
                <?php endif; ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="detail-item">
            <span class="detail-label">聯絡人</span>
            <span class="detail-value"><?= e($customerContact ?: '-') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">電話</span>
            <span class="detail-value"><?php if ($customerPhone): ?><a href="tel:<?= e($customerPhone) ?>"><?= e($customerPhone) ?></a><?php else: ?>-<?php endif; ?></span>
        </div>
        <?php endif; ?>
        <?php if ($customerMobile): ?>
        <div class="detail-item">
            <span class="detail-label">客戶手機</span>
            <span class="detail-value"><a href="tel:<?= e($customerMobile) ?>"><?= e($customerMobile) ?></a></span>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($caseNote)): ?>
    <div style="padding:8px 12px;border-top:1px solid var(--gray-100)">
        <span class="detail-label">備註</span>
        <p style="margin:4px 0;white-space:pre-line"><?= nl2br(e($caseNote)) ?></p>
    </div>
    <?php endif; ?>
    <?php if (!empty($caseSalesNote)): ?>
    <div style="padding:8px 12px;border-top:1px solid var(--gray-100);background:#f5f9ff">
        <span class="detail-label">業務備註</span>
        <p style="margin:4px 0;white-space:pre-line;color:#1565c0"><?= nl2br(e($caseSalesNote)) ?></p>
    </div>
    <?php endif; ?>
    <?php if ($caseQuote): ?>
    <div style="padding:8px 12px;border-top:1px solid var(--gray-100)">
        <span class="detail-label">報價單</span>
        <span><?= e($caseQuote['quotation_number']) ?> — $<?= number_format($caseQuote['total_amount']) ?></span>
    </div>
    <?php endif; ?>
</div>

<!-- 案件附件 -->
<?php if ($schedule['case_id']): ?>
<?php
$schAttachSections = array();
foreach ($schAttachTypes as $tk => $tlabel) {
    if (!empty($schGroupedAtt[$tk])) {
        $schAttachSections[] = array('label' => $tlabel, 'files' => $schGroupedAtt[$tk]);
    }
}
$schHasAnyFile = !empty($schAttachSections);
?>
<?php if ($schHasAnyFile): ?>
<div class="card">
    <div class="card-header">案件附件</div>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding:12px">
        <?php foreach ($schAttachSections as $sec): ?>
        <?php if (!empty($sec['files'])): ?>
        <div>
            <div style="font-weight:600;font-size:.85rem;margin-bottom:6px"><?= e($sec['label']) ?> <span style="color:var(--gray-400);font-weight:400">(<?= count($sec['files']) ?>)</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                <?php foreach ($sec['files'] as $f):
                    $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
                    $isImg = in_array($ext, array('jpg','jpeg','png','gif','webp','bmp'));
                    $fpath = ltrim($f['file_path'], '/');
                ?>
                <?php if ($isImg): ?>
                <img src="/<?= e($fpath) ?>" class="sch-photo" onclick="openSchLightbox('/<?= e($fpath) ?>')" style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid var(--gray-200);cursor:pointer" alt="<?= e($f['file_name']) ?>">
                <?php else: ?>
                <a href="javascript:void(0)" onclick="openSchFile('/<?= e($fpath) ?>','<?= e($f['file_name']) ?>')" style="font-size:.8rem;color:var(--primary);text-decoration:none">📄 <?= e($f['file_name']) ?></a>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- 施工時程與條件 -->
<?php if ($caseInfo): ?>
<div class="card">
    <div class="card-header">施工時程與條件</div>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">預計施工日</span>
            <span class="detail-value"><?= !empty($caseInfo['planned_start_date']) ? format_date($caseInfo['planned_start_date']) : '-' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">預計完工日</span>
            <span class="detail-value"><?= !empty($caseInfo['planned_end_date']) ? format_date($caseInfo['planned_end_date']) : '-' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">急迫性</span>
            <span class="detail-value"><?= (int)($caseInfo['urgency'] ?? 3) ?> <?= (int)($caseInfo['urgency'] ?? 3) >= 4 ? '<span style="color:var(--danger)">(高)</span>' : '' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">施工時間</span>
            <span class="detail-value">
                <?php if (!empty($caseInfo['work_time_start']) && !empty($caseInfo['work_time_end'])): ?>
                <?= e($caseInfo['work_time_start']) ?> ~ <?= e($caseInfo['work_time_end']) ?>
                <?php else: ?>-<?php endif; ?>
            </span>
        </div>
        <?php if (!empty($caseInfo['customer_break_time'])): ?>
        <div class="detail-item">
            <span class="detail-label">客戶休息時間</span>
            <span class="detail-value"><?= e($caseInfo['customer_break_time']) ?></span>
        </div>
        <?php endif; ?>
        <div class="detail-item">
            <span class="detail-label">條件</span>
            <span class="detail-value">
                <?php
                $tags = array();
                if (!empty($caseInfo['has_time_restriction'])) $tags[] = '有施工時間限制';
                if (!empty($caseInfo['allow_night_work'])) $tags[] = '可夜間加班';
                if (!empty($caseInfo['is_flexible'])) $tags[] = '可隨時安排';
                if (!empty($caseInfo['is_large_project'])) $tags[] = '大型案件';
                echo !empty($tags) ? implode('、', $tags) : '-';
                ?>
            </span>
        </div>
    </div>
    <?php if ($constructionNote): ?>
    <div style="padding:8px 12px;border-top:1px solid var(--gray-100);background:#fff8e1">
        <span class="detail-label" style="color:#e65100">⚠ 施工注意事項</span>
        <p style="margin:4px 0;white-space:pre-line;color:#bf360c;font-weight:500"><?= nl2br(e($constructionNote)) ?></p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 施工人員 -->
<div class="card">
    <div class="card-header">施工人員</div>
    <?php if (empty($schedule['engineers'])): ?>
        <p class="text-muted" style="padding:12px">未指派工程師</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>姓名</th><th>電話</th><th>主工程師</th><th>備註</th></tr></thead>
            <tbody>
                <?php foreach ($schedule['engineers'] as $eng): ?>
                <tr>
                    <td><a href="/staff.php?action=view&id=<?= $eng['user_id'] ?>"><?= e($eng['real_name']) ?></a></td>
                    <td><a href="tel:<?= e($eng['phone'] ?? '') ?>"><?= e($eng['phone'] ?? '-') ?></a></td>
                    <td><?= $eng['is_lead'] ? '<span class="badge badge-primary">主工程師</span>' : '-' ?></td>
                    <td>
                        <?php if (!empty($eng['is_support'])): ?>
                        <span class="badge badge-success">支援</span>
                        <?php elseif ($eng['is_override']): ?>
                        <span class="badge badge-warning">強制加入</span>
                        <?php if ($eng['override_reason']): ?><br><small><?= e($eng['override_reason']) ?></small><?php endif; ?>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($schedule['dispatch_workers'])): ?>
<div class="card">
    <div class="card-header">點工人員</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>姓名</th><th>電話</th><th>所屬廠商</th></tr></thead>
            <tbody>
                <?php foreach ($schedule['dispatch_workers'] as $dw): ?>
                <tr>
                    <td><?= e($dw['name']) ?></td>
                    <td><a href="tel:<?= e($dw['phone'] ?? '') ?>"><?= e($dw['phone'] ?? '-') ?></a></td>
                    <td><?= e($dw['vendor'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 現場環境 -->
<?php if ($schedule['case_id']):
$structureLabels = array('rc' => 'RC結構', 'steel' => '鐵皮', 'open' => '空曠地', 'construction' => '建築工地');
$conduitLabels = array('pvc' => 'PVC', 'emt' => 'EMT', 'rsg' => 'RSG', 'raceway' => '壓條', 'wall' => '穿牆', 'overhead' => '架空', 'underground' => '切地埋管');
$structures = !empty($siteCond['structure_type']) ? json_decode($siteCond['structure_type'], true) : array();
if (!is_array($structures)) $structures = array();
$conduits = !empty($siteCond['conduit_type']) ? json_decode($siteCond['conduit_type'], true) : array();
if (!is_array($conduits)) $conduits = array();
?>
<div class="card">
    <div class="card-header">現場環境</div>
    <div style="padding:12px">
        <div style="margin-bottom:12px">
            <span class="detail-label">建築結構（可複選）</span>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px">
                <?php foreach ($structureLabels as $sk => $sl): ?>
                <label style="display:flex;align-items:center;gap:4px;font-size:.9rem;color:var(--gray-700)">
                    <input type="checkbox" disabled <?= in_array($sk, $structures) ? 'checked' : '' ?>> <?= e($sl) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="margin-bottom:12px">
            <span class="detail-label">管線需求（可複選）</span>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px">
                <?php foreach ($conduitLabels as $ck => $cl): ?>
                <label style="display:flex;align-items:center;gap:4px;font-size:.9rem;color:var(--gray-700)">
                    <input type="checkbox" disabled <?= in_array($ck, $conduits) ? 'checked' : '' ?>> <?= e($cl) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px">
            <div>
                <span class="detail-label">樓層數</span>
                <div style="font-size:.95rem;margin-top:2px"><?= !empty($siteCond['floor_count']) ? e($siteCond['floor_count']) : '-' ?></div>
            </div>
            <div>
                <label style="display:flex;align-items:center;gap:4px;font-size:.9rem;color:var(--gray-700);margin-top:18px">
                    <input type="checkbox" disabled <?= !empty($siteCond['has_elevator']) ? 'checked' : '' ?>> 有電梯
                </label>
            </div>
        </div>
        <div style="margin-bottom:12px">
            <span class="detail-label">特殊設備需求</span>
            <div style="display:flex;flex-direction:column;gap:6px;margin-top:6px">
                <label style="display:flex;align-items:center;gap:4px;font-size:.9rem;color:var(--gray-700)">
                    <input type="checkbox" disabled <?= !empty($siteCond['has_ladder_needed']) ? 'checked' : '' ?>> 拉梯
                    <?php if (!empty($siteCond['has_ladder_needed']) && !empty($siteCond['ladder_size'])): ?>
                    <span style="margin-left:8px;color:var(--gray-500)">(<?= e($siteCond['ladder_size']) ?>)</span>
                    <?php endif; ?>
                </label>
                <label style="display:flex;align-items:center;gap:4px;font-size:.9rem;color:var(--gray-700)">
                    <input type="checkbox" disabled <?= !empty($siteCond['high_ceiling_height']) ? 'checked' : '' ?>> 挑高場所
                    <?php if (!empty($siteCond['high_ceiling_height'])): ?>
                    <span style="margin-left:8px;color:var(--gray-500)">(<?= e($siteCond['high_ceiling_height']) ?>m)</span>
                    <?php endif; ?>
                </label>
                <label style="display:flex;align-items:center;gap:4px;font-size:.9rem;color:var(--gray-700)">
                    <input type="checkbox" disabled <?= !empty($siteCond['needs_scissor_lift']) ? 'checked' : '' ?>> 自走車
                </label>
            </div>
        </div>
        <div>
            <span class="detail-label">特殊需求</span>
            <div style="margin-top:4px;font-size:.9rem;min-height:40px;padding:8px;background:var(--gray-50);border-radius:var(--radius);color:var(--gray-700)"><?= !empty($siteCond['special_requirements']) ? nl2br(e($siteCond['special_requirements'])) : '-' ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 前次施工回報（其他日期的排工） -->
<?php if (!empty($previousVisitWorklogs)): ?>
<div class="card" style="border-left:4px solid #1565c0">
    <div class="card-header">
        <span>📚 同案件其他日期施工回報</span>
        <span class="text-muted" style="font-size:.8rem;margin-left:8px">(<?= count($previousVisitWorklogs) ?> 筆)</span>
    </div>
    <?php foreach ($previousVisitWorklogs as $pvw): ?>
    <div style="padding:12px;border-bottom:1px solid var(--gray-100);background:#f8fbff">
        <div class="d-flex justify-between align-center mb-1" style="flex-wrap:wrap;gap:6px">
            <div>
                <strong style="color:#1565c0"><?= e(date('Y-m-d', strtotime($pvw['schedule_date']))) ?></strong>
                <?php if (!empty($pvw['visit_number'])): ?>
                <span class="badge" style="background:#e3f2fd;color:#1565c0;margin-left:4px">第 <?= (int)$pvw['visit_number'] ?> 次</span>
                <?php endif; ?>
                <span style="margin-left:8px"><?= e($pvw['real_name']) ?></span>
                <?php if (!empty($pvw['is_completed'])): ?>
                <span class="badge badge-success" style="margin-left:4px">已完工</span>
                <?php endif; ?>
                <?php if (!empty($pvw['next_visit_needed'])): ?>
                <span class="badge badge-warning" style="margin-left:4px">需再施工</span>
                <?php endif; ?>
            </div>
            <a href="/schedule.php?action=view&id=<?= (int)$pvw['schedule_id'] ?>" class="btn btn-outline btn-sm" style="font-size:.75rem">前往該排工</a>
        </div>
        <div style="margin-bottom:4px"><p style="margin:2px 0;white-space:pre-line;font-size:.9rem"><?= e($pvw['work_description']) ?></p></div>
        <?php if (!empty($pvw['issues'])): ?>
        <div style="margin-bottom:4px"><span style="font-size:.8rem;color:var(--danger)">問題：</span><span style="font-size:.85rem"><?= e($pvw['issues']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($pvw['photos'])): ?>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin:4px 0">
            <?php foreach ($pvw['photos'] as $p): ?>
            <img src="<?= e($p['file_path']) ?>" class="sch-photo" onclick="openSchLightbox('<?= e($p['file_path']) ?>')" style="width:50px;height:50px;object-fit:cover;border-radius:4px;border:1px solid var(--gray-200);cursor:pointer">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- 施工回報紀錄 -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>施工回報紀錄</span>
        <a href="/worklog.php?action=report&id=<?= $myWorklog['id'] ?>&from_schedule=<?= $schedule['id'] ?>" class="btn btn-sm" style="background:#FF9800;color:#fff">📝 填寫回報</a>
    </div>
    <?php
    // 顯示所有有內容的回報
    $filledWorklogs = array();
    foreach ($worklogs as $wl) {
        if (!empty($wl['work_description'])) $filledWorklogs[] = $wl;
    }
    ?>
    <?php if (empty($filledWorklogs)): ?>
        <p class="text-muted" style="padding:16px">尚無施工回報</p>
    <?php else: ?>
        <?php foreach ($filledWorklogs as $wl): ?>
        <div style="padding:12px;border-bottom:1px solid var(--gray-100)">
            <div class="d-flex justify-between align-center mb-1">
                <div>
                    <strong><?= e($wl['real_name']) ?></strong>
                    <?php if ($wl['arrival_time'] && $wl['departure_time']): ?>
                    <span class="text-muted" style="font-size:.8rem;margin-left:8px">
                        <?= date('H:i', strtotime($wl['arrival_time'])) ?> ~ <?= date('H:i', strtotime($wl['departure_time'])) ?>
                        <?php $dur = strtotime($wl['departure_time']) - strtotime($wl['arrival_time']); if ($dur > 0) echo ' (' . round($dur/3600, 1) . 'h)'; ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($wl['is_completed'])): ?>
                    <span class="badge badge-success" style="margin-left:4px">已完工</span>
                    <?php endif; ?>
                    <?php if (!empty($wl['next_visit_needed'])): ?>
                    <span class="badge badge-warning" style="margin-left:4px">需再施工</span>
                    <?php endif; ?>
                </div>
                <a href="/worklog.php?action=report&id=<?= $wl['id'] ?>&from_schedule=<?= $schedule['id'] ?>" class="btn btn-outline btn-sm" style="font-size:.75rem">編輯</a>
            </div>
            <div style="margin-bottom:4px"><p style="margin:2px 0;white-space:pre-line"><?= e($wl['work_description']) ?></p></div>
            <?php if ($wl['issues']): ?>
            <div style="margin-bottom:4px"><span style="font-size:.8rem;color:var(--danger)">問題：</span><span style="font-size:.9rem"><?= e($wl['issues']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($wl['photos'])): ?>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:4px 0">
                <?php foreach ($wl['photos'] as $p): ?>
                <img src="<?= e($p['file_path']) ?>" class="sch-photo" onclick="openSchLightbox('<?= e($p['file_path']) ?>')" style="width:60px;height:60px;object-fit:cover;border-radius:4px;border:1px solid var(--gray-200);cursor:pointer">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($wl['payment_collected']): ?>
            <div><span class="badge badge-success">收款</span> $<?= number_format($wl['payment_amount']) ?> (<?= e($wl['payment_method'] ?: '-') ?>)</div>
            <?php endif; ?>
            <?php if (!empty($wl['next_visit_needed'])): ?>
            <div style="color:#e65100;font-size:.85rem">
                ⚠ 需再次施工
                <?php if (!empty($wl['next_visit_date'])): ?> — 預計 <?= e($wl['next_visit_date']) ?><?php endif; ?>
                <?php if ($wl['next_visit_note']): ?><?= e($wl['next_visit_note']) ?><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($schedule['case_id']): ?>
    <div style="padding:8px;text-align:center">
        <a href="/cases.php?action=edit&id=<?= $schedule['case_id'] ?>&from=schedule#sec-worklog" class="btn btn-outline btn-sm">查看完整回報紀錄</a>
    </div>
    <?php endif; ?>
</div>

<style>
.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding: 12px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: .8rem; color: var(--gray-500); }
@media (max-width: 767px) { .detail-grid { grid-template-columns: 1fr; } }
</style>

<script>
function joinSchedule(scheduleId) {
    var btn = document.getElementById('joinBtn');
    if (!confirm('確定要參加此排工？')) return;
    btn.disabled = true;
    btn.textContent = '加入中...';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/schedule.php?action=join&id=' + scheduleId + '&csrf_token=<?= e(Session::getCsrfToken()) ?>');
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) {
                location.reload();
            } else {
                alert(res.error || '加入失敗');
                btn.disabled = false;
                btn.textContent = '\uD83E\uDD1D 參加此排工';
            }
        } catch(e) { alert('加入失敗'); btn.disabled = false; }
    };
    xhr.onerror = function() { alert('網路錯誤'); btn.disabled = false; };
    xhr.send();
}

// ===== 行事曆檢視照片 lightbox =====
var schLbImages = [], schLbIndex = 0;
function openSchLightbox(src) {
    // 收集頁面所有照片
    schLbImages = [];
    document.querySelectorAll('.sch-photo').forEach(function(img) {
        var oc = img.getAttribute('onclick') || '';
        var m = oc.match(/openSchLightbox\(['"]([^'"]+)['"]/);
        if (m && schLbImages.indexOf(m[1]) === -1) schLbImages.push(m[1]);
    });
    if (schLbImages.length === 0) schLbImages = [src];
    schLbIndex = schLbImages.indexOf(src);
    if (schLbIndex < 0) schLbIndex = 0;

    // 手機：跳獨立檢視頁（支援雙指縮放）。電腦：走原 lightbox
    if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
        var params = 'idx=' + schLbIndex + '&images=' + encodeURIComponent(JSON.stringify(schLbImages)) + '&back=' + encodeURIComponent(location.href);
        location.href = '/photo_view.php?' + params;
        return;
    }

    showSchLbImage();
    document.getElementById('schLightbox').classList.add('active');
}
function showSchLbImage() {
    document.getElementById('schLbImg').src = schLbImages[schLbIndex];
    var c = document.getElementById('schLbCounter');
    if (schLbImages.length > 1) {
        c.textContent = (schLbIndex + 1) + ' / ' + schLbImages.length;
        c.style.display = 'block';
        document.querySelector('.sch-lb-prev').style.display = 'block';
        document.querySelector('.sch-lb-next').style.display = 'block';
    } else {
        c.style.display = 'none';
        document.querySelector('.sch-lb-prev').style.display = 'none';
        document.querySelector('.sch-lb-next').style.display = 'none';
    }
}
function schLbNav(dir) {
    schLbIndex += dir;
    if (schLbIndex < 0) schLbIndex = schLbImages.length - 1;
    if (schLbIndex >= schLbImages.length) schLbIndex = 0;
    showSchLbImage();
}
function closeSchLightbox() { document.getElementById('schLightbox').classList.remove('active'); document.getElementById('schLbImg').src=''; }
function openSchFile(src, name) {
    document.getElementById('schFileTitle').textContent = name || '檔案';
    document.getElementById('schFileDownload').href = src;
    document.getElementById('schFileFrame').src = src;
    document.getElementById('schFileModal').classList.add('active');
}
function closeSchFile() {
    document.getElementById('schFileModal').classList.remove('active');
    document.getElementById('schFileFrame').src = '';
}
document.addEventListener('keydown', function(e) {
    var lb = document.getElementById('schLightbox');
    if (lb && lb.classList.contains('active')) {
        if (e.key === 'Escape') closeSchLightbox();
        if (e.key === 'ArrowLeft') schLbNav(-1);
        if (e.key === 'ArrowRight') schLbNav(1);
        return;
    }
    var fm = document.getElementById('schFileModal');
    if (fm && fm.classList.contains('active') && e.key === 'Escape') closeSchFile();
});
(function() {
    var sx=0, sy=0;
    document.addEventListener('DOMContentLoaded', function() {
        var o = document.getElementById('schLightbox');
        if (!o) return;
        o.addEventListener('touchstart', function(e) { sx=e.changedTouches[0].screenX; sy=e.changedTouches[0].screenY; }, {passive:true});
        o.addEventListener('touchend', function(e) {
            var dx = e.changedTouches[0].screenX - sx;
            var dy = e.changedTouches[0].screenY - sy;
            if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;
            if (Math.abs(dx) > Math.abs(dy)) {
                if (dx > 0) schLbNav(-1); else schLbNav(1);
            } else {
                closeSchLightbox();
            }
        }, {passive:true});
    });
})();
</script>

<!-- 行事曆檢視 Lightbox -->
<div class="sch-lightbox" id="schLightbox" onclick="if(event.target===this)closeSchLightbox()">
    <span class="sch-lb-close" onclick="closeSchLightbox()">&times;</span>
    <span class="sch-lb-prev" onclick="event.stopPropagation();schLbNav(-1)">&lsaquo;</span>
    <span class="sch-lb-next" onclick="event.stopPropagation();schLbNav(1)">&rsaquo;</span>
    <img id="schLbImg" src="" alt="預覽" onclick="event.stopPropagation()">
    <span class="sch-lb-counter" id="schLbCounter"></span>
</div>
<div class="sch-file-modal" id="schFileModal">
    <div class="sch-file-header">
        <span id="schFileTitle"></span>
        <div style="display:flex;gap:8px;align-items:center">
            <a id="schFileDownload" href="" download class="sch-file-btn">下載</a>
            <span class="sch-file-close" onclick="closeSchFile()">&times;</span>
        </div>
    </div>
    <iframe id="schFileFrame" src="" frameborder="0"></iframe>
</div>
<style>
.sch-lightbox { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.85); z-index:9999; align-items:center; justify-content:center; cursor:pointer; }
.sch-lightbox.active { display:flex; }
.sch-lightbox img { max-width:90%; max-height:90%; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,.5); }
.sch-lb-close { position:absolute; top:16px; right:16px; color:#fff; font-size:2.5rem; cursor:pointer; z-index:10000; width:48px; height:48px; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.4); border-radius:50%; line-height:1; }
.sch-lb-prev, .sch-lb-next { position:absolute; top:50%; transform:translateY(-50%); color:#fff; font-size:2.5rem; cursor:pointer; padding:16px 12px; z-index:10000; background:rgba(0,0,0,.4); border-radius:8px; user-select:none; }
.sch-lb-prev { left:10px; } .sch-lb-next { right:10px; }
.sch-lb-counter { position:absolute; bottom:20px; left:50%; transform:translateX(-50%); color:#fff; font-size:.9rem; z-index:10000; background:rgba(0,0,0,.4); padding:4px 12px; border-radius:12px; }
.sch-file-modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#fff; z-index:9999; flex-direction:column; }
.sch-file-modal.active { display:flex; }
.sch-file-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#1a73e8; color:#fff; flex-shrink:0; }
.sch-file-header span { font-weight:600; font-size:.95rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; margin-right:12px; }
.sch-file-btn { background:rgba(255,255,255,.2); color:#fff; padding:6px 14px; border-radius:6px; text-decoration:none; font-size:.85rem; }
.sch-file-close { color:#fff; font-size:1.8rem; cursor:pointer; width:36px; height:36px; display:flex; align-items:center; justify-content:center; line-height:1; }
.sch-file-modal iframe { flex:1; width:100%; border:0; }
</style>
