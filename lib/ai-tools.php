<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/trips.php';
require_once __DIR__ . '/spot-actions.php';
require_once __DIR__ . '/recommendations.php';

/**
 * Return all tool definitions in DeepSeek / OpenAI function calling format.
 *
 * Each entry has: name, description, parameters (JSON Schema).
 */
function get_tool_definitions(): array
{
    return [
        // -------------------------------------------------------------------
        // Tool 1: search_trips
        // -------------------------------------------------------------------
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'search_trips',
                'description' => '搜尋公開行程，依關鍵字比對標題、摘要與規劃師名稱',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => '搜尋關鍵字（比對行程標題、摘要或規劃師名稱）',
                        ],
                        'limit' => [
                            'type'        => 'integer',
                            'description' => '回傳筆數上限（1–20）',
                            'default'     => 5,
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ],

        // -------------------------------------------------------------------
        // Tool 2: get_trip_detail
        // -------------------------------------------------------------------
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'get_trip_detail',
                'description' => '取得特定行程的完整資訊，包含所有景點細節',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'trip_id' => [
                            'type'        => 'integer',
                            'description' => '行程 ID',
                        ],
                    ],
                    'required' => ['trip_id'],
                ],
            ],
        ],

        // -------------------------------------------------------------------
        // Tool 3: get_planner_stats
        // -------------------------------------------------------------------
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'get_planner_stats',
                'description' => '取得目前登入規劃師的儀表板統計資料（發布數、草稿數、收藏者、評分等）。僅限規劃師角色使用。',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                ],
            ],
        ],

        // -------------------------------------------------------------------
        // Tool 4: get_traveler_footprints
        // -------------------------------------------------------------------
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'get_traveler_footprints',
                'description' => '取得旅行者的足跡摘要與統計（參加行程數、收藏數、手動足跡等）。僅限旅人角色使用。',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                ],
            ],
        ],

        // -------------------------------------------------------------------
        // Tool 5: recommend_trips
        // -------------------------------------------------------------------
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'recommend_trips',
                'description' => '根據使用者的收藏規劃師、足跡與行程評分，推薦可能感興趣的行程',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'limit' => [
                            'type'        => 'integer',
                            'description' => '推薦筆數上限（1–10）',
                            'default'     => 3,
                        ],
                    ],
                ],
            ],
        ],

        // -------------------------------------------------------------------
        // Tool 6: suggest_spots
        // -------------------------------------------------------------------
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'suggest_spots',
                'description' => '建議某個區域的熱門景點（從現有行程景點中統計出現頻率）',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'area' => [
                            'type'        => 'string',
                            'description' => '地區名稱（例如「台北」、「台中」、「花蓮」、「台南」）',
                        ],
                        'count' => [
                            'type'        => 'integer',
                            'description' => '回傳景點數上限（1–20）',
                            'default'     => 5,
                        ],
                    ],
                    'required' => ['area'],
                ],
            ],
        ],

        // -------------------------------------------------------------------
        // Tool 7: generate_trip_summary
        // -------------------------------------------------------------------
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'generate_trip_summary',
                'description' => '為行程產生一段吸引人的摘要文案，適合放在行程卡片、社群分享或 SEO 描述中使用',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'trip_title' => [
                            'type'        => 'string',
                            'description' => '行程標題',
                        ],
                        'spots' => [
                            'type'        => 'array',
                            'description' => '行程包含的景點列表',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'name'    => ['type' => 'string', 'description' => '景點名稱'],
                                    'address' => ['type' => 'string', 'description' => '景點地址（選填）'],
                                ],
                            ],
                        ],
                    ],
                    'required' => ['trip_title', 'spots'],
                ],
            ],
        ],

        // -------------------------------------------------------------------
        // Tool 8: fill_editor
        // -------------------------------------------------------------------
        [
            'type'     => 'function',
            'function' => [
                'name'        => 'fill_editor',
                'description' => '將 LLM 生成的行程內容（標題、摘要、景點）填入編輯器表單。呼叫後前端會顯示「填入編輯器」按鈕讓使用者確認。只能填自己擁有的行程。',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'trip_id' => [
                            'type'        => 'integer',
                            'description' => '要填入的行程 ID',
                        ],
                        'content' => [
                            'type'        => 'object',
                            'description' => '要填入編輯器的內容結構',
                            'properties'  => [
                                'title'   => ['type' => 'string', 'description' => '行程標題（選填）'],
                                'summary' => ['type' => 'string', 'description' => '行程摘要（選填）'],
                                'spots'   => [
                                    'type'        => 'array',
                                    'description' => '景點列表（選填）',
                                    'items'       => [
                                        'type'       => 'object',
                                        'properties' => [
                                            'name'      => ['type' => 'string', 'description' => '景點名稱'],
                                            'address'   => ['type' => 'string', 'description' => '地址'],
                                            'latitude'  => ['type' => 'number', 'description' => '緯度'],
                                            'longitude' => ['type' => 'number', 'description' => '經度'],
                                            'notes'     => ['type' => 'string', 'description' => '備註'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'required' => ['trip_id', 'content'],
                ],
            ],
        ],
    ];
}

/**
 * Route a tool call to its handler and return structured data.
 *
 * @param string $name      Tool name (must match one of get_tool_definitions)
 * @param array  $arguments Parsed arguments from the LLM
 * @param array  $user      Current authenticated user array (from current_user / require_login)
 *
 * @return array Result data, or ['error' => '...'] on failure
 */
function execute_tool(string $name, array $arguments, array $user): array
{
    return match ($name) {
        'search_trips'            => handle_search_trips($arguments, $user),
        'get_trip_detail'         => handle_get_trip_detail($arguments, $user),
        'get_planner_stats'       => handle_get_planner_stats($arguments, $user),
        'get_traveler_footprints' => handle_get_traveler_footprints($arguments, $user),
        'recommend_trips'         => handle_recommend_trips($arguments, $user),
        'suggest_spots'           => handle_suggest_spots($arguments, $user),
        'generate_trip_summary'   => handle_generate_trip_summary($arguments, $user),
        'fill_editor'             => handle_fill_editor($arguments, $user),
        default                   => ['error' => "未知工具：{$name}"],
    };
}

// ============================================================================
// Handler implementations
// ============================================================================

/**
 * search_trips(query, limit)
 *
 * Search public trips by keyword matching title, summary, or author name.
 * Always available to any authenticated user.
 */
function handle_search_trips(array $args, array $user): array
{
    $query = trim((string) ($args['query'] ?? ''));
    $limit = min(max((int) ($args['limit'] ?? 5), 1), 20);

    if ($query === '') {
        return ['error' => '請提供搜尋關鍵字'];
    }

    $like = '%' . addcslashes($query, '%_') . '%';

    $stmt = pdo()->prepare(
        'SELECT t.id, t.title, t.summary, t.average_rating, t.review_count,
                u.name AS author_name, u.avatar_url AS author_avatar
         FROM trips t
         JOIN users u ON u.id = t.author_id
         WHERE t.is_published = 1
           AND (t.title LIKE ? OR t.summary LIKE ? OR u.name LIKE ?)
         ORDER BY t.average_rating DESC, t.review_count DESC, t.updated_at DESC
         LIMIT ?'
    );
    $stmt->execute([$like, $like, $like, $limit]);
    return $stmt->fetchAll();
}

/**
 * get_trip_detail(trip_id)
 *
 * Return full trip info including spots. Respects visibility rules
 * (unpublished trips visible only to author / admin).
 */
function handle_get_trip_detail(array $args, array $user): array
{
    $tripId = (int) ($args['trip_id'] ?? 0);

    if ($tripId < 1) {
        return ['error' => '無效的行程 ID'];
    }

    $trip = find_visible_trip($tripId, $user);
    if (!$trip) {
        return ['error' => '找不到此行程或無權限檢視'];
    }

    $spots = get_trip_spots($tripId);

    return [
        'trip'       => $trip,
        'spots'      => $spots,
        'spot_count' => count($spots),
    ];
}

/**
 * get_planner_stats()
 *
 * Dashboard-style stats for the logged-in planner.
 * Permission: planner or admin only.
 */
function handle_get_planner_stats(array $args, array $user): array
{
    $role = $user['role'] ?? '';
    if ($role !== 'planner' && $role !== 'admin') {
        return ['error' => '僅規劃師角色可使用此工具'];
    }

    $uid = $user['id'];

    $stmt = pdo()->prepare(
        'SELECT
            (SELECT COUNT(*) FROM trips WHERE author_id = ? AND is_published = 1) AS published_count,
            (SELECT COUNT(*) FROM trips WHERE author_id = ? AND is_published = false) AS draft_count,
            (SELECT COUNT(*) FROM favorite_planners WHERE planner_id = ?) AS follower_count,
            (SELECT COUNT(*) FROM reviews r JOIN trips t ON t.id = r.trip_id WHERE t.author_id = ?) AS review_count,
            (SELECT ROUND(AVG(t.average_rating), 1) FROM trips t WHERE t.author_id = ? AND t.is_published = 1 AND t.review_count > 0) AS avg_rating'
    );
    $stmt->execute([$uid, $uid, $uid, $uid, $uid]);
    $stats = $stmt->fetch();

    // Recent 7-day analytics
    $stmt2 = pdo()->prepare(
        'SELECT
            (SELECT COUNT(*) FROM trip_daily_unique_views v JOIN trips t ON t.id = v.trip_id WHERE t.author_id = ? AND v.view_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS weekly_unique_views,
            (SELECT COUNT(*) FROM favorite_trips ft JOIN trips t ON t.id = ft.trip_id WHERE t.author_id = ? AND ft.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS weekly_new_favorites'
    );
    $stmt2->execute([$uid, $uid]);
    $analytics = $stmt2->fetch();

    return array_merge($stats, $analytics);
}

/**
 * get_traveler_footprints()
 *
 * Footprints summary and recent marks for the logged-in traveler.
 * Permission: traveler or admin only.
 */
function handle_get_traveler_footprints(array $args, array $user): array
{
    $role = $user['role'] ?? '';
    if ($role !== 'traveler' && $role !== 'admin') {
        return ['error' => '僅旅人角色可使用此工具'];
    }

    $uid = $user['id'];

    $stmt = pdo()->prepare(
        'SELECT
            (SELECT COUNT(*) FROM trip_participations WHERE user_id = ?) AS participation_count,
            (SELECT COUNT(*) FROM favorite_trips WHERE user_id = ?) AS favorite_trip_count,
            (SELECT COUNT(*) FROM reviews WHERE reviewer_id = ?) AS review_count,
            (SELECT COUNT(*) FROM traveler_footprints WHERE user_id = ?) AS manual_footprint_count'
    );
    $stmt->execute([$uid, $uid, $uid, $uid]);
    $stats = $stmt->fetch();

    // Auto footprints (trips the user joined with coordinates)
    $autoFp = pdo()->prepare(
        'SELECT t.id, t.title, t.latitude, t.longitude, p.joined_at
         FROM trip_participations p
         JOIN trips t ON t.id = p.trip_id
         WHERE p.user_id = ? AND t.latitude IS NOT NULL AND t.longitude IS NOT NULL
         ORDER BY p.joined_at DESC
         LIMIT 10'
    );
    $autoFp->execute([$uid]);
    $autoRows = $autoFp->fetchAll();

    // Manual footprints
    $manualFp = pdo()->prepare(
        'SELECT id, name, latitude, longitude, visited_at, notes
         FROM traveler_footprints
         WHERE user_id = ?
         ORDER BY visited_at DESC
         LIMIT 10'
    );
    $manualFp->execute([$uid]);
    $manualRows = $manualFp->fetchAll();

    return [
        'stats'             => $stats,
        'auto_footprints'   => $autoRows,
        'manual_footprints' => $manualRows,
    ];
}

/**
 * recommend_trips(limit)
 *
 * Delegates to recommend_trips_for_user() in recommendations.php.
 * Available to any authenticated user.
 */
function handle_recommend_trips(array $args, array $user): array
{
    $limit = min(max((int) ($args['limit'] ?? 3), 1), 10);
    return recommend_trips_for_user($user['id'], $limit);
}

/**
 * suggest_spots(area, count)
 *
 * Delegates to suggest_popular_spots() in recommendations.php.
 * Available to any authenticated user.
 */
function handle_suggest_spots(array $args, array $user): array
{
    $area = trim((string) ($args['area'] ?? ''));
    if ($area === '') {
        return ['error' => '請提供地區名稱'];
    }

    $count = min(max((int) ($args['count'] ?? 5), 1), 20);
    return suggest_popular_spots($area, $count);
}

/**
 * generate_trip_summary(trip_title, spots[])
 *
 * Generate an attractive summary text from the provided trip data.
 * Always available to any authenticated user.
 */
function handle_generate_trip_summary(array $args, array $user): array
{
    $title = trim((string) ($args['trip_title'] ?? ''));
    $spots = isset($args['spots']) && is_array($args['spots']) ? $args['spots'] : [];

    if ($title === '') {
        return ['error' => '請提供行程標題'];
    }

    $spotNames = [];
    foreach ($spots as $spot) {
        if (is_array($spot) && !empty($spot['name'])) {
            $spotNames[] = trim((string) $spot['name']);
        }
    }

    if (empty($spotNames)) {
        $summary = "探索「{$title}」，一趟精心規劃的旅程。";
    } else {
        $spotList = implode(' → ', $spotNames);
        $count = count($spotNames);
        $summary = "「{$title}」帶你走訪 {$count} 個精選景點：{$spotList}。"
                 . "從第一站到最後一站，每個地點都經過細心挑選，"
                 . "讓這趟旅程充滿驚喜與美好回憶。";
    }

    return [
        'summary'    => $summary,
        'spot_count' => count($spotNames),
        'spot_names' => $spotNames,
    ];
}

/**
 * fill_editor(trip_id, content)
 *
 * Store editor-fill content in session so the front-end can redirect
 * to the editor page with pre-filled fields.
 * Permission: planner or admin only (must own the trip).
 */
function handle_fill_editor(array $args, array $user): array
{
    $role = $user['role'] ?? '';
    if ($role !== 'planner' && $role !== 'admin') {
        return ['error' => '僅規劃師角色可使用此工具'];
    }

    $tripId = (int) ($args['trip_id'] ?? 0);
    if ($tripId < 1) {
        return ['error' => '請提供有效的行程 ID'];
    }

    // Verify the trip exists and belongs to this user
    $trip = find_trip($tripId);
    if (!$trip) {
        return ['error' => '找不到此行程'];
    }
    if ((int) $trip['author_id'] !== (int) $user['id']) {
        return ['error' => '你只能編輯自己的行程'];
    }

    $content = $args['content'] ?? [];
    if (!is_array($content)) {
        return ['error' => '請提供有效的內容結構'];
    }

    // Store fill data in session (used by editor.php on redirect)
    $_SESSION['ai_fill_data'] = [
        'trip_id' => $tripId,
        'content' => $content,
    ];

    return [
        'ok'        => true,
        'fill_url'  => "/editor.php?id={$tripId}&ai_fill=1",
        'trip_id'   => $tripId,
        'action'    => 'fill_editor',
    ];
}
