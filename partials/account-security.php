<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/trusted-devices.php';
require_once __DIR__ . '/../lib/notifications.php';

$accountUser = $accountUser ?? $user ?? $admin;
$trustedDevices = user_trusted_devices($accountUser);
$notificationPreferences = get_notification_preferences($accountUser);
?>
<?php if ($notificationPreferences): ?>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Notifications</p><h2>郵件通知偏好</h2></div></div>
    <form method="post" action="/actions/notification-preferences.php" class="form-grid">
        <?= csrf_field() ?>
        <div class="preference-list">
            <div class="preference-row">
                <label><input type="checkbox" name="popular_digest_enabled" <?= (int) $notificationPreferences['popular_digest_enabled'] === 1 ? 'checked' : '' ?>> 每日熱門行程與規劃師推薦</label>
                <span class="device-meta">每日 17:00</span>
            </div>
            <?php if ($accountUser['role'] === 'planner'): ?>
                <div class="preference-row">
                    <label><input type="checkbox" name="planner_digest_enabled" <?= (int) $notificationPreferences['planner_digest_enabled'] === 1 ? 'checked' : '' ?>> 規劃師營運摘要</label>
                    <span class="device-meta">每三日 09:00</span>
                </div>
            <?php endif; ?>
            <div class="preference-row">
                <label><input type="checkbox" name="winback_enabled" <?= (int) $notificationPreferences['winback_enabled'] === 1 ? 'checked' : '' ?>> 未登入召回提醒</label>
                <span class="device-meta">未登入第 5、14 日</span>
            </div>
        </div>
        <div class="actions"><button class="primary small" type="submit">儲存通知偏好</button></div>
    </form>
</section>
<?php endif; ?>
<section class="panel">
    <div class="section-heading"><div><p class="eyebrow">Security</p><h2>登入裝置</h2></div></div>
    <?php if (!$trustedDevices): ?>
        <div class="empty-state">目前沒有已記錄的登入裝置。</div>
    <?php else: ?>
        <div class="device-list">
            <?php foreach ($trustedDevices as $device): ?>
                <div class="device-row">
                    <div>
                        <strong><?= e($device['device_label']) ?></strong>
                        <?php if ($device['is_current']): ?><span class="badge">目前裝置</span><?php endif; ?>
                        <?php if ($device['revoked_at']): ?><span class="badge gray">已撤銷</span><?php endif; ?>
                        <p class="device-meta">最近使用 <?= e(format_date($device['last_seen_at'])) ?> · IP <?= e($device['last_ip'] ?: '-') ?></p>
                    </div>
                    <?php if (!$device['revoked_at']): ?>
                        <form method="post" action="/actions/trusted-device-revoke.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="device_id" value="<?= (int) $device['id'] ?>">
                            <button class="small" type="submit">移除信任</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
