# Travel Platform Phase 2 Validation Instructions

## Goal

完成 `traveler`、`planner`、`admin` 三角色核心流程的真實可操作閉環驗收與必要修復。

## Non-Goals

- 不新增付款、訂單、聊天、圖片上傳或新資料表。
- 不更換 PHP + MySQL + PDO 架構。
- 不進行與 Phase 2 無關的視覺重構。

## Runtime Preconditions

Required:

- PHP 8.2 or 8.3 with `pdo`, `pdo_mysql`, `session`, `filter`
- MySQL 8.x or MariaDB compatible server

Recommended:

- Composer

## Boot Sequence

1. Copy `.env.example` to `.env` and configure DB credentials.
2. Import `migrations/schema.sql`.
3. Run `php seed.php`.
4. Start `php -S localhost:8000 -t public public/router.php`.
5. Test against `http://localhost:8000/`.

## Seed Accounts

- `admin@example.com` / `password123` / `admin`
- `traveler@example.com` / `password123` / `traveler`
- `planner@example.com` / `password123` / `planner`
- `planner2@example.com` / `password123` / `planner`

## Shared Rules

- You are not alone in this codebase. Do not revert changes made by other workers.
- Edit only files assigned to your worker unless the lead explicitly reassigns ownership.
- Do not change schema, shared helpers, auth, routing, layout partials, CSS, README, seed, or `instruction.md`.
- Preserve CSRF checks, role guards, prepared PDO statements, redirects, and flash messages.
- Every user action must end on a meaningful page with visible success or error feedback.
- Report changed files, tested flows, unresolved risks, and exact repro steps.

## Comment Style

Only comment non-obvious business rules:

```php
// Leaving a trip invalidates the traveler's review and its aggregated rating.
$reviewDelete = pdo()->prepare('DELETE FROM reviews WHERE reviewer_id = ? AND trip_id = ?');
```

```php
// Prevent the active admin session from removing its own admin access.
if ($userId === (int) $admin['id'] && $role !== 'admin') {
    flash('error', '不能移除自己的管理員角色。');
    redirect('/admin-dashboard.php');
}
```

Do not narrate obvious operations:

```php
// Delete the review.
$delete->execute([$reviewId]);
```

## Worker T: Traveler Flow

Owned files:

- `public/traveler-dashboard.php`
- `public/trip.php`
- `public/index.php`
- `public/planner.php`
- `actions/participation.php`
- `actions/review.php`
- `actions/favorite-trip.php`
- `actions/favorite-planner.php`

Required scenarios:

1. Traveler login lands on `/traveler-dashboard.php`.
2. Homepage search resolves trip title, trip summary, and planner name.
3. Public trip and planner detail pages are navigable.
4. Trip and planner favorites can be added and removed.
5. Reviews can only be created after joining a trip.
6. Existing reviews can be updated and deleted.
7. Canceling participation removes that traveler's review and recalculates rating totals.
8. Planner and admin visibility on the trip page obeys role restrictions.

Required behavior:

- Actions return to the relevant public page with a visible flash result.
- Dashboard collections and counters reflect each completed interaction.
- Planner users do not see join/review actions.
- Admin users see read-only trip interactions.

## Worker P: Planner Flow

Owned files:

- `public/planner-dashboard.php`
- `public/editor.php`
- `actions/trip-save.php`

Required scenarios:

1. Planner login lands on `/planner-dashboard.php`.
2. A new draft can be created and previewed.
3. A draft is visible only to its author or an admin.
4. Publishing makes a trip publicly discoverable.
5. The author can edit an existing trip.
6. A planner can favorite and remove a public trip favorite; report issues in traveler-owned actions to the lead only.
7. A planner cannot edit another planner's trip.

Required behavior:

- Draft and publish saves display success feedback.
- Saving stays in the editor with paths back to the dashboard and trip page.
- Draft, published, and favorite statistics reflect saved state.

## Worker A: Admin Flow

Owned files:

- `public/admin-dashboard.php`
- `actions/admin-user-role.php`
- `actions/admin-delete-review.php`

Required scenarios:

1. Admin login lands on `/admin-dashboard.php`.
2. Admin can update another user's role.
3. Admin cannot demote its own current account.
4. Admin can delete a review and trigger trip rating/count recomputation.

Required behavior:

- Success and error flash messages are present for role and review operations.
- Deleted reviews disappear from the dashboard and associated trip page.
- Trip aggregate rating and review count reflect deletion.

## Lead-Only Ownership

Workers must not modify:

- `instruction.md`
- `config/database.php`
- `migrations/schema.sql`
- `seed.php`
- `lib/auth.php`
- `lib/helpers.php`
- `lib/trips.php`
- `lib/reviews.php`
- `partials/*`
- `public/assets/app.css`
- `public/router.php`
- `README.md`
- `composer.json`
- `.env.example`

If shared work is needed, report:

```md
Shared Change Request:
- File:
- Current behavior:
- Required behavior:
- Reproduction:
- Suggested minimal fix:
```

## Validation Note Template

```md
### Scenario
Traveler cancels a joined trip after leaving a review.

### Steps
1. Login as `traveler@example.com`.
2. Open the joined trip.
3. Confirm an existing review is displayed.
4. Click `取消參加`.

### Expected
- Success flash is displayed.
- Traveler returns to the trip page.
- Participation no longer exists.
- The review is removed.
- Trip rating and review count are recalculated.

### Result
PASS / FAIL

### Files Changed
- `actions/participation.php`

### Evidence
- Browser screenshot path or visible confirmation
- Optional SQL query result
```

## Integration Verification

Before and after worker integration, the lead runs:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
composer check-platform-reqs
git diff --check
```

Reset state before validating each role:

```powershell
php seed.php
```

The lead performs final Browser verification in this order:

1. Logged-out public browsing and search.
2. Traveler full flow.
3. Reset seed data.
4. Planner full flow.
5. Reset seed data.
6. Admin full flow.
7. Desktop and mobile visual inspection of core pages.
