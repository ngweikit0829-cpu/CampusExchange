<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = (int) ($input['order_id'] ?? 0);
$buyerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$result = cancelEscrowOrderForBuyer($buyerId, $orderId);

echo json_encode([
    'success' => $result['ok'],
    'message' => $result['message'],
    'new_balance' => $result['new_balance'] ?? null
]);