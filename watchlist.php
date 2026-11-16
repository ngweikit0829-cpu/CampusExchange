<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$productId = (int) ($input['product_id'] ?? 0);
$action = strtolower(trim((string) ($input['action'] ?? 'toggle')));
$userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);

try {
    if ($action === 'list') {
        echo json_encode(['success' => true, 'items' => getUserWatchlistProductIds($userId)]);
        exit;
    }

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product selected.']);
        exit;
    }

    if ($action === 'remove') {
        $ok = removeWatchlistItem($userId, $productId);
        $count = getUserDashboardStats($userId)['watchlist_count'];
        echo json_encode(['success' => $ok, 'count' => $count, 'is_favorited' => false]);
        exit;
    }

    if ($action === 'add') {
        $ok = addWatchlistItem($userId, $productId);
        $count = getUserDashboardStats($userId)['watchlist_count'];
        echo json_encode(['success' => $ok, 'count' => $count, 'is_favorited' => true]);
        exit;
    }

    $existing = getUserWatchlistProductIds($userId);
    $isFavorited = in_array($productId, $existing, true);

    if ($isFavorited) {
        $ok = removeWatchlistItem($userId, $productId);
        $newState = false;
    } else {
        $ok = addWatchlistItem($userId, $productId);
        $newState = true;
    }

    $count = getUserDashboardStats($userId)['watchlist_count'];
    echo json_encode(['success' => $ok, 'count' => $count, 'is_favorited' => $newState]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
