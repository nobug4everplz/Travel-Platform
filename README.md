# 🧳 Travel Platform — 旅遊市集平台

> **PHP 8 + PostgreSQL 全端旅遊平台**  
> 整合 AI 對話、互動地圖、即時天氣匯率、行程管理、郵件通知  
> 🔗 線上 Demo：[travel-platform-demo.fly.dev](https://travel-platform-demo.fly.dev)

---

## 📋 功能總覽

### 🗺️ 互動地圖
- 基於 **Leaflet.js** 的行程地圖，標記所有公開行程地點
- 點擊標記查看行程詳情、評分、摘要
- 一鍵縮放至所有行程範圍

### 🤖 AI 智慧助理（DeepSeek V3）
- 右下角 **聊天浮窗**，登入後即可使用
- 根據當前頁面 context 回答行程相關問題
- **Function Calling**：可查天氣、匯率、行程搜尋
- 防幻覺機制：明確範圍限制、來源引用、不敢確定的會說不知道
- Token 用量追蹤（`ai_usage_log`）

### 🌤️ 即時天氣
- 串接 **OpenWeatherMap API**
- 行程頁顯示目的地天氣，含 emoji 圖示
- 30 分鐘快取，一次拉取 4 城市減少 API 呼叫

### 💱 即時匯率
- 顯示行程預算的外幣對台幣參考匯率

### 📄 PDF 行程手冊匯出
- 使用 **TCPDF** 生成繁體中文 PDF
- 嵌入 **Noto Sans TC** 字型，跨平台正確顯示
- 包含行程資訊、景點列表、裝備建議

### 🖼️ 照片牆
- 每個行程獨立的照片牆，旅客可上傳旅途照片
- Fly.io persistent volume 儲存，不怕重部署

### 🎒 裝備購物車
- 規劃師可為行程加入建議裝備（名稱 + 購買連結）
- 旅客查看行程時一併顯示

### 👥 三種角色
| 角色 | 功能 |
|------|------|
| **Traveler** 旅客 | 瀏覽行程、參加、收藏、評論、上傳照片、AI 對話 |
| **Planner** 規劃師 | 發布/編輯行程、管理景點與裝備、查看數據 |
| **Admin** 管理員 | 儀表板、用戶管理、評論審核、流量分析 |

### ⭐ 社群互動
- 行程評分與評論
- 收藏行程 / 收藏規劃師
- 旅行者媒合推薦
- 足跡地圖

### 📊 數據分析
- 管理後台：DAU、新註冊、角色分佈、裝置分佈
- 行程每日不重複瀏覽統計
- 規劃師儀表板：發布數、參加數、收藏數

### 💌 自動化郵件
- **PHPMailer SMTP** 發送
- 四支排程腳本：
  - 每日管理摘要（09:00）
  - 規劃師三日摘要（09:00）
  - 沉睡用戶召回（09:00）
  - 每日熱門行程（17:00）
- 註冊歡迎信、新裝置登入警示

### 🔐 安全性
- CSRF 防護（所有表單）
- Session 管理 + 信任裝置追蹤
- 新裝置登入自動郵件通知
- 密碼 bcrypt 雜湊
- `public/` 為 web root，action 檔案隔離在外部

---

## 🛠️ 技術棧

| 分類 | 技術 |
|------|------|
| **後端** | PHP 8.2, PDO |
| **資料庫** | PostgreSQL 17（從 MySQL 遷移） |
| **前端** | Vanilla JS + CSS，Leaflet.js 地圖，零前端框架 |
| **AI** | DeepSeek V3 API（chat + function calling） |
| **外部 API** | OpenWeatherMap（天氣）、Exchange Rate（匯率） |
| **PDF** | TCPDF 6.11 + Noto Sans TC 內嵌字型 |
| **郵件** | PHPMailer 6.10 SMTP |
| **部署** | Fly.io（Docker + persistent volume） |
| **依賴管理** | Composer |

---

## 📁 專案結構

```
├── public/                  # Web root（對外）
│   ├── index.php            # 首頁：行程搜尋 + 地圖
│   ├── trip.php             # 行程詳情（天氣/匯率/照片牆/裝備/AI）
│   ├── planner.php          # 規劃師公開頁
│   ├── editor.php           # 行程編輯器
│   ├── login.php / register.php
│   ├── traveler-dashboard.php
│   ├── planner-dashboard.php
│   ├── admin-dashboard.php  # 管理後台：數據儀表板 + 用戶管理
│   ├── router.php           # 路由：/actions/* → 外部 actions/
│   ├── assets/
│   │   ├── app.css
│   │   ├── map-utils.js     # Leaflet 地圖工具
│   │   └── chat-widget.js   # AI 聊天浮窗（484 行，零外部依賴）
│   └── .htaccess
│
├── actions/                 # 表單處理（不可直接訪問）
│   ├── login.php, register.php, logout.php
│   ├── chat.php             # AI 對話 API endpoint
│   ├── trip-save.php        # 行程儲存
│   ├── participation.php    # 參加/退出行程
│   ├── favorite-trip.php, favorite-planner.php
│   ├── review.php           # 評論
│   └── upload-photo.php     # 照片上傳
│
├── lib/                     # 商業邏輯層
│   ├── auth.php             # 登入/session/CSRF/信任裝置
│   ├── trips.php            # 行程 CRUD
│   ├── ai.php               # DeepSeek API 呼叫
│   ├── ai-context.php       # AI system prompt 建構
│   ├── ai-tools.php         # Function calling 工具定義
│   ├── weather.php          # OpenWeatherMap（30min cache）
│   ├── currency.php         # 匯率轉換
│   ├── pdf-export.php       # TCPDF PDF 匯出
│   ├── trip-photos.php      # 照片牆
│   ├── trip-gear.php        # 裝備建議
│   ├── analytics.php        # 管理後台分析
│   ├── notifications.php    # 通知偏好
│   ├── trusted-devices.php  # 信任裝置管理
│   ├── mail.php             # PHPMailer SMTP
│   ├── spot-actions.php     # 景點管理
│   ├── footprint-actions.php # 足跡
│   ├── traveler-match.php   # 旅者媒合
│   ├── recommendations.php  # 個人化推薦
│   ├── trip-views.php       # 瀏覽統計
│   ├── reviews.php          # 評論
│   └── helpers.php          # 工具函數
│
├── config/
│   └── database.php         # PDO 連線 + env loader
│
├── partials/
│   ├── header.php           # 共用 header（nav + 角色選單）
│   └── footer.php           # 共用 footer（載入 chat-widget）
│
├── scripts/                 # 排程郵件腳本
│   ├── send-daily-admin-digest.php
│   ├── send-planner-three-day-digest.php
│   ├── send-winback-emails.php
│   └── send-daily-popular-digest.php
│
├── migrations/
│   └── schema.sql
│
├── docs/
│   └── HANDOFF-2026-06-25.md
│
├── Dockerfile               # Fly.io 部署
├── fly.toml                 # Fly.io 設定（256MB, auto-stop）
├── init-db.php              # 自動初始化 schema + migration
├── seed.php                 # 種子資料
├── composer.json
└── README.md
```

---

## 🚀 快速啟動

### 需求
- PHP 8.0+
- PostgreSQL 17（或 MySQL 8.0+）
- Composer

### 1. 安裝依賴
```bash
composer install
```

### 2. 設定環境變數
```bash
cp .env.example .env
# 編輯 .env 填入資料庫連線 + API keys
```

### 3. 初始化資料庫
```bash
php init-db.php
php seed.php
```

### 4. 啟動
```bash
composer run serve
# 或
php -S localhost:8000 -t public public/router.php
```
打開 http://localhost:8000

### 種子帳號
| 角色 | Email | 密碼 |
|------|-------|------|
| Admin | `admin@example.com` | `password123` |
| Traveler | `traveler@example.com` | `password123` |
| Planner | `planner@example.com` | `password123` |
| Planner | `planner2@example.com` | `password123` |

---

## ☁️ 部署（Fly.io）

專案已部署於 Fly.io，使用 Docker 容器化：

```bash
flyctl deploy
```

- **Region:** lax (Los Angeles)
- **規格:** shared-cpu-1x, 256MB RAM
- **資料庫:** Fly.io unmanaged PostgreSQL 17
- **儲存:** persistent volume（照片上傳）
- **自動休眠:** 無流量時自動停止，有請求自動喚醒

---

## ⚙️ 環境變數

| 變數 | 用途 | 必要 |
|------|------|------|
| `DATABASE_URL` | PostgreSQL 連線字串 | ✅ |
| `DEEPSEEK_API_KEY` | AI Chat | ✅ |
| `APP_URL` | 站台網址 | ✅ |
| `APP_TIMEZONE` | 時區（預設 Asia/Taipei） | ❌ |
| `OPENWEATHERMAP_API_KEY` | 天氣資訊 | ❌ |
| `MAIL_HOST` ~ `MAIL_ENCRYPTION` | SMTP 郵件設定 | ❌ |
| `PLANNER_DIGEST_ANCHOR_DATE` | 規劃師摘要基準日 | ❌ |

---

## ⚠️ 部署注意事項

1. **Web root 必須指向 `public/`**，不可指向專案根目錄
2. 所有 `/actions/*` 請求透過 `router.php` 轉發到外部 `actions/` 目錄
3. PostgreSQL 環境下需注意：boolean 值用 `'true'/'false'` 字串，不可用 `1/0`
4. `.env` 不應進版本控制（已在 `.gitignore`）
5. 照片上傳需要 persistent volume 或 S3，否則重部署會遺失

---

## 📝 License

 Proprietary — 學術專案，僅供展示與學習用途。
