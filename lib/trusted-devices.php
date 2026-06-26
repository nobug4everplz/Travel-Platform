<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const TRUSTED_DEVICE_COOKIE = 'travel_trusted_device';
const TRUSTED_DEVICE_LIFETIME = 60 * 60 * 24 * 30;

function client_ip_address(): ?string
{
    $ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    return $ipAddress === '' ? null : substr($ipAddress, 0, 45);
}

function client_user_agent(): ?string
{
    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    return $userAgent === '' ? null : substr($userAgent, 0, 512);
}

function trusted_device_label(?string $userAgent): string
{
    if ($userAgent === null) {
        return 'Unknown browser';
    }

    $browser = match (true) {
        str_contains($userAgent, 'Edg/') => 'Edge',
        str_contains($userAgent, 'Chrome/') => 'Chrome',
        str_contains($userAgent, 'Firefox/') => 'Firefox',
        str_contains($userAgent, 'Safari/') => 'Safari',
        default => 'Browser',
    };
    $platform = match (true) {
        str_contains($userAgent, 'Windows') => 'Windows',
        str_contains($userAgent, 'Android') => 'Android',
        str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
        str_contains($userAgent, 'Macintosh') => 'macOS',
        str_contains($userAgent, 'Linux') => 'Linux',
        default => 'Unknown device',
    };

    return $browser . ' on ' . $platform;
}

function trusted_device_cookie_value(): ?string
{
    $token = $_COOKIE[TRUSTED_DEVICE_COOKIE] ?? null;

    return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1 ? $token : null;
}

function set_trusted_device_cookie(string $token): void
{
    setcookie(TRUSTED_DEVICE_COOKIE, $token, [
        'expires' => time() + TRUSTED_DEVICE_LIFETIME,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[TRUSTED_DEVICE_COOKIE] = $token;
}

function clear_trusted_device_cookie(): void
{
    setcookie(TRUSTED_DEVICE_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[TRUSTED_DEVICE_COOKIE]);
}

function create_trusted_device(array $user): array
{
    $token = bin2hex(random_bytes(32));
    $userAgent = client_user_agent();
    $expiresAt = date('Y-m-d H:i:s', time() + TRUSTED_DEVICE_LIFETIME);
    $stmt = pdo()->prepare(
        'INSERT INTO trusted_devices
            (user_id, token_hash, device_label, user_agent, first_ip, last_ip, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $user['id'],
        hash('sha256', $token),
        trusted_device_label($userAgent),
        $userAgent,
        client_ip_address(),
        client_ip_address(),
        $expiresAt,
    ]);

    set_trusted_device_cookie($token);

    return [
        'id' => (int) pdo()->lastInsertId(),
        'device_label' => trusted_device_label($userAgent),
        'is_new' => true,
    ];
}

function resolve_trusted_device(array $user): array
{
    $token = trusted_device_cookie_value();
    if ($token !== null) {
        $stmt = pdo()->prepare(
            'SELECT id, device_label
             FROM trusted_devices
             WHERE user_id = ? AND token_hash = ? AND revoked_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([(int) $user['id'], hash('sha256', $token)]);
        $device = $stmt->fetch();

        if ($device) {
            $expiresAt = date('Y-m-d H:i:s', time() + TRUSTED_DEVICE_LIFETIME);
            $update = pdo()->prepare(
                'UPDATE trusted_devices
                 SET last_ip = ?, user_agent = ?, last_seen_at = NOW(), expires_at = ?
                 WHERE id = ?'
            );
            $update->execute([client_ip_address(), client_user_agent(), $expiresAt, (int) $device['id']]);
            set_trusted_device_cookie($token);

            return [
                'id' => (int) $device['id'],
                'device_label' => (string) $device['device_label'],
                'is_new' => false,
            ];
        }
    }

    return create_trusted_device($user);
}

function record_login_event(array $user, array $device): void
{
    $stmt = pdo()->prepare(
        'INSERT INTO login_events (user_id, ip_address, user_agent, trusted_device_id, is_new_device)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $user['id'],
        client_ip_address(),
        client_user_agent(),
        (int) $device['id'],
        $device['is_new'] ? 'true' : 'false',
    ]);
}

function revoke_trusted_device(array $user, int $deviceId): bool
{
    $stmt = pdo()->prepare('SELECT token_hash FROM trusted_devices WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$deviceId, (int) $user['id']]);
    $device = $stmt->fetch();
    if (!$device) {
        return false;
    }

    $update = pdo()->prepare(
        'UPDATE trusted_devices SET revoked_at = NOW() WHERE id = ? AND user_id = ? AND revoked_at IS NULL'
    );
    $update->execute([$deviceId, (int) $user['id']]);

    $token = trusted_device_cookie_value();
    if ($token !== null && hash_equals((string) $device['token_hash'], hash('sha256', $token))) {
        // Revoking this browser removes trust but does not sign out its active session.
        clear_trusted_device_cookie();
    }

    return $update->rowCount() > 0;
}

function user_trusted_devices(array $user): array
{
    $stmt = pdo()->prepare(
        'SELECT id, token_hash, device_label, first_ip, last_ip, first_seen_at, last_seen_at, expires_at, revoked_at
         FROM trusted_devices
         WHERE user_id = ?
         ORDER BY revoked_at IS NULL DESC, last_seen_at DESC'
    );
    $stmt->execute([(int) $user['id']]);
    $token = trusted_device_cookie_value();
    $currentHash = $token === null ? null : hash('sha256', $token);

    return array_map(static function (array $device) use ($currentHash): array {
        $device['is_current'] = $currentHash !== null && hash_equals((string) $device['token_hash'], $currentHash);
        return $device;
    }, $stmt->fetchAll());
}
