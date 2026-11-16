<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['messages' => []]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$productId = (int) ($input['product_id'] ?? ($_GET['product_id'] ?? 0));
$partnerId = (int) ($input['partner_id'] ?? ($_GET['partner_id'] ?? 0));
$action = strtolower(trim((string) ($input['action'] ?? ($_GET['action'] ?? ''))));
$includeAllMessages = $productId <= 0 && $action !== 'conversations';

if ($action === 'mark_read') {
    $userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
    if ($productId <= 0 || $partnerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid conversation.']);
        exit;
    }

    $markReadStmt = $pdo->prepare('UPDATE chat_messages SET is_read = 1 WHERE product_id = ? AND receiver_id = ? AND sender_id = ? AND is_read = 0');
    $markReadStmt->execute([$productId, $userId, $partnerId]);
    $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0');
    $unreadStmt->execute([$userId]);

    echo json_encode(['success' => true, 'unread_count' => (int) $unreadStmt->fetchColumn()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiverId = (int) ($input['receiver_id'] ?? 0);
    $messageText = trim((string) ($input['message'] ?? ''));
    $userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);

    if ($productId <= 0 || $receiverId <= 0 || $messageText === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid chat payload.']);
        exit;
    }
    $messageLength = function_exists('mb_strlen') ? mb_strlen($messageText, 'UTF-8') : strlen($messageText);
    if ($messageLength > 500) {
        echo json_encode(['success' => false, 'message' => 'Messages cannot exceed 500 characters.']);
        exit;
    }

    $participantStmt = $pdo->prepare('SELECT seller_id FROM products WHERE product_id = ? AND status <> "Deleted" LIMIT 1');
    $participantStmt->execute([$productId]);
    $sellerId = (int) ($participantStmt->fetchColumn() ?: 0);
    if ($sellerId <= 0 || $userId === $receiverId || ($userId !== $sellerId && $receiverId !== $sellerId)) {
        echo json_encode(['success' => false, 'message' => 'You are not allowed to message this product participant.']);
        exit;
    }

    try {
        $pdo->prepare('INSERT INTO chat_messages (product_id, sender_id, receiver_id, message_text, is_read) VALUES (?, ?, ?, ?, 0)')
            ->execute([$productId, $userId, $receiverId, $messageText]);

        echo json_encode(['success' => true]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'conversations') {
    $userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT
            p.product_id,
            p.title AS product_title,
            p.status AS product_status,
            seller.user_id AS seller_id,
                        partner.user_id AS partner_id,
                        partner.full_name AS partner_name,
            COALESCE(img.image_url, 'assets/images/default.jpg') AS product_image,
                        partner_uni.university_name AS campus,
            MAX(cm.created_at) AS last_message_at,
            COUNT(DISTINCT unread_cm.message_id) AS unread_count,
            (
                SELECT cm2.message_text
                FROM chat_messages cm2
                WHERE cm2.product_id = p.product_id
                                    AND ((cm2.sender_id = :user_id AND cm2.receiver_id = partner.user_id)
                                        OR (cm2.receiver_id = :user_id AND cm2.sender_id = partner.user_id))
                ORDER BY cm2.created_at DESC
                LIMIT 1
            ) AS last_message
        FROM chat_messages cm
        INNER JOIN products p ON p.product_id = cm.product_id
        LEFT JOIN users seller ON seller.user_id = p.seller_id
        INNER JOIN users partner ON partner.user_id = CASE
            WHEN cm.sender_id = :user_id THEN cm.receiver_id
            ELSE cm.sender_id
        END
        LEFT JOIN universities partner_uni ON partner_uni.university_id = partner.university_id
        LEFT JOIN product_images img ON img.product_id = p.product_id AND img.is_primary = 1
        LEFT JOIN chat_messages unread_cm
            ON unread_cm.product_id = p.product_id
            AND unread_cm.receiver_id = :user_id
            AND unread_cm.is_read = 0
            AND ((unread_cm.sender_id = :user_id AND unread_cm.receiver_id = partner.user_id)
              OR (unread_cm.receiver_id = :user_id AND unread_cm.sender_id = partner.user_id))
        WHERE cm.sender_id = :user_id OR cm.receiver_id = :user_id
        GROUP BY p.product_id, p.title, p.status, seller.user_id, partner.user_id, partner.full_name, img.image_url, partner_uni.university_name
        ORDER BY last_message_at DESC
    ");
    $stmt->execute(['user_id' => $userId]);
    $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0');
    $unreadStmt->execute([$userId]);
    echo json_encode([
        'conversations' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'unread_count' => (int) $unreadStmt->fetchColumn()
    ]);
    exit;
}

if ($includeAllMessages) {
    $userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT
            cm.message_id,
            cm.product_id,
            cm.sender_id,
            cm.receiver_id,
            cm.message_text,
            cm.is_read,
            cm.created_at,
            sender.full_name AS sender_name,
            receiver.full_name AS receiver_name,
            p.title AS product_title,
            COALESCE(img.image_url, 'assets/images/default.jpg') AS product_image
        FROM chat_messages cm
        LEFT JOIN users sender ON sender.user_id = cm.sender_id
        LEFT JOIN users receiver ON receiver.user_id = cm.receiver_id
        LEFT JOIN products p ON p.product_id = cm.product_id
        LEFT JOIN product_images img ON img.product_id = cm.product_id AND img.is_primary = 1
        WHERE cm.sender_id = :user_id OR cm.receiver_id = :user_id
        ORDER BY cm.created_at ASC
    ");
    $stmt->execute(['user_id' => $userId]);
    echo json_encode(['messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

$userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
echo json_encode(['messages' => getProductChatMessages($productId, $userId, $partnerId)]);
