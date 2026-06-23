<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';
require_once __DIR__ . '/../lib/footprint-actions.php';

$travelerId = input_int($_GET, 'id');
if ($travelerId === null) {
    abort_page(400, '缺少旅人', '請提供有效的旅人 ID。');
}

$stmt = pdo()->prepare('SELECT id, name, avatar_url, created_at FROM users WHERE id = ? AND role = \'traveler\' LIMIT 1');
$stmt->execute([$travelerId]);
$traveler = $stmt->fetch();

if (!$traveler) {
    abort_page(404, '找不到旅人', '這個旅人不存在。');
}

// 參加過的行程數（只算已發佈行程）
$tripCountStmt = pdo()->prepare(
    'SELECT COUNT(*) AS cnt
     FROM trip_participations p
     JOIN trips t ON t.id = p.trip_id AND t.is_published = 1
     WHERE p.user_id = ?'
);
$tripCountStmt->execute([$travelerId]);
$tripCount = (int) $tripCountStmt->fetch()['cnt'];

// 足跡地圖
$footprints = get_traveler_footprints($travelerId);
$hasFootprints = count($footprints) > 0;
$loadMap = $hasFootprints;

$user = current_user();
$pageTitle = ($traveler['name'] ?: '旅人') . '的檔案';
$pageType = 'traveler_profile';
require __DIR__ . '/../partials/header.php';
?>
<section class="page-heading" style="display:flex;align-items:center;gap:1.5rem;">
    <?php if ($traveler['avatar_url']): ?>
        <img src="<?= e($traveler['avatar_url']) ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
    <?php else: ?>
        <span style="width:80px;height:80px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;color:#6b7280;"><?= e(display_initial($traveler['name'])) ?></span>
    <?php endif; ?>
    <div>
        <p class="eyebrow">Traveler Profile</p>
        <h1><?= e($traveler['name'] ?: '匿名旅人') ?></h1>
        <p class="muted">加入於 <?= e(format_date($traveler['created_at'])) ?></p>
        <p class="meta">已參加 <strong><?= $tripCount ?></strong> 個行程</p>
    </div>
</section>

<?php if ($hasFootprints): ?>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Footprints</p><h2>旅人足跡</h2></div></div>
    <div id="footprint-map" style="height: 400px; border-radius: 12px;"></div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var map = initMap('footprint-map');
        var markers = [];
        var footprints = <?= json_encode(array_map(fn($f) => [
            'name' => $f['name'],
            'latitude' => $f['latitude'],
            'longitude' => $f['longitude'],
            'notes' => $f['notes'],
        ], $footprints), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        footprints.forEach(function(fp) {
            if (fp.latitude && fp.longitude) {
                var m = L.marker([parseFloat(fp.latitude), parseFloat(fp.longitude)])
                    .addTo(map)
                    .bindPopup('<strong>' + fp.name + '</strong>' + (fp.notes ? '<br>' + fp.notes : ''));
                markers.push(m);
            }
        });
        if (markers.length > 1) {
            var group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        } else if (markers.length === 1) {
            map.setView(markers[0].getLatLng(), 10);
        }
    });
    </script>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
