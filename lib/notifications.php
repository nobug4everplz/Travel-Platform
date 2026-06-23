<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function ensure_notification_preferences(array $user): void
{
    if (!in_array($user['role'], ['traveler', 'planner'], true)) {
        return;
    }

    $stmt = pdo()->prepare(
        'INSERT INTO notification_preferences
            (user_id, popular_digest_enabled, planner_digest_enabled, winback_enabled)
         VALUES (?, true, ?, true)
         ON CONFLICT (user_id) DO NOTHING'
    );
    $stmt->execute([(int) $user['id'], $user['role'] === 'planner' ? true : false]);
}

function get_notification_preferences(array $user): ?array
{
    if (!in_array($user['role'], ['traveler', 'planner'], true)) {
        return null;
    }

    ensure_notification_preferences($user);
    $stmt = pdo()->prepare('SELECT * FROM notification_preferences WHERE user_id = ?');
    $stmt->execute([(int) $user['id']]);

    return $stmt->fetch() ?: null;
}
