<?php
require_once __DIR__ . '/includes/helpers.php';
requireLogin();

$user = getCurrentUser();
$sellerId = (int) ($_GET['id'] ?? 0);
$seller = null;
$reviews = [];

if ($sellerId > 0) {
    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $stmt = $pdo->prepare('
        SELECT
            u.user_id,
            u.full_name,
            u.trust_score,
            u.completed_trades,
            u.created_at,
            uni.university_name
        FROM users u
        LEFT JOIN universities uni ON uni.university_id = u.university_id
        WHERE u.user_id = :user_id AND u.role = "student" AND u.status = "active"
        LIMIT 1
    ');
    $stmt->execute(['user_id' => $sellerId]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($seller) {
        $reviews = getReviewsForUser($sellerId);
    }
}

function sellerEscape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$themeColor = strtolower(trim((string) ($user['theme_color'] ?? '')));
$themeColor = preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor) ? $themeColor : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="assets/image/website_icon.png" />
  <title><?php echo sellerEscape($seller['full_name'] ?? 'Seller Not Found'); ?> | Campus Exchange</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen"<?php if ($themeColor !== ''): ?> style="--primary: <?php echo sellerEscape($themeColor); ?>; --primary-hover: <?php echo sellerEscape($themeColor); ?>; --primary-light: <?php echo sellerEscape($themeColor); ?>"<?php endif; ?>>
  <header class="sticky top-0 z-40 bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
      <a href="home.php" class="flex items-center gap-3"><div class="w-10 h-10 rounded-lg bg-custom-primary text-white flex items-center justify-center font-bold text-xl shadow-md"><i class="fa-solid fa-graduation-cap"></i></div><span class="text-lg font-bold text-gray-900">Campus<span class="text-custom-primary">Exchange</span></span></a>
      <a href="profile.php" class="text-xs font-semibold text-gray-600 hover:text-custom-primary transition"><i class="fa-solid fa-user mr-1"></i> My Profile</a>
    </div>
  </header>

  <main class="max-w-3xl mx-auto px-4 py-8">
    <a href="javascript:history.back()" class="inline-flex items-center text-xs font-semibold text-gray-600 hover:text-custom-primary transition mb-5"><i class="fa-solid fa-arrow-left mr-2"></i> Back</a>

    <?php if (!$seller): ?>
      <section class="bg-white rounded-2xl border border-gray-200 p-10 text-center"><i class="fa-solid fa-user-slash text-4xl text-gray-300 mb-3"></i><h1 class="text-lg font-bold text-gray-900">Seller not found</h1><p class="text-sm text-gray-500 mt-1">This seller account is unavailable.</p></section>
    <?php else: ?>
      <section class="bg-custom-primary text-white rounded-2xl p-6 shadow-md mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
          <div class="w-16 h-16 rounded-full bg-white/40 border-2 border-white/60 flex items-center justify-center text-2xl font-extrabold shrink-0"><?php echo sellerEscape(strtoupper(substr((string) $seller['full_name'], 0, 1))); ?></div>
          <div class="min-w-0">
            <h1 class="text-xl font-extrabold truncate"><?php echo sellerEscape($seller['full_name']); ?></h1>
            <p class="text-sm text-white/80 mt-0.5"><i class="fa-solid fa-building-columns mr-1"></i><?php echo sellerEscape($seller['university_name'] ?? 'University'); ?></p>
            <div class="flex flex-wrap items-center gap-3 mt-2 text-sm">
              <span class="flex items-center gap-1 font-bold"><i class="fa-solid fa-star text-white"></i> <?php echo number_format((float) $seller['trust_score'], 2); ?> / 5.00</span>
              <span class="text-white/80 text-xs"><?php echo (int) $seller['completed_trades']; ?> completed trades</span>
            </div>
          </div>
        </div>
      </section>

      <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-3 mb-4">
          <h2 class="text-sm font-bold text-gray-900"><i class="fa-solid fa-star text-amber-500 mr-1"></i> Buyer Reviews</h2>
          <span class="text-xs text-gray-400"><?php echo count($reviews); ?> review<?php echo count($reviews) === 1 ? '' : 's'; ?></span>
        </div>

        <?php if (!$reviews): ?>
          <p class="text-sm text-gray-400 text-center py-6">This seller has not received any reviews yet.</p>
        <?php else: ?>
          <div class="space-y-4">
            <?php foreach ($reviews as $review): ?>
              <article class="border-b border-gray-100 last:border-0 pb-4 last:pb-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p class="text-xs font-bold text-gray-800"><?php echo sellerEscape($review['reviewer_name'] ?? 'Buyer'); ?></p>
                    <p class="text-[10px] text-gray-400 mt-0.5">For <?php echo sellerEscape($review['product_title'] ?? 'Product'); ?></p>
                  </div>
                  <div class="text-right">
                    <div class="text-amber-500 text-sm"><?php echo str_repeat('&#9733;', (int) $review['rating']); ?><span class="text-gray-300"><?php echo str_repeat('&#9733;', 5 - (int) $review['rating']); ?></span></div>
                    <p class="text-[10px] text-gray-400 mt-0.5"><?php echo sellerEscape($review['created_at'] ?? ''); ?></p>
                  </div>
                </div>
                <?php if (trim((string) ($review['comment'] ?? '')) !== ''): ?>
                  <p class="text-sm text-gray-600 mt-3 leading-relaxed"><?php echo sellerEscape($review['comment']); ?></p>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
