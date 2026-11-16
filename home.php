<?php
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$user = getCurrentUser();
$universitiesStmt = $pdo->query('SELECT university_name FROM universities ORDER BY university_name ASC');
$universities = $universitiesStmt->fetchAll(PDO::FETCH_COLUMN);
$stats = getUserDashboardStats((int) ($user['id'] ?? $user['user_id'] ?? 0));
$userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$warnings = getUserWarnings($userId);
$latestWarningId = (int) ($warnings[0]['log_id'] ?? 0);
$lastWarningReadStmt = $pdo->prepare('SELECT last_seen_log_id FROM account_warning_reads WHERE user_id = :user_id LIMIT 1');
$lastWarningReadStmt->execute(['user_id' => $userId]);
$lastShownWarningId = (int) ($lastWarningReadStmt->fetchColumn() ?? 0);
$showWarningNotice = $latestWarningId > 0 && $latestWarningId > $lastShownWarningId;
$themeColor = (string) ($user['theme_color'] ?? '');
$themeColor = preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor) ? strtolower($themeColor) : '#1e40af';
$universityName = strtolower(trim((string) ($user['university'] ?? '')));
$themeClass = str_contains($universityName, 'new era')
  ? 'theme-newera'
  : (str_contains($universityName, 'tunku abdul rahman') || str_contains($universityName, 'utar')
    ? 'theme-utar'
    : (str_contains($universityName, 'tarc') || str_contains($universityName, 'tar umt')
      ? 'theme-tarc'
      : 'theme-newera'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <script>
    window.__APP_USER__ = <?php echo json_encode([
      'id' => $user['id'] ?? null,
      'full_name' => $user['full_name'] ?? 'Student',
      'student_id' => $user['student_id'] ?? '',
      'email' => $user['email'] ?? '',
      'role' => $user['role'] ?? 'student',
      'university' => $user['university'] ?? 'New Era University College',
      'theme_color' => $themeColor,
      'wallet_balance' => (float)($user['wallet_balance'] ?? 150),
      'unread_messages' => (int)($stats['unread_messages'] ?? 0),
      'seller_orders_count' => (int)($stats['seller_orders_count'] ?? 0),
      'seller_removed_listings_count' => (int)($stats['seller_removed_listings_count'] ?? 0),
      'watchlist_count' => (int)($stats['watchlist_count'] ?? 0),
      'trust_score' => (float)($stats['trust_score'] ?? 5.00),
      'completed_trades' => (int)($stats['completed_trades'] ?? 0),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="assets/image/website_icon.png" />
  <title>Campus Marketplace | Student Second-Hand E-Commerce</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body class="bg-gray-50 font-sans text-gray-800 transition-colors duration-300 min-h-screen flex flex-col <?php echo $themeClass; ?>" style="--primary: <?php echo htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>" data-page="home" id="appBody">
  <header class="sticky top-0 z-40 bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16 gap-4">
        <div class="flex items-center space-x-3 shrink-0">
          <div id="uniLogoContainer" class="w-10 h-10 rounded-lg bg-custom-primary text-white flex items-center justify-center font-bold text-xl shadow-md transition-all">
            <i id="uniLogoIcon" class="fa-solid fa-graduation-cap"></i>
          </div>
          <div>
            <a href="home.php" class="text-lg font-bold text-gray-900 tracking-tight leading-none block">Campus<span class="text-custom-primary">Exchange</span></a>
          </div>
        </div>

        <div class="flex-1 max-w-2xl mx-2 hidden md:flex items-center gap-2">
          <div class="relative w-48">
            <select id="headerCampusFilter" onchange="applyFilters()" class="w-full bg-gray-100 border border-gray-300 text-gray-700 text-xs rounded-lg pl-3 pr-8 py-2.5 focus:outline-none focus:bg-white focus:border-blue-500 appearance-none font-medium truncate">
              <option value="home">📍 Home Campus Only</option>
              <option value="all">🌐 All Partner Campuses</option>
              <?php foreach ($universities as $university): ?>
                <?php
                $universityFilterValue = preg_match('/new era/i', $university)
                  ? 'New Era UC'
                  : (preg_match('/university\s+tunku\s+abdul\s+rahman/i', $university) || preg_match('/utar/i', $university)
                    ? 'UTAR'
                    : (preg_match('/tunku abdul rahman|tar umt|tarc/i', $university) ? 'TAR UMT' : $university));
                ?>
                <option value="<?php echo htmlspecialchars($universityFilterValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($university); ?></option>
              <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-chevron-down absolute right-2.5 top-3.5 text-xs text-gray-400 pointer-events-none"></i>
          </div>
          <div class="relative flex-1">
            <input type="text" id="searchInput" placeholder="Search textbooks, electronics, dorm items... (min 2 chars)" class="w-full bg-gray-100 border border-gray-300 text-gray-800 text-sm rounded-lg pl-9 pr-10 py-2 focus:outline-none focus:bg-white focus:border-blue-500 transition" />
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400 text-sm"></i>
            <button onclick="executeSearch()" class="absolute right-1.5 top-1.5 bg-custom-primary text-white px-3 py-1 rounded-md text-xs font-medium hover:bg-opacity-90 transition">Search</button>
          </div>
        </div>

        <div class="flex items-center space-x-3 shrink-0">
          <button onclick="openWalletModal()" class="flex items-center space-x-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 px-3 py-1.5 rounded-lg transition text-xs font-semibold">
            <i class="fa-solid fa-wallet text-emerald-600"></i>
            <span id="navWalletBalance">RM <?php echo number_format((float)($user['wallet_balance'] ?? 150), 2); ?></span>
          </button>

          <button onclick="openChatModal()" class="relative p-2 text-gray-600 hover:text-custom-primary hover:bg-gray-100 rounded-lg transition" title="Messages">
            <i class="fa-regular fa-comments text-lg"></i>
            <span id="unreadBadge" class="absolute top-1 right-1 bg-red-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center border-2 border-white"><?php echo (int)($stats['unread_messages'] ?? 0); ?></span>
          </button>

          <button onclick="filterFavorites()" class="p-2 text-gray-600 hover:text-red-500 hover:bg-gray-100 rounded-lg transition relative" title="Watchlist">
            <i class="fa-regular fa-heart text-lg"></i>
            <span id="favoriteCount" class="absolute top-1 right-1 bg-red-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center"><?php echo (int)($stats['watchlist_count'] ?? 0); ?></span>
          </button>

          <a href="profile.php" class="flex items-center space-x-2 pl-2 border-l border-gray-200 hover:opacity-80 transition" title="View profile">
            <div id="profileThumb" aria-label="User initial" class="w-8 h-8 rounded-full border border-gray-300 bg-custom-primary text-white flex items-center justify-center text-sm font-bold uppercase"><?php echo htmlspecialchars(strtoupper(substr((string) ($user['full_name'] ?? 'S'), 0, 1))); ?></div>
            <div class="hidden lg:block text-left">
              <div id="profileNameLabel" class="text-xs font-bold text-gray-800 leading-tight"><?php echo htmlspecialchars($user['full_name']); ?></div>
              <div id="profileTrustLabel" class="text-[10px] text-gray-500 flex items-center gap-1"><i class="fa-solid fa-star text-amber-400"></i> <?php echo number_format((float)($stats['trust_score'] ?? 5.00), 1, '.', ''); ?> (<?php echo (int)($stats['completed_trades'] ?? 0); ?> trades)</div>
            </div>
          </a>

          <a href="logout.php" class="text-xs font-semibold text-slate-600 hover:text-red-600">Logout</a>
        </div>
      </div>
    </div>
  </header>

  <div id="searchAlert" class="hidden bg-amber-50 border-b border-amber-200 text-amber-800 text-xs py-2 px-4 text-center"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i><span id="searchAlertText">Search query must contain at least 2 characters.</span></div>

  <?php if ($showWarningNotice): ?>
    <section class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 pt-4" aria-label="Account warnings">
      <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900 shadow-sm">
        <div class="flex items-start gap-3">
          <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-600"></i>
          <div class="flex-1">
            <div class="font-bold text-sm">Account warning</div>
            <div class="mt-1 text-xs leading-5">
              <?php echo htmlspecialchars($warnings[0]['details']); ?>
              <span class="text-amber-700">(<?php echo htmlspecialchars($warnings[0]['created_at']); ?>)</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="relative overflow-hidden rounded-2xl bg-custom-primary text-white p-6 sm:p-8 mb-8 shadow-lg transition-all duration-300">
      <div class="absolute -right-10 -bottom-10 opacity-10 text-9xl pointer-events-none"><i class="fa-solid fa-graduation-cap"></i></div>
      <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
          <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-md px-3 py-1 rounded-full text-xs font-medium mb-3">
            <i class="fa-solid fa-shield-halved text-amber-300"></i>
            <span>Verified Student Account</span>
            <span class="opacity-60">•</span>
            <span id="heroUniBadge">New Era University College</span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Welcome back, <span id="heroStudentName"><?php echo htmlspecialchars($user['full_name']); ?></span>! 👋</h1>
          <p class="text-blue-100 text-sm mt-1 max-w-xl">Discover second-hand textbooks, electronics, and dorm essentials from fellow verified students on your campus.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button onclick="document.getElementById('postModal').classList.remove('hidden')" class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold px-4 py-2.5 rounded-xl shadow-md transition flex items-center gap-2 text-sm"><i class="fa-solid fa-circle-plus"></i> Post Listing</button>
          <?php $myListingsBadgeCount = (int)($stats['seller_orders_count'] ?? 0) + (int)($stats['seller_removed_listings_count'] ?? 0); ?>
          <a href="my_listings.php" class="relative bg-white/10 hover:bg-white/20 text-white font-semibold px-4 py-2.5 rounded-xl border border-white/20 transition flex items-center gap-2 text-sm"><i class="fa-solid fa-list text-violet-200"></i> My Listings<?php if ($myListingsBadgeCount > 0): ?><span class="absolute -top-2 -right-2 min-w-5 h-5 px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white" aria-label="<?php echo $myListingsBadgeCount; ?> My Listings notifications"><?php echo $myListingsBadgeCount; ?></span><?php endif; ?></a>
          <a href="orders.php" class="bg-white/10 hover:bg-white/20 text-white font-semibold px-4 py-2.5 rounded-xl border border-white/20 transition flex items-center gap-2 text-sm"><i class="fa-solid fa-box text-blue-200"></i> My Orders</a>
          <button onclick="openWalletModal()" class="bg-white/10 hover:bg-white/20 text-white font-semibold px-4 py-2.5 rounded-xl border border-white/20 transition flex items-center gap-2 text-sm"><i class="fa-solid fa-wallet text-emerald-300"></i> My Wallet</button>
        </div>
      </div>
    </div>

    <div class="mb-8">
      <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Explore Categories</h2>
      <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
        <button data-category="All" class="category-btn active border border-custom-primary bg-custom-light text-custom-primary p-3 rounded-xl text-center hover:shadow-sm transition group"><div class="text-2xl mb-1 text-custom-primary"><i class="fa-solid fa-border-all"></i></div><div class="text-xs font-semibold">All Items</div></button>
        <button data-category="Books" class="category-btn border border-gray-200 bg-white hover:border-custom-primary hover:text-custom-primary p-3 rounded-xl text-center hover:shadow-sm transition group"><div class="text-2xl mb-1 text-gray-600 group-hover:text-custom-primary"><i class="fa-solid fa-book-open"></i></div><div class="text-xs font-semibold text-gray-700 group-hover:text-custom-primary">Books & Study</div></button>
        <button data-category="Electronics" class="category-btn border border-gray-200 bg-white hover:border-custom-primary hover:text-custom-primary p-3 rounded-xl text-center hover:shadow-sm transition group"><div class="text-2xl mb-1 text-gray-600 group-hover:text-custom-primary"><i class="fa-solid fa-laptop"></i></div><div class="text-xs font-semibold text-gray-700 group-hover:text-custom-primary">Electronics</div></button>
        <button data-category="Lifestyle" class="category-btn border border-gray-200 bg-white hover:border-custom-primary hover:text-custom-primary p-3 rounded-xl text-center hover:shadow-sm transition group"><div class="text-2xl mb-1 text-gray-600 group-hover:text-custom-primary"><i class="fa-solid fa-couch"></i></div><div class="text-xs font-semibold text-gray-700 group-hover:text-custom-primary">Lifestyle Goods</div></button>
        <button data-category="Fashion" class="category-btn border border-gray-200 bg-white hover:border-custom-primary hover:text-custom-primary p-3 rounded-xl text-center hover:shadow-sm transition group"><div class="text-2xl mb-1 text-gray-600 group-hover:text-custom-primary"><i class="fa-solid fa-shirt"></i></div><div class="text-xs font-semibold text-gray-700 group-hover:text-custom-primary">Fashion & Accessories</div></button>
        <button data-category="Sports" class="category-btn border border-gray-200 bg-white hover:border-custom-primary hover:text-custom-primary p-3 rounded-xl text-center hover:shadow-sm transition group"><div class="text-2xl mb-1 text-gray-600 group-hover:text-custom-primary"><i class="fa-solid fa-basketball"></i></div><div class="text-xs font-semibold text-gray-700 group-hover:text-custom-primary">Sports & Gears</div></button>
      </div>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6">
      <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
          <span class="text-xs font-bold text-gray-500 uppercase flex items-center gap-1"><i class="fa-solid fa-filter"></i> Filters:</span>
          <select id="conditionFilter" onchange="applyFilters()" class="bg-gray-50 border border-gray-300 text-gray-700 text-xs rounded-lg px-2.5 py-2 focus:outline-none focus:border-blue-500">
            <option value="All">Condition: All</option>
            <option value="Brand New">Brand New</option>
            <option value="Like New">Like New</option>
            <option value="Well Used">Well Used</option>
            <option value="For Parts">For Parts</option>
          </select>
          <div class="flex items-center space-x-1">
            <input type="number" id="minPriceInput" placeholder="Min RM" onchange="applyFilters()" class="w-20 bg-gray-50 border border-gray-300 text-gray-700 text-xs rounded-lg px-2 py-2 focus:outline-none focus:border-blue-500" />
            <span class="text-gray-400 text-xs">-</span>
            <input type="number" id="maxPriceInput" placeholder="Max RM" onchange="applyFilters()" class="w-20 bg-gray-50 border border-gray-300 text-gray-700 text-xs rounded-lg px-2 py-2 focus:outline-none focus:border-blue-500" />
          </div>
          <button onclick="resetFilters()" class="text-xs text-gray-500 hover:text-red-600 underline font-medium">Reset All</button>
        </div>

        <div class="flex items-center space-x-2 self-end lg:self-auto">
          <label class="text-xs font-medium text-gray-600 whitespace-nowrap">Sort By:</label>
          <select id="sortSelect" onchange="applyFilters()" class="bg-gray-50 border border-gray-300 text-gray-700 text-xs rounded-lg px-3 py-2 font-medium focus:outline-none focus:border-blue-500">
            <option value="newest">Date Posted: Newest First</option>
            <option value="price-low">Price: Low to High</option>
            <option value="price-high">Price: High to Low</option>
          </select>
        </div>
      </div>
    </div>

    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2"><span>Active Campus Listings</span><span id="activeCampusIndicator" class="text-xs font-normal text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full border border-slate-200">Showing New Era UC Feed</span></h2>
        <p class="text-xs text-gray-500">Deterministic chronological feed</p>
      </div>
      <span id="resultCountLabel" class="text-xs text-gray-500 font-medium">Showing 12 active listings</span>
    </div>

    <div id="productGrid" class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6 mb-8"></div>

    <div class="bg-white px-4 py-3 rounded-xl border border-gray-200 flex items-center justify-between">
      <div class="text-xs text-gray-600">Page <span id="currentPageNum" class="font-bold text-gray-900">1</span> of <span id="totalPagesNum" class="font-bold text-gray-900">3</span> (<span id="totalItemsCount">36</span> total items)</div>
      <div class="flex items-center space-x-1">
        <button id="prevPageBtn" onclick="changePage(-1)" disabled class="px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-lg text-gray-400 bg-gray-50 cursor-not-allowed transition"><i class="fa-solid fa-chevron-left mr-1"></i> Prev</button>
        <button onclick="goToPage(1)" class="page-num-btn w-8 h-8 flex items-center justify-center text-xs rounded-lg font-bold bg-custom-primary text-white">1</button>
        <button onclick="goToPage(2)" class="page-num-btn w-8 h-8 flex items-center justify-center text-xs rounded-lg font-medium text-gray-700 hover:bg-gray-100">2</button>
        <button onclick="goToPage(3)" class="page-num-btn w-8 h-8 flex items-center justify-center text-xs rounded-lg font-medium text-gray-700 hover:bg-gray-100">3</button>
        <button id="nextPageBtn" onclick="changePage(1)" class="px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-lg text-gray-700 hover:bg-gray-50 transition">Next <i class="fa-solid fa-chevron-right ml-1"></i></button>
      </div>
    </div>
  </main>

  <footer class="bg-slate-900 text-slate-400 text-xs border-t border-slate-800 mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div>
          <div class="flex items-center space-x-2 text-white font-bold text-base mb-2"><i class="fa-solid fa-graduation-cap text-blue-400"></i><span>Campus Exchange</span></div>
          <p class="text-slate-400 leading-relaxed">A verified multi-institutional second-hand trading platform engineered for Malaysian university students.</p>
        </div>
        <div>
          <h4 class="text-white font-bold mb-2">Partner Institutions</h4>
          <ul class="space-y-1">
            <?php foreach ($universities as $university): ?>
              <li>• <?php echo htmlspecialchars($university, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <!-- <div>
          <h4 class="text-white font-bold mb-2">System Architecture</h4>
          <p class="text-slate-400">Built with PHP 8.1 PDO, MySQL 8.0, TLS 1.3 HTTPS, and Escrow Financial Safeguard Ledger.</p>
        </div> -->
        <div>
          <h4 class="text-white font-bold mb-2">Safety & Policy</h4>
          <p class="text-slate-400">Strict university domain validation. Real-time escrow holds funds until physical handover confirmation.</p>
        </div>
      </div>
      <div class="border-t border-slate-800 pt-4 flex flex-col sm:flex-row justify-between items-center text-[11px] text-slate-500">
        <p>&copy; 2026 Campus Second-Hand E-Commerce Platform.</p>
        <p class="mt-2 sm:mt-0">New Era Institute of Vocational and Continuing Education</p>
      </div>
    </div>
  </footer>

  <div id="postModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-100 my-8">
      <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2"><i class="fa-solid fa-plus-circle text-custom-primary"></i> Create New Product Listing</h3>
        <button onclick="document.getElementById('postModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-lg"></i></button>
      </div>
      <form id="postListingForm" class="space-y-4">
        <div class="bg-red-50 border border-red-200 text-red-700 text-xs p-3 rounded-lg hidden" id="postErrorAlert">Please provide a valid title (5-100 characters) and a detailed description (at least 20 characters).</div>
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Product Title (5 - 100 characters)*</label>
          <input id="postTitle" name="title" type="text" class="input-shell" placeholder="e.g. Fundamental Algorithms Textbook 3rd Ed." required />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Category*</label>
            <select id="postCategory" name="category_id" class="input-shell">
              <option value="1">Books</option>
              <option value="2">Electronics</option>
              <option value="3">Lifestyle</option>
              <option value="4">Fashion</option>
              <option value="3">Sports</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Condition*</label>
            <select id="postCondition" name="condition_status" class="input-shell">
              <option value="Brand New">Brand New</option>
              <option value="Like New" selected>Like New</option>
              <option value="Well Used">Well Used</option>
              <option value="For Parts">For Parts</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Price (RM 1.00 - RM 5,000.00)*</label>
          <div class="relative"><span class="absolute left-3 top-2 text-xs font-bold text-gray-500">RM</span><input id="postPrice" name="price" type="number" step="0.01" min="1" max="5000" placeholder="0.00" class="w-full bg-gray-50 border border-gray-300 rounded-lg pl-10 pr-3 py-2 text-xs focus:outline-none focus:border-blue-500" required /></div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Product Description (Min 20 characters)*</label>
          <textarea id="postDescription" name="description" rows="3" placeholder="Provide details..." class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-blue-500" required></textarea>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Listing Photos (Required: 1 to 3 images)*</label>
          <input id="postImage" name="images[]" type="file" accept="image/*" multiple class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-blue-500" />
          <p class="text-[10px] text-gray-400 mt-1">Upload at least 1 and up to 3 photos. JPG, PNG, or WEBP up to 2MB each.</p>
        </div>
        <div class="pt-2 flex justify-end space-x-2">
          <button type="button" onclick="document.getElementById('postModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 text-gray-700 text-xs rounded-lg hover:bg-gray-100">Cancel</button>
          <button type="submit" class="px-5 py-2 bg-custom-primary text-white text-xs font-bold rounded-lg hover:bg-opacity-90">Publish Listing</button>
        </div>
      </form>
    </div>
  </div>

  <div id="walletModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100">
      <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2"><i class="fa-solid fa-wallet text-emerald-600"></i> Virtual Campus Wallet</h3>
        <button onclick="closeWalletModal()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-lg"></i></button>
      </div>
      <div class="bg-gradient-to-r from-emerald-600 to-teal-700 rounded-xl p-4 text-white mb-4 shadow-md">
        <span class="text-xs uppercase tracking-wider text-emerald-100 font-semibold">Available Balance</span>
        <div class="text-3xl font-extrabold mt-1" id="modalWalletBalance">RM <?php echo number_format((float)($user['wallet_balance'] ?? 150), 2); ?></div>
        <div class="text-[10px] text-emerald-100 mt-2 flex items-center gap-1"><i class="fa-solid fa-lock"></i> Escrow Protection Active</div>
      </div>
      <div class="mb-5 bg-gray-50 p-3 rounded-xl border border-gray-200">
        <h4 class="text-xs font-bold text-gray-800 mb-2">Simulate Top-Up Balance (RM 10.00 - RM 500.00)</h4>
        <div id="topUpErrorAlert" class="hidden bg-red-50 border border-red-200 text-red-700 text-[11px] p-2 rounded mb-2">Top-up amount must be between RM 10.00 and RM 500.00.</div>
        <div class="flex gap-2">
          <div class="relative flex-1">
            <span class="absolute left-2.5 top-2 text-xs font-bold text-gray-500">RM</span>
            <input type="number" id="topUpAmount" placeholder="50.00" min="10" max="500" step="10" class="w-full bg-white border border-gray-300 rounded-lg pl-8 pr-2 py-1.5 text-xs focus:outline-none focus:border-emerald-500" />
          </div>
          <button onclick="executeTopUp()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-1.5 rounded-lg text-xs transition">Add Funds</button>
        </div>
      </div>
      <h4 class="text-xs font-bold text-gray-700 mb-2">Immutable Transaction Ledger</h4>
      <div class="space-y-2 max-h-40 overflow-y-auto pr-1 text-xs" id="ledgerLogList">
        <div class="text-[11px] text-gray-400 text-center py-3" id="ledgerEmptyState">No wallet transactions yet.</div>
      </div>
    </div>
  </div>

  <div id="buyModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100">
      <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-900">Confirm Escrow Order</h3>
        <button onclick="closeBuyModal()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-lg"></i></button>
      </div>
      <div id="crossCampusNotice" class="hidden bg-amber-50 border border-amber-200 text-amber-900 text-xs p-3 rounded-xl mb-4"><div class="font-bold mb-1 flex items-center gap-1.5"><i class="fa-solid fa-triangle-exclamation text-amber-600"></i> Cross-Campus Notice</div>Notice: Cross-campus trade detected. Please confirm meetup logistics via private chat.</div>
      <div class="flex gap-3 bg-gray-50 p-3 rounded-xl border border-gray-200 mb-4">
        <img id="buyProductImg" src="" alt="Product" class="w-16 h-16 rounded-lg object-cover border border-gray-200" />
        <div class="flex-1 min-w-0">
          <h4 id="buyProductTitle" class="text-xs font-bold text-gray-800 truncate">Product Title</h4>
          <div id="buyProductSeller" class="text-[11px] text-gray-500">Seller: Student Name</div>
          <div id="buyProductPrice" class="text-sm font-extrabold text-custom-primary mt-1">RM 0.00</div>
        </div>
      </div>
      <div class="space-y-2 text-xs text-gray-600 mb-5">
        <div class="flex justify-between"><span>Your Wallet Balance:</span><span id="buyUserBalance" class="font-bold text-gray-800">RM 150.00</span></div>
        <div class="flex justify-between"><span>Escrow Holding Fee:</span><span class="text-emerald-600 font-bold">RM 0.00 (Free)</span></div>
        <div class="border-t border-gray-200 pt-2 flex justify-between font-bold text-gray-900"><span>Total Deduction:</span><span id="buyTotalDeduction" class="text-custom-primary">RM 0.00</span></div>
      </div>
      <div id="buyErrorAlert" class="hidden bg-red-50 border border-red-200 text-red-700 text-xs p-3 rounded-xl mb-4">Insufficient wallet balance. Please top up your account to proceed with this purchase.</div>
      <div class="flex space-x-2"><button onclick="closeBuyModal()" class="w-1/2 py-2 border border-gray-300 text-gray-700 text-xs rounded-xl font-semibold">Cancel</button><button onclick="confirmEscrowPurchase()" class="w-1/2 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs rounded-xl font-bold transition">Lock Escrow & Order</button></div>
    </div>
  </div>

  <div id="chatModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full h-[520px] flex flex-col shadow-2xl border border-gray-100 overflow-hidden">
      <div class="bg-slate-900 text-white p-3.5 flex items-center justify-between">
        <div class="flex items-center space-x-2.5">
          <button id="chatBackBtn" onclick="showChatConversationList()" class="hidden text-slate-300 hover:text-white text-sm"><i class="fa-solid fa-arrow-left"></i></button>
          <img id="chatSellerAvatar" src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=80" class="w-8 h-8 rounded-full border border-slate-700 object-cover" />
          <div>
            <div id="chatSellerName" class="text-xs font-bold">Seller Name</div>
            <span id="chatUniBadge" class="bg-blue-600/40 text-blue-200 text-[10px] px-1.5 py-0.5 rounded border border-blue-400/30">New Era UC</span>
          </div>
        </div>
        <button onclick="closeChatModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
      </div>
      <div class="hidden">
        <img id="chatProductImg" src="" class="w-8 h-8 rounded object-cover" />
        <span id="chatProductTitle">Product Name</span>
        <span id="chatProductPrice">RM 0.00</span>
      </div>
      <div id="chatMessagesStream" class="flex-1 p-3 overflow-y-auto space-y-3 bg-gray-50 text-xs">
        <div class="text-center text-[10px] text-gray-400 my-1">--- Encrypted Student Chat Started ---</div>
        <div class="flex flex-col items-start"><div class="bg-white border border-gray-200 rounded-2xl rounded-tl-none p-2.5 max-w-[80%] text-gray-800 shadow-sm">Hi! Is this textbook still available for meetup at the Student Cafeteria?</div><span class="text-[9px] text-gray-400 mt-1 pl-1">10:42 AM</span></div>
      </div>
      <div id="chatComposer" class="hidden p-3 bg-white border-t border-gray-200">
        <div id="chatLimitWarning" class="hidden text-[10px] text-red-600 mb-1 font-semibold">Message exceeds the 500-character limit.</div>
        <div class="flex items-center gap-2">
          <input type="text" id="chatInput" oninput="validateChatLength(this)" placeholder="Type message..." class="flex-1 bg-gray-100 border border-gray-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:bg-white focus:border-blue-500" />
          <button id="sendChatBtn" onclick="sendChatMessage()" class="bg-custom-primary text-white p-2 rounded-xl text-xs px-3 font-bold hover:bg-opacity-90"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
        <div class="flex justify-between items-center text-[9px] text-gray-400 mt-1 px-1"><span>XSS Filter Enabled</span><span id="chatCharCounter">0/500</span></div>
      </div>
    </div>
  </div>

  <div id="myListingsModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-100">
      <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2"><i class="fa-solid fa-list text-violet-600"></i> My Listings</h3>
        <button onclick="closeMyListingsModal()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-lg"></i></button>
      </div>
      <div id="myListingsList" class="space-y-3 max-h-80 overflow-y-auto"></div>
    </div>
  </div>

  <div id="ordersModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-100">
      <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2"><i class="fa-solid fa-box text-blue-600"></i> My Purchase Orders</h3>
        <button onclick="document.getElementById('ordersModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-lg"></i></button>
      </div>
      <div id="ordersList" class="space-y-3 max-h-80 overflow-y-auto"></div>
    </div>
  </div>

  <?php if ($showWarningNotice): ?>
    <script>
      window.addEventListener('load', () => {
        fetch('mark_warning_read.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          credentials: 'same-origin',
          body: new URLSearchParams({ warning_id: '<?php echo $latestWarningId; ?>' })
        }).catch(() => {});
      });
    </script>
  <?php endif; ?>
  <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
</body>
</html>
