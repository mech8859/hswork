#!/usr/bin/env python3
"""Generate SQL import files from Ragic-exported Excel files for:
1. bank_transactions (4 files, different bank accounts)
2. petty_cash (4 files, different branches)
3. reserve_fund (1 file)
4. cash_details (1 file)
"""
import pandas as pd
import os, re, sys

USB = '/Volumes/ADATA UC300/ragic下載資料'
OUT = os.path.join(os.path.dirname(__file__), '..', 'database')

BRANCH_MAP = {
    '潭子分公司': 1, '清水分公司': 2, '員林分公司': 3,
    '東區電子鎖': 4, '清水電子鎖': 5, '中區專案部': 6,
    '中區技術組': 18, '清水門市': 19, '東區門市': 20, '中區管理處': 21,
}

def esc(val):
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return 'NULL'
    s = str(val).strip()
    if s == '' or s == 'nan':
        return 'NULL'
    s = s.replace("\\", "\\\\").replace("'", "\\'")
    return "'" + s + "'"

def esc_date(val):
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return 'NULL'
    if isinstance(val, pd.Timestamp):
        return "'" + val.strftime('%Y-%m-%d') + "'"
    s = str(val).strip()
    if s == '' or s == 'nan' or s == 'NaT':
        return 'NULL'
    # Try to parse date string
    s = re.sub(r'[/.]', '-', s)
    return "'" + s + "'"

def esc_num(val):
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return '0'
    try:
        n = float(str(val).replace(',', ''))
        return str(int(n))
    except:
        return '0'

def col(df, name, row_idx):
    """Get column value, trying exact match first, then partial match."""
    if name in df.columns:
        return df.at[row_idx, name]
    for c in df.columns:
        if name in c:
            return df.at[row_idx, c]
    return None

# ============================================================
# 1. Bank Transactions
# ============================================================
def gen_bank_transactions():
    files = [
        '彰化銀行帳戶交易明細.xlsx',
        '政遠銀行帳戶交易明細.xlsx',
        '禾順中信銀行帳戶交易明細 .xlsx',
        '禾順富邦銀行帳戶交易明細.xlsx',
    ]
    lines = ["-- Import: bank_transactions\n"]
    total = 0
    for fname in files:
        fpath = os.path.join(USB, fname)
        if not os.path.exists(fpath):
            print(f"WARNING: {fpath} not found, skipping")
            continue
        df = pd.read_excel(fpath)
        print(f"  {fname}: {len(df)} rows, columns: {list(df.columns)}")
        for i in range(len(df)):
            vals = [
                esc(col(df, '系統編號', i)),
                esc(col(df, '上傳編號', i)),
                esc(col(df, '銀行帳戶', i)),
                esc_date(col(df, '交易日期', i)),
                esc_date(col(df, '記帳日', i)),
                esc(col(df, '交易時間', i)),
                esc(col(df, '現轉別', i)),
                esc(col(df, '摘要', i)),
                esc(col(df, '幣別', i)),
                esc_num(col(df, '支出金額', i)),
                esc_num(col(df, '存入金額', i)),
                esc_num(col(df, '餘額', i)),
                esc(col(df, '備註', i)),
                esc(col(df, '轉出入帳號', i)),
                esc(col(df, '存匯代號', i)),
                esc(col(df, '對方帳號', i)),
                esc(col(df, '註記', i)),
                esc(col(df, '對象說明', i)),
            ]
            lines.append(
                "INSERT INTO bank_transactions (sys_number, upload_number, bank_account, transaction_date, posting_date, transaction_time, cash_transfer, summary, currency, debit_amount, credit_amount, balance, note, transfer_account, bank_code, counter_account, remark, description) VALUES (" +
                ', '.join(vals) + ');\n'
            )
            total += 1
    path = os.path.join(OUT, 'import_05_bank_transactions.sql')
    with open(path, 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print(f"=> {path}: {total} records")
    return total

# ============================================================
# 2. Petty Cash
# ============================================================
def gen_petty_cash():
    files_branches = [
        ('潭子分公司零用金管理.xlsx', '潭子分公司'),
        ('清水分公司零用金管理.xlsx', '清水分公司'),
        ('員林分公司零用金管理.xlsx', '員林分公司'),
        ('東區電子鎖零用金管理.xlsx', '東區電子鎖'),
    ]
    lines = ["-- Import: petty_cash\n"]
    total = 0
    for fname, branch_name in files_branches:
        fpath = os.path.join(USB, fname)
        if not os.path.exists(fpath):
            print(f"WARNING: {fpath} not found, skipping")
            continue
        df = pd.read_excel(fpath)
        branch_id = BRANCH_MAP.get(branch_name, 'NULL')
        print(f"  {fname}: {len(df)} rows, columns: {list(df.columns)}")

        for i in range(len(df)):
            # Column names vary across files
            entry_num = col(df, '零用金編號', i)
            entry_date = col(df, '日期', i)
            # expense_date: 款項日期 or 支出日期
            expense_date = col(df, '款項日期', i)
            if expense_date is None:
                expense_date = col(df, '支出日期', i)
            type_val = col(df, '收支別', i)
            has_invoice = col(df, '有無發票', i)

            # Invoice info: might be multiple columns or single
            invoice_parts = []
            for cn in df.columns:
                if '發票資訊' in cn:
                    v = df.at[i, cn]
                    if v is not None and not (isinstance(v, float) and pd.isna(v)):
                        s = str(v).strip()
                        if s and s != 'nan':
                            invoice_parts.append(s)
            invoice_info = ' '.join(invoice_parts) if invoice_parts else None

            expense_untaxed = col(df, '支出未稅金額', i)
            expense_tax = col(df, '支出稅額', i)
            # expense_amount: 支出總金額 or 支出金額
            expense_amount = col(df, '支出總金額', i)
            if expense_amount is None:
                expense_amount = col(df, '支出金額', i)
            income_amount = col(df, '收入金額', i)
            description = col(df, '用途說明', i)
            registrar = col(df, '登記人', i)
            approval_status = col(df, '簽核狀態', i)
            approval_date = col(df, '簽核日期', i)
            user_name = col(df, '使用者', i)
            upload_number = col(df, '上傳編號', i)

            vals = [
                esc(entry_num),
                esc_date(entry_date),
                esc_date(expense_date),
                str(branch_id),
                esc(type_val),
                esc(has_invoice),
                esc(invoice_info),
                esc_num(expense_untaxed),
                esc_num(expense_tax),
                esc_num(expense_amount),
                esc_num(income_amount),
                esc(description),
                esc(registrar),
                esc(approval_status),
                esc_date(approval_date),
                esc(user_name),
                esc(upload_number),
            ]
            lines.append(
                "INSERT INTO petty_cash (entry_number, entry_date, expense_date, branch_id, type, has_invoice, invoice_info, expense_untaxed, expense_tax, expense_amount, income_amount, description, registrar, approval_status, approval_date, user_name, upload_number) VALUES (" +
                ', '.join(vals) + ');\n'
            )
            total += 1
    path = os.path.join(OUT, 'import_06_petty_cash.sql')
    with open(path, 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print(f"=> {path}: {total} records")
    return total

# ============================================================
# 3. Reserve Fund
# ============================================================
def gen_reserve_fund():
    fpath = os.path.join(USB, '備用金管理.xlsx')
    df = pd.read_excel(fpath)
    print(f"  備用金管理.xlsx: {len(df)} rows, columns: {list(df.columns)}")
    lines = ["-- Import: reserve_fund\n"]
    total = 0
    for i in range(len(df)):
        branch_name = col(df, '所屬分公司', i)
        branch_id = 'NULL'
        if branch_name and not (isinstance(branch_name, float) and pd.isna(branch_name)):
            bn = str(branch_name).strip()
            branch_id = str(BRANCH_MAP.get(bn, 'NULL'))

        vals = [
            esc(col(df, '備用金編號', i)),
            esc_date(col(df, '日期', i)),
            esc_date(col(df, '支出日期', i)),
            branch_id,
            esc(col(df, '收支別', i)),
            esc_num(col(df, '支出金額', i)),
            esc_num(col(df, '收入金額', i)),
            esc(col(df, '用途說明', i)),
            esc(col(df, '發票資訊', i)),
            esc(col(df, '登記人', i)),
            esc(col(df, '簽核狀態', i)),
            esc_date(col(df, '簽核日期', i)),
            esc(col(df, '使用者', i)),
            esc(col(df, '上傳編號', i)),
        ]
        lines.append(
            "INSERT INTO reserve_fund (entry_number, entry_date, expense_date, branch_id, type, expense_amount, income_amount, description, invoice_info, registrar, approval_status, approval_date, user_name, upload_number) VALUES (" +
            ', '.join(vals) + ');\n'
        )
        total += 1
    path = os.path.join(OUT, 'import_07_reserve_fund.sql')
    with open(path, 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print(f"=> {path}: {total} records")
    return total

# ============================================================
# 4. Cash Details
# ============================================================
def gen_cash_details():
    fpath = os.path.join(USB, '現金明細.xlsx')
    df = pd.read_excel(fpath)
    print(f"  現金明細.xlsx: {len(df)} rows, columns: {list(df.columns)}")
    lines = ["-- Import: cash_details\n"]
    total = 0
    for i in range(len(df)):
        branch_name = col(df, '所屬分公司', i)
        branch_id = 'NULL'
        if branch_name and not (isinstance(branch_name, float) and pd.isna(branch_name)):
            bn = str(branch_name).strip()
            branch_id = str(BRANCH_MAP.get(bn, 'NULL'))

        vals = [
            esc(col(df, '現金編號', i)),
            esc_date(col(df, '登記日期', i)),
            esc_date(col(df, '交易日期', i)),
            branch_id,
            esc(col(df, '承辦業務', i)),
            esc(col(df, '明細', i)),
            esc_num(col(df, '收入金額', i)),
            esc_num(col(df, '支出金額', i)),
            esc(col(df, '上傳編號', i)),
        ]
        lines.append(
            "INSERT INTO cash_details (entry_number, register_date, transaction_date, branch_id, sales_name, description, income_amount, expense_amount, upload_number) VALUES (" +
            ', '.join(vals) + ');\n'
        )
        total += 1
    path = os.path.join(OUT, 'import_08_cash_details.sql')
    with open(path, 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print(f"=> {path}: {total} records")
    return total

# ============================================================
if __name__ == '__main__':
    print("=== Generating import SQL files ===\n")
    t1 = gen_bank_transactions()
    print()
    t2 = gen_petty_cash()
    print()
    t3 = gen_reserve_fund()
    print()
    t4 = gen_cash_details()
    print(f"\n=== Total: {t1+t2+t3+t4} records across 4 modules ===")
