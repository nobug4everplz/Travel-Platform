<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function add_traveler_footprint(int $userId, ?int $tripId, string $name, ?float $lat, ?float $lng, ?string $placeId, ?string $notes, ?string $visitedAt): int
{
    $stmt = pdo()->prepare(
        'INSERT INTO traveler_footprints (user_id, trip_id, name, latitude, longitude, place_id, visited_at, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $visited = $visitedAt ?? date('Y-m-d H:i:s');
    $stmt->execute([$userId, $tripId, $name, $lat, $lng, $placeId, $visited, $notes]);

    return (int) pdo()->lastInsertId();
}

function get_traveler_footprints(int $userId): array
{
    $stmt = pdo()->prepare(
        'SELECT * FROM traveler_footprints WHERE user_id = ? ORDER BY visited_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function delete_traveler_footprint(int $footprintId, int $userId): bool
{
    $stmt = pdo()->prepare('DELETE FROM traveler_footprints WHERE id = ? AND user_id = ?');
    $stmt->execute([$footprintId, $userId]);
    return $stmt->rowCount() > 0;
}

function get_traveler_footprints_from_participations(int $userId): array
{
    $stmt = pdo()->prepare(
        'SELECT ts.*, t.title AS trip_title
         FROM trip_spots ts
         JOIN trip_participations tp ON tp.trip_id = ts.trip_id
         JOIN trips t ON t.id = ts.trip_id
         WHERE tp.user_id = ?
         ORDER BY ts.sort_order'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
