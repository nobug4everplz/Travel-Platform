<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/reviews.php';

$user = require_role('traveler');

$statsStmt = pdo()->prepare(
    'SELECT
        (SELECT COUNT(*) FROM trip_participations WHERE user_id = ?) AS participation_count,
        (SELECT COUNT(*) FROM favorite_trips WHERE user_id = ?) AS favorite_trip_count,
        (SELECT COUNT(*) FROM favorite_planners WHERE traveler_id = ?) AS favorite_planner_count,
        (SELECT COUNT(*) FROM reviews WHERE reviewer_id = ?) AS review_count'
);
$statsStmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$stats = $statsStmt->fetch();

$participations = pdo()->prepare(
    'SELECT p.joined_at, t.id, t.title, t.summary, t.cover_image, t.average_rating
     FROM trip_participations p
     JOIN trips t ON t.id = p.trip_id
     WHERE p.user_id = ?
     ORDER BY p.joined_at DESC'
);
$participations->execute([$user['id']]);
$participationRows = $participations->fetchAll();

$favoriteTrips = pdo()->prepare(
    'SELECT ft.created_at, t.id, t.title, t.summary, t.cover_image, t.average_rating
     FROM favorite_trips ft
     JOIN trips t ON t.id = ft.trip_id
     WHERE ft.user_id = ?
     ORDER BY ft.created_at DESC'
);
$favoriteTrips->execute([$user['id']]);
$favoriteTripRows = $favoriteTrips->fetchAll();

$favoritePlanners = pdo()->prepare(
    'SELECT fp.created_at, u.id, u.email, u.name, u.avatar_url,
            COUNT(t.id) AS published_trip_count
     FROM favorite_planners fp
     JOIN users u ON u.id = fp.planner_id
     LEFT JOIN trips t ON t.author_id = u.id AND t.is_published = 1
     WHERE fp.traveler_id = ?
     GROUP BY fp.created_at, u.id, u.email, u.name, u.avatar_url
     ORDER BY fp.created_at DESC'
);
$favoritePlanners->execute([$user['id']]);
$favoritePlannerRows = $favoritePlanners->fetchAll();

$myReviews = pdo()->prepare(
    'SELECT r.id, r.rating, r.comment, r.updated_at, t.id AS trip_id, t.title AS trip_title
     FROM reviews r
     JOIN trips t ON t.id = r.trip_id
     WHERE r.reviewer_id = ?
     ORDER BY r.updated_at DESC'
);
$myReviews->execute([$user['id']]);
$myReviewRows = $myReviews->fetchAll();

$pageTitle = '旅人工作台';
require __DIR__ . '/../partials/header.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Traveler Dashboard</p>
        <h1><?= e($user['name'] ?: $user['email']) ?></h1>
        <p class="muted">管理已參加行程、收藏清單、評論紀錄與個人資料。</p>
    </div>
</section>
<section class="panel">
    <div class="grid two">
        <form method="post" action="/actions/profile-update.php" class="form-grid">
            <?= csrf_field() ?>
            <label>顯示名稱
                <input type="text" name="name" value="<?= e($user['name']) ?>" maxlength="120">
            </label>
            <label>頭像網址
                <input type="url" name="avatar_url" value="<?= e($user['avatar_url']) ?>" placeholder="https://...">
            </label>
            <button class="primary" type="submit">更新個人資料</button>
        </form>
        <div class="stats">
            <div class="stat"><strong><?= (int) $stats['participation_count'] ?></strong><span>已參加</span></div>
            <div class="stat"><strong><?= (int) $stats['favorite_trip_count'] ?></strong><span>收藏行程</span></div>
            <div class="stat"><strong><?= (int) $stats['favorite_planner_count'] ?></strong><span>收藏規劃師</span></div>
            <div class="stat"><strong><?= (int) $stats['review_count'] ?></strong><span>評論</span></div>
        </div>
    </div>
</section>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Joined</p><h2>已參加行程</h2></div></div>
    <?php if (!$participationRows): ?>
        <div class="empty-state">尚未參加任何行程。</div>
    <?php else: ?>
        <div class="grid two">
            <?php foreach ($participationRows as $trip): ?>
                <article class="card"><div class="card-body">
                    <span class="badge"><?= e(format_rating($trip['average_rating'])) ?></span>
                    <h3><a href="/trip.php?id=<?= (int) $trip['id'] ?>"><?= e($trip['title']) ?></a></h3>
                    <p class="muted"><?= e($trip['summary'] ?: '這個行程尚未填寫摘要。') ?></p>
                    <p class="meta">參加於 <?= e(format_date($trip['joined_at'])) ?></p>
                    <a class="button small" href="/trip.php?id=<?= (int) $trip['id'] ?>">前往評論或取消參加</a>
                </div></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Favorites</p><h2>收藏行程</h2></div></div>
    <?php if (!$favoriteTripRows): ?>
        <div class="empty-state">尚未收藏任何行程。</div>
    <?php else: ?>
        <div class="grid two">
            <?php foreach ($favoriteTripRows as $trip): ?>
                <article class="card"><div class="card-body">
                    <span class="badge"><?= e(format_rating($trip['average_rating'])) ?></span>
                    <h3><a href="/trip.php?id=<?= (int) $trip['id'] ?>"><?= e($trip['title']) ?></a></h3>
                    <p class="muted"><?= e($trip['summary'] ?: '這個行程尚未填寫摘要。') ?></p>
                    <p class="meta">收藏於 <?= e(format_date($trip['created_at'])) ?></p>
                </div></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Planners</p><h2>收藏規劃師</h2></div></div>
    <?php if (!$favoritePlannerRows): ?>
        <div class="empty-state">尚未收藏任何規劃師。</div>
    <?php else: ?>
        <div class="grid three">
            <?php foreach ($favoritePlannerRows as $planner): ?>
                <article class="card"><div class="card-body">
                    <h3><a href="/planner.php?id=<?= (int) $planner['id'] ?>"><?= e($planner['name'] ?: $planner['email']) ?></a></h3>
                    <p class="muted"><?= (int) $planner['published_trip_count'] ?> 個公開行程</p>
                    <a class="button small" href="/planner.php?id=<?= (int) $planner['id'] ?>">查看規劃師</a>
                </div></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Reviews</p><h2>我的評論</h2></div></div>
    <?php if (!$myReviewRows): ?>
        <div class="empty-state">尚未留下任何評論。</div>
    <?php else: ?>
        <div class="grid two">
            <?php foreach ($myReviewRows as $review): ?>
                <article class="card"><div class="card-body">
                    <span class="badge gray"><?= (int) $review['rating'] ?> 星</span>
                    <h3><a href="/trip.php?id=<?= (int) $review['trip_id'] ?>"><?= e($review['trip_title']) ?></a></h3>
                    <p class="muted"><?= e($review['comment'] ?: '沒有文字評論。') ?></p>
                    <p class="meta">更新於 <?= e(format_date($review['updated_at'])) ?></p>
                    <a class="button small" href="/trip.php?id=<?= (int) $review['trip_id'] ?>">修改或刪除評論</a>
                </div></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/account-security.php'; ?>
<?php require __DIR__ . '/../partials/footer.php'; ?>
