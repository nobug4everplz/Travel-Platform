<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/cli.php';

require_cli();

$today = new DateTimeImmutable('today');
$anchorValue = app_env('PLANNER_DIGEST_ANCHOR_DATE', '2026-05-27');
$anchor = DateTimeImmutable::createFromFormat('!Y-m-d', $anchorValue);
$anchorErrors = DateTimeImmutable::getLastErrors();

if (!$anchor || ($anchorErrors !== false && ($anchorErrors['warning_count'] > 0 || $anchorErrors['error_count'] > 0))) {
    fwrite(STDERR, "PLANNER_DIGEST_ANCHOR_DATE must use YYYY-MM-DD format.\n");
    cli_result(0, 0, 1);
}

$daysSinceAnchor = (int) $anchor->diff($today)->format('%r%a');
if ($daysSinceAnchor < 0 || $daysSinceAnchor % 3 !== 0) {
    cli_result(0, 0, 0);
}

$recipients = pdo()->query(
    "SELECT u.id, u.email, u.name
     FROM users u JOIN notification_preferences np ON np.user_id = u.id
     WHERE u.role = 'planner' AND np.planner_digest_enabled = true ORDER BY u.id"
)->fetchAll();

$processed = 0;
$sent = 0;
$failed = 0;
$periodEnd = $today->modify('-1 day')->format('Y-m-d');
$periodStart = $today->modify('-3 days')->format('Y-m-d');
foreach ($recipients as $recipient) {
    $referenceKey = sprintf('planner_three_day_digest:%s:%d', $periodEnd, (int) $recipient['id']);
    if (mail_was_sent($referenceKey)) {
        continue;
    }

    $statsStmt = pdo()->prepare(
        "SELECT
            (SELECT COUNT(*) FROM trip_daily_unique_views v JOIN trips t ON t.id = v.trip_id
             WHERE t.author_id = ? AND t.is_published = true AND v.view_date BETWEEN ? AND ?) AS views,
            (SELECT COUNT(*) FROM favorite_trips f JOIN trips t ON t.id = f.trip_id
             WHERE t.author_id = ? AND t.is_published = true AND DATE(f.created_at) BETWEEN ? AND ?) AS favorites,
            (SELECT COUNT(*) FROM trip_participations p JOIN trips t ON t.id = p.trip_id
             WHERE t.author_id = ? AND t.is_published = true AND DATE(p.joined_at) BETWEEN ? AND ?) AS participations,
            (SELECT COUNT(*) FROM reviews r JOIN trips t ON t.id = r.trip_id
             WHERE t.author_id = ? AND t.is_published = true AND DATE(r.created_at) BETWEEN ? AND ?) AS reviews"
    );
    $statsStmt->execute([(int) $recipient['id'], $periodStart, $periodEnd, (int) $recipient['id'], $periodStart, $periodEnd, (int) $recipient['id'], $periodStart, $periodEnd, (int) $recipient['id'], $periodStart, $periodEnd]);
    $stats = $statsStmt->fetch();
    $topStmt = pdo()->prepare(
        'SELECT t.title, COUNT(v.id) AS view_count FROM trips t LEFT JOIN trip_daily_unique_views v ON v.trip_id = t.id AND v.view_date BETWEEN ? AND ?
         WHERE t.author_id = ? AND t.is_published = true GROUP BY t.id, t.title ORDER BY view_count DESC, t.title ASC LIMIT 1'
    );
    $topStmt->execute([$periodStart, $periodEnd, (int) $recipient['id']]);
    $topTrip = $topStmt->fetch();
    $reviewStmt = pdo()->prepare(
        'SELECT t.title, r.comment FROM reviews r JOIN trips t ON t.id = r.trip_id
         WHERE t.author_id = ? AND t.is_published = true AND DATE(r.created_at) BETWEEN ? AND ? ORDER BY r.created_at DESC LIMIT 2'
    );
    $reviewStmt->execute([(int) $recipient['id'], $periodStart, $periodEnd]);
    $reviews = $reviewStmt->fetchAll();
    $contentHtml = '<p>以下是 ' . htmlspecialchars($periodStart . ' 至 ' . $periodEnd, ENT_QUOTES, 'UTF-8') . ' 的行程表現：</p><ul>'
        . '<li><strong>' . (int) $stats['views'] . '</strong> 位不重複瀏覽</li>'
        . '<li><strong>' . (int) $stats['favorites'] . '</strong> 次新增收藏</li>'
        . '<li><strong>' . (int) $stats['participations'] . '</strong> 次新增參加</li>'
        . '<li><strong>' . (int) $stats['reviews'] . '</strong> 則新評論待查看</li></ul>'
        . '<p>最多人查看的行程：<strong>' . htmlspecialchars((string) ($topTrip['title'] ?? '尚無資料'), ENT_QUOTES, 'UTF-8') . '</strong></p>';
    foreach ($reviews as $review) {
        $contentHtml .= '<p style="border-top:1px solid #dbe3ee;padding-top:8px;"><strong>' . htmlspecialchars((string) $review['title'], ENT_QUOTES, 'UTF-8') . '</strong><br>' . htmlspecialchars((string) ($review['comment'] ?: '沒有文字評論。'), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $text = "規劃師三日營運摘要（{$periodStart} 至 {$periodEnd}）\n"
        . sprintf("不重複瀏覽: %d\n新增收藏: %d\n新增參加: %d\n新評論待查看: %d\n", (int) $stats['views'], (int) $stats['favorites'], (int) $stats['participations'], (int) $stats['reviews'])
        . '前往工作台: ' . app_url('/planner-dashboard.php');
    $html = email_shell('你的三日行程成效摘要', $contentHtml, '查看規劃師工作台', app_url('/planner-dashboard.php'));

    $processed++;
    if (send_platform_email($recipient, 'planner_three_day_digest', '你的三日行程成效摘要', $html, $text, $referenceKey)) {
        $sent++;
    } else {
        $failed++;
    }
}

cli_result($processed, $sent, $failed);
