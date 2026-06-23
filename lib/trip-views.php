<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function record_trip_unique_view(array $trip, ?array $viewer): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || (int) $trip['is_published'] !== 1) {
        return;
    }

    if ($viewer && ($viewer['role'] === 'admin' || (int) $viewer['id'] === (int) $trip['author_id'])) {
        return;
    }

    $viewerKeyHash = trip_viewer_key_hash($viewer);
    $stmt = pdo()->prepare(
        'INSERT INTO trip_daily_unique_views (trip_id, view_date, viewer_key_hash)
         VALUES (?, ?, ?)
         ON CONFLICT (trip_id, view_date, viewer_key_hash) DO NOTHING'
    );
    $stmt->execute([(int) $trip['id'], date('Y-m-d'), $viewerKeyHash]);
}

function trip_viewer_key_hash(?array $viewer): string
{
    if ($viewer) {
        return hash('sha256', 'user:' . (int) $viewer['id']);
    }

    $cookieName = 'travel_visitor_id';
    $visitorId = $_COOKIE[$cookieName] ?? '';

    if (!is_string($visitorId) || preg_match('/\A[a-f0-9]{64}\z/D', $visitorId) !== 1) {
        $visitorId = bin2hex(random_bytes(32));
        setcookie($cookieName, $visitorId, [
            'expires' => time() + (90 * 24 * 60 * 60),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$cookieName] = $visitorId;
    }

    return hash('sha256', 'visitor:' . $visitorId);
}
