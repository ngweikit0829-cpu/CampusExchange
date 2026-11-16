<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = (int) ($input['order_id'] ?? 0);
$buyerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order id.']);
    exit;
}

$result = completeEscrowOrderForBuyer($buyerId, $orderId);
if (!$result['ok']) {
    echo json_encode(['success' => false, 'message' => $result['message']]);
    exit;
}

echo json_encode(['success' => true, 'message' => $result['message']]);
