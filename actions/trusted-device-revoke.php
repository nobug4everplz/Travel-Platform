<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trusted-devices.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

$user = require_login();
verify_csrf();

$deviceId = input_int($_POST, 'device_id');
if ($deviceId === null || !revoke_trusted_device($user, $deviceId)) {
    flash('error', '找不到可移除的信任裝置。');
    redirect(dashboard_path((string) $user['role']));
}

flash('success', '已移除信任裝置。');
redirect(dashboard_path((string) $user['role']));
