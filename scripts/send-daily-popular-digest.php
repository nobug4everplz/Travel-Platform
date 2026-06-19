<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/cli.php';
require_once __DIR__ . '/../lib/analytics.php';

require_cli();

function digest_escape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function digest_trip_rating(array $trip): string
{
    return $trip['average_rating'] === null
        ? 'New'
        : number_format((float) $trip['average_rating'], 1) . ' / 5';
}

$today = date('Y-m-d');
$trips = popular_public_trips(5);
$planners = popular_planners(3);

$tripItems = '';
foreach ($trips as $trip) {
    $tripItems .= '<li><a href="' . digest_escape(app_url('/trip.php?id=' . (int) $trip['id'])) . '">'
        . digest_escape((string) $trip['title']) . '</a> - '
        . digest_escape(digest_trip_rating($trip)) . ', '
        . (int) $trip['favorite_count'] . ' saves</li>';
}
if ($tripItems === '') {
    $tripItems = '<li>New public trips are coming soon.</li>';
}

$plannerItems = '';
foreach ($planners as $planner) {
    $plannerItems .= '<li><a href="' . digest_escape(app_url('/planner.php?id=' . (int) $planner['id'])) . '">'
        . digest_escape((string) ($planner['name'] ?: $planner['email'])) . '</a> - '
        . (int) $planner['trip_count'] . ' public trips, '
        . (int) $planner['follower_count'] . ' followers</li>';
}
if ($plannerItems === '') {
    $plannerItems = '<li>Featured planners are coming soon.</li>';
}

$contentHtml = '<p>今天最受旅人關注的行程與規劃師都整理好了。</p>'
    . '<h2 style="font-size:18px;">熱門行程 Top 5</h2><ol>' . $tripItems . '</ol>'
    . '<h2 style="font-size:18px;">熱門規劃師 Top 3</h2><ol>' . $plannerItems . '</ol>';

$textLines = ["Today's popular travel picks", '', 'Top 5 trips'];
foreach ($trips as $index => $trip) {
    $textLines[] = sprintf(
        '%d. %s - %s, %d saves - %s',
        $index + 1,
        (string) $trip['title'],
        digest_trip_rating($trip),
        (int) $trip['favorite_count'],
        app_url('/trip.php?id=' . (int) $trip['id'])
    );
}
if (!$trips) {
    $textLines[] = 'New public trips are coming soon.';
}
$textLines[] = '';
$textLines[] = 'Top 3 planners';
foreach ($planners as $index => $planner) {
    $textLines[] = sprintf(
        '%d. %s - %d public trips, %d followers - %s',
        $index + 1,
        (string) ($planner['name'] ?: $planner['email']),
        (int) $planner['trip_count'],
        (int) $planner['follower_count'],
        app_url('/planner.php?id=' . (int) $planner['id'])
    );
}
if (!$planners) {
    $textLines[] = 'Featured planners are coming soon.';
}
$textLines[] = '';
$textLines[] = 'Explore: ' . app_url('/index.php');
$text = implode("\n", $textLines);
$html = email_shell('今天的熱門旅行靈感', $contentHtml, '探索熱門行程', app_url('/index.php'));

$recipients = pdo()->query(
    "SELECT u.id, u.email, u.name, u.role
     FROM users u
     JOIN notification_preferences np ON np.user_id = u.id
     WHERE u.role IN ('traveler', 'planner') AND np.popular_digest_enabled = 1
     ORDER BY u.id"
)->fetchAll();

$processed = 0;
$sent = 0;
$failed = 0;
foreach ($recipients as $recipient) {
    $referenceKey = sprintf('daily_popular_digest:%s:%d', $today, (int) $recipient['id']);
    if (mail_was_sent($referenceKey)) {
        continue;
    }

    $processed++;
    if (send_platform_email($recipient, 'daily_popular_digest', '今天的熱門旅行靈感', $html, $text, $referenceKey)) {
        $sent++;
    } else {
        $failed++;
    }
}

cli_result($processed, $sent, $failed);
