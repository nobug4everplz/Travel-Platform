<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

$existingUser = current_user();
if ($existingUser) {
    redirect(dashboard_path($existingUser['role']));
}

$pageTitle = '登入';
require __DIR__ . '/../partials/header.php';
?>
<section class="panel narrow">
    <p class="eyebrow">Login</p>
    <h1>登入你的帳號</h1>
    <p class="muted">選擇正確角色後登入，系統會導向對應的工作台。</p>
    <form method="post" action="/actions/login.php" class="form-grid">
        <?= csrf_field() ?>
        <label>Email
            <input type="email" name="email" required autocomplete="email">
        </label>
        <label>密碼
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <label>角色
            <select name="role" required>
                <option value="traveler">旅人</option>
                <option value="planner">規劃師</option>
                <option value="admin">管理員</option>
            </select>
        </label>
        <button class="primary" type="submit">登入</button>
    </form>
    <p class="auth-note">還沒有帳號？<a href="/register.php">建立新帳號</a></p>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
