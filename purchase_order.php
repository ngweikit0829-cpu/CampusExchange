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
$sellerId = (int) ($input['seller_id'] ?? 0);
$amount = (float) ($input['amount'] ?? 0);
$buyerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);

if ($buyerId <= 0 || $productId <= 0 || $sellerId <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid purchase payload.']);
    exit;
}

$result = createEscrowOrderForBuyer($buyerId, $productId, $sellerId, $amount);
if (!$result['ok']) {
    echo json_encode(['success' => false, 'message' => $result['message']]);
    exit;
}

echo json_encode([
    'success' => true,
    'order_ref' => $result['order_ref'],
    'new_balance' => $result['new_balance'] ?? getUserWalletBalance($buyerId),
]);
