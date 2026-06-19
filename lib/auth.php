<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('travel_platform_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;

    if (!is_int($userId)) {
        return null;
    }

    $stmt = pdo()->prepare('SELECT id, email, name, avatar_url, role, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    csrf_token();
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function require_login(): array
{
    $user = current_user();

    if (!$user) {
        flash('error', '請先登入。');
        redirect('/login.php');
    }

    return $user;
}

function require_role(array|string $roles): array
{
    $allowedRoles = is_array($roles) ? $roles : [$roles];
    $user = require_login();

    if (!in_array($user['role'], $allowedRoles, true)) {
        abort_page(403, '沒有權限', '你的角色無法查看或操作這個頁面。');
    }

    return $user;
}
