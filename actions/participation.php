<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';
require_once __DIR__ . '/../lib/reviews.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

verify_csrf();
$user = require_role('traveler');

$tripId = input_int($_POST, 'trip_id');
if ($tripId === null) {
    flash('error', '缺少行程 ID。');
    redirect('/index.php');
}

$trip = find_trip($tripId);
if (!$trip || (int) $trip['is_published'] !== 1) {
    flash('error', '這個行程目前無法參加。');
    redirect('/trip.php?id=' . $tripId);
}

$intent = (string) ($_POST['intent'] ?? 'join');

if (!in_array($intent, ['join', 'leave'], true)) {
    flash('error', '無效的參加操作。');
    redirect('/trip.php?id=' . $tripId);
}

if ($intent === 'leave') {
    if (!user_has_participation((int) $user['id'], $tripId)) {
        flash('error', '你尚未參加此行程。');
        redirect('/trip.php?id=' . $tripId);
    }

    pdo()->beginTransaction();
    try {
        $reviewDelete = pdo()->prepare('DELETE FROM reviews WHERE reviewer_id = ? AND trip_id = ?');
        $reviewDelete->execute([$user['id'], $tripId]);

        $participationDelete = pdo()->prepare('DELETE FROM trip_participations WHERE user_id = ? AND trip_id = ?');
        $participationDelete->execute([$user['id'], $tripId]);

        recalculate_trip_rating($tripId);
        pdo()->commit();
    } catch (Throwable $throwable) {
        pdo()->rollBack();
        throw $throwable;
    }

    flash('success', '已取消參加，相關評論也已移除。');
    redirect('/trip.php?id=' . $tripId);
}

if (user_has_participation((int) $user['id'], $tripId)) {
    flash('error', '你已經參加這個行程。');
    redirect('/trip.php?id=' . $tripId);
}

$stmt = pdo()->prepare('INSERT INTO trip_participations (user_id, trip_id, status) VALUES (?, ?, ?)');
$stmt->execute([$user['id'], $tripId, 'active']);

flash('success', '已參加行程。');
redirect('/trip.php?id=' . $tripId);
