<?php
require_once __DIR__ . '/includes/helpers.php';
if (!empty($_SESSION['user'])) {
    header('Location: home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="assets/image/website_icon.png" />
  <title>Campus Exchange | Register</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body class="theme-newera auth-shell" data-page="register">
  <div class="max-w-5xl mx-auto px-4 py-8">
    <div class="auth-card rounded-3xl overflow-hidden">
      <div class="grid grid-cols-1 lg:grid-cols-[1.1fr_1.4fr]">
        <div class="bg-custom-primary text-white p-8 hidden lg:block">
          <div class="flex items-center gap-3 mb-8">
            <div class="w-12 h-12 rounded-xl bg-white/10 backdrop-blur text-white flex items-center justify-center shadow-md"><i class="fa-solid fa-graduation-cap text-xl"></i></div>
            <div>
              <div class="text-xl font-extrabold tracking-tight">Campus<span class="text-blue-200">Exchange</span></div>
              <div class="text-xs text-blue-100">Partner Campus Signup</div>
            </div>
          </div>
          <h1 class="text-3xl font-black mb-4">Create your student profile</h1>
          <p class="text-blue-100 text-sm leading-7 mb-8">Join the campus marketplace with your verified student email.</p>
          <div class="space-y-3 text-sm text-blue-50">
            <div class="flex items-center gap-3"><i class="fa-solid fa-circle-check"></i> Verified student email required</div>
            <div class="flex items-center gap-3"><i class="fa-solid fa-circle-check"></i> Password strength enforcement</div>
            <div class="flex items-center gap-3"><i class="fa-solid fa-circle-check"></i> University-specific branding</div>
            <div class="flex items-center gap-3"><i class="fa-solid fa-circle-check"></i> Safe escrow purchase workflow</div>
          </div>
        </div>

        <div class="p-6 md:p-8">
          <div class="flex items-center justify-between mb-6">
            <div>
              <div class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Register</div>
              <h2 class="text-2xl font-black text-slate-900 mt-1">Create Student Account</h2>
            </div>
            <a href="index.php" class="text-xs font-bold text-custom-primary">Back to Login</a>
          </div>

          <?php $flash = flash('flash_error'); if ($flash): ?>
            <div class="alert-box alert-error show"><?php echo htmlspecialchars($flash); ?></div>
          <?php endif; ?>

          <form action="auth.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="register" />
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Full Name</label>
              <input name="full_name" type="text" class="input-shell" placeholder="e.g. Alex Tan" required />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Student ID</label>
                <input name="student_id" type="text" class="input-shell" placeholder="24B0037" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Phone Number</label>
                <input name="phone_number" type="tel" class="input-shell" placeholder="0123456789" required />
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">University Email</label>
              <input name="email" type="email" class="input-shell" placeholder="name@student.newera.edu.my" required />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Password</label>
                <input name="password" type="password" class="input-shell" placeholder="Minimum 8 chars" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Confirm Password</label>
                <input name="confirm_password" type="password" class="input-shell" placeholder="Re-enter password" required />
              </div>
            </div>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 text-[11px] rounded-xl p-3"><strong>Password Rule:</strong> 8-32 chars, uppercase, lowercase, number, and special character required.</div>
            <button type="submit" class="btn-primary w-full py-3 font-bold text-sm">Create Account</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
