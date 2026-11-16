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
$rating = (int) ($input['rating'] ?? 0);
$comment = trim((string) ($input['comment'] ?? ''));
$reviewerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);

if ($orderId <= 0 || $rating < 1 || $rating > 5 || strlen($comment) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Please provide a rating from 1 to 5 and a comment up to 1000 characters.']);
    exit;
}

global $pdo;
try {
    $pdo->beginTransaction();

    $orderStmt = $pdo->prepare('SELECT order_id, seller_id, status FROM orders WHERE order_id = ? AND buyer_id = ? LIMIT 1');
    $orderStmt->execute([$orderId, $reviewerId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order || $order['status'] !== 'Completed') {
        throw new RuntimeException('Only completed orders can be reviewed.');
    }

    $reviewStmt = $pdo->prepare('INSERT INTO reviews (order_id, reviewer_id, reviewee_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
    $reviewStmt->execute([$orderId, $reviewerId, (int) $order['seller_id'], $rating, $comment !== '' ? $comment : null]);

    $scoreStmt = $pdo->prepare('SELECT COALESCE(AVG(rating), 5.00) FROM reviews WHERE reviewee_id = ?');
    $scoreStmt->execute([(int) $order['seller_id']]);
    $score = min(5, max(0, round((float) $scoreStmt->fetchColumn(), 2)));
    $updateStmt = $pdo->prepare('UPDATE users SET trust_score = ? WHERE user_id = ?');
    $updateStmt->execute([$score, (int) $order['seller_id']]);

    $pdo->commit();
    echo json_encode(['success' => true, 'rating' => $rating, 'comment' => $comment]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = $e instanceof PDOException && $e->getCode() === '23000' ? 'This order has already been reviewed.' : $e->getMessage();
    echo json_encode(['success' => false, 'message' => $message]);
}