<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';
require_once __DIR__ . '/../lib/spot-actions.php';
require_once __DIR__ . '/../lib/trip-gear.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/planner-dashboard.php');
}

verify_csrf();
$user = require_role('planner');

$tripId = input_int($_POST, 'trip_id');
$hasTripId = array_key_exists('trip_id', $_POST);
$title = trim((string) ($_POST['title'] ?? ''));
$summary = trim_or_null($_POST['summary'] ?? null);
$coverImage = trim_or_null($_POST['cover_image'] ?? null);
$latitude = input_float($_POST, 'latitude', -90, 90);
$longitude = input_float($_POST, 'longitude', -180, 180);
$address = trim_or_null($_POST['address'] ?? null);
$placeId = trim_or_null($_POST['place_id'] ?? null);
$intent = (string) ($_POST['intent'] ?? '');

if ($hasTripId && $tripId === null) {
    flash('error', '請提供有效的行程 ID。');
    redirect('/planner-dashboard.php');
}

if (!in_array($intent, ['draft', 'publish'], true)) {
    flash('error', '請選擇有效的儲存方式。');
    redirect($tripId ? '/editor.php?id=' . $tripId : '/editor.php');
}

$isPublished = $intent === 'publish' ? 1 : 0;

if ($title === '') {
    flash('error', '請填寫行程標題。');
    redirect($tripId ? '/editor.php?id=' . $tripId : '/editor.php');
}

if ($tripId !== null) {
    $trip = find_trip($tripId);
    if (!$trip || (int) $trip['author_id'] !== (int) $user['id']) {
        abort_page(404, '找不到行程', '這個行程不存在，或你沒有權限編輯。');
    }

    $stmt = pdo()->prepare('UPDATE trips SET title = ?, summary = ?, cover_image = ?, latitude = ?, longitude = ?, address = ?, place_id = ?, is_published = ? WHERE id = ? AND author_id = ?');
    $stmt->execute([$title, $summary, $coverImage, $latitude, $longitude, $address, $placeId, $isPublished, $tripId, $user['id']]);
    save_trip_spots($tripId, $_POST['spots'] ?? []);

    /* ── 儲存建議裝備 ── */
    save_trip_gear($tripId, (array) ($_POST['gear'] ?? []));

    flash('success', $isPublished ? '行程已發布。' : '草稿已儲存。');
    redirect('/editor.php?id=' . $tripId);
}

$stmt = pdo()->prepare('INSERT INTO trips (title, summary, cover_image, latitude, longitude, address, place_id, is_published, author_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$title, $summary, $coverImage, $latitude, $longitude, $address, $placeId, $isPublished, $user['id']]);
$newTripId = (int) pdo()->lastInsertId();
save_trip_spots($newTripId, $_POST['spots'] ?? []);
save_trip_gear($newTripId, (array) ($_POST['gear'] ?? []));

flash('success', $isPublished ? '行程已建立並發布。' : '行程草稿已建立。');
redirect('/editor.php?id=' . $newTripId);
