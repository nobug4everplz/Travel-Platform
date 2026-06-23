<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * 取得已參加此行程的所有 traveler（排除 planner 本人）
 */
function get_trip_participants(int $tripId): array
{
    $stmt = pdo()->prepare(
        'SELECT u.id, u.name, u.avatar_url, p.joined_at
         FROM trip_participations p
         JOIN users u ON u.id = p.user_id AND u.role = \'traveler\'
         WHERE p.trip_id = ?
         ORDER BY p.joined_at ASC'
    );
    $stmt->execute([$tripId]);
    return $stmt->fetchAll();
}
