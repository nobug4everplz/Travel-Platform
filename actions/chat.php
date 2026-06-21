<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ai.php';
require_once __DIR__ . '/../lib/ai-context.php';
require_once __DIR__ . '/../lib/ai-tools.php';

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
$validPages = ['home', 'trip', 'editor', 'planner_dashboard', 'traveler_dashboard'];
$pageType = in_array($pageType, $validPages, true) ? $pageType : 'home';
$tripData = isset($input['trip_data']) && is_array($input['trip_data']) ? $input['trip_data'] : null;

if ($message === '') {
    http_response_code(400);
    echo json_encode(['reply' => null, 'error' => '請輸入訊息']);
    exit;
}

// ───── Build system prompt ─────
$systemPrompt = build_system_prompt($pageType, $user, $tripData);

// ───── Function Calling loop ─────
$tools = get_tool_definitions();

$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user', 'content' => $message],
];

$maxRounds = 5;
$reply = null;
$toolCallsUsed = [];
$loopError = null;

for ($round = 0; $round < $maxRounds; $round++) {
    $result = chat_with_tools($messages, $tools);

    if (!empty($result['error'])) {
        $loopError = $result['error'];
        break;
    }

    // Store the current text reply (may be null if only tool calls)
    if ($result['reply'] !== null) {
        $reply = $result['reply'];
    }

    $toolCalls = $result['tool_calls'];
    if (empty($toolCalls)) {
        // No tool calls → LLM response is final text
        break;
    }

    // ── Process tool calls ──
    // Push the assistant's response (with tool_calls) into messages
    $assistantMessage = [
        'role' => 'assistant',
        'content' => $result['reply'],
        'tool_calls' => [],
    ];

    foreach ($toolCalls as $tc) {
        $toolCallId = $tc['id'] ?? '';
        $functionName = $tc['function']['name'] ?? '';
        $argumentsRaw = $tc['function']['arguments'] ?? '{}';
        $arguments = json_decode($argumentsRaw, true);
        if (!is_array($arguments)) {
            $arguments = [];
        }

        // Record the tool call used
        $toolCallsUsed[] = [
            'name'      => $functionName,
            'arguments' => $arguments,
        ];

        // Add to assistant's tool_calls list
        $assistantMessage['tool_calls'][] = [
            'id'       => $toolCallId,
            'type'     => 'function',
            'function' => [
                'name'      => $functionName,
                'arguments' => $argumentsRaw,
            ],
        ];

        // Execute the tool with permission check
        $toolResult = execute_tool($functionName, $arguments, $user);

        // If the tool had an error, use graceful fallback
        $toolResultContent = $toolResult;
        if (isset($toolResult['error'])) {
            $toolResultContent = [
                'error'   => true,
                'message' => $toolResult['error'],
            ];
        }

        // Push tool result as a message
        $messages[] = [
            'role'         => 'tool',
            'tool_call_id' => $toolCallId,
            'content'      => json_encode($toolResultContent, JSON_UNESCAPED_UNICODE),
        ];
    }

    // Push the assistant's message (with tool_calls references) into messages
    $messages[] = $assistantMessage;

    // Continue loop for next round
}

// If max rounds reached without final text, use the last accumulated reply
if ($reply === null && $loopError === null) {
    $reply = '已超過最大處理次數，請簡化你的問題後重新詢問。';
}

// If error occurred, use fallback
if ($loopError !== null) {
    $reply = 'AI 服務暫時無法處理，請稍後再試。';
}

header('Content-Type: application/json');
echo json_encode([
    'reply'           => $reply,
    'tool_calls'      => $toolCallsUsed,
    'error'           => $loopError,
]);
