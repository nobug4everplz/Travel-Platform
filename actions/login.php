<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/mail.php';
require_once __DIR__ . '/../lib/trusted-devices.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/login.php');
}

verify_csrf();

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$role = (string) ($_POST['role'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || !in_array($role, ['traveler', 'planner', 'admin'], true)) {
    flash('error', '請輸入有效的登入資料。');
    redirect('/login.php');
}

$stmt = pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== $role || !password_verify($password, $user['password'])) {
    flash('error', '帳號、密碼或角色不正確。');
    redirect('/login.php');
}

pdo()->beginTransaction();
try {
    $device = resolve_trusted_device($user);
    record_login_event($user, $device);
    pdo()->commit();
} catch (Throwable $exception) {
    if (pdo()->inTransaction()) {
        pdo()->rollBack();
    }

    throw $exception;
}

login_user($user);
flash('success', '登入成功。');

if ($device['is_new']) {
    $deviceLabel = e((string) $device['device_label']);
    $loginTime = e(date('Y/m/d H:i'));
    $ipAddress = e(client_ip_address() ?? 'Unknown');
    $html = email_shell(
        '偵測到新的登入裝置',
        '<p>你的帳號剛從新的裝置登入：<strong>' . $deviceLabel . '</strong>。</p><p>時間：' . $loginTime . '<br>IP：' . $ipAddress . '</p><p>若這不是你的操作，請立即檢查帳號安全。</p>',
        '前往工作台',
        app_url(dashboard_path((string) $user['role']))
    );
    $text = '你的帳號剛從新的裝置登入：' . $device['device_label'] . '。時間：' . date('Y/m/d H:i') . '。IP：' . (client_ip_address() ?? 'Unknown') . '。若這不是你的操作，請立即檢查帳號安全。';
    if (!send_platform_email($user, 'new_device_login', '新的登入裝置通知', $html, $text, 'new-device:' . $device['id'])) {
        flash('error', '登入成功，但裝置通知郵件暫時無法寄送。');
    }
}

redirect(dashboard_path($user['role']));
