<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

verify_csrf();
$user = require_login();

$name = trim_or_null($_POST['name'] ?? null);
$avatarUrl = trim_or_null($_POST['avatar_url'] ?? null);

$stmt = pdo()->prepare('UPDATE users SET name = ?, avatar_url = ? WHERE id = ?');
$stmt->execute([$name, $avatarUrl, $user['id']]);

flash('success', '個人資料已更新。');
redirect(dashboard_path($user['role']));
