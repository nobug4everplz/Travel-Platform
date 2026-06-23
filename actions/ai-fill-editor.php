<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';
require_once __DIR__ . '/../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => '僅接受 POST 請求']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '請提供有效的 JSON 資料']);
    exit;
}

// CSRF verification (from JSON body, matching the pattern in reorder-spots.php)
$csrfToken = (string) ($input['csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => '安全驗證失敗']);
    exit;
}

// Require planner role
$user = require_role('planner');

$tripId = (int) ($input['trip_id'] ?? 0);
if ($tripId < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '請提供有效的行程 ID']);
    exit;
}

$content = $input['content'] ?? null;
if (!is_array($content)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '請提供有效的內容結構']);
    exit;
}

// Verify trip exists and belongs to this user
$trip = find_trip($tripId);
if (!$trip || (int) $trip['author_id'] !== (int) $user['id']) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => '找不到此行程或無權限編輯']);
    exit;
}

// Store fill data in session
$_SESSION['ai_fill_data'] = [
    'trip_id' => $tripId,
    'content' => $content,
];

echo json_encode([
    'ok'       => true,
    'redirect' => '/editor.php?id=' . $tripId . '&ai_fill=1',
]);
