<?php

declare(strict_types=1);

/**
 * Integration tests for lib/spot-actions.php and lib/footprint-actions.php
 *
 * Requires a MySQL database configured in .env.
 * Creates temporary test data, runs CRUD operations, then cleans up.
 *
 * Run: php tests/db_test.php
 *
 * Note: Tests are sequential and assert against DB state after each operation.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/spot-actions.php';
require_once __DIR__ . '/../lib/footprint-actions.php';

$pass = 0;
$fail = 0;

function test(string $label, bool|Closure $condition): void
{
    global $pass, $fail;
    $result = $condition instanceof Closure ? $condition() : $condition;
    if ($result) {
        echo "  ✅ {$label}\n";
        $pass++;
    } else {
        echo "  ❌ {$label}\n";
        $fail++;
    }
}

function section(string $name): void
{
    echo "\n─── {$name} ───\n";
}

// ---------------------------------------------------------------------------
// Setup: create a test user and trip
// ---------------------------------------------------------------------------
section('Setup');

$pdo = pdo();

// Create a temp users table if it doesn't exist (for test isolation)
$pdo->exec("CREATE TEMPORARY TABLE IF NOT EXISTS _test_users LIKE users");
// Alternative approach: just use existing tables with unique test data

$testSuffix = '_test_' . time();
$testEmail  = "spot_test_{$testSuffix}@example.com";
$testTripTitle = "Test Trip {$testSuffix}";

// Create test user
$stmt = $pdo->prepare("INSERT INTO users (email, password, display_name, role) VALUES (?, ?, ?, 'planner')");
$stmt->execute([$testEmail, password_hash('test', PASSWORD_DEFAULT), "Test User {$testSuffix}"]);
$testUserId = (int) $pdo->lastInsertId();
echo "  Test user ID: {$testUserId}\n";

// Create test trip
$stmt = $pdo->prepare("INSERT INTO trips (author_id, title, is_published) VALUES (?, ?, 0)");
$stmt->execute([$testUserId, $testTripTitle]);
$testTripId = (int) $pdo->lastInsertId();
echo "  Test trip ID: {$testTripId}\n";

// ---------------------------------------------------------------------------
// save_trip_spots — create new spots
// ---------------------------------------------------------------------------
section('save_trip_spots — create');

$rawSpots = [
    ['name' => 'Spot A', 'latitude' => '25.0330', 'longitude' => '121.5654', 'notes' => 'First spot'],
    ['name' => 'Spot B', 'latitude' => '25.0478', 'longitude' => '121.5170', 'notes' => 'Second spot'],
    ['name' => 'Spot C', 'latitude' => '24.0', 'longitude' => '121.0',   'notes' => 'Third spot'],
];

save_trip_spots($testTripId, $rawSpots);

$spots = get_trip_spots($testTripId);
test('3 spots created', count($spots) === 3);

test('Spot A has correct name', $spots[0]['name'] === 'Spot A');
test('Spot A sort_order = 1', (int) $spots[0]['sort_order'] === 1);
test('Spot A latitude is float 25.033', abs((float) $spots[0]['latitude'] - 25.033) < 0.001);
test('Spot A longitude is float 121.5654', abs((float) $spots[0]['longitude'] - 121.5654) < 0.001);

test('Spot B sort_order = 2', (int) $spots[1]['sort_order'] === 2);
test('Spot C sort_order = 3', (int) $spots[2]['sort_order'] === 3);

// ---------------------------------------------------------------------------
// save_trip_spots — update existing, add new, delete removed
// ---------------------------------------------------------------------------
section('save_trip_spots — update / delete / insert');

$existingId = (int) $spots[0]['id'];

// Rename Spot A → Spot A Updated, reorder, drop Spot B, add Spot D
$updatedSpots = [
    ['id' => (string) $existingId, 'name' => 'Spot A Updated', 'latitude' => '25.0330', 'longitude' => '121.5654', 'notes' => 'Updated'],
    ['name' => 'Spot D', 'latitude' => '25.0000', 'longitude' => '121.5000', 'notes' => 'New spot'],
];

save_trip_spots($testTripId, $updatedSpots);

$spots2 = get_trip_spots($testTripId);
test('2 spots remain after update (B deleted)', count($spots2) === 2);

test('Spot A renamed to "Spot A Updated"', $spots2[0]['name'] === 'Spot A Updated');
test('Spot A sort_order = 1', (int) $spots2[0]['sort_order'] === 1);
test('Spot A notes updated', $spots2[0]['notes'] === 'Updated');

test('Spot D is sort_order = 2', (int) $spots2[1]['sort_order'] === 2);
test('Spot D name correct', $spots2[1]['name'] === 'Spot D');

// ---------------------------------------------------------------------------
// reorder_trip_spots
// ---------------------------------------------------------------------------
section('reorder_trip_spots');

$allIds = array_map(fn($s) => (int) $s['id'], $spots2);
$reordered = array_reverse($allIds);
reorder_trip_spots($testTripId, $reordered);

$spots3 = get_trip_spots($testTripId);
test('spots reordered (reverse)', (int) $spots3[0]['id'] === $reordered[0]);
test('new sort_order[0] = 1', (int) $spots3[0]['sort_order'] === 1);
test('new sort_order[1] = 2', (int) $spots3[1]['sort_order'] === 2);
test('Spot D moved to position 1', $spots3[0]['name'] === 'Spot D');
test('Spot A moved to position 2', $spots3[1]['name'] === 'Spot A Updated');

// ---------------------------------------------------------------------------
// delete_trip_spot
// ---------------------------------------------------------------------------
section('delete_trip_spot');

$deleted = delete_trip_spot((int) $spots3[1]['id'], $testTripId, $testUserId);
test('delete_trip_spot returns true', $deleted === true);

$spots4 = get_trip_spots($testTripId);
test('1 spot remains after delete', count($spots4) === 1);
test('remaining spot is Spot D', $spots4[0]['name'] === 'Spot D');

// Try deleting with wrong user (should fail)
$deleted2 = delete_trip_spot((int) $spots4[0]['id'], $testTripId, 99999);
test('delete with wrong user returns false', $deleted2 === false);

// ---------------------------------------------------------------------------
// save_trip_spots — edge cases
// ---------------------------------------------------------------------------
section('save_trip_spots — edge cases');

test('empty rawSpots deletes all', function() use ($testTripId) {
    save_trip_spots($testTripId, []);
    $remaining = get_trip_spots($testTripId);
    return count($remaining) === 0;
});

// Invalid coordinates
$badSpots = [
    ['name' => 'Bad Lat', 'latitude' => 'abc', 'longitude' => '121.0', 'notes' => 'invalid lat'],
    ['name' => 'Bad Lng', 'latitude' => '25.0', 'longitude' => 'abc',   'notes' => 'invalid lng'],
    ['name' => 'Out of Range', 'latitude' => '100', 'longitude' => '200', 'notes' => 'out of bounds'],
];
save_trip_spots($testTripId, $badSpots);
$badSpotsResult = get_trip_spots($testTripId);

test('spot with invalid lat stores null latitude', $badSpotsResult[0]['latitude'] === null);
test('spot with invalid lng stores null longitude', $badSpotsResult[1]['longitude'] === null);
test('spot with out-of-range coords stores null', $badSpotsResult[2]['latitude'] === null && $badSpotsResult[2]['longitude'] === null);

// Spot with empty name is filtered out
save_trip_spots($testTripId, [
    ['name' => '', 'latitude' => '25.0', 'longitude' => '121.0'],
    ['name' => 'Valid', 'latitude' => '25.1', 'longitude' => '121.1'],
]);
$filteredSpots = get_trip_spots($testTripId);
test('empty name spots filtered out', count($filteredSpots) === 1);
test('only "Valid" spot remains', $filteredSpots[0]['name'] === 'Valid');

// ---------------------------------------------------------------------------
// Footprint CRUD
// ---------------------------------------------------------------------------
section('Traveler Footprint CRUD');

$fpId = add_traveler_footprint($testUserId, $testTripId, 'Footprint A', 25.0330, 121.5654, null, 'My footprint', null);
test('footprint created with ID > 0', $fpId > 0);

$footprints = get_traveler_footprints($testUserId);
test('footprint appears in get_traveler_footprints', count($footprints) >= 1);
$found = array_filter($footprints, fn($fp) => (int) $fp['id'] === $fpId);
test('footprint has correct name', !empty($found) && $found[array_key_first($found)]['name'] === 'Footprint A');

$deletedFp = delete_traveler_footprint($fpId, $testUserId);
test('delete_traveler_footprint returns true', $deletedFp === true);

$footprints2 = get_traveler_footprints($testUserId);
$found2 = array_filter($footprints2, fn($fp) => (int) $fp['id'] === $fpId);
test('footprint deleted', empty($found2));

// ---------------------------------------------------------------------------
// Cleanup
// ---------------------------------------------------------------------------
section('Cleanup');

$pdo->prepare("DELETE FROM trip_spots WHERE trip_id = ?")->execute([$testTripId]);
$pdo->prepare("DELETE FROM trips WHERE id = ?")->execute([$testTripId]);
$pdo->prepare("DELETE FROM traveler_footprints WHERE user_id = ?")->execute([$testUserId]);
$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$testUserId]);

echo "\n─── 結果 ───\n";
echo "  通過: {$pass}\n";
echo "  失敗: {$fail}\n";
echo "\n";

exit($fail > 0 ? 1 : 0);
