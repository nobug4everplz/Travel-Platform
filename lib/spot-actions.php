<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

function add_trip_spot(int $tripId, string $name, ?float $lat, ?float $lng, ?string $placeId, ?string $address, ?string $notes, ?int $sortOrder = null): int
{
    if ($sortOrder === null) {
        $stmt = pdo()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM trip_spots WHERE trip_id = ?');
        $stmt->execute([$tripId]);
        $sortOrder = (int) $stmt->fetchColumn();
    }

    $mapsUrl = get_spot_google_maps_url($placeId);
    $stmt = pdo()->prepare(
        'INSERT INTO trip_spots (trip_id, sort_order, name, latitude, longitude, place_id, address, google_maps_url, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$tripId, $sortOrder, $name, $lat, $lng, $placeId, $address, $mapsUrl, $notes]);

    return (int) pdo()->lastInsertId();
}

function update_trip_spot(int $spotId, string $name, ?float $lat, ?float $lng, ?string $placeId, ?string $address, ?string $notes, ?int $sortOrder = null): void
{
    $mapsUrl = get_spot_google_maps_url($placeId);
    $stmt = pdo()->prepare(
        'UPDATE trip_spots SET name = ?, latitude = ?, longitude = ?, place_id = ?, address = ?, google_maps_url = ?, notes = ?, sort_order = ? WHERE id = ?'
    );
    $stmt->execute([$name, $lat, $lng, $placeId, $address, $mapsUrl, $notes, $sortOrder, $spotId]);
}

function delete_trip_spot(int $spotId, int $tripId, int $userId): bool
{
    $stmt = pdo()->prepare(
        'SELECT 1 FROM trip_spots ts
         JOIN trips t ON t.id = ts.trip_id
         WHERE ts.id = ? AND ts.trip_id = ? AND t.author_id = ?
         LIMIT 1'
    );
    $stmt->execute([$spotId, $tripId, $userId]);

    if (!$stmt->fetch()) {
        return false;
    }

    $stmt = pdo()->prepare('DELETE FROM trip_spots WHERE id = ?');
    $stmt->execute([$spotId]);
    return true;
}

function get_trip_spots(int $tripId): array
{
    $stmt = pdo()->prepare('SELECT * FROM trip_spots WHERE trip_id = ? ORDER BY sort_order');
    $stmt->execute([$tripId]);
    return $stmt->fetchAll();
}

function reorder_trip_spots(int $tripId, array $spotIds): void
{
    if (empty($spotIds)) {
        return;
    }

    pdo()->beginTransaction();
    try {
        $stmt = pdo()->prepare('UPDATE trip_spots SET sort_order = ? WHERE id = ? AND trip_id = ?');
        foreach ($spotIds as $index => $spotId) {
            $stmt->execute([$index + 1, (int) $spotId, $tripId]);
        }
        pdo()->commit();
    } catch (Exception $e) {
        pdo()->rollBack();
        throw $e;
    }
}

function get_spot_google_maps_url(?string $placeId): ?string
{
    if ($placeId === null || $placeId === '') {
        return null;
    }

    return 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode($placeId);
}

function save_trip_spots(int $tripId, array $rawSpots): void
{
    // Parse incoming spots
    $incoming = [];
    foreach ($rawSpots as $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $incoming[] = [
            'id'       => isset($raw['id']) && $raw['id'] !== '' ? (int) $raw['id'] : null,
            'name'     => trim((string) ($raw['name'] ?? '')),
            'latitude' => input_float($raw, 'latitude', -90, 90),
            'longitude'=> input_float($raw, 'longitude', -180, 180),
            'place_id' => trim_or_null($raw['place_id'] ?? null),
            'address'  => trim_or_null($raw['address'] ?? null),
            'notes'    => trim_or_null($raw['notes'] ?? null),
        ];
    }

    // Remove spots with empty name
    $incoming = array_values(array_filter($incoming, fn($s) => $s['name'] !== ''));

    // Get existing spots
    $existing = get_trip_spots($tripId);
    $existingIds = array_map(fn($s) => (int) $s['id'], $existing);

    pdo()->beginTransaction();
    try {
        $incomingIds = [];

        foreach ($incoming as $idx => $spot) {
            $sortOrder = $idx + 1;

            if ($spot['id'] !== null && in_array($spot['id'], $existingIds, true)) {
                // Update existing
                update_trip_spot(
                    $spot['id'],
                    $spot['name'],
                    $spot['latitude'],
                    $spot['longitude'],
                    $spot['place_id'],
                    $spot['address'],
                    $spot['notes'],
                    $sortOrder
                );
                $incomingIds[] = $spot['id'];
            } else {
                // Insert new
                $newId = add_trip_spot(
                    $tripId,
                    $spot['name'],
                    $spot['latitude'],
                    $spot['longitude'],
                    $spot['place_id'],
                    $spot['address'],
                    $spot['notes'],
                    $sortOrder
                );
                $incomingIds[] = $newId;
            }
        }

        // Delete spots absent from incoming
        $toDelete = array_diff($existingIds, $incomingIds);
        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $params = array_values($toDelete);
            $params[] = $tripId;
            $stmt = pdo()->prepare("DELETE FROM trip_spots WHERE id IN ({$placeholders}) AND trip_id = ?");
            $stmt->execute($params);
        }

        pdo()->commit();
    } catch (Exception $e) {
        pdo()->rollBack();
        throw $e;
    }
}
