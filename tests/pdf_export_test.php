<?php

declare(strict_types=1);

/**
 * Tests for lib/pdf-export.php — stand-alone PHP assertion test
 *
 * Usage: php tests/pdf_export_test.php
 */

require_once __DIR__ . '/../lib/pdf-export.php';

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

function assert_contains_cjk(string $pdf, string $cjkText, string $msg): void
{
    $hex = strtoupper(bin2hex(mb_convert_encoding($cjkText, 'UTF-16BE', 'UTF-8')));
    if (str_contains($pdf, $hex)) {
        return;
    }
    throw new RuntimeException("{$msg}: expected to find CJK hex '{$hex}' in PDF");
}

function assert_starts_with(string $haystack, string $prefix, string $msg): void
{
    if (str_starts_with($haystack, $prefix)) {
        return;
    }
    throw new RuntimeException("{$msg}: expected to start with '{$prefix}', got '" . substr($haystack, 0, 50) . "'");
}

// ── Test 1: local cover image path ──
test('local cover image renders in PDF', function () {
    // Create a minimal 1x1 PNG
    $pngData = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    );
    $tmpPng = tempnam(sys_get_temp_dir(), 'test_cover_') . '.png';
    file_put_contents($tmpPng, $pngData);

    $trip = [
        'title' => '測試行程',
        'author_name' => '測試作者',
        'cover_image' => $tmpPng,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-05',
        'latitude' => 25.0330,
        'longitude' => 121.5654,
    ];

    $pdf = generate_trip_pdf($trip, [], null, null);
    assert_starts_with($pdf, '%PDF-', 'PDF header present');
    assert_contains_cjk($pdf, '測試行程', 'CJK title in PDF');

    @unlink($tmpPng);
});

// ── Test 2: remote URL cover image ──
test('remote URL cover image downloads and renders', function () {
    $trip = [
        'title' => 'URL封面測試',
        'author_name' => '測試作者',
        'cover_image' => 'https://upload.wikimedia.org/wikipedia/en/a/a9/Example.jpg',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-05',
        'latitude' => 25.0330,
        'longitude' => 121.5654,
    ];

    $pdf = generate_trip_pdf($trip, [], null, null);
    assert_starts_with($pdf, '%PDF-', 'PDF header present');
    assert_contains_cjk($pdf, 'URL封面測試', 'CJK title in PDF');
});

// ── Test 3: empty cover image (no image at all) ──
test('empty cover image still generates valid PDF', function () {
    $trip = [
        'title' => '無封面測試',
        'author_name' => '測試作者',
        'cover_image' => '',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-05',
    ];

    $pdf = generate_trip_pdf($trip, [], null, null);
    assert_starts_with($pdf, '%PDF-', 'PDF header present');
    assert_contains_cjk($pdf, '無封面測試', 'CJK title in PDF');
});

// ── Test 4: broken URL gracefully handled ──
test('broken cover URL does not crash', function () {
    $trip = [
        'title' => '壞掉URL測試',
        'author_name' => '作者',
        'cover_image' => 'https://nonexistent.example.com/404.jpg',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-05',
    ];

    $pdf = generate_trip_pdf($trip, [], null, null);
    assert_starts_with($pdf, '%PDF-', 'PDF header present');
    assert_contains_cjk($pdf, '壞掉URL測試', 'CJK title in PDF');
});

echo "\n── Results ──\n";
echo "  Pass: {$pass}\n";
echo "  Fail: {$fail}\n";
echo ($fail === 0 ? "  ✅ All passed!\n" : "  ❌ Some tests failed!\n");
exit($fail === 0 ? 0 : 1);
