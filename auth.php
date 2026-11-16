<?php
require_once __DIR__ . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = getDatabaseConnection();

if ($action === 'login') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $result = loginStudent($pdo, $email, $password);

    if (!$result['ok']) {
        $_SESSION['flash_error'] = $result['message'];
        if (!empty($result['suspended'])) {
            $_SESSION['suspended_email'] = $result['email'] ?? $email;
            $_SESSION['show_recovery_form'] = true;
        }
        header('Location: index.php');
        exit;
    }

    if (($_SESSION['user']['role'] ?? '') === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    }

    header('Location: home.php');
    exit;
}

if ($action === 'request_password_reset') {
    header('Content-Type: application/json; charset=utf-8');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    $domain = strtolower(substr(strrchr($email, '@'), 1));
    $domainStmt = $pdo->prepare('SELECT 1 FROM universities WHERE LOWER(email_domain) = :domain LIMIT 1');
    $domainStmt->execute(['domain' => $domain]);
    if (!$domainStmt->fetch()) {
        echo json_encode(['ok' => false, 'message' => 'Please use an email address from a partner university.']);
        exit;
    }

    $userStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
    $userStmt->execute(['email' => $email]);
    if (!$userStmt->fetch()) {
        echo json_encode(['ok' => false, 'message' => 'This university email is not registered.']);
        exit;
    }

    echo json_encode(['ok' => true, 'message' => 'Please check your email to reset your password.']);
    exit;
}

if ($action === 'submit_recovery_request') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $reason = trim((string) ($_POST['recovery_reason'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $reason === '' || strlen($reason) > 2000) {
        $_SESSION['flash_error'] = 'Enter a valid email and a recovery reason of 1 to 2000 characters.';
        $_SESSION['show_recovery_form'] = true;
        $_SESSION['suspended_email'] = $email;
        header('Location: index.php');
        exit;
    }

    $userStmt = $pdo->prepare('SELECT user_id, full_name, status FROM users WHERE email = :email AND role = "student" LIMIT 1');
    $userStmt->execute(['email' => $email]);
    $student = $userStmt->fetch();
    if (!$student || $student['status'] !== 'suspended') {
        $_SESSION['flash_error'] = 'No suspended student account was found for this email.';
        $_SESSION['show_recovery_form'] = true;
        $_SESSION['suspended_email'] = $email;
        header('Location: index.php');
        exit;
    }

    $pendingStmt = $pdo->prepare('SELECT request_id FROM account_recovery_requests WHERE user_id = :user_id AND status = "pending" LIMIT 1');
    $pendingStmt->execute(['user_id' => $student['user_id']]);
    if ($pendingStmt->fetch()) {
        $_SESSION['flash_error'] = 'You already have a pending recovery request.';
    } else {
        $requestStmt = $pdo->prepare('INSERT INTO account_recovery_requests (user_id, reason) VALUES (:user_id, :reason)');
        $requestStmt->execute(['user_id' => $student['user_id'], 'reason' => $reason]);
        $_SESSION['flash_success'] = 'Your recovery request has been submitted to the administrator.';
    }
    $_SESSION['show_recovery_form'] = true;
    $_SESSION['suspended_email'] = $email;
    header('Location: index.php');
    exit;
}

if ($action === 'register') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $studentId = trim((string)($_POST['student_id'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone_number'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($password !== $confirm) {
        $_SESSION['flash_error'] = 'Passwords do not match.';
        header('Location: register.php');
        exit;
    }

    $result = registerStudent($pdo, $fullName, $studentId, $email, $phone, $password);
    if (!$result['ok']) {
        $_SESSION['flash_error'] = $result['message'];
        header('Location: register.php');
        exit;
    }

    $_SESSION['flash_success'] = 'Registration successful. You can now log in.';
    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;
