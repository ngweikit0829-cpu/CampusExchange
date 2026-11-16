<?php
require_once __DIR__ . '/includes/helpers.php';
if (!empty($_SESSION['user'])) {
    header('Location: home.php');
    exit;
}
$showRecoveryForm = !empty($_SESSION['show_recovery_form']);
$suspendedEmail = $_SESSION['suspended_email'] ?? '';
unset($_SESSION['show_recovery_form'], $_SESSION['suspended_email']);
$loginStatsStmt = getDatabaseConnection()->query('
  SELECT
    (SELECT COUNT(*) FROM universities) AS partner_campuses,
    (SELECT COALESCE(SUM(price_amount), 0) FROM orders WHERE status = "Completed") AS completed_trade_volume,
    (SELECT COUNT(*) FROM users WHERE role = "student") AS verified_students,
    (SELECT COUNT(*) FROM products WHERE status = "Available") AS active_listings
');
$loginStats = $loginStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$partnerCampuses = (int) ($loginStats['partner_campuses'] ?? 0);
$completedTradeVolume = (float) ($loginStats['completed_trade_volume'] ?? 0);
$verifiedStudents = (int) ($loginStats['verified_students'] ?? 0);
$activeListings = (int) ($loginStats['active_listings'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="assets/image/website_icon.png" />
  <title>Campus Exchange | Student Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body class="theme-newera auth-shell" data-page="login">
  <div class="max-w-6xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 items-center min-h-[90vh] gap-8">
      <div class="hidden lg:block">
        <div class="bg-white/70 border border-white/80 rounded-3xl p-8 shadow-xl">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-xl bg-custom-primary text-white flex items-center justify-center shadow-lg"><i class="fa-solid fa-graduation-cap text-xl"></i></div>
            <div>
              <div class="text-xl font-extrabold tracking-tight">Campus<span class="text-custom-primary">Exchange</span></div>
              <div class="text-xs text-slate-500">Verified Student Marketplace</div>
            </div>
          </div>
          <h1 class="text-4xl font-black tracking-tight text-slate-900 mb-4">Trade smarter. Buy faster. Sell easier.</h1>
          <p class="text-slate-600 text-sm leading-7 mb-6">A trusted second-hand marketplace for Malaysian university students.</p>
          <div class="grid grid-cols-2 gap-4">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4"><div class="text-2xl font-black text-custom-primary"><?php echo $partnerCampuses; ?></div><div class="text-xs text-slate-500">Partner campuses</div></div>
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4"><div class="text-2xl font-black text-custom-primary">RM <?php echo number_format($completedTradeVolume, 0); ?></div><div class="text-xs text-slate-500">Completed trade volume</div></div>
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4"><div class="text-2xl font-black text-custom-primary"><?php echo $verifiedStudents; ?></div><div class="text-xs text-slate-500">Verified students</div></div>
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4"><div class="text-2xl font-black text-custom-primary"><?php echo $activeListings; ?></div><div class="text-xs text-slate-500">Active listings</div></div>
          </div>
        </div>
      </div>

      <div class="auth-card rounded-3xl p-6 md:p-8">
        <div class="flex items-center justify-between mb-6">
          <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Welcome Back</div>
            <h2 class="text-2xl font-black text-slate-900 mt-1">Student Sign In</h2>
          </div>
          <span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-1 rounded-full">Secure Access</span>
        </div>

        <?php $flash = flash('flash_error'); if ($flash): ?>
          <div class="alert-box alert-error show"><?php echo htmlspecialchars($flash); ?></div>
        <?php endif; ?>
        <?php $flash = flash('flash_success'); if ($flash): ?>
          <div class="alert-box alert-success show"><?php echo htmlspecialchars($flash); ?></div>
        <?php endif; ?>

        <?php if ($showRecoveryForm): ?>
          <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <div class="flex items-start gap-3">
              <i class="fa-solid fa-lock text-amber-600 mt-0.5"></i>
              <div>
                <h3 class="text-sm font-bold text-amber-900">Request account recovery</h3>
                <p class="mt-1 text-xs leading-5 text-amber-800">Explain why you believe your suspended account should be restored. An administrator will review your request.</p>
              </div>
            </div>
            <form action="auth.php" method="POST" class="mt-3 space-y-3">
              <input type="hidden" name="action" value="submit_recovery_request" />
              <input name="email" type="email" value="<?php echo htmlspecialchars($suspendedEmail); ?>" class="input-shell" placeholder="University email" required />
              <textarea name="recovery_reason" rows="4" maxlength="2000" class="input-shell resize-y" placeholder="Why should your account be restored?" required></textarea>
              <button type="submit" class="w-full rounded-lg bg-amber-600 py-3 text-sm font-bold text-white hover:bg-amber-700">Submit Recovery Request</button>
            </form>
          </div>
        <?php endif; ?>

        <form action="auth.php" method="POST" class="space-y-4">
          <input type="hidden" name="action" value="login" />
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-2">University Email</label>
            <input name="email" type="email" class="input-shell" placeholder="name@student.newera.edu.my" required />
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-2">Password</label>
            <input name="password" type="password" class="input-shell" placeholder="Enter your password" required />
          </div>

          <div class="flex justify-end text-xs text-slate-500">
            <a href="#forgotPasswordModal" id="forgotPasswordLink" class="text-custom-primary font-semibold">Forgot password?</a>
          </div>

          <button type="submit" class="btn-primary w-full py-3 font-bold text-sm">Login to Marketplace</button>
        </form>

        <div class="mt-6 text-center text-xs text-slate-500">
          New here? <a href="register.php" class="font-bold text-custom-primary">Create a student account</a>
        </div>
      </div>
    </div>
  </div>

  <div id="forgotPasswordModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-6 shadow-2xl">
      <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-3">
        <h3 class="flex items-center gap-2 text-lg font-bold text-gray-900"><i class="fa-solid fa-key text-custom-primary"></i> Reset Password</h3>
        <button type="button" id="closeForgotPasswordModal" class="text-gray-400 hover:text-gray-600" aria-label="Close reset password form"><i class="fa-solid fa-xmark text-lg"></i></button>
      </div>
      <p class="mb-4 text-xs leading-5 text-gray-600">Enter your university email and we will check whether it can be used for password recovery.</p>
      <div id="forgotPasswordError" class="mb-3 hidden rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700"></div>
      <form id="forgotPasswordForm" class="space-y-4">
        <input type="hidden" name="action" value="request_password_reset" />
        <div>
          <label for="forgotPasswordEmail" class="mb-2 block text-xs font-semibold text-gray-700">University Email</label>
          <input id="forgotPasswordEmail" name="email" type="email" class="input-shell" placeholder="name@student.newera.edu.my" required />
        </div>
        <button type="submit" class="btn-primary w-full py-3 text-sm font-bold">Check Email</button>
      </form>
    </div>
  </div>

  <script>
    const forgotPasswordModal = document.getElementById('forgotPasswordModal');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const forgotPasswordError = document.getElementById('forgotPasswordError');

    function closeForgotPasswordModal() {
      forgotPasswordModal.classList.add('hidden');
      forgotPasswordModal.classList.remove('flex');
      forgotPasswordError.classList.add('hidden');
      forgotPasswordError.textContent = '';
    }

    document.getElementById('forgotPasswordLink').addEventListener('click', (event) => {
      event.preventDefault();
      forgotPasswordModal.classList.remove('hidden');
      forgotPasswordModal.classList.add('flex');
      document.getElementById('forgotPasswordEmail').focus();
    });

    document.getElementById('closeForgotPasswordModal').addEventListener('click', closeForgotPasswordModal);
    forgotPasswordModal.addEventListener('click', (event) => {
      if (event.target === forgotPasswordModal) {
        closeForgotPasswordModal();
      }
    });

    forgotPasswordForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      forgotPasswordError.classList.add('hidden');

      try {
        const response = await fetch('auth.php', {
          method: 'POST',
          headers: { 'Accept': 'application/json' },
          body: new FormData(forgotPasswordForm)
        });
        const result = await response.json();

        if (!result.ok) {
          forgotPasswordError.textContent = result.message;
          forgotPasswordError.classList.remove('hidden');
          return;
        }

        closeForgotPasswordModal();
        window.alert('Email verified. Please check your email to reset your password.');
      } catch (error) {
        forgotPasswordError.textContent = 'Unable to check the email right now. Please try again.';
        forgotPasswordError.classList.remove('hidden');
      }
    });
  </script>
</body>
</html>
