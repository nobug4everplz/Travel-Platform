<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';
require_once __DIR__ . '/../lib/reviews.php';
require_once __DIR__ . '/../lib/trip-views.php';

$tripId = input_int($_GET, 'id');
if ($tripId === null) {
    abort_page(400, '缺少行程', '請提供有效的行程 ID。');
}

$user = current_user();
$trip = find_visible_trip($tripId, $user);
if (!$trip) {
    abort_page(404, '找不到行程', '這個行程不存在，或你沒有權限查看。');
}

record_trip_unique_view($trip, $user);

$isPublished = (int) $trip['is_published'] === 1;
$isTraveler = $user && $user['role'] === 'traveler';
$isPlanner = $user && $user['role'] === 'planner';
$isAuthor = $isPlanner && (int) $trip['author_id'] === (int) $user['id'];
$reviews = get_trip_reviews($tripId);
$isParticipating = $isTraveler ? user_has_participation((int) $user['id'], $tripId) : false;
$isFavorited = $user && in_array($user['role'], ['traveler', 'planner'], true)
    ? user_favorited_trip((int) $user['id'], $tripId)
    : false;
$myReview = $isTraveler ? get_user_review_for_trip((int) $user['id'], $tripId) : null;

require_once __DIR__ . '/../lib/spot-actions.php';
require_once __DIR__ . '/../lib/trip-gear.php';
require_once __DIR__ . '/../lib/weather.php';
require_once __DIR__ . '/../lib/currency.php';
require_once __DIR__ . '/../lib/trip-photos.php';
require_once __DIR__ . '/../lib/traveler-match.php';
$participants = get_trip_participants($tripId);
$participantCount = count($participants);
$spots = get_trip_spots($tripId);
$gear = get_trip_gear($tripId);
$hasGear = count($gear) > 0;
$hasMap = $trip['latitude'] && $trip['longitude'];
if ($hasMap) {
    $loadMap = true;
}
$photos = get_trip_photos($tripId);
$spotPhotos = get_spot_photos_grouped($tripId);

$pageTitle = $trip['title'];
$pageType = 'trip';
$bodyDataAttrs = 'data-trip-id="' . (int) $trip['id'] . '" data-trip-title="' . e($trip['title']) . '"';
require __DIR__ . '/../partials/header.php';
?>
<section class="panel">
    <div class="grid two">
        <div>
            <?php if ($trip['cover_image']): ?>
                <img class="cover" src="<?= e($trip['cover_image']) ?>" alt="<?= e($trip['title']) ?>">
            <?php else: ?>
                <div class="placeholder-cover">Trip</div>
            <?php endif; ?>
        </div>
        <div>
            <p class="eyebrow"><?= $isPublished ? 'Published Trip' : 'Draft Preview' ?></p>
            <h1><?= e($trip['title']) ?></h1>
            <p class="muted"><?= render_markdown_images($trip['summary'] ?: '這個行程尚未填寫摘要。') ?></p>
            <p class="meta">評分 <?= e(format_rating($trip['average_rating'])) ?>，<?= (int) $trip['review_count'] ?> 則評論</p>
            <p class="meta">規劃師 <a class="text-link" href="/planner.php?id=<?= (int) $trip['author_id'] ?>"><?= e($trip['author_name'] ?: $trip['author_email']) ?></a></p>
            <?php if (!empty($trip['start_date'])): ?>
                <div class="countdown" data-start-date="<?= e($trip['start_date']) ?>"></div>
            <?php endif; ?>
            <div class="actions">
                <?php if (!$user): ?>
                    <a class="button primary" href="/login.php">登入後互動</a>
                <?php else: ?>
                    <?php if ($isTraveler && $isPublished): ?>
                        <form method="post" action="/actions/participation.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                            <input type="hidden" name="intent" value="<?= $isParticipating ? 'leave' : 'join' ?>">
                            <button class="<?= $isParticipating ? '' : 'primary' ?>" type="submit"><?= $isParticipating ? '取消參加' : '參加行程' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (in_array($user['role'], ['traveler', 'planner'], true) && $isPublished): ?>
                        <form method="post" action="/actions/favorite-trip.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                            <input type="hidden" name="intent" value="<?= $isFavorited ? 'remove' : 'add' ?>">
                            <button type="submit"><?= $isFavorited ? '取消收藏' : '收藏行程' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($isAuthor): ?>
                        <a class="button" href="/editor.php?id=<?= (int) $trip['id'] ?>">編輯行程</a>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        <span class="badge gray">管理員檢視</span>
                    <?php endif; ?>
                <?php endif; ?>
                <a class="button" href="/actions/export-pdf.php?id=<?= (int) $trip['id'] ?>">📥 下載行程手冊</a>
            </div>
        </div>
    </div>
</section>

<?php
$budgetAmount = $trip['budget'] ?? null;
$tripCurrency = $trip['currency'] ?? 'TWD';
$showBudget = $budgetAmount !== null && (float) $budgetAmount > 0;

if ($showBudget):
    $destCurrency = guess_destination_currency($trip['address'] ?? null);
    $hasConversion = $destCurrency !== null && $destCurrency !== $tripCurrency;
    $converted = $hasConversion ? convert_currency((float) $budgetAmount, $tripCurrency, $destCurrency) : null;
    $cacheTime = $hasConversion ? get_exchange_rate_cache_time() : null;
?>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Budget</p><h2>行程預算</h2></div></div>
    <p style="font-size:1.25rem;font-weight:600;">
        <?= format_currency((float) $budgetAmount, $tripCurrency) ?>
        <?php if ($hasConversion && $converted !== null): ?>
            &nbsp;≈ <?= format_currency($converted, $destCurrency) ?>
        <?php endif; ?>
    </p>
    <?php if ($cacheTime !== null): ?>
        <p class="muted" style="font-size:13px;">匯率更新 <?= e($cacheTime) ?></p>
    <?php elseif ($hasConversion): ?>
        <p class="muted" style="font-size:13px;">匯率暫時無法取得</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel">
    <div class="section-heading">
        <div><p class="eyebrow">Itinerary</p><h2>行程景點</h2></div>
        <button class="button small" onclick="var c=this.parentElement.parentElement.querySelector('.collapse-wrap');c.style.display=c.style.display==='none'?'':'none';this.textContent=c.style.display==='none'?'展開':'收合'" type="button">收合</button>
    </div>
    <div class="collapse-wrap">

    <?php if ($hasMap): ?>
    <div id="trip-map" style="height:300px;border-radius:12px;overflow:hidden;margin-bottom:1rem;"></div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var map = initMap('trip-map');
        var markers = [];

        // 行程位置 marker
        var trip = <?= json_encode([
            'id' => (int) $trip['id'],
            'title' => $trip['title'],
            'latitude' => $trip['latitude'],
            'longitude' => $trip['longitude'],
            'average_rating' => $trip['average_rating'],
            'summary' => $trip['summary'],
        ], JSON_UNESCAPED_UNICODE) ?>;
        var tm = addTripMarker(map, trip);
        if (tm) markers.push(tm);

        // 景點 markers
        var spots = <?= json_encode($spots, JSON_UNESCAPED_UNICODE) ?>;
        var spotPhotos = <?= json_encode($spotPhotos, JSON_UNESCAPED_UNICODE) ?>;
        var spotMarkers = [];
        for (var i = 0; i < spots.length; i++) {
            if (spotPhotos[spots[i].id]) {
                spots[i]._photos = spotPhotos[spots[i].id];
            }
            var sm = addSpotMarker(map, spots[i], i);
            if (sm) spotMarkers.push(sm);
        }
        markers = markers.concat(spotMarkers);

        // 景點連線
        connectSpotsPolyline(map, spots);

        if (markers.length > 0) fitAllMarkers(map, markers);
    });
    </script>
    <?php endif; ?>

    <?php if ($spots): ?>
    <div class="grid two spots-list">
        <?php foreach ($spots as $idx => $spot): ?>
        <article class="card"><div class="card-body">
            <span class="badge"><?= $idx + 1 ?></span>
            <h3><?= e($spot['name']) ?></h3>
            <?php if ($spot['address']): ?>
            <p class="muted"><?= e($spot['address']) ?></p>
            <?php endif; ?>
            <?php if ($spot['notes']): ?>
            <p><?= nl2br(e($spot['notes'])) ?></p>
            <?php endif; ?>
            <?php if ($spot['google_maps_url']): ?>
            <div class="actions">
                <a class="button small" href="<?= e($spot['google_maps_url']) ?>" target="_blank" rel="noopener">🗺 Google Maps</a>
            </div>
            <?php endif; ?>
        </div></article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">這個行程尚未新增景點。</div>
    <?php endif; ?>
    </div>
</section>
<?php if ($hasGear): ?>
<section class="panel">
    <div class="section-heading">
        <div><p class="eyebrow">Gear</p><h2>建議裝備</h2></div>
        <button class="button small" onclick="var c=this.parentElement.parentElement.querySelector('.collapse-wrap');c.style.display=c.style.display==='none'?'':'none';this.textContent=c.style.display==='none'?'展開':'收合'" type="button">收合</button>
    </div>
    <div class="collapse-wrap">
        <div class="grid">
            <?php foreach ($gear as $g): ?>
            <article class="card"><div class="card-body" style="text-align:center;">
                <span style="font-size:2.5rem;display:block;margin-bottom:0.5rem;"><?= e($g['icon']) ?></span>
                <h3><?= e($g['name']) ?></h3>
                <?php if ($g['affiliate_url']): ?>
                <div class="actions" style="justify-content:center;">
                    <a class="button small primary" href="<?= e($g['affiliate_url']) ?>" target="_blank" rel="noopener">🛒 購買</a>
                </div>
                <?php endif; ?>
            </div></article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($participantCount > 0): ?>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Travelers</p><h2>參加者 (<?= $participantCount ?>)</h2></div></div>
    <?php if ($user): ?>
        <div class="grid three">
            <?php foreach ($participants as $p): ?>
                <a class="card card-link" href="/traveler-profile.php?id=<?= (int) $p['id'] ?>"><div class="card-body" style="display:flex;align-items:center;gap:0.75rem;">
                    <?php if ($p['avatar_url']): ?>
                        <img class="avatar" src="<?= e($p['avatar_url']) ?>" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        <span class="avatar-placeholder" style="width:48px;height:48px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-weight:700;color:#6b7280;"><?= e(display_initial($p['name'])) ?></span>
                    <?php endif; ?>
                    <div>
                        <strong><?= e($p['name'] ?: '匿名旅人') ?></strong>
                        <p class="muted" style="font-size:13px;">參加於 <?= e(format_date($p['joined_at'])) ?></p>
                    </div>
                </div></a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="muted">登入後即可查看參加者詳細資訊。</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php
$hasWeather = false;
$weatherNow = null;
$weatherForecast = null;
if (!empty($trip['address'])) {
    $city = extract_city_from_address($trip['address']);
    if ($city !== '') {
        $weatherNow = get_weather($city);
        $weatherForecast = get_forecast($city);
        $hasWeather = $weatherNow !== null;
    }
}
?>
<?php if ($hasWeather): ?>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Weather</p><h2>當地天氣</h2></div></div>
    <div class="weather-current" style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
        <span style="font-size:60px;"><?= e($weatherNow['icon']) ?></span>
        <div>
            <span style="font-size:2rem;font-weight:700;"><?= round($weatherNow['temp']) ?>°C</span>
            <span style="color:#666;"><?= e($weatherNow['description']) ?></span>
        </div>
    </div>
    <?php if ($weatherForecast): ?>
    <div class="grid three">
        <?php foreach ($weatherForecast as $day): ?>
        <div class="card"><div class="card-body" style="text-align:center;">
            <p class="muted" style="margin-bottom:0.25rem;"><?= e(date('m/d', strtotime($day['date']))) ?></p>
            <span style="font-size:50px;"><?= e($day['icon']) ?></span>
            <p style="margin:0;"><strong><?= round($day['temp_high']) ?>°</strong> / <?= round($day['temp_low']) ?>°</p>
            <p class="muted" style="font-size:13px;"><?= e($day['description']) ?></p>
        </div></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php if (count($photos) > 0 || ($isTraveler && $isParticipating)): ?>
<section class="panel">
    <div class="section-heading">
        <div><p class="eyebrow">Photos</p><h2>照片牆</h2></div>
    </div>

    <?php if ($isTraveler && $isParticipating): ?>
    <form method="post" action="/actions/upload-photo.php" enctype="multipart/form-data" class="form-grid" style="margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--border);">
        <?= csrf_field() ?>
        <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
        <label>選擇照片
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
        </label>
        <label>關聯景點（選填）
            <select name="spot_id">
                <option value="">不指定</option>
                <?php foreach ($spots as $spot): ?>
                <option value="<?= (int) $spot['id'] ?>"><?= e($spot['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>照片說明（選填）
            <input type="text" name="caption" placeholder="簡短描述這張照片..." maxlength="500">
        </label>
        <div class="actions">
            <button class="primary" type="submit">📷 上傳照片</button>
        </div>
    </form>
    <?php endif; ?>

    <?php if (count($photos) > 0): ?>
    <div class="photo-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
        <?php foreach ($photos as $photo): ?>
        <article class="card" style="overflow:hidden;">
            <a href="#" class="photo-thumb" data-src="<?= e($photo['image_path']) ?>" data-caption="<?= e($photo['caption'] ?? '') ?>" data-uploader="<?= e($photo['uploader_name'] ?: '匿名旅行者') ?>" data-spot="<?= e($photo['spot_name'] ?? '') ?>" data-date="<?= e(format_date($photo['created_at'])) ?>" onclick="openLightbox(this);return false;" style="display:block;aspect-ratio:4/3;background:var(--surface-muted);overflow:hidden;">
                <img src="<?= e($photo['image_path']) ?>" alt="<?= e($photo['caption'] ?? '照片') ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
            </a>
            <div class="card-body" style="padding:10px 12px;font-size:13px;">
                <p style="margin:0;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">📸 <?= e($photo['uploader_name'] ?: '匿名旅行者') ?></p>
                <?php if ($photo['spot_name']): ?>
                <p style="margin:0;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">📍 <?= e($photo['spot_name']) ?></p>
                <?php endif; ?>
                <?php if ($photo['caption']): ?>
                <p style="margin:0;color:var(--muted-strong);font-style:italic;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">“<?= e($photo['caption']) ?>”</p>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">尚無照片，快上傳第一張吧！</div>
    <?php endif; ?>
</section>
<?php endif; ?>
<!-- Lightbox overlay -->
<div id="lightbox" onclick="closeLightbox(event)" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(0,0,0,0.85);justify-content:center;align-items:center;flex-direction:column;padding:2rem;">
    <button onclick="closeLightbox()" style="position:absolute;top:1rem;right:1.5rem;background:none;border:none;color:#fff;font-size:2rem;cursor:pointer;z-index:10;" type="button" aria-label="關閉">✕</button>
    <img id="lightbox-image" src="" alt="" style="max-width:90vw;max-height:75vh;border-radius:8px;object-fit:contain;">
    <div id="lightbox-info" style="color:#ccc;text-align:center;margin-top:1rem;font-size:14px;max-width:600px;"></div>
</div>
<script>
function openLightbox(el) {
    var lb = document.getElementById('lightbox');
    var img = document.getElementById('lightbox-image');
    var info = document.getElementById('lightbox-info');
    img.src = el.getAttribute('data-src');
    var parts = [];
    var uploader = el.getAttribute('data-uploader');
    if (uploader) parts.push('📸 ' + uploader);
    var spot = el.getAttribute('data-spot');
    if (spot) parts.push('📍 ' + spot);
    var caption = el.getAttribute('data-caption');
    if (caption) parts.push('“' + caption + '”');
    info.textContent = parts.join(' · ');
    lb.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLightbox(e) {
    if (e && e.target !== e.currentTarget) return;
    var lb = document.getElementById('lightbox');
    lb.style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var lb = document.getElementById('lightbox');
        if (lb.style.display !== 'none') closeLightbox();
    }
});
</script>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Reviews</p><h2>行程評論</h2></div></div>
    <?php if ($isTraveler && $isParticipating): ?>
        <form method="post" action="/actions/review.php" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
            <input type="hidden" name="intent" value="<?= $myReview ? 'update' : 'create' ?>">
            <?php if ($myReview): ?>
                <input type="hidden" name="review_id" value="<?= (int) $myReview['id'] ?>">
            <?php endif; ?>
            <label>評分
                <select name="rating" required>
                    <?php for ($score = 5; $score >= 1; $score--): ?>
                        <option value="<?= $score ?>" <?= $myReview && (int) $myReview['rating'] === $score ? 'selected' : '' ?>><?= $score ?> 星</option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>評論
                <textarea name="comment" placeholder="分享你對這個行程的感受"><?= e($myReview['comment'] ?? '') ?></textarea>
            </label>
            <div class="actions"><button class="primary" type="submit"><?= $myReview ? '更新評論' : '送出評論' ?></button></div>
        </form>
        <?php if ($myReview): ?>
            <form method="post" action="/actions/review.php" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                <input type="hidden" name="review_id" value="<?= (int) $myReview['id'] ?>">
                <input type="hidden" name="intent" value="delete">
                <button class="danger small" type="submit">刪除我的評論</button>
            </form>
        <?php endif; ?>
    <?php elseif ($isTraveler): ?>
        <div class="empty-state">參加行程後才能留下評論。</div>
    <?php elseif (!$user): ?>
        <div class="empty-state">登入並參加行程後即可評論。</div>
    <?php endif; ?>

    <?php if (!$reviews): ?>
        <div class="empty-state">目前尚無評論。</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($reviews as $review): ?>
                <article class="card"><div class="card-body">
                    <p><strong><?= e($review['reviewer_name'] ?: '匿名使用者') ?></strong> <span class="badge gray"><?= (int) $review['rating'] ?> 分</span></p>
                    <p><?= e($review['comment'] ?: '沒有留下文字評論。') ?></p>
                    <p class="muted">更新於 <?= e(format_date($review['updated_at'])) ?></p>
                </div></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<style>
.countdown {
    margin: 0.75rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    display: inline-block;
}
.countdown-past { color: #999; font-weight: 400; }
.countdown-today { color: #10b981; background: #ecfdf5; }
.countdown-soon { color: #d97706; background: #fffbeb; border: 1px solid #fde68a; }
.countdown-far { color: #4f46e5; font-weight: 400; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function updateCountdowns() {
        var els = document.querySelectorAll('.countdown[data-start-date]');
        var now = new Date();
        now.setHours(0, 0, 0, 0);
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            var parts = el.getAttribute('data-start-date').split('-');
            var startDate = new Date(+parts[0], +parts[1] - 1, +parts[2]);
            startDate.setHours(0, 0, 0, 0);
            var diff = Math.round((startDate.getTime() - now.getTime()) / 86400000);
            var text, cls;
            if (diff < 0)       { text = '行程已出發';          cls = 'countdown-past'; }
            else if (diff === 0){ text = '就是今天！🎉';         cls = 'countdown-today'; }
            else if (diff <= 7) { text = '🔥 距離出發還有 ' + diff + ' 天'; cls = 'countdown-soon'; }
            else if (diff <= 30){ text = '距離出發還有 ' + diff + ' 天';  cls = ''; }
            else                { text = '距離出發還有 ' + diff + ' 天';  cls = 'countdown-far'; }
            el.textContent = text;
            el.className = 'countdown' + (cls ? ' ' + cls : '');
        }
    }
    updateCountdowns();
    setInterval(updateCountdowns, 60000);
});
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
