<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json');

$user = getCurrentUser();
$userId = $user['user_id'] ?? $user['id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = (float)($input['amount'] ?? 0);

if ($amount < 10 || $amount > 500) {
    echo json_encode(['success' => false, 'message' => '充值金额必须在 RM 10 至 RM 500 之间']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. 检查并确保用户在 wallets 表中有记录
    $stmtCheck = $pdo->prepare("SELECT wallet_id, balance FROM wallets WHERE user_id = ?");
    $stmtCheck->execute([$userId]);
    $wallet = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$wallet) {
        $stmtCreate = $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
        $stmtCreate->execute([$userId]);
        $walletId = $pdo->lastInsertId();
        $currentBalance = 0.00;
    } else {
        $walletId = $wallet['wallet_id'];
        $currentBalance = (float)$wallet['balance'];
    }

    // 2. 更新余额
    $newBalance = $currentBalance + $amount;
    $stmtUpdate = $pdo->prepare("UPDATE wallets SET balance = ? WHERE wallet_id = ?");
    $stmtUpdate->execute([$newBalance, $walletId]);

    // 3. 记录流水到 wallet_transactions 表
    $txRef = 'TXN-' . date('Ymd') . '-' . sprintf('%04d', rand(1000, 9999));
    $stmtTx = $pdo->prepare("
        INSERT INTO wallet_transactions (transaction_ref, wallet_id, transaction_type, amount, resulting_balance)
        VALUES (?, ?, 'TOPUP', ?, ?)
    ");
    $stmtTx->execute([$txRef, $walletId, $amount, $newBalance]);

    $pdo->commit();
    echo json_encode(['success' => true, 'new_balance' => $newBalance]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>