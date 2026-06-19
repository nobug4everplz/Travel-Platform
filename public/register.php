<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

$existingUser = current_user();
if ($existingUser) {
    redirect(dashboard_path($existingUser['role']));
}

$pageTitle = '註冊';
require __DIR__ . '/../partials/header.php';
?>
<section class="panel narrow">
    <p class="eyebrow">Register</p>
    <h1>建立你的旅遊帳號</h1>
    <p class="muted">旅人可以參加、收藏與評論行程；規劃師可以建立草稿並發布行程。</p>
    <form method="post" action="/actions/register.php" class="form-grid">
        <?= csrf_field() ?>
        <label>顯示名稱
            <input type="text" name="name" maxlength="120" placeholder="你的名稱">
        </label>
        <label>Email
            <input type="email" name="email" required autocomplete="email">
        </label>
        <label>密碼
            <input type="password" name="password" required minlength="6" autocomplete="new-password">
        </label>
        <label>角色
            <select name="role" required>
                <option value="traveler">旅人</option>
                <option value="planner">規劃師</option>
            </select>
        </label>
        <button class="primary" type="submit">建立帳號</button>
    </form>
    <p class="auth-note">已經有帳號？<a href="/login.php">前往登入</a></p>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
