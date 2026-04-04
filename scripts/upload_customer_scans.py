#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
客戶掃描檔上傳腳本
從 USB 讀取 JPG，用檔名數字對應客戶，FTP 上傳到主機
"""
import os
import re
import json
import sys
from ftplib import FTP

# 設定
USB_SCAN_DIR = '/Volumes/UC300-2/客戶資料掃瞄檔'
JSON_FILE = '/Users/chifangtang/hswork/database/customer_import_v2.json'

FTP_HOST = '211.72.207.233'
FTP_USER = 'hswork.com.tw'
FTP_PASS = 'Kss9227456'
FTP_BASE = '/www/uploads/customers'

# SQL 檔輸出（用 PHP 跑 DB insert）
SQL_OUTPUT = '/Users/chifangtang/hswork/database/customer_scan_import.sql'

# 只處理指定資料夾（測試用，None = 全部）
TEST_FOLDER = None


def build_customer_map(json_file):
    """建立原客戶編號數字 → customer 的對照"""
    with open(json_file, 'r', encoding='utf-8') as f:
        data = json.load(f)

    # 主索引：「客戶XXX」的數字部分 → customer
    cust_by_num = {}
    # 員林索引：可能有不同格式
    for r in data:
        orig = r.get('original_customer_no', '')
        # 「客戶003」→ 3, 「客戶6208.業#3254」→ 6208
        m = re.match(r'客戶0*(\d+)', orig)
        if m:
            num = m.group(1)
            if num not in cust_by_num:
                cust_by_num[num] = r

        # 「維#354」→ 不處理（掃描檔只對應「客戶XXX」）
        # 「自取客戶55」→ 不處理
        # 「C01」→ 不處理（海線另外處理）
        # 「業#123」→ 不處理

    return cust_by_num, data


def get_scan_folders(base_dir, test_folder=None):
    """取得所有掃描檔資料夾"""
    folders = []
    for name in sorted(os.listdir(base_dir)):
        full = os.path.join(base_dir, name)
        if os.path.isdir(full) and name.startswith('JPG'):
            if test_folder and name != test_folder:
                continue
            folders.append((name, full))
    return folders


def extract_file_number(filename):
    """從檔名提取客戶編號數字"""
    # 「003-1蘭花園張仕賢.jpg」→ 3
    # 「1-1大甲仕女.jpg」→ 1
    # 「100-永豐餘造紙股份有限公司.jpg」→ 100
    m = re.match(r'^0*(\d+)', filename)
    if m:
        return m.group(1)
    return None


def main():
    global TEST_FOLDER

    if len(sys.argv) > 1:
        TEST_FOLDER = sys.argv[1]
        print(f'測試模式：只處理 {TEST_FOLDER}')

    print('載入客戶資料...')
    cust_by_num, all_customers = build_customer_map(JSON_FILE)
    print(f'  可匹配客戶數（客戶XXX格式）: {len(cust_by_num)}')

    folders = get_scan_folders(USB_SCAN_DIR, TEST_FOLDER)
    print(f'  掃描檔資料夾: {len(folders)} 個')

    # 連接 FTP
    print('\n連接 FTP...')
    ftp = FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.encoding = 'utf-8'
    print('  FTP 連線成功')

    # 確認 base 目錄存在
    try:
        ftp.cwd(FTP_BASE)
    except:
        ftp.mkd(FTP_BASE)
        ftp.cwd(FTP_BASE)

    total_files = 0
    uploaded = 0
    matched = 0
    not_matched = 0
    errors = []
    sql_lines = []

    for folder_name, folder_path in folders:
        print(f'\n=== {folder_name} ===')
        files = [f for f in os.listdir(folder_path)
                 if f.lower().endswith(('.jpg', '.jpeg', '.png', '.pdf'))]
        total_files += len(files)
        print(f'  檔案數: {len(files)}')

        for filename in sorted(files):
            num = extract_file_number(filename)
            if num is None:
                not_matched += 1
                errors.append(f'{folder_name}/{filename} (無法提取編號)')
                continue

            cust = cust_by_num.get(num)
            if cust is None:
                not_matched += 1
                if len(errors) < 100:
                    errors.append(f'{folder_name}/{filename} (客戶{num}不存在)')
                continue

            matched += 1
            cust_id = all_customers.index(cust) + 1  # customer ID = index + 1
            customer_no = cust['customer_no']

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

            local_path = os.path.join(folder_path, filename)
            remote_file = filename

            try:
                with open(local_path, 'rb') as fp:
                    ftp.storbinary(f'STOR {remote_file}', fp)
                uploaded += 1
                file_size = os.path.getsize(local_path)
                rel_path = f'uploads/customers/{cust_id}/{filename}'

                # SQL insert
                fn_esc = filename.replace("'", "\\'")
                rp_esc = rel_path.replace("'", "\\'")
                sql_lines.append(f"({cust_id},'scan','{fn_esc}','{rp_esc}',{file_size},1,'從ERP客戶資料匯入')")

                if uploaded <= 5 or uploaded % 500 == 0:
                    print(f'  [{uploaded}] {filename} → {customer_no} (ID={cust_id})')

            except Exception as e:
                errors.append(f'{filename} (FTP upload: {e})')

    ftp.quit()

    # 輸出 SQL
    if sql_lines:
        with open(SQL_OUTPUT, 'w', encoding='utf-8') as f:
            f.write('SET NAMES utf8mb4;\n\n')
            f.write('INSERT INTO customer_files (customer_id, file_type, file_name, file_path, file_size, uploaded_by, note) VALUES\n')
            f.write(',\n'.join(sql_lines))
            f.write(';\n')
        print(f'\nSQL: {SQL_OUTPUT}')

    print(f'\n=== 結果 ===')
    print(f'總檔案數: {total_files}')
    print(f'匹配成功: {matched}')
    print(f'上傳成功: {uploaded}')
    print(f'未匹配: {not_matched}')

    if errors:
        print(f'\n=== 錯誤（前20筆）===')
        for e in errors[:20]:
            print(f'  {e}')
        if len(errors) > 20:
            print(f'  ... 還有 {len(errors)-20} 筆')


if __name__ == '__main__':
    main()
