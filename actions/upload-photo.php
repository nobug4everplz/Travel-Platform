<?php

declare(strict_types=1);

/**
 * actions/upload-photo.php — Trip photo upload endpoint.
 *
 * Expects: POST
 *   trip_id   (int, required)
 *   spot_id   (int, optional)
 *   caption   (string, optional)
 *   photo     (file, required — max 5 MB, jpg/png/webp)
 *
 * Saves to uploads/photos/ with a unique filename.
 * Redirects back to the trip page on success or failure.
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trip-photos.php';

// ── Auth check ──
$user = require_login();
verify_csrf();

// ── Validate POST params ──
$tripId = input_int($_POST, 'trip_id');
if ($tripId === null) {
    flash('error', '缺少行程 ID。');
    redirect('/traveler-dashboard.php');
}

// Verify trip exists and is published (travelers can only upload to published trips)
require_once __DIR__ . '/../lib/trips.php';
$trip = find_visible_trip($tripId, $user);
if (!$trip || (int) $trip['is_published'] !== 1) {
    flash('error', '無法上傳照片：行程不存在或尚未發布。');
    redirect('/trip.php?id=' . $tripId);
}

// Only travelers can upload
if ($user['role'] !== 'traveler') {
    flash('error', '只有旅客可以上傳照片。');
    redirect('/trip.php?id=' . $tripId);
}

// Only trip participants can upload
require_once __DIR__ . '/../lib/trips.php';
if (!user_has_participation((int) $user['id'], $tripId)) {
    flash('error', '你沒有參加這個行程，無法上傳照片。');
    redirect('/trip.php?id=' . $tripId);
}

$spotId = input_int($_POST, 'spot_id');
$caption = trim_or_null($_POST['caption'] ?? null);

// ── Validate file ──
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['photo']['error'] ?? -1;
    $msg = match ($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '檔案超過大小限制（最大 5 MB）。',
        UPLOAD_ERR_NO_FILE => '請選擇一張照片上傳。',
        default => '檔案上傳失敗，請再試一次。',
    };
    flash('error', $msg);
    redirect('/trip.php?id=' . $tripId);
}

$file = $_FILES['photo'];
$fileSize = (int) $file['size'];
$maxSize = 5 * 1024 * 1024; // 5 MB

if ($fileSize > $maxSize) {
    flash('error', '照片檔案過大，請選擇 5 MB 以下的圖片。');
    redirect('/trip.php?id=' . $tripId);
}

// Validate MIME type
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes, true)) {
    flash('error', '僅接受 JPG、PNG 或 WebP 格式的圖片。');
    redirect('/trip.php?id=' . $tripId);
}

// Validate extension
$ext = match ($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => null,
};

if ($ext === null) {
    flash('error', '不支援的圖片格式。');
    redirect('/trip.php?id=' . $tripId);
}

// ── Save file ──
$uploadDir = __DIR__ . '/../uploads/photos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    flash('error', '照片儲存失敗，請再試一次。');
    redirect('/trip.php?id=' . $tripId);
}

// Relative path for DB storage
$imagePath = '/uploads/photos/' . $filename;

// ── Insert DB record ──
insert_trip_photo($tripId, $spotId, (int) $user['id'], $imagePath, $caption);

flash('success', '照片上傳成功！');
redirect('/trip.php?id=' . $tripId);
