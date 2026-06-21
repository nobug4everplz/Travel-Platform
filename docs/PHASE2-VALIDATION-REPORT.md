# Travel Platform Phase 2 Validation Report

Date: 2026-05-26

## Scope

本階段完成 `traveler`、`planner`、`admin` 三角色核心互動閉環的必要修復與本機驗收，不新增產品範圍或資料表。

## Environment Result

| Item | Result |
| --- | --- |
| PHP | PASS - XAMPP PHP 8.2.12 (`C:\xampp\php\php.exe`) |
| Extensions | PASS - `PDO`, `pdo_mysql`, `session`, `filter` 可用 |
| Database | PASS - MariaDB 10.4.32 (`C:\xampp\mysql\bin\mysql.exe`) |
| Composer | PASS - Composer 2.9.8 (`C:\xampp\php\composer.phar`) |
| Schema import | PASS - `migrations/schema.sql` 已匯入 |
| Seed | PASS - 四組測試帳號及驗收資料可建立 |
| Local app | PASS - `http://127.0.0.1:8000/` 可載入 |

`.env` 已建立供本機連線使用，並由 `.gitignore` 排除，不納入提交。

## Implemented Fixes

| Area | Files | Change |
| --- | --- | --- |
| Public trip query | `lib/trips.php` | 公開行程查詢補回作者 email fallback 欄位，避免空名稱時資料缺欄。 |
| Traveler participation | `actions/participation.php` | 僅接受明確 `join` / `leave` intent，阻擋不存在參加紀錄的取消操作。 |
| Traveler reviews | `actions/review.php` | 僅接受明確 intent；更新與刪除前驗證評論歸屬與行程對應。 |
| Favorites | `actions/favorite-trip.php`, `actions/favorite-planner.php` | 收藏與取消收藏改為明確 intent，不再讓重送 `add` 意外變成取消。 |
| Planner save/publish | `actions/trip-save.php` | 限制 `draft` / `publish` intent，錯誤 `trip_id` 不會被誤判為新增。 |
| Planner dashboard | `public/planner-dashboard.php` | 收藏行程僅統計與列出已發布行程，避免草稿曝光。 |
| Admin review deletion | `actions/admin-delete-review.php` | 刪除評論與行程評分重算置於同一 transaction，維持聚合一致性。 |

## Browser Validation

### Public Browsing

Result: PASS

1. 未登入可載入首頁與公開行程/規劃師內容。
2. 搜尋 `Sarah` 可篩出對應規劃師與其公開行程。
3. 未登入不具備角色互動操作。

### Traveler Flow

Result: PASS

1. `traveler@example.com` 登入後導向 traveler dashboard 並顯示成功訊息。
2. 可收藏及取消收藏行程。
3. 可收藏及取消收藏規劃師。
4. 未參加行程只顯示參加後才能評論提示。
5. 參加行程後可新增、更新、刪除評論，且皆顯示操作結果。
6. 取消已評論的參加紀錄後，評論同步移除，行程顯示 `尚無評分` 與 `0 則評論`。

Database evidence after canceling the seeded Kyoto participation:

```text
trip_participations (traveler, Kyoto): 0
reviews (traveler, Kyoto): 0
trips.average_rating / review_count (Kyoto): NULL / 0
```

### Planner Flow

Result: PASS

1. `planner@example.com` 登入後導向 planner dashboard 並顯示成功訊息。
2. 建立 `台北週末咖啡散步` 草稿後留在 editor，作者可查看 `DRAFT PREVIEW`。
3. 將該草稿發布為 `台北週末咖啡散步公開版` 後，可於公開搜尋找到。
4. 嘗試開啟其他規劃師的 editor 會顯示找不到行程。
5. 可收藏及取消收藏公開行程。
6. 作者可預覽自己的 seed 草稿；登出後訪客不可查看草稿。

### Admin Flow

Result: PASS

1. `admin@example.com` 登入後導向 admin dashboard 並顯示成功訊息。
2. 可將 traveler 測試帳號更新為 planner，dashboard 立即反映。
3. 嘗試將自己降級時顯示錯誤訊息，資料庫角色仍為 `admin`。
4. 刪除京都評論後 dashboard 移除該評論，行程詳情顯示 `尚無評分` 與 `0 則評論`。
5. admin 行程詳情頁為唯讀，不顯示參加或收藏操作。

Database evidence after admin checks:

```text
admin@example.com role: admin
traveler@example.com role after test update: planner
Kyoto average_rating / review_count after deletion: NULL / 0
```

## Static Verification

| Check | Result |
| --- | --- |
| All PHP files: `php -l` | PASS |
| `composer check-platform-reqs --lock` | PASS |
| `git diff --check` | PASS |

## Evidence And Residual Risk

- Browser desktop captures were recorded during public search, traveler dashboard, planner dashboard, and admin dashboard verification.
- 每組角色驗收前皆重新執行 `php seed.php`，避免前一流程污染下一組結果。
- Browser 外掛此次可提供桌面互動與截圖，但沒有暴露 viewport 切換 API；mobile 視覺檢查尚未形成可重現證據，應在下一階段以可固定 viewport 的瀏覽器測試補上。
- Runtime 使用 XAMPP 內含的 MariaDB 相容服務；部署到 MySQL 8.x 前仍建議在目標環境重新跑 schema、seed 與上述三角色驗收。
