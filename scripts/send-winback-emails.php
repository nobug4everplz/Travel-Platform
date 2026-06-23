<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/cli.php';
require_once __DIR__ . '/../lib/analytics.php';

require_cli();

$today = date('Y-m-d');
$stmt = pdo()->prepare(
    "SELECT u.id, u.email, u.name, u.role, activity.last_login_at,
            DATEDIFF(?, DATE(activity.last_login_at)) AS inactivity_days
     FROM users u
     JOIN notification_preferences np ON np.user_id = u.id
     JOIN (
        SELECT user_id, MAX(logged_in_at) AS last_login_at
        FROM login_events
        GROUP BY user_id
     ) activity ON activity.user_id = u.id
     WHERE u.role IN ('traveler', 'planner')
       AND np.winback_enabled = true
       AND DATEDIFF(?, DATE(activity.last_login_at)) IN (5, 14)
     ORDER BY u.id"
);
$stmt->execute([$today, $today]);
$recipients = $stmt->fetchAll();

$processed = 0;
$sent = 0;
$failed = 0;
foreach ($recipients as $recipient) {
    $days = (int) $recipient['inactivity_days'];
    $inactiveSinceDate = date('Y-m-d', strtotime((string) $recipient['last_login_at']));
    $referenceKey = sprintf('winback_day_%d:%s:%d', $days, $inactiveSinceDate, (int) $recipient['id']);
    if (mail_was_sent($referenceKey)) {
        continue;
    }

    if ($recipient['role'] === 'planner') {
        $statsStmt = pdo()->prepare(
            'SELECT COUNT(DISTINCT v.id) AS views, COUNT(DISTINCT r.id) AS reviews
             FROM trips t LEFT JOIN trip_daily_unique_views v ON v.trip_id = t.id
             LEFT JOIN reviews r ON r.trip_id = t.id
             WHERE t.author_id = ? AND t.is_published = 1'
        );
        $statsStmt->execute([(int) $recipient['id']]);
        $plannerStats = $statsStmt->fetch();
        $subject = $days === 5 ? '你的行程頁最近還有人在瀏覽' : '回來更新行程，讓旅人重新找到你';
        $heading = $days === 5 ? '旅人仍在尋找你的行程' : '讓你的行程重新被看見';
        $contentHtml = $days === 5
            ? '<p>你已有 <strong>' . (int) $plannerStats['views'] . '</strong> 筆行程瀏覽與 <strong>' . (int) $plannerStats['reviews'] . '</strong> 則評論紀錄，回來看看旅人的回饋。</p>'
            : '<p>兩週未更新行程了。回到工作台整理已發布內容，讓新的旅人更容易找到你。</p>';
        $text = ($days === 5
                ? "It has been five days since your last visit. Share a fresh itinerary or polish a draft for travelers looking for their next destination.\n\n"
                : "It has been two weeks since your last visit. Return to publish an itinerary and reconnect with travelers.\n\n")
            . 'Open planner dashboard: ' . app_url('/planner-dashboard.php');
        $actionLabel = 'Open planner dashboard';
        $actionUrl = app_url('/planner-dashboard.php');
    } else {
        $popularTrips = popular_public_trips(3);
        $tripList = '<ol>';
        foreach ($popularTrips as $trip) {
            $tripList .= '<li>' . htmlspecialchars((string) $trip['title'], ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $tripList .= '</ol>';
        $subject = $days === 5 ? '最近有新的旅程靈感等你回來看看' : '下一段旅行，也許已經在等你決定';
        $heading = $days === 5 ? '熱門行程等你探索' : '慢慢挑選下一段旅行';
        $contentHtml = $days === 5
            ? '<p>最近熱門的公開行程：</p>' . $tripList
            : '<p>這裡有一些近期受到收藏的行程，回來時可以再決定是否加入或收藏。</p>' . $tripList;
        $text = ($days === 5
                ? "It has been five days since your last visit. Browse popular itineraries and save a trip for your next adventure.\n\n"
                : "It has been two weeks since your last visit. New destinations and planners are waiting for you.\n\n")
            . 'Explore trips: ' . app_url('/index.php');
        $actionLabel = 'Explore trips';
        $actionUrl = app_url('/index.php');
    }

    $html = email_shell($heading, $contentHtml, $actionLabel, $actionUrl);

    $processed++;
    if (send_platform_email($recipient, 'winback_day_' . $days, $subject, $html, $text, $referenceKey)) {
        $sent++;
    } else {
        $failed++;
    }
}

cli_result($processed, $sent, $failed);
