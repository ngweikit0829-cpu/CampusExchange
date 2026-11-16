<?php
require_once __DIR__ . '/db.php';

function flash(string $key): ?string
{
    if (!isset($_SESSION[$key])) {
        return null;
    }

    $value = $_SESSION[$key];
    unset($_SESSION[$key]);
    return $value;
}

function getCurrentUser() {
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $userSession = $_SESSION['user'] ?? [];
    $userId = $_SESSION['user_id'] ?? ($userSession['id'] ?? $userSession['user_id'] ?? null);
    if (!$userId) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT 
            u.user_id AS id,
            u.user_id,
            u.full_name,
            u.student_id,
            u.email,
            u.phone_number,
            u.role,
            u.status,
            u.university_id,
            uni.university_name AS university,
            uni.theme_color,
            COALESCE(w.balance, 0.00) AS wallet_balance
        FROM users u
        LEFT JOIN wallets w ON u.user_id = w.user_id
        LEFT JOIN universities uni ON u.university_id = uni.university_id
        WHERE u.user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return null;
    }

    return $user;
}

function getWalletTransactionsForUser(int $userId): array
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $stmt = $pdo->prepare('
        SELECT
            wt.transaction_ref,
            wt.transaction_type,
            wt.amount,
            wt.resulting_balance,
            wt.created_at
        FROM wallets w
        INNER JOIN wallet_transactions wt ON wt.wallet_id = w.wallet_id
        WHERE w.user_id = :user_id
        ORDER BY wt.created_at DESC, wt.transaction_id DESC
        LIMIT 20
    ');
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserDashboardStats(int $userId): array
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $unreadStmt = $pdo->prepare('SELECT COUNT(*) AS unread_count FROM chat_messages WHERE receiver_id = :user_id AND is_read = 0');
    $unreadStmt->execute(['user_id' => $userId]);
    $unreadCount = (int) ($unreadStmt->fetch(PDO::FETCH_ASSOC)['unread_count'] ?? 0);

    $sellerOrdersStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE seller_id = :user_id AND status = "Awaiting Handover"');
    $sellerOrdersStmt->execute(['user_id' => $userId]);
    $sellerOrdersCount = (int) $sellerOrdersStmt->fetchColumn();

    $lastSeenStmt = $pdo->prepare('SELECT last_seen_log_id FROM seller_listing_notification_reads WHERE user_id = :user_id LIMIT 1');
    $lastSeenStmt->execute(['user_id' => $userId]);
    $lastSeenRemovedLogId = (int) ($lastSeenStmt->fetchColumn() ?? 0);

    $removedListingsStmt = $pdo->prepare('
        SELECT COUNT(DISTINCT p.product_id)
        FROM admin_logs al
        INNER JOIN products p ON p.product_id = al.target_product_id
                WHERE al.action = "delete_listing"
                    AND p.seller_id = :user_id
                    AND al.log_id > :last_seen_log_id
    ');
        $removedListingsStmt->execute(['user_id' => $userId, 'last_seen_log_id' => $lastSeenRemovedLogId]);
    $removedListingsCount = (int) $removedListingsStmt->fetchColumn();

    $watchlistStmt = $pdo->prepare('
        SELECT COUNT(*) AS watchlist_count
        FROM watchlists w
        INNER JOIN products p ON p.product_id = w.product_id
        WHERE w.user_id = :user_id AND p.status = "Available"
    ');
    $watchlistStmt->execute(['user_id' => $userId]);
    $watchlistCount = (int) ($watchlistStmt->fetch(PDO::FETCH_ASSOC)['watchlist_count'] ?? 0);

    $profileStmt = $pdo->prepare('SELECT trust_score, completed_trades FROM users WHERE user_id = :user_id LIMIT 1');
    $profileStmt->execute(['user_id' => $userId]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: ['trust_score' => 5.00, 'completed_trades' => 0];

    return [
        'unread_messages' => $unreadCount,
        'seller_orders_count' => $sellerOrdersCount,
        'seller_removed_listings_count' => $removedListingsCount,
        'watchlist_count' => $watchlistCount,
        'trust_score' => (float) ($profile['trust_score'] ?? 5.00),
        'completed_trades' => (int) ($profile['completed_trades'] ?? 0),
    ];
}

function getUserWarnings(int $userId): array
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $stmt = $pdo->prepare('
        SELECT al.log_id, al.details, al.created_at, admin.full_name AS admin_name
        FROM admin_logs al
        INNER JOIN users admin ON admin.user_id = al.admin_id
        WHERE al.action = "warn_user" AND al.target_user_id = :user_id
        ORDER BY al.created_at DESC, al.log_id DESC
        LIMIT 10
    ');
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAccountRecoveryRequests(int $userId): array
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $stmt = $pdo->prepare('
        SELECT request_id, reason, status, admin_response, created_at, reviewed_at
        FROM account_recovery_requests
        WHERE user_id = :user_id
        ORDER BY request_id DESC
        LIMIT 10
    ');
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getReviewsForUser(int $userId): array
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $stmt = $pdo->prepare('
        SELECT
            r.rating,
            r.comment,
            r.created_at,
            reviewer.full_name AS reviewer_name,
            p.title AS product_title
        FROM reviews r
        INNER JOIN users reviewer ON reviewer.user_id = r.reviewer_id
        INNER JOIN orders o ON o.order_id = r.order_id
        INNER JOIN products p ON p.product_id = o.product_id
        WHERE r.reviewee_id = :user_id
        ORDER BY r.created_at DESC, r.review_id DESC
    ');
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserWatchlistProductIds(int $userId): array
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $stmt = $pdo->prepare('
        SELECT w.product_id
        FROM watchlists w
        INNER JOIN products p ON p.product_id = w.product_id
        WHERE w.user_id = :user_id AND p.status = "Available"
        ORDER BY w.created_at DESC
    ');
    $stmt->execute(['user_id' => $userId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
}

function addWatchlistItem(int $userId, int $productId): bool
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    if ($userId <= 0 || $productId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('INSERT IGNORE INTO watchlists (user_id, product_id) VALUES (:user_id, :product_id)');
    return $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);
}

function removeWatchlistItem(int $userId, int $productId): bool
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    if ($userId <= 0 || $productId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('DELETE FROM watchlists WHERE user_id = :user_id AND product_id = :product_id');
    return $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);
}

function getUserWalletBalance(int $userId): float
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $stmt = $pdo->prepare('SELECT COALESCE(balance, 0.00) AS balance FROM wallets WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (float) ($row['balance'] ?? 0.00);
}

function getUserOrders(int $userId): array
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $stmt = $pdo->prepare('
        SELECT
            o.order_id,
            o.order_ref,
            o.product_id,
            o.buyer_id,
            o.seller_id,
            o.price_amount AS price,
            o.handover_location,
            o.status,
            o.created_at,
            p.title AS item_title,
            s.full_name AS seller_name,
            b.full_name AS buyer_name,
            uni.university_name AS campus,
            COALESCE(img.image_url, "assets/images/default.jpg") AS product_image,
            r.review_id,
            r.rating AS review_rating,
            r.comment AS review_comment
        FROM orders o
        INNER JOIN products p ON p.product_id = o.product_id
        INNER JOIN users s ON s.user_id = o.seller_id
        INNER JOIN users b ON b.user_id = o.buyer_id
        INNER JOIN universities uni ON uni.university_id = p.university_id
        LEFT JOIN product_images img ON img.product_id = p.product_id AND img.is_primary = 1
        LEFT JOIN reviews r ON r.order_id = o.order_id AND r.reviewer_id = o.buyer_id
        WHERE o.buyer_id = :user_id
        ORDER BY o.created_at DESC
    ');
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProductChatMessages(int $productId, int $userId, int $partnerId): array
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $stmt = $pdo->prepare('
        SELECT
            cm.message_id,
            cm.product_id,
            cm.sender_id,
            cm.receiver_id,
            cm.message_text,
            cm.is_read,
            cm.created_at,
            sender.full_name AS sender_name,
            receiver.full_name AS receiver_name
        FROM chat_messages cm
        INNER JOIN users sender ON sender.user_id = cm.sender_id
        INNER JOIN users receiver ON receiver.user_id = cm.receiver_id
                WHERE cm.product_id = :product_id
                    AND ((cm.sender_id = :user_id AND cm.receiver_id = :partner_id)
                        OR (cm.receiver_id = :user_id AND cm.sender_id = :partner_id))
        ORDER BY cm.created_at ASC
    ');
        $stmt->execute(['product_id' => $productId, 'user_id' => $userId, 'partner_id' => $partnerId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createEscrowOrderForBuyer(int $buyerId, int $productId, int $sellerId, float $amount, string $handoverLocation = 'Student Lounge'): array
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    if ($productId <= 0 || $sellerId <= 0 || $amount <= 0) {
        return ['ok' => false, 'message' => 'Invalid product or amount.'];
    }

    $productStmt = $pdo->prepare('SELECT product_id, seller_id, price, status FROM products WHERE product_id = :product_id LIMIT 1');
    $productStmt->execute(['product_id' => $productId]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        return ['ok' => false, 'message' => 'Product no longer exists.'];
    }
    if ((int) $product['seller_id'] !== $sellerId) {
        return ['ok' => false, 'message' => 'Seller mismatch.'];
    }
    if ($product['status'] !== 'Available') {
        return ['ok' => false, 'message' => 'This product is no longer available.'];
    }

    $purchaseAmount = round((float) $product['price'], 2);
    if (round($amount, 2) !== $purchaseAmount) {
        return ['ok' => false, 'message' => 'The product price has changed. Please refresh and try again.'];
    }

    $buyerWalletStmt = $pdo->prepare('SELECT wallet_id, balance FROM wallets WHERE user_id = :user_id FOR UPDATE');
    $buyerWalletStmt->execute(['user_id' => $buyerId]);
    $buyerWallet = $buyerWalletStmt->fetch(PDO::FETCH_ASSOC);
    if (!$buyerWallet) {
        return ['ok' => false, 'message' => 'Wallet not found for current user.'];
    }
    $walletBalance = round((float) $buyerWallet['balance'], 2);
    if ($walletBalance < $purchaseAmount) {
        return ['ok' => false, 'message' => 'Insufficient wallet balance.'];
    }

    try {
        $pdo->beginTransaction();

        $newBalance = (float) $buyerWallet['balance'] - $purchaseAmount;
        $pdo->prepare('UPDATE wallets SET balance = :balance WHERE wallet_id = :wallet_id')
            ->execute(['balance' => $newBalance, 'wallet_id' => $buyerWallet['wallet_id']]);

        $txRef = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $pdo->prepare('INSERT INTO wallet_transactions (transaction_ref, wallet_id, transaction_type, amount, resulting_balance) VALUES (?, ?, "ESCROW_HOLD", ?, ?)')
            ->execute([$txRef, $buyerWallet['wallet_id'], -$purchaseAmount, $newBalance]);

        $orderRef = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $orderStmt = $pdo->prepare('INSERT INTO orders (order_ref, product_id, buyer_id, seller_id, price_amount, handover_location, status) VALUES (?, ?, ?, ?, ?, ?, "Awaiting Handover")');
        $orderStmt->execute([$orderRef, $productId, $buyerId, $sellerId, $purchaseAmount, $handoverLocation]);
        $orderId = (int) $pdo->lastInsertId();

        $pdo->prepare('UPDATE products SET status = "Sold" WHERE product_id = :product_id')
            ->execute(['product_id' => $productId]);

        $pdo->commit();

        return ['ok' => true, 'order_id' => $orderId, 'order_ref' => $orderRef, 'new_balance' => $newBalance];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function completeEscrowOrderForBuyer(int $buyerId, int $orderId): array
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    if ($orderId <= 0) {
        return ['ok' => false, 'message' => 'Invalid order.'];
    }

    $orderStmt = $pdo->prepare('SELECT order_id, product_id, buyer_id, seller_id, price_amount, status FROM orders WHERE order_id = :order_id LIMIT 1');
    $orderStmt->execute(['order_id' => $orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return ['ok' => false, 'message' => 'Order not found.'];
    }
    if ((int) $order['buyer_id'] !== $buyerId) {
        return ['ok' => false, 'message' => 'This order is not yours.'];
    }
    if ($order['status'] === 'Completed') {
        return ['ok' => true, 'message' => 'Order already completed.'];
    }

    try {
        $pdo->beginTransaction();

        $sellerWalletStmt = $pdo->prepare('SELECT wallet_id, balance FROM wallets WHERE user_id = :user_id FOR UPDATE');
        $sellerWalletStmt->execute(['user_id' => $order['seller_id']]);
        $sellerWallet = $sellerWalletStmt->fetch(PDO::FETCH_ASSOC);
        if ($sellerWallet) {
            $sellerBalance = (float) $sellerWallet['balance'] + (float) $order['price_amount'];
            $pdo->prepare('UPDATE wallets SET balance = :balance WHERE wallet_id = :wallet_id')
                ->execute(['balance' => $sellerBalance, 'wallet_id' => $sellerWallet['wallet_id']]);

            $sellerTxRef = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $pdo->prepare('INSERT INTO wallet_transactions (transaction_ref, wallet_id, transaction_type, amount, resulting_balance) VALUES (?, ?, "ESCROW_RELEASE", ?, ?)')
                ->execute([$sellerTxRef, $sellerWallet['wallet_id'], (float) $order['price_amount'], $sellerBalance]);
        }

        $pdo->prepare('UPDATE orders SET status = "Completed", updated_at = NOW() WHERE order_id = :order_id')
            ->execute(['order_id' => $orderId]);

        $pdo->prepare('UPDATE users SET completed_trades = completed_trades + 1 WHERE user_id = :user_id')
            ->execute(['user_id' => $order['seller_id']]);

        $pdo->commit();

        return ['ok' => true, 'message' => 'Order completed and escrow released.'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function cancelEscrowOrderForBuyer(int $buyerId, int $orderId): array
{
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    if ($orderId <= 0) {
        return ['ok' => false, 'message' => 'Invalid order.'];
    }

    try {
        $pdo->beginTransaction();

        $orderStmt = $pdo->prepare('SELECT order_id, product_id, buyer_id, price_amount, status FROM orders WHERE order_id = :order_id FOR UPDATE');
        $orderStmt->execute(['order_id' => $orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }
        if ((int) $order['buyer_id'] !== $buyerId) {
            throw new RuntimeException('This order is not yours.');
        }
        if ($order['status'] !== 'Awaiting Handover') {
            throw new RuntimeException('Only orders awaiting handover can be cancelled.');
        }

        $walletStmt = $pdo->prepare('SELECT wallet_id, balance FROM wallets WHERE user_id = :user_id FOR UPDATE');
        $walletStmt->execute(['user_id' => $buyerId]);
        $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
        if (!$wallet) {
            throw new RuntimeException('Wallet not found for current user.');
        }

        $newBalance = round((float) $wallet['balance'] + (float) $order['price_amount'], 2);
        $pdo->prepare('UPDATE wallets SET balance = :balance WHERE wallet_id = :wallet_id')
            ->execute(['balance' => $newBalance, 'wallet_id' => $wallet['wallet_id']]);

        $txRef = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $pdo->prepare('INSERT INTO wallet_transactions (transaction_ref, wallet_id, transaction_type, amount, resulting_balance) VALUES (?, ?, "REFUND", ?, ?)')
            ->execute([$txRef, $wallet['wallet_id'], (float) $order['price_amount'], $newBalance]);

        $pdo->prepare('UPDATE orders SET status = "Cancelled", updated_at = NOW() WHERE order_id = :order_id')
            ->execute(['order_id' => $orderId]);
        $pdo->prepare('UPDATE products SET status = "Available" WHERE product_id = :product_id')
            ->execute(['product_id' => $order['product_id']]);

        $pdo->commit();
        return ['ok' => true, 'message' => 'Order cancelled and escrow refunded.', 'new_balance' => $newBalance];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}
