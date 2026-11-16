<?php
require_once __DIR__ . '/includes/helpers.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$removedLogStmt = $pdo->prepare('
  SELECT MAX(al.log_id)
  FROM admin_logs al
  INNER JOIN products p ON p.product_id = al.target_product_id
  WHERE al.action = "delete_listing" AND p.seller_id = :user_id
');
$removedLogStmt->execute(['user_id' => $userId]);
$latestRemovedLogId = (int) ($removedLogStmt->fetchColumn() ?? 0);
if ($latestRemovedLogId > 0) {
  $markRemovedReadStmt = $pdo->prepare('
    INSERT INTO seller_listing_notification_reads (user_id, last_seen_log_id)
    VALUES (:user_id, :last_seen_log_id)
    ON DUPLICATE KEY UPDATE last_seen_log_id = GREATEST(last_seen_log_id, VALUES(last_seen_log_id))
  ');
  $markRemovedReadStmt->execute([
    'user_id' => $userId,
    'last_seen_log_id' => $latestRemovedLogId,
  ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="assets/image/website_icon.png" />
  <title>My Listings | Campus Exchange</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">
  <header class="sticky top-0 z-40 bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <a href="home.php" class="flex items-center gap-3"><div class="w-10 h-10 rounded-lg bg-custom-primary text-white flex items-center justify-center font-bold text-xl shadow-md"><i class="fa-solid fa-graduation-cap"></i></div><span class="text-lg font-bold text-gray-900">Campus<span class="text-custom-primary">Exchange</span></span></a>
      <div class="flex items-center gap-3"><a href="profile.php" class="text-xs font-semibold text-gray-600 hover:text-custom-primary transition"><i class="fa-solid fa-user mr-1"></i> Profile</a><a href="logout.php" class="text-xs font-semibold text-slate-600 hover:text-red-600 transition">Logout</a></div>
    </div>
  </header>

  <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-end justify-between gap-4 mb-6"><div><p class="text-xs font-bold uppercase tracking-wider text-custom-primary mb-1">Seller dashboard</p><h1 class="text-2xl font-extrabold text-gray-900">My Listings</h1><p class="text-sm text-gray-500 mt-1">Manage the products you have posted.</p></div><a href="home.php" class="text-xs font-semibold text-gray-600 hover:text-custom-primary transition"><i class="fa-solid fa-arrow-left mr-1"></i> Back to marketplace</a></div>
    <div id="listingsPageList" class="grid grid-cols-1 md:grid-cols-2 gap-4"><div class="md:col-span-2 bg-white rounded-2xl border border-gray-200 p-8 text-center text-sm text-gray-400">Loading listings...</div></div>
  </main>

  <script>
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
    const statusClass = status => status === 'Available' ? 'bg-emerald-100 text-emerald-800' : status === 'Sold' ? 'bg-red-100 text-red-800' : status === 'Awaiting Handover' ? 'bg-blue-100 text-blue-800' : status === 'Reserved' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700';

    async function deleteListing(productId) {
      if (!window.confirm('Delete this listing? It will no longer be visible in the marketplace.')) return;

      try {
        const response = await fetch('my_listings_api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          credentials: 'same-origin',
          body: new URLSearchParams({ action: 'delete', product_id: String(productId) })
        });
        const data = await response.json();
        if (!data.success) {
          window.alert(data.message || 'Unable to delete this listing.');
          return;
        }
        await loadListings();
      } catch (error) {
        window.alert('Unable to delete this listing right now.');
      }
    }

    async function loadListings() {
      const container = document.getElementById('listingsPageList');
      try {
        const response = await fetch('my_listings_api.php', { credentials: 'same-origin' });
        const data = await response.json();
        const listings = Array.isArray(data) ? data : [];
        if (!listings.length) { container.innerHTML = '<div class="md:col-span-2 bg-white rounded-2xl border border-gray-200 p-10 text-center"><i class="fa-solid fa-list text-3xl text-gray-300 mb-3"></i><p class="text-sm text-gray-500">You have not posted any listings yet.</p><a href="home.php" class="inline-block mt-4 text-xs font-bold text-custom-primary hover:underline">Post a listing</a></div>'; return; }
        container.innerHTML = listings.map(item => `
          <article class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
            <div class="flex gap-4"><img src="${escapeHtml(item.primary_image || 'assets/images/default.jpg')}" alt="${escapeHtml(item.title)}" class="w-24 h-24 rounded-xl object-cover border border-gray-200" /><div class="flex-1 min-w-0"><div class="flex items-start justify-between gap-2"><h2 class="text-sm font-bold text-gray-900">${escapeHtml(item.title)}</h2><span class="shrink-0 px-2 py-1 rounded-full text-[10px] font-bold ${statusClass(item.status)}">${escapeHtml(item.status || 'Available')}</span></div><p class="text-[11px] text-gray-500 mt-1">${escapeHtml(item.category_name || 'General')} · ${escapeHtml(item.item_condition || 'Like New')}</p><p class="text-base font-extrabold text-custom-primary mt-2">RM ${Number(item.price || 0).toFixed(2)}</p></div></div>
            <div class="flex justify-between items-center border-t border-gray-100 mt-4 pt-3 text-[11px] text-gray-500"><span><i class="fa-solid fa-location-dot mr-1"></i>${escapeHtml(item.university_name || 'Campus')}</span><span>${item.created_at ? new Date(item.created_at).toLocaleDateString('en-GB') : ''}</span></div>
            ${item.buyer_name ? `<div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900"><div class="font-bold"><i class="fa-solid fa-handshake mr-1"></i>Order details</div><div class="mt-1 grid grid-cols-1 gap-1 sm:grid-cols-3"><span><i class="fa-solid fa-user mr-1 text-blue-600"></i><strong>Buyer:</strong> ${escapeHtml(item.buyer_name)}</span><span><i class="fa-solid fa-graduation-cap mr-1 text-blue-600"></i><strong>School:</strong> ${escapeHtml(item.buyer_university || 'Unknown')}</span><span><i class="fa-solid fa-location-dot mr-1 text-blue-600"></i><strong>Handover:</strong> ${escapeHtml(item.handover_location || 'Not specified')}</span></div></div>` : ''}
            ${item.status === 'Deleted' && item.removal_reason ? `<div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800"><div class="font-bold"><i class="fa-solid fa-circle-exclamation mr-1"></i>Removed by administrator</div><div class="mt-1"><span class="font-semibold">Reason:</span> ${escapeHtml(item.removal_reason)}</div>${item.removed_at ? `<div class="mt-1 text-[10px] text-red-700">Removed on ${escapeHtml(item.removed_at)}</div>` : ''}</div>` : item.status === 'Available' ? `<div class="mt-3 flex justify-end"><button type="button" onclick="deleteListing(${Number(item.product_id)})" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 text-red-600 text-xs font-bold hover:bg-red-50 transition"><i class="fa-solid fa-trash"></i> Delete</button></div>` : ''}
          </article>`).join('');
      } catch (error) { container.innerHTML = '<div class="md:col-span-2 bg-white rounded-2xl border border-red-200 p-8 text-center text-sm text-red-600">Unable to load your listings right now.</div>'; }
    }
    loadListings();
  </script>
</body>
</html>
