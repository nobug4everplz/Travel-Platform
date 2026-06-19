<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mail.php';

function require_cli(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit('Not Found');
    }
}

function cli_result(int $processed, int $sent, int $failed): never
{
    echo sprintf("Processed: %d; Sent: %d; Failed: %d\n", $processed, $sent, $failed);
    exit($failed > 0 ? 1 : 0);
}
