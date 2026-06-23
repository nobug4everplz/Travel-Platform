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

// ═══════════════════════════════════════
// ABUSE PREVENTION LAYER
// ═══════════════════════════════════════

// 1. Message length cap
$message = trim((string) ($input['message'] ?? ''));
if (mb_strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['reply' => null, 'error' => '訊息過長，請限制在 2000 字以內。']);
    exit;
}
if ($message === '') {
    http_response_code(400);
    echo json_encode(['reply' => null, 'error' => '請輸入訊息']);
    exit;
}

// 2. Block obvious prompt injection patterns
$blockedPatterns = [
    '/system:\s*ignore/i',
    '/ignore\s+(all\s+)?(previous|above|instructions)/i',
    '/you\s+are\s+now\s+(DAN|jailbroken)/i',
    '/\[INST\].*\[\/INST\]/i',
    '/<\|im_start\|>/i',
];
foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $message)) {
        http_response_code(400);
        echo json_encode(['reply' => null, 'error' => '訊息包含不允許的內容。']);
        exit;
    }
}

// 3. Session rate limit (10/min) — existing, moved here
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

// 4. IP-based rate limit (30/min per IP)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipKey = 'chat_ip_rate_' . md5($ip);
$ipWindow = $_SESSION[$ipKey] ?? [];
$ipWindow = array_values(array_filter($ipWindow, fn(int $ts) => $now - $ts < 60));
if (count($ipWindow) >= 30) {
    http_response_code(429);
    echo json_encode(['reply' => null, 'error' => '請求過於頻繁，請稍後再試。']);
    exit;
}
$ipWindow[] = $now;
$_SESSION[$ipKey] = $ipWindow;

// 5. Daily token budget per user (50,000 tokens/day ≈ ~150 messages)
define('DAILY_TOKEN_BUDGET', 50000);
$db = pdo();
$today = date('Y-m-d');
$usageStmt = $db->prepare(
    'SELECT SUM(tokens_used) AS total FROM ai_usage_log WHERE user_id = ? AND usage_date = ?'
);
$usageStmt->execute([(int) $user['id'], $today]);
$todayUsage = (int) ($usageStmt->fetch()['total'] ?? 0);
if ($todayUsage >= DAILY_TOKEN_BUDGET) {
    http_response_code(429);
    echo json_encode(['reply' => null, 'error' => '今日 AI 使用額度已達上限，請明天再試。']);
    exit;
}

// 6. Consecutive failure lockout (5 errors → 10 min ban)
$failKey = 'chat_fail_count_' . md5($ip);
$failCount = (int) ($_SESSION[$failKey] ?? 0);
$failLockUntil = (int) ($_SESSION[$failKey . '_lock'] ?? 0);
if ($failLockUntil > 0 && $now < $failLockUntil) {
    $waitSec = $failLockUntil - $now;
    http_response_code(429);
    echo json_encode(['reply' => null, 'error' => "暫時鎖定，請 {$waitSec} 秒後再試。"]);
    exit;
}

// 7. Validate page type whitelist (already exists)
$pageType = trim((string) ($input['page'] ?? ''));
$validPages = ['home', 'trip', 'editor', 'planner_dashboard', 'traveler_dashboard'];
$pageType = in_array($pageType, $validPages, true) ? $pageType : 'home';

// 8. Sanitize trip_data — only allow expected keys
$tripData = null;
if (isset($input['trip_data']) && is_array($input['trip_data'])) {
    $allowedKeys = ['id', 'title', 'summary', 'address', 'budget', 'currency', 'start_date'];
    $tripData = array_intersect_key($input['trip_data'], array_flip($allowedKeys));
    // Truncate each value to 500 chars max
    foreach ($tripData as $k => $v) {
        if (is_string($v)) $tripData[$k] = mb_substr($v, 0, 500);
    }
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

// ───── Scan for fill_editor tool call to forward to frontend ─────
$fillEditorAction = null;
foreach ($toolCallsUsed as $tc) {
    if ($tc['name'] === 'fill_editor' && empty($tc['arguments']['error'])) {
        $fillEditorAction = [
            'trip_id' => $tc['arguments']['trip_id'] ?? null,
            'content' => $tc['arguments']['content'] ?? null,
        ];
    }
}

// If max rounds reached without final text, use the last accumulated reply
if ($reply === null && $loopError === null) {
    $reply = '已超過最大處理次數，請簡化你的問題後重新詢問。';
}

// If error occurred, use fallback
if ($loopError !== null) {
    $reply = 'AI 服務暫時無法處理，請稍後再試。';
    // Increment fail counter
    $_SESSION[$failKey] = ($_SESSION[$failKey] ?? 0) + 1;
    if (($_SESSION[$failKey] ?? 0) >= 5) {
        $_SESSION[$failKey . '_lock'] = $now + 600; // 10 min lockout
        $_SESSION[$failKey] = 0;
    }
}

// Log usage (estimate tokens: ~4 chars per token for Chinese)
if ($loopError === null) {
    $tokensUsed = (int) ceil((mb_strlen($message) + mb_strlen($reply ?? '')) / 2);
    log_ai_usage((int) $user['id'], $ip, $pageType, $tokensUsed, $today);
    // Reset fail counter on success
    $_SESSION[$failKey] = 0;
    $_SESSION[$failKey . '_lock'] = 0;
}

header('Content-Type: application/json');
$response = [
    'reply'           => $reply,
    'tool_calls'      => $toolCallsUsed,
    'error'           => $loopError,
];
if ($fillEditorAction) {
    $response['_action'] = 'fill_editor';
    $response['trip_id'] = $fillEditorAction['trip_id'];
    $response['content'] = $fillEditorAction['content'];
}
echo json_encode($response, JSON_UNESCAPED_UNICODE);
