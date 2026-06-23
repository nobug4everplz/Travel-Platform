<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function get_public_trips(?string $query = null, int $limit = 12): array
{
    $params = [];
    $where = 't.is_published = 1';

    if ($query !== null && trim($query) !== '') {
        $like = '%' . trim($query) . '%';
        $where .= ' AND (t.title LIKE ? OR t.summary LIKE ? OR u.name LIKE ? OR t.address LIKE ? OR ts.name LIKE ?)';
        $params = [$like, $like, $like, $like, $like];
    }

    $sql = "SELECT DISTINCT t.*, u.id AS author_id, u.email AS author_email, u.name AS author_name, u.avatar_url AS author_avatar
            FROM trips t
            JOIN users u ON u.id = t.author_id
            LEFT JOIN trip_spots ts ON ts.trip_id = t.id
            WHERE {$where}
            ORDER BY t.updated_at DESC
            LIMIT {$limit}";
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_public_planners(?string $query = null, int $limit = 8): array
{
    $params = [];
    $where = "u.role = 'planner'";

    if ($query !== null && trim($query) !== '') {
        $like = '%' . trim($query) . '%';
        $where .= ' AND (u.name LIKE ? OR EXISTS (
            SELECT 1 FROM trips st
            WHERE st.author_id = u.id AND st.is_published = 1 AND (st.title LIKE ? OR st.summary LIKE ?)
        ))';
        $params = [$like, $like, $like];
    }

    $sql = "SELECT u.id, u.email, u.name, u.avatar_url, u.created_at,
                   COUNT(t.id) AS published_trip_count
            FROM users u
            LEFT JOIN trips t ON t.author_id = u.id AND t.is_published = 1
             WHERE {$where}
             GROUP BY u.id, u.email, u.name, u.avatar_url, u.created_at
             ORDER BY u.created_at DESC
             LIMIT {$limit}";
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function find_trip(int $tripId): ?array
{
    $stmt = pdo()->prepare(
        'SELECT t.*, u.name AS author_name, u.email AS author_email, u.avatar_url AS author_avatar, u.role AS author_role
         FROM trips t
         JOIN users u ON u.id = t.author_id
         WHERE t.id = ?
         LIMIT 1'
    );
    $stmt->execute([$tripId]);
    $trip = $stmt->fetch();
    return $trip ?: null;
}

function find_visible_trip(int $tripId, ?array $user): ?array
{
    $trip = find_trip($tripId);

    if (!$trip) {
        return null;
    }

    if ((int) $trip['is_published'] === 1) {
        return $trip;
    }

    if ($user && ($user['role'] === 'admin' || (int) $trip['author_id'] === (int) $user['id'])) {
        return $trip;
    }

    return null;
}

function get_planner(int $plannerId): ?array
{
    $stmt = pdo()->prepare(
        "SELECT u.id, u.email, u.name, u.avatar_url, u.created_at,
                COUNT(t.id) AS published_trip_count
         FROM users u
         LEFT JOIN trips t ON t.author_id = u.id AND t.is_published = 1
         WHERE u.id = ? AND u.role = 'planner'
         GROUP BY u.id, u.email, u.name, u.avatar_url, u.created_at
         LIMIT 1"
    );
    $stmt->execute([$plannerId]);
    $planner = $stmt->fetch();
    return $planner ?: null;
}

function get_planner_public_trips(int $plannerId): array
{
    $stmt = pdo()->prepare(
        'SELECT * FROM trips
         WHERE author_id = ? AND is_published = 1
         ORDER BY updated_at DESC'
    );
    $stmt->execute([$plannerId]);
    return $stmt->fetchAll();
}

function user_has_participation(int $userId, int $tripId): bool
{
    $stmt = pdo()->prepare('SELECT id FROM trip_participations WHERE user_id = ? AND trip_id = ? LIMIT 1');
    $stmt->execute([$userId, $tripId]);
    return (bool) $stmt->fetch();
}

function user_favorited_trip(int $userId, int $tripId): bool
{
    $stmt = pdo()->prepare('SELECT id FROM favorite_trips WHERE user_id = ? AND trip_id = ? LIMIT 1');
    $stmt->execute([$userId, $tripId]);
    return (bool) $stmt->fetch();
}

function traveler_favorited_planner(int $travelerId, int $plannerId): bool
{
    $stmt = pdo()->prepare('SELECT id FROM favorite_planners WHERE traveler_id = ? AND planner_id = ? LIMIT 1');
    $stmt->execute([$travelerId, $plannerId]);
    return (bool) $stmt->fetch();
}
