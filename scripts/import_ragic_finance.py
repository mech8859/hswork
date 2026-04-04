#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Convert Ragic finance Excel exports to SQL INSERT statements.
Generates a single .sql file for import via PHP script.
"""
import pandas as pd
import re
import sys
from datetime import datetime

RAGIC_DIR = '/Volumes/ADATA UC300/ragic下載資料'
OUTPUT_FILE = '/Users/chifangtang/hswork/database/import_ragic_finance.sql'

# Branch name → ID mapping (will be resolved at runtime via PHP)
# For now, store branch names directly; the PHP script will look them up.

def esc(val):
    """Escape value for SQL"""
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return 'NULL'
    s = str(val).strip()
    if s == '' or s == 'NaN' or s == 'nan':
        return 'NULL'
    s = s.replace("\\", "\\\\").replace("'", "\\'").replace("\r\n", "\\n").replace("\r", "\\n").replace("\n", "\\n")
    return "'" + s + "'"

def esc_date(val):
    """Extract date part from datetime string"""
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return 'NULL'
    s = str(val).strip()
    if s in ('', 'NaN', 'nan', 'NaT'):
        return 'NULL'
    # Extract YYYY-MM-DD from datetime
    m = re.match(r'(\d{4}-\d{2}-\d{2})', s)
    if m:
        return "'" + m.group(1) + "'"
    return 'NULL'

def esc_num(val):
    """Convert to integer, default 0"""
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return '0'
    s = str(val).strip()
    if s in ('', 'NaN', 'nan'):
        return '0'
    # Remove commas and try to parse
    s = s.replace(',', '')
    try:
        return str(int(float(s)))
    except:
        return '0'

def get_val(row, col):
    """Safely get a value from a row"""
    if col not in row.index:
        return None
    v = row[col]
    if pd.isna(v):
        return None
    return str(v).strip()

def import_receivables(f):
    """應收帳款 → receivables + receivable_items"""
    df = pd.read_excel(f'{RAGIC_DIR}/{f}', dtype=str)
    lines = []
    lines.append('-- ========================================')
    lines.append('-- 應收帳款 (receivables) - {} rows'.format(len(df)))
    lines.append('-- ========================================')

    for idx, row in df.iterrows():
        invoice_number = get_val(row, '請款單號') or ''
        if not invoice_number:
            continue

        customer = get_val(row, '客戶名稱(新建)') or get_val(row, '客戶名稱(原有)') or ''

        lines.append(
            "INSERT INTO receivables (invoice_number, invoice_date, customer_name, "
            "branch_name_tmp, sales_name_tmp, invoice_category, status, "
            "invoice_title, tax_id, phone, mobile, invoice_email, invoice_address, "
            "payment_method, payment_terms, subtotal, note, created_at) VALUES ("
            "{}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, NOW());".format(
                esc(invoice_number),
                esc_date(get_val(row, '請款日期')),
                esc(customer),
                esc(get_val(row, '所屬分公司')),
                esc(get_val(row, '承辦業務')),
                esc(get_val(row, '請款類別')),
                esc(get_val(row, '狀態')),
                esc(get_val(row, '發票抬頭')),
                esc(get_val(row, '統一編號')),
                esc(get_val(row, '家用/公司電話')),
                esc(get_val(row, '行動電話')),
                esc(get_val(row, '發票Email')),
                esc(get_val(row, '發票地址')),
                esc(get_val(row, '付款方式')),
                esc(get_val(row, '付款條件')),
                esc_num(get_val(row, '小計')),
                esc(get_val(row, '備註')),
            )
        )

        # receivable_items - use 進件編號 as the main case number
        case_num = get_val(row, '進件編號')
        subtotal = get_val(row, '小計')
        if case_num:
            lines.append(
                "INSERT INTO receivable_items (receivable_id, main_case_number, amount, note) "
                "VALUES (LAST_INSERT_ID(), {}, {}, NULL);".format(
                    esc(case_num),
                    esc_num(subtotal),
                )
            )

    return lines

def import_receipts(f):
    """收款單 → receipts + receipt_items"""
    df = pd.read_excel(f'{RAGIC_DIR}/{f}', dtype=str)
    lines = []
    lines.append('-- ========================================')
    lines.append('-- 收款單 (receipts) - {} rows'.format(len(df)))
    lines.append('-- ========================================')

    for idx, row in df.iterrows():
        receipt_number = get_val(row, '收款單編號') or ''
        if not receipt_number:
            continue

        customer = get_val(row, '客戶名稱(新建)') or get_val(row, '客戶名稱(原有)') or ''

        lines.append(
            "INSERT INTO receipts (receipt_number, register_date, deposit_date, "
            "customer_name, branch_name_tmp, sales_name_tmp, "
            "subtotal, tax, discount, total_amount, receipt_method, "
            "invoice_category, status, bank_ref, note, created_at) VALUES ("
            "{}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, NOW());".format(
                esc(receipt_number),
                esc_date(get_val(row, '登記日期')),
                esc_date(get_val(row, '入帳日期')),
                esc(customer),
                esc(get_val(row, '所屬分公司')),
                esc(get_val(row, '承辦業務')),
                esc_num(get_val(row, '小計')),
                esc_num(get_val(row, '稅金')),
                esc_num(get_val(row, '折讓or匯費金額')),
                esc_num(get_val(row, '收款總計')),
                esc(get_val(row, '收款方式')),
                esc(get_val(row, '請款類別')),
                esc(get_val(row, '狀態')),
                esc(get_val(row, '銀行明細上傳編號')),
                esc(get_val(row, '備註')),
            )
        )

        # receipt_items
        case_num = get_val(row, '進件編號')
        subtotal = get_val(row, '小計')
        if case_num:
            lines.append(
                "INSERT INTO receipt_items (receipt_id, main_case_number, amount, note) "
                "VALUES (LAST_INSERT_ID(), {}, {}, NULL);".format(
                    esc(case_num),
                    esc_num(subtotal),
                )
            )

    return lines

def import_payables(f):
    """應付帳款單 → payables + payable_branches + payable_invoices"""
    df = pd.read_excel(f'{RAGIC_DIR}/{f}', dtype=str)
    lines = []
    lines.append('-- ========================================')
    lines.append('-- 應付帳款單 (payables) - {} rows'.format(len(df)))
    lines.append('-- ========================================')

    for idx, row in df.iterrows():
        payable_number = get_val(row, '付款單號') or ''
        if not payable_number:
            continue

        lines.append(
            "INSERT INTO payables (payable_number, create_date, vendor_name, "
            "payment_period, payment_terms, subtotal, tax, total_amount, "
            "prepaid, payable_amount, note, created_at) VALUES ("
            "{}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, NOW());".format(
                esc(payable_number),
                esc_date(get_val(row, '建立日期')),
                esc(get_val(row, '廠商名稱')),
                esc(get_val(row, '付款期別')),
                esc(get_val(row, '付款條件')),
                esc_num(get_val(row, '未稅總額')),
                esc_num(get_val(row, '稅金')),
                esc_num(get_val(row, '總計')),
                esc_num(get_val(row, '預付總額')),
                esc_num(get_val(row, '應付總額')),
                esc(get_val(row, '備註.6')),
            )
        )

        # payable_branches - up to 6 branch columns
        for i in range(7):
            suffix = '' if i == 0 else '.{}'.format(i)
            branch_col = '所屬分公司' + suffix
            amount_col = '未稅金額' + suffix
            note_col = '備註' + suffix

            branch_name = get_val(row, branch_col)
            amount = get_val(row, amount_col)
            note = get_val(row, note_col)

            if branch_name and amount:
                lines.append(
                    "INSERT INTO payable_branches (payable_id, branch_name_tmp, amount, note) "
                    "VALUES (LAST_INSERT_ID(), {}, {}, {});".format(
                        esc(branch_name),
                        esc_num(amount),
                        esc(note),
                    )
                )

        # payable_invoices - single invoice line from the bottom section
        inv_untaxed = get_val(row, '未稅')
        inv_tax = get_val(row, '稅額')
        inv_subtotal = get_val(row, '小計')
        if inv_untaxed or inv_tax:
            lines.append(
                "INSERT INTO payable_invoices (payable_id, amount_untaxed, tax, subtotal) "
                "VALUES (LAST_INSERT_ID(), {}, {}, {});".format(
                    esc_num(inv_untaxed),
                    esc_num(inv_tax),
                    esc_num(inv_subtotal),
                )
            )

    return lines

def import_payments_out(f):
    """付款單 → payments_out + payment_out_branches + payment_out_vouchers"""
    df = pd.read_excel(f'{RAGIC_DIR}/{f}', dtype=str)
    lines = []
    lines.append('-- ========================================')
    lines.append('-- 付款單 (payments_out) - {} rows'.format(len(df)))
    lines.append('-- ========================================')

    for idx, row in df.iterrows():
        payment_number = get_val(row, '付款單編號') or ''
        if not payment_number:
            continue

        lines.append(
            "INSERT INTO payments_out (payment_number, create_date, payment_date, "
            "vendor_name, payment_method, payment_type, payment_terms, status, "
            "subtotal, tax, remittance_fee, total_amount, "
            "main_category, sub_category, note, created_at) VALUES ("
            "{}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, NOW());".format(
                esc(payment_number),
                esc_date(get_val(row, '建立日期')),
                esc_date(get_val(row, '付款日期')),
                esc(get_val(row, '廠商名稱')),
                esc(get_val(row, '付款方式')),
                esc(get_val(row, '付款類別')),
                esc(get_val(row, '付款條件')),
                esc(get_val(row, '狀態')),
                esc_num(get_val(row, '未稅')),
                esc_num(get_val(row, '稅金')),
                esc_num(get_val(row, '匯費')),
                esc_num(get_val(row, '付款金額')),
                esc(get_val(row, '主分類')),
                esc(get_val(row, '細分類')),
                esc(get_val(row, '備註')),
            )
        )

        # payment_out_branches - up to 6 branch columns
        for i in range(1, 7):
            branch_col = '所屬分公司{}'.format(i)
            amount_col = '未稅金額{}'.format(i)
            note_col = '備註{}'.format(i)

            branch_name = get_val(row, branch_col)
            amount = get_val(row, amount_col)
            note = get_val(row, note_col)

            if branch_name and amount:
                lines.append(
                    "INSERT INTO payment_out_branches (payment_out_id, branch_name_tmp, amount, note) "
                    "VALUES (LAST_INSERT_ID(), {}, {}, {});".format(
                        esc(branch_name),
                        esc_num(amount),
                        esc(note),
                    )
                )

        # payment_out_vouchers - up to 6 voucher columns
        for i in range(1, 7):
            vtype_col = '憑證種類{}'.format(i)
            vamount_col = '憑證金額{}'.format(i)

            vtype = get_val(row, vtype_col)
            vamount = get_val(row, vamount_col)

            if vtype and vamount:
                lines.append(
                    "INSERT INTO payment_out_vouchers (payment_out_id, voucher_type, amount) "
                    "VALUES (LAST_INSERT_ID(), {}, {});".format(
                        esc(vtype),
                        esc_num(vamount),
                    )
                )

    return lines

def main():
    all_lines = []
    all_lines.append('-- Ragic Finance Data Import')
    all_lines.append('-- Generated: {}'.format(datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
    all_lines.append('SET NAMES utf8mb4;')
    all_lines.append('')

    # First: add temporary columns for name-based lookups
    all_lines.append('-- Add temporary columns for branch/sales name resolution')
    all_lines.append("ALTER TABLE receivables ADD COLUMN IF NOT EXISTS branch_name_tmp VARCHAR(50) DEFAULT NULL;")
    all_lines.append("ALTER TABLE receivables ADD COLUMN IF NOT EXISTS sales_name_tmp VARCHAR(50) DEFAULT NULL;")
    all_lines.append("ALTER TABLE receipts ADD COLUMN IF NOT EXISTS branch_name_tmp VARCHAR(50) DEFAULT NULL;")
    all_lines.append("ALTER TABLE receipts ADD COLUMN IF NOT EXISTS sales_name_tmp VARCHAR(50) DEFAULT NULL;")
    all_lines.append("ALTER TABLE payable_branches ADD COLUMN IF NOT EXISTS branch_name_tmp VARCHAR(50) DEFAULT NULL;")
    all_lines.append("ALTER TABLE payment_out_branches ADD COLUMN IF NOT EXISTS branch_name_tmp VARCHAR(50) DEFAULT NULL;")
    all_lines.append('')

    print('Processing 應收帳款...')
    all_lines.extend(import_receivables('應收帳款.xlsx'))
    all_lines.append('')

    print('Processing 收款單...')
    all_lines.extend(import_receipts('收款單.xlsx'))
    all_lines.append('')

    print('Processing 應付帳款單...')
    all_lines.extend(import_payables('應付帳款單.xlsx'))
    all_lines.append('')

    print('Processing 付款單...')
    all_lines.extend(import_payments_out('付款單.xlsx'))
    all_lines.append('')

    # Resolve branch names → IDs
    all_lines.append('-- ========================================')
    all_lines.append('-- Resolve branch_name_tmp → branch_id')
    all_lines.append('-- ========================================')
    all_lines.append("UPDATE receivables r SET r.branch_id = (SELECT b.id FROM branches b WHERE b.name = r.branch_name_tmp LIMIT 1) WHERE r.branch_name_tmp IS NOT NULL;")
    all_lines.append("UPDATE receipts r SET r.branch_id = (SELECT b.id FROM branches b WHERE b.name = r.branch_name_tmp LIMIT 1) WHERE r.branch_name_tmp IS NOT NULL;")
    all_lines.append("UPDATE payable_branches pb SET pb.branch_id = (SELECT b.id FROM branches b WHERE b.name = pb.branch_name_tmp LIMIT 1) WHERE pb.branch_name_tmp IS NOT NULL;")
    all_lines.append("UPDATE payment_out_branches pb SET pb.branch_id = (SELECT b.id FROM branches b WHERE b.name = pb.branch_name_tmp LIMIT 1) WHERE pb.branch_name_tmp IS NOT NULL;")
    all_lines.append('')

    # Resolve sales names → IDs
    all_lines.append('-- Resolve sales_name_tmp → sales_id')
    all_lines.append("UPDATE receivables r SET r.sales_id = (SELECT u.id FROM users u WHERE u.real_name = r.sales_name_tmp LIMIT 1) WHERE r.sales_name_tmp IS NOT NULL;")
    all_lines.append("UPDATE receipts r SET r.sales_id = (SELECT u.id FROM users u WHERE u.real_name = r.sales_name_tmp LIMIT 1) WHERE r.sales_name_tmp IS NOT NULL;")
    all_lines.append('')

    # Drop temporary columns
    all_lines.append('-- Drop temporary columns')
    all_lines.append("ALTER TABLE receivables DROP COLUMN IF EXISTS branch_name_tmp;")
    all_lines.append("ALTER TABLE receivables DROP COLUMN IF EXISTS sales_name_tmp;")
    all_lines.append("ALTER TABLE receipts DROP COLUMN IF EXISTS branch_name_tmp;")
    all_lines.append("ALTER TABLE receipts DROP COLUMN IF EXISTS sales_name_tmp;")
    all_lines.append("ALTER TABLE payable_branches DROP COLUMN IF EXISTS branch_name_tmp;")
    all_lines.append("ALTER TABLE payment_out_branches DROP COLUMN IF EXISTS branch_name_tmp;")
    all_lines.append('')

    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        f.write('\n'.join(all_lines))

    print(f'\nDone! Output: {OUTPUT_FILE}')
    print(f'Total SQL lines: {len(all_lines)}')

if __name__ == '__main__':
    main()
