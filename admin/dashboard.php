<?php
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();
$user = getCurrentUser();
$pdo = getDatabaseConnection();
$adminView = $_GET['view'] ?? 'overview';
$allowedAdminViews = ['overview', 'user-moderation', 'listing-moderation', 'removed-listing-history', 'transactions', 'top-up-records', 'logs', 'recovery-requests'];
if (!in_array($adminView, $allowedAdminViews, true)) {
  $adminView = 'overview';
}

$schoolMessage = flash('admin_school_message');
$schoolMessageType = flash('admin_school_message_type') ?? 'success';
$adminActionMessage = flash('admin_action_message');
$adminActionMessageType = flash('admin_action_message_type') ?? 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'add_school') {
  $action = $_POST['action'] ?? '';
  $targetUserId = (int) ($_POST['user_id'] ?? 0);
  $targetProductId = (int) ($_POST['product_id'] ?? 0);
  $recoveryRequestId = (int) ($_POST['request_id'] ?? 0);
  $recoveryDecision = $_POST['recovery_decision'] ?? '';
  $adminResponse = trim($_POST['admin_response'] ?? '');
  $warningComment = trim($_POST['warning_comment'] ?? '');
  $suspendReason = trim($_POST['suspend_reason'] ?? '');
  $deleteReason = trim($_POST['delete_reason'] ?? '');
  $adminId = (int) ($user['id'] ?? $user['user_id'] ?? 0);

  try {
    if ($action === 'review_recovery_request' && $recoveryRequestId > 0) {
      if (!in_array($recoveryDecision, ['approve', 'reject'], true)) {
        throw new RuntimeException('Choose whether to approve or reject the recovery request.');
      }
      if ($adminResponse === '' || strlen($adminResponse) > 2000) {
        throw new RuntimeException('Please provide an administrator response of 1 to 2000 characters.');
      }
      $requestStmt = $pdo->prepare('SELECT request_id, user_id, status FROM account_recovery_requests WHERE request_id = :request_id LIMIT 1');
      $requestStmt->execute(['request_id' => $recoveryRequestId]);
      $recoveryRequest = $requestStmt->fetch();
      if (!$recoveryRequest || $recoveryRequest['status'] !== 'pending') {
        throw new RuntimeException('This recovery request has already been reviewed.');
      }
      $newRequestStatus = $recoveryDecision === 'approve' ? 'approved' : 'rejected';
      $pdo->beginTransaction();
      if ($recoveryDecision === 'approve') {
        $restoreStmt = $pdo->prepare('UPDATE users SET status = "active" WHERE user_id = :user_id AND role = "student" AND status = "suspended"');
        $restoreStmt->execute(['user_id' => $recoveryRequest['user_id']]);
        if ($restoreStmt->rowCount() === 0) {
          throw new RuntimeException('The suspended student could not be restored.');
        }
      }
      $reviewStmt = $pdo->prepare('UPDATE account_recovery_requests SET status = :status, admin_response = :response, reviewed_by = :admin_id, reviewed_at = NOW() WHERE request_id = :request_id AND status = "pending"');
      $reviewStmt->execute(['status' => $newRequestStatus, 'response' => $adminResponse, 'admin_id' => $adminId, 'request_id' => $recoveryRequestId]);
      $logStmt = $pdo->prepare('INSERT INTO admin_logs (admin_id, action, target_user_id, details) VALUES (:admin_id, :action, :target_id, :details)');
      $logStmt->execute([
        'admin_id' => $adminId,
        'action' => $recoveryDecision === 'approve' ? 'approve_recovery_request' : 'reject_recovery_request',
        'target_id' => $recoveryRequest['user_id'],
        'details' => 'Administrator response: ' . $adminResponse,
      ]);
      $pdo->commit();
      $adminActionMessage = $recoveryDecision === 'approve' ? 'Account recovery approved.' : 'Account recovery request rejected.';
    } elseif (in_array($action, ['warn_user', 'suspend_user', 'unsuspend_user'], true) && $targetUserId > 0) {
      if ($action === 'warn_user' && ($warningComment === '' || strlen($warningComment) > 1000)) {
        throw new RuntimeException('Please provide a warning reason of 1 to 1000 characters.');
      }
      if ($action === 'suspend_user' && ($suspendReason === '' || strlen($suspendReason) > 1000)) {
        throw new RuntimeException('Please provide a suspension reason of 1 to 1000 characters.');
      }
      $userAction = [
        'warn_user' => ['UPDATE users SET warning_count = warning_count + 1 WHERE user_id = :id AND role = "student"', 'Warning issued'],
        'suspend_user' => ['UPDATE users SET status = "suspended" WHERE user_id = :id AND role = "student"', 'User suspended'],
        'unsuspend_user' => ['UPDATE users SET status = "active" WHERE user_id = :id AND role = "student"', 'User suspension removed'],
      ][$action];
      $stmt = $pdo->prepare($userAction[0]);
      $stmt->execute(['id' => $targetUserId]);
      if ($stmt->rowCount() === 0) {
        throw new RuntimeException('The selected student could not be updated.');
      }
      $details = $action === 'warn_user'
        ? 'Warning reason: ' . $warningComment
        : ($action === 'suspend_user' ? 'Suspension reason: ' . $suspendReason : $userAction[1]);
      $logStmt = $pdo->prepare('INSERT INTO admin_logs (admin_id, action, target_user_id, details) VALUES (:admin_id, :action, :target_id, :details)');
      $logStmt->execute(['admin_id' => $adminId, 'action' => $action, 'target_id' => $targetUserId, 'details' => $details]);
      $adminActionMessage = $userAction[1] . '.';
    } elseif ($action === 'delete_listing' && $targetProductId > 0) {
      if ($deleteReason === '' || strlen($deleteReason) > 1000) {
        throw new RuntimeException('Please provide a removal reason of 1 to 1000 characters.');
      }
      $stmt = $pdo->prepare('UPDATE products SET status = "Deleted" WHERE product_id = :id AND status = "Available"');
      $stmt->execute(['id' => $targetProductId]);
      if ($stmt->rowCount() === 0) {
        throw new RuntimeException('The selected listing could not be updated.');
      }
      $logStmt = $pdo->prepare('INSERT INTO admin_logs (admin_id, action, target_product_id, details) VALUES (:admin_id, :action, :target_id, :details)');
      $logStmt->execute(['admin_id' => $adminId, 'action' => 'delete_listing', 'target_id' => $targetProductId, 'details' => 'Removal reason: ' . $deleteReason]);
      $adminActionMessage = 'Listing marked as deleted.';
    } else {
      throw new RuntimeException('Invalid administrator action.');
    }
  } catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $adminActionMessage = $exception->getMessage();
    $adminActionMessageType = 'error';
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_school') {
  $schoolName = trim($_POST['university_name'] ?? '');
  $emailDomain = strtolower(trim($_POST['email_domain'] ?? ''));
  $themeColor = trim($_POST['theme_color'] ?? '#0056b3');

  if ($schoolName === '' || $emailDomain === '') {
    $schoolMessage = 'School name and student email domain are required.';
    $schoolMessageType = 'error';
  } elseif (strlen($schoolName) > 150 || !preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $emailDomain) || strlen($emailDomain) > 100) {
    $schoolMessage = 'Enter a valid school name and email domain, for example student.example.edu.';
    $schoolMessageType = 'error';
  } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) {
    $schoolMessage = 'Theme color must be a six-digit hex color.';
    $schoolMessageType = 'error';
  } else {
    try {
      $pdo->beginTransaction();
      $schoolStmt = $pdo->prepare('INSERT INTO universities (university_name, email_domain, theme_color) VALUES (:name, :domain, :theme)');
      $schoolStmt->execute([
        'name' => $schoolName,
        'domain' => $emailDomain,
        'theme' => $themeColor,
      ]);
      $logStmt = $pdo->prepare('INSERT INTO admin_logs (admin_id, action, details) VALUES (:admin_id, :action, :details)');
      $logStmt->execute([
        'admin_id' => (int) ($user['id'] ?? $user['user_id'] ?? 0),
        'action' => 'add_school',
        'details' => 'School added: ' . $schoolName . ' (' . $emailDomain . ').',
      ]);
      $pdo->commit();
      $schoolMessage = 'School added successfully.';
    } catch (PDOException $exception) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $schoolMessage = $exception->getCode() === '23000'
        ? 'This email domain is already registered.'
        : 'The school could not be added. Please check the database configuration.';
      $schoolMessageType = 'error';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($adminActionMessage !== null) {
    $_SESSION['admin_action_message'] = $adminActionMessage;
    $_SESSION['admin_action_message_type'] = $adminActionMessageType;
  }
  if ($schoolMessage !== null) {
    $_SESSION['admin_school_message'] = $schoolMessage;
    $_SESSION['admin_school_message_type'] = $schoolMessageType;
  }
  $redirectView = $_POST['return_view'] ?? $adminView;
  if (!in_array($redirectView, $allowedAdminViews, true)) {
    $redirectView = 'overview';
  }
  header('Location: dashboard.php?view=' . urlencode($redirectView));
  exit;
}

$schoolsStmt = $pdo->query('SELECT university_id, university_name, email_domain, theme_color, created_at FROM universities ORDER BY university_name ASC');
$schools = $schoolsStmt->fetchAll(PDO::FETCH_ASSOC);

$adminData = [
  'summary' => [
    'total_users' => 0,
    'active_listings' => 0,
    'completed_trades' => 0,
    'trade_volume' => 0,
  ],
  'moderation' => [],
  'integrity' => [],
];

$summaryStmt = $pdo->query('
  SELECT
    (SELECT COUNT(*) FROM users WHERE role = "student") AS total_users,
    (SELECT COUNT(*) FROM products WHERE status = "Available") AS active_listings,
    (SELECT COUNT(*) FROM orders WHERE status = "Completed") AS completed_trades,
    (SELECT COALESCE(SUM(price_amount), 0) FROM orders WHERE status = "Completed") AS trade_volume
');
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$adminData['summary'] = [
  'total_users' => (int) ($summary['total_users'] ?? 0),
  'active_listings' => (int) ($summary['active_listings'] ?? 0),
  'completed_trades' => (int) ($summary['completed_trades'] ?? 0),
  'trade_volume' => (float) ($summary['trade_volume'] ?? 0),
];

$moderationStmt = $pdo->query('
  SELECT
    u.user_id,
    u.full_name,
    u.student_id,
    u.email,
    uni.university_name AS university,
    (
      SELECT COUNT(*)
      FROM admin_logs warning_logs
      WHERE warning_logs.action = "warn_user"
        AND warning_logs.target_user_id = u.user_id
    ) AS warning_count,
    u.status,
    u.updated_at
  FROM users u
  INNER JOIN universities uni ON uni.university_id = u.university_id
  WHERE u.role = "student"
  ORDER BY u.updated_at DESC, u.user_id DESC
');
$adminData['moderation'] = $moderationStmt->fetchAll(PDO::FETCH_ASSOC);

$moderationHistoryStmt = $pdo->query('
  SELECT al.target_user_id, al.action, al.details, al.created_at, admin.full_name AS admin_name
  FROM admin_logs al
  INNER JOIN users admin ON admin.user_id = al.admin_id
  WHERE al.target_user_id IS NOT NULL
    AND al.action IN ("warn_user", "suspend_user", "unsuspend_user", "approve_recovery_request", "reject_recovery_request")
  ORDER BY al.created_at DESC, al.log_id DESC
');
$moderationHistory = [];
foreach ($moderationHistoryStmt->fetchAll(PDO::FETCH_ASSOC) as $historyEntry) {
  $targetUserId = (int) $historyEntry['target_user_id'];
  $moderationHistory[$targetUserId][] = [
    'action' => $historyEntry['action'],
    'details' => $historyEntry['details'] ?? '',
    'created_at' => $historyEntry['created_at'],
    'admin_name' => $historyEntry['admin_name'],
  ];
}

$listingStmt = $pdo->query('
  SELECT p.product_id, p.title, p.description, p.price, p.item_condition, p.status, p.created_at, p.updated_at,
         c.category_name, img.image_url AS primary_image,
         u.full_name AS seller_name, u.email AS seller_email, uni.university_name AS university
  FROM products p
  INNER JOIN users u ON u.user_id = p.seller_id
  INNER JOIN universities uni ON uni.university_id = p.university_id
  INNER JOIN categories c ON c.category_id = p.category_id
  LEFT JOIN product_images img ON img.product_id = p.product_id AND img.is_primary = 1
  WHERE p.status <> "Deleted"
  ORDER BY p.created_at DESC, p.product_id DESC
');
$listings = $listingStmt->fetchAll(PDO::FETCH_ASSOC);

$removedListingsStmt = $pdo->query('
  SELECT al.details, al.created_at, admin.full_name AS admin_name,
         target_product.product_id, target_product.title,
         seller.full_name AS seller_name
  FROM admin_logs al
  INNER JOIN users admin ON admin.user_id = al.admin_id
  LEFT JOIN products target_product ON target_product.product_id = al.target_product_id
  LEFT JOIN users seller ON seller.user_id = target_product.seller_id
  WHERE al.action = "delete_listing"
  ORDER BY al.created_at DESC, al.log_id DESC
');
$removedListings = $removedListingsStmt->fetchAll(PDO::FETCH_ASSOC);

$transactionsStmt = $pdo->query('
  SELECT
    o.order_id,
    o.order_ref,
    o.price_amount,
    o.handover_location,
    o.status,
    o.created_at,
    o.updated_at,
    p.title AS product_title,
    buyer.full_name AS buyer_name,
    buyer.email AS buyer_email,
    seller.full_name AS seller_name,
    seller.email AS seller_email,
    uni.university_name AS university,
    COALESCE(review.rating, 0) AS review_rating
  FROM orders o
  INNER JOIN products p ON p.product_id = o.product_id
  INNER JOIN users buyer ON buyer.user_id = o.buyer_id
  INNER JOIN users seller ON seller.user_id = o.seller_id
  LEFT JOIN universities uni ON uni.university_id = p.university_id
  LEFT JOIN reviews review ON review.order_id = o.order_id
  ORDER BY o.created_at DESC, o.order_id DESC
');
$transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);

$topUpStmt = $pdo->query('
  SELECT wt.transaction_id, wt.transaction_ref, wt.amount, wt.resulting_balance, wt.created_at,
         u.full_name, u.student_id, u.email, uni.university_name AS university
  FROM wallet_transactions wt
  INNER JOIN wallets w ON w.wallet_id = wt.wallet_id
  INNER JOIN users u ON u.user_id = w.user_id
  LEFT JOIN universities uni ON uni.university_id = u.university_id
  WHERE wt.transaction_type = "TOPUP"
  ORDER BY wt.created_at DESC, wt.transaction_id DESC
');
$topUpRecords = $topUpStmt->fetchAll(PDO::FETCH_ASSOC);

$logsStmt = $pdo->query('
  SELECT al.action, al.details, al.created_at, admin.full_name AS admin_name,
         target_user.full_name AS target_user_name, target_product.title AS target_product_title
  FROM admin_logs al
  INNER JOIN users admin ON admin.user_id = al.admin_id
  LEFT JOIN users target_user ON target_user.user_id = al.target_user_id
  LEFT JOIN products target_product ON target_product.product_id = al.target_product_id
  ORDER BY al.created_at DESC, al.log_id DESC
');
$adminLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

$recoveryStmt = $pdo->query('
  SELECT arr.request_id, arr.user_id, arr.reason, arr.status, arr.admin_response, arr.created_at,
         u.full_name, u.email, u.student_id, uni.university_name AS university
  FROM account_recovery_requests arr
  INNER JOIN users u ON u.user_id = arr.user_id
  LEFT JOIN universities uni ON uni.university_id = u.university_id
  ORDER BY CASE WHEN arr.status = "pending" THEN 0 ELSE 1 END, arr.created_at DESC, arr.request_id DESC
');
$recoveryRequests = $recoveryStmt->fetchAll(PDO::FETCH_ASSOC);

$escrowStmt = $pdo->query('
  SELECT COUNT(*)
  FROM wallet_transactions
  WHERE transaction_type IN ("ESCROW_HOLD", "ESCROW_RELEASE", "REFUND")
');
$emailDomainStmt = $pdo->query('SELECT COUNT(*) FROM universities WHERE email_domain IS NOT NULL AND email_domain <> ""');
$deletedListingsStmt = $pdo->query('SELECT COUNT(*) FROM products WHERE status = "Deleted"');
$adminData['integrity'] = [
  'escrow_transactions' => (int) $escrowStmt->fetchColumn(),
  'university_domains' => (int) $emailDomainStmt->fetchColumn(),
  'deleted_listings' => (int) $deletedListingsStmt->fetchColumn(),
  'database_connected' => true,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="../assets/image/website_icon.png" />
  <title>Campus Exchange | Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    body[data-page="admin"] {
      overflow-x: hidden;
    }

    body[data-page="admin"][data-admin-view="overview"] #userModeration,
    body[data-page="admin"][data-admin-view="overview"] #listingModeration,
    body[data-page="admin"][data-admin-view="overview"] #transactionDetails,
    body[data-page="admin"][data-admin-view="overview"] #topUpRecords,
    body[data-page="admin"][data-admin-view="overview"] #recoveryRequests,
    body[data-page="admin"][data-admin-view="overview"] #removedListingHistory,
    body[data-page="admin"][data-admin-view="user-moderation"] #adminOverview,
    body[data-page="admin"][data-admin-view="user-moderation"] #schoolManagement,
    body[data-page="admin"][data-admin-view="user-moderation"] #listingModeration,
    body[data-page="admin"][data-admin-view="user-moderation"] #adminLogs,
    body[data-page="admin"][data-admin-view="user-moderation"] #transactionDetails,
    body[data-page="admin"][data-admin-view="user-moderation"] #systemIntegrity,
    body[data-page="admin"][data-admin-view="user-moderation"] #topUpRecords,
    body[data-page="admin"][data-admin-view="user-moderation"] #removedListingHistory,
    body[data-page="admin"][data-admin-view="listing-moderation"] #adminOverview,
    body[data-page="admin"][data-admin-view="listing-moderation"] #schoolManagement,
    body[data-page="admin"][data-admin-view="listing-moderation"] #userModeration,
    body[data-page="admin"][data-admin-view="listing-moderation"] #adminLogs,
    body[data-page="admin"][data-admin-view="listing-moderation"] #transactionDetails,
    body[data-page="admin"][data-admin-view="listing-moderation"] #systemIntegrity,
    body[data-page="admin"][data-admin-view="listing-moderation"] #topUpRecords,
    body[data-page="admin"][data-admin-view="listing-moderation"] #removedListingHistory,
    body[data-page="admin"][data-admin-view="removed-listing-history"] #adminOverview,
    body[data-page="admin"][data-admin-view="removed-listing-history"] #schoolManagement,
    body[data-page="admin"][data-admin-view="removed-listing-history"] #userModeration,
    body[data-page="admin"][data-admin-view="removed-listing-history"] #listingModeration,
    body[data-page="admin"][data-admin-view="removed-listing-history"] #adminLogs,
    body[data-page="admin"][data-admin-view="removed-listing-history"] #transactionDetails,
    body[data-page="admin"][data-admin-view="removed-listing-history"] #systemIntegrity,
    body[data-page="admin"][data-admin-view="removed-listing-history"] #topUpRecords,
    body[data-page="admin"][data-admin-view="removed-listing-history"] #recoveryRequests,
    body[data-page="admin"][data-admin-view="transactions"] #adminOverview,
    body[data-page="admin"][data-admin-view="transactions"] #schoolManagement,
    body[data-page="admin"][data-admin-view="transactions"] #userModeration,
    body[data-page="admin"][data-admin-view="transactions"] #listingModeration,
    body[data-page="admin"][data-admin-view="transactions"] #adminLogs,
    body[data-page="admin"][data-admin-view="transactions"] #systemIntegrity,
    body[data-page="admin"][data-admin-view="transactions"] #topUpRecords,
    body[data-page="admin"][data-admin-view="transactions"] #removedListingHistory,
    body[data-page="admin"][data-admin-view="top-up-records"] #adminOverview,
    body[data-page="admin"][data-admin-view="top-up-records"] #schoolManagement,
    body[data-page="admin"][data-admin-view="top-up-records"] #userModeration,
    body[data-page="admin"][data-admin-view="top-up-records"] #listingModeration,
    body[data-page="admin"][data-admin-view="top-up-records"] #adminLogs,
    body[data-page="admin"][data-admin-view="top-up-records"] #transactionDetails,
    body[data-page="admin"][data-admin-view="top-up-records"] #systemIntegrity,
    body[data-page="admin"][data-admin-view="top-up-records"] #recoveryRequests,
    body[data-page="admin"][data-admin-view="top-up-records"] #removedListingHistory,
    body[data-page="admin"][data-admin-view="logs"] #adminOverview,
    body[data-page="admin"][data-admin-view="logs"] #schoolManagement,
    body[data-page="admin"][data-admin-view="logs"] #userModeration,
    body[data-page="admin"][data-admin-view="logs"] #listingModeration,
    body[data-page="admin"][data-admin-view="logs"] #transactionDetails,
    body[data-page="admin"][data-admin-view="logs"] #systemIntegrity,
    body[data-page="admin"][data-admin-view="logs"] #topUpRecords,
    body[data-page="admin"][data-admin-view="logs"] #removedListingHistory,
    body[data-page="admin"][data-admin-view="recovery-requests"] #adminOverview,
    body[data-page="admin"][data-admin-view="recovery-requests"] #schoolManagement,
    body[data-page="admin"][data-admin-view="recovery-requests"] #userModeration,
    body[data-page="admin"][data-admin-view="recovery-requests"] #listingModeration,
    body[data-page="admin"][data-admin-view="recovery-requests"] #adminLogs,
    body[data-page="admin"][data-admin-view="recovery-requests"] #transactionDetails,
    body[data-page="admin"][data-admin-view="recovery-requests"] #systemIntegrity,
    body[data-page="admin"][data-admin-view="recovery-requests"] #topUpRecords,
    body[data-page="admin"][data-admin-view="recovery-requests"] #removedListingHistory,
    body[data-page="admin"][data-admin-view="user-moderation"] #recoveryRequests,
    body[data-page="admin"][data-admin-view="listing-moderation"] #recoveryRequests,
    body[data-page="admin"][data-admin-view="transactions"] #recoveryRequests,
    body[data-page="admin"][data-admin-view="logs"] #recoveryRequests,
    body[data-page="admin"][data-admin-view="top-up-records"] #recoveryRequests {
      display: none;
    }

    body[data-page="admin"] .table-scroll {
      overflow-x: hidden;
    }

    body[data-page="admin"] .transaction-table-scroll {
      overflow-x: auto;
    }

    body[data-page="admin"] .admin-table {
      width: 100%;
      table-layout: fixed;
    }

    body[data-page="admin"] .admin-table th,
    body[data-page="admin"] .admin-table td {
      overflow-wrap: anywhere;
      word-break: break-word;
    }
  </style>
</head>
<body class="bg-slate-100 text-slate-800" data-page="admin" data-admin-view="<?php echo htmlspecialchars($adminView); ?>">
  <div class="min-h-screen flex">
    <aside class="w-72 bg-slate-900 text-white p-5 hidden lg:block">
      <div class="flex items-center gap-3 mb-8">
        <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center"><i class="fa-solid fa-shield-halved"></i></div>
        <div>
          <div class="font-black text-base">Campus<span class="text-blue-300">Exchange</span></div>
          <div class="text-[10px] uppercase tracking-[0.2em] text-slate-400">Admin Console</div>
        </div>
      </div>

      <nav class="sidenav space-y-2">
        <a href="dashboard.php?view=overview" class="<?php echo $adminView === 'overview' ? 'active' : ''; ?>"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="dashboard.php?view=user-moderation" class="<?php echo $adminView === 'user-moderation' ? 'active' : ''; ?>"><i class="fa-solid fa-users"></i> User Moderation</a>
        <a href="dashboard.php?view=listing-moderation" class="<?php echo $adminView === 'listing-moderation' ? 'active' : ''; ?>"><i class="fa-solid fa-box-open"></i> Listing Moderation</a>
        <a href="dashboard.php?view=removed-listing-history" class="<?php echo $adminView === 'removed-listing-history' ? 'active' : ''; ?>"><i class="fa-solid fa-clock-rotate-left"></i> Removed History</a>
        <a href="dashboard.php?view=transactions" class="<?php echo $adminView === 'transactions' ? 'active' : ''; ?>"><i class="fa-solid fa-money-bill-transfer"></i> Transactions</a>
        <a href="dashboard.php?view=top-up-records" class="<?php echo $adminView === 'top-up-records' ? 'active' : ''; ?>"><i class="fa-solid fa-wallet"></i> Top-up Records</a>
        <a href="dashboard.php?view=recovery-requests" class="<?php echo $adminView === 'recovery-requests' ? 'active' : ''; ?>"><i class="fa-solid fa-unlock-keyhole"></i> Recovery Requests</a>
        <a href="dashboard.php?view=overview#adminLogs"><i class="fa-solid fa-file-lines"></i> System Logs</a>
      </nav>

      <div class="mt-8 border-t border-slate-700 pt-4 text-xs text-slate-300">
        <div class="font-bold text-white mb-2">Logged In As</div>
        <div class="text-sm font-semibold text-white"><?php echo htmlspecialchars($user['full_name']); ?></div>
        <div class="text-[11px] text-slate-400 mt-1"><?php echo htmlspecialchars($user['email']); ?></div>
      </div>
    </aside>

    <main class="flex-1 p-4 md:p-8">
      <div class="flex items-center justify-between mb-6">
        <div>
          <div class="text-xs uppercase tracking-[0.2em] font-bold text-slate-500">Monitoring</div>
          <h1 class="text-2xl md:text-3xl font-black text-slate-900 mt-1">Platform Overview Dashboard</h1>
        </div>
        <div class="flex items-center gap-3">
          <div class="hidden sm:block text-right">
            <div class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Administrator</div>
            <div class="font-bold text-slate-800"><?php echo htmlspecialchars($user['full_name']); ?></div>
          </div>
          <a href="../logout.php" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-700">Logout</a>
        </div>
      </div>

      <div id="adminOverview">
        <div id="adminSummary" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8"></div>
      </div>

      <section id="schoolManagement" class="page-section p-5 mb-6">
        <div class="flex items-start justify-between gap-4 mb-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900">School Management</h2>
            <p class="text-[11px] text-slate-500">Add a partner school and enable its student email domain.</p>
          </div>
          <span class="text-xs font-semibold text-slate-500"><?php echo count($schools); ?> registered schools</span>
        </div>

        <?php if ($schoolMessage !== null): ?>
          <div class="mb-4 rounded-xl border px-3 py-2 text-sm <?php echo $schoolMessageType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'; ?>">
            <?php echo htmlspecialchars($schoolMessage); ?>
          </div>
        <?php endif; ?>

        <form method="post" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 items-end">
          <input type="hidden" name="action" value="add_school" />
          <label class="text-xs font-semibold text-slate-600">School name
            <input name="university_name" required maxlength="150" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Example University" />
          </label>
          <label class="text-xs font-semibold text-slate-600">Student email domain
            <input name="email_domain" required maxlength="100" pattern="[A-Za-z0-9][A-Za-z0-9.-]*[A-Za-z0-9]" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="student.example.edu" />
          </label>
          <label class="text-xs font-semibold text-slate-600">Theme color
            <input type="color" name="theme_color" value="#0056b3" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-1 py-1" />
          </label>
          <button type="submit" class="rounded-lg bg-custom-primary px-3 py-2.5 text-sm font-bold text-white hover:opacity-90"><i class="fa-solid fa-plus mr-1"></i> Add School</button>
        </form>

        <div class="table-scroll mt-5">
          <table class="w-full table-fixed text-left admin-table">
            <thead>
              <tr class="text-[10px] uppercase tracking-[0.18em] text-slate-500">
                <th class="px-3 py-2 font-semibold">School</th>
                <th class="px-3 py-2 font-semibold">Student email domain</th>
                <th class="px-3 py-2 font-semibold">Theme</th>
                <th class="px-3 py-2 font-semibold">Added</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($schools as $school): ?>
                <tr class="border-t border-gray-200 text-xs text-slate-700">
                  <td class="px-3 py-3 font-semibold"><?php echo htmlspecialchars($school['university_name']); ?></td>
                  <td class="px-3 py-3"><?php echo htmlspecialchars($school['email_domain']); ?></td>
                  <td class="px-3 py-3"><span class="inline-block h-4 w-4 rounded-full border border-slate-300 align-middle" style="background-color: <?php echo htmlspecialchars($school['theme_color']); ?>"></span> <?php echo htmlspecialchars($school['theme_color']); ?></td>
                  <td class="px-3 py-3"><?php echo htmlspecialchars($school['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <?php if ($adminActionMessage !== null): ?>
        <div class="mb-6 rounded-xl border px-3 py-2 text-sm <?php echo $adminActionMessageType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'; ?>">
          <?php echo htmlspecialchars($adminActionMessage); ?>
        </div>
      <?php endif; ?>

      <section id="recoveryRequests" class="page-section p-5 mb-6">
        <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-lg font-bold text-slate-900">Account Recovery Requests</h2>
            <p class="text-[11px] text-slate-500">Review suspended student requests and send a response.</p>
          </div>
          <div class="flex flex-col gap-2 sm:items-end">
            <label class="relative block w-full sm:w-64">
              <span class="sr-only">Search recovery requests</span>
              <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
              <input type="search" class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-xs text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Search student, ID, status..." aria-label="Search recovery requests" data-list-search="recovery" />
            </label>
            <span class="text-xs font-semibold text-slate-500"><?php echo count(array_filter($recoveryRequests, static fn ($request) => $request['status'] === 'pending')); ?> pending</span>
          </div>
        </div>
        <?php if (!$recoveryRequests): ?>
          <p class="text-sm text-slate-500">No account recovery requests have been submitted.</p>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($recoveryRequests as $request): ?>
              <article class="rounded-xl border <?php echo $request['status'] === 'pending' ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50'; ?> p-4" data-recovery-row>
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <div class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($request['full_name']); ?></div>
                    <div class="mt-1 text-xs text-slate-500"><?php echo htmlspecialchars($request['email']); ?> · <?php echo htmlspecialchars($request['student_id']); ?> · <?php echo htmlspecialchars($request['university'] ?? ''); ?></div>
                    <p class="mt-3 text-sm text-slate-700"><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                    <div class="mt-2 text-[10px] text-slate-500">Submitted <?php echo htmlspecialchars($request['created_at']); ?></div>
                  </div>
                  <span class="rounded-full px-2 py-1 text-[10px] font-bold <?php echo $request['status'] === 'pending' ? 'bg-amber-100 text-amber-700' : ($request['status'] === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'); ?>"><?php echo ucfirst(htmlspecialchars($request['status'])); ?></span>
                </div>
                <?php if ($request['status'] === 'pending'): ?>
                  <form method="post" class="mt-4 border-t border-slate-200/70 pt-3">
                    <input type="hidden" name="action" value="review_recovery_request" />
                    <input type="hidden" name="request_id" value="<?php echo (int) $request['request_id']; ?>" />
                    <label class="block text-xs font-semibold text-slate-600">Administrator response
                      <textarea name="admin_response" required maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Explain your decision to the student."></textarea>
                    </label>
                    <div class="mt-2 flex flex-wrap justify-end gap-2">
                      <button type="submit" name="recovery_decision" value="reject" class="rounded-lg bg-red-100 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-200">Reject Request</button>
                      <button type="submit" name="recovery_decision" value="approve" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Approve &amp; Restore</button>
                    </div>
                  </form>
                <?php elseif (trim((string) $request['admin_response']) !== ''): ?>
                  <p class="mt-3 border-t border-slate-200/70 pt-3 text-xs text-slate-600"><span class="font-bold">Admin response:</span> <?php echo nl2br(htmlspecialchars($request['admin_response'])); ?></p>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
          <p class="hidden mt-3 text-xs text-slate-500" data-recovery-empty>No matching recovery requests found.</p>
        <?php endif; ?>
      </section>

      <div class="grid grid-cols-1 xl:grid-cols-[1.3fr_0.7fr] gap-6">
        <section id="userModeration" class="page-section p-5 xl:col-span-2">
          <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-lg font-bold text-slate-900">Recent User Moderation Queue</h2>
              <p class="text-[11px] text-slate-500">Search, warn, and suspend accounts flagged for review.</p>
            </div>
            <div class="flex flex-col gap-2 sm:items-end">
              <label class="relative block w-full sm:w-64">
                <span class="sr-only">Search students</span>
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                <input type="search" class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-xs text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Search student, ID, email..." aria-label="Search students" data-list-search="moderation" />
              </label>
              <span class="text-[11px] font-semibold text-slate-500">Showing latest 6 of <?php echo count($adminData['moderation']); ?> students</span>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($adminData['moderation'] as $moderationIndex => $moderatedUser): ?>
              <?php
                $hasWarnings = (int) $moderatedUser['warning_count'] > 0;
                $isSuspended = $moderatedUser['status'] === 'suspended';
              ?>
              <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-md<?php echo $moderationIndex >= 6 ? ' hidden' : ''; ?>" data-moderation-row data-search-row="moderation">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <h3 class="truncate text-sm font-bold text-slate-900" title="<?php echo htmlspecialchars($moderatedUser['full_name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($moderatedUser['full_name']); ?></h3>
                    <p class="mt-1 text-[10px] text-slate-500"><?php echo htmlspecialchars($moderatedUser['student_id']); ?> · <?php echo htmlspecialchars($moderatedUser['university']); ?></p>
                  </div>
                  <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold <?php echo $isSuspended ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'; ?>"><?php echo $isSuspended ? 'Suspended' : 'Active'; ?></span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 text-xs">
                  <div><div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Email</div><div class="mt-1 break-all text-slate-700"><?php echo htmlspecialchars($moderatedUser['email']); ?></div></div>
                  <div><div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Warnings</div><div class="mt-1 font-bold <?php echo $hasWarnings ? 'text-amber-700' : 'text-slate-600'; ?>"><?php echo $hasWarnings ? (int) $moderatedUser['warning_count'] . ' issued' : 'No action'; ?></div></div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-2 text-[10px] text-slate-500">
                  <span><i class="fa-solid fa-clock mr-1"></i>Updated <?php echo htmlspecialchars($moderatedUser['updated_at']); ?></span>
                  <span class="font-semibold <?php echo $hasWarnings ? 'text-amber-700' : 'text-slate-500'; ?>"><?php echo $hasWarnings ? 'Needs attention' : 'Clear record'; ?></span>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                  <button class="rounded bg-amber-100 px-2.5 py-1.5 text-[10px] font-bold text-amber-700 hover:bg-amber-200" type="button" data-warning-trigger data-user-id="<?php echo (int) $moderatedUser['user_id']; ?>" data-user-name="<?php echo htmlspecialchars($moderatedUser['full_name'], ENT_QUOTES); ?>"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Warning</button>
                  <?php if ($isSuspended): ?>
                    <form method="post"><input type="hidden" name="action" value="unsuspend_user" /><input type="hidden" name="user_id" value="<?php echo (int) $moderatedUser['user_id']; ?>" /><button class="rounded bg-emerald-100 px-2.5 py-1.5 text-[10px] font-bold text-emerald-700 hover:bg-emerald-200" type="submit"><i class="fa-solid fa-unlock mr-1"></i>Restore</button></form>
                  <?php else: ?>
                    <button class="rounded bg-red-100 px-2.5 py-1.5 text-[10px] font-bold text-red-700 hover:bg-red-200" type="button" data-suspend-trigger data-user-id="<?php echo (int) $moderatedUser['user_id']; ?>" data-user-name="<?php echo htmlspecialchars($moderatedUser['full_name'], ENT_QUOTES); ?>"><i class="fa-solid fa-ban mr-1"></i>Suspend</button>
                  <?php endif; ?>
                  <button class="rounded bg-slate-100 px-2.5 py-1.5 text-[10px] font-bold text-slate-700 hover:bg-slate-200" type="button" data-history-trigger data-user-id="<?php echo (int) $moderatedUser['user_id']; ?>" data-user-name="<?php echo htmlspecialchars($moderatedUser['full_name'], ENT_QUOTES); ?>"><i class="fa-solid fa-clock-rotate-left mr-1"></i>History</button>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <p class="hidden mt-3 text-xs text-slate-500" data-moderation-empty>No matching students found.</p>
          <?php if (count($adminData['moderation']) > 6): ?>
            <button type="button" class="mt-3 text-xs font-bold text-blue-700 hover:text-blue-900" data-show-more="moderation" data-visible-limit="6" aria-expanded="false">Show more <i class="fa-solid fa-chevron-down ml-1"></i></button>
          <?php endif; ?>
        </section>

      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-6">
        <section id="listingModeration" class="page-section p-5 xl:col-span-2">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-lg font-bold text-slate-900">Listing Moderation</h2>
              <p class="text-[11px] text-slate-500">Remove listings that violate marketplace rules.</p>
            </div>
            <div class="flex flex-col gap-2 items-end">
              <label class="relative block w-64 max-w-full">
                <span class="sr-only">Search listings</span>
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                <input type="search" class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-xs text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Search listing, seller, campus..." aria-label="Search listings" data-list-search="listings" />
              </label>
              <span class="text-xs font-semibold text-slate-500">Showing latest 6 of <?php echo count($listings); ?> listings</span>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <?php if (!$listings): ?>
              <p class="text-sm text-slate-500 md:col-span-2">No active listings found.</p>
            <?php endif; ?>
            <?php foreach ($listings as $listingIndex => $listing): ?>
              <?php
                $listingImage = trim((string) ($listing['primary_image'] ?? ''));
                $listingImage = $listingImage !== '' ? '../' . ltrim($listingImage, '/') : '../assets/images/default.jpg';
                $listingStatusClass = $listing['status'] === 'Available' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600';
              ?>
              <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:border-blue-200 hover:shadow-md<?php echo $listingIndex >= 6 ? ' hidden' : ''; ?>" data-listing-row data-search-row="listings">
                <div class="grid grid-cols-[8rem_1fr] min-h-48">
                  <div class="bg-slate-100">
                    <img src="<?php echo htmlspecialchars($listingImage, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($listing['title'], ENT_QUOTES); ?>" class="h-full min-h-48 w-full object-cover" />
                  </div>
                  <div class="flex min-w-0 flex-col p-4">
                    <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0">
                        <h3 class="truncate text-sm font-bold text-slate-900" title="<?php echo htmlspecialchars($listing['title'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($listing['title']); ?></h3>
                        <p class="mt-1 text-[10px] text-slate-500"><?php echo htmlspecialchars($listing['category_name']); ?> · <?php echo htmlspecialchars($listing['item_condition']); ?></p>
                      </div>
                      <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold <?php echo $listingStatusClass; ?>"><?php echo htmlspecialchars($listing['status']); ?></span>
                    </div>
                    <p class="mt-3 line-clamp-2 text-xs leading-5 text-slate-600"><?php echo htmlspecialchars($listing['description']); ?></p>
                    <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-[10px] text-slate-500">
                      <div><span class="font-semibold text-slate-700">Seller</span><br /><?php echo htmlspecialchars($listing['seller_name']); ?></div>
                      <div><span class="font-semibold text-slate-700">Campus</span><br /><?php echo htmlspecialchars($listing['university']); ?></div>
                      <div><span class="font-semibold text-slate-700">Price</span><br /><span class="text-sm font-bold text-blue-700">RM <?php echo number_format((float) $listing['price'], 2); ?></span></div>
                      <div><span class="font-semibold text-slate-700">Listed</span><br /><?php echo htmlspecialchars($listing['created_at']); ?></div>
                    </div>
                    <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                      <span class="text-[10px] text-slate-400">Updated <?php echo htmlspecialchars($listing['updated_at']); ?></span>
                      <div class="flex items-center gap-2">
                        <?php if ($listing['status'] === 'Available'): ?>
                          <button class="rounded bg-red-100 px-2 py-1 text-[10px] font-bold text-red-700 hover:bg-red-200" type="button" data-remove-listing-trigger data-product-id="<?php echo (int) $listing['product_id']; ?>" data-product-title="<?php echo htmlspecialchars($listing['title'], ENT_QUOTES); ?>"><i class="fa-solid fa-trash mr-1"></i>Remove</button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <p class="hidden mt-3 text-xs text-slate-500" data-listings-empty>No matching listings found.</p>
          <?php if (count($listings) > 6): ?>
            <button type="button" class="mt-3 text-xs font-bold text-blue-700 hover:text-blue-900" data-show-more="listings" data-visible-limit="6" aria-expanded="false">Show more <i class="fa-solid fa-chevron-down ml-1"></i></button>
          <?php endif; ?>

        </section>

        <section id="systemIntegrity" class="page-section p-5">
          <h2 class="text-lg font-bold text-slate-900 mb-4">System Integrity</h2>
          <div class="space-y-3 text-sm">
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-emerald-800"><i class="fa-solid fa-circle-check mr-2"></i> <?php echo (int) $adminData['integrity']['escrow_transactions']; ?> escrow wallet transactions recorded</div>
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-blue-800"><i class="fa-solid fa-shield-halved mr-2"></i> <?php echo (int) $adminData['integrity']['university_domains']; ?> university email domains configured</div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-amber-800"><i class="fa-solid fa-triangle-exclamation mr-2"></i> <?php echo (int) $adminData['integrity']['deleted_listings']; ?> listings marked as deleted</div>
            <div class="bg-slate-100 border border-slate-200 rounded-xl p-3 text-slate-700"><i class="fa-solid fa-database mr-2"></i> MySQL connection health: <?php echo $adminData['integrity']['database_connected'] ? 'connected' : 'unavailable'; ?></div>
          </div>
        </section>

        <section id="adminLogs" class="page-section p-5">
          <h2 class="text-lg font-bold text-slate-900 mb-4">Recent Admin Activity</h2>
          <div class="space-y-3" data-log-list>
            <?php if (!$adminLogs): ?><p class="text-sm text-slate-500">No administrator actions have been recorded.</p><?php endif; ?>
            <?php foreach ($adminLogs as $logIndex => $log): ?>
              <div class="border-b border-slate-100 pb-3 text-xs<?php echo $logIndex >= 5 ? ' hidden' : ''; ?>" data-log-row>
                <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($log['action']); ?><?php if ($log['target_user_name']): ?>: <?php echo htmlspecialchars($log['target_user_name']); ?><?php elseif ($log['target_product_title']): ?>: <?php echo htmlspecialchars($log['target_product_title']); ?><?php endif; ?></div>
                <div class="mt-1 text-slate-500"><?php echo htmlspecialchars($log['details'] ?? ''); ?> · <?php echo htmlspecialchars($log['created_at']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if (count($adminLogs) > 5): ?>
            <button type="button" class="mt-3 text-xs font-bold text-blue-700 hover:text-blue-900" data-show-more="logs" aria-expanded="false">Show more <i class="fa-solid fa-chevron-down ml-1"></i></button>
          <?php endif; ?>
        </section>
      </div>

      <section id="transactionDetails" class="page-section p-5 mt-6">
        <div class="flex items-start justify-between gap-4 mb-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900">All Transaction Details</h2>
            <p class="text-[11px] text-slate-500">Live order records from the database, including buyer, seller, amount, status and handover details.</p>
          </div>
          <div class="flex flex-col gap-2 items-end">
            <label class="relative block w-64 max-w-full">
              <span class="sr-only">Search transactions</span>
              <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
              <input type="search" class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-xs text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Search order, buyer, seller..." aria-label="Search transactions" data-list-search="transactions" />
            </label>
            <span class="text-xs font-semibold text-slate-500">Showing latest 5 of <?php echo count($transactions); ?> transactions</span>
          </div>
        </div>
        <div class="table-scroll transaction-table-scroll">
          <table class="w-full min-w-[1050px] table-fixed text-left admin-table">
            <thead>
              <tr class="text-[10px] uppercase tracking-[0.18em] text-slate-500">
                <th class="px-3 py-2 font-semibold">Order</th>
                <th class="px-3 py-2 font-semibold">Product</th>
                <th class="px-3 py-2 font-semibold">Buyer</th>
                <th class="px-3 py-2 font-semibold">Seller</th>
                <th class="px-3 py-2 font-semibold">Amount</th>
                <th class="px-3 py-2 font-semibold">Status</th>
                <th class="px-3 py-2 font-semibold">Handover</th>
                <th class="px-3 py-2 font-semibold">Review</th>
                <th class="px-3 py-2 font-semibold">Created / Updated</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$transactions): ?>
                <tr class="border-t border-gray-200 text-xs text-slate-500"><td class="px-3 py-6 text-center" colspan="9">No transactions found.</td></tr>
              <?php endif; ?>
              <?php foreach ($transactions as $transactionIndex => $transaction): ?>
                <?php
                  $transactionStatusClass = $transaction['status'] === 'Completed'
                    ? 'bg-emerald-100 text-emerald-700'
                    : ($transaction['status'] === 'Cancelled' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
                ?>
                <tr class="border-t border-gray-200 text-xs text-slate-700<?php echo $transactionIndex >= 5 ? ' hidden' : ''; ?>" data-transaction-row data-search-row="transactions">
                  <td class="px-3 py-3"><div class="font-semibold"><?php echo htmlspecialchars($transaction['order_ref']); ?></div><div class="text-[10px] text-slate-500">#<?php echo (int) $transaction['order_id']; ?></div></td>
                  <td class="px-3 py-3"><div class="font-semibold"><?php echo htmlspecialchars($transaction['product_title']); ?></div><div class="text-[10px] text-slate-500"><?php echo htmlspecialchars($transaction['university'] ?? ''); ?></div></td>
                  <td class="px-3 py-3"><div><?php echo htmlspecialchars($transaction['buyer_name']); ?></div><div class="text-[10px] text-slate-500"><?php echo htmlspecialchars($transaction['buyer_email']); ?></div></td>
                  <td class="px-3 py-3"><div><?php echo htmlspecialchars($transaction['seller_name']); ?></div><div class="text-[10px] text-slate-500"><?php echo htmlspecialchars($transaction['seller_email']); ?></div></td>
                  <td class="px-3 py-3 font-bold">RM <?php echo number_format((float) $transaction['price_amount'], 2); ?></td>
                  <td class="px-3 py-3"><span class="rounded-full px-2 py-1 <?php echo $transactionStatusClass; ?>"><?php echo htmlspecialchars($transaction['status']); ?></span></td>
                  <td class="px-3 py-3"><?php echo htmlspecialchars($transaction['handover_location']); ?></td>
                  <td class="px-3 py-3"><?php echo (int) $transaction['review_rating'] > 0 ? (int) $transaction['review_rating'] . '/5' : 'Not reviewed'; ?></td>
                  <td class="px-3 py-3 break-words"><div><?php echo htmlspecialchars($transaction['created_at']); ?></div><div class="text-[10px] text-slate-500">Updated: <?php echo htmlspecialchars($transaction['updated_at']); ?></div></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($transactions) > 5): ?>
          <button type="button" class="mt-3 text-xs font-bold text-blue-700 hover:text-blue-900" data-show-more="transactions" aria-expanded="false">Show more <i class="fa-solid fa-chevron-down ml-1"></i></button>
        <?php endif; ?>
      </section>

      <section id="topUpRecords" class="page-section p-5 mt-6">
        <div class="flex items-start justify-between gap-4 mb-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900">User Top-up Records</h2>
            <p class="text-[11px] text-slate-500">Completed wallet top-ups recorded in the database.</p>
          </div>
          <div class="flex flex-col gap-2 items-end">
            <label class="relative block w-64 max-w-full">
              <span class="sr-only">Search top-up records</span>
              <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
              <input type="search" class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-xs text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Search student, reference..." aria-label="Search top-up records" data-list-search="topups" />
            </label>
            <span class="text-xs font-semibold text-slate-500">Showing latest 5 of <?php echo count($topUpRecords); ?> top-ups</span>
          </div>
        </div>
        <div class="table-scroll">
          <table class="w-full table-fixed text-left admin-table">
            <thead>
              <tr class="text-[10px] uppercase tracking-[0.18em] text-slate-500">
                <th class="px-3 py-2 font-semibold">Student</th>
                <th class="px-3 py-2 font-semibold">Campus</th>
                <th class="px-3 py-2 font-semibold">Reference</th>
                <th class="px-3 py-2 font-semibold">Amount</th>
                <th class="px-3 py-2 font-semibold">Balance After</th>
                <th class="px-3 py-2 font-semibold">Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$topUpRecords): ?>
                <tr class="border-t border-gray-200 text-xs text-slate-500"><td class="px-3 py-6 text-center" colspan="6">No top-up records found.</td></tr>
              <?php endif; ?>
              <?php foreach ($topUpRecords as $topUpIndex => $topUp): ?>
                <tr class="border-t border-gray-200 text-xs text-slate-700<?php echo $topUpIndex >= 5 ? ' hidden' : ''; ?>" data-topup-row data-search-row="topups">
                  <td class="px-3 py-3"><div class="font-semibold"><?php echo htmlspecialchars($topUp['full_name']); ?></div><div class="text-[10px] text-slate-500"><?php echo htmlspecialchars($topUp['student_id']); ?> · <?php echo htmlspecialchars($topUp['email']); ?></div></td>
                  <td class="px-3 py-3"><?php echo htmlspecialchars($topUp['university'] ?? ''); ?></td>
                  <td class="px-3 py-3 font-semibold"><?php echo htmlspecialchars($topUp['transaction_ref']); ?><div class="text-[10px] font-normal text-slate-500">#<?php echo (int) $topUp['transaction_id']; ?></div></td>
                  <td class="px-3 py-3 font-bold text-emerald-700">+ RM <?php echo number_format((float) $topUp['amount'], 2); ?></td>
                  <td class="px-3 py-3">RM <?php echo number_format((float) $topUp['resulting_balance'], 2); ?></td>
                  <td class="px-3 py-3 whitespace-nowrap"><?php echo htmlspecialchars($topUp['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($topUpRecords) > 5): ?>
          <button type="button" class="mt-3 text-xs font-bold text-blue-700 hover:text-blue-900" data-show-more="topups" aria-expanded="false">Show more <i class="fa-solid fa-chevron-down ml-1"></i></button>
        <?php endif; ?>
      </section>

      <section id="removedListingHistory" class="page-section p-5 mt-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900">Removed Listing History</h2>
            <p class="text-[11px] text-slate-500">Review listings removed by administrators and the reason recorded at removal time.</p>
          </div>
          <div class="flex flex-col gap-2 items-end">
            <label class="relative block w-64 max-w-full">
              <span class="sr-only">Search removed listings</span>
              <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
              <input type="search" class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-xs text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Search listing, seller, admin..." aria-label="Search removed listings" data-list-search="removed-listings" />
            </label>
            <span class="text-xs font-semibold text-slate-500">Showing <?php echo count($removedListings); ?> removed records</span>
          </div>
        </div>
        <div class="table-scroll">
          <table class="w-full table-fixed text-left admin-table">
            <thead>
              <tr class="text-[10px] uppercase tracking-[0.18em] text-slate-500">
                <th class="w-[22%] px-3 py-2 font-semibold">Listing</th>
                <th class="w-[16%] px-3 py-2 font-semibold">Seller</th>
                <th class="w-[32%] px-3 py-2 font-semibold">Reason</th>
                <th class="w-[15%] px-3 py-2 font-semibold">Removed By</th>
                <th class="w-[15%] px-3 py-2 font-semibold">Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$removedListings): ?>
                <tr class="border-t border-slate-200 text-xs text-slate-500"><td class="px-3 py-8 text-center" colspan="5">No removed listing history found.</td></tr>
              <?php else: ?>
                <tr class="hidden border-t border-slate-200 text-xs text-slate-500" data-removed-listings-empty><td class="px-3 py-8 text-center" colspan="5">No removed listings match your search.</td></tr>
              <?php endif; ?>
              <?php foreach ($removedListings as $removedListing): ?>
                <tr class="border-t border-slate-200 align-top text-xs text-slate-700" data-search-row="removed-listings">
                  <td class="px-3 py-3"><div class="font-semibold text-slate-900"><?php echo htmlspecialchars($removedListing['title'] ?? 'Deleted listing'); ?></div><div class="text-[10px] text-slate-500">#<?php echo (int) ($removedListing['product_id'] ?? 0); ?></div></td>
                  <td class="px-3 py-3"><?php echo htmlspecialchars($removedListing['seller_name'] ?? 'Unknown seller'); ?></td>
                  <td class="px-3 py-3 break-words"><?php echo htmlspecialchars($removedListing['details'] ?? 'No reason recorded.'); ?></td>
                  <td class="px-3 py-3"><?php echo htmlspecialchars($removedListing['admin_name']); ?></td>
                  <td class="px-3 py-3 whitespace-nowrap"><?php echo htmlspecialchars($removedListing['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <div id="warningModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="warningModalTitle">
    <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl" data-warning-modal-panel>
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 id="warningModalTitle" class="text-lg font-bold text-slate-900">Issue Warning</h2>
          <p class="mt-1 text-xs text-slate-500">Enter the reason for warning <span class="font-semibold text-slate-700" data-warning-user-name></span>.</p>
        </div>
        <button type="button" class="text-slate-400 hover:text-slate-700" aria-label="Close warning dialog" data-warning-modal-close><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form method="post" class="mt-4 space-y-3">
        <input type="hidden" name="action" value="warn_user" />
        <input type="hidden" name="user_id" value="" data-warning-user-id />
        <label class="block text-xs font-semibold text-slate-600">Warning reason
          <textarea name="warning_comment" required maxlength="1000" rows="4" class="mt-1 w-full resize-y rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Explain why this warning is being issued." data-warning-modal-input></textarea>
        </label>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200" data-warning-modal-cancel>Cancel</button>
          <button type="submit" class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white hover:bg-amber-700">Issue Warning</button>
        </div>
      </form>
    </div>
  </div>

  <div id="suspendModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="suspendModalTitle">
    <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 id="suspendModalTitle" class="text-lg font-bold text-slate-900">Suspend Student</h2>
          <p class="mt-1 text-xs text-slate-500">Are you sure you want to suspend <span class="font-semibold text-slate-700" data-suspend-user-name></span>?</p>
        </div>
        <button type="button" class="text-slate-400 hover:text-slate-700" aria-label="Close suspend dialog" data-suspend-modal-close><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form method="post" class="mt-5 space-y-3">
        <input type="hidden" name="action" value="suspend_user" />
        <input type="hidden" name="user_id" value="" data-suspend-user-id />
        <label class="block text-xs font-semibold text-slate-600">Suspension reason
          <textarea name="suspend_reason" required maxlength="1000" rows="4" class="mt-1 w-full resize-y rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Explain why this student is being suspended." data-suspend-modal-input></textarea>
        </label>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200" data-suspend-modal-cancel>Cancel</button>
          <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700">Confirm Suspend</button>
        </div>
      </form>
    </div>
  </div>

  <div id="historyModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="historyModalTitle">
    <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-2xl">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 id="historyModalTitle" class="text-lg font-bold text-slate-900">Moderation History</h2>
          <p class="mt-1 text-xs text-slate-500">Previous moderation actions for <span class="font-semibold text-slate-700" data-history-user-name></span>.</p>
        </div>
        <button type="button" class="text-slate-400 hover:text-slate-700" aria-label="Close moderation history" data-history-modal-close><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="mt-4 max-h-80 space-y-3 overflow-y-auto" data-history-list></div>
      <div class="mt-4 flex justify-end">
        <button type="button" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200" data-history-modal-cancel>Close</button>
      </div>
    </div>
  </div>

  <div id="removeListingModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="removeListingModalTitle">
    <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 id="removeListingModalTitle" class="text-lg font-bold text-slate-900">Remove Listing</h2>
          <p class="mt-1 text-xs text-slate-500">Enter the reason for removing <span class="font-semibold text-slate-700" data-remove-listing-title></span>.</p>
        </div>
        <button type="button" class="text-slate-400 hover:text-slate-700" aria-label="Close remove listing dialog" data-remove-listing-close><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form method="post" class="mt-4 space-y-3">
        <input type="hidden" name="action" value="delete_listing" />
        <input type="hidden" name="product_id" value="" data-remove-listing-id />
        <label class="block text-xs font-semibold text-slate-600">Removal reason
          <textarea name="delete_reason" required maxlength="1000" rows="4" class="mt-1 w-full resize-y rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Explain why this listing is being removed." data-remove-listing-input></textarea>
        </label>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200" data-remove-listing-cancel>Cancel</button>
          <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700">Confirm Remove</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    window.__ADMIN_DATA__ = <?php echo json_encode($adminData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    window.__MODERATION_HISTORY__ = <?php echo json_encode($moderationHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const warningModal = document.getElementById('warningModal');
    const warningModalInput = warningModal.querySelector('[data-warning-modal-input]');
    const warningModalUserId = warningModal.querySelector('[data-warning-user-id]');
    const warningModalUserName = warningModal.querySelector('[data-warning-user-name]');

    const closeWarningModal = () => {
      warningModal.classList.add('hidden');
      warningModal.classList.remove('flex');
      warningModalInput.value = '';
      warningModalUserId.value = '';
      warningModalUserName.textContent = '';
    };

    document.querySelectorAll('[data-warning-trigger]').forEach((trigger) => {
      trigger.addEventListener('click', () => {
        warningModalUserId.value = trigger.dataset.userId;
        warningModalUserName.textContent = trigger.dataset.userName;
        warningModal.classList.remove('hidden');
        warningModal.classList.add('flex');
        warningModalInput.focus();
      });
    });

    warningModal.querySelector('[data-warning-modal-close]').addEventListener('click', closeWarningModal);
    warningModal.querySelector('[data-warning-modal-cancel]').addEventListener('click', closeWarningModal);
    warningModal.addEventListener('click', (event) => {
      if (event.target === warningModal) {
        closeWarningModal();
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !warningModal.classList.contains('hidden')) {
        closeWarningModal();
      }
    });

    const suspendModal = document.getElementById('suspendModal');
    const suspendModalUserId = suspendModal.querySelector('[data-suspend-user-id]');
    const suspendModalUserName = suspendModal.querySelector('[data-suspend-user-name]');
    const suspendModalInput = suspendModal.querySelector('[data-suspend-modal-input]');

    const closeSuspendModal = () => {
      suspendModal.classList.add('hidden');
      suspendModal.classList.remove('flex');
      suspendModalUserId.value = '';
      suspendModalUserName.textContent = '';
      suspendModalInput.value = '';
    };

    document.querySelectorAll('[data-suspend-trigger]').forEach((trigger) => {
      trigger.addEventListener('click', () => {
        suspendModalUserId.value = trigger.dataset.userId;
        suspendModalUserName.textContent = trigger.dataset.userName;
        suspendModal.classList.remove('hidden');
        suspendModal.classList.add('flex');
        suspendModalInput.focus();
      });
    });

    suspendModal.querySelector('[data-suspend-modal-close]').addEventListener('click', closeSuspendModal);
    suspendModal.querySelector('[data-suspend-modal-cancel]').addEventListener('click', closeSuspendModal);
    suspendModal.addEventListener('click', (event) => {
      if (event.target === suspendModal) {
        closeSuspendModal();
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !suspendModal.classList.contains('hidden')) {
        closeSuspendModal();
      }
    });

    const removeListingModal = document.getElementById('removeListingModal');
    const removeListingId = removeListingModal.querySelector('[data-remove-listing-id]');
    const removeListingTitle = removeListingModal.querySelector('[data-remove-listing-title]');
    const removeListingInput = removeListingModal.querySelector('[data-remove-listing-input]');

    const closeRemoveListingModal = () => {
      removeListingModal.classList.add('hidden');
      removeListingModal.classList.remove('flex');
      removeListingId.value = '';
      removeListingTitle.textContent = '';
      removeListingInput.value = '';
    };

    document.querySelectorAll('[data-remove-listing-trigger]').forEach((trigger) => {
      trigger.addEventListener('click', () => {
        removeListingId.value = trigger.dataset.productId;
        removeListingTitle.textContent = trigger.dataset.productTitle;
        removeListingModal.classList.remove('hidden');
        removeListingModal.classList.add('flex');
        removeListingInput.focus();
      });
    });

    removeListingModal.querySelector('[data-remove-listing-close]').addEventListener('click', closeRemoveListingModal);
    removeListingModal.querySelector('[data-remove-listing-cancel]').addEventListener('click', closeRemoveListingModal);
    removeListingModal.addEventListener('click', (event) => {
      if (event.target === removeListingModal) {
        closeRemoveListingModal();
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !removeListingModal.classList.contains('hidden')) {
        closeRemoveListingModal();
      }
    });

    const historyModal = document.getElementById('historyModal');
    const historyModalUserName = historyModal.querySelector('[data-history-user-name]');
    const historyList = historyModal.querySelector('[data-history-list]');
    const moderationHistory = window.__MODERATION_HISTORY__ || {};
    const historyActionLabels = {
      warn_user: 'Warning issued',
      suspend_user: 'Account suspended',
      unsuspend_user: 'Suspension removed',
      approve_recovery_request: 'Recovery request approved',
      reject_recovery_request: 'Recovery request rejected',
    };

    const closeHistoryModal = () => {
      historyModal.classList.add('hidden');
      historyModal.classList.remove('flex');
      historyModalUserName.textContent = '';
      historyList.replaceChildren();
    };

    document.querySelectorAll('[data-history-trigger]').forEach((trigger) => {
      trigger.addEventListener('click', () => {
        const history = moderationHistory[trigger.dataset.userId] || [];
        historyModalUserName.textContent = trigger.dataset.userName;
        historyList.replaceChildren();

        if (!history.length) {
          const emptyMessage = document.createElement('p');
          emptyMessage.className = 'text-sm text-slate-500';
          emptyMessage.textContent = 'No previous warning or suspension history found.';
          historyList.appendChild(emptyMessage);
        } else {
          history.forEach((entry) => {
            const item = document.createElement('article');
            item.className = 'rounded-lg border border-slate-200 bg-slate-50 p-3';

            const title = document.createElement('div');
            title.className = 'text-sm font-bold text-slate-800';
            title.textContent = historyActionLabels[entry.action] || entry.action;

            const details = document.createElement('p');
            details.className = 'mt-1 text-xs text-slate-600';
            details.textContent = entry.details || 'No reason provided.';

            const metadata = document.createElement('p');
            metadata.className = 'mt-2 text-[10px] text-slate-500';
            metadata.textContent = `By ${entry.admin_name || 'Administrator'} on ${entry.created_at}`;

            item.append(title, details, metadata);
            historyList.appendChild(item);
          });
        }

        historyModal.classList.remove('hidden');
        historyModal.classList.add('flex');
      });
    });

    historyModal.querySelector('[data-history-modal-close]').addEventListener('click', closeHistoryModal);
    historyModal.querySelector('[data-history-modal-cancel]').addEventListener('click', closeHistoryModal);
    historyModal.addEventListener('click', (event) => {
      if (event.target === historyModal) {
        closeHistoryModal();
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !historyModal.classList.contains('hidden')) {
        closeHistoryModal();
      }
    });

    document.querySelectorAll('[data-list-search]').forEach((searchInput) => {
      const listType = searchInput.dataset.listSearch;
      const rows = Array.from(document.querySelectorAll(`[data-search-row="${listType}"]`));
      const moreButton = document.querySelector(`[data-show-more="${listType === 'topups' ? 'topups' : listType}"]`);
      const emptyMessage = document.querySelector(`[data-${listType === 'moderation' ? 'moderation' : listType === 'recovery' ? 'recovery' : listType}-empty]`);

      searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();
        const isSearching = query !== '';
        let visibleMatches = 0;

        rows.forEach((row, index) => {
          const matches = !isSearching || row.textContent.toLowerCase().includes(query);
          const visibleLimit = moreButton ? Number(moreButton.dataset.visibleLimit || 5) : 5;
          const hiddenByDefaultLimit = !isSearching && moreButton && index >= visibleLimit && moreButton.getAttribute('aria-expanded') !== 'true';
          row.classList.toggle('hidden', !matches || hiddenByDefaultLimit);
          if (matches) {
            visibleMatches += 1;
          }
        });

        if (moreButton) {
          moreButton.classList.toggle('hidden', isSearching || rows.length <= 5);
          if (!isSearching) {
            moreButton.setAttribute('aria-expanded', 'false');
            moreButton.innerHTML = 'Show more <i class="fa-solid fa-chevron-down ml-1"></i>';
          }
        }
        if (emptyMessage) {
          emptyMessage.classList.toggle('hidden', visibleMatches > 0);
        }
      });
    });

    document.querySelectorAll('[data-show-more]').forEach((button) => {
      button.addEventListener('click', () => {
        const type = button.dataset.showMore;
        const rowSelector = type === 'moderation'
          ? '[data-search-row="moderation"]'
          : type === 'listings'
            ? '[data-search-row="listings"]'
            : type === 'topups'
              ? '[data-search-row="topups"]'
              : type === 'transactions'
                ? '[data-search-row="transactions"]'
                : '[data-log-row]';
        const rows = document.querySelectorAll(rowSelector);
        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        const visibleLimit = Number(button.dataset.visibleLimit || 5);
        rows.forEach((row, index) => {
          if (index >= visibleLimit) {
            row.classList.toggle('hidden', isExpanded);
          }
        });
        button.setAttribute('aria-expanded', String(!isExpanded));
        button.innerHTML = isExpanded
          ? 'Show more <i class="fa-solid fa-chevron-down ml-1"></i>'
          : 'Show less <i class="fa-solid fa-chevron-up ml-1"></i>';
      });
    });
  </script>
  <script src="../assets/js/app.js"></script>
</body>
</html>
