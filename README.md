# 弱電工程排程系統

Low Voltage Engineering Scheduling System

為弱電工程公司設計的排程管理系統，支援多據點、多角色、手機優先的施工排程與回報管理。

## 功能概覽

### 第一期功能
- **登入系統** - 7 種角色 (老闆/業務主管/工程主管/工程副主管/業務/業務助理/行政人員)，各據點獨立，有權限者可查看全區
- **案件管理** - 新增/編輯案件，排工條件驗證 (缺少報價單/現場照片/金額/現場資料自動顯示警示)
- **人員管理** - 技能熟練度 (22 項技能，1-5 星)、證照管理 (到期前 30 天警示)、工程師配對表 (1-5 星)
- **排工行事曆** - 月曆式排程，智慧篩選 (技能符合+配對度佳+未排工+車輛連動)，多次施工人員連續性檢查
- **施工回報** - 工程師手機填寫：打卡到離場、施作項目說明、材料出貨 vs 使用數量對照
- **API 介面** - RESTful API，支援 Ragic / Google Sheet 串接同步

### 資料庫 (23 張資料表)
branches / users / vehicles / skills / user_skills / certifications / user_certifications / engineer_pairs / cases / case_readiness / case_contacts / case_site_conditions / case_required_skills / case_attachments / payments / schedules / schedule_engineers / schedule_visit_check / work_logs / material_usage / inter_branch_support / api_keys / sync_logs

## 技術規格

| 項目 | 規格 |
|------|------|
| 後端 | PHP 7.4+ / MySQL 5.7+ |
| 架構 | 模組化 MVC (積木式，功能可逐步疊加) |
| 前端 | 原生 CSS + JS，RWD 手機優先 |
| 部署 | 智邦 Linux 虛擬主機 (hswork.com.tw) |
| 安全 | bcrypt 密碼、CSRF Token、XSS 防護、Session Fixation 防護 |
| API  | RESTful，API Key 驗證，支援 Ragic / Google Sheet |

## 專案結構

```
hswork/
├── config/                 # 設定檔
│   ├── app.php             #   應用設定 (角色/權限/上傳)
│   └── database.php        #   資料庫連線
├── database/               # 資料庫
│   ├── schema.sql          #   23 張資料表結構
│   └── seed.sql            #   初始資料 (管理員/技能/證照)
├── includes/               # 核心程式
│   ├── bootstrap.php       #   啟動載入
│   ├── Database.php        #   PDO Singleton
│   ├── Session.php         #   Session / CSRF
│   ├── Auth.php            #   認證與權限
│   └── helpers.php         #   共用函式
├── modules/                # 功能模組
│   ├── api/                #   API (cases/schedules/staff/sync)
│   ├── cases/              #   案件管理 Model
│   ├── schedule/           #   排工 + 施工回報 Model
│   └── staff/              #   人員管理 Model
├── templates/              # 頁面模板
│   ├── layouts/            #   共用版面 (header/footer/403)
│   ├── cases/              #   案件 (list/form/view)
│   ├── staff/              #   人員 (list/form/view/skills/pairs)
│   └── schedule/           #   排工 + 施工回報
├── public/                 # 網站根目錄 (DocumentRoot)
│   ├── api/index.php       #   API 入口
│   ├── css/style.css       #   RWD 樣式
│   ├── js/app.js           #   前端 JS
│   ├── uploads/            #   上傳檔案
│   ├── index.php           #   儀表板
│   ├── login.php           #   登入
│   ├── cases.php           #   案件管理
│   ├── staff.php           #   人員管理
│   ├── schedule.php        #   排工行事曆
│   └── worklog.php         #   施工回報 (手機版)
└── logs/                   # 日誌
```

## 角色與權限

| 角色 | 案件 | 人員 | 排工 | 報表 | 全區 |
|------|------|------|------|------|------|
| 老闆 | 全部 | 全部 | 全部 | 全部 | V |
| 業務主管 | 管理 | 查看 | 查看 | 查看 | - |
| 工程主管 | 查看 | 管理 | 管理 | 查看 | - |
| 工程副主管 | 查看 | 查看 | 管理 | - | - |
| 業務 | 自己 | - | 查看 | - | - |
| 業務助理 | 協助 | - | 查看 | - | - |
| 行政人員 | 查看 | 查看 | 查看 | - | - |

## API 使用方式

```bash
# 取得案件清單
GET /api/?endpoint=cases&action=list&api_key=YOUR_KEY

# 取得單一案件
GET /api/?endpoint=cases&action=get&id=1&api_key=YOUR_KEY

# 新增案件
POST /api/?endpoint=cases&action=create
Header: X-API-Key: YOUR_KEY
Body: {"branch_id": 1, "title": "案件名稱"}

# Ragic 匯入
POST /api/?endpoint=sync&action=ragic_import
Header: X-API-Key: YOUR_KEY
Body: {"records": [{"ragic_id": "123", "title": "...", "branch_id": 1}]}

# 取得排工
GET /api/?endpoint=schedules&action=list&start_date=2026-03-01&end_date=2026-03-31&api_key=YOUR_KEY

# 取得人員/工程師
GET /api/?endpoint=staff&action=list&engineers_only=1&api_key=YOUR_KEY
```

## 部署

請參考 [DEPLOY.md](DEPLOY.md) 取得完整部署步驟。

## 預設帳號

| 帳號 | 密碼 | 角色 |
|------|------|------|
| admin | admin123 | 老闆 |

> 部署後請立即修改密碼

## 授權

私有專案，僅限內部使用。
