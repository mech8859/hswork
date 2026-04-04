#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Generate 4 separate SQL import files from Ragic Excel exports.
Resolves branch names and sales names to IDs directly.
"""
import pandas as pd
import re

RAGIC_DIR = '/Volumes/ADATA UC300/ragic下載資料'
OUT_DIR = '/Users/chifangtang/hswork/database'

BRANCH_MAP = {
    '潭子分公司': 1, '清水分公司': 2, '員林分公司': 3,
    '東區電子鎖': 4, '清水電子鎖': 5, '中區專案部': 6,
    '中區技術組': 18, '清水門市': 19, '東區門市': 20, '中區管理處': 21,
}

# User name → ID (will be populated from server data)
# For now, store names and let the PHP resolve them
# Actually, let's just store names in note fields and skip user ID resolution

def esc(val):
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return 'NULL'
    s = str(val).strip()
    if s in ('', 'NaN', 'nan'):
        return 'NULL'
    s = s.replace("\\", "\\\\").replace("'", "\\'")
    s = s.replace("\r\n", "\\n").replace("\r", "\\n").replace("\n", "\\n")
    return "'" + s + "'"

def esc_date(val):
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return 'NULL'
    s = str(val).strip()
    if s in ('', 'NaN', 'nan', 'NaT'):
        return 'NULL'
    m = re.match(r'(\d{4}-\d{2}-\d{2})', s)
    return "'" + m.group(1) + "'" if m else 'NULL'

def esc_num(val):
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return '0'
    s = str(val).strip().replace(',', '')
    if s in ('', 'NaN', 'nan'):
        return '0'
    try:
        f = float(s)
        return str(int(round(f)))
    except:
        return '0'

def get(row, col):
    if col not in row.index:
        return None
    v = row[col]
    return None if pd.isna(v) else str(v).strip()

def bid(name):
    if not name:
        return 'NULL'
    return str(BRANCH_MAP.get(name.strip(), 'NULL'))

# ==================== 應收帳款 ====================
def gen_receivables():
    df = pd.read_excel(f'{RAGIC_DIR}/應收帳款.xlsx', dtype=str)
    lines = ['SET NAMES utf8mb4;', '']

    for _, row in df.iterrows():
        inv = get(row, '請款單號')
        if not inv:
            continue
        cust = get(row, '客戶名稱(新建)') or get(row, '客戶名稱(原有)') or ''

        lines.append(
            "INSERT INTO receivables (invoice_number, invoice_date, customer_name, "
            "branch_id, invoice_category, status, "
            "invoice_title, tax_id, phone, mobile, invoice_email, invoice_address, "
            "payment_method, payment_terms, subtotal, note) VALUES ("
            + ', '.join([
                esc(inv), esc_date(get(row, '請款日期')), esc(cust),
                bid(get(row, '所屬分公司')),
                esc(get(row, '請款類別')), esc(get(row, '狀態')),
                esc(get(row, '發票抬頭')), esc(get(row, '統一編號')),
                esc(get(row, '家用/公司電話')), esc(get(row, '行動電話')),
                esc(get(row, '發票Email')), esc(get(row, '發票地址')),
                esc(get(row, '付款方式')), esc(get(row, '付款條件')),
                esc_num(get(row, '小計')),
                esc((get(row, '備註') or '') + (' [業務:' + get(row, '承辦業務') + ']' if get(row, '承辦業務') else '')),
            ]) + ');'
        )

        case_num = get(row, '進件編號')
        if case_num:
            lines.append(
                "INSERT INTO receivable_items (receivable_id, main_case_number, amount) "
                f"VALUES (LAST_INSERT_ID(), {esc(case_num)}, {esc_num(get(row, '小計'))});"
            )

    with open(f'{OUT_DIR}/import_01_receivables.sql', 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines))
    print(f'應收帳款: {len([l for l in lines if l.startswith("INSERT INTO receivables ")])} rows')

# ==================== 收款單 ====================
def gen_receipts():
    df = pd.read_excel(f'{RAGIC_DIR}/收款單.xlsx', dtype=str)
    lines = ['SET NAMES utf8mb4;', '']

    for _, row in df.iterrows():
        rnum = get(row, '收款單編號')
        if not rnum:
            continue
        cust = get(row, '客戶名稱(新建)') or get(row, '客戶名稱(原有)') or ''

        lines.append(
            "INSERT INTO receipts (receipt_number, register_date, deposit_date, "
            "customer_name, branch_id, "
            "subtotal, tax, discount, total_amount, receipt_method, "
            "invoice_category, status, bank_ref, note) VALUES ("
            + ', '.join([
                esc(rnum), esc_date(get(row, '登記日期')), esc_date(get(row, '入帳日期')),
                esc(cust), bid(get(row, '所屬分公司')),
                esc_num(get(row, '小計')), esc_num(get(row, '稅金')),
                esc_num(get(row, '折讓or匯費金額')), esc_num(get(row, '收款總計')),
                esc(get(row, '收款方式')),
                esc(get(row, '請款類別')), esc(get(row, '狀態')),
                esc(get(row, '銀行明細上傳編號')),
                esc((get(row, '備註') or '') + (' [業務:' + get(row, '承辦業務') + ']' if get(row, '承辦業務') else '')),
            ]) + ');'
        )

        case_num = get(row, '進件編號')
        if case_num:
            lines.append(
                "INSERT INTO receipt_items (receipt_id, main_case_number, amount) "
                f"VALUES (LAST_INSERT_ID(), {esc(case_num)}, {esc_num(get(row, '小計'))});"
            )

    with open(f'{OUT_DIR}/import_02_receipts.sql', 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines))
    print(f'收款單: {len([l for l in lines if l.startswith("INSERT INTO receipts ")])} rows')

# ==================== 應付帳款單 ====================
def gen_payables():
    df = pd.read_excel(f'{RAGIC_DIR}/應付帳款單.xlsx', dtype=str)
    lines = ['SET NAMES utf8mb4;', '']

    for _, row in df.iterrows():
        pnum = get(row, '付款單號')
        if not pnum:
            continue

        lines.append(
            "INSERT INTO payables (payable_number, create_date, vendor_name, "
            "payment_period, payment_terms, subtotal, tax, total_amount, "
            "prepaid, payable_amount, note) VALUES ("
            + ', '.join([
                esc(pnum), esc_date(get(row, '建立日期')), esc(get(row, '廠商名稱')),
                esc(get(row, '付款期別')), esc(get(row, '付款條件')),
                esc_num(get(row, '未稅總額')), esc_num(get(row, '稅金')),
                esc_num(get(row, '總計')),
                esc_num(get(row, '預付總額')), esc_num(get(row, '應付總額')),
                esc(get(row, '備註.6')),
            ]) + ');'
        )
        lines.append("SET @pid = LAST_INSERT_ID();")

        # Branches (up to 6)
        for i in range(7):
            sfx = '' if i == 0 else f'.{i}'
            bn = get(row, f'所屬分公司{sfx}')
            amt = get(row, f'未稅金額{sfx}')
            note = get(row, f'備註{sfx}')
            if bn and amt and esc_num(amt) != '0':
                lines.append(
                    "INSERT INTO payable_branches (payable_id, branch_id, amount, note) "
                    f"VALUES (@pid, {bid(bn)}, {esc_num(amt)}, {esc(note)});"
                )

        # Invoice
        inv_u = get(row, '未稅')
        inv_t = get(row, '稅額')
        inv_s = get(row, '小計')
        if inv_u or inv_t:
            lines.append(
                "INSERT INTO payable_invoices (payable_id, amount_untaxed, tax, subtotal) "
                f"VALUES (@pid, {esc_num(inv_u)}, {esc_num(inv_t)}, {esc_num(inv_s)});"
            )

    with open(f'{OUT_DIR}/import_03_payables.sql', 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines))
    print(f'應付帳款單: {len([l for l in lines if l.startswith("INSERT INTO payables ")])} rows')

# ==================== 付款單 ====================
def gen_payments_out():
    df = pd.read_excel(f'{RAGIC_DIR}/付款單.xlsx', dtype=str)
    lines = ['SET NAMES utf8mb4;', '']

    for _, row in df.iterrows():
        pnum = get(row, '付款單編號')
        if not pnum:
            continue

        lines.append(
            "INSERT INTO payments_out (payment_number, create_date, payment_date, "
            "vendor_name, payment_method, payment_type, payment_terms, status, "
            "subtotal, tax, remittance_fee, total_amount, "
            "main_category, sub_category, note) VALUES ("
            + ', '.join([
                esc(pnum), esc_date(get(row, '建立日期')), esc_date(get(row, '付款日期')),
                esc(get(row, '廠商名稱')), esc(get(row, '付款方式')),
                esc(get(row, '付款類別')), esc(get(row, '付款條件')),
                esc(get(row, '狀態')),
                esc_num(get(row, '未稅')), esc_num(get(row, '稅金')),
                esc_num(get(row, '匯費')), esc_num(get(row, '付款金額')),
                esc(get(row, '主分類')), esc(get(row, '細分類')),
                esc(get(row, '備註')),
            ]) + ');'
        )
        lines.append("SET @pid = LAST_INSERT_ID();")

        # Branches (columns: 所屬分公司1-6, 未稅金額1-6, 備註1-6)
        for i in range(1, 7):
            bn = get(row, f'所屬分公司{i}')
            amt = get(row, f'未稅金額{i}')
            note = get(row, f'備註{i}')
            if bn and amt and esc_num(amt) != '0':
                lines.append(
                    "INSERT INTO payment_out_branches (payment_out_id, branch_id, amount, note) "
                    f"VALUES (@pid, {bid(bn)}, {esc_num(amt)}, {esc(note)});"
                )

        # Vouchers (columns: 憑證種類1-6, 憑證金額1-6)
        for i in range(1, 7):
            vt = get(row, f'憑證種類{i}')
            va = get(row, f'憑證金額{i}')
            if vt and va and esc_num(va) != '0':
                lines.append(
                    "INSERT INTO payment_out_vouchers (payment_out_id, voucher_type, amount) "
                    f"VALUES (@pid, {esc(vt)}, {esc_num(va)});"
                )

    with open(f'{OUT_DIR}/import_04_payments_out.sql', 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines))
    print(f'付款單: {len([l for l in lines if l.startswith("INSERT INTO payments_out ")])} rows')

if __name__ == '__main__':
    gen_receivables()
    gen_receipts()
    gen_payables()
    gen_payments_out()
    print('\nDone! Files in', OUT_DIR)
