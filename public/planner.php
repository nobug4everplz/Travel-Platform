<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';

$plannerId = input_int($_GET, 'id');
if ($plannerId === null) {
    abort_page(400, '缺少規劃師', '請提供有效的規劃師 ID。');
}

$planner = get_planner($plannerId);
if (!$planner) {
    abort_page(404, '找不到規劃師', '這位公開規劃師不存在。');
}

$user = current_user();
$isFavorited = $user && $user['role'] === 'traveler'
    ? traveler_favorited_planner((int) $user['id'], $plannerId)
    : false;
$trips = get_planner_public_trips($plannerId);

$pageTitle = $planner['name'] ?: '規劃師';
require __DIR__ . '/../partials/header.php';
?>
<section class="page-heading">
    <div class="person">
        <?php if ($planner['avatar_url']): ?>
            <img class="avatar" src="<?= e($planner['avatar_url']) ?>" alt="<?= e($planner['name']) ?>">
        <?php else: ?>
            <div class="placeholder-avatar"><?= e(display_initial($planner['name'] ?: 'P')) ?></div>
        <?php endif; ?>
        <div>
            <p class="eyebrow">Planner Profile</p>
            <h1><?= e($planner['name'] ?: $planner['email']) ?></h1>
            <p class="muted"><?= (int) $planner['published_trip_count'] ?> 個公開行程，加入於 <?= e(format_date($planner['created_at'])) ?></p>
        </div>
    </div>
    <?php if ($user && $user['role'] === 'traveler'): ?>
        <form method="post" action="/actions/favorite-planner.php" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="planner_id" value="<?= (int) $planner['id'] ?>">
            <input type="hidden" name="intent" value="<?= $isFavorited ? 'remove' : 'add' ?>">
            <button class="primary" type="submit"><?= $isFavorited ? '取消收藏' : '收藏規劃師' ?></button>
        </form>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Trips</p>
            <h2>公開行程</h2>
        </div>
    </div>
    <?php if (!$trips): ?>
        <div class="empty-state">這位規劃師尚未發布公開行程。</div>
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
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
