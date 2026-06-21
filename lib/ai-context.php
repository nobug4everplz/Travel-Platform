<?php

declare(strict_types=1);

/**
 * Build a page-aware system prompt for the AI assistant.
 *
 * @param string     $pageType home|trip|editor|planner_dashboard|traveler_dashboard
 * @param array|null $user     User data from current_user() (id, email, name, role, …)
 * @param array|null $tripData Optional trip data for trip / editor pages
 * @return string
 */
function build_system_prompt(string $pageType, ?array $user, ?array $tripData = null): string
{
    $user = $user ?? [];

    $roleLabel = match ($user['role'] ?? '') {
        'traveler' => '旅人',
        'planner'  => '規劃師',
        'admin'    => '管理員',
        default    => '訪客',
    };

    $userName = trim($user['name'] ?? '');
    if ($userName === '') {
        $userName = $user['email'] ?? '親愛的使用者';
    }

    // ── 核心人格 ──
    $prompt = '你是一個繁體中文旅遊平台「行伴」AI 助手。你的任務是協助使用者規劃行程、推薦景點與餐廳，並回答關於旅遊的問題。
請用繁體中文回答，語氣友善專業、溫暖簡潔。

';

    // ── 使用者資訊 ──
    $prompt .= "【使用者資訊】\n角色：{$roleLabel}\n名稱：{$userName}\n\n";

    // ── 頁面情境 ──
    $prompt .= "【當前頁面】\n";

    switch ($pageType) {
        case 'home':
            $prompt .= "首頁（探索頁面）— 使用者在瀏覽公開行程列表。
你可以協助搜尋、篩選或推薦合適的公開行程。";
            break;

        case 'trip':
            $prompt .= "行程頁面 — 使用者在查看一個具體行程。\n";
            if ($tripData) {
                $t = sanitize_trip_text($tripData['title'] ?? '');
                $s = sanitize_trip_text($tripData['summary'] ?? '');
                $spots = $tripData['spots'] ?? null;
                $prompt .= "--- 以下為行程資料 ---\n";
                if ($t !== '') {
                    $prompt .= "行程名稱：{$t}\n";
                }
                if ($s !== '') {
                    $prompt .= "行程摘要：{$s}\n";
                }
                if (is_array($spots) && $spots !== []) {
                    $names = array_map('sanitize_trip_text', array_column($spots, 'name'));
                    $prompt .= '包含景點：' . implode('、', $names) . "\n";
                }
                $prompt .= "--- 行程資料結束 ---\n";
            }
            $prompt .= '你可以協助深入了解這個行程，或提供相關旅遊建議。';
            break;

        case 'editor':
            $prompt .= "行程編輯器 — 使用者在撰寫或編輯行程。\n";
            if ($tripData) {
                $t = sanitize_trip_text($tripData['title'] ?? '');
                $s = sanitize_trip_text($tripData['summary'] ?? '');
                $prompt .= "--- 以下為行程資料 ---\n";
                if ($t !== '') {
                    $prompt .= "行程名稱：{$t}\n";
                }
                if ($s !== '') {
                    $prompt .= "目前已填寫摘要：{$s}\n";
                }
                $prompt .= "--- 行程資料結束 ---\n";
            }
            $prompt .= "你可以協助：\n"
                . "- 撰寫或潤飾行程摘要\n"
                . "- 推薦適合的景點與餐廳\n"
                . "- 建議行程安排與順序\n"
                . "- 提供旅遊資訊";
            break;

        case 'planner_dashboard':
            $prompt .= "規劃師工作台 — 使用者在查看數據儀表板。
你可以協助分析行程成效（瀏覽、收藏、評論），並提出改善建議。";
            break;

        case 'traveler_dashboard':
            $prompt .= "旅人工作台 — 使用者在查看足跡、參加的行程與收藏。
你可以協助回顧旅遊經歷、推薦類似行程或規劃師。";
            break;

        default:
            $prompt .= '根據使用者提出的問題提供適當的協助。';
            break;
    }

    $prompt .= "\n\n【限制】\n"
        . "- 你無法執行實際系統操作（發布行程、修改資料等）\n"
        . "- 不確定的資訊請誠實告知，不要編造\n"
        . "- 景點推薦以現實存在的地點為基礎";

    return $prompt;
}

/**
 * Sanitize user-controlled text before embedding into prompt.
 * Strips newlines, trims, truncates to prevent injection.
 */
function sanitize_trip_text(?string $text, int $maxLen = 200): string
{
    if ($text === null || $text === '') {
        return '';
    }
    return trim(str_replace(array("\r\n", "\n"), ' ', mb_substr($text, 0, $maxLen)));
}
