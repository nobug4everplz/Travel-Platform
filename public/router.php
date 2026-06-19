<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$publicFile = __DIR__ . $path;

if ($path !== '/' && is_file($publicFile)) {
    return false;
}

if (str_starts_with($path, '/actions/')) {
    $action = basename($path);
    $actionFile = __DIR__ . '/../actions/' . $action;

    if (is_file($actionFile)) {
        require $actionFile;
        return true;
    }
}

if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
echo '404 Not Found';
return true;
