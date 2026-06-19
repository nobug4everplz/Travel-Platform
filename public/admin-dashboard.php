<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/analytics.php';

$admin = require_role('admin');
$analytics = admin_analytics();
$metrics = $analytics['metrics'];
$trend = $analytics['trend'];
$roles = $analytics['roles'];
$devices = $analytics['devices'];
$peakActiveUsers = 1;
foreach ($trend as $activityDay) {
    $peakActiveUsers = max($peakActiveUsers, (int) $activityDay['active_users']);
}

$users = pdo()->query(
    'SELECT u.id, u.email, u.name, u.role, u.created_at,
            COUNT(DISTINCT t.id) AS trip_count,
            COUNT(DISTINCT p.id) AS participation_count,
            COUNT(DISTINCT r.id) AS review_count
     FROM users u
     LEFT JOIN trips t ON t.author_id = u.id
     LEFT JOIN trip_participations p ON p.user_id = u.id
     LEFT JOIN reviews r ON r.reviewer_id = u.id
     GROUP BY u.id, u.email, u.name, u.role, u.created_at
     ORDER BY u.created_at DESC'
)->fetchAll();

$reviews = pdo()->query(
    'SELECT r.*, u.email AS reviewer_email, u.name AS reviewer_name, t.title AS trip_title
     FROM reviews r
     JOIN users u ON u.id = r.reviewer_id
     JOIN trips t ON t.id = r.trip_id
     ORDER BY r.created_at DESC
     LIMIT 50'
)->fetchAll();

$pageTitle = '管理員工作台';
require __DIR__ . '/../partials/header.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Admin Dashboard</p>
        <h1><?= e($admin['name'] ?: $admin['email']) ?></h1>
        <p class="muted">管理使用者角色、檢視近期評論，並在刪除評論後維持行程評分一致。</p>
    </div>
</section>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Activity Snapshot</p><h2>平台概況</h2></div></div>
    <div class="stats five">
        <div class="stat"><strong><?= (int) $metrics['user_count'] ?></strong><span>總使用者</span></div>
        <div class="stat"><strong><?= (int) $metrics['registered_today'] ?></strong><span>今日註冊</span></div>
        <div class="stat"><strong><?= (int) $metrics['dau'] ?></strong><span>今日活躍</span></div>
        <div class="stat"><strong><?= (int) $metrics['wau'] ?></strong><span>7 日活躍</span></div>
        <div class="stat"><strong><?= (int) $metrics['mau'] ?></strong><span>30 日活躍</span></div>
        <div class="stat"><strong><?= (int) $metrics['emails_sent_today'] ?></strong><span>今日郵件寄送</span></div>
        <div class="stat"><strong><?= (int) $metrics['emails_failed_today'] ?></strong><span>今日郵件失敗</span></div>
    </div>
</section>
<div class="insight-grid">
    <section class="panel">
        <div class="section-heading"><div><p class="eyebrow">7-Day Trend</p><h2>每日活躍使用者</h2></div></div>
        <div class="trend-chart" aria-label="過去七日每日活躍使用者">
            <?php foreach ($trend as $activityDay): ?>
                <?php $barHeight = (int) round(((int) $activityDay['active_users'] / $peakActiveUsers) * 100); ?>
                <div class="trend-column">
                    <span class="trend-value"><?= (int) $activityDay['active_users'] ?></span>
                    <span class="trend-bar" style="height: <?= $barHeight ?>%" aria-hidden="true"></span>
                    <span class="trend-label"><?= e(date('m/d', strtotime((string) $activityDay['day']))) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="panel">
        <div class="section-heading"><div><p class="eyebrow">30-Day Activity</p><h2>角色活躍分布</h2></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>角色</th><th>活躍使用者</th></tr></thead>
                <tbody>
                    <?php foreach ($roles as $roleActivity): ?>
                        <tr>
                            <td><span class="badge gray"><?= e($roleActivity['role']) ?></span></td>
                            <td><?= (int) $roleActivity['active_users'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Security</p><h2>近期新裝置登入</h2></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>時間</th><th>使用者</th><th>角色</th><th>IP 位址</th></tr></thead>
            <tbody>
                <?php foreach ($devices as $device): ?>
                    <tr>
                        <td><?= e((string) $device['logged_in_at']) ?></td>
                        <td><strong><?= e($device['name'] ?: $device['email']) ?></strong><br><span class="muted"><?= e($device['email']) ?></span></td>
                        <td><span class="badge gray"><?= e($device['role']) ?></span></td>
                        <td class="muted"><?= e($device['ip_address'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$devices): ?>
                    <tr><td colspan="4" class="muted">目前沒有新裝置登入事件。</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Users</p><h2>使用者清單</h2></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>使用者</th><th>角色</th><th>統計</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td><strong><?= e($row['name'] ?: $row['email']) ?></strong><br><span class="muted"><?= e($row['email']) ?></span></td>
                        <td><span class="badge gray"><?= e($row['role']) ?></span></td>
                        <td class="muted">行程 <?= (int) $row['trip_count'] ?> / 參加 <?= (int) $row['participation_count'] ?> / 評論 <?= (int) $row['review_count'] ?></td>
                        <td>
                            <form method="post" action="/actions/admin-user-role.php" class="actions">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                <select name="role">
                                    <?php foreach (['traveler' => '旅人', 'planner' => '規劃師', 'admin' => '管理員'] as $role => $label): ?>
                                        <option value="<?= e($role) ?>" <?= $row['role'] === $role ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="small" type="submit">更新</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Reviews</p><h2>近期評論</h2></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>行程</th><th>評論者</th><th>內容</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($reviews as $review): ?>
                    <tr>
                        <td><a class="text-link" href="/trip.php?id=<?= (int) $review['trip_id'] ?>"><?= e($review['trip_title']) ?></a></td>
                        <td><?= e($review['reviewer_name'] ?: $review['reviewer_email']) ?></td>
                        <td><span class="badge gray"><?= (int) $review['rating'] ?> 分</span> <?= e($review['comment'] ?: '沒有文字評論。') ?></td>
                        <td>
                            <form method="post" action="/actions/admin-delete-review.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                                <button class="danger small" type="submit">刪除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$reviews): ?>
                    <tr><td colspan="4" class="muted">目前沒有評論。</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../partials/account-security.php'; ?>
<?php require __DIR__ . '/../partials/footer.php'; ?>
