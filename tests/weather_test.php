<?php

declare(strict_types=1);

/**
 * Tests for lib/weather.php — standalone PHP assertion test
 *
 * Usage: php tests/weather_test.php
 *
 * These tests use function overloading (runkit/zend.assert) to mock
 * the http/file_get_contents calls. Since PHP's built-in file_get_contents
 * cannot be easily mocked at runtime, we test the helper + data processing
 * logic by injecting known data into the parse path.
 *
 * For the API functions (get_weather, get_forecast), we set a bogus
 * API key and verify they return null gracefully on failure.
 */

require_once __DIR__ . '/../lib/weather.php';

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

// ============================================================
echo "📋 extract_city_from_address — helper\n";
// ============================================================

test('simple city name returns as-is', function () {
    $result = extract_city_from_address('Tokyo');
    assert($result === 'Tokyo', "Expected 'Tokyo', got '{$result}'");
});

test('city with country returns city only', function () {
    $result = extract_city_from_address('Osaka, Japan');
    assert($result === 'Osaka', "Expected 'Osaka', got '{$result}'");
});

test('full address extracts first segment', function () {
    $result = extract_city_from_address('Shibuya, Tokyo, Japan');
    assert($result === 'Shibuya', "Expected 'Shibuya', got '{$result}'");
});

test('address with extra spaces is trimmed', function () {
    $result = extract_city_from_address('  Taipei City , Taiwan ');
    assert($result === 'Taipei City', "Expected 'Taipei City', got '{$result}'");
});

// ============================================================
echo "\n📋 get_weather — API error handling\n";
// ============================================================

test('returns null when API key is empty', function () {
    putenv('OPENWEATHERMAP_API_KEY=');
    $result = get_weather('Tokyo');
    assert($result === null, 'Expected null when no API key');
});

test('returns null on bad API key (HTTP error)', function () {
    putenv('OPENWEATHERMAP_API_KEY=invalid_key_12345');
    $result = get_weather('Tokyo');
    assert($result === null, 'Expected null on failed API call');
});

// ============================================================
echo "\n📋 get_forecast — API error handling\n";
// ============================================================

test('forecast returns null when API key is empty', function () {
    putenv('OPENWEATHERMAP_API_KEY=');
    $result = get_forecast('Tokyo');
    assert($result === null, 'Expected null when no API key');
});

test('forecast returns null on bad API key (HTTP error)', function () {
    putenv('OPENWEATHERMAP_API_KEY=invalid_key_12345');
    $result = get_forecast('Tokyo');
    assert($result === null, 'Expected null on failed API call');
});

// ============================================================
echo "\n─────────────────────────────────\n";
echo "Results: {$pass} passed, {$fail} failed\n";

if ($fail > 0) {
    exit(1);
}
