<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['orders' => []]);
    exit;
}

$userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
echo json_encode(['orders' => getUserOrders($userId)]);
