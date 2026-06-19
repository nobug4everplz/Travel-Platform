<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';

$user = require_role('planner');
$tripId = input_int($_GET, 'id');
$trip = null;

if ($tripId !== null) {
    $trip = find_trip($tripId);
    if (!$trip || (int) $trip['author_id'] !== (int) $user['id']) {
        abort_page(404, '找不到行程', '這個行程不存在，或你沒有權限編輯。');
    }
}

$pageTitle = $trip ? '編輯行程' : '新增行程';
require __DIR__ . '/../partials/header.php';
?>
<section class="panel narrow">
    <p class="eyebrow"><?= $trip ? 'Edit Trip' : 'New Trip' ?></p>
    <h1><?= $trip ? e($trip['title']) : '建立新的行程草稿' ?></h1>
    <p class="muted">草稿只會讓你自己和管理員看見；發布後會出現在首頁與規劃師頁面。</p>
    <?php if ($trip): ?>
        <div class="actions">
            <a class="button small" href="/planner-dashboard.php">回工作台</a>
            <a class="button small" href="/trip.php?id=<?= (int) $trip['id'] ?>">查看行程頁</a>
        </div>
    <?php endif; ?>
    <form method="post" action="/actions/trip-save.php" class="form-grid">
        <?= csrf_field() ?>
        <?php if ($trip): ?>
            <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
        <?php endif; ?>
        <label>行程標題
            <input type="text" name="title" value="<?= e($trip['title'] ?? '') ?>" required maxlength="255">
        </label>
        <label>封面圖片網址
            <input type="url" name="cover_image" value="<?= e($trip['cover_image'] ?? '') ?>" placeholder="https://...">
        </label>
        <label>行程摘要
            <textarea name="summary" placeholder="描述行程亮點、適合對象與體驗內容"><?= e($trip['summary'] ?? '') ?></textarea>
        </label>
        <div class="actions">
            <button type="submit" name="intent" value="draft">儲存草稿</button>
            <button class="primary" type="submit" name="intent" value="publish">發布行程</button>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
