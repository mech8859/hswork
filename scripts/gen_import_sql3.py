#!/usr/bin/env python3
"""Generate SQL import files for:
1. vendors (廠商資訊.xlsx - 280 rows)
2. inventory (總庫存明細 - 1241 items × 5 warehouses)
3. requisitions (請購單 - 15 headers only)
4. purchase_orders (採購單 - 17 headers only)
5. warehouse_transfers (倉庫調撥 - 38 headers only)
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

# Warehouse IDs matching migration_027 seed order
WAREHOUSE_MAP = {
    '潭子倉庫': 1, '員林倉庫': 2, '清水倉庫': 3,
    '東區電子鎖倉庫': 4, '清水電子鎖倉庫': 5,
}

def esc(val):
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return 'NULL'
    s = str(val).strip()
    if s == '' or s == 'nan' or s == 'NaT':
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
    s = re.sub(r'[/.]', '-', s)
    return "'" + s + "'"

def esc_num(val):
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return '0'
    try:
        n = float(str(val).replace(',', ''))
        if n == int(n):
            return str(int(n))
        return str(n)
    except:
        return '0'

def col(df, name, row_idx):
    if name in df.columns:
        return df.at[row_idx, name]
    for c in df.columns:
        if name in c:
            return df.at[row_idx, c]
    return None

def resolve_branch(name):
    if name is None or (isinstance(name, float) and pd.isna(name)):
        return 'NULL'
    bn = str(name).strip()
    bid = BRANCH_MAP.get(bn)
    return str(bid) if bid else 'NULL'

# ============================================================
# 1. Vendors
# ============================================================
def gen_vendors():
    fpath = os.path.join(USB, '廠商資訊.xlsx')
    df = pd.read_excel(fpath)
    print(f"  廠商資訊.xlsx: {len(df)} rows")
    lines = ["-- Import: vendors\n"]
    total = 0
    for i in range(len(df)):
        vals = [
            esc(col(df, '廠商編號', i)),
            esc(col(df, '廠商名稱', i)),
            esc(col(df, '廠商簡稱', i)),
            esc(col(df, '統一編號', i)),
            esc(col(df, '廠商類別', i)),
            esc(col(df, '廠商服務項目', i)),
            esc(col(df, '聯絡窗口', i)),
            esc(col(df, '電話號碼', i)),
            esc(col(df, '傳真號碼', i)),
            esc(col(df, 'E-mail', i)),
            esc(col(df, '郵遞區號', i)),
            esc(col(df, '縣市及鄉鎮地區', i)),
            esc(col(df, '街道地址', i)),
            esc(col(df, '地址', i)),
            esc(col(df, '付款方式', i)),
            esc(col(df, '付款條件', i)),
            esc(col(df, '結帳日', i)),
            esc(col(df, '發票方式', i)),
            esc(col(df, '發票種類1', i)),
            esc(col(df, '抬頭1', i)),
            esc(col(df, '統一編號', i)),  # tax_id1 same as tax_id
            esc(col(df, '抬頭2', i)),
            esc(col(df, '統一編號2', i)),
            esc(col(df, '發票種類2', i)),
            esc(col(df, '備註', i)),
            esc(col(df, '建立人員', i)),
        ]
        lines.append(
            "INSERT INTO vendors (vendor_code, name, short_name, tax_id, category, service_items, contact_person, phone, fax, email, postal_code, city_district, street_address, address, payment_method, payment_terms, settlement_day, invoice_method, invoice_type, header1, tax_id1, header2, tax_id2, invoice_type2, note, created_by) VALUES (" +
            ', '.join(vals) + ');\n'
        )
        total += 1
    path = os.path.join(OUT, 'import_09_vendors.sql')
    with open(path, 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print(f"=> {path}: {total} records")
    return total

# ============================================================
# 2. Inventory
# ============================================================
def gen_inventory():
    # Use just one file since all 5 are identical
    fpath = os.path.join(USB, '總庫存明細NEW -台中.xlsx')
    df = pd.read_excel(fpath)
    print(f"  總庫存明細NEW -台中.xlsx: {len(df)} rows, {len(df.columns)} cols")

    # We need to match products by model or name to get product_id
    # Since we can't query DB from Python, we'll generate SQL that does a subquery match
    lines = ["-- Import: inventory (using product model subquery match)\n"]
    total = 0

    # Warehouse column mapping
    wh_cols = [
        # (warehouse_id, available_col_pattern, stock_col_pattern, reserved_col_pattern, loaned_col_pattern, display_col_pattern)
        (1, '潭子', '可用', '庫存', '已備貨', '借出', '展示'),
        (2, '員林', '可用', '庫存', '已備貨', '借出', '展示'),
        (3, '清水', '可用', '庫存', '已備貨', '借出', '展示'),
        (4, '東區電子鎖', '可用', '庫存', '已備貨', '借出', '展示'),
        (5, '清水電子鎖', '可用', '庫存', '已備貨', '借出', '展示'),
    ]

    # Find actual column names for each warehouse
    def find_wh_col(prefix, qty_type):
        """Find column like '潭子-可用' or '員林 可用' etc"""
        for c in df.columns:
            # Look for columns containing both the warehouse prefix and qty type
            if prefix in c and qty_type in c:
                return c
        return None

    # Map warehouse columns
    warehouse_cols = {}
    for wh_id, prefix, avail, stock, reserved, loaned, display in wh_cols:
        warehouse_cols[wh_id] = {
            'available': find_wh_col(prefix, avail),
            'stock': find_wh_col(prefix, stock),
            'reserved': find_wh_col(prefix, reserved),
            'loaned': find_wh_col(prefix, loaned),
            'display': find_wh_col(prefix, display),
        }

    print(f"  Column mappings per warehouse:")
    for wh_id, cols in warehouse_cols.items():
        found = sum(1 for v in cols.values() if v is not None)
        print(f"    WH {wh_id}: {found}/5 columns found")

    for i in range(len(df)):
        product_model = col(df, '商品型號', i)
        if product_model is None or (isinstance(product_model, float) and pd.isna(product_model)):
            continue
        product_model_str = str(product_model).strip()
        if not product_model_str or product_model_str == 'nan':
            continue

        product_model_esc = esc(product_model_str)

        for wh_id, cols in warehouse_cols.items():
            avail = 0
            stock = 0
            reserved = 0
            loaned = 0
            disp = 0

            if cols['available']:
                v = df.at[i, cols['available']]
                avail = int(float(v)) if v is not None and not (isinstance(v, float) and pd.isna(v)) else 0
            if cols['stock']:
                v = df.at[i, cols['stock']]
                stock = int(float(v)) if v is not None and not (isinstance(v, float) and pd.isna(v)) else 0
            if cols['reserved']:
                v = df.at[i, cols['reserved']]
                reserved = int(float(v)) if v is not None and not (isinstance(v, float) and pd.isna(v)) else 0
            if cols['loaned']:
                v = df.at[i, cols['loaned']]
                loaned = int(float(v)) if v is not None and not (isinstance(v, float) and pd.isna(v)) else 0
            if cols['display']:
                v = df.at[i, cols['display']]
                disp = int(float(v)) if v is not None and not (isinstance(v, float) and pd.isna(v)) else 0

            # Skip if all zeros
            if avail == 0 and stock == 0 and reserved == 0 and loaned == 0 and disp == 0:
                continue

            # Use subquery to find product_id by model
            lines.append(
                f"INSERT IGNORE INTO inventory (product_id, warehouse_id, available_qty, stock_qty, reserved_qty, loaned_qty, display_qty) "
                f"SELECT id, {wh_id}, {avail}, {stock}, {reserved}, {loaned}, {disp} "
                f"FROM products WHERE model = {product_model_esc} LIMIT 1;\n"
            )
            total += 1

    path = os.path.join(OUT, 'import_10_inventory.sql')
    with open(path, 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print(f"=> {path}: {total} inventory rows")
    return total

# ============================================================
# 3. Requisitions (headers only)
# ============================================================
def gen_requisitions():
    fpath = os.path.join(USB, '請購單 NEW.xlsx')
    df = pd.read_excel(fpath)
    print(f"  請購單 NEW.xlsx: {len(df)} rows")
    lines = ["-- Import: requisitions (headers only)\n"]
    total = 0
    for i in range(len(df)):
        branch_id = resolve_branch(col(df, '所屬分公司', i))
        vals = [
            esc(col(df, '請購單號', i)),
            esc_date(col(df, '請購日期', i)),
            esc(col(df, '請購人員', i)),
            branch_id,
            esc(col(df, '負責業務', i)),
            esc(col(df, '緊急程度', i)),
            esc(col(df, '案名', i)),
            esc(col(df, '報價確認單號', i)),
            esc(col(df, '廠商', i)),
            esc_date(col(df, '期望到貨日', i)),
            esc(col(df, '簽核狀態', i)),
            esc(col(df, '覆核人員', i)),
            esc_date(col(df, '覆核日期', i)),
            esc(col(df, '覆核備註', i)),
            esc(col(df, '下一位簽核人', i)),
            esc(col(df, '請購備註', i)),
        ]
        lines.append(
            "INSERT INTO requisitions (requisition_number, requisition_date, requester_name, branch_id, sales_name, urgency, case_name, quotation_number, vendor_name, expected_date, status, approval_user, approval_date, approval_note, next_approver, note) VALUES (" +
            ', '.join(vals) + ');\n'
        )
        total += 1
    path = os.path.join(OUT, 'import_11_requisitions.sql')
    with open(path, 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print(f"=> {path}: {total} records")
    return total

# ============================================================
# 4. Purchase Orders (headers only)
# ============================================================
def gen_purchase_orders():
    fpath = os.path.join(USB, '採購單 NEW.xlsx')
    df = pd.read_excel(fpath)
    print(f"  採購單 NEW.xlsx: {len(df)} rows")
    lines = ["-- Import: purchase_orders (headers only)\n"]
    total = 0
    for i in range(len(df)):
        branch_id = resolve_branch(col(df, '所屬分公司', i))
        vals = [
            esc(col(df, '採購單號', i)),
            esc_date(col(df, '採購日期', i)),
            esc(col(df, '狀態', i)),
            esc(col(df, '採購人員', i)),
            esc(col(df, '來自請購單號', i)),
            esc_date(col(df, '進貨日期', i)),
            esc(col(df, '案名', i)),
            branch_id,
            esc(col(df, '負責業務', i)),
            esc(col(df, '緊急程度', i)),
            esc(col(df, '廠商編號', i)),
            esc(col(df, '廠商名稱', i)),
            esc(col(df, '統一編號', i)),
            esc(col(df, '聯絡人', i)),
            esc(col(df, '電話', i)),
            esc(col(df, '傳真', i)),
            esc(col(df, 'E-mail', i)),
            esc(col(df, '地址', i)),
            esc(col(df, '付款方式', i)),
            esc(col(df, '付款條件', i)),
            esc(col(df, '發票方式', i)),
            esc(col(df, '發票種類', i)),
            esc_date(col(df, '付款日期', i)),
            '1' if col(df, '是否已付款', i) == True else '0',
            esc_num(col(df, '付款金額', i)),
            esc_num(col(df, '小計', i)),
            esc(col(df, '課稅別', i)),
            esc_num(col(df, '稅率', i)),
            esc_num(col(df, '稅額', i)),
            esc_num(col(df, '運費', i)),
            esc_num(col(df, '合計金額', i)),
            esc_num(col(df, '本單金額', i)),
            esc_num(col(df, '優惠價(未稅)', i)),
            esc_num(col(df, '優惠價(含稅)', i)),
            '1' if col(df, '走請款流程', i) == True else '0',
            '1' if col(df, '是否轉進貨', i) == True else '0',
            '1' if col(df, '取消退款', i) == True else '0',
            esc_date(col(df, '退款日期', i)),
            esc(col(df, '交貨地點', i)),
            esc_date(col(df, '要求到貨日', i)),
            esc_date(col(df, '承諾到貨日', i)),
            esc(col(df, '備註', i)),
        ]
        lines.append(
            "INSERT INTO purchase_orders (po_number, po_date, status, purchaser_name, requisition_number, receiving_date, case_name, branch_id, sales_name, urgency, vendor_code, vendor_name, vendor_tax_id, vendor_contact, vendor_phone, vendor_fax, vendor_email, vendor_address, payment_method, payment_terms, invoice_method, invoice_type, payment_date, is_paid, paid_amount, subtotal, tax_type, tax_rate, tax_amount, shipping_fee, total_amount, this_amount, discount_untaxed, discount_taxed, use_payment_flow, convert_to_receiving, is_cancelled, refund_date, delivery_location, required_date, promised_date, note) VALUES (" +
            ', '.join(vals) + ');\n'
        )
        total += 1
    path = os.path.join(OUT, 'import_12_purchase_orders.sql')
    with open(path, 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print(f"=> {path}: {total} records")
    return total

# ============================================================
# 5. Warehouse Transfers (headers only)
# ============================================================
def gen_transfers():
    fpath = os.path.join(USB, '倉庫調撥 NEW.xlsx')
    df = pd.read_excel(fpath)
    print(f"  倉庫調撥 NEW.xlsx: {len(df)} rows")
    lines = ["-- Import: warehouse_transfers (headers only)\n"]
    total = 0
    for i in range(len(df)):
        from_branch = resolve_branch(col(df, '所屬分公司-出貨', i))
        to_branch = resolve_branch(col(df, '所屬分公司-進貨', i))

        from_wh_name = col(df, '倉庫名稱-出貨', i)
        to_wh_name = col(df, '倉庫名稱-進貨', i)
        from_wh_id = 'NULL'
        to_wh_id = 'NULL'
        if from_wh_name and not (isinstance(from_wh_name, float) and pd.isna(from_wh_name)):
            fwn = str(from_wh_name).strip()
            if fwn in WAREHOUSE_MAP:
                from_wh_id = str(WAREHOUSE_MAP[fwn])
        if to_wh_name and not (isinstance(to_wh_name, float) and pd.isna(to_wh_name)):
            twn = str(to_wh_name).strip()
            if twn in WAREHOUSE_MAP:
                to_wh_id = str(WAREHOUSE_MAP[twn])

        vals = [
            esc(col(df, '調撥單號', i)),
            esc_date(col(df, '出貨日期', i)),
            from_branch,
            to_branch,
            from_wh_id,
            to_wh_id,
            esc(from_wh_name),
            esc(to_wh_name),
            esc(col(df, '單據狀態', i)),
            '1' if col(df, '是否更新庫存', i) == True else '0',
            esc(col(df, '出貨人員', i)),
            esc(col(df, '進貨人員', i)),
            esc_num(col(df, '合計', i)),
            esc(col(df, '備註', i)),
        ]
        lines.append(
            "INSERT INTO warehouse_transfers (transfer_number, transfer_date, from_branch_id, to_branch_id, from_warehouse_id, to_warehouse_id, from_warehouse_name, to_warehouse_name, status, update_inventory, shipper_name, receiver_name, total_amount, note) VALUES (" +
            ', '.join(vals) + ');\n'
        )
        total += 1
    path = os.path.join(OUT, 'import_13_transfers.sql')
    with open(path, 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print(f"=> {path}: {total} records")
    return total

# ============================================================
if __name__ == '__main__':
    print("=== Generating import SQL files (batch 3) ===\n")
    t1 = gen_vendors()
    print()
    t2 = gen_inventory()
    print()
    t3 = gen_requisitions()
    print()
    t4 = gen_purchase_orders()
    print()
    t5 = gen_transfers()
    print(f"\n=== Total: {t1+t2+t3+t4+t5} SQL statements ===")
