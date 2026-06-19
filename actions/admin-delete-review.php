<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/reviews.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin-dashboard.php');
}

verify_csrf();
require_role('admin');

$reviewId = input_int($_POST, 'review_id');
if ($reviewId === null) {
    flash('error', '缺少評論 ID。');
    redirect('/admin-dashboard.php');
}

$stmt = pdo()->prepare('SELECT trip_id FROM reviews WHERE id = ? LIMIT 1');
$stmt->execute([$reviewId]);
$review = $stmt->fetch();

if (!$review) {
    flash('error', '找不到這則評論。');
    redirect('/admin-dashboard.php');
}

pdo()->beginTransaction();
try {
    $delete = pdo()->prepare('DELETE FROM reviews WHERE id = ?');
    $delete->execute([$reviewId]);
    recalculate_trip_rating((int) $review['trip_id']);
    pdo()->commit();
} catch (Throwable $throwable) {
    pdo()->rollBack();
    throw $throwable;
}

flash('success', '評論已刪除，行程評分已重新計算。');
redirect('/admin-dashboard.php');
