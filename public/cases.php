<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/cases/CaseModel.php';

$model = new CaseModel();
$action = $_GET['action'] ?? 'list';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    // ---- 案件清單 ----
    case 'list':
        $filters = [
            'status'    => $_GET['status'] ?? '',
            'case_type' => $_GET['case_type'] ?? '',
            'keyword'   => $_GET['keyword'] ?? '',
            'branch_id' => $_GET['branch_id'] ?? '',
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $result = $model->getList($branchIds, $filters, $page);
        $branches = $model->getAllBranches();

        $pageTitle = '案件管理';
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增案件 ----
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/cases.php');
            }
            $caseId = $model->create($_POST);
            $model->updateReadiness($caseId, $_POST);
            $model->updateSiteConditions($caseId, $_POST);
            if (!empty($_POST['contacts'])) {
                $model->saveContacts($caseId, $_POST['contacts']);
            }
            if (!empty($_POST['required_skills'])) {
                $model->saveRequiredSkills($caseId, $_POST['required_skills']);
            }
            Session::flash('success', '案件已新增');
            redirect('/cases.php?action=view&id=' . $caseId);
        }

        $case = null;
        $branches = $model->getAllBranches();
        $skills = $model->getAllSkills();
        $salesUsers = $model->getSalesUsers($branchIds);

        $pageTitle = '新增案件';
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯案件 ----
    case 'edit':
        $id = (int)($_GET['id'] ?? 0);
        $case = $model->getById($id);
        if (!$case) {
            Session::flash('error', '案件不存在');
            redirect('/cases.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/cases.php?action=edit&id=' . $id);
            }
            $model->update($id, $_POST);
            $model->updateReadiness($id, $_POST);
            $model->updateSiteConditions($id, $_POST);
            if (isset($_POST['contacts'])) {
                $model->saveContacts($id, $_POST['contacts']);
            }
            if (isset($_POST['required_skills'])) {
                $model->saveRequiredSkills($id, $_POST['required_skills']);
            }
            Session::flash('success', '案件已更新');
            redirect('/cases.php?action=view&id=' . $id);
        }

        $branches = $model->getAllBranches();
        $skills = $model->getAllSkills();
        $salesUsers = $model->getSalesUsers($branchIds);

        $pageTitle = '編輯案件';
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 案件詳情 ----
    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        $case = $model->getById($id);
        if (!$case) {
            Session::flash('error', '案件不存在');
            redirect('/cases.php');
        }

        $pageTitle = $case['case_number'] . ' - ' . $case['title'];
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    default:
        redirect('/cases.php');
}
