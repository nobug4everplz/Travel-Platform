<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/trips.php';
require_once __DIR__ . '/../lib/spot-actions.php';

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

$csrfToken = (string) ($input['csrf_token'] ?? '');
$tripId    = (int) ($input['trip_id'] ?? 0);
$spotIds   = isset($input['spot_ids']) && is_array($input['spot_ids']) ? $input['spot_ids'] : [];

if ($tripId < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => '請提供有效的行程 ID']);
    exit;
}

if (!hash_equals(csrf_token(), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => '安全驗證失敗']);
    exit;
}

$user = require_role('planner');

$trip = find_trip($tripId);
if (!$trip || (int) $trip['author_id'] !== (int) $user['id']) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => '找不到行程']);
    exit;
}

// Filter to only integer IDs, keep order
$validIds = [];
foreach ($spotIds as $sid) {
    $id = (int) $sid;
    if ($id > 0) {
        $validIds[] = $id;
    }
}

reorder_trip_spots($tripId, $validIds);

echo json_encode(['ok' => true, 'reordered' => count($validIds)]);
