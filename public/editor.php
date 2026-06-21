<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';

$user = require_role('planner');
$tripId = input_int($_GET, 'id');
$trip = null;

if ($tripId !== null) {
    $trip = find_trip($tripId);
    if (!$trip || (int) $trip['author_id'] !== (int) $user['id']) {
        abort_page(404, '找不到行程', '這個行程不存在，或你沒有權限編輯。');
    }
}

// ───── AI fill support ─────
if ($_GET['ai_fill'] ?? null) {
    $aiFill = $_SESSION['ai_fill_data'] ?? null;
    if ($aiFill && isset($aiFill['content'])) {
        $content = $aiFill['content'];
        if (!empty($content['title'])) {
            $trip['title'] = $content['title'];
        }
        if (!empty($content['summary'])) {
            $trip['summary'] = $content['summary'];
        }
        unset($_SESSION['ai_fill_data']);
    }
}

require_once __DIR__ . '/../lib/spot-actions.php';
$spots = $tripId ? get_trip_spots($tripId) : [];
$pageTitle = $trip ? '編輯行程' : '新增行程';
$pageType = 'editor';
if ($trip) {
    $bodyDataAttrs = 'data-trip-id="' . (int) $trip['id'] . '" data-trip-title="' . e($trip['title']) . '"';
}
$loadMap = true;
$loadSortable = true;
require __DIR__ . '/../partials/header.php';
?>
<section class="panel narrow">
    <p class="eyebrow"><?= $trip ? 'Edit Trip' : 'New Trip' ?></p>
    <h1><?= $trip ? e($trip['title']) : '建立新的行程草稿' ?></h1>
    <p class="muted">草稿只會讓你自己和管理員看見；發布後會出現在首頁與規劃師頁面。</p>
    <?php if ($trip): ?>
        <div class="actions">
            <a class="button small" href="/planner-dashboard.php">回工作台</a>
            <a class="button small" href="/trip.php?id=<?= (int) $trip['id'] ?>">查看行程頁</a>
        </div>
    <?php endif; ?>
    <form method="post" action="/actions/trip-save.php" class="form-grid">
        <?= csrf_field() ?>
        <?php if ($trip): ?>
            <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
        <?php endif; ?>
        <label>行程標題
            <input type="text" name="title" value="<?= e($trip['title'] ?? '') ?>" required maxlength="255">
        </label>
        <label>封面圖片網址
            <input type="url" name="cover_image" value="<?= e($trip['cover_image'] ?? '') ?>" placeholder="https://...">
        </label>
        <label>行程摘要
            <textarea name="summary" placeholder="描述行程亮點、適合對象與體驗內容"><?= e($trip['summary'] ?? '') ?></textarea>
        </label>

        <fieldset>
            <legend>行程地點</legend>
            <label>地址
                <input type="text" id="trip-address" name="address" value="<?= e($trip['address'] ?? '') ?>" placeholder="輸入地址或點擊地圖定位">
            </label>
            <input type="hidden" id="trip-lat" name="latitude" value="<?= e($trip['latitude'] ?? '') ?>">
            <input type="hidden" id="trip-lng" name="longitude" value="<?= e($trip['longitude'] ?? '') ?>">
            <input type="hidden" name="place_id" value="<?= e($trip['place_id'] ?? '') ?>">
            <div id="editor-location-map" style="height: 250px; border-radius: 8px; margin: 0.5rem 0;"></div>
        </fieldset>

        <div class="actions">
            <button type="submit" name="intent" value="draft">儲存草稿</button>
            <button class="primary" type="submit" name="intent" value="publish">發布行程</button>
        </div>
    </form>
</section>

<?php if ($trip): ?>
<section class="panel">
    <div class="section-heading">
        <div><p class="eyebrow">Spots</p><h2>行程景點</h2></div>
        <button class="button small" type="button" onclick="addSpotRow()">+ 新增景點</button>
    </div>

    <div id="spots-container">
        <?php foreach ($spots as $idx => $spot): ?>
        <div class="card spot-row" data-index="<?= $idx ?>">
            <span class="spot-handle">⠿</span>
            <input type="hidden" name="spots[<?= $idx ?>][id]" value="<?= (int) $spot['id'] ?>">
            <div class="spot-fields">
                <input type="text" name="spots[<?= $idx ?>][name]" value="<?= e($spot['name']) ?>" placeholder="景點名稱" required>
                <input type="text" name="spots[<?= $idx ?>][address]" value="<?= e($spot['address'] ?? '') ?>" placeholder="地址">
                <textarea name="spots[<?= $idx ?>][notes]" placeholder="備註"><?= e($spot['notes'] ?? '') ?></textarea>
                <div class="spot-coords">
                    <input type="text" class="spot-lat" name="spots[<?= $idx ?>][latitude]" value="<?= e($spot['latitude'] ?? '') ?>" placeholder="緯度">
                    <input type="text" class="spot-lng" name="spots[<?= $idx ?>][longitude]" value="<?= e($spot['longitude'] ?? '') ?>" placeholder="經度">
                    <button class="button small pick-map-btn" type="button" onclick="openSpotPicker(this)">📍 地圖定位</button>
                </div>
            </div>
            <button class="danger small" type="button" onclick="this.closest('.spot-row').remove()">刪除</button>
        </div>
        <?php endforeach; ?>
    </div>

    <template id="spot-template">
        <div class="card spot-row" data-index="__IDX__">
            <span class="spot-handle">⠿</span>
            <input type="hidden" name="spots[__IDX__][id]" value="">
            <div class="spot-fields">
                <input type="text" name="spots[__IDX__][name]" placeholder="景點名稱" required>
                <input type="text" name="spots[__IDX__][address]" placeholder="地址">
                <textarea name="spots[__IDX__][notes]" placeholder="備註"></textarea>
                <div class="spot-coords">
                    <input type="text" class="spot-lat" name="spots[__IDX__][latitude]" placeholder="緯度">
                    <input type="text" class="spot-lng" name="spots[__IDX__][longitude]" placeholder="經度">
                    <button class="button small pick-map-btn" type="button" onclick="openSpotPicker(this)">📍 地圖定位</button>
                </div>
            </div>
            <button class="danger small" type="button" onclick="this.closest('.spot-row').remove()">刪除</button>
        </div>
    </template>

    <div id="spot-picker-map" style="height:0;border-radius:8px;margin-top:0.5rem;transition:height 0.3s;overflow:hidden;"></div>
    <p class="muted" style="margin-top:0.75rem"><small>點擊「新增景點」後填寫資訊，或點「📍 地圖定位」在地圖上點選位置。拖曳 ⠿ 可調整排序。</small></p>
</section>
<?php endif; ?>

<script>
// Spot row counter (for new spots)
var spotCounter = <?= count($spots) ?>;
var activeSpotRow = null;
var spotPickerMap = null;
var spotPickerMarker = null;

function addSpotRow() {
    var t = document.getElementById('spot-template');
    var html = t.innerHTML.replace(/__IDX__/g, spotCounter++);
    var div = document.createElement('div');
    div.innerHTML = html;
    document.getElementById('spots-container').appendChild(div.firstElementChild);
}

function openSpotPicker(btn) {
    activeSpotRow = btn.closest('.spot-row');
    var mapContainer = document.getElementById('spot-picker-map');

    if (spotPickerMap === null) {
        mapContainer.style.height = '250px';
        spotPickerMap = initMap('spot-picker-map', { center: [23.6978, 120.9605], zoom: 7 });

        spotPickerMap.on('click', function(e) {
            if (!activeSpotRow) return;
            var lat = e.latlng.lat.toFixed(7);
            var lng = e.latlng.lng.toFixed(7);
            activeSpotRow.querySelector('.spot-lat').value = lat;
            activeSpotRow.querySelector('.spot-lng').value = lng;
            if (spotPickerMarker) spotPickerMap.removeLayer(spotPickerMarker);
            spotPickerMarker = L.marker([lat, lng]).addTo(spotPickerMap);
            // Reverse geocode
            fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&accept-language=zh', {
                headers: { 'User-Agent': 'TravelPlatform/1.0 (school-project)' }
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.display_name) activeSpotRow.querySelector('input[name$="[address]"]').value = d.display_name;
            })
            .catch(function() {});
        });
    } else {
        mapContainer.style.height = mapContainer.style.height === '0px' ? '250px' : '0px';
        if (mapContainer.style.height === '0px') {
            spotPickerMap.remove();
            spotPickerMap = null;
            spotPickerMarker = null;
        } else {
            setTimeout(function() { spotPickerMap.invalidateSize(); }, 300);
        }
    }
}

// SortableJS drag reorder for spots
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('spots-container');
    if (el && typeof Sortable !== 'undefined') {
        Sortable.create(el, {
            handle: '.spot-handle',
            animation: 150,
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var lat = <?= json_encode($trip['latitude'] ?? null) ?>;
    var lng = <?= json_encode($trip['longitude'] ?? null) ?>;
    var defaultCenter = lat && lng ? [lat, lng] : [23.6978, 120.9605];
    var defaultZoom = lat && lng ? 15 : 7;

    var map = initMap('editor-location-map', { center: defaultCenter, zoom: defaultZoom });
    var marker = lat && lng ? L.marker([lat, lng]).addTo(map) : null;

    map.on('click', function(e) {
        var lat = e.latlng.lat.toFixed(7);
        var lng = e.latlng.lng.toFixed(7);
        document.getElementById('trip-lat').value = lat;
        document.getElementById('trip-lng').value = lng;
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lng]).addTo(map);
        // Reverse geocode
        fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&accept-language=zh', {
                headers: { 'User-Agent': 'TravelPlatform/1.0 (school-project)' }
            })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.display_name) document.getElementById('trip-address').value = d.display_name; })
            .catch(function() {});
    });
});
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
