<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/reviews.php';

$db = pdo();

$db->beginTransaction();
$db->exec('SET CONSTRAINTS ALL DEFERRED');
foreach (['email_delivery_logs', 'trip_daily_unique_views', 'notification_preferences', 'login_events', 'trusted_devices', 'reviews', 'favorite_planners', 'favorite_trips', 'trip_participations', 'trips', 'users'] as $table) {
    $db->exec("TRUNCATE TABLE {$table}");
}
$db->commit();

$password = password_hash('password123', PASSWORD_DEFAULT);

$users = [
    ['admin@example.com', $password, '平台管理員', null, 'admin'],
    ['traveler@example.com', $password, '旅人 Jason', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=400&auto=format&fit=crop', 'traveler'],
    ['planner@example.com', $password, 'Sarah 城市規劃師', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=400&auto=format&fit=crop', 'planner'],
    ['planner2@example.com', $password, 'Leo 自然旅遊顧問', 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?q=80&w=400&auto=format&fit=crop', 'planner'],
];

$insertUser = $db->prepare('INSERT INTO users (email, password, name, avatar_url, role) VALUES (?, ?, ?, ?, ?)');
$userIds = [];
foreach ($users as $user) {
    $insertUser->execute($user);
    $userIds[$user[0]] = (int) $db->lastInsertId();
}

$insertPreference = $db->prepare(
    'INSERT INTO notification_preferences (user_id, popular_digest_enabled, planner_digest_enabled, winback_enabled)
     VALUES (?, ?, ?, ?)'
);
$insertPreference->execute([$userIds['traveler@example.com'], 1, 0, 1]);
$insertPreference->execute([$userIds['planner@example.com'], 1, 1, 1]);
$insertPreference->execute([$userIds['planner2@example.com'], 1, 1, 1]);

$deviceStmt = $db->prepare(
    'INSERT INTO trusted_devices
        (user_id, token_hash, device_label, user_agent, first_ip, last_ip, first_seen_at, last_seen_at, expires_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$deviceStmt->execute([
    $userIds['traveler@example.com'],
    hash('sha256', 'seed-traveler-device'),
    'Chrome on Windows',
    'Seed Browser',
    '192.168.1.20',
    '192.168.1.20',
    date('Y-m-d H:i:s', strtotime('-4 days')),
    date('Y-m-d H:i:s', strtotime('-1 day')),
    date('Y-m-d H:i:s', strtotime('+26 days')),
]);
$travelerDeviceId = (int) $db->lastInsertId();

$loginStmt = $db->prepare(
    'INSERT INTO login_events (user_id, logged_in_at, ip_address, user_agent, trusted_device_id, is_new_device)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$loginStmt->execute([$userIds['traveler@example.com'], date('Y-m-d H:i:s', strtotime('-4 days')), '192.168.1.20', 'Seed Browser', $travelerDeviceId, true]);
$loginStmt->execute([$userIds['traveler@example.com'], date('Y-m-d H:i:s', strtotime('-1 day')), '192.168.1.20', 'Seed Browser', $travelerDeviceId, false]);
$loginStmt->execute([$userIds['planner@example.com'], date('Y-m-d H:i:s', strtotime('-2 days')), '192.168.1.30', 'Seed Browser', null, true]);
$loginStmt->execute([$userIds['planner@example.com'], date('Y-m-d H:i:s'), '192.168.1.30', 'Seed Browser', null, false]);
$loginStmt->execute([$userIds['admin@example.com'], date('Y-m-d H:i:s'), '127.0.0.1', 'Seed Browser', null, true]);

$trips = [
    ['京都深度五日慢旅', 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?q=80&w=1200&auto=format&fit=crop', '走訪老街、茶道體驗與私房庭園，適合第一次想慢慢認識京都的旅人。', 1, $userIds['planner@example.com']],
    ['峇里島 Villa 放鬆週末', 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?q=80&w=1200&auto=format&fit=crop', '入住海邊 Villa，安排 Spa、瑜伽與夕陽晚餐，讓短假也能完整充電。', 1, $userIds['planner@example.com']],
    ['紐約藝文散步草稿', 'https://images.unsplash.com/photo-1496442226666-8d4d0e62e6e9?q=80&w=1200&auto=format&fit=crop', '草稿行程，規劃美術館、爵士酒吧與街區散步路線。', 0, $userIds['planner@example.com']],
    ['冰島極光自然 10 日', 'https://images.unsplash.com/photo-1521127264627-72ce893e32b4?q=80&w=1200&auto=format&fit=crop', '自駕環島、冰川健行與極光觀測，適合喜歡自然景觀與冒險的旅人。', 1, $userIds['planner2@example.com']],
    ['瑞士湖區鐵道假期', 'https://images.unsplash.com/photo-1530122037265-a5f1f91d3b99?q=80&w=1200&auto=format&fit=crop', '用火車串連湖泊、山城與觀景步道，節奏舒適，適合家庭與初次歐洲旅行。', 1, $userIds['planner2@example.com']],
];

$insertTrip = $db->prepare('INSERT INTO trips (title, cover_image, summary, is_published, author_id) VALUES (?, ?, ?, ?, ?)');
$tripIds = [];
foreach ($trips as $trip) {
    $insertTrip->execute($trip);
    $tripIds[] = (int) $db->lastInsertId();
}

$travelerId = $userIds['traveler@example.com'];
$kyotoTripId = $tripIds[0];
$baliTripId = $tripIds[1];
$icelandTripId = $tripIds[3];

$db->prepare('INSERT INTO trip_participations (user_id, trip_id, status) VALUES (?, ?, ?)')->execute([$travelerId, $kyotoTripId, 'active']);
$db->prepare('INSERT INTO trip_participations (user_id, trip_id, status) VALUES (?, ?, ?)')->execute([$travelerId, $icelandTripId, 'active']);
$db->prepare('INSERT INTO favorite_trips (user_id, trip_id) VALUES (?, ?)')->execute([$travelerId, $baliTripId]);
$db->prepare('INSERT INTO favorite_trips (user_id, trip_id) VALUES (?, ?)')->execute([$userIds['planner@example.com'], $icelandTripId]);
$db->prepare('INSERT INTO favorite_planners (traveler_id, planner_id) VALUES (?, ?)')->execute([$travelerId, $userIds['planner@example.com']]);

$insertReview = $db->prepare('INSERT INTO reviews (reviewer_id, trip_id, rating, comment) VALUES (?, ?, ?, ?)');
$insertReview->execute([$travelerId, $kyotoTripId, 5, '節奏安排很好，導覽與餐廳都很有記憶點，適合第一次去京都。']);
$insertReview->execute([$travelerId, $icelandTripId, 4, '自然景觀非常震撼，行程稍微緊湊，但規劃師回覆很清楚。']);

recalculate_trip_rating($kyotoTripId);
recalculate_trip_rating($icelandTripId);

$viewStmt = $db->prepare(
    'INSERT INTO trip_daily_unique_views (trip_id, view_date, viewer_key_hash, first_viewed_at)
     VALUES (?, ?, ?, ?)'
);
foreach ([6, 5, 4, 3, 2, 1, 0] as $daysAgo) {
    $date = date('Y-m-d', strtotime("-{$daysAgo} days"));
    $views = 1 + (6 - $daysAgo);
    for ($index = 1; $index <= $views; $index++) {
        $viewStmt->execute([
            $kyotoTripId,
            $date,
            hash('sha256', "seed-view-{$date}-{$index}"),
            $date . ' 10:00:00',
        ]);
    }
}

echo "Seed completed.\n";
echo "Accounts:\n";
echo "- admin@example.com / password123 / admin\n";
echo "- traveler@example.com / password123 / traveler\n";
echo "- planner@example.com / password123 / planner\n";
echo "- planner2@example.com / password123 / planner\n";
