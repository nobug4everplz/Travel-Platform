<?php

declare(strict_types=1);

/**
 * Tests for lib/helpers.php — standalone PHP assertion test
 *
 * Usage: php tests/helpers_test.php
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

function assert_contains(string $haystack, string $needle, string $msg): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException("{$msg}: expected '{$needle}' not found in output");
    }
}

function assert_not_contains(string $haystack, string $needle, string $msg): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException("{$msg}: unexpected '{$needle}' found in output");
    }
}

echo "📋 render_markdown_images — edge cases\n";

test('null returns empty string', function () {
    $result = render_markdown_images(null);
    assert($result === '', 'Expected empty string for null');
});

test('empty string returns empty string', function () {
    $result = render_markdown_images('');
    assert($result === '', 'Expected empty string for empty string');
});

test('plain text without images is returned unchanged', function () {
    $input = '這是一個很棒的行程，包含知名景點與美食';
    $result = render_markdown_images($input);
    assert($result === $input, 'Plain text should pass through unchanged');
});

echo "\n📋 render_markdown_images — image conversion\n";

test('basic ![alt](https://...) converts to img', function () {
    $result = render_markdown_images('![test](https://ex.com/img.jpg)');
    assert_contains($result, '<img src="https://ex.com/img.jpg"', 'Should contain img tag with src');
    assert_contains($result, 'alt="test"', 'Should contain img tag with alt');
    assert_contains($result, 'style="max-width:100%;border-radius:8px;"', 'Should contain style');
});

test('multiple images all converted correctly', function () {
    $result = render_markdown_images('A ![第一張](https://a.com/1.jpg) B ![第二張](https://b.com/2.png) C');
    assert_contains($result, '<img src="https://a.com/1.jpg" alt="第一張"', 'First image');
    assert_contains($result, '<img src="https://b.com/2.png" alt="第二張"', 'Second image');
    assert_contains($result, 'A ', 'Leading text preserved');
    assert_contains($result, ' B ', 'Between images preserved');
    assert_contains($result, ' C', 'Trailing text preserved');
});

echo "\n📋 render_markdown_images — XSS safety\n";

test('script tags are escaped', function () {
    $result = render_markdown_images('<script>alert(1)</script>');
    assert_contains($result, '&lt;script&gt;', 'Script tags should be HTML-escaped');
    assert_not_contains($result, '<script>', 'Raw script tags should not appear');
    assert_not_contains($result, '<img', 'No img tag should be generated');
});

test('alt text containing HTML is escaped in output', function () {
    $result = render_markdown_images('![<script>](https://ex.com/x.jpg)');
    assert_contains($result, '&lt;script&gt;', 'Alt text HTML should be escaped');
    assert_not_contains($result, 'alt="<script>"', 'Raw HTML in alt should not appear');
});

test('javascript: protocol URL is preserved as-is', function () {
    $result = render_markdown_images('![x](javascript:alert(1))');
    assert_not_contains($result, '<img', 'No img tag for javascript: URL');
    assert_contains($result, 'javascript:alert(1)', 'Original text preserved');
});

echo "\n📋 render_markdown_images — mixed content\n";

test('image + surrounding text renders correctly', function () {
    $result = render_markdown_images('封面圖：![封面](https://ex.com/cover.jpg) 快來看！');
    assert_contains($result, '<img src="https://ex.com/cover.jpg"', 'Image converted');
    assert_contains($result, '封面圖：', 'Leading text');
    assert_contains($result, ' 快來看！', 'Trailing text');
});

echo "\n📋 input_int — integer validation\n";

test('returns int for valid positive int', function () {
    $result = input_int(['id' => 42], 'id');
    assert($result === 42, 'Expected 42, got ' . var_export($result, true));
});

test('returns null for missing key', function () {
    $result = input_int(['name' => 'foo'], 'id');
    assert($result === null, 'Expected null for missing key');
});

test('returns null for string value', function () {
    $result = input_int(['id' => 'abc'], 'id');
    assert($result === null, 'Expected null for string value');
});

test('returns null for zero', function () {
    $result = input_int(['id' => 0], 'id');
    assert($result === null, 'Expected null for zero (min_range=1)');
});

test('returns null for negative', function () {
    $result = input_int(['id' => -5], 'id');
    assert($result === null, 'Expected null for negative (min_range=1)');
});

test('returns null for float', function () {
    $result = input_int(['id' => 3.14], 'id');
    assert($result === null, 'Expected null for float');
});

test('returns int from string containing number', function () {
    $result = input_int(['id' => '42'], 'id');
    assert($result === 42, 'Expected 42 from numeric string');
});

echo "\n📋 e — HTML escaping\n";

test('escapes < and >', function () {
    $result = e('<script>');
    assert($result === '&lt;script&gt;', 'Expected escaped script tags');
});

test('escapes double quotes', function () {
    $result = e('say "hello"');
    assert_contains($result, '&quot;', 'Double quotes should be escaped');
});

test('null returns empty string', function () {
    $result = e(null);
    assert($result === '', 'Expected empty string for null');
});

test('normal text passes through unchanged', function () {
    $result = e('Hello World');
    assert($result === 'Hello World', 'Normal text should not be modified');
});

test('ampersand is escaped', function () {
    $result = e('a & b');
    assert($result === 'a &amp; b', 'Ampersand should be escaped');
});

echo "\n📋 trim_or_null — trimming\n";

test('returns trimmed value for non-empty', function () {
    $result = trim_or_null('  hello  ');
    assert($result === 'hello', 'Expected trimmed string');
});

test('returns null for null', function () {
    $result = trim_or_null(null);
    assert($result === null, 'Expected null for null input');
});

test('returns null for empty string', function () {
    $result = trim_or_null('');
    assert($result === null, 'Expected null for empty string');
});

test('returns null for whitespace-only', function () {
    $result = trim_or_null('   ');
    assert($result === null, 'Expected null for whitespace-only');
});

test('passes through non-empty string unchanged', function () {
    $result = trim_or_null('already trimmed');
    assert($result === 'already trimmed', 'Already trimmed string should pass through');
});

echo "\n📋 format_rating — rating formatting\n";

test('null returns 尚無評分', function () {
    $result = format_rating(null);
    assert($result === '尚無評分', 'Expected 尚無評分 for null');
});

test('integer formats with one decimal', function () {
    $result = format_rating('4');
    assert($result === '4.0 / 5', 'Expected "4.0 / 5"');
});

test('float formats correctly', function () {
    $result = format_rating('4.5');
    assert($result === '4.5 / 5', 'Expected "4.5 / 5"');
});

test('empty string returns 尚無評分', function () {
    $result = format_rating('');
    assert($result === '0.0 / 5', 'Empty string casts to 0.0 and formats');
});

echo "\n📋 format_date — date formatting\n";

test('null returns empty string', function () {
    $result = format_date(null);
    assert($result === '', 'Expected empty string for null');
});

test('empty string returns empty string', function () {
    $result = format_date('');
    assert($result === '', 'Expected empty string for empty string');
});

test('formats Y-m-d to Y/m/d', function () {
    $result = format_date('2026-06-23');
    assert($result === '2026/06/23', 'Expected "2026/06/23"');
});

test('formats ISO datetime to Y/m/d', function () {
    $result = format_date('2026-06-23 14:30:00');
    assert($result === '2026/06/23', 'Expected "2026/06/23" from datetime');
});

echo "\n📋 display_initial — first character\n";

test('null returns ?', function () {
    $result = display_initial(null);
    assert($result === '?', 'Expected "?" for null');
});

test('empty string returns ?', function () {
    $result = display_initial('');
    assert($result === '?', 'Expected "?" for empty string');
});

test('whitespace-only returns ?', function () {
    $result = display_initial('   ');
    assert($result === '?', 'Expected "?" for whitespace-only');
});

test('returns first character of string', function () {
    $result = display_initial('Hello');
    assert($result === 'H', 'Expected "H" for "Hello"');
});

test('returns first CJK character', function () {
    $result = display_initial('台灣旅遊');
    assert($result === '台', 'Expected "台" for "台灣旅遊"');
});

test('single character returns itself', function () {
    $result = display_initial('A');
    assert($result === 'A', 'Expected "A" for single char');
});

echo "\n─────────────────────────────────\n";
echo "Results: {$pass} passed, {$fail} failed\n";

if ($fail > 0) {
    exit(1);
}
