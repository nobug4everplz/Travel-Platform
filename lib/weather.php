<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Fetch current weather for a city.
 *
 * @return array{temp: float, description: string, icon: string}|null
 */
function get_weather(string $city, string $country = ''): ?array
{
    $apiKey = getenv('OPENWEATHERMAP_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        return null;
    }

    $q = rawurlencode($city);
    if ($country !== '') {
        $q .= ',' . rawurlencode($country);
    }

    $url = sprintf(
        'https://api.openweathermap.org/data/2.5/weather?q=%s&units=metric&appid=%s',
        $q,
        $apiKey
    );

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['main']['temp'], $data['weather'][0]['description'], $data['weather'][0]['icon'])) {
        return null;
    }

    return [
        'temp' => (float) $data['main']['temp'],
        'description' => (string) $data['weather'][0]['description'],
        'icon' => (string) $data['weather'][0]['icon'],
    ];
}

/**
 * Fetch 3-day weather forecast for a city.
 *
 * @return list<array{date: string, temp_high: float, temp_low: float, description: string, icon: string}>|null
 */
function get_forecast(string $city, string $country = ''): ?array
{
    $apiKey = getenv('OPENWEATHERMAP_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        return null;
    }

    $q = rawurlencode($city);
    if ($country !== '') {
        $q .= ',' . rawurlencode($country);
    }

    $url = sprintf(
        'https://api.openweathermap.org/data/2.5/forecast?q=%s&units=metric&cnt=24&appid=%s',
        $q,
        $apiKey
    );

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['list']) || !is_array($data['list'])) {
        return null;
    }

    // Aggregate by day — take the first 3 full days
    $days = [];
    foreach ($data['list'] as $item) {
        if (!isset($item['dt'], $item['main']['temp_max'], $item['main']['temp_min'], $item['weather'][0]['description'], $item['weather'][0]['icon'])) {
            continue;
        }

        $date = date('Y-m-d', (int) $item['dt']);
        if (!isset($days[$date])) {
            $days[$date] = [
                'date' => $date,
                'temp_high' => (float) $item['main']['temp_max'],
                'temp_low' => (float) $item['main']['temp_min'],
                'description' => (string) $item['weather'][0]['description'],
                'icon' => (string) $item['weather'][0]['icon'],
            ];
        } else {
            // Track daily high/low across 3-hour segments
            $days[$date]['temp_high'] = max($days[$date]['temp_high'], (float) $item['main']['temp_max']);
            $days[$date]['temp_low'] = min($days[$date]['temp_low'], (float) $item['main']['temp_min']);
        }
    }

    return array_slice(array_values($days), 0, 3);
}

/**
 * Extract a city name from a trip address string.
 * Returns the first segment before comma, cleaned up.
 */
function extract_city_from_address(string $address): string
{
    $parts = explode(',', $address);
    return trim($parts[0]);
}
