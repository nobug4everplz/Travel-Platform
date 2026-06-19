<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

verify_csrf();
$user = require_role('traveler');

$plannerId = input_int($_POST, 'planner_id');
if ($plannerId === null) {
    flash('error', '缺少規劃師 ID。');
    redirect('/index.php');
}

$planner = get_planner($plannerId);
if (!$planner) {
    flash('error', '找不到這位規劃師。');
    redirect('/index.php');
}

$intent = (string) ($_POST['intent'] ?? 'toggle');

if (!in_array($intent, ['add', 'remove'], true)) {
    flash('error', '無效的收藏操作。');
    redirect('/planner.php?id=' . $plannerId);
}

if ($intent === 'remove') {
    $stmt = pdo()->prepare('DELETE FROM favorite_planners WHERE traveler_id = ? AND planner_id = ?');
    $stmt->execute([$user['id'], $plannerId]);
    flash('success', '已取消收藏規劃師。');
} else {
    $stmt = pdo()->prepare('INSERT IGNORE INTO favorite_planners (traveler_id, planner_id) VALUES (?, ?)');
    $stmt->execute([$user['id'], $plannerId]);
    flash('success', '已收藏規劃師。');
}

redirect('/planner.php?id=' . $plannerId);
