<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/mail.php';
require_once __DIR__ . '/../lib/notifications.php';
require_once __DIR__ . '/../lib/trusted-devices.php';

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
    $device = create_trusted_device($user);
    record_login_event($user, $device);
    pdo()->commit();

    login_user($user);
    flash('success', '註冊成功，已自動登入。');

    $html = email_shell(
        '歡迎加入 Travel Platform',
        '<p>你的帳號已建立完成，現在可以開始探索與規劃旅程。</p>',
        '前往工作台',
        app_url(dashboard_path($role))
    );
    $text = '歡迎加入 Travel Platform！你的帳號已建立完成，現在可以開始探索與規劃旅程。';
    if (!send_platform_email($user, 'welcome', '歡迎加入 Travel Platform', $html, $text, 'welcome:' . $userId)) {
        flash('error', '註冊成功，但歡迎郵件暫時無法寄送。');
    }

    redirect(dashboard_path($role));
} catch (PDOException $exception) {
    if (pdo()->inTransaction()) {
        pdo()->rollBack();
    }

    if ($exception->getCode() === '23000') {
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
