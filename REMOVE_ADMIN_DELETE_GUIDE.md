# 管理者刪改工具移除指南

**建立日期**：2026-04-08
**目的**：測試期專用 — 系統管理者對 4 張單據（出庫單、入庫單、進貨單、退貨單）的「刪除」與「修改基本資訊」功能。
**移除時機**：正式上線、測試髒資料清除完畢後。

---

## 移除步驟概要

所有相關程式碼都用以下標記包夾，搜尋並整段刪除即可：

```
ADMIN_TOOL_BLOCK_START
... (要刪除的內容)
ADMIN_TOOL_BLOCK_END
```

---

## 1. 後端 Model

### `modules/inventory/StockModel.php`

刪除 `ADMIN_TOOL_BLOCK_START` 到 `ADMIN_TOOL_BLOCK_END` 之間的整段（包含註解）。

包含的方法：
- `checkStockOutDeletable($id)`
- `deleteStockOutHard($id)`
- `updateStockOutBasic($id, $data)`
- `checkStockInDeletable($id)`
- `deleteStockInHard($id)`
- `updateStockInBasic($id, $data)`

### `modules/procurement/GoodsReceiptModel.php`

刪除 `ADMIN_TOOL_BLOCK_START` 到 `ADMIN_TOOL_BLOCK_END` 之間的整段。

包含的方法：
- `checkDeletable($id)`
- `deleteHard($id)`
- `updateBasic($id, $data)`

### `modules/returns/ReturnModel.php`

刪除 `ADMIN_TOOL_BLOCK_START` 到 `ADMIN_TOOL_BLOCK_END` 之間的整段。

包含的方法：
- `checkDeletable($id)`
- `deleteHard($id)`
- `updateBasic($id, $data)`

---

## 2. 控制器

### `public/stock_outs.php`、`public/stock_ins.php`、`public/goods_receipts.php`、`public/returns.php`

四支控制器都各加了一段，刪除 `ADMIN_TOOL_BLOCK_START` 到 `ADMIN_TOOL_BLOCK_END` 之間的整段。

包含的 actions：
- `case 'admin_delete':`
- `case 'admin_edit_basic':`

---

## 3. View 模板

### `templates/stock_outs/view.php`

刪除以下兩段（都用 `ADMIN_TOOL_BLOCK_START / ADMIN_TOOL_BLOCK_END` 標記）：
1. 操作列上方的「管理者改客戶」「管理者刪除整張單」按鈕
2. 頁面下方的 modal HTML + script

### `templates/stock_ins/view.php`

刪除兩段標記區塊（按鈕 + modal/script）

### `templates/goods_receipts/view.php`

刪除兩段標記區塊（按鈕 + modal/script）

### `templates/returns/view.php`

刪除兩段標記區塊（按鈕 + modal/script）

---

## 4. 進貨單／退貨單 廠商強制必選（**這部分建議保留**）

下列修改是「廠商必須從廠商管理選擇」的資料品質強化，**正式上線後強烈建議保留**，不需移除：

### 保留：`templates/goods_receipts/form.php`
- vendor_name 欄位變成 autocomplete + 必填
- 加了 `grValidateVendorBeforeSubmit()` JS 驗證
- 加了 `grVendorAutoSearch()` 即時搜尋

### 保留：`templates/returns/form.php`
- vendor_name 欄位變成 autocomplete + 必填 + hidden vendor_id
- 加了 submit 前廠商必選驗證

### 保留：`public/goods_receipts.php` create/edit
- POST 處理 加了 vendor_id 必填檢查

### 保留：`public/returns.php` create/edit
- POST 處理 加了 vendor_id 必填檢查

如果**真的要移除廠商強制必選**（不建議），把上述 4 處的驗證碼拿掉、把 `required` 屬性拿掉、把 hidden vendor_id 移除即可。

---

## 5. 一鍵移除指令參考

當要批次移除 admin 工具時，可用以下指令找出所有標記區塊：

```bash
cd /Users/chifangtang/hswork
grep -rln "ADMIN_TOOL_BLOCK_START" --include="*.php"
```

預期會列出：
- `modules/inventory/StockModel.php`
- `modules/procurement/GoodsReceiptModel.php`
- `modules/returns/ReturnModel.php`
- `public/stock_outs.php`
- `public/stock_ins.php`
- `public/goods_receipts.php`
- `public/returns.php`
- `templates/stock_outs/view.php`
- `templates/stock_ins/view.php`
- `templates/goods_receipts/view.php`
- `templates/returns/view.php`

每支檔案都用編輯器搜尋 `ADMIN_TOOL_BLOCK_START` 與 `ADMIN_TOOL_BLOCK_END`，把兩個標記之間的所有內容（含標記本身）刪掉。

---

## 6. 移除後的驗證

1. 切到 admin 帳號，到 4 張單的 view 頁面，**不應該** 看到紫色「管理者改 X」與紅色「管理者刪除整張單」按鈕
2. 直接打 URL `https://hswork.com.tw/stock_outs.php?action=admin_delete` → 應 redirect 回列表（因為 case 不存在）
3. 進貨單／退貨單建立頁，廠商欄位仍然必填且只能從下拉選擇（這部分保留）

---

## 7. 還原存檔點

所有改動都在 git tag `savepoint-before-admin-tools-20260408-confirmed` 之後。
如要整批回滾：

```bash
git reset --hard savepoint-before-admin-tools-20260408-confirmed
```

⚠ 此操作會把廠商強制必選的改動也一併回滾，不建議直接用，除非確定要全砍。
