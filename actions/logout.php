<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

verify_csrf();
logout_user();
session_start();
flash('success', '已登出。');
redirect('/index.php');
