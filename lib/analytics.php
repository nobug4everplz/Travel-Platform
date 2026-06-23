<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function admin_analytics(): array
{
    $metrics = pdo()->query(
        "SELECT
            (SELECT COUNT(*) FROM users) AS user_count,
            (SELECT COUNT(*) FROM users WHERE created_at::date = CURRENT_DATE) AS registered_today,
            (SELECT COUNT(DISTINCT user_id) FROM login_events WHERE logged_in_at::date = CURRENT_DATE) AS dau,
            (SELECT COUNT(DISTINCT user_id) FROM login_events WHERE logged_in_at >= CURRENT_DATE - INTERVAL '7 days') AS wau,
            (SELECT COUNT(DISTINCT user_id) FROM login_events WHERE logged_in_at >= CURRENT_DATE - INTERVAL '30 days') AS mau,
            (SELECT COUNT(*) FROM email_delivery_logs WHERE created_at::date = CURRENT_DATE AND status = 'sent') AS emails_sent_today,
            (SELECT COUNT(*) FROM email_delivery_logs WHERE created_at::date = CURRENT_DATE AND status = 'failed') AS emails_failed_today"
    )->fetch();

    $trend = pdo()->query(
        "SELECT dates.day, COUNT(DISTINCT le.user_id) AS active_users
         FROM (
            SELECT CURRENT_DATE AS day UNION ALL SELECT CURRENT_DATE - INTERVAL '1 day'
            UNION ALL SELECT CURRENT_DATE - INTERVAL '2 days'
            UNION ALL SELECT CURRENT_DATE - INTERVAL '3 days'
            UNION ALL SELECT CURRENT_DATE - INTERVAL '4 days'
            UNION ALL SELECT CURRENT_DATE - INTERVAL '5 days'
            UNION ALL SELECT CURRENT_DATE - INTERVAL '6 days'
         ) dates
         LEFT JOIN login_events le ON le.logged_in_at::date = dates.day
         GROUP BY dates.day ORDER BY dates.day ASC"
    )->fetchAll();

    $roles = pdo()->query(
        "SELECT u.role, COUNT(DISTINCT le.user_id) AS active_users
         FROM users u
         LEFT JOIN login_events le ON le.user_id = u.id AND le.logged_in_at >= CURRENT_DATE - INTERVAL '30 days'
         GROUP BY u.role ORDER BY u.role"
    )->fetchAll();

    $devices = pdo()->query(
        "SELECT le.logged_in_at, le.ip_address, u.email, u.name, u.role
         FROM login_events le JOIN users u ON u.id = le.user_id
         WHERE le.is_new_device = true
         ORDER BY le.logged_in_at DESC LIMIT 20"
    )->fetchAll();

    return ['metrics' => $metrics, 'trend' => $trend, 'roles' => $roles, 'devices' => $devices];
}

function popular_public_trips(int $limit = 5): array
{
    $stmt = pdo()->prepare(
        'SELECT t.id, t.title, t.average_rating, t.review_count, COUNT(ft.id) AS favorite_count
         FROM trips t LEFT JOIN favorite_trips ft ON ft.trip_id = t.id
         WHERE t.is_published = 1
         GROUP BY t.id, t.title, t.average_rating, t.review_count, t.updated_at
         ORDER BY favorite_count DESC, t.average_rating IS NULL ASC, t.average_rating DESC, t.review_count DESC, t.updated_at DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function popular_planners(int $limit = 3): array
{
    $stmt = pdo()->prepare(
        'SELECT u.id, u.email, u.name, COUNT(DISTINCT fp.id) AS follower_count,
                AVG(t.average_rating) AS average_rating, COUNT(DISTINCT t.id) AS trip_count
         FROM users u
         JOIN trips t ON t.author_id = u.id AND t.is_published = 1
         LEFT JOIN favorite_planners fp ON fp.planner_id = u.id
         WHERE u.role = ?
         GROUP BY u.id, u.email, u.name, u.created_at
         ORDER BY follower_count DESC, average_rating IS NULL ASC, average_rating DESC, trip_count DESC, u.created_at ASC
         LIMIT ?'
    );
    $stmt->bindValue(1, 'planner');
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
