<?php

declare(strict_types=1);

/**
 * Exchange rate API abstraction using ExchangeRate-API (free tier).
 *
 * - Free tier: https://open.er-api.com/v6/latest/USD
 * - No API key required
 * - 1-hour cache via JSON file in cache/
 */

/**
 * Country → currency code mapping for destination auto-detection.
 * Keys are case-insensitive substrings matched against the trip address.
 */
const COUNTRY_CURRENCIES = [
    'taiwan'       => 'TWD',
    '台灣'          => 'TWD',
    '臺灣'          => 'TWD',
    'japan'        => 'JPY',
    '日本'          => 'JPY',
    'thailand'     => 'THB',
    'thailand'     => 'THB',
    '泰國'          => 'THB',
    'korea'        => 'KRW',
    'south korea'  => 'KRW',
    '韓國'          => 'KRW',
    'hong kong'    => 'HKD',
    '香港'          => 'HKD',
    'macau'        => 'MOP',
    '澳門'          => 'MOP',
    'singapore'    => 'SGD',
    '新加坡'        => 'SGD',
    'malaysia'     => 'MYR',
    'malaysia'     => 'MYR',
    '馬來西亞'       => 'MYR',
    'indonesia'    => 'IDR',
    '印尼'          => 'IDR',
    'philippines'  => 'PHP',
    'philippines'  => 'PHP',
    '菲律賓'        => 'PHP',
    'vietnam'      => 'VND',
    '越南'          => 'VND',
    'cambodia'     => 'KHR',
    '柬埔寨'        => 'KHR',
    'myanmar'      => 'MMK',
    '緬甸'          => 'MMK',
    'laos'         => 'LAK',
    '寮國'          => 'LAK',
    'india'        => 'INR',
    '印度'          => 'INR',
    'nepal'        => 'NPR',
    '尼泊爾'        => 'NPR',
    'united kingdom' => 'GBP',
    'uk'           => 'GBP',
    'england'      => 'GBP',
    '英國'          => 'GBP',
    'united states' => 'USD',
    'usa'          => 'USD',
    '美國'          => 'USD',
    'australia'    => 'AUD',
    '澳洲'          => 'AUD',
    'new zealand'  => 'NZD',
    '紐西蘭'        => 'NZD',
    'europe'       => 'EUR',
    'eur'          => 'EUR',
    'france'       => 'EUR',
    'germany'      => 'EUR',
    'italy'        => 'EUR',
    'spain'        => 'EUR',
    '法國'          => 'EUR',
    '德國'          => 'EUR',
    '義大利'        => 'EUR',
    '西班牙'        => 'EUR',
    'canada'       => 'CAD',
    '加拿大'        => 'CAD',
    'china'        => 'CNY',
    '中國'          => 'CNY',
    '瑞士'          => 'CHF',
    'switzerland'  => 'CHF',
    '阿拉伯聯合大公國' => 'AED',
    'uae'          => 'AED',
    'dubai'        => 'AED',
    '土耳其'        => 'TRY',
    'turkey'       => 'TRY',
];

/**
 * Cache directory path (relative to project root).
 */
define('CURRENCY_CACHE_DIR', __DIR__ . '/../cache');

/**
 * Cache TTL in seconds (1 hour).
 */
define('CURRENCY_CACHE_TTL', 3600);

/**
 * Format a currency amount with its symbol prefix.
 */
function format_currency(float $amount, string $currency): string
{
    return match ($currency) {
        'TWD' => 'NT$' . number_format($amount),
        'JPY' => '¥' . number_format($amount),
        'KRW' => '₩' . number_format($amount),
        'CNY' => '¥' . number_format($amount),
        'EUR' => '€' . number_format($amount, 2),
        'GBP' => '£' . number_format($amount, 2),
        'THB' => '฿' . number_format($amount, 2),
        'VND' => '₫' . number_format($amount),
        'IDR' => 'Rp' . number_format($amount),
        'HKD' => 'HK$' . number_format($amount, 2),
        'SGD' => 'S$' . number_format($amount, 2),
        'MYR' => 'RM' . number_format($amount, 2),
        'PHP' => '₱' . number_format($amount, 2),
        'INR' => '₹' . number_format($amount, 2),
        'USD' => '$' . number_format($amount, 2),
        'AUD' => 'A$' . number_format($amount, 2),
        'NZD' => 'NZ$' . number_format($amount, 2),
        'CAD' => 'C$' . number_format($amount, 2),
        'CHF' => 'CHF ' . number_format($amount, 2),
        'AED' => 'AED ' . number_format($amount, 2),
        'TRY' => '₺' . number_format($amount, 2),
        default => number_format($amount, 2) . ' ' . $currency,
    };
}

/**
 * Guess destination currency from a trip address string.
 *
 * Searches for known country names (case-insensitive) in the address.
 * Returns the currency code, or null if unknown.
 */
function guess_destination_currency(?string $address): ?string
{
    if ($address === null || $address === '') {
        return null;
    }

    $lower = mb_strtolower($address, 'UTF-8');

    foreach (COUNTRY_CURRENCIES as $keyword => $currency) {
        if (mb_strpos($lower, $keyword) !== false) {
            return $currency;
        }
    }

    return null;
}

/**
 * Fetch exchange rates from the public API (no key required).
 *
 * Returns all rates relative to USD, or null on failure.
 *
 * @return array<string, float>|null e.g. ['JPY' => 149.5, 'TWD' => 32.1, ...]
 */
function fetch_exchange_rates(): ?array
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents('https://open.er-api.com/v6/latest/USD', false, $ctx);
    if ($body === false) {
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['result']) || $data['result'] !== 'success') {
        return null;
    }

    if (!isset($data['rates']) || !is_array($data['rates'])) {
        return null;
    }

    /** @var array<string, float> */
    $rates = [];
    foreach ($data['rates'] as $code => $rate) {
        if (is_string($code) && (is_float($rate) || is_int($rate))) {
            $rates[$code] = (float) $rate;
        }
    }

    return $rates;
}

/**
 * Get exchange rates, using cache if fresh.
 *
 * @return array<string, float>|null
 */
function get_exchange_rates(): ?array
{
    $cacheFile = CURRENCY_CACHE_DIR . '/exchange_rates.json';

    // Try cache first
    if (is_file($cacheFile)) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            if (is_array($data)
                && isset($data['rates'], $data['cached_at'])
                && is_array($data['rates'])
                && $data['cached_at'] + CURRENCY_CACHE_TTL > time()
            ) {
                return $data['rates'];
            }
        }
    }

    // Fetch fresh
    $rates = fetch_exchange_rates();
    if ($rates === null) {
        // Fall back to stale cache if fresh fetch fails
        if (is_file($cacheFile)) {
            $cached = @file_get_contents($cacheFile);
            if ($cached !== false) {
                $data = json_decode($cached, true);
                if (is_array($data) && isset($data['rates']) && is_array($data['rates'])) {
                    return $data['rates'];
                }
            }
        }
        return null;
    }

    // Write cache
    if (!is_dir(CURRENCY_CACHE_DIR)) {
        @mkdir(CURRENCY_CACHE_DIR, 0755, true);
    }

    $cacheData = json_encode([
        'rates' => $rates,
        'cached_at' => time(),
    ], JSON_UNESCAPED_UNICODE);

    if ($cacheData !== false) {
        @file_put_contents($cacheFile, $cacheData, LOCK_EX);
    }

    return $rates;
}

/**
 * Get exchange rate from one currency to another.
 *
 * Uses USD as base (the free API only provides rates relative to USD).
 * Returns null on failure.
 */
function get_exchange_rate(string $from, string $to): ?float
{
    $from = strtoupper($from);
    $to   = strtoupper($to);

    if ($from === $to) {
        return 1.0;
    }

    $rates = get_exchange_rates();
    if ($rates === null) {
        return null;
    }

    if ($from === 'USD') {
        return $rates[$to] ?? null;
    }

    if ($to === 'USD') {
        $rate = $rates[$from] ?? null;
        return $rate !== null ? (1.0 / $rate) : null;
    }

    // Cross-rate: from → USD → to
    $fromToUsd = $rates[$from] ?? null;
    $usdToTo   = $rates[$to] ?? null;

    if ($fromToUsd === null || $usdToTo === null) {
        return null;
    }

    return $usdToTo / $fromToUsd;
}

/**
 * Convert an amount from one currency to another.
 *
 * Returns the converted amount, or null on failure.
 */
function convert_currency(float $amount, string $from, string $to): ?float
{
    $rate = get_exchange_rate($from, $to);
    if ($rate === null) {
        return null;
    }

    return round($amount * $rate, 2);
}

/**
 * Get a human-readable timestamp for when the cache was last updated.
 */
function get_exchange_rate_cache_time(): ?string
{
    $cacheFile = CURRENCY_CACHE_DIR . '/exchange_rates.json';

    if (!is_file($cacheFile)) {
        return null;
    }

    $cached = @file_get_contents($cacheFile);
    if ($cached === false) {
        return null;
    }

    $data = json_decode($cached, true);
    if (!is_array($data) || !isset($data['cached_at'])) {
        return null;
    }

    return date('Y/m/d H:i', (int) $data['cached_at']);
}
