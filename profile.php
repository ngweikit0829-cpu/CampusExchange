<?php
require_once __DIR__ . '/includes/helpers.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$stats = getUserDashboardStats($userId);
$receivedReviews = getReviewsForUser($userId);
$warnings = getUserWarnings($userId);
$recoveryRequests = getAccountRecoveryRequests($userId);

function profileEscape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$displayStatus = ucfirst((string) ($user['status'] ?? 'active'));
$displayRole = ucfirst((string) ($user['role'] ?? 'student'));
$phone = trim((string) ($user['phone_number'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="assets/image/website_icon.png" />
  <title>My Profile | Campus Exchange</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">
  <header class="sticky top-0 z-40 bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <a href="home.php" class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-custom-primary text-white flex items-center justify-center font-bold text-xl shadow-md"><i class="fa-solid fa-graduation-cap"></i></div>
        <span class="text-lg font-bold text-gray-900">Campus<span class="text-custom-primary">Exchange</span></span>
      </a>
      <div class="flex items-center gap-3">
        <a href="home.php" class="text-xs font-semibold text-gray-600 hover:text-custom-primary transition"><i class="fa-solid fa-house mr-1"></i> Home</a>
        <a href="logout.php" class="text-xs font-semibold text-slate-600 hover:text-red-600 transition">Logout</a>
      </div>
    </div>
  </header>

  <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
      <p class="text-xs font-bold uppercase tracking-wider text-custom-primary mb-1">Account</p>
      <h1 class="text-2xl font-extrabold text-gray-900">My Profile</h1>
      <p class="text-sm text-gray-500 mt-1">Your verified student account details.</p>
    </div>

    <section class="bg-custom-primary rounded-2xl p-6 sm:p-8 text-white shadow-lg mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="w-20 h-20 rounded-full bg-white/20 border-2 border-white/40 flex items-center justify-center text-3xl font-bold shrink-0">
          <?php echo profileEscape(strtoupper(substr((string) ($user['full_name'] ?? 'S'), 0, 1))); ?>
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-xl font-extrabold truncate"><?php echo profileEscape($user['full_name'] ?? 'Student'); ?></h2>
            <span class="bg-emerald-400/20 border border-emerald-200/40 text-emerald-100 text-[10px] font-bold px-2 py-1 rounded-full"><i class="fa-solid fa-circle-check mr-1"></i>Verified Student</span>
          </div>
          <p class="text-blue-100 text-sm mt-1"><?php echo profileEscape($user['university'] ?? 'University'); ?></p>
          <p class="text-blue-100/80 text-xs mt-1">Student ID: <?php echo profileEscape($user['student_id'] ?? ''); ?></p>
        </div>
      </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <section class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3 mb-4">Personal Information</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
          <div><dt class="text-[10px] uppercase tracking-wider font-bold text-gray-400">Full Name</dt><dd class="text-sm font-semibold text-gray-800 mt-1"><?php echo profileEscape($user['full_name'] ?? ''); ?></dd></div>
          <div><dt class="text-[10px] uppercase tracking-wider font-bold text-gray-400">Student ID</dt><dd class="text-sm font-semibold text-gray-800 mt-1"><?php echo profileEscape($user['student_id'] ?? ''); ?></dd></div>
          <div><dt class="text-[10px] uppercase tracking-wider font-bold text-gray-400">University</dt><dd class="text-sm font-semibold text-gray-800 mt-1"><?php echo profileEscape($user['university'] ?? ''); ?></dd></div>
          <div><dt class="text-[10px] uppercase tracking-wider font-bold text-gray-400">University Email</dt><dd class="text-sm font-semibold text-gray-800 mt-1 break-all"><?php echo profileEscape($user['email'] ?? ''); ?></dd></div>
          <div><dt class="text-[10px] uppercase tracking-wider font-bold text-gray-400">Phone Number</dt><dd class="text-sm font-semibold text-gray-800 mt-1"><?php echo profileEscape($phone !== '' ? $phone : 'Not provided'); ?></dd></div>
          <div><dt class="text-[10px] uppercase tracking-wider font-bold text-gray-400">Account Type</dt><dd class="text-sm font-semibold text-gray-800 mt-1"><?php echo profileEscape($displayRole); ?></dd></div>
        </dl>
      </section>

      <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3 mb-4">Account Overview</h2>
        <div class="space-y-4">
          <div class="flex items-center justify-between"><span class="text-xs text-gray-500">Wallet balance</span><span class="text-sm font-extrabold text-emerald-600">RM <?php echo number_format((float) ($user['wallet_balance'] ?? 0), 2); ?></span></div>
          <div class="flex items-center justify-between"><span class="text-xs text-gray-500">Watchlist items</span><span class="text-sm font-bold text-gray-800"><?php echo (int) ($stats['watchlist_count'] ?? 0); ?></span></div>
          <div class="flex items-center justify-between"><span class="text-xs text-gray-500">Completed trades</span><span class="text-sm font-bold text-gray-800"><?php echo (int) ($stats['completed_trades'] ?? 0); ?></span></div>
          <div class="flex items-center justify-between"><span class="text-xs text-gray-500">Trust score</span><span class="text-sm font-bold text-gray-800"><?php echo number_format((float) ($stats['trust_score'] ?? 0), 1); ?> / 5.0</span></div>
          <div class="flex items-center justify-between pt-3 border-t border-gray-100"><span class="text-xs text-gray-500">Account status</span><span class="text-xs font-bold text-emerald-600"><?php echo profileEscape($displayStatus); ?></span></div>
        </div>
      </section>
    </div>

    <?php if ($warnings): ?>
      <section class="bg-white rounded-2xl border border-amber-200 shadow-sm p-6 mt-6">
        <div class="flex items-center justify-between gap-3 border-b border-amber-100 pb-3 mb-4">
          <h2 class="text-sm font-bold text-gray-900"><i class="fa-solid fa-triangle-exclamation text-amber-500 mr-2"></i>Account Warnings</h2>
          <span class="text-xs text-gray-400"><?php echo count($warnings); ?> warning<?php echo count($warnings) === 1 ? '' : 's'; ?></span>
        </div>
        <div class="space-y-3">
          <?php foreach ($warnings as $warning): ?>
            <article class="rounded-xl border border-amber-100 bg-amber-50 p-3">
              <p class="text-sm text-amber-900"><?php echo profileEscape($warning['details'] ?? 'Warning issued.'); ?></p>
              <p class="text-[10px] text-amber-700 mt-1">Issued by <?php echo profileEscape($warning['admin_name'] ?? 'Administrator'); ?> on <?php echo profileEscape($warning['created_at'] ?? ''); ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($recoveryRequests): ?>
      <section class="bg-white rounded-2xl border border-blue-200 shadow-sm p-6 mt-6">
        <div class="flex items-center justify-between gap-3 border-b border-blue-100 pb-3 mb-4">
          <h2 class="text-sm font-bold text-gray-900"><i class="fa-solid fa-unlock-keyhole text-blue-500 mr-2"></i>Account Recovery Requests</h2>
          <span class="text-xs text-gray-400"><?php echo count($recoveryRequests); ?> request<?php echo count($recoveryRequests) === 1 ? '' : 's'; ?></span>
        </div>
        <div class="space-y-3">
          <?php foreach ($recoveryRequests as $request): ?>
            <article class="rounded-xl border <?php echo $request['status'] === 'pending' ? 'border-amber-100 bg-amber-50' : ($request['status'] === 'approved' ? 'border-emerald-100 bg-emerald-50' : 'border-red-100 bg-red-50'); ?> p-3">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="rounded-full px-2 py-1 text-[10px] font-bold <?php echo $request['status'] === 'pending' ? 'bg-amber-100 text-amber-700' : ($request['status'] === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'); ?>"><?php echo ucfirst(profileEscape($request['status'])); ?></span>
                <span class="text-[10px] text-gray-500"><?php echo profileEscape($request['created_at']); ?></span>
              </div>
              <p class="mt-2 text-sm text-gray-700"><span class="font-semibold">Your reason:</span> <?php echo nl2br(profileEscape($request['reason'])); ?></p>
              <?php if (trim((string) ($request['admin_response'] ?? '')) !== ''): ?>
                <p class="mt-2 text-sm text-gray-700"><span class="font-semibold">Administrator response:</span> <?php echo nl2br(profileEscape($request['admin_response'])); ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mt-6">
      <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-3 mb-4">
        <h2 class="text-sm font-bold text-gray-900">Reviews From Buyers</h2>
        <span class="text-xs text-gray-400"><?php echo count($receivedReviews); ?> review<?php echo count($receivedReviews) === 1 ? '' : 's'; ?></span>
      </div>
      <?php if (!$receivedReviews): ?>
        <p class="text-sm text-gray-400 text-center py-5">No buyer reviews yet.</p>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($receivedReviews as $review): ?>
            <article class="border-b border-gray-100 last:border-0 pb-4 last:pb-0">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p class="text-xs font-bold text-gray-800"><?php echo profileEscape($review['reviewer_name'] ?? 'Buyer'); ?></p>
                  <p class="text-[10px] text-gray-400 mt-0.5">For <?php echo profileEscape($review['product_title'] ?? 'Product'); ?></p>
                </div>
                <div class="text-right">
                  <div class="text-amber-500 text-sm"><?php echo str_repeat('&#9733;', (int) $review['rating']); ?><span class="text-gray-300"><?php echo str_repeat('&#9733;', 5 - (int) $review['rating']); ?></span></div>
                  <p class="text-[10px] text-gray-400 mt-0.5"><?php echo profileEscape($review['created_at'] ?? ''); ?></p>
                </div>
              </div>
              <?php if (trim((string) ($review['comment'] ?? '')) !== ''): ?>
                <p class="text-sm text-gray-600 mt-3 leading-relaxed"><?php echo profileEscape($review['comment']); ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
