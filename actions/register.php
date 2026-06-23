<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/mail.php';
require_once __DIR__ . '/../lib/notifications.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/register.php');
}

verify_csrf();

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$name = trim_or_null($_POST['name'] ?? null);
$role = (string) ($_POST['role'] ?? 'traveler');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', '請填寫有效的 Email。');
    redirect('/register.php');
}

if (strlen($password) < 6) {
    flash('error', '密碼至少需要 6 個字元。');
    redirect('/register.php');
}

if (!in_array($role, ['traveler', 'planner'], true)) {
    flash('error', '註冊角色只能是旅人或規劃師。');
    redirect('/register.php');
}

try {
    pdo()->beginTransaction();
    $stmt = pdo()->prepare('INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name, $role]);

    $userId = (int) pdo()->lastInsertId();
    $userStmt = pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    ensure_notification_preferences($user);
    pdo()->commit();

    flash('success', '註冊成功，請登入。');

    redirect('/login.php');
} catch (PDOException $exception) {
    if (pdo()->inTransaction()) {
        pdo()->rollBack();
    }

    if (in_array($exception->getCode(), ['23505', '23000'], true)) {
        flash('error', '這個 Email 已被註冊。');
        redirect('/register.php');
    }

    throw $exception;
} catch (Throwable $exception) {
    if (pdo()->inTransaction()) {
        pdo()->rollBack();
    }

    throw $exception;
}
