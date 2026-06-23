<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

verify_csrf();
$user = require_role(['traveler', 'planner']);

ensure_notification_preferences($user);

$popularDigestEnabled = isset($_POST['popular_digest_enabled']) ? true : false;
$plannerDigestEnabled = $user['role'] === 'planner' && isset($_POST['planner_digest_enabled']) ? true : false;
$winbackEnabled = isset($_POST['winback_enabled']) ? true : false;

$stmt = pdo()->prepare(
    'UPDATE notification_preferences
     SET popular_digest_enabled = ?, planner_digest_enabled = ?, winback_enabled = ?
     WHERE user_id = ?'
);
$stmt->execute([$popularDigestEnabled, $plannerDigestEnabled, $winbackEnabled, (int) $user['id']]);

flash('success', '郵件通知偏好已更新。');
redirect(dashboard_path($user['role']));
