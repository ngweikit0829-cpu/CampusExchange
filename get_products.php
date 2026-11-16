<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json');

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $currentUserId = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
    $sql = "
        SELECT 
            p.product_id,
            p.seller_id,
            p.title,
            p.description,
            p.price,
            p.item_condition,
            p.created_at,
            c.category_name,
            u.full_name AS seller_name,
            uni.university_name,
            COALESCE(img.image_url, 'assets/images/default.jpg') AS primary_image
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        JOIN users u ON p.seller_id = u.user_id
        JOIN universities uni ON p.university_id = uni.university_id
        LEFT JOIN product_images img ON p.product_id = img.product_id AND img.is_primary = 1
        WHERE p.status = 'Available'
    ";

    $params = [];
    if ($currentUserId) {
        $sql .= ' AND p.seller_id != :current_user_id';
        $params['current_user_id'] = (int) $currentUserId;
    }

    $sql .= ' ORDER BY p.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>