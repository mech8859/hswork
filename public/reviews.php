<?php
/**
 * 五星評價統計
 * 獨立模組：跟出勤狀況表無關聯，只是側邊選單排在其下方
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

// 權限：獨立模組權限
if (!Auth::hasPermission('reviews.manage') && !Auth::hasPermission('reviews.view') && !Auth::hasPermission('all')) {
    Session::flash('error', '無權限');
    redirect('/');
}

require_once __DIR__ . '/../modules/reviews/ReviewModel.php';

$model = new ReviewModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$branchIds = Auth::getAccessibleBranchIds();
$canManage = Auth::hasPermission('reviews.manage') || Auth::hasPermission('all');

switch ($action) {
    // ---- 列表 ----
    case 'list':
        $filters = array(
            'branch_id' => isset($_GET['branch_id']) ? $_GET['branch_id'] : '',
            'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'   => isset($_GET['date_to']) ? $_GET['date_to'] : '',
            'keyword'   => isset($_GET['keyword']) ? $_GET['keyword'] : '',
        );
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $result = $model->getList($filters, $page);
        $records = $result['data'];
        $branches = $model->getBranchOptions($branchIds);

        // 建立人員 id → name 對照（給列表顯示用）
        $engineers = $model->getEngineerOptions($branchIds);
        $engineerNameMap = array();
        foreach ($engineers as $eng) {
            $engineerNameMap[(int)$eng['id']] = $eng['real_name'];
        }

        $pageTitle = '五星評價統計';
        $currentPage = 'reviews';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/reviews/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增表單 ----
    case 'create':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/reviews.php');
        }
        $record = null;
        $engineers = $model->getEngineerOptions($branchIds);
        $branches = $model->getBranchOptions($branchIds);
        $nextNumber = peek_next_doc_number('five_star_reviews');

        $pageTitle = '新增五星評價';
        $currentPage = 'reviews';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/reviews/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯表單 ----
    case 'edit':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/reviews.php');
        }
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '找不到記錄');
            redirect('/reviews.php');
        }
        $engineers = $model->getEngineerOptions($branchIds);
        $branches = $model->getBranchOptions($branchIds);

        $pageTitle = '編輯五星評價';
        $currentPage = 'reviews';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/reviews/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 儲存（新增/更新） ----
    case 'store':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/reviews.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/reviews.php');
        }
        if (!verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/reviews.php');
        }

        // 把多選人員陣列轉成 JSON 字串
        $groupPhotoIds = array();
        if (isset($_POST['group_photo_engineer_ids']) && is_array($_POST['group_photo_engineer_ids'])) {
            foreach ($_POST['group_photo_engineer_ids'] as $uid) {
                $uid = (int)$uid;
                if ($uid > 0) $groupPhotoIds[] = $uid;
            }
        }
        $engIds = array();
        if (isset($_POST['engineer_ids']) && is_array($_POST['engineer_ids'])) {
            foreach ($_POST['engineer_ids'] as $uid) {
                $uid = (int)$uid;
                if ($uid > 0) $engIds[] = $uid;
            }
        }

        $data = array(
            'review_date'              => isset($_POST['review_date']) ? trim($_POST['review_date']) : '',
            'reason'                   => isset($_POST['reason']) ? trim($_POST['reason']) : '',
            'photo_path'               => isset($_POST['photo_path']) ? trim($_POST['photo_path']) : '',
            'group_photo_engineer_ids' => !empty($groupPhotoIds) ? json_encode($groupPhotoIds) : null,
            'customer_name'            => isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '',
            'original_customer_name'   => isset($_POST['original_customer_name']) ? trim($_POST['original_customer_name']) : '',
            'google_reviewer_name'     => isset($_POST['google_reviewer_name']) ? trim($_POST['google_reviewer_name']) : '',
            'engineer_ids'             => !empty($engIds) ? json_encode($engIds) : null,
            'original_engineer_names'  => isset($_POST['original_engineer_names']) ? trim($_POST['original_engineer_names']) : '',
            'branch_id'                => isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0,
            'bonus_payment_date'       => isset($_POST['bonus_payment_date']) ? trim($_POST['bonus_payment_date']) : '',
        );

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $model->update($id, $data);
            AuditLog::log('reviews', 'update', $id, '更新五星評價');
            Session::flash('success', '已更新五星評價');
        } else {
            $newId = $model->create($data);
            AuditLog::log('reviews', 'create', $newId, '新增五星評價');
            Session::flash('success', '已新增五星評價');
        }
        redirect('/reviews.php');
        break;

    // ---- 刪除 ----
    case 'delete':
        if (!Auth::hasPermission('all')) {
            Session::flash('error', '僅系統管理者可刪除');
            redirect('/reviews.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/reviews.php');
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            AuditLog::log('reviews', 'delete', $id, '刪除五星評價');
            $model->delete($id);
            Session::flash('success', '已刪除');
        }
        redirect('/reviews.php');
        break;

    default:
        redirect('/reviews.php');
}
