<?php

require_once __DIR__ . '/../lib/auth.php';

$currentUser = current_user();
$pageTitle = $pageTitle ?? 'Travel Platform';
$flashes = consume_flashes();
$pageType = $pageType ?? '';
$bodyDataAttrs = $bodyDataAttrs ?? '';
if ($pageType !== '') {
    $bodyDataAttrs = 'data-page-type="' . e($pageType) . '" ' . $bodyDataAttrs;
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Travel Platform</title>
    <link rel="stylesheet" href="/assets/app.css">
    <?php if (!empty($loadMap)): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js" defer></script>
    <script src="/assets/map-utils.js" defer></script>
    <?php endif; ?>
</head>
<body <?= $bodyDataAttrs ?>>
<header class="site-header">
    <a class="brand" href="/index.php" aria-label="Travel Platform 首頁">
        <span class="brand-mark">TP</span>
        <span class="brand-text">Travel Platform</span>
    </a>
    <nav class="nav" aria-label="主要導覽">
        <a href="/index.php">探索行程</a>
        <?php if ($currentUser): ?>
            <a href="<?= e(dashboard_path($currentUser['role'])) ?>">我的工作台</a>
            <?php if ($currentUser['role'] === 'planner'): ?>
                <a href="/editor.php">新增行程</a>
            <?php endif; ?>
            <span class="user-name"><?= e($currentUser['name']) ?></span>
            <form method="post" action="/actions/logout.php" class="inline-form">
                <?= csrf_field() ?>
                <button class="link-button" type="submit">登出</button>
            </form>
        <?php else: ?>
            <a href="/login.php">登入</a>
            <a class="button small primary" href="/register.php">註冊</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
    <?php foreach ($flashes as $flash): ?>
        <div class="flash <?= e($flash['type'] ?? 'info') ?>"><?= e($flash['message'] ?? '') ?></div>
    <?php endforeach; ?>
