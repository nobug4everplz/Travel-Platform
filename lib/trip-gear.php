<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function get_trip_gear(int $tripId): array
{
    $stmt = pdo()->prepare(
        'SELECT id, name, icon, affiliate_url, sort_order
         FROM trip_gear WHERE trip_id = ? ORDER BY sort_order ASC'
    );
    $stmt->execute([$tripId]);
    return $stmt->fetchAll();
}

function save_trip_gear(int $tripId, array $gearItems): void
{
    $pdo = pdo();

    /* Full-replace: delete all existing, re-insert with fresh sort_order */
    $del = $pdo->prepare('DELETE FROM trip_gear WHERE trip_id = ?');
    $del->execute([$tripId]);

    if (empty($gearItems)) {
        return;
    }

    $ins = $pdo->prepare(
        'INSERT INTO trip_gear (trip_id, name, icon, affiliate_url, sort_order) VALUES (?, ?, ?, ?, ?)'
    );

    foreach ($gearItems as $i => $item) {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $icon = trim((string) ($item['icon'] ?? ''));
        $url  = trim((string) ($item['affiliate_url'] ?? ''));
        $ins->execute([$tripId, $name, $icon ?: null, $url ?: null, $i]);
    }
}

function delete_trip_gear(int $gearId, int $tripId, int $userId): bool
{
    /* Verify trip ownership */
    $tripStmt = pdo()->prepare('SELECT author_id FROM trips WHERE id = ? LIMIT 1');
    $tripStmt->execute([$tripId]);
    $trip = $tripStmt->fetch();

    if (!$trip || (int) $trip['author_id'] !== $userId) {
        return false;
    }

    $stmt = pdo()->prepare('DELETE FROM trip_gear WHERE id = ? AND trip_id = ?');
    $stmt->execute([$gearId, $tripId]);
    return $stmt->rowCount() > 0;
}
