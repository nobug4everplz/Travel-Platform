<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ai.php';
require_once __DIR__ . '/../lib/ai-context.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['reply' => null, 'error' => '僅接受 POST 請求']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['reply' => null, 'error' => '請提供有效的 JSON 資料']);
    exit;
}

$csrfToken = (string) ($input['csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['reply' => null, 'error' => '安全驗證失敗']);
    exit;
}

$user = require_login();

$now = time();
$window = $_SESSION['chat_rate_limit'] ?? [];
$window = array_values(array_filter($window, fn(int $ts) => $now - $ts < 60));
if (count($window) >= 10) {
    http_response_code(429);
    echo json_encode(['reply' => null, 'error' => '請求過於頻繁，請稍後再試。']);
    exit;
}
$window[] = $now;
$_SESSION['chat_rate_limit'] = $window;

$message  = trim((string) ($input['message'] ?? ''));
$pageType = trim((string) ($input['page'] ?? ''));
$tripData = isset($input['trip_data']) && is_array($input['trip_data']) ? $input['trip_data'] : null;

if ($message === '') {
    http_response_code(400);
    echo json_encode(['reply' => null, 'error' => '請輸入訊息']);
    exit;
}

$systemPrompt = build_system_prompt($pageType, $user, $tripData);

$result = chat($systemPrompt, $message);

if (isset($result['error'])) {
    http_response_code(503);
}

header('Content-Type: application/json');
echo json_encode([
    'reply' => $result['reply'],
    'tool_calls' => $result['tool_calls'] ?? null,
]);
