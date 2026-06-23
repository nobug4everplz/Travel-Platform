<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Geocode a city name to latitude/longitude using Open-Meteo Geocoding API (free, no key).
 *
 * @return array{lat: float, lng: float}|null
 */
function geocode_city(string $city): ?array
{
    $url = sprintf(
        'https://geocoding-api.open-meteo.com/v1/search?name=%s&count=1&language=zh',
        rawurlencode($city)
    );

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
            'header' => "User-Agent: TravelPlatform/1.0\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['results'][0]['latitude'], $data['results'][0]['longitude'])) {
        return null;
    }

    return [
        'lat' => (float) $data['results'][0]['latitude'],
        'lng' => (float) $data['results'][0]['longitude'],
    ];
}

/**
 * Map WMO weather code to description and icon.
 * Codes: https://www.nodc.noaa.gov/archive/arc0021/0002199/1.1/data/0-data/HTML/WMO-CODE/WMO4677.HTM
 */
function wmo_to_weather(int $code): array
{
    $map = [
        0  => ['晴天', '☀️'],
        1  => ['晴時多雲', '🌤️'],
        2  => ['多雲', '⛅'],
        3  => ['陰天', '☁️'],
        45 => ['有霧', '🌫️'],
        48 => ['霧凇', '🌫️'],
        51 => ['毛毛雨', '🌦️'],
        53 => ['毛毛雨', '🌦️'],
        55 => ['毛毛雨', '🌦️'],
        61 => ['小雨', '🌧️'],
        63 => ['雨', '🌧️'],
        65 => ['大雨', '🌧️'],
        71 => ['小雪', '🌨️'],
        73 => ['雪', '🌨️'],
        75 => ['大雪', '🌨️'],
        80 => ['陣雨', '🌦️'],
        81 => ['陣雨', '🌦️'],
        82 => ['豪雨', '🌧️'],
        85 => ['陣雪', '🌨️'],
        86 => ['陣雪', '🌨️'],
        95 => ['雷雨', '⛈️'],
        96 => ['雷雨+冰雹', '⛈️'],
        99 => ['雷雨+冰雹', '⛈️'],
    ];

    return $map[$code] ?? ['未知', '🌡️'];
}

/**
 * Fetch current weather for a city using Open-Meteo (free, no API key).
 *
 * @return array{temp: float, description: string, icon: string}|null
 */
function get_weather(string $city, string $country = ''): ?array
{
    $query = $city;
    if ($country !== '') {
        $query = $city . ',' . $country;
    }

    $geo = geocode_city($query);
    if ($geo === null) {
        return null;
    }

    $url = sprintf(
        'https://api.open-meteo.com/v1/forecast?latitude=%.4f&longitude=%.4f&current=temperature_2m,weather_code&timezone=auto',
        $geo['lat'],
        $geo['lng']
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
    if (!is_array($data) || !isset($data['current']['temperature_2m'], $data['current']['weather_code'])) {
        return null;
    }

    [$desc, $icon] = wmo_to_weather((int) $data['current']['weather_code']);

    return [
        'temp' => (float) $data['current']['temperature_2m'],
        'description' => $desc,
        'icon' => $icon,
    ];
}

/**
 * Fetch 3-day weather forecast for a city using Open-Meteo (free, no API key).
 *
 * @return list<array{date: string, temp_high: float, temp_low: float, description: string, icon: string}>|null
 */
function get_forecast(string $city, string $country = ''): ?array
{
    $query = $city;
    if ($country !== '') {
        $query = $city . ',' . $country;
    }

    $geo = geocode_city($query);
    if ($geo === null) {
        return null;
    }

    $url = sprintf(
        'https://api.open-meteo.com/v1/forecast?latitude=%.4f&longitude=%.4f&daily=temperature_2m_max,temperature_2m_min,weather_code&forecast_days=3&timezone=auto',
        $geo['lat'],
        $geo['lng']
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
    if (!is_array($data) || !isset($data['daily']['time']) || !is_array($data['daily']['time'])) {
        return null;
    }

    $forecast = [];
    $count = count($data['daily']['time']);
    for ($i = 0; $i < $count; $i++) {
        $code = (int) ($data['daily']['weather_code'][$i] ?? 0);
        [$desc, $icon] = wmo_to_weather($code);

        $forecast[] = [
            'date' => (string) $data['daily']['time'][$i],
            'temp_high' => (float) ($data['daily']['temperature_2m_max'][$i] ?? 0),
            'temp_low' => (float) ($data['daily']['temperature_2m_min'][$i] ?? 0),
            'description' => $desc,
            'icon' => $icon,
        ];
    }

    return $forecast;
}

/**
 * Extract a city name from a trip address string.
 * Returns the first segment before comma, cleaned up.
 */
function extract_city_from_address(string $address): string
{
    // Try comma-separated first (e.g. "Kyoto, Japan")
    $parts = explode(',', $address);
    if (count($parts) > 1) {
        return trim($parts[0]);
    }
    
    // Try common Taiwanese address patterns: 縣市區...
    if (preg_match('/^(.{2,3}[縣市])/u', $address, $m)) {
        return $m[1];
    }
    
    // Fallback: first 3 chars (for non-TW addresses without comma)
    return mb_substr(trim($address), 0, 6);
}
