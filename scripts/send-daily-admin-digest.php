<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/cli.php';
require_once __DIR__ . '/../lib/mail.php';

require_cli();

$summary = pdo()->query(
    "SELECT
        DATE(DATE_SUB(CURDATE(), INTERVAL 1 DAY)) AS report_date,
        (SELECT COUNT(*)
         FROM users
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND created_at < CURDATE()) AS registrations,
        (SELECT COUNT(DISTINCT user_id)
         FROM login_events
         WHERE logged_in_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND logged_in_at < CURDATE()) AS active_users,
        (SELECT COUNT(*)
         FROM login_events
         WHERE is_new_device = true
           AND logged_in_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND logged_in_at < CURDATE()) AS new_devices,
         (SELECT COUNT(*)
          FROM trip_participations
          WHERE joined_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND joined_at < CURDATE()) AS participations,
        (SELECT COUNT(*)
         FROM reviews
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND created_at < CURDATE()) AS reviews,
        (SELECT COUNT(*)
         FROM email_delivery_logs
         WHERE status = 'sent'
           AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND created_at < CURDATE()) AS emails_sent,
        (SELECT COUNT(*)
         FROM email_delivery_logs
         WHERE status = 'failed'
           AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND created_at < CURDATE()) AS emails_failed"
)->fetch();

$admins = pdo()->query(
    "SELECT id, email, name
     FROM users
     WHERE role = 'admin'
     ORDER BY id ASC"
)->fetchAll();

$reportDate = (string) $summary['report_date'];
$subject = sprintf('Travel Platform 每日管理摘要 - %s', $reportDate);
$items = [
    '註冊人數' => (int) $summary['registrations'],
    '活躍使用者' => (int) $summary['active_users'],
    '新裝置登入' => (int) $summary['new_devices'],
    '參加行程' => (int) $summary['participations'],
    '新增評論' => (int) $summary['reviews'],
    '郵件寄送成功' => (int) $summary['emails_sent'],
    '郵件寄送失敗' => (int) $summary['emails_failed'],
];

$contentHtml = '<p>' . htmlspecialchars($reportDate, ENT_QUOTES, 'UTF-8') . ' 的平台活動摘要如下：</p>'
    . '<table style="width:100%;border-collapse:collapse;">';
$text = "Travel Platform 每日管理摘要 - {$reportDate}\n\n";
foreach ($items as $label => $value) {
    $contentHtml .= '<tr><td style="padding:8px 0;border-bottom:1px solid #dbe3ee;">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</td><td style="padding:8px 0;border-bottom:1px solid #dbe3ee;text-align:right;font-weight:700;">'
        . $value . '</td></tr>';
    $text .= sprintf("%s: %d\n", $label, $value);
}
$contentHtml .= '</table>';

$processed = 0;
$sent = 0;
$failed = 0;
foreach ($admins as $recipient) {
    $processed++;
    $referenceKey = sprintf('daily_admin_digest:%s:%d', $reportDate, (int) $recipient['id']);
    $html = email_shell(
        '每日管理摘要',
        $contentHtml,
        '前往管理工作台',
        app_url('/admin-dashboard.php')
    );

    if (send_platform_email($recipient, 'daily_admin_digest', $subject, $html, $text, $referenceKey)) {
        $sent++;
    } else {
        $failed++;
    }
}

cli_result($processed, $sent, $failed);
