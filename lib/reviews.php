<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function recalculate_trip_rating(int $tripId): void
{
    $stmt = pdo()->prepare('SELECT COUNT(*) AS review_count, AVG(rating) AS average_rating FROM reviews WHERE trip_id = ?');
    $stmt->execute([$tripId]);
    $row = $stmt->fetch();

    $reviewCount = (int) ($row['review_count'] ?? 0);
    $averageRating = $reviewCount > 0 ? round((float) $row['average_rating'], 1) : null;

    $update = pdo()->prepare('UPDATE trips SET average_rating = ?, review_count = ?, updated_at = updated_at WHERE id = ?');
    $update->execute([$averageRating, $reviewCount, $tripId]);
}

function get_trip_reviews(int $tripId): array
{
    $stmt = pdo()->prepare(
        'SELECT r.*, u.name AS reviewer_name, u.avatar_url AS reviewer_avatar
         FROM reviews r
         JOIN users u ON u.id = r.reviewer_id
         WHERE r.trip_id = ?
         ORDER BY r.created_at DESC'
    );
    $stmt->execute([$tripId]);
    return $stmt->fetchAll();
}

function get_user_review_for_trip(int $userId, int $tripId): ?array
{
    $stmt = pdo()->prepare('SELECT * FROM reviews WHERE reviewer_id = ? AND trip_id = ? LIMIT 1');
    $stmt->execute([$userId, $tripId]);
    $review = $stmt->fetch();
    return $review ?: null;
}
