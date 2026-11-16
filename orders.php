<?php
require_once __DIR__ . '/includes/helpers.php';
requireLogin();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="assets/image/website_icon.png" />
    <title>My Orders | Campus Exchange</title>
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
                <a href="profile.php" class="text-xs font-semibold text-gray-600 hover:text-custom-primary transition"><i class="fa-solid fa-user mr-1"></i> Profile</a>
                <a href="logout.php" class="text-xs font-semibold text-slate-600 hover:text-red-600 transition">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-end justify-between gap-4 mb-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-custom-primary mb-1">Purchases</p>
                <h1 class="text-2xl font-extrabold text-gray-900">My Orders</h1>
                <p class="text-sm text-gray-500 mt-1">Track your purchases and escrow handovers.</p>
            </div>
            <a href="home.php" class="text-xs font-semibold text-gray-600 hover:text-custom-primary transition"><i class="fa-solid fa-arrow-left mr-1"></i> Back to marketplace</a>
        </div>

        <div id="ordersPageList" class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-8 text-center text-sm text-gray-400">Loading orders...</div>
        </div>
    </main>

    <script>
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
        const statusClass = status => status === 'Completed' ? 'bg-emerald-100 text-emerald-800' : status === 'Sold' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800';
        let orders = [];

        function renderOrders() {
            const container = document.getElementById('ordersPageList');
            if (!orders.length) {
                container.innerHTML = '<div class="bg-white rounded-2xl border border-gray-200 p-10 text-center"><i class="fa-solid fa-box-open text-3xl text-gray-300 mb-3"></i><p class="text-sm text-gray-500">You have no purchase orders yet.</p><a href="home.php" class="inline-block mt-4 text-xs font-bold text-custom-primary hover:underline">Browse listings</a></div>';
                return;
            }

            container.innerHTML = orders.map(order => `
                <article class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 sm:p-5">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <img src="${escapeHtml(order.product_image || 'assets/images/default.jpg')}" alt="${escapeHtml(order.item_title)}" class="w-full sm:w-24 h-24 rounded-xl object-cover border border-gray-200" />
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div><p class="text-[10px] uppercase tracking-wider font-bold text-gray-400">${escapeHtml(order.order_ref || 'Order')}</p><h2 class="text-base font-bold text-gray-900 mt-1">${escapeHtml(order.item_title)}</h2></div>
                                <span class="px-2.5 py-1 text-[10px] rounded-full font-bold ${statusClass(order.status)}">${escapeHtml(order.status)}</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-4 text-xs text-gray-500">
                                <span><i class="fa-solid fa-user mr-1 text-gray-400"></i>${escapeHtml(order.seller_name || 'Seller')}</span>
                                <span><i class="fa-solid fa-location-dot mr-1 text-gray-400"></i>${escapeHtml(order.campus || 'Campus')}</span>
                                <span class="font-extrabold text-custom-primary">RM ${Number(order.price || 0).toFixed(2)}</span>
                            </div>
                            ${order.handover_location ? `<p class="text-[11px] text-gray-400 mt-3"><i class="fa-solid fa-handshake mr-1"></i> Handover: ${escapeHtml(order.handover_location)}</p>` : ''}
                                                        ${order.status === 'Awaiting Handover' ? `<div class="mt-4 flex flex-wrap gap-2"><button onclick="confirmReceived(${Number(order.order_id || 0)})" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-lg text-xs transition"><i class="fa-solid fa-check mr-1"></i> Confirm Item Received</button><button onclick="cancelOrder(${Number(order.order_id || 0)})" class="bg-red-50 hover:bg-red-100 border border-red-200 text-red-700 font-bold px-4 py-2 rounded-lg text-xs transition"><i class="fa-solid fa-ban mr-1"></i> Cancel Order</button></div>` : ''}
                                                        ${order.status === 'Completed' && !order.review_id ? `
                                                            <form onsubmit="submitReview(event, ${Number(order.order_id || 0)})" class="mt-4 border-t border-gray-100 pt-4">
                                                                <p class="text-xs font-bold text-gray-700 mb-2">Review seller</p>
                                                                <div class="flex items-center gap-2 mb-2">
                                                                    <label for="rating-${Number(order.order_id || 0)}" class="text-[11px] text-gray-500">Rating</label>
                                                                    <select id="rating-${Number(order.order_id || 0)}" required class="bg-gray-50 border border-gray-300 rounded-lg px-2 py-1.5 text-xs">
                                                                        <option value="">Choose</option><option value="5">5 - Excellent</option><option value="4">4 - Good</option><option value="3">3 - Average</option><option value="2">2 - Poor</option><option value="1">1 - Very poor</option>
                                                                    </select>
                                                                </div>
                                                                <textarea id="comment-${Number(order.order_id || 0)}" maxlength="1000" rows="2" placeholder="Share your experience (optional)" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:bg-white focus:border-blue-500"></textarea>
                                                                <button type="submit" class="mt-2 bg-custom-primary hover:bg-opacity-90 text-white font-bold px-4 py-2 rounded-lg text-xs transition"><i class="fa-solid fa-star mr-1"></i> Submit Review</button>
                                                            </form>` : ''}
                                                        ${order.review_id ? `<div class="mt-4 border-t border-gray-100 pt-3"><p class="text-xs font-bold text-gray-700">Your review: <span class="text-amber-500">${'★'.repeat(Number(order.review_rating || 0))}${'☆'.repeat(5 - Number(order.review_rating || 0))}</span></p>${order.review_comment ? `<p class="text-xs text-gray-500 mt-1">${escapeHtml(order.review_comment)}</p>` : ''}</div>` : ''}
                        </div>
                    </div>
                </article>
            `).join('');
        }

        async function loadOrders() {
            try {
                const response = await fetch('orders_api.php', { credentials: 'same-origin' });
                const data = await response.json();
                orders = Array.isArray(data.orders) ? data.orders : [];
                renderOrders();
            } catch (error) {
                document.getElementById('ordersPageList').innerHTML = '<div class="bg-white rounded-2xl border border-red-200 p-8 text-center text-sm text-red-600">Unable to load your orders right now.</div>';
            }
        }

        async function confirmReceived(orderId) {
            try {
                const response = await fetch('complete_order.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ order_id: orderId }) });
                const data = await response.json();
                if (!data.success) { alert(data.message || 'Unable to complete order.'); return; }
                await loadOrders();
            } catch (error) {
                alert('Unable to complete order right now.');
            }
        }

        async function submitReview(event, orderId) {
            event.preventDefault();
            const rating = document.getElementById(`rating-${orderId}`).value;
            const comment = document.getElementById(`comment-${orderId}`).value;
            try {
                const response = await fetch('submit_review.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ order_id: orderId, rating: Number(rating), comment }) });
                const data = await response.json();
                if (!data.success) { alert(data.message || 'Unable to submit review.'); return; }
                await loadOrders();
            } catch (error) {
                alert('Unable to submit review right now.');
            }
        }

                async function cancelOrder(orderId) {
                    if (!confirm('Cancel this order and return the escrow amount to your wallet?')) return;
                    try {
                        const response = await fetch('cancel_order.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ order_id: orderId }) });
                        const data = await response.json();
                        if (!data.success) { alert(data.message || 'Unable to cancel order.'); return; }
                        await loadOrders();
                        alert('Order cancelled. The escrow amount has been returned to your wallet.');
                    } catch (error) {
                        alert('Unable to cancel order right now.');
                    }
                }

        loadOrders();
    </script>
</body>
</html>
