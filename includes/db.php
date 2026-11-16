<?php
require_once __DIR__ . '/../config.php';

function getDatabaseConnection(): PDO {
    $mysqlDsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($mysqlDsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
    ]);
    ensureAccountRecoverySchema($pdo);
    ensureSellerListingNotificationSchema($pdo);
    ensureAccountWarningSchema($pdo);
    return $pdo;
}

function ensureAccountRecoverySchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS account_recovery_requests (
            request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            reason TEXT NOT NULL,
                status ENUM("pending", "approved", "rejected") NOT NULL DEFAULT "pending",
            admin_response TEXT DEFAULT NULL,
            reviewed_by INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (request_id),
            KEY idx_recovery_user (user_id),
            KEY idx_recovery_status (status),
            CONSTRAINT fk_recovery_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
            CONSTRAINT fk_recovery_admin FOREIGN KEY (reviewed_by) REFERENCES users (user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
}

function ensureSellerListingNotificationSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS seller_listing_notification_reads (
            user_id INT UNSIGNED NOT NULL,
            last_seen_log_id INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            CONSTRAINT fk_listing_notification_reads_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
}

function ensureAccountWarningSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS account_warning_reads (
            user_id INT UNSIGNED NOT NULL,
            last_seen_log_id INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            CONSTRAINT fk_account_warning_reads_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
}

function ensureSqliteSchema(PDO $pdo): void {
    // Not needed for MySQL
}

function getAllowedDomains(): array
{
    global $pdo;

    try {
        if (!isset($pdo) || !$pdo instanceof PDO) {
            $pdo = getDatabaseConnection();
        }
        $stmt = $pdo->query('SELECT email_domain FROM universities WHERE email_domain IS NOT NULL AND email_domain <> ""');
        $domains = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter(array_map('strtolower', $domains)));
    } catch (PDOException $exception) {
        return [];
    }
}

function isAllowedEmail(string $email): bool
{
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    return in_array($domain, getAllowedDomains(), true);
}

function passwordMeetsRequirements(string $password): bool
{
    return preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,32}$/', $password) === 1;
}

function findUserByEmail(PDO $pdo, string $email): ?array
{
    // 在 user_id 和其他字段前加上明确的表名前缀 (users.)，并将 wallets 的 balance 别名化
    $stmt = $pdo->prepare('SELECT users.user_id as id, users.full_name, users.student_id, users.email, users.phone_number, users.password_hash, users.role, users.university_id, wallets.balance as wallet_balance FROM users LEFT JOIN wallets ON users.user_id = wallets.user_id WHERE users.email = :email LIMIT 1');
    $stmt->execute(['email' => strtolower(trim($email))]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function registerStudent(PDO $pdo, string $fullName, string $studentId, string $email, string $phone, string $password): array
{
    $email = strtolower(trim($email));
    $fullName = trim($fullName);
    $studentId = trim($studentId);

    if (!isAllowedEmail($email)) {
        return ['ok' => false, 'message' => 'Registration is currently restricted to official student emails of partnered universities.'];
    }

    if (!passwordMeetsRequirements($password)) {
        return ['ok' => false, 'message' => 'Password must include uppercase, lowercase, number, special character, and be 8-32 characters.'];
    }

    if (!preg_match('/^\d{10,11}$/', $phone)) {
        return ['ok' => false, 'message' => 'Phone number must contain 10 to 11 digits only.'];
    }

    if (empty($fullName) || empty($studentId)) {
        return ['ok' => false, 'message' => 'Please complete all required fields.'];
    }

    if (findUserByEmail($pdo, $email)) {
        return ['ok' => false, 'message' => 'An account with this email or Student ID already exists.'];
    }

    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE student_id = :student_id LIMIT 1');
    $stmt->execute(['student_id' => $studentId]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'message' => 'An account with this email or Student ID already exists.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    
    $uniStmt = $pdo->prepare('SELECT university_id FROM universities WHERE email_domain = :domain LIMIT 1');
    $uniStmt->execute(['domain' => $domain]);
    $uni = $uniStmt->fetch();
    $universityId = $uni['university_id'] ?? 1;

    $stmt = $pdo->prepare('INSERT INTO users (university_id, full_name, student_id, email, phone_number, password_hash, role, status) VALUES (:university_id, :full_name, :student_id, :email, :phone_number, :password_hash, :role, :status)');
    $stmt->execute([
        'university_id' => $universityId,
        'full_name' => $fullName,
        'student_id' => $studentId,
        'email' => $email,
        'phone_number' => $phone,
        'password_hash' => $hash,
        'role' => 'student',
        'status' => 'active',
    ]);

    $userId = $pdo->lastInsertId();
    
    // 只创建钱包，初始余额设定为 0.00，不再插入多余的 150 块钱充值记录
    $pdo->prepare('INSERT INTO wallets (user_id, balance) VALUES (:user_id, 0.00)')->execute(['user_id' => $userId]);

    return ['ok' => true, 'message' => 'Registration successful.'];
}

function loginStudent(PDO $pdo, string $email, string $password): array
{
    $email = strtolower(trim($email));
    $stmt = $pdo->prepare('SELECT u.user_id, u.full_name, u.student_id, u.email, u.role, u.university_id, u.status, w.balance FROM users u LEFT JOIN wallets w ON u.user_id = w.user_id WHERE u.email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['ok' => false, 'message' => 'Invalid email address or password.'];
    }

    $passStmt = $pdo->prepare('SELECT password_hash FROM users WHERE email = :email');
    $passStmt->execute(['email' => $email]);
    $passRecord = $passStmt->fetch();
    if (!$passRecord || !password_verify($password, $passRecord['password_hash'])) {
        return ['ok' => false, 'message' => 'Invalid email address or password.'];
    }

    if ($user['status'] === 'suspended') {
        $requestStmt = $pdo->prepare('
            SELECT status, admin_response
            FROM account_recovery_requests
            WHERE user_id = :user_id
            ORDER BY request_id DESC
            LIMIT 1
        ');
        $requestStmt->execute(['user_id' => $user['user_id']]);
        $recoveryRequest = $requestStmt->fetch();
        $message = 'Your account has been suspended. You can submit an account recovery request below.';
        if ($recoveryRequest && $recoveryRequest['status'] === 'pending') {
            $message = 'Your account recovery request is pending administrator review.';
        } elseif ($recoveryRequest && $recoveryRequest['status'] === 'rejected' && trim((string) $recoveryRequest['admin_response']) !== '') {
            $message .= ' Administrator response: ' . $recoveryRequest['admin_response'];
        }
        return ['ok' => false, 'message' => $message, 'suspended' => true, 'email' => $user['email']];
    }

    $uniStmt = $pdo->prepare('SELECT university_name FROM universities WHERE university_id = :id');
    $uniStmt->execute(['id' => $user['university_id']]);
    $uniRecord = $uniStmt->fetch();

    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['user'] = [
        'id' => (int) $user['user_id'],
        'user_id' => (int) $user['user_id'],
        'full_name' => $user['full_name'],
        'student_id' => $user['student_id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'university_id' => $user['university_id'],
        'university' => $uniRecord['university_name'] ?? 'Unknown',
        'wallet_balance' => (float) ($user['balance'] ?? 0),
    ];

    return ['ok' => true, 'user' => $_SESSION['user']];
}

function requireLogin(): void
{
    if (empty($_SESSION['user'])) {
        // 检测是否在 admin 目录，如果是则使用相对路径返回根目录
        if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
            header('Location: ../index.php');
        } else {
            header('Location: index.php');
        }
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        $_SESSION['flash_error'] = 'Unauthorized access. Administrative privileges required.';
        header('Location: ../home.php');
        exit;
    }
}
