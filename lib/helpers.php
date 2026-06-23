<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($messages) ? $messages : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        flash('error', '安全驗證失敗，請重新操作。');
        redirect('/index.php');
    }
}

function input_int(array $source, string $key): ?int
{
    if (!isset($source[$key])) {
        return null;
    }

    $value = filter_var($source[$key], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $value === false ? null : $value;
}

function trim_or_null(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $trimmed = trim($value);
    return $trimmed === '' ? null : $trimmed;
}

function abort_page(int $status, string $title, string $message): never
{
    http_response_code($status);
    $pageTitle = $title;
    require __DIR__ . '/../partials/header.php';
    ?>
    <section class="panel narrow">
        <p class="eyebrow">Error</p>
        <h1><?= e($title) ?></h1>
        <p class="muted"><?= e($message) ?></p>
        <a class="button primary" href="/index.php">回首頁</a>
    </section>
    <?php
    require __DIR__ . '/../partials/footer.php';
    exit;
}

function dashboard_path(string $role): string
{
    return match ($role) {
        'admin' => '/admin-dashboard.php',
        'planner' => '/planner-dashboard.php',
        default => '/traveler-dashboard.php',
    };
}

function input_float(array $source, string $key, ?float $min = null, ?float $max = null): ?float
{
    if (!isset($source[$key]) || $source[$key] === '') {
        return null;
    }

    $value = filter_var($source[$key], FILTER_VALIDATE_FLOAT);
    if ($value === false) {
        return null;
    }

    if ($min !== null && $value < $min) {
        return null;
    }

    if ($max !== null && $value > $max) {
        return null;
    }

    return $value;
}

function format_rating(?string $rating): string
{
    return $rating === null ? '尚無評分' : number_format((float) $rating, 1) . ' / 5';
}

function format_date(?string $value): string
{
    if (!$value) {
        return '';
    }

    return date('Y/m/d', strtotime($value));
}

/**
 * Render summary text supporting Markdown image syntax `![alt](url)`.
 *
 * HTML-escapes everything first (XSS protection), then converts
 * safe `![alt](https://...)` patterns to <img> tags.
 * Only http/https URLs are accepted; non-http URLs are left as text.
 */
function render_markdown_images(?string $text): string
{
    if ($text === null || $text === '') {
        return '';
    }

    $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $result = preg_replace_callback(
        '/!\\[(.*?)\\]\\((.*?)\\)/',
        function (array $m): string {
            $alt = $m[1];
            $url = $m[2];
            if (preg_match('#^https?://#i', $url)) {
                return '<img src="' . $url . '" alt="' . $alt . '" style="max-width:100%;border-radius:8px;">';
            }
            return $m[0];
        },
        $safe
    );

    return $result;
}

function display_initial(?string $value): string
{
    $text = trim($value ?? '');

    if ($text === '') {
        return '?';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, 1, 'UTF-8');
    }

    return substr($text, 0, 1);
}
