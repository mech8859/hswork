<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/procurement/ProcurementModel.php';
require_once __DIR__ . '/../modules/inventory/InventoryModel.php';

$model = new ProcurementModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    case 'list':
        $filters = array(
            'branch_id'   => !empty($_GET['branch_id']) ? $_GET['branch_id'] : '',
            'status'      => !empty($_GET['status']) ? $_GET['status'] : '',
            'vendor_name' => !empty($_GET['vendor_name']) ? $_GET['vendor_name'] : '',
            'keyword'     => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from'   => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'     => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
        );
        $records = $model->getPurchaseOrders($filters);
        $branches = $model->getBranches($branchIds);

        $pageTitle = '採購單';
        $currentPage = 'purchase_orders';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/purchase_orders/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        // Check if converting from requisition
        $fromReqId = !empty($_GET['from_requisition']) ? (int)$_GET['from_requisition'] : 0;
        $prefill = null;
        if ($fromReqId > 0) {
            $prefill = $model->convertFromRequisition($fromReqId);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/purchase_orders.php');
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'po_date'              => !empty($_POST['po_date']) ? $_POST['po_date'] : date('Y-m-d'),
                'status'               => !empty($_POST['status']) ? $_POST['status'] : '尚未進貨',
                'purchaser_name'       => !empty($_POST['purchaser_name']) ? $_POST['purchaser_name'] : null,
                'requisition_id'       => !empty($_POST['requisition_id']) ? $_POST['requisition_id'] : null,
                'requisition_number'   => !empty($_POST['requisition_number']) ? $_POST['requisition_number'] : null,
                'receiving_date'       => !empty($_POST['receiving_date']) ? $_POST['receiving_date'] : null,
                'case_name'            => !empty($_POST['case_name']) ? $_POST['case_name'] : null,
                'branch_id'            => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'sales_name'           => !empty($_POST['sales_name']) ? $_POST['sales_name'] : null,
                'urgency'              => !empty($_POST['urgency']) ? $_POST['urgency'] : '一般件',
                'req_vendor_name'      => !empty($_POST['req_vendor_name']) ? $_POST['req_vendor_name'] : null,
                'vendor_id'            => !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null,
                'vendor_code'          => !empty($_POST['vendor_code']) ? $_POST['vendor_code'] : null,
                'vendor_name'          => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'vendor_tax_id'        => !empty($_POST['vendor_tax_id']) ? $_POST['vendor_tax_id'] : null,
                'vendor_contact'       => !empty($_POST['vendor_contact']) ? $_POST['vendor_contact'] : null,
                'vendor_phone'         => !empty($_POST['vendor_phone']) ? $_POST['vendor_phone'] : null,
                'vendor_fax'           => !empty($_POST['vendor_fax']) ? $_POST['vendor_fax'] : null,
                'vendor_email'         => !empty($_POST['vendor_email']) ? $_POST['vendor_email'] : null,
                'vendor_address'       => !empty($_POST['vendor_address']) ? $_POST['vendor_address'] : null,
                'payment_method'       => !empty($_POST['payment_method']) ? $_POST['payment_method'] : null,
                'payment_terms'        => !empty($_POST['payment_terms']) ? $_POST['payment_terms'] : null,
                'invoice_method'       => !empty($_POST['invoice_method']) ? $_POST['invoice_method'] : null,
                'invoice_type'         => !empty($_POST['invoice_type']) ? $_POST['invoice_type'] : null,
                'payment_date'         => !empty($_POST['payment_date']) ? $_POST['payment_date'] : null,
                'is_paid'              => !empty($_POST['is_paid']) ? 1 : 0,
                'paid_amount'          => !empty($_POST['paid_amount']) ? $_POST['paid_amount'] : 0,
                'subtotal'             => !empty($_POST['subtotal']) ? $_POST['subtotal'] : 0,
                'tax_type'             => !empty($_POST['tax_type']) ? $_POST['tax_type'] : '營業稅',
                'tax_rate'             => !empty($_POST['tax_rate']) ? $_POST['tax_rate'] : 5,
                'tax_amount'           => !empty($_POST['tax_amount']) ? $_POST['tax_amount'] : 0,
                'shipping_fee'         => !empty($_POST['shipping_fee']) ? $_POST['shipping_fee'] : 0,
                'total_amount'         => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'this_amount'          => !empty($_POST['this_amount']) ? $_POST['this_amount'] : 0,
                'discount_untaxed'     => !empty($_POST['discount_untaxed']) ? $_POST['discount_untaxed'] : null,
                'discount_taxed'       => !empty($_POST['discount_taxed']) ? $_POST['discount_taxed'] : null,
                'use_payment_flow'     => !empty($_POST['use_payment_flow']) ? 1 : 0,
                'convert_to_receiving' => !empty($_POST['convert_to_receiving']) ? 1 : 0,
                'delivery_location'    => !empty($_POST['delivery_location']) ? $_POST['delivery_location'] : null,
                'required_date'        => !empty($_POST['required_date']) ? $_POST['required_date'] : null,
                'promised_date'        => !empty($_POST['promised_date']) ? $_POST['promised_date'] : null,
                'note'                 => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'           => $userId,
            );

            $poId = $model->createPurchaseOrder($data);

            if (!empty($_POST['items'])) {
                $model->savePurchaseOrderItems($poId, $_POST['items']);
            }

            // If from requisition, update requisition status
            if (!empty($data['requisition_id'])) {
                $model->updateRequisition($data['requisition_id'], array('status' => '已轉採購'));
            }

            // 通知
            try {
                require_once __DIR__ . '/../modules/notifications/NotificationDispatcher.php';
                $poRecord = $model->getPurchaseOrder($poId);
                if ($poRecord) NotificationDispatcher::dispatch('purchase_orders', 'created', $poRecord);
            } catch (\Throwable $e) { /* 通知失敗不影響主流程 */ }

            Session::flash('success', '採購單已新增');
            redirect('/purchase_orders.php?action=edit&id=' . $poId);
        }

        $record = $prefill ? $prefill['header'] : null;
        $isFromRequisition = !empty($prefill);
        $items = $prefill ? $prefill['items'] : array();
        $branches = $model->getBranches($branchIds);
        $vendors = $model->getVendors(array());

        $pageTitle = '新增採購單';
        $currentPage = 'purchase_orders';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/purchase_orders/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getPurchaseOrder($id);
        if (!$record) {
            Session::flash('error', '採購單不存在');
            redirect('/purchase_orders.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/purchase_orders.php?action=edit&id=' . $id);
            }

            $userId = Session::getUser()['id'];
            $oldStatus = $record['status'];
            $newStatus = !empty($_POST['status']) ? $_POST['status'] : $oldStatus;

            $data = array(
                'po_date'              => !empty($_POST['po_date']) ? $_POST['po_date'] : date('Y-m-d'),
                'status'               => $newStatus,
                'purchaser_name'       => !empty($_POST['purchaser_name']) ? $_POST['purchaser_name'] : null,
                'requisition_id'       => !empty($_POST['requisition_id']) ? $_POST['requisition_id'] : (!empty($record['requisition_id']) ? $record['requisition_id'] : null),
                'requisition_number'   => !empty($_POST['requisition_number']) ? $_POST['requisition_number'] : null,
                'receiving_date'       => !empty($_POST['receiving_date']) ? $_POST['receiving_date'] : null,
                'case_name'            => !empty($_POST['case_name']) ? $_POST['case_name'] : null,
                'branch_id'            => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'sales_name'           => !empty($_POST['sales_name']) ? $_POST['sales_name'] : null,
                'urgency'              => !empty($_POST['urgency']) ? $_POST['urgency'] : '一般件',
                'vendor_id'            => !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null,
                'vendor_code'          => !empty($_POST['vendor_code']) ? $_POST['vendor_code'] : null,
                'vendor_name'          => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'vendor_tax_id'        => !empty($_POST['vendor_tax_id']) ? $_POST['vendor_tax_id'] : null,
                'vendor_contact'       => !empty($_POST['vendor_contact']) ? $_POST['vendor_contact'] : null,
                'vendor_phone'         => !empty($_POST['vendor_phone']) ? $_POST['vendor_phone'] : null,
                'vendor_fax'           => !empty($_POST['vendor_fax']) ? $_POST['vendor_fax'] : null,
                'vendor_email'         => !empty($_POST['vendor_email']) ? $_POST['vendor_email'] : null,
                'vendor_address'       => !empty($_POST['vendor_address']) ? $_POST['vendor_address'] : null,
                'payment_method'       => !empty($_POST['payment_method']) ? $_POST['payment_method'] : null,
                'payment_terms'        => !empty($_POST['payment_terms']) ? $_POST['payment_terms'] : null,
                'invoice_method'       => !empty($_POST['invoice_method']) ? $_POST['invoice_method'] : null,
                'invoice_type'         => !empty($_POST['invoice_type']) ? $_POST['invoice_type'] : null,
                'payment_date'         => !empty($_POST['payment_date']) ? $_POST['payment_date'] : null,
                'is_paid'              => !empty($_POST['is_paid']) ? 1 : 0,
                'paid_amount'          => !empty($_POST['paid_amount']) ? $_POST['paid_amount'] : 0,
                'subtotal'             => !empty($_POST['subtotal']) ? $_POST['subtotal'] : 0,
                'tax_type'             => !empty($_POST['tax_type']) ? $_POST['tax_type'] : '營業稅',
                'tax_rate'             => !empty($_POST['tax_rate']) ? $_POST['tax_rate'] : 5,
                'tax_amount'           => !empty($_POST['tax_amount']) ? $_POST['tax_amount'] : 0,
                'shipping_fee'         => !empty($_POST['shipping_fee']) ? $_POST['shipping_fee'] : 0,
                'total_amount'         => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'this_amount'          => !empty($_POST['this_amount']) ? $_POST['this_amount'] : 0,
                'discount_untaxed'     => !empty($_POST['discount_untaxed']) ? $_POST['discount_untaxed'] : null,
                'discount_taxed'       => !empty($_POST['discount_taxed']) ? $_POST['discount_taxed'] : null,
                'use_payment_flow'     => !empty($_POST['use_payment_flow']) ? 1 : 0,
                'convert_to_receiving' => !empty($_POST['convert_to_receiving']) ? 1 : 0,
                'is_cancelled'         => !empty($_POST['is_cancelled']) ? 1 : 0,
                'refund_date'          => !empty($_POST['refund_date']) ? $_POST['refund_date'] : null,
                'delivery_location'    => !empty($_POST['delivery_location']) ? $_POST['delivery_location'] : null,
                'required_date'        => !empty($_POST['required_date']) ? $_POST['required_date'] : null,
                'promised_date'        => !empty($_POST['promised_date']) ? $_POST['promised_date'] : null,
                'note'                 => !empty($_POST['note']) ? $_POST['note'] : null,
                'updated_by'           => $userId,
            );

            $model->updatePurchaseOrder($id, $data);

            if (isset($_POST['items'])) {
                $model->savePurchaseOrderItems($id, $_POST['items']);
            }

            // 確認進貨 → 更新庫存
            if ($oldStatus !== '確認進貨' && $newStatus === '確認進貨') {
                $invModel = new InventoryModel();
                $poItems = $model->getPurchaseOrderItems($id);
                // Determine warehouse from branch
                $warehouseId = null;
                if (!empty($data['branch_id'])) {
                    $whs = $invModel->getWarehouses();
                    foreach ($whs as $wh) {
                        if ($wh['branch_id'] == $data['branch_id']) {
                            $warehouseId = $wh['id'];
                            break;
                        }
                    }
                }
                if ($warehouseId) {
                    foreach ($poItems as $item) {
                        if (!empty($item['product_id']) && $item['quantity'] > 0) {
                            $invModel->adjustStock(
                                $item['product_id'],
                                $warehouseId,
                                $item['quantity'],
                                'purchase_in',
                                'purchase_order',
                                $id,
                                '採購進貨: ' . $record['po_number'],
                                $userId
                            );
                        }
                    }
                }
            }

            // 狀態變更通知
            if ($oldStatus !== $newStatus) {
                try {
                    require_once __DIR__ . '/../modules/notifications/NotificationDispatcher.php';
                    $updatedRecord = $model->getPurchaseOrder($id);
                    if ($updatedRecord) NotificationDispatcher::dispatch('purchase_orders', 'status_changed', $updatedRecord);
                } catch (\Throwable $e) {
                    // 通知失敗不影響主流程
                }
            }

            Session::flash('success', '採購單已更新');
            redirect('/purchase_orders.php?action=edit&id=' . $id);
        }

        $items = $model->getPurchaseOrderItems($record['id']);
        $branches = $model->getBranches($branchIds);
        $vendors = $model->getVendors(array());

        $pageTitle = '編輯採購單 - ' . $record['po_number'];
        $currentPage = 'purchase_orders';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/purchase_orders/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'delete':
        if (!Auth::hasPermission('procurement.manage')) {
            Session::flash('error', '無權限');
            redirect('/purchase_orders.php');
        }
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            $model->deletePurchaseOrder($id);
            Session::flash('success', '採購單已刪除');
        }
        redirect('/purchase_orders.php');
        break;

    default:
        redirect('/purchase_orders.php');
        break;
}
