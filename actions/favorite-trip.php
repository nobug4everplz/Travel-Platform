<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

verify_csrf();
$user = require_role(['traveler', 'planner']);

$tripId = input_int($_POST, 'trip_id');
if ($tripId === null) {
    flash('error', '缺少行程 ID。');
    redirect('/index.php');
}

$trip = find_trip($tripId);
if (!$trip || (int) $trip['is_published'] !== 1) {
    flash('error', '這個行程目前無法收藏。');
    redirect('/index.php');
}

$intent = (string) ($_POST['intent'] ?? 'toggle');

if (!in_array($intent, ['add', 'remove'], true)) {
    flash('error', '無效的收藏操作。');
    redirect('/trip.php?id=' . $tripId);
}

if ($intent === 'remove') {
    $stmt = pdo()->prepare('DELETE FROM favorite_trips WHERE user_id = ? AND trip_id = ?');
    $stmt->execute([$user['id'], $tripId]);
    flash('success', '已取消收藏行程。');
} else {
    $stmt = pdo()->prepare('INSERT INTO favorite_trips (user_id, trip_id) VALUES (?, ?) ON CONFLICT (user_id, trip_id) DO NOTHING');
    $stmt->execute([$user['id'], $tripId]);
    flash('success', '已收藏行程。');
}

redirect('/trip.php?id=' . $tripId);
