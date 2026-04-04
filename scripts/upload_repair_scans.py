#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
維修單掃描檔上傳 — 處理 維#XXX 和 業#XXX 格式
"""
import os
import re
import json
import sys
from ftplib import FTP

SCAN_DIR = '/Volumes/UC300-2/客戶資料掃瞄檔/維修單'
JSON_FILE = '/Users/chifangtang/hswork/database/customer_import_v2.json'
SQL_OUTPUT = '/Users/chifangtang/hswork/database/repair_scan_import.sql'

FTP_HOST = '211.72.207.233'
FTP_USER = 'hswork.com.tw'
FTP_PASS = 'Kss9227456'
FTP_BASE = '/www/uploads/customers'


def build_map(json_file):
    with open(json_file, 'r', encoding='utf-8') as f:
        data = json.load(f)

    # 維#XXX → customer
    wei_map = {}
    # 業#XXX → customer
    ye_map = {}
    # 理#XXX → customer
    li_map = {}

    for r in data:
        orig = r.get('original_customer_no', '')
        # 維#001, 維#052
        m = re.match(r'^維#?0*(\d+)', orig)
        if m:
            num = m.group(1)
            if num not in wei_map:
                wei_map[num] = r
            continue

        # 業#013, 業305
        m = re.match(r'^業#?0*(\d+)', orig)
        if m:
            num = m.group(1)
            if num not in ye_map:
                ye_map[num] = r
            continue

        # 理#223, 理#0168
        m = re.match(r'^理#?0*(\d+)', orig)
        if m:
            num = m.group(1)
            if num not in li_map:
                li_map[num] = r

    return wei_map, ye_map, li_map, data


def extract_repair_number(filename):
    """從檔名提取 維XXX 或 業XXX（有無#都支援）"""
    # 維#114黃志揚.jpg / 維001 東京金屬.jpg / 維943.jpg
    m = re.match(r'^維#?0*(\d+)', filename)
    if m:
        return '維', m.group(1)

    # 業#023 Kia.jpg / 業024 胡誌哲.jpg / 業1244 巨菱.jpg
    m = re.match(r'^業#?0*(\d+)', filename)
    if m:
        return '業', m.group(1)

    # 理0172 劉宇恆.jpg / 理#223
    m = re.match(r'^理#?0*(\d+)', filename)
    if m:
        return '理', m.group(1)

    # 日新社區維943.jpg — 包含「維」+數字
    m = re.search(r'維0*(\d+)', filename)
    if m:
        return '維', m.group(1)

    return None, None


def main():
    print('載入客戶資料...')
    wei_map, ye_map, li_map, all_data = build_map(JSON_FILE)
    print(f'  維# 可匹配: {len(wei_map)}')
    print(f'  業# 可匹配: {len(ye_map)}')
    print(f'  理# 可匹配: {len(li_map)}')

    # 收集所有 JPG
    all_files = []
    for root, dirs, files in os.walk(SCAN_DIR):
        for f in files:
            if f.lower().endswith(('.jpg', '.jpeg', '.png')):
                all_files.append((root, f))

    print(f'  掃描檔總數: {len(all_files)}')

    # 連接 FTP
    print('\n連接 FTP...')
    ftp = FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.encoding = 'utf-8'
    print('  FTP 連線成功')

    uploaded = 0
    not_matched = 0
    errors = []
    sql_lines = []

    for root, filename in sorted(all_files, key=lambda x: x[1]):
        type_prefix, num = extract_repair_number(filename)

        if type_prefix is None:
            not_matched += 1
            if len(errors) < 100:
                rel = os.path.relpath(root, SCAN_DIR)
                errors.append(f'{rel}/{filename} (無法提取維#/業#編號)')
            continue

        # 找客戶
        cust = None
        if type_prefix == '維':
            cust = wei_map.get(num)
        elif type_prefix == '業':
            cust = ye_map.get(num)
        elif type_prefix == '理':
            cust = li_map.get(num)

        if cust is None:
            not_matched += 1
            if len(errors) < 100:
                rel = os.path.relpath(root, SCAN_DIR)
                errors.append(f'{rel}/{filename} ({type_prefix}#{num} 不存在)')
            continue

        cust_id = all_data.index(cust) + 1

        # FTP 上傳
        remote_dir = f'{FTP_BASE}/{cust_id}'
        try:
            ftp.cwd(remote_dir)
        except:
            try:
                ftp.mkd(remote_dir)
                ftp.cwd(remote_dir)
            except Exception as e:
                errors.append(f'{filename} (FTP mkdir: {e})')
                continue

        local_path = os.path.join(root, filename)
        try:
            with open(local_path, 'rb') as fp:
                ftp.storbinary(f'STOR {filename}', fp)
            uploaded += 1
            file_size = os.path.getsize(local_path)
            rel_path = f'uploads/customers/{cust_id}/{filename}'

            fn_esc = filename.replace("'", "\\'")
            rp_esc = rel_path.replace("'", "\\'")
            sql_lines.append(f"({cust_id},'scan','{fn_esc}','{rp_esc}',{file_size},1,'維修單掃描匯入')")

            if uploaded <= 5 or uploaded % 500 == 0:
                print(f'  [{uploaded}] {filename} → {cust["customer_no"]} (ID={cust_id})')
        except Exception as e:
            errors.append(f'{filename} (FTP: {e})')

    try:
        ftp.quit()
    except:
        pass

    # 輸出 SQL
    if sql_lines:
        with open(SQL_OUTPUT, 'w', encoding='utf-8') as f:
            f.write('SET NAMES utf8mb4;\n\n')
            f.write('INSERT INTO customer_files (customer_id, file_type, file_name, file_path, file_size, uploaded_by, note) VALUES\n')
            f.write(',\n'.join(sql_lines))
            f.write(';\n')
        print(f'\nSQL: {SQL_OUTPUT}')

    print(f'\n=== 結果 ===')
    print(f'總檔案數: {len(all_files)}')
    print(f'上傳成功: {uploaded}')
    print(f'未匹配: {not_matched}')

    if errors:
        print(f'\n=== 未匹配/錯誤（前30筆）===')
        for e in errors[:30]:
            print(f'  {e}')
        if len(errors) > 30:
            print(f'  ... 還有 {len(errors)-30} 筆')

    # 錯誤記錄
    with open('/Users/chifangtang/hswork/database/repair_scan_errors.log', 'w', encoding='utf-8') as f:
        for e in errors:
            f.write(e + '\n')
    print(f'\n錯誤記錄: database/repair_scan_errors.log')


if __name__ == '__main__':
    main()
