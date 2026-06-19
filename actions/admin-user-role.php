<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin-dashboard.php');
}

verify_csrf();
$admin = require_role('admin');

$userId = input_int($_POST, 'user_id');
$role = (string) ($_POST['role'] ?? '');

if ($userId === null || !in_array($role, ['traveler', 'planner', 'admin'], true)) {
    flash('error', '請提供有效的使用者與角色。');
    redirect('/admin-dashboard.php');
}

if ($userId === (int) $admin['id'] && $role !== 'admin') {
    flash('error', '不能移除自己的管理員角色。');
    redirect('/admin-dashboard.php');
}

$stmt = pdo()->prepare('UPDATE users SET role = ? WHERE id = ?');
$stmt->execute([$role, $userId]);

flash('success', '使用者角色已更新。');
redirect('/admin-dashboard.php');
