<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';

$pageTitle = '探索行程';
$query = trim((string) ($_GET['q'] ?? ''));
$trips = get_public_trips($query);
$planners = get_public_planners($query);

require __DIR__ . '/../partials/header.php';
?>
<section class="hero">
    <div>
        <p class="eyebrow">Travel Marketplace</p>
        <h1>探索公開行程，找到適合你的下一段旅程</h1>
        <p class="muted">瀏覽規劃師發布的精選行程、查看評分與評論，登入後即可收藏、參加與分享心得。</p>
    </div>
    <form method="get" action="/index.php" class="form-grid">
        <label>搜尋行程、摘要或規劃師
            <input type="search" name="q" value="<?= e($query) ?>" placeholder="東京、親子、Sarah">
        </label>
        <button class="primary" type="submit">搜尋</button>
    </form>
</section>

<section class="panel">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Trips</p>
            <h2>公開行程</h2>
            <p class="muted"><?= $query !== '' ? '以下是符合搜尋條件的公開行程。' : '精選可報名與收藏的公開行程。' ?></p>
        </div>
    </div>
    <?php if (!$trips): ?>
        <div class="empty-state">目前沒有符合條件的公開行程。</div>
    <?php else: ?>
        <div class="grid three">
            <?php foreach ($trips as $trip): ?>
                <article class="card">
                    <?php if ($trip['cover_image']): ?>
                        <img class="cover" src="<?= e($trip['cover_image']) ?>" alt="<?= e($trip['title']) ?>">
                    <?php else: ?>
                        <div class="placeholder-cover">Trip</div>
                    <?php endif; ?>
                    <div class="card-body">
                        <span class="badge"><?= e(format_rating($trip['average_rating'])) ?></span>
                        <h3><a href="/trip.php?id=<?= (int) $trip['id'] ?>"><?= e($trip['title']) ?></a></h3>
                        <p class="muted"><?= e($trip['summary'] ?: '這個行程尚未填寫摘要。') ?></p>
                        <p class="meta">規劃師 <a class="text-link" href="/planner.php?id=<?= (int) $trip['author_id'] ?>"><?= e($trip['author_name'] ?: $trip['author_email']) ?></a></p>
                        <a class="button small" href="/trip.php?id=<?= (int) $trip['id'] ?>">查看詳情</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Planners</p>
            <h2>公開規劃師</h2>
        </div>
    </div>
    <?php if (!$planners): ?>
        <div class="empty-state">目前沒有符合條件的規劃師。</div>
    <?php else: ?>
        <div class="grid three">
            <?php foreach ($planners as $planner): ?>
                <article class="card">
                    <div class="card-body">
                        <div class="person">
                            <?php if ($planner['avatar_url']): ?>
                                <img class="avatar" src="<?= e($planner['avatar_url']) ?>" alt="<?= e($planner['name']) ?>">
                            <?php else: ?>
                                <div class="placeholder-avatar"><?= e(display_initial($planner['name'] ?: 'P')) ?></div>
                            <?php endif; ?>
                            <div>
                                <h3><a href="/planner.php?id=<?= (int) $planner['id'] ?>"><?= e($planner['name'] ?: $planner['email']) ?></a></h3>
                                <p class="muted"><?= (int) $planner['published_trip_count'] ?> 個公開行程</p>
                            </div>
                        </div>
                        <a class="button small" href="/planner.php?id=<?= (int) $planner['id'] ?>">查看作品</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
