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

$intent = (string) ($_POST['intent'] ?? 'create');
$tripId = input_int($_POST, 'trip_id');

if ($tripId === null) {
    flash('error', '缺少行程 ID。');
    redirect('/index.php');
}

if (!user_has_participation((int) $user['id'], $tripId)) {
    flash('error', '參加行程後才能評論。');
    redirect('/trip.php?id=' . $tripId);
}

if (!in_array($intent, ['create', 'update', 'delete'], true)) {
    flash('error', '無效的評論操作。');
    redirect('/trip.php?id=' . $tripId);
}

if ($intent === 'delete') {
    $reviewId = input_int($_POST, 'review_id');
    if ($reviewId === null) {
        flash('error', '缺少評論 ID。');
        redirect('/trip.php?id=' . $tripId);
    }

    $existingReview = get_user_review_for_trip((int) $user['id'], $tripId);
    if (!$existingReview || (int) $existingReview['id'] !== $reviewId) {
        flash('error', '找不到你的評論。');
        redirect('/trip.php?id=' . $tripId);
    }

    $stmt = pdo()->prepare('DELETE FROM reviews WHERE id = ? AND reviewer_id = ? AND trip_id = ?');
    $stmt->execute([$reviewId, $user['id'], $tripId]);
    recalculate_trip_rating($tripId);
    flash('success', '評論已刪除。');
    redirect('/trip.php?id=' . $tripId);
}

$rating = input_int($_POST, 'rating');
$comment = trim_or_null($_POST['comment'] ?? null);

if ($rating === null || $rating < 1 || $rating > 5) {
    flash('error', '評分必須介於 1 到 5 星。');
    redirect('/trip.php?id=' . $tripId);
}

if ($intent === 'update') {
    $reviewId = input_int($_POST, 'review_id');
    if ($reviewId === null) {
        flash('error', '缺少評論 ID。');
        redirect('/trip.php?id=' . $tripId);
    }

    $existingReview = get_user_review_for_trip((int) $user['id'], $tripId);
    if (!$existingReview || (int) $existingReview['id'] !== $reviewId) {
        flash('error', '找不到你的評論。');
        redirect('/trip.php?id=' . $tripId);
    }

    $stmt = pdo()->prepare('UPDATE reviews SET rating = ?, comment = ? WHERE id = ? AND reviewer_id = ? AND trip_id = ?');
    $stmt->execute([$rating, $comment, $reviewId, $user['id'], $tripId]);
    recalculate_trip_rating($tripId);
    flash('success', '評論已更新。');
    redirect('/trip.php?id=' . $tripId);
}

try {
    $stmt = pdo()->prepare('INSERT INTO reviews (reviewer_id, trip_id, rating, comment) VALUES (?, ?, ?, ?)');
    $stmt->execute([$user['id'], $tripId, $rating, $comment]);
    recalculate_trip_rating($tripId);
    flash('success', '評論已送出。');
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        flash('error', '每位旅人每個行程只能留下一則評論。');
    } else {
        throw $exception;
    }
}

redirect('/trip.php?id=' . $tripId);
