#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
從 customer_import.json 重新產生 SQL，加入 warranty_date 欄位
解析 warranty_note：民國日期 → warranty_date，文字 → warranty_note
"""
import json
import re
from datetime import datetime, date

JSON_FILE = '/Users/chifangtang/hswork/database/customer_import.json'
OUTPUT_SQL = '/Users/chifangtang/hswork/database/customer_import_customers.sql'

def parse_roc_date(val):
    """解析民國年日期"""
    if val is None:
        return None
    if isinstance(val, (datetime, date)):
        return val.strftime('%Y-%m-%d')
    s = str(val).strip()
    if not s or s in ('', 'NaN', 'nan'):
        return None
    m = re.match(r'^(\d{2,3})[/\-.](\d{1,2})(?:[/\-.](\d{1,2}))?$', s)
    if m:
        year = int(m.group(1)) + 1911
        month = int(m.group(2))
        day = int(m.group(3)) if m.group(3) else 1
        if 1 <= month <= 12 and 1 <= day <= 31 and 1911 <= year <= 2030:
            return f'{year}-{month:02d}-{day:02d}'
    m2 = re.match(r'^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})$', s)
    if m2:
        return f'{m2.group(1)}-{int(m2.group(2)):02d}-{int(m2.group(3)):02d}'
    return None

def sql_str(val):
    """轉 SQL 字串，None → NULL"""
    if val is None or (isinstance(val, str) and val.strip() == ''):
        return 'NULL'
    s = str(val).replace("'", "\\'").replace("\\", "\\\\")
    # Fix double-escaped
    s = str(val).replace("\\", "\\\\").replace("'", "\\'")
    return f"'{s}'"

def sql_int(val):
    if val is None:
        return 'NULL'
    return str(int(val))

def main():
    with open(JSON_FILE, 'r', encoding='utf-8') as f:
        records = json.load(f)

    print(f'Loaded {len(records)} records')

    # Parse warranty_note → payment_terms / warranty_note (warranty_date already set by import)
    payment_keywords = ['匯款', '現金', '支票', '月結', '付款', '月底', '15號', '固定', '不收費']
    payment_count = 0
    note_count = 0
    for r in records:
        if not r.get('payment_terms'):
            r['payment_terms'] = None
        wn = r.get('warranty_note')
        if wn and str(wn).strip():
            wn_str = str(wn).strip()
            if any(k in wn_str for k in payment_keywords):
                r['payment_terms'] = wn_str
                r['warranty_note'] = None
                payment_count += 1
            else:
                note_count += 1
        else:
            r['warranty_note'] = None

    wd_count = sum(1 for r in records if r.get('warranty_date'))
    print(f'warranty_date: {wd_count}, payment_terms: {payment_count}, notes: {note_count}')

    # 業務比對在 PHP 匯入腳本中處理（查 users 表 is_sales=1）

    # Generate SQL
    columns = (
        'customer_no,case_number,name,category,source_company,'
        'original_customer_no,related_group_id,contact_person,phone,mobile,'
        'tax_id,site_address,completion_date,warranty_date,warranty_note,'
        'payment_info,payment_terms,salesperson_name,sales_id,line_official,'
        'source_branch,import_source,is_active,created_at'
    )

    # Build groups from records
    groups = {}
    for r in records:
        gid = r.get('related_group_id')
        if gid and gid not in groups:
            groups[gid] = {
                'name': r.get('customer_name', ''),
                'tax_id': r.get('tax_id')
            }

    lines = []
    lines.append('SET NAMES utf8mb4;')
    lines.append('SET FOREIGN_KEY_CHECKS=0;')
    lines.append('')
    lines.append('TRUNCATE TABLE customer_contacts;')
    lines.append('DELETE FROM customer_groups;')
    lines.append('ALTER TABLE customer_groups AUTO_INCREMENT = 1;')
    lines.append('UPDATE cases SET customer_id = NULL, customer_no = NULL;')
    lines.append('DELETE FROM customers;')
    lines.append('ALTER TABLE customers AUTO_INCREMENT = 1;')
    lines.append('')

    # Groups
    for gid in sorted(groups.keys()):
        g = groups[gid]
        gname = str(g['name']).replace("'", "\\'")
        gtax = sql_str(g['tax_id'])
        lines.append(f"INSERT INTO customer_groups (id, group_name, tax_id) VALUES ({gid}, '{gname}', {gtax});")

    lines.append('')

    # Customers - batch 50 per INSERT
    batch_size = 50
    for i in range(0, len(records), batch_size):
        batch = records[i:i+batch_size]
        lines.append(f'INSERT INTO customers ({columns}) VALUES')
        values = []
        for r in batch:
            vals = (
                f"({sql_str(r.get('customer_no'))},"
                f"{sql_str(r.get('case_number'))},"
                f"{sql_str(r.get('customer_name'))},"
                f"{sql_str(r.get('category'))},"
                f"{sql_str(r.get('source_company'))},"
                f"{sql_str(r.get('original_customer_no'))},"
                f"{sql_int(r.get('related_group_id'))},"
                f"{sql_str(r.get('contact_person'))},"
                f"{sql_str(r.get('phone'))},"
                f"{sql_str(r.get('mobile'))},"
                f"{sql_str(r.get('tax_id'))},"
                f"{sql_str(r.get('site_address'))},"
                f"{sql_str(r.get('completion_date'))},"
                f"{sql_str(r.get('warranty_date'))},"
                f"{sql_str(r.get('warranty_note'))},"
                f"{sql_str(r.get('payment_info'))},"
                f"{sql_str(r.get('payment_terms'))},"
                f"{sql_str(r.get('salesperson_name'))},"
                f"NULL,"  # sales_id - resolved at import time
                f"{sql_str(r.get('line_official'))},"
                f"{sql_str(r.get('source_branch'))},"
                f"'excel_import',1,NOW())"
            )
            values.append(vals)
        lines.append(',\n'.join(values) + ';')

    sql_content = '\n'.join(lines)

    with open(OUTPUT_SQL, 'w', encoding='utf-8') as f:
        f.write(sql_content)

    print(f'Output: {OUTPUT_SQL}')
    print(f'Size: {len(sql_content):,} bytes')

    # Verify some samples
    print('\n=== 前 10 筆預覽 ===')
    for r in records[:10]:
        wd = r.get('warranty_date') or '-'
        wn = r.get('warranty_note') or '-'
        pt = r.get('payment_terms') or '-'
        print(f"  {r['customer_no']} | {r['case_number']} | 完工:{r['completion_date']} | 保固:{wd} | 付款:{pt} | 備註:{wn}")

    # Show payment_terms samples
    print('\n=== 付款條件樣本 (前 20 筆) ===')
    count = 0
    for r in records:
        if r.get('payment_terms') and count < 20:
            print(f"  {r['customer_no']} | {r['payment_terms']}")
            count += 1

    # Show remaining warranty_notes
    print('\n=== 其他備註樣本 (前 20 筆) ===')
    count = 0
    for r in records:
        if r.get('warranty_note') and count < 20:
            print(f"  {r['customer_no']} | {r['warranty_note']}")
            count += 1

if __name__ == '__main__':
    main()
