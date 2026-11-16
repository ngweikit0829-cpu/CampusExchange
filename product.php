<?php
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$user = getCurrentUser();

$productId = (int) ($_GET['id'] ?? 0);
$product = null;
$images = [];
$sellerStats = ['trust_score' => 5.00, 'completed_trades' => 0];

if ($productId > 0) {
    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) {
        $pdo = getDatabaseConnection();
    }

    $stmt = $pdo->prepare('
        SELECT
            p.product_id,
            p.seller_id,
            p.title,
            p.description,
            p.price,
            p.item_condition,
            p.status,
            p.created_at,
            c.category_name,
            seller.full_name AS seller_name,
            seller.email AS seller_email,
            seller.trust_score,
            seller.completed_trades,
            uni.university_name,
            uni.theme_color
        FROM products p
        INNER JOIN categories c ON c.category_id = p.category_id
        INNER JOIN users seller ON seller.user_id = p.seller_id
        INNER JOIN universities uni ON uni.university_id = p.university_id
        WHERE p.product_id = :product_id
        LIMIT 1
    ');
    $stmt->execute(['product_id' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($product) {
        $imageStmt = $pdo->prepare('SELECT image_url FROM product_images WHERE product_id = :product_id ORDER BY is_primary DESC, created_at ASC');
        $imageStmt->execute(['product_id' => $productId]);
        $images = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
        $sellerStats = [
            'trust_score' => (float) ($product['trust_score'] ?? 5.00),
            'completed_trades' => (int) ($product['completed_trades'] ?? 0)
        ];
    }
}

function productEscape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$primaryImage = $images[0] ?? 'assets/images/default.jpg';
$isAvailable = ($product['status'] ?? '') === 'Available';
$isCrossCampus = $product && strtolower(trim((string) ($user['university'] ?? ''))) !== strtolower(trim((string) ($product['university_name'] ?? '')));
$themeColor = strtolower(trim((string) ($user['theme_color'] ?? '')));
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) {
  $themeColor = '';
}
$themeHoverColor = $themeColor;
$themeLightColor = $themeColor;
$userUniversity = strtolower(trim((string) ($user['university'] ?? '')));
$themeClass = str_contains($userUniversity, 'new era')
  ? 'theme-newera'
  : ((str_contains($userUniversity, 'tunku abdul rahman') || str_contains($userUniversity, 'utar'))
    ? 'theme-utar'
    : ((str_contains($userUniversity, 'tarc') || str_contains($userUniversity, 'tar umt'))
      ? 'theme-tarc'
      : 'theme-newera'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="assets/image/website_icon.png" />
  <title><?php echo productEscape($product['title'] ?? 'Product Not Found'); ?> | Campus Exchange</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen <?php echo $themeClass; ?>"<?php if ($themeColor !== ''): ?> style="--primary: <?php echo productEscape($themeColor); ?>; --primary-hover: <?php echo productEscape($themeHoverColor); ?>; --primary-light: <?php echo productEscape($themeLightColor); ?>"<?php endif; ?>>
  <header class="sticky top-0 z-40 bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <a href="home.php" class="flex items-center gap-3"><div class="w-10 h-10 rounded-lg bg-custom-primary text-white flex items-center justify-center font-bold text-xl shadow-md"><i class="fa-solid fa-graduation-cap"></i></div><span class="text-lg font-bold text-gray-900">Campus<span class="text-custom-primary">Exchange</span></span></a>
      <div class="flex items-center gap-3"><a href="profile.php" class="text-xs font-semibold text-gray-600 hover:text-custom-primary transition"><i class="fa-solid fa-user mr-1"></i> Profile</a><a href="logout.php" class="text-xs font-semibold text-slate-600 hover:text-red-600 transition">Logout</a></div>
    </div>
  </header>

  <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <a href="home.php" class="inline-flex items-center text-xs font-semibold text-gray-600 hover:text-custom-primary transition mb-6"><i class="fa-solid fa-arrow-left mr-2"></i> Back to marketplace</a>
    <?php if (!$product): ?>
      <section class="bg-white rounded-2xl border border-gray-200 p-10 text-center"><i class="fa-solid fa-box-open text-4xl text-gray-300 mb-3"></i><h1 class="text-lg font-bold text-gray-900">Product not found</h1><p class="text-sm text-gray-500 mt-1">This listing may have been removed.</p></section>
    <?php else: ?>
      <section class="grid grid-cols-1 lg:grid-cols-2 gap-8 bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-7">
        <div>
          <div class="aspect-square rounded-xl bg-gray-100 overflow-hidden border border-gray-200"><img id="mainProductImage" src="<?php echo productEscape($primaryImage); ?>" alt="<?php echo productEscape($product['title']); ?>" class="w-full h-full object-cover" /></div>
          <?php if (count($images) > 1): ?><div class="grid grid-cols-3 gap-3 mt-3"><?php foreach (array_slice($images, 0, 3) as $image): ?><button type="button" onclick="document.getElementById('mainProductImage').src='<?php echo productEscape($image); ?>'" class="aspect-square rounded-lg overflow-hidden border border-gray-200 hover:border-custom-primary"><img src="<?php echo productEscape($image); ?>" alt="Product image" class="w-full h-full object-cover" /></button><?php endforeach; ?></div><?php endif; ?>
        </div>
        <div class="flex flex-col">
          <div class="flex items-start justify-between gap-3"><span class="text-[10px] font-bold uppercase tracking-wider text-custom-primary"><?php echo productEscape($product['category_name']); ?></span><span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?php echo $isAvailable ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'; ?>"><?php echo productEscape($product['status']); ?></span></div>
          <h1 class="text-2xl font-extrabold text-gray-900 mt-3"><?php echo productEscape($product['title']); ?></h1>
          <p class="text-2xl font-extrabold text-custom-primary mt-4">RM <?php echo number_format((float) $product['price'], 2); ?></p>
          <div class="flex flex-wrap gap-2 mt-4"><span class="bg-gray-100 text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-lg"><?php echo productEscape($product['item_condition']); ?></span><span class="bg-gray-100 text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-lg"><i class="fa-solid fa-building-columns mr-1"></i><?php echo productEscape($product['university_name']); ?></span></div>
          <div class="border-t border-gray-100 mt-6 pt-5"><h2 class="text-sm font-bold text-gray-900">Description</h2><p class="text-sm text-gray-600 leading-7 mt-2 whitespace-pre-line"><?php echo productEscape($product['description']); ?></p></div>
          <a href="seller.php?id=<?php echo (int) $product['seller_id']; ?>" class="block border-t border-gray-100 mt-6 pt-5 hover:bg-gray-50 rounded-lg transition px-2 -mx-2">
            <div class="flex items-center justify-between gap-2">
              <p class="text-[10px] uppercase tracking-wider font-bold text-gray-400">Seller</p>
              <span class="text-[10px] font-semibold text-custom-primary">View seller reviews <i class="fa-solid fa-chevron-right ml-0.5"></i></span>
            </div>
            <p class="text-base font-bold text-gray-900 mt-1"><?php echo productEscape($product['seller_name']); ?></p>
            <p class="text-xs text-gray-500 mt-1"><?php echo productEscape($product['university_name']); ?></p>
            <div class="flex items-center gap-2 mt-3"><span class="text-amber-500 text-base"><?php echo str_repeat('&#9733;', max(0, min(5, (int) round($sellerStats['trust_score'])))); ?></span><span class="text-sm font-bold text-gray-800"><?php echo number_format($sellerStats['trust_score'], 2); ?> / 5.00</span><span class="text-xs text-gray-500">(<?php echo $sellerStats['completed_trades']; ?> trades)</span></div>
          </a>
          <?php if ($isAvailable && (int) $product['seller_id'] !== (int) ($user['id'] ?? $user['user_id'] ?? 0)): ?><div class="mt-auto pt-7"><div class="grid grid-cols-2 gap-3"><a href="chat.php?product_id=<?php echo (int) $product['product_id']; ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-bold py-3 rounded-xl text-center transition"><i class="fa-regular fa-comment mr-1"></i> Chat seller</a><button type="button" onclick="openProductBuyModal()" class="bg-custom-primary hover:bg-opacity-90 text-white text-sm font-bold py-3 rounded-xl text-center transition">Buy Now</button></div><p id="purchaseMessage" class="text-xs text-center mt-3 hidden"></p></div><?php endif; ?>
        </div>
      </section>
    <?php endif; ?>
  </main>
  <?php if ($product && $isAvailable && (int) $product['seller_id'] !== (int) ($user['id'] ?? $user['user_id'] ?? 0)): ?>
  <div id="productBuyModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100">
      <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-100"><h2 class="text-lg font-bold text-gray-900">Confirm Escrow Order</h2><button type="button" onclick="closeProductBuyModal()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-lg"></i></button></div>
      <div class="flex gap-3 bg-gray-50 p-3 rounded-xl border border-gray-200 mb-4"><img src="<?php echo productEscape($primaryImage); ?>" alt="Product" class="w-16 h-16 rounded-lg object-cover border border-gray-200" /><div class="flex-1 min-w-0"><h3 class="text-xs font-bold text-gray-800 truncate"><?php echo productEscape($product['title']); ?></h3><p class="text-[11px] text-gray-500">Seller: <?php echo productEscape($product['seller_name']); ?></p><p class="text-sm font-extrabold text-custom-primary mt-1">RM <?php echo number_format((float) $product['price'], 2); ?></p></div></div>
      <?php if ($isCrossCampus): ?><div class="bg-amber-50 border border-amber-200 text-amber-900 text-xs p-3 rounded-xl mb-4"><div class="font-bold mb-1 flex items-center gap-1.5"><i class="fa-solid fa-triangle-exclamation text-amber-600"></i> Cross-Campus Notice</div><p>Notice: Cross-campus trade detected. Please confirm meetup logistics via private chat.</p></div><?php endif; ?>
      <div class="space-y-2 text-xs text-gray-600 mb-5"><div class="flex justify-between"><span>Your Wallet Balance:</span><span id="productBuyBalance" class="font-bold text-gray-800">RM 0.00</span></div><div class="flex justify-between"><span>Escrow Holding Fee:</span><span class="text-emerald-600 font-bold">RM 0.00 (Free)</span></div><div class="border-t border-gray-200 pt-2 flex justify-between font-bold text-gray-900"><span>Total Deduction:</span><span class="text-custom-primary">RM <?php echo number_format((float) $product['price'], 2); ?></span></div></div>
      <p id="productBuyError" class="hidden bg-red-50 border border-red-200 text-red-700 text-xs p-3 rounded-xl mb-4"></p><div class="flex gap-2"><button type="button" onclick="closeProductBuyModal()" class="w-1/2 py-2 border border-gray-300 text-gray-700 text-xs rounded-xl font-semibold">Cancel</button><button type="button" onclick="buyProduct()" class="w-1/2 py-2 bg-custom-primary text-white text-xs rounded-xl font-bold">Lock Escrow &amp; Order</button></div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($product && $isAvailable && (int) $product['seller_id'] !== (int) ($user['id'] ?? $user['user_id'] ?? 0)): ?>
  <script>
    const productData = <?php echo json_encode(['id' => (int) $product['product_id'], 'sellerId' => (int) $product['seller_id'], 'price' => (float) $product['price'], 'title' => $product['title']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const currentWalletBalance = <?php echo json_encode((float) ($user['wallet_balance'] ?? 0)); ?>;

    function openProductBuyModal() {
      document.getElementById('productBuyBalance').textContent = `RM ${currentWalletBalance.toFixed(2)}`;
      document.getElementById('productBuyError').classList.add('hidden');
      const modal = document.getElementById('productBuyModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeProductBuyModal() {
      const modal = document.getElementById('productBuyModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    async function buyProduct() {
      const message = document.getElementById('purchaseMessage');
      const error = document.getElementById('productBuyError');
      closeProductBuyModal();
      if (currentWalletBalance + 0.001 < productData.price) {
        message.textContent = 'Insufficient wallet balance.';
        message.className = 'text-xs text-center mt-3 text-red-600';
        return;
      }

      try {
        const response = await fetch('purchase_order.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ product_id: productData.id, seller_id: productData.sellerId, amount: productData.price }) });
        const data = await response.json();
        if (!data.success) {
          message.textContent = data.message || 'Unable to place order.';
          message.className = 'text-xs text-center mt-3 text-red-600';
          return;
        }
        message.textContent = `Order ${data.order_ref || ''} created. RM ${Number(data.new_balance).toFixed(2)} remains in your wallet.`;
        message.className = 'text-xs text-center mt-3 text-emerald-600';
        document.querySelector('button[onclick="buyProduct()"]')?.remove();
      } catch (error) {
        message.textContent = 'Unable to place order right now.';
        message.className = 'text-xs text-center mt-3 text-red-600';
      }
    }
  </script>
  <?php endif; ?>
</body>
</html>