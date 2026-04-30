# 月底出勤同步 SOP

每月月底彙整考勤資料、加班單、請假單到「出勤紀錄.xlsx」之標準作業程序。

## 前置作業

1. **準備檔案**（同一資料夾，建議路徑 `/Volumes/MATERIAL-1/臻梅相關/{MM.DD}/`）：
   - `考勤管理系統.xlsx`（從考勤刷卡系統匯出，含「考勤詳細」分頁）
   - `出勤紀錄.xlsx`（HR 提供的空白模板，每位員工一個分頁，已預填部門/姓名/日期/約定上下班/休息時段）

2. **建立存檔點**（每次寫入前必做）：
   ```bash
   cp 出勤紀錄.xlsx 出勤紀錄_存檔點_$(date +%Y%m%d_%H%M%S).xlsx
   ```

3. **關閉 Excel**：執行寫入前，確認 `出勤紀錄.xlsx` 沒有被 Excel 開啟（否則無法寫入）。

---

## 流程一：考勤管理系統 → 出勤紀錄

### 輸入
- `考勤管理系統.xlsx`「考勤詳細」分頁
  - 表頭：A=日期 / C=姓名 / M=時段1-簽到 / N=時段1-簽退

### 輸出（寫入到 `出勤紀錄.xlsx` 每位員工的分頁）
- F 欄（實際出勤上班）
- G 欄（實際出勤下班）

### 步驟
1. **比對名單**
   - 考勤系統的 `姓名` ↔ 出勤紀錄的「分頁名稱」
   - 兩邊都有 → 處理；單邊缺 → 略過並列出（例：考勤多了 `test`、`王正宏` 等系統測試帳號或非編制人員）

2. **比對日期**
   - 考勤的 `04月27日` ↔ 出勤紀錄的 `4/27(一)`（依月/日）

3. **轉寫規則**

   | 考勤系統的值 | 寫入出勤紀錄 |
   |---|---|
   | 時間（如 `07:56`） | 原樣寫入 |
   | `未簽` | 寫成 `-` |
   | `-` | 寫成 `-` |

4. **保留既有手填標記**（不覆蓋）：
   - F/G 已含中文字（如 `留職停薪`、`排休`、`特休`、`生理假`、`事假`、`病假`、`兒童節補假`、`上午\n事假` 等）
   - F/G 為 `/`（如 `朱良明`，全月皆 `/`）

### 異常處置（人工確認後請 Claude 對調）
- **考勤系統簽到/簽退顛倒**：少數員工會出現「簽到時間 > 簽退時間」的明顯異常（**確認非跨夜班**），需將 F/G 對調。
  - 案例：`莊竣珽`、`蕭凱澤`、`林晏鈴` 4/27–4/29

### 後續處理
1. **依星期幾上色**（保留各分頁既有顏色慣例）：
   - 平日（週一至週五）：黃色 `#FFFF00`
   - 週六：依該分頁原顏色（多數為粉色 theme 5；部分用 `FFFFCC`、`00FFFF`）
   - 週日：粉色 theme 5

2. **移除外部連結**（避免 Excel 開啟時跳「不安全的外部來源連結」警示）：
   - 檢查並轉成靜態值
   - 案例：`郭育銘` G6 曾有 `=[1]考勤詳細!$N$173` 公式，需改為快取值

---

## 流程二：請假管理 → 出勤紀錄

### 輸入
- 系統 `leaves` 表，`status='approved'` 的紀錄
- 欄位：`user_id`（→ users.real_name）、`leave_type`、`start_date`、`end_date`、`reason`

### 輸出
- L 欄（請假時數）

### 步驟
1. **撈取資料**（從 hswork DB）

   ```sql
   SELECT u.real_name, l.leave_type, l.start_date, l.end_date, l.reason
   FROM leaves l
   JOIN users u ON l.user_id = u.id
   WHERE l.start_date <= '{月底}' AND l.end_date >= '{月初}'
     AND l.status = 'approved';
   ```

   - 注意是日期**區間重疊**（不是 BETWEEN），因為一筆請假可能跨多日

2. **比對員工分頁**並逐日寫入 L 欄

3. **規則**：
   - **1 天 = 8 小時**
   - 跨日請假 → 區間內每一個工作日都寫入 8
   - 同一人同一天若已有 L 值（例：手填半日 `4`）→ 系統有對應請假單時以系統值為準；無對應請假單時保留手填

### 注意
- 假別代碼：`annual`=特休、`personal`=事假、`sick`=病假、`menstrual`=生理假、`official`=公假
- F/G 是否同步寫入「事假」「特休」等中文字標記，由考勤系統流程決定（多數已標記）
- L 欄目前只記時數（8），不記假別文字

---

## 流程三：加班單管理 → 出勤紀錄

### 輸入
- 系統 `overtimes` 表，`status='approved'` 的紀錄
- 欄位：`user_id`、`overtime_date`、`start_time`、`end_time`、`hours`（DECIMAL 5,2）、`overtime_type`

### 輸出
- M 欄（加班-起始時間）
- N 欄（加班-結束時間）
- O 欄（加班-總時數，格式 `2小時` 或 `2.5小時`）

### 步驟
1. **撈取資料**

   ```sql
   SELECT u.real_name, o.overtime_date, o.start_time, o.end_time, o.hours
   FROM overtimes o
   JOIN users u ON o.user_id = u.id
   WHERE o.overtime_date BETWEEN '{月初}' AND '{月底}'
     AND o.status = 'approved';
   ```

2. **比對員工分頁 + 日期**寫入 M/N/O

3. **格式規則**：
   - M、N：時間格式 `h:mm`（如 `17:00`）
   - O：整數小時 `2小時`、半小時 `2.5小時`、零小時 `0小時`

### 注意
- 一人一天可能有多筆加班單（例：分時段請）→ 目前是後筆覆蓋前筆，若有需要請改為合併
- 若加班單已建立但**尚未核准**（status=pending）→ 不寫入

---

## P 欄：實際出勤總時數（公式）

寫完 F/G 後，P 欄一律改為公式（不存固定值），讓 G 變動時自動重算。

**公式**（以第 r 列為例）：
```
=IFERROR(IF(ISNUMBER(Gr),HOUR(Gr),VALUE(LEFT(Gr,FIND(":",Gr)-1)))-8&"小時","")
```

**邏輯**：
- 取 G（下班時間）的小時部分減 8
- 若 G 是 Excel 時間值 → 用 `HOUR()`
- 若 G 是字串「HH:MM」→ 用 `LEFT()/FIND()` 取冒號前的小時數
- 若 G 是 `-`、中文標記、空值 → 顯示空白

**驗證範例**：
- G=`19:30` → `11小時`
- G=`23:23` → `15小時`
- G=`-` → 空白
- G=`特休` → 空白

---

## 驗證清單

寫入完成後，抽樣檢查以下項目：

- [ ] 考勤系統的 4 天（或當月）資料都已同步到 F/G
- [ ] 中文標記（事假/特休/留職停薪等）未被覆蓋
- [ ] 簽到/簽退顛倒的員工已對調
- [ ] 系統內的加班單筆數 = 出勤紀錄寫入 M/N/O 的筆數
- [ ] 系統內的已核准請假天數 = 出勤紀錄 L=8 的儲存格數
- [ ] 跨多日請假（例：黃星晴 4/27–4/30）每天都有 L=8
- [ ] P 欄公式對 G=時間時計算正確、G=其他時為空
- [ ] 開啟檔案沒有「外部來源連結」警示
- [ ] 平日/週六/週日的底色依分頁慣例套用

---

## 常見異常與處置

| 異常 | 原因 | 處置 |
|---|---|---|
| 考勤系統 `姓名` 在出勤紀錄找不到分頁 | 系統測試帳號（如 `test`）、未編制人員、離職人員 | 略過，列入跳過清單回報 |
| 出勤紀錄分頁在考勤系統無資料 | 該人員已離職、長假、留職停薪 | 不動，保留原檔內容（如 `朱良明` 的 `/`、`李秉謙` 空白） |
| 一筆 G 同時是時間 + 中文（如 `清明節補假\n07:56`） | 假日仍有刷卡 | 視為中文標記儲存格，不覆蓋 |
| 跨夜班的 F > G | 真實夜班員工 | 不對調，照樣寫入；P 公式取 G 小時減 8 仍正確 |
| 系統異常的 F > G | 考勤系統錯誤 | 人工確認非夜班後對調 F/G |
| Excel 開啟跳「外部連結」警示 | 模板殘留舊公式 `=[1]xxx` | 用 Python 改寫成靜態值 + 移除 externalLinks/* |
| 開檔時 Excel 顯示「已修復」 | openpyxl 重寫某些次要結構 | 通常無資料損失，按「儲存」即可清掉 |

---

## 一次性 PHP 匯出腳本（取系統加班/請假）

放置位置：`public/export_attendance_aux.php`（用完**立即刪除**）

```php
<?php
header('Content-Type: application/json; charset=utf-8');
$pdo = new PDO("mysql:host=localhost;dbname=vhost158992;charset=utf8mb4",
               'vhost158992', 'Kss9227456', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$start = '2026-04-27'; $end = '2026-04-30';

$ot = $pdo->prepare("SELECT u.real_name, o.overtime_date, o.start_time, o.end_time, o.hours
                     FROM overtimes o JOIN users u ON o.user_id=u.id
                     WHERE o.overtime_date BETWEEN ? AND ? AND o.status='approved'
                     ORDER BY o.overtime_date, u.real_name");
$ot->execute([$start, $end]);

$lv = $pdo->prepare("SELECT u.real_name, l.leave_type, l.start_date, l.end_date, l.reason
                     FROM leaves l JOIN users u ON l.user_id=u.id
                     WHERE l.start_date <= ? AND l.end_date >= ? AND l.status='approved'
                     ORDER BY l.start_date, u.real_name");
$lv->execute([$end, $start]);

echo json_encode(['overtimes'=>$ot->fetchAll(PDO::FETCH_ASSOC),
                  'leaves'=>$lv->fetchAll(PDO::FETCH_ASSOC)],
                 JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
```

抓取指令：
```bash
curl -s -A "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)" \
     "https://hswork.com.tw/export_attendance_aux.php" -o aux_data.json
```

> **重要**：腳本含 DB 密碼，使用後立即從 `/www/` 刪除：
> `lftp ... rm /www/export_attendance_aux.php`

---

## 執行順序（每月例行）

1. 拿到 `考勤管理系統.xlsx` + `出勤紀錄.xlsx` → 放到本月資料夾
2. 建立存檔點
3. **流程一**：考勤系統 → F/G
4. 確認異常（顛倒對調、底色、外部連結）
5. **流程二**：請假管理 → L
6. **流程三**：加班單管理 → M/N/O
7. 套上 P 欄公式
8. 跑驗證清單
9. 交付給 HR / 會計

> 全程委由 Claude 執行：把上述步驟貼給 Claude，Claude 會自動跑 Python 腳本 + curl 撈系統資料 + 比對寫入。
