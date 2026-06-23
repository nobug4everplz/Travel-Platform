<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Get trip IDs the user has already participated in or favorited.
 */
function get_user_interacted_trip_ids(int $userId): array
{
    $stmt = pdo()->prepare(
        'SELECT trip_id FROM trip_participations WHERE user_id = ?
         UNION
         SELECT trip_id FROM favorite_trips WHERE user_id = ?'
    );
    $stmt->execute([$userId, $userId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'trip_id'));
}

/**
 * Recommended trips for a user based on:
 *   1. Trips by planners the user follows (favorite_planners)
 *   2. Top-rated public trips the user hasn't interacted with yet
 *
 * Excludes trips the user already participated in or favorited.
 */
function recommend_trips_for_user(int $userId, int $limit = 3): array
{
    $excludedIds = get_user_interacted_trip_ids($userId);
    $results = [];

    // Phase 1: trips by planners the user follows
    $results = get_top_trips_by_followed_planners($userId, $excludedIds, $limit);
    if (count($results) >= $limit) {
        return array_slice($results, 0, $limit);
    }

    // Phase 2: fill remaining with top-rated public trips
    $remaining = $limit - count($results);
    $seenIds = array_merge(
        array_map('intval', array_column($results, 'id')),
        $excludedIds
    );

    $fill = get_top_public_trips_excluding($seenIds, $remaining);
    return array_merge($results, $fill);
}

function get_top_trips_by_followed_planners(int $userId, array $exclude, int $limit): array
{
    $sql = 'SELECT t.id, t.title, t.summary, t.average_rating, t.review_count,
                   u.name AS author_name
            FROM trips t
            JOIN users u ON u.id = t.author_id
            JOIN favorite_planners fp ON fp.planner_id = t.author_id
            WHERE t.is_published = true
              AND fp.traveler_id = ?';

    $params = [$userId];

    if (!empty($exclude)) {
        $placeholders = implode(',', array_fill(0, count($exclude), '?'));
        $sql .= " AND t.id NOT IN ({$placeholders})";
        $params = array_merge($params, $exclude);
    }

    $sql .= ' ORDER BY t.average_rating DESC, t.review_count DESC LIMIT ?';
    $params[] = $limit;

    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_top_public_trips_excluding(array $exclude, int $limit): array
{
    if ($limit < 1) {
        return [];
    }

    $sql = 'SELECT t.id, t.title, t.summary, t.average_rating, t.review_count,
                   u.name AS author_name
            FROM trips t
            JOIN users u ON u.id = t.author_id
            WHERE t.is_published = true';

    $params = [];

    if (!empty($exclude)) {
        $placeholders = implode(',', array_fill(0, count($exclude), '?'));
        $sql .= " AND t.id NOT IN ({$placeholders})";
        $params = $exclude;
    }

    $sql .= ' ORDER BY t.average_rating DESC, t.review_count DESC, t.updated_at DESC LIMIT ?';
    $params[] = $limit;

    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Suggest popular spots in a given area from existing trip spots.
 * Groups spots by name+location and returns the most frequent ones.
 */
function suggest_popular_spots(string $area, int $count = 5): array
{
    $like = '%' . addcslashes($area, '%_') . '%';

    $stmt = pdo()->prepare(
        "SELECT ts.name, ts.latitude, ts.longitude, ts.address,
                COUNT(*) AS occurrence_count,
                GROUP_CONCAT(DISTINCT t.title ORDER BY t.title ASC SEPARATOR '、') AS featured_in_trips
         FROM trip_spots ts
         JOIN trips t ON t.id = ts.trip_id
         WHERE t.is_published = true
           AND (ts.address LIKE ? OR ts.name LIKE ?)
         GROUP BY ts.name, ts.latitude, ts.longitude, ts.address
         ORDER BY occurrence_count DESC, ts.name ASC
         LIMIT ?"
    );
    $stmt->execute([$like, $like, $count]);
    return $stmt->fetchAll();
}
