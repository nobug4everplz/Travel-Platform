<?php

declare(strict_types=1);

/**
 * lib/trip-photos.php — Trip photo wall queries.
 *
 * Provides functions for loading trip photos, with optional spot association.
 * All paths are relative to the web root (/uploads/photos/...).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

/**
 * Get all photos for a trip, newest first.
 *
 * Each row includes:
 *   id, trip_id, spot_id, user_id, image_path, caption, created_at,
 *   uploader_name (from users), spot_name (from trip_spots, nullable)
 */
function get_trip_photos(int $tripId): array
{
    $stmt = pdo()->prepare(
        'SELECT tp.*, u.name AS uploader_name, s.name AS spot_name
         FROM trip_photos tp
         LEFT JOIN users u ON u.id = tp.user_id
         LEFT JOIN trip_spots s ON s.id = tp.spot_id
         WHERE tp.trip_id = ?
         ORDER BY tp.created_at DESC'
    );
    $stmt->execute([$tripId]);
    return $stmt->fetchAll();
}

/**
 * Get photos associated with specific spots in a trip, keyed by spot_id.
 *
 * Returns array of [spot_id => [photo, ...], ...] so callers can
 * efficiently attach photo thumbnails to spot markers.
 */
function get_spot_photos_grouped(int $tripId): array
{
    $stmt = pdo()->prepare(
        'SELECT tp.*, u.name AS uploader_name
         FROM trip_photos tp
         LEFT JOIN users u ON u.id = tp.user_id
         WHERE tp.trip_id = ? AND tp.spot_id IS NOT NULL
         ORDER BY tp.created_at DESC'
    );
    $stmt->execute([$tripId]);
    $rows = $stmt->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $sid = (int) $row['spot_id'];
        if (!isset($grouped[$sid])) {
            $grouped[$sid] = [];
        }
        $grouped[$sid][] = $row;
    }
    return $grouped;
}

/**
 * Insert a photo record.
 *
 * Returns the new photo id.
 */
function insert_trip_photo(int $tripId, ?int $spotId, int $userId, string $imagePath, ?string $caption): int
{
    $stmt = pdo()->prepare(
        'INSERT INTO trip_photos (trip_id, spot_id, user_id, image_path, caption)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$tripId, $spotId, $userId, $imagePath, $caption]);
    return (int) pdo()->lastInsertId();
}
