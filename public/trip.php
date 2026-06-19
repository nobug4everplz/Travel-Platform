<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';
require_once __DIR__ . '/../lib/reviews.php';
require_once __DIR__ . '/../lib/trip-views.php';

$tripId = input_int($_GET, 'id');
if ($tripId === null) {
    abort_page(400, '缺少行程', '請提供有效的行程 ID。');
}

$user = current_user();
$trip = find_visible_trip($tripId, $user);
if (!$trip) {
    abort_page(404, '找不到行程', '這個行程不存在，或你沒有權限查看。');
}

record_trip_unique_view($trip, $user);

$isPublished = (int) $trip['is_published'] === 1;
$isTraveler = $user && $user['role'] === 'traveler';
$isPlanner = $user && $user['role'] === 'planner';
$isAuthor = $isPlanner && (int) $trip['author_id'] === (int) $user['id'];
$reviews = get_trip_reviews($tripId);
$isParticipating = $isTraveler ? user_has_participation((int) $user['id'], $tripId) : false;
$isFavorited = $user && in_array($user['role'], ['traveler', 'planner'], true)
    ? user_favorited_trip((int) $user['id'], $tripId)
    : false;
$myReview = $isTraveler ? get_user_review_for_trip((int) $user['id'], $tripId) : null;

$pageTitle = $trip['title'];
require __DIR__ . '/../partials/header.php';
?>
<section class="panel">
    <div class="grid two">
        <div>
            <?php if ($trip['cover_image']): ?>
                <img class="cover" src="<?= e($trip['cover_image']) ?>" alt="<?= e($trip['title']) ?>">
            <?php else: ?>
                <div class="placeholder-cover">Trip</div>
            <?php endif; ?>
        </div>
        <div>
            <p class="eyebrow"><?= $isPublished ? 'Published Trip' : 'Draft Preview' ?></p>
            <h1><?= e($trip['title']) ?></h1>
            <p class="muted"><?= e($trip['summary'] ?: '這個行程尚未填寫摘要。') ?></p>
            <p class="meta">評分 <?= e(format_rating($trip['average_rating'])) ?>，<?= (int) $trip['review_count'] ?> 則評論</p>
            <p class="meta">規劃師 <a class="text-link" href="/planner.php?id=<?= (int) $trip['author_id'] ?>"><?= e($trip['author_name'] ?: $trip['author_email']) ?></a></p>
            <div class="actions">
                <?php if (!$user): ?>
                    <a class="button primary" href="/login.php">登入後互動</a>
                <?php else: ?>
                    <?php if ($isTraveler && $isPublished): ?>
                        <form method="post" action="/actions/participation.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                            <input type="hidden" name="intent" value="<?= $isParticipating ? 'leave' : 'join' ?>">
                            <button class="<?= $isParticipating ? '' : 'primary' ?>" type="submit"><?= $isParticipating ? '取消參加' : '參加行程' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (in_array($user['role'], ['traveler', 'planner'], true) && $isPublished): ?>
                        <form method="post" action="/actions/favorite-trip.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                            <input type="hidden" name="intent" value="<?= $isFavorited ? 'remove' : 'add' ?>">
                            <button type="submit"><?= $isFavorited ? '取消收藏' : '收藏行程' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($isAuthor): ?>
                        <a class="button" href="/editor.php?id=<?= (int) $trip['id'] ?>">編輯行程</a>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        <span class="badge gray">管理員檢視</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Reviews</p><h2>行程評論</h2></div></div>
    <?php if ($isTraveler && $isParticipating): ?>
        <form method="post" action="/actions/review.php" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
            <input type="hidden" name="intent" value="<?= $myReview ? 'update' : 'create' ?>">
            <?php if ($myReview): ?>
                <input type="hidden" name="review_id" value="<?= (int) $myReview['id'] ?>">
            <?php endif; ?>
            <label>評分
                <select name="rating" required>
                    <?php for ($score = 5; $score >= 1; $score--): ?>
                        <option value="<?= $score ?>" <?= $myReview && (int) $myReview['rating'] === $score ? 'selected' : '' ?>><?= $score ?> 星</option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>評論
                <textarea name="comment" placeholder="分享你對這個行程的感受"><?= e($myReview['comment'] ?? '') ?></textarea>
            </label>
            <div class="actions"><button class="primary" type="submit"><?= $myReview ? '更新評論' : '送出評論' ?></button></div>
        </form>
        <?php if ($myReview): ?>
            <form method="post" action="/actions/review.php" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                <input type="hidden" name="review_id" value="<?= (int) $myReview['id'] ?>">
                <input type="hidden" name="intent" value="delete">
                <button class="danger small" type="submit">刪除我的評論</button>
            </form>
        <?php endif; ?>
    <?php elseif ($isTraveler): ?>
        <div class="empty-state">參加行程後才能留下評論。</div>
    <?php elseif (!$user): ?>
        <div class="empty-state">登入並參加行程後即可評論。</div>
    <?php endif; ?>

    <?php if (!$reviews): ?>
        <div class="empty-state">目前尚無評論。</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($reviews as $review): ?>
                <article class="card"><div class="card-body">
                    <p><strong><?= e($review['reviewer_name'] ?: '匿名使用者') ?></strong> <span class="badge gray"><?= (int) $review['rating'] ?> 分</span></p>
                    <p><?= e($review['comment'] ?: '沒有留下文字評論。') ?></p>
                    <p class="muted">更新於 <?= e(format_date($review['updated_at'])) ?></p>
                </div></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
