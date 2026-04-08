<?php
/**
 * Auto-Journal Service
 * Automatically creates journal entries when financial transactions occur.
 * PHP 7.2 compatible.
 */
require_once __DIR__ . '/AccountingModel.php';

class AutoJournalService
{
    /**
     * Get account ID by code, returns null if not found
     * @param AccountingModel $model
     * @param string $code
     * @return int|null
     */
    private static function getAccountId($model, $code)
    {
        $account = $model->getAccountByCode($code);
        return $account ? (int)$account['id'] : null;
    }

    /**
     * Create and auto-post a journal entry
     * @param array $data
     * @return int journal entry ID
     * @throws Exception
     */
    private static function createAndPost($data)
    {
        $model = new AccountingModel();
        $journalId = $model->createJournalEntry($data);
        // Auto-post
        $model->postJournalEntry($journalId, $data['created_by']);
        return $journalId;
    }

    /**
     * 收款確認 -> 借:銀行存款/現金  貸:應收帳款
     * @param int $receiptId
     * @return int|false journal entry ID or false
     */
    public static function onReceiptConfirmed($receiptId)
    {
        try {
            require_once __DIR__ . '/../finance/FinanceModel.php';
            $fm = new FinanceModel();
            $receipt = $fm->getReceipt($receiptId);
            if (!$receipt) return false;

            $am = new AccountingModel();
            $amount = (float)$receipt['total_amount'];
            if ($amount <= 0) return false;

            // Determine debit account based on receipt method
            $method = !empty($receipt['receipt_method']) ? $receipt['receipt_method'] : '';
            if ($method === '現金') {
                $debitCode = '1100'; // Cash
            } else {
                $debitCode = '1102'; // Bank deposit
            }
            $debitAccountId = self::getAccountId($am, $debitCode);
            $creditAccountId = self::getAccountId($am, '1131'); // AR

            if (!$debitAccountId || !$creditAccountId) return false;

            $desc = '收款入帳 - ' . (!empty($receipt['receipt_number']) ? $receipt['receipt_number'] : 'RC#' . $receiptId);
            if (!empty($receipt['customer_name'])) {
                $desc .= ' (' . $receipt['customer_name'] . ')';
            }

            $userId = !empty($receipt['updated_by']) ? $receipt['updated_by'] : (!empty($receipt['created_by']) ? $receipt['created_by'] : 1);

            $data = array(
                'voucher_date'  => !empty($receipt['deposit_date']) ? $receipt['deposit_date'] : date('Y-m-d'),
                'voucher_type'  => 'receipt',
                'description'   => $desc,
                'source_module' => 'receipt',
                'source_id'     => $receiptId,
                'created_by'    => $userId,
                'lines' => array(
                    array(
                        'account_id'    => $debitAccountId,
                        'cost_center_id' => null,
                        'debit_amount'  => $amount,
                        'credit_amount' => 0,
                        'description'   => $desc,
                    ),
                    array(
                        'account_id'    => $creditAccountId,
                        'cost_center_id' => null,
                        'debit_amount'  => 0,
                        'credit_amount' => $amount,
                        'description'   => $desc,
                    ),
                ),
            );

            return self::createAndPost($data);
        } catch (Exception $e) {
            error_log('AutoJournal::onReceiptConfirmed error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 付款確認 -> 借:應付帳款  貸:銀行存款/現金
     * @param int $paymentId
     * @return int|false
     */
    public static function onPaymentConfirmed($paymentId)
    {
        try {
            require_once __DIR__ . '/../finance/FinanceModel.php';
            $fm = new FinanceModel();
            $payment = $fm->getPaymentOut($paymentId);
            if (!$payment) return false;

            $am = new AccountingModel();
            $amount = (float)$payment['total_amount'];
            if ($amount <= 0) return false;

            // Determine credit account based on payment method
            $method = !empty($payment['payment_method']) ? $payment['payment_method'] : '';
            if ($method === '現金') {
                $creditCode = '1100'; // Cash
            } else {
                $creditCode = '1102'; // Bank deposit
            }
            $debitAccountId = self::getAccountId($am, '2101'); // AP
            $creditAccountId = self::getAccountId($am, $creditCode);

            if (!$debitAccountId || !$creditAccountId) return false;

            $desc = '付款出帳 - ' . (!empty($payment['payment_number']) ? $payment['payment_number'] : 'PO#' . $paymentId);
            if (!empty($payment['vendor_name'])) {
                $desc .= ' (' . $payment['vendor_name'] . ')';
            }

            $userId = !empty($payment['updated_by']) ? $payment['updated_by'] : (!empty($payment['created_by']) ? $payment['created_by'] : 1);

            $data = array(
                'voucher_date'  => !empty($payment['payment_date']) ? $payment['payment_date'] : date('Y-m-d'),
                'voucher_type'  => 'payment',
                'description'   => $desc,
                'source_module' => 'payment_out',
                'source_id'     => $paymentId,
                'created_by'    => $userId,
                'lines' => array(
                    array(
                        'account_id'    => $debitAccountId,
                        'cost_center_id' => null,
                        'debit_amount'  => $amount,
                        'credit_amount' => 0,
                        'description'   => $desc,
                    ),
                    array(
                        'account_id'    => $creditAccountId,
                        'cost_center_id' => null,
                        'debit_amount'  => 0,
                        'credit_amount' => $amount,
                        'description'   => $desc,
                    ),
                ),
            );

            return self::createAndPost($data);
        } catch (Exception $e) {
            error_log('AutoJournal::onPaymentConfirmed error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 進貨入庫確認 -> 借:存貨+進項稅額  貸:應付帳款
     * @param int $stockInId
     * @return int|false
     */
    public static function onStockInConfirmed($stockInId)
    {
        try {
            require_once __DIR__ . '/../inventory/StockModel.php';
            $sm = new StockModel();
            $stockIn = $sm->getStockInById($stockInId);
            if (!$stockIn) return false;

            $am = new AccountingModel();

            // Calculate total from items
            $items = $sm->getStockInItems($stockInId);
            $totalAmount = 0;
            foreach ($items as $item) {
                $qty = isset($item['quantity']) ? (float)$item['quantity'] : 0;
                $price = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
                $totalAmount += $qty * $price;
            }
            if ($totalAmount <= 0) return false;

            // Calculate tax (5%)
            $taxRate = 0.05;
            $taxAmount = round($totalAmount * $taxRate);
            $untaxedAmount = $totalAmount - $taxAmount;

            $inventoryId = self::getAccountId($am, '1151'); // Inventory
            $inputTaxId = self::getAccountId($am, '1301'); // Input tax
            $apId = self::getAccountId($am, '2101'); // AP

            if (!$inventoryId || !$apId) return false;

            $desc = '入庫 - ' . (!empty($stockIn['si_number']) ? $stockIn['si_number'] : 'SI#' . $stockInId);
            $userId = !empty($stockIn['confirmed_by']) ? $stockIn['confirmed_by'] : (!empty($stockIn['created_by']) ? $stockIn['created_by'] : 1);

            $lines = array(
                array(
                    'account_id'    => $inventoryId,
                    'cost_center_id' => null,
                    'debit_amount'  => $untaxedAmount,
                    'credit_amount' => 0,
                    'description'   => $desc . ' (存貨)',
                ),
            );

            if ($inputTaxId && $taxAmount > 0) {
                $lines[] = array(
                    'account_id'    => $inputTaxId,
                    'cost_center_id' => null,
                    'debit_amount'  => $taxAmount,
                    'credit_amount' => 0,
                    'description'   => $desc . ' (進項稅額)',
                );
            } else {
                // No tax account, add tax to inventory
                $lines[0]['debit_amount'] = $totalAmount;
            }

            $lines[] = array(
                'account_id'    => $apId,
                'cost_center_id' => null,
                'debit_amount'  => 0,
                'credit_amount' => $totalAmount,
                'description'   => $desc . ' (應付帳款)',
            );

            $data = array(
                'voucher_date'  => !empty($stockIn['si_date']) ? $stockIn['si_date'] : date('Y-m-d'),
                'voucher_type'  => 'general',
                'description'   => $desc,
                'source_module' => 'stock_in',
                'source_id'     => $stockInId,
                'created_by'    => $userId,
                'lines'         => $lines,
            );

            return self::createAndPost($data);
        } catch (Exception $e) {
            error_log('AutoJournal::onStockInConfirmed error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 出貨出庫確認 -> 借:銷貨成本  貸:存貨
     * @param int $stockOutId
     * @return int|false
     */
    public static function onStockOutConfirmed($stockOutId)
    {
        try {
            require_once __DIR__ . '/../inventory/StockModel.php';
            $sm = new StockModel();
            $stockOut = $sm->getStockOutById($stockOutId);
            if (!$stockOut) return false;

            $am = new AccountingModel();

            // Calculate total from items
            // 新語意（Migration 111 之後）：用 shipped_qty 計算實際銷貨成本
            // fallback 到 quantity 以相容舊資料（shipped_qty 欄位不存在時）
            $items = isset($stockOut['items']) ? $stockOut['items'] : $sm->getStockOutItems($stockOutId);
            $totalCost = 0;
            foreach ($items as $item) {
                $shipped = isset($item['shipped_qty']) ? (float)$item['shipped_qty'] : 0;
                // 若無 shipped_qty（舊資料）或為 0 但已確認，退回用 quantity
                if ($shipped <= 0 && !empty($item['is_confirmed'])) {
                    $shipped = isset($item['quantity']) ? (float)$item['quantity'] : 0;
                }
                $price = isset($item['unit_cost']) ? (float)$item['unit_cost'] : (isset($item['unit_price']) ? (float)$item['unit_price'] : 0);
                $totalCost += $shipped * $price;
            }
            if ($totalCost <= 0) return false;

            $cogsId = self::getAccountId($am, '5101'); // COGS
            $inventoryId = self::getAccountId($am, '1151'); // Inventory

            if (!$cogsId || !$inventoryId) return false;

            $soNumber = !empty($stockOut['stockout_number']) ? $stockOut['stockout_number'] : (!empty($stockOut['so_number']) ? $stockOut['so_number'] : 'SO#' . $stockOutId);
            $desc = '出庫 - ' . $soNumber;
            $userId = !empty($stockOut['confirmed_by']) ? $stockOut['confirmed_by'] : (!empty($stockOut['created_by']) ? $stockOut['created_by'] : 1);

            $data = array(
                'voucher_date'  => !empty($stockOut['so_date']) ? $stockOut['so_date'] : date('Y-m-d'),
                'voucher_type'  => 'general',
                'description'   => $desc,
                'source_module' => 'stock_out',
                'source_id'     => $stockOutId,
                'created_by'    => $userId,
                'lines' => array(
                    array(
                        'account_id'    => $cogsId,
                        'cost_center_id' => null,
                        'debit_amount'  => $totalCost,
                        'credit_amount' => 0,
                        'description'   => $desc . ' (銷貨成本)',
                    ),
                    array(
                        'account_id'    => $inventoryId,
                        'cost_center_id' => null,
                        'debit_amount'  => 0,
                        'credit_amount' => $totalCost,
                        'description'   => $desc . ' (存貨)',
                    ),
                ),
            );

            return self::createAndPost($data);
        } catch (Exception $e) {
            error_log('AutoJournal::onStockOutConfirmed error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 銷項發票開立 -> 借:應收帳款  貸:營業收入+銷項稅額
     * @param int $invoiceId
     * @return int|false
     */
    public static function onSalesInvoiceCreated($invoiceId)
    {
        try {
            require_once __DIR__ . '/InvoiceModel.php';
            $im = new InvoiceModel();
            $invoice = $im->getSalesInvoiceById($invoiceId);
            if (!$invoice) return false;

            $am = new AccountingModel();
            $totalAmount = (float)$invoice['total_amount'];
            if ($totalAmount <= 0) return false;

            $taxAmount = isset($invoice['tax_amount']) ? (float)$invoice['tax_amount'] : 0;
            $untaxedAmount = isset($invoice['amount_untaxed']) ? (float)$invoice['amount_untaxed'] : ($totalAmount - $taxAmount);

            $arId = self::getAccountId($am, '1131'); // AR
            $revenueId = self::getAccountId($am, '4101'); // Revenue
            $outputTaxId = self::getAccountId($am, '2401'); // Output tax

            if (!$arId || !$revenueId) return false;

            $desc = '銷項發票 - ' . (!empty($invoice['invoice_number']) ? $invoice['invoice_number'] : 'SI#' . $invoiceId);
            if (!empty($invoice['customer_name'])) {
                $desc .= ' (' . $invoice['customer_name'] . ')';
            }

            $userId = !empty($invoice['created_by']) ? $invoice['created_by'] : 1;

            $lines = array(
                array(
                    'account_id'    => $arId,
                    'cost_center_id' => null,
                    'debit_amount'  => $totalAmount,
                    'credit_amount' => 0,
                    'description'   => $desc . ' (應收帳款)',
                ),
                array(
                    'account_id'    => $revenueId,
                    'cost_center_id' => null,
                    'debit_amount'  => 0,
                    'credit_amount' => $untaxedAmount,
                    'description'   => $desc . ' (工程收入)',
                ),
            );

            if ($outputTaxId && $taxAmount > 0) {
                $lines[] = array(
                    'account_id'    => $outputTaxId,
                    'cost_center_id' => null,
                    'debit_amount'  => 0,
                    'credit_amount' => $taxAmount,
                    'description'   => $desc . ' (銷項稅額)',
                );
            } else {
                // No tax account, add all to revenue
                $lines[1]['credit_amount'] = $totalAmount;
            }

            $data = array(
                'voucher_date'  => !empty($invoice['invoice_date']) ? $invoice['invoice_date'] : date('Y-m-d'),
                'voucher_type'  => 'general',
                'description'   => $desc,
                'source_module' => 'sales_invoice',
                'source_id'     => $invoiceId,
                'created_by'    => $userId,
                'lines'         => $lines,
            );

            return self::createAndPost($data);
        } catch (Exception $e) {
            error_log('AutoJournal::onSalesInvoiceCreated error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 進項發票 -> 借:費用/存貨+進項稅額  貸:應付帳款
     * @param int $invoiceId
     * @return int|false
     */
    public static function onPurchaseInvoiceCreated($invoiceId)
    {
        try {
            require_once __DIR__ . '/InvoiceModel.php';
            $im = new InvoiceModel();
            $invoice = $im->getPurchaseInvoiceById($invoiceId);
            if (!$invoice) return false;

            $am = new AccountingModel();
            $totalAmount = (float)$invoice['total_amount'];
            if ($totalAmount <= 0) return false;

            $taxAmount = isset($invoice['tax_amount']) ? (float)$invoice['tax_amount'] : 0;
            $untaxedAmount = isset($invoice['amount_untaxed']) ? (float)$invoice['amount_untaxed'] : ($totalAmount - $taxAmount);

            // Debit: expense or inventory (use 5101 engineering cost as default)
            $expenseId = self::getAccountId($am, '5101'); // Engineering cost
            $inputTaxId = self::getAccountId($am, '1301'); // Input tax
            $apId = self::getAccountId($am, '2101'); // AP

            if (!$expenseId || !$apId) return false;

            $desc = '進項發票 - ' . (!empty($invoice['invoice_number']) ? $invoice['invoice_number'] : 'PI#' . $invoiceId);
            if (!empty($invoice['vendor_name'])) {
                $desc .= ' (' . $invoice['vendor_name'] . ')';
            }

            $userId = !empty($invoice['created_by']) ? $invoice['created_by'] : 1;

            $lines = array(
                array(
                    'account_id'    => $expenseId,
                    'cost_center_id' => null,
                    'debit_amount'  => $untaxedAmount,
                    'credit_amount' => 0,
                    'description'   => $desc . ' (費用/存貨)',
                ),
            );

            if ($inputTaxId && $taxAmount > 0) {
                $lines[] = array(
                    'account_id'    => $inputTaxId,
                    'cost_center_id' => null,
                    'debit_amount'  => $taxAmount,
                    'credit_amount' => 0,
                    'description'   => $desc . ' (進項稅額)',
                );
            } else {
                $lines[0]['debit_amount'] = $totalAmount;
            }

            $lines[] = array(
                'account_id'    => $apId,
                'cost_center_id' => null,
                'debit_amount'  => 0,
                'credit_amount' => $totalAmount,
                'description'   => $desc . ' (應付帳款)',
            );

            $data = array(
                'voucher_date'  => !empty($invoice['invoice_date']) ? $invoice['invoice_date'] : date('Y-m-d'),
                'voucher_type'  => 'general',
                'description'   => $desc,
                'source_module' => 'purchase_invoice',
                'source_id'     => $invoiceId,
                'created_by'    => $userId,
                'lines'         => $lines,
            );

            return self::createAndPost($data);
        } catch (Exception $e) {
            error_log('AutoJournal::onPurchaseInvoiceCreated error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 零用金支出 -> 借:費用科目  貸:零用金
     * @param int $pettyCashId
     * @return int|false
     */
    public static function onPettyCashExpense($pettyCashId)
    {
        try {
            require_once __DIR__ . '/../finance/FinanceModel.php';
            $fm = new FinanceModel();
            $pc = $fm->getPettyCashById($pettyCashId);
            if (!$pc) return false;

            // Only create journal for expense type
            $type = !empty($pc['type']) ? $pc['type'] : '';
            $amount = 0;
            if ($type === '支出') {
                $amount = isset($pc['expense_amount']) ? (float)$pc['expense_amount'] : 0;
            } else {
                // Income to petty cash: debit petty cash, credit revenue/other
                $amount = isset($pc['income_amount']) ? (float)$pc['income_amount'] : 0;
            }
            if ($amount <= 0) return false;

            $am = new AccountingModel();
            $pettyCashAccountId = self::getAccountId($am, '1103'); // Petty cash
            $expenseAccountId = self::getAccountId($am, '5101'); // Engineering cost (default expense)

            if (!$pettyCashAccountId || !$expenseAccountId) return false;

            $desc = '零用金' . $type . ' - ' . (!empty($pc['entry_number']) ? $pc['entry_number'] : 'PC#' . $pettyCashId);
            if (!empty($pc['description'])) {
                $desc .= ' (' . mb_substr($pc['description'], 0, 30) . ')';
            }

            $userId = 1; // system user for petty cash

            if ($type === '支出') {
                $lines = array(
                    array(
                        'account_id'    => $expenseAccountId,
                        'cost_center_id' => null,
                        'debit_amount'  => $amount,
                        'credit_amount' => 0,
                        'description'   => $desc,
                    ),
                    array(
                        'account_id'    => $pettyCashAccountId,
                        'cost_center_id' => null,
                        'debit_amount'  => 0,
                        'credit_amount' => $amount,
                        'description'   => $desc,
                    ),
                );
            } else {
                // Income: debit petty cash, credit revenue
                $revenueId = self::getAccountId($am, '4101');
                if (!$revenueId) $revenueId = $expenseAccountId;
                $lines = array(
                    array(
                        'account_id'    => $pettyCashAccountId,
                        'cost_center_id' => null,
                        'debit_amount'  => $amount,
                        'credit_amount' => 0,
                        'description'   => $desc,
                    ),
                    array(
                        'account_id'    => $revenueId,
                        'cost_center_id' => null,
                        'debit_amount'  => 0,
                        'credit_amount' => $amount,
                        'description'   => $desc,
                    ),
                );
            }

            $data = array(
                'voucher_date'  => !empty($pc['entry_date']) ? $pc['entry_date'] : date('Y-m-d'),
                'voucher_type'  => 'payment',
                'description'   => $desc,
                'source_module' => 'petty_cash',
                'source_id'     => $pettyCashId,
                'created_by'    => $userId,
                'lines'         => $lines,
            );

            return self::createAndPost($data);
        } catch (Exception $e) {
            error_log('AutoJournal::onPettyCashExpense error: ' . $e->getMessage());
            return false;
        }
    }
}
