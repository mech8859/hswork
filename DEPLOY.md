# 弱電工程排程系統 - 部署說明

## 智邦虛擬主機部署步驟

### 1. 上傳檔案
將整個專案上傳至虛擬主機，將 `public/` 目錄設為網站根目錄（DocumentRoot）。

### 2. 建立資料庫
登入 phpMyAdmin 或 MySQL CLI：
```sql
CREATE DATABASE hswork CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
匯入資料表結構：
```
database/schema.sql
database/seed.sql
```

### 3. 設定資料庫連線
編輯 `config/database.php`，修改為智邦提供的資料庫連線資訊：
- host: 通常為 localhost
- dbname: 你的資料庫名稱
- username: 你的資料庫帳號
- password: 你的資料庫密碼

### 4. 目錄權限
```bash
chmod 755 public/uploads/
chmod 755 public/uploads/cases/
chmod 755 logs/
```

### 5. 預設登入
- 帳號: admin
- 密碼: admin123
- **請登入後立即修改密碼**

## 專案結構
```
hswork/
├── config/          # 設定檔
├── database/        # SQL 檔案
├── includes/        # 核心程式 (Database, Session, Auth, helpers)
├── modules/         # 功能模組 (積木式架構)
│   ├── api/         # API 模組 (Ragic/Google Sheet 串接)
│   ├── auth/        # 認證模組
│   ├── cases/       # 案件管理
│   ├── staff/       # 人員管理
│   ├── schedule/    # 排工管理
│   └── reports/     # 報表
├── templates/       # 版面模板
│   ├── layouts/     # 共用版面 (header/footer)
│   ├── auth/
│   ├── cases/
│   ├── staff/
│   └── schedule/
├── public/          # 網站根目錄 (DocumentRoot)
│   ├── css/
│   ├── js/
│   ├── uploads/
│   ├── api/         # API 入口
│   ├── index.php    # 首頁/儀表板
│   ├── login.php    # 登入
│   └── logout.php   # 登出
└── logs/            # 日誌
```

## API 使用方式
```
GET  /api/?endpoint=cases&action=list&api_key=YOUR_KEY
GET  /api/?endpoint=cases&action=get&id=1&api_key=YOUR_KEY
POST /api/?endpoint=cases&action=create (Header: X-API-Key)
POST /api/?endpoint=sync&action=ragic_import (Header: X-API-Key)
```
