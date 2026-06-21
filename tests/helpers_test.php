<?php

declare(strict_types=1);

/**
 * Pure unit tests for lib/helpers.php
 *
 * Run: php tests/helpers_test.php
 */

require_once __DIR__ . '/../lib/helpers.php';

$pass = 0;
$fail = 0;

function test(string $label, bool|Closure $condition): void
{
    global $pass, $fail;
    $result = $condition instanceof Closure ? $condition() : $condition;
    if ($result) {
        echo "  ✅ {$label}\n";
        $pass++;
    } else {
        echo "  ❌ {$label}\n";
        $fail++;
    }
}

function section(string $name): void
{
    echo "\n─── {$name} ───\n";
}

// ============================================================
section('input_float — basic parsing');
// ============================================================

test('valid float from string', fn() => input_float(['x' => '3.14'], 'x') === 3.14);
test('valid integer as float', fn() => input_float(['x' => '42'], 'x') === 42.0);
test('negative float', fn() => input_float(['x' => '-15.5'], 'x') === -15.5);
test('null on missing key', fn() => input_float(['a' => '1'], 'x') === null);
test('null on empty string', fn() => input_float(['x' => ''], 'x') === null);
test('null on non-numeric string', fn() => input_float(['x' => 'abc'], 'x') === null);
test('null on mixed string', fn() => input_float(['x' => '12abc'], 'x') === null);
test('null on whitespace-only', fn() => input_float(['x' => '   '], 'x') === null);

// ============================================================
section('input_float — min/max range validation');
// ============================================================

// Latitude range: [-90, 90]
test('lat 0 within [-90,90]', fn() => input_float(['x' => '0'], 'x', -90, 90) === 0.0);
test('lat -90 within [-90,90]', fn() => input_float(['x' => '-90'], 'x', -90, 90) === -90.0);
test('lat 90 within [-90,90]', fn() => input_float(['x' => '90'], 'x', -90, 90) === 90.0);
test('lat -91 exceeds min', fn() => input_float(['x' => '-91'], 'x', -90, 90) === null);
test('lat 91 exceeds max', fn() => input_float(['x' => '91'], 'x', -90, 90) === null);
test('lat 45.5 within range', fn() => input_float(['x' => '45.5'], 'x', -90, 90) === 45.5);

// Longitude range: [-180, 180]
test('lng -180 within [-180,180]', fn() => input_float(['x' => '-180'], 'x', -180, 180) === -180.0);
test('lng 180 within [-180,180]', fn() => input_float(['x' => '180'], 'x', -180, 180) === 180.0);
test('lng -181 exceeds min', fn() => input_float(['x' => '-181'], 'x', -180, 180) === null);
test('lng 181 exceeds max', fn() => input_float(['x' => '181'], 'x', -180, 180) === null);

// Min-only constraint
test('min only: 5 passes >= 3', fn() => input_float(['x' => '5'], 'x', 3, null) === 5.0);
test('min only: 1 fails < 3', fn() => input_float(['x' => '1'], 'x', 3) === null);

// Max-only constraint
test('max only: 2 passes <= 10', fn() => input_float(['x' => '2'], 'x', null, 10) === 2.0);
test('max only: 15 fails > 10', fn() => input_float(['x' => '15'], 'x', max: 10) === null);

// Edge: null min/max (no constraint)
test('no constraint: -999 passes', fn() => input_float(['x' => '-999'], 'x') === -999.0);
test('no constraint: 999 passes', fn() => input_float(['x' => '999'], 'x') === 999.0);

// Invalid with range
test('invalid value with min/max returns null', fn() => input_float(['x' => 'abc'], 'x', -90, 90) === null);
test('empty value with min/max returns null', fn() => input_float(['x' => ''], 'x', -90, 90) === null);

// ============================================================
section('input_float — original behaviour preserved');
// ============================================================

test('no min/max changes existing callers', fn() => input_float(['x' => '3.14'], 'x') === 3.14);
test('empty string still returns null (no range)', fn() => input_float(['x' => ''], 'x') === null);
test('missing key still returns null (no range)', fn() => input_float(['a' => '1'], 'x') === null);

// ============================================================
section('e() — html escaping');
// ============================================================

test('plain text unchanged', fn() => e('hello') === 'hello');
test('HTML special chars escaped', fn() => e('<script>alert("xss")</script>') === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
test('null treated as empty string', fn() => e(null) === '');
test('ampersand escaped', fn() => e('a&b') === 'a&amp;b');

// ============================================================
section('trim_or_null');
// ============================================================

test('normal string trimmed', fn() => trim_or_null('  hello  ') === 'hello');
test('null returns null', fn() => trim_or_null(null) === null);
test('empty string returns null', fn() => trim_or_null('') === null);
test('whitespace returns null', fn() => trim_or_null('   ') === null);

// ============================================================
section('format_rating');
// ============================================================

test('null returns default', fn() => format_rating(null) === '尚無評分');
test('3.5 formatted as 3.5 / 5', fn() => format_rating('3.5') === '3.5 / 5');
test('0 formatted as 0.0 / 5', fn() => format_rating('0') === '0.0 / 5');

// ============================================================
section('format_date');
// ============================================================

test('null returns empty', fn() => format_date(null) === '');
test('date formatted as Y/m/d', fn() => format_date('2026-06-21') === '2026/06/21');
test('datetime trimmed to date', fn() => format_date('2026-01-05 14:30:00') === '2026/01/05');

// ============================================================
section('display_initial');
// ============================================================

test('null returns ?', fn() => display_initial(null) === '?');
test('empty returns ?', fn() => display_initial('') === '?');
test('first char of CJK', fn() => display_initial('台北市') === '台');
test('first char of ASCII', fn() => display_initial('Hello') === 'H');
test('whitespace returns ?', fn() => display_initial('   ') === '?');

// ============================================================
echo "\n─── 結果 ───\n";
echo "  通過: {$pass}\n";
echo "  失敗: {$fail}\n";
echo "\n";

exit($fail > 0 ? 1 : 0);
