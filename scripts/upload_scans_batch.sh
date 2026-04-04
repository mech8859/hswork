#!/bin/bash
# 批次上傳掃描檔，一次一個資料夾，記錄失敗
cd /Users/chifangtang/hswork

LOGFILE="database/scan_upload_errors.log"
SQLFILE="database/customer_scan_import_all.sql"

echo "=== 掃描檔批次上傳 $(date) ===" > "$LOGFILE"
echo "SET NAMES utf8mb4;" > "$SQLFILE"

FOLDERS=(
"JPG客戶編號541-720"
"JPG客戶編號721-900"
"JPG客戶編號901-1080"
"JPG客戶編號1081-1260"
"JPG客戶編號1261-1440"
"JPG客戶編號1441-1620"
"JPG客戶編號1621-1800"
"JPG客戶編號1801-1980"
"JPG客戶編號1981-2160"
"JPG客戶編號2161-2340"
"JPG客戶編號2341-2520"
"JPG客戶編號2521-2700"
"JPG客戶編號2701-2900"
"JPG客戶編號2901-3080"
"JPG客戶編號3081-3260"
"JPG客戶編號3261-3440"
"JPG客戶編號3441-3620"
"JPG客戶編號3621-3800"
"JPG客戶編號3801-3980"
"JPG客戶編號3981-4160"
"JPG客戶編號4161-4340"
"JPG客戶編號4341-4520"
"JPG客戶編號4521-4700"
"JPG客戶編號4701-4880"
"JPG客戶編號4881-5060"
"JPG客戶編號5421-5600"
"JPG客戶編號5601-5780"
"JPG客戶編號5781-5960"
"JPG客戶編號5961-6140"
"JPG客戶編號6321-6500"
"JPG員林客戶編號1-200"
)

for FOLDER in "${FOLDERS[@]}"; do
    echo ""
    echo "=== $FOLDER ==="
    python3 scripts/upload_customer_scans.py "$FOLDER" 2>&1 | tee -a "$LOGFILE"

    # 把每次的 SQL 追加到總檔
    if [ -f "database/customer_scan_import.sql" ]; then
        # 去掉 SET NAMES 行，只取 INSERT
        grep "^INSERT\|^(" "database/customer_scan_import.sql" >> "$SQLFILE"
    fi
done

echo ""
echo "=== 全部完成 $(date) ==="
echo "失敗記錄: $LOGFILE"
echo "SQL 總檔: $SQLFILE"
