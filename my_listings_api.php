<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        if ($productId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit;
        }

        $deleteStmt = $pdo->prepare("UPDATE products SET status = 'Deleted' WHERE product_id = :product_id AND seller_id = :seller_id AND status = 'Available'");
        $deleteStmt->execute(['product_id' => $productId, 'seller_id' => $userId]);

        if ($deleteStmt->rowCount() !== 1) {
            echo json_encode(['success' => false, 'message' => 'Only your available products can be deleted.']);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            p.product_id,
            p.seller_id,
            p.title,
            p.description,
            p.price,
            p.item_condition,
            p.created_at,
            CASE
                WHEN p.status = 'Sold' AND EXISTS (
                    SELECT 1 FROM orders o
                    WHERE o.product_id = p.product_id
                      AND o.seller_id = p.seller_id
                      AND o.status = 'Awaiting Handover'
                ) THEN 'Awaiting Handover'
                ELSE p.status
            END AS status,
            c.category_name,
            u.full_name AS seller_name,
            uni.university_name,
            order_info.buyer_name,
            order_info.buyer_university,
            order_info.handover_location,
            order_info.order_status,
            (SELECT al.details FROM admin_logs al WHERE al.target_product_id = p.product_id AND al.action = 'delete_listing' ORDER BY al.created_at DESC, al.log_id DESC LIMIT 1) AS removal_reason,
            (SELECT al.created_at FROM admin_logs al WHERE al.target_product_id = p.product_id AND al.action = 'delete_listing' ORDER BY al.created_at DESC, al.log_id DESC LIMIT 1) AS removed_at,
            COALESCE(img.image_url, 'assets/images/default.jpg') AS primary_image
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        JOIN users u ON p.seller_id = u.user_id
        JOIN universities uni ON p.university_id = uni.university_id
        LEFT JOIN product_images img ON p.product_id = img.product_id AND img.is_primary = 1
        LEFT JOIN (
            SELECT latest_order.product_id,
                   buyer.full_name AS buyer_name,
                   buyer_university.university_name AS buyer_university,
                   latest_order.handover_location,
                   latest_order.status AS order_status
            FROM orders latest_order
            INNER JOIN users buyer ON buyer.user_id = latest_order.buyer_id
            LEFT JOIN universities buyer_university ON buyer_university.university_id = buyer.university_id
            INNER JOIN (
                SELECT product_id, MAX(order_id) AS latest_order_id
                FROM orders
                WHERE status <> 'Cancelled'
                GROUP BY product_id
            ) latest ON latest.product_id = latest_order.product_id AND latest.latest_order_id = latest_order.order_id
            WHERE latest_order.status <> 'Cancelled'
        ) order_info ON order_info.product_id = p.product_id
        WHERE p.seller_id = :seller_id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute(['seller_id' => $userId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
