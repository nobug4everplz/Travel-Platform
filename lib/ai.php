<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

function chat(string $systemPrompt, string $userMessage): array
{
    $apiKey = app_env('DEEPSEEK_API_KEY');
    if ($apiKey === '') {
        return [
            'reply' => 'AI 服務尚未設定，請聯絡管理員。',
            'error' => 'DEEPSEEK_API_KEY not configured',
        ];
    }

    $payload = json_encode([
        'model' => 'deepseek-chat',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ],
        'stream' => false,
        'max_tokens' => 2000,
    ]);

    $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return [
            'reply' => 'AI 服務暫時無法連線，請稍後再試。',
            'error' => 'cURL error: ' . $curlError,
        ];
    }

    if ($httpCode !== 200) {
        return [
            'reply' => 'AI 服務暫時無法回應，請稍後再試。',
            'error' => "DeepSeek API returned HTTP {$httpCode}",
        ];
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['choices'][0]['message']['content'])) {
        return [
            'reply' => 'AI 服務回應異常，請稍後再試。',
            'error' => 'Unexpected API response structure',
        ];
    }

    $reply = $data['choices'][0]['message']['content'];

    return [
        'reply' => $reply,
        'tool_calls' => null,
    ];
}
