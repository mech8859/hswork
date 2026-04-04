<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/procurement/ProcurementModel.php';

$model = new ProcurementModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $filters = array(
            'keyword'  => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'category' => !empty($_GET['category']) ? $_GET['category'] : '',
        );
        $records = $model->getVendors($filters);

        $pageTitle = '廠商管理';
        $currentPage = 'vendors';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/vendors/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/vendors.php');
            }

            $data = array(
                'vendor_code'    => !empty($_POST['vendor_code']) ? $_POST['vendor_code'] : null,
                'name'           => !empty($_POST['name']) ? $_POST['name'] : '',
                'short_name'     => !empty($_POST['short_name']) ? $_POST['short_name'] : null,
                'tax_id'         => !empty($_POST['tax_id']) ? $_POST['tax_id'] : null,
                'category'       => !empty($_POST['category']) ? $_POST['category'] : null,
                'service_items'  => !empty($_POST['service_items']) ? $_POST['service_items'] : null,
                'contact_person' => !empty($_POST['contact_person']) ? $_POST['contact_person'] : null,
                'phone'          => !empty($_POST['phone']) ? $_POST['phone'] : null,
                'fax'            => !empty($_POST['fax']) ? $_POST['fax'] : null,
                'email'          => !empty($_POST['email']) ? $_POST['email'] : null,
                'postal_code'    => !empty($_POST['postal_code']) ? $_POST['postal_code'] : null,
                'city_district'  => !empty($_POST['city_district']) ? $_POST['city_district'] : null,
                'street_address' => !empty($_POST['street_address']) ? $_POST['street_address'] : null,
                'address'        => !empty($_POST['address']) ? $_POST['address'] : null,
                'payment_method' => !empty($_POST['payment_method']) ? $_POST['payment_method'] : null,
                'payment_terms'  => !empty($_POST['payment_terms']) ? $_POST['payment_terms'] : null,
                'settlement_day' => !empty($_POST['settlement_day']) ? $_POST['settlement_day'] : null,
                'invoice_method' => !empty($_POST['invoice_method']) ? $_POST['invoice_method'] : null,
                'invoice_type'   => !empty($_POST['invoice_type']) ? $_POST['invoice_type'] : null,
                'header1'        => !empty($_POST['header1']) ? $_POST['header1'] : null,
                'tax_id1'        => !empty($_POST['tax_id1']) ? $_POST['tax_id1'] : null,
                'header2'        => !empty($_POST['header2']) ? $_POST['header2'] : null,
                'tax_id2'        => !empty($_POST['tax_id2']) ? $_POST['tax_id2'] : null,
                'invoice_type2'  => !empty($_POST['invoice_type2']) ? $_POST['invoice_type2'] : null,
                'note'           => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'     => Session::getUser()['name'],
            );

            $model->createVendor($data);
            Session::flash('success', '廠商已新增');
            redirect('/vendors.php');
        }

        $record = null;
        $pageTitle = '新增廠商';
        $currentPage = 'vendors';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/vendors/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getVendor($id);
        if (!$record) {
            Session::flash('error', '廠商不存在');
            redirect('/vendors.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/vendors.php?action=edit&id=' . $id);
            }

            $data = array(
                'vendor_code'    => !empty($_POST['vendor_code']) ? $_POST['vendor_code'] : null,
                'name'           => !empty($_POST['name']) ? $_POST['name'] : '',
                'short_name'     => !empty($_POST['short_name']) ? $_POST['short_name'] : null,
                'tax_id'         => !empty($_POST['tax_id']) ? $_POST['tax_id'] : null,
                'category'       => !empty($_POST['category']) ? $_POST['category'] : null,
                'service_items'  => !empty($_POST['service_items']) ? $_POST['service_items'] : null,
                'contact_person' => !empty($_POST['contact_person']) ? $_POST['contact_person'] : null,
                'phone'          => !empty($_POST['phone']) ? $_POST['phone'] : null,
                'fax'            => !empty($_POST['fax']) ? $_POST['fax'] : null,
                'email'          => !empty($_POST['email']) ? $_POST['email'] : null,
                'postal_code'    => !empty($_POST['postal_code']) ? $_POST['postal_code'] : null,
                'city_district'  => !empty($_POST['city_district']) ? $_POST['city_district'] : null,
                'street_address' => !empty($_POST['street_address']) ? $_POST['street_address'] : null,
                'address'        => !empty($_POST['address']) ? $_POST['address'] : null,
                'payment_method' => !empty($_POST['payment_method']) ? $_POST['payment_method'] : null,
                'payment_terms'  => !empty($_POST['payment_terms']) ? $_POST['payment_terms'] : null,
                'settlement_day' => !empty($_POST['settlement_day']) ? $_POST['settlement_day'] : null,
                'invoice_method' => !empty($_POST['invoice_method']) ? $_POST['invoice_method'] : null,
                'invoice_type'   => !empty($_POST['invoice_type']) ? $_POST['invoice_type'] : null,
                'header1'        => !empty($_POST['header1']) ? $_POST['header1'] : null,
                'tax_id1'        => !empty($_POST['tax_id1']) ? $_POST['tax_id1'] : null,
                'header2'        => !empty($_POST['header2']) ? $_POST['header2'] : null,
                'tax_id2'        => !empty($_POST['tax_id2']) ? $_POST['tax_id2'] : null,
                'invoice_type2'  => !empty($_POST['invoice_type2']) ? $_POST['invoice_type2'] : null,
                'note'           => !empty($_POST['note']) ? $_POST['note'] : null,
            );

            $model->updateVendor($id, $data);
            Session::flash('success', '廠商已更新');
            redirect('/vendors.php');
        }

        $pageTitle = '編輯廠商 - ' . $record['name'];
        $currentPage = 'vendors';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/vendors/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'delete':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            $model->deleteVendor($id);
            Session::flash('success', '廠商已停用');
        }
        redirect('/vendors.php');
        break;

    default:
        redirect('/vendors.php');
        break;
}
