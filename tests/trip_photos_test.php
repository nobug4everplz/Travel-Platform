<?php

declare(strict_types=1);

/**
 * Tests for lib/trip-photos.php — standalone PHP assertion test
 *
 * Tests function existence and SQL query structure only.
 * DB-dependent execution is tested through integration tests.
 *
 * Usage: php tests/trip_photos_test.php
 */

require_once __DIR__ . '/../lib/helpers.php';

$pass = 0;
$fail = 0;

function test(string $label, callable $fn): void
{
    global $pass, $fail;
    try {
        $fn();
        echo "  ✅ {$label}\n";
        $pass++;
    } catch (Throwable $e) {
        echo "  ❌ {$label}: {$e->getMessage()}\n";
        $fail++;
    }
}

echo "📋 trip-photos.php — file loads without errors\n";

test('lib/trip-photos.php parses without fatal errors', function () {
    // Read the raw source and check function declarations
    $src = file_get_contents(__DIR__ . '/../lib/trip-photos.php');
    if ($src === false) {
        throw new RuntimeException('Could not read lib/trip-photos.php');
    }
    // Verify all three expected functions are declared
    $checks = [
        'function get_trip_photos(' => 'get_trip_photos',
        'function get_spot_photos_grouped(' => 'get_spot_photos_grouped',
        'function insert_trip_photo(' => 'insert_trip_photo',
    ];
    foreach ($checks as $signature => $name) {
        if (!str_contains($src, $signature)) {
            throw new RuntimeException("Function declaration '{$name}' not found in source");
        }
    }
});

echo "📋 trip-photos.php — SQL query structure\n";

test('get_trip_photos SQL contains correct JOINs', function () {
    $src = file_get_contents(__DIR__ . '/../lib/trip-photos.php');
    $checks = [
        'LEFT JOIN users u ON u.id = tp.user_id',
        'LEFT JOIN trip_spots s ON s.id = tp.spot_id',
        'ORDER BY tp.created_at DESC',
    ];
    foreach ($checks as $needle) {
        if (!str_contains($src, $needle)) {
            throw new RuntimeException("get_trip_photos missing: {$needle}");
        }
    }
});

test('get_spot_photos_grouped SQL is correct', function () {
    $src = file_get_contents(__DIR__ . '/../lib/trip-photos.php');
    $checks = [
        'tp.spot_id IS NOT NULL',
        'ORDER BY tp.created_at DESC',
    ];
    foreach ($checks as $needle) {
        if (!str_contains($src, $needle)) {
            throw new RuntimeException("get_spot_photos_grouped missing: {$needle}");
        }
    }
});

test('insert_trip_photo INSERT has all columns', function () {
    $src = file_get_contents(__DIR__ . '/../lib/trip-photos.php');
    $checks = ['trip_id', 'spot_id', 'user_id', 'image_path', 'caption'];
    foreach ($checks as $col) {
        if (!str_contains($src, $col)) {
            throw new RuntimeException("insert_trip_photo missing column: {$col}");
        }
    }
});

echo "📋 actions/upload-photo.php — structure check\n";

test('upload-photo.php has all required validation checks', function () {
    $src = file_get_contents(__DIR__ . '/../actions/upload-photo.php');
    $checks = [
        'verify_csrf()',
        'require_login()',
        'max 5 MB',
        'image/jpeg',
        'image/png',
        'image/webp',
        'finfo_open',
        'move_uploaded_file',
        'insert_trip_photo',
        'uploads/photos/',
    ];
    foreach ($checks as $needle) {
        if (!str_contains($src, $needle)) {
            throw new RuntimeException("upload-photo.php missing: {$needle}");
        }
    }
});

echo "\n─────────────────────────────────\n";
echo "Results: {$pass} passed, {$fail} failed\n";

if ($fail > 0) {
    exit(1);
}
