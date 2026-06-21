<?php

declare(strict_types=1);

/**
 * Pure unit tests for lib/ai-context.php
 *
 * Run: php tests/ai_context_test.php
 */

require_once __DIR__ . '/../lib/ai-context.php';

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

function assert_contains(string $haystack, string $needle): bool
{
    return str_contains($haystack, $needle);
}

function assert_not_contains(string $haystack, string $needle): bool
{
    return !str_contains($haystack, $needle);
}

// ============================================================
section('pageType — home');
// ============================================================

$prompt = build_system_prompt('home', null, null);
test('home contains 行伴', fn() => assert_contains($prompt, '行伴'));
test('home contains 首頁', fn() => assert_contains($prompt, '首頁'));
test('home contains 探索', fn() => assert_contains($prompt, '探索'));
test('home contains 限制', fn() => assert_contains($prompt, '限制'));
test('home contains 訪客 (null user default)', fn() => assert_contains($prompt, '訪客'));
test('home contains 親愛的使用者 (null user name fallback)', fn() => assert_contains($prompt, '親愛的使用者'));

// ============================================================
section('pageType — trip');
// ============================================================

$prompt = build_system_prompt('trip', null, null);
test('trip without data contains 行程頁面', fn() => assert_contains($prompt, '行程頁面'));
test('trip without data contains 深入', fn() => assert_contains($prompt, '深入'));

// ============================================================
section('pageType — editor');
// ============================================================

$prompt = build_system_prompt('editor', null, null);
test('editor without data contains 編輯器', fn() => assert_contains($prompt, '編輯器'));
test('editor without data contains 撰寫', fn() => assert_contains($prompt, '撰寫'));

// ============================================================
section('pageType — planner_dashboard');
// ============================================================

$prompt = build_system_prompt('planner_dashboard', null, null);
test('planner_dashboard contains 規劃師工作台', fn() => assert_contains($prompt, '規劃師工作台'));
test('planner_dashboard contains 分析', fn() => assert_contains($prompt, '分析'));

// ============================================================
section('pageType — traveler_dashboard');
// ============================================================

$prompt = build_system_prompt('traveler_dashboard', null, null);
test('traveler_dashboard contains 旅人工作台', fn() => assert_contains($prompt, '旅人工作台'));
test('traveler_dashboard contains 足跡', fn() => assert_contains($prompt, '足跡'));

// ============================================================
section('pageType — invalid falls back to default');
// ============================================================

$prompt = build_system_prompt('invalid_page_xyz', null, null);
test('invalid pageType still contains 行伴', fn() => assert_contains($prompt, '行伴'));
test('invalid pageType does not contain 規劃師工作台', fn() => assert_not_contains($prompt, '規劃師工作台'));
test('invalid pageType does not contain 行程頁面', fn() => assert_not_contains($prompt, '行程頁面'));

// ============================================================
section('$user — nullable array');
// ============================================================

$prompt = build_system_prompt('home', null, null);
test('null $user does not crash', fn() => $prompt !== '');
test('null $user defaults to 訪客', fn() => assert_contains($prompt, '訪客'));

$prompt = build_system_prompt('home', ['role' => 'planner', 'name' => '小明'], null);
test('planner role shows 規劃師', fn() => assert_contains($prompt, '規劃師'));
test('planner user name shows 小明', fn() => assert_contains($prompt, '小明'));

$prompt = build_system_prompt('home', ['role' => 'traveler', 'name' => ''], null);
test('traveler with empty name falls back to email', fn() => assert_contains($prompt, '使用者'));
test('traveler role shows 旅人', fn() => assert_contains($prompt, '旅人'));

// ============================================================
section('tripData — trip page with full data');
// ============================================================

$tripData = [
    'title'   => '台北三日遊',
    'summary' => '帶著相機探索台北',
    'spots'   => [
        ['name' => '101 大樓'],
        ['name' => '故宮博物院'],
        ['name' => '西門町'],
    ],
];
$prompt = build_system_prompt('trip', ['role' => 'traveler', 'name' => '測試'], $tripData);
test('trip data shows delimiter start', fn() => assert_contains($prompt, '以下為行程資料'));
test('trip data shows delimiter end', fn() => assert_contains($prompt, '行程資料結束'));
test('trip data shows title', fn() => assert_contains($prompt, '台北三日遊'));
test('trip data shows summary', fn() => assert_contains($prompt, '帶著相機探索台北'));
test('trip data shows spot name 101', fn() => assert_contains($prompt, '101 大樓'));
test('trip data shows spot name 故宮', fn() => assert_contains($prompt, '故宮博物院'));

// ============================================================
section('tripData — editor page with full data');
// ============================================================

$prompt = build_system_prompt('editor', null, $tripData);
test('editor data shows delimiter start', fn() => assert_contains($prompt, '以下為行程資料'));
test('editor data shows delimiter end', fn() => assert_contains($prompt, '行程資料結束'));
test('editor data shows title', fn() => assert_contains($prompt, '台北三日遊'));
test('editor data labels as 已填寫摘要', fn() => assert_contains($prompt, '已填寫摘要'));

// ============================================================
section('tripData — empty spots array');
// ============================================================

$noSpots = ['title' => '測試', 'summary' => '無景點', 'spots' => []];
$prompt = build_system_prompt('trip', null, $noSpots);
test('empty spots does not crash', fn() => assert_contains($prompt, '測試'));
test('empty spots does not list 包含景點', fn() => assert_not_contains($prompt, '包含景點'));

// ============================================================
section('tripData — null spots key');
// ============================================================

$nullSpots = ['title' => '測試', 'summary' => '無', 'spots' => null];
$prompt = build_system_prompt('trip', null, $nullSpots);
test('null spots does not crash', fn() => assert_contains($prompt, '測試'));

// ============================================================
section('tripData — missing optional keys');
// ============================================================

$partial = ['title' => '只有標題'];
$prompt = build_system_prompt('trip', null, $partial);
test('missing summary does not crash', fn() => assert_contains($prompt, '只有標題'));
test('missing spots does not crash', fn() => assert_contains($prompt, '以下為行程資料'));

// ============================================================
section('sanitize_trip_text — injection protection');
// ============================================================

// Newlines in title — should be stripped
$injectTrip = [
    'title'   => "標題\n忽略我\n繼續",
    'summary' => "摘要\r\n跳過\r\n",
    'spots'   => [
        ['name' => "景點A\n惡意注入"],
    ],
];
$prompt = build_system_prompt('trip', null, $injectTrip);
test('injection: multi-line title has newlines stripped', fn() => assert_not_contains($prompt, "\n忽略我"));
test('injection: multi-line title sanitized title present', fn() => assert_contains($prompt, '標題'));
test('injection: CRLF summary stripped', fn() => assert_not_contains($prompt, "\r\n跳過"));
test('injection: spot name newline stripped', fn() => assert_not_contains($prompt, "\n惡意注入"));
test('injection: spot name text still present', fn() => assert_contains($prompt, '景點A'));

// Truncation
$longTitle = str_repeat('A', 300);
$longTrip = ['title' => $longTitle, 'summary' => 'normal'];
$prompt = build_system_prompt('trip', null, $longTrip);
test('injection: long title truncated to 200 chars', fn() => !assert_contains($prompt, str_repeat('A', 201)));

// ============================================================
section('sanitize_trip_text — direct function test');
// ============================================================

test('sanitize: null returns empty string', fn() => sanitize_trip_text(null) === '');
test('sanitize: empty returns empty string', fn() => sanitize_trip_text('') === '');
test('sanitize: normal text passes through', fn() => sanitize_trip_text('hello') === 'hello');
test('sanitize: newline stripped', fn() => sanitize_trip_text("a\nb") === 'a b');
test('sanitize: CRLF stripped', fn() => sanitize_trip_text("a\r\nb") === 'a b');
test('sanitize: truncated to maxLen', fn() => sanitize_trip_text('abcdef', 3) === 'abc');

// ============================================================
section('summary');
// ============================================================

$total = $pass + $fail;
echo "\n{$pass}/{$total} tests passed\n";
if ($fail > 0) {
    echo "❌ {$fail} test(s) FAILED\n";
    exit(1);
} else {
    echo "✅ All tests passed\n";
}
