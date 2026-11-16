<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$warningId = (int) ($_POST['warning_id'] ?? 0);
$userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);

if ($warningId <= 0 || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid warning.']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $warningStmt = $pdo->prepare('SELECT log_id FROM admin_logs WHERE log_id = :log_id AND action = "warn_user" AND target_user_id = :user_id LIMIT 1');
    $warningStmt->execute(['log_id' => $warningId, 'user_id' => $userId]);
    if (!$warningStmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Warning not found.']);
        exit;
    }

    $readStmt = $pdo->prepare('
        INSERT INTO account_warning_reads (user_id, last_seen_log_id)
        VALUES (:user_id, :last_seen_log_id)
        ON DUPLICATE KEY UPDATE last_seen_log_id = GREATEST(last_seen_log_id, VALUES(last_seen_log_id))
    ');
    $readStmt->execute(['user_id' => $userId, 'last_seen_log_id' => $warningId]);
    echo json_encode(['success' => true]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update warning status.']);
}