<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['transactions' => []]);
    exit;
}

$userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$transactions = getWalletTransactionsForUser($userId);

echo json_encode(['transactions' => $transactions]);
