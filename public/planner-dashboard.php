<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

$user = require_role('planner');

$statsStmt = pdo()->prepare(
    'SELECT
        (SELECT COUNT(*) FROM trips WHERE author_id = ? AND is_published = 1) AS published_count,
        (SELECT COUNT(*) FROM trips WHERE author_id = ? AND is_published = 0) AS draft_count,
        (SELECT COUNT(*) FROM favorite_planners WHERE planner_id = ?) AS follower_count,
        (SELECT COUNT(*)
         FROM favorite_trips ft
         JOIN trips t ON t.id = ft.trip_id
         WHERE ft.user_id = ? AND t.is_published = 1) AS favorite_trip_count'
);
$statsStmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$stats = $statsStmt->fetch();

$tripsStmt = pdo()->prepare('SELECT * FROM trips WHERE author_id = ? ORDER BY updated_at DESC');
$tripsStmt->execute([$user['id']]);
$trips = $tripsStmt->fetchAll();
$drafts = array_values(array_filter($trips, fn (array $trip): bool => (int) $trip['is_published'] === 0));
$published = array_values(array_filter($trips, fn (array $trip): bool => (int) $trip['is_published'] === 1));

$favoriteTrips = pdo()->prepare(
    'SELECT ft.created_at, t.id, t.title, t.summary, t.average_rating
     FROM favorite_trips ft
     JOIN trips t ON t.id = ft.trip_id
     WHERE ft.user_id = ? AND t.is_published = 1
     ORDER BY ft.created_at DESC'
);
$favoriteTrips->execute([$user['id']]);
$favoriteTripRows = $favoriteTrips->fetchAll();

$today = date('Y-m-d');
$trendSince = date('Y-m-d', strtotime('-6 days'));
$recentSince = date('Y-m-d 00:00:00', strtotime('-2 days'));

$analyticsStmt = pdo()->prepare(
    'SELECT
        (SELECT COUNT(*)
         FROM trip_daily_unique_views v
         JOIN trips t ON t.id = v.trip_id
         WHERE t.author_id = ? AND t.is_published = 1 AND v.view_date >= ?) AS unique_views,
        (SELECT COUNT(*)
         FROM favorite_trips ft
         JOIN trips t ON t.id = ft.trip_id
         WHERE t.author_id = ? AND t.is_published = 1 AND ft.created_at >= ?) AS new_favorites,
        (SELECT COUNT(*)
         FROM trip_participations tp
         JOIN trips t ON t.id = tp.trip_id
         WHERE t.author_id = ? AND t.is_published = 1 AND tp.joined_at >= ?) AS new_participations,
        (SELECT COUNT(*)
         FROM reviews r
         JOIN trips t ON t.id = r.trip_id
         WHERE t.author_id = ? AND t.is_published = 1 AND r.created_at >= ?) AS new_reviews'
);
$analyticsStmt->execute([
    $user['id'],
    substr($recentSince, 0, 10),
    $user['id'],
    $recentSince,
    $user['id'],
    $recentSince,
    $user['id'],
    $recentSince,
]);
$analytics = $analyticsStmt->fetch();

$trendStmt = pdo()->prepare(
    'SELECT v.view_date, COUNT(*) AS unique_views
     FROM trip_daily_unique_views v
     JOIN trips t ON t.id = v.trip_id
     WHERE t.author_id = ? AND t.is_published = 1 AND v.view_date BETWEEN ? AND ?
     GROUP BY v.view_date
     ORDER BY v.view_date'
);
$trendStmt->execute([$user['id'], $trendSince, $today]);
$viewsByDate = [];
foreach ($trendStmt->fetchAll() as $point) {
    $viewsByDate[$point['view_date']] = (int) $point['unique_views'];
}

$viewTrend = [];
for ($day = 6; $day >= 0; $day--) {
    $date = date('Y-m-d', strtotime('-' . $day . ' days'));
    $viewTrend[] = ['date' => $date, 'views' => $viewsByDate[$date] ?? 0];
}
$maxDailyViews = max(1, ...array_column($viewTrend, 'views'));
$trendDescription = implode('，', array_map(
    fn (array $point): string => format_date($point['date']) . ' ' . $point['views'] . ' 次',
    $viewTrend
));

$bestRatedStmt = pdo()->prepare(
    'SELECT id, title, average_rating, review_count
     FROM trips
     WHERE author_id = ? AND is_published = 1 AND review_count > 0
     ORDER BY average_rating DESC, review_count DESC, updated_at DESC
     LIMIT 1'
);
$bestRatedStmt->execute([$user['id']]);
$bestRatedTrip = $bestRatedStmt->fetch();

$latestReviewsStmt = pdo()->prepare(
    'SELECT r.rating, r.comment, r.created_at, u.name AS reviewer_name,
            t.id AS trip_id, t.title AS trip_title
     FROM reviews r
     JOIN trips t ON t.id = r.trip_id
     JOIN users u ON u.id = r.reviewer_id
     WHERE t.author_id = ? AND t.is_published = 1
     ORDER BY r.created_at DESC
     LIMIT 5'
);
$latestReviewsStmt->execute([$user['id']]);
$latestReviews = $latestReviewsStmt->fetchAll();

// ───── Hot spots ─────
$hotSpotsStmt = pdo()->prepare(
    'SELECT ts.name, ts.address, COUNT(DISTINCT ts.trip_id) AS trip_count
     FROM trip_spots ts
     JOIN trips t ON t.id = ts.trip_id
     WHERE t.author_id = ? AND t.is_published = 1
     GROUP BY ts.name, ts.address
     ORDER BY trip_count DESC, ts.name ASC
     LIMIT 10'
);
$hotSpotsStmt->execute([$user['id']]);
$hotSpots = $hotSpotsStmt->fetchAll();

$pageTitle = '規劃師工作台';
$pageType = 'planner_dashboard';

// Map: published trips with coordinates
$mapPublished = array_filter($published, fn($t) => !empty($t['latitude']) && !empty($t['longitude']));
$loadMap = !empty($mapPublished);

require __DIR__ . '/../partials/header.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Planner Dashboard</p>
        <h1><?= e($user['name'] ?: $user['email']) ?></h1>
        <p class="muted">建立草稿、發布行程，並查看收藏與追蹤狀態。</p>
    </div>
    <a class="button primary" href="/editor.php">新增行程</a>
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
            <div class="stat"><strong><?= (int) $stats['published_count'] ?></strong><span>已發布</span></div>
            <div class="stat"><strong><?= (int) $stats['draft_count'] ?></strong><span>草稿</span></div>
            <div class="stat"><strong><?= (int) $stats['follower_count'] ?></strong><span>收藏者</span></div>
            <div class="stat"><strong><?= (int) $stats['favorite_trip_count'] ?></strong><span>收藏行程</span></div>
        </div>
    </div>
</section>

<?php if (!empty($mapPublished)): ?>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Map</p><h2>已發布行程地圖</h2></div></div>
    <div id="planner-trip-map" style="height: 350px; border-radius: 12px;"></div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var map = initMap('planner-trip-map');
        var markers = [];
        var trips = <?= json_encode(array_map(fn($t) => [
            'id' => (int)$t['id'],
            'title' => $t['title'],
            'latitude' => $t['latitude'],
            'longitude' => $t['longitude'],
            'average_rating' => $t['average_rating'],
            'summary' => $t['summary'],
        ], array_values($mapPublished)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        trips.forEach(function(trip) {
            var m = addTripMarker(map, trip);
            if (m) markers.push(m);
        });
        if (markers.length > 1) fitAllMarkers(map, markers);
    });
    </script>
</section>
<?php endif; ?>

<section class="panel" aria-labelledby="planner-insights-title">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Analytics</p>
            <h2 id="planner-insights-title">行程成效</h2>
        </div>
        <p class="muted">近 3 天互動與整體評分</p>
    </div>
    <div class="stats five">
        <div class="stat"><strong><?= (int) $analytics['unique_views'] ?></strong><span>不重複瀏覽</span></div>
        <div class="stat"><strong><?= (int) $analytics['new_favorites'] ?></strong><span>新增收藏</span></div>
        <div class="stat"><strong><?= (int) $analytics['new_participations'] ?></strong><span>新增參加</span></div>
        <div class="stat"><strong><?= (int) $analytics['new_reviews'] ?></strong><span>新增評論</span></div>
        <div class="stat">
            <strong><?= $bestRatedTrip ? e(format_rating($bestRatedTrip['average_rating'])) : '-' ?></strong>
            <span>最高評分</span>
        </div>
    </div>
    <div class="insight-grid">
        <div>
            <h3>近 7 天不重複瀏覽</h3>
            <div class="trend-chart" role="img" aria-label="<?= e($trendDescription) ?>">
                <?php foreach ($viewTrend as $point): ?>
                    <?php $barHeight = max(4, (int) round(((int) $point['views'] / $maxDailyViews) * 100)); ?>
                    <div class="trend-column">
                        <span class="trend-value"><?= (int) $point['views'] ?></span>
                        <span class="trend-bar" style="height: <?= $barHeight ?>%"></span>
                        <span class="trend-label"><?= e(date('m/d', strtotime($point['date']))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <h3>最佳評價行程</h3>
            <?php if (!$bestRatedTrip): ?>
                <div class="empty-state">尚無已評分的公開行程。</div>
            <?php else: ?>
                <p><a class="text-link" href="/trip.php?id=<?= (int) $bestRatedTrip['id'] ?>"><?= e($bestRatedTrip['title']) ?></a></p>
                <p class="meta"><?= e(format_rating($bestRatedTrip['average_rating'])) ?>，<?= (int) $bestRatedTrip['review_count'] ?> 則評論</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($hotSpots): ?>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Popular Spots</p><h2>熱門景點</h2></div></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>景點名稱</th><th>地址</th><th>使用次數</th></tr>
            </thead>
            <tbody>
                <?php foreach ($hotSpots as $spot): ?>
                    <tr>
                        <td><strong><?= e($spot['name']) ?></strong></td>
                        <td class="muted"><?= e($spot['address'] ?? '-') ?></td>
                        <td><span class="badge"><?= (int) $spot['trip_count'] ?> 次</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Reviews</p><h2>最新評論</h2></div></div>
    <?php if (!$latestReviews): ?>
        <div class="empty-state">公開行程目前尚無評論。</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>行程</th><th>旅人</th><th>評論</th><th>日期</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($latestReviews as $review): ?>
                        <tr>
                            <td><a class="text-link" href="/trip.php?id=<?= (int) $review['trip_id'] ?>"><?= e($review['trip_title']) ?></a></td>
                            <td><?= e($review['reviewer_name'] ?: '匿名使用者') ?></td>
                            <td><span class="badge gray"><?= (int) $review['rating'] ?> 分</span> <?= e($review['comment'] ?: '沒有文字評論。') ?></td>
                            <td class="muted"><?= e(format_date($review['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Drafts</p><h2>草稿</h2></div></div>
    <?php if (!$drafts): ?>
        <div class="empty-state">目前沒有草稿。</div>
    <?php else: ?>
        <div class="grid two">
            <?php foreach ($drafts as $trip): ?>
                <article class="card"><div class="card-body">
                    <span class="badge gray">草稿</span>
                    <h3><a href="/editor.php?id=<?= (int) $trip['id'] ?>"><?= e($trip['title']) ?></a></h3>
                    <p class="muted"><?= e($trip['summary'] ?: '這個行程尚未填寫摘要。') ?></p>
                    <div class="actions">
                        <a class="button small" href="/editor.php?id=<?= (int) $trip['id'] ?>">編輯</a>
                        <a class="button small" href="/trip.php?id=<?= (int) $trip['id'] ?>">預覽</a>
                    </div>
                </div></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Published</p><h2>已發布行程</h2></div></div>
    <?php if (!$published): ?>
        <div class="empty-state">目前沒有已發布行程。</div>
    <?php else: ?>
        <div class="grid two">
            <?php foreach ($published as $trip): ?>
                <article class="card"><div class="card-body">
                    <span class="badge">已發布</span>
                    <h3><a href="/trip.php?id=<?= (int) $trip['id'] ?>"><?= e($trip['title']) ?></a></h3>
                    <p class="muted"><?= e($trip['summary'] ?: '這個行程尚未填寫摘要。') ?></p>
                    <p class="meta">評分 <?= e(format_rating($trip['average_rating'])) ?>，<?= (int) $trip['review_count'] ?> 則評論</p>
                    <div class="actions">
                        <a class="button small" href="/trip.php?id=<?= (int) $trip['id'] ?>">查看公開頁</a>
                        <a class="button small" href="/editor.php?id=<?= (int) $trip['id'] ?>">編輯</a>
                    </div>
                </div></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Saved</p><h2>收藏行程</h2></div></div>
    <?php if (!$favoriteTripRows): ?>
        <div class="empty-state">尚未收藏任何行程。</div>
    <?php else: ?>
        <div class="grid two">
            <?php foreach ($favoriteTripRows as $trip): ?>
                <article class="card"><div class="card-body">
                    <span class="badge"><?= e(format_rating($trip['average_rating'])) ?></span>
                    <h3><a href="/trip.php?id=<?= (int) $trip['id'] ?>"><?= e($trip['title']) ?></a></h3>
                    <p class="muted"><?= e($trip['summary'] ?: '這個行程尚未填寫摘要。') ?></p>
                    <a class="button small" href="/trip.php?id=<?= (int) $trip['id'] ?>">查看行程</a>
                </div></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/account-security.php'; ?>
<?php require __DIR__ . '/../partials/footer.php'; ?>
