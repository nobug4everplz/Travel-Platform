<?php

declare(strict_types=1);

/**
 * actions/export-pdf.php — PDF itinerary download endpoint.
 *
 * Expects: GET ?id=<trip_id>
 * Validates trip exists + visibility (public or logged-in author/admin).
 * Returns the generated PDF as a Content-Disposition download.
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';
require_once __DIR__ . '/../lib/weather.php';
require_once __DIR__ . '/../lib/spot-actions.php';
require_once __DIR__ . '/../lib/trip-photos.php';
require_once __DIR__ . '/../lib/pdf-export.php';

// ── Validate trip id ──
$tripId = input_int($_GET, 'id');
if ($tripId === null) {
    http_response_code(400);
    echo '缺少行程 ID。';
    exit;
}

// ── Load trip + check visibility ──
$user = current_user();
$trip = find_visible_trip($tripId, $user);
if (!$trip) {
    http_response_code(404);
    echo '找不到行程，或你沒有權限查看。';
    exit;
}

// ── Load spots ──
$spots = get_trip_spots($tripId);

// ── Weather ──
$weatherNow = null;
$weatherForecast = null;
if (!empty($trip['address'])) {
    $city = extract_city_from_address($trip['address']);
    if ($city !== '') {
        $weatherAll = get_weather_all($city);
        if ($weatherAll !== null) {
            $weatherNow = $weatherAll['current'];
            $weatherForecast = $weatherAll['forecast'];
        }
    }
}

// ── Generate PDF ──
$photos = [];
$spotPhotos = [];
if (function_exists('get_trip_photos') && function_exists('get_spot_photos_grouped')) {
    $photos = get_trip_photos($tripId);
    $spotPhotos = get_spot_photos_grouped($tripId);
}
$pdfContent = generate_trip_pdf($trip, $spots, $weatherNow, $weatherForecast, $photos, $spotPhotos);

$filename = rawurlencode($trip['title'] ?? 'itinerary') . '_行程手冊.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode(($trip['title'] ?? 'itinerary') . '_行程手冊.pdf'));
header('Content-Length: ' . strlen($pdfContent));
header('Cache-Control: no-cache, no-store, must-revalidate');

echo $pdfContent;
