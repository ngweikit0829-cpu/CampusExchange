<?php
require_once __DIR__ . '/includes/helpers.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$productId = (int) ($_GET['product_id'] ?? 0);
$product = null;

if ($productId > 0) {
    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('
        SELECT p.product_id, p.title, p.seller_id, seller.full_name AS seller_name,
               seller.university_id, uni.university_name AS seller_university
        FROM products p
        INNER JOIN users seller ON seller.user_id = p.seller_id
        INNER JOIN universities uni ON uni.university_id = seller.university_id
        WHERE p.product_id = :product_id
        LIMIT 1
    ');
    $stmt->execute(['product_id' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function chatEscape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$themeColor = (string) ($user['theme_color'] ?? '#1e40af');
$themeColor = preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor) ? strtolower($themeColor) : '#1e40af';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="assets/image/website_icon.png" />
  <title>Chat Seller | Campus Exchange</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen" style="--primary: <?php echo chatEscape($themeColor); ?>">
  <header class="sticky top-0 z-40 bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
      <a href="home.php" class="flex items-center gap-3"><div class="w-10 h-10 rounded-lg bg-custom-primary text-white flex items-center justify-center font-bold text-xl"><i class="fa-solid fa-graduation-cap"></i></div><span class="text-lg font-bold text-gray-900">Campus<span class="text-custom-primary">Exchange</span></span></a>
      <a href="profile.php" class="text-xs font-semibold text-gray-600 hover:text-custom-primary">Profile</a>
    </div>
  </header>

  <main class="max-w-3xl mx-auto px-4 py-8">
    <a href="product.php?id=<?php echo $productId; ?>" class="inline-flex items-center text-xs font-semibold text-gray-600 hover:text-custom-primary mb-5"><i class="fa-solid fa-arrow-left mr-2"></i> Back to product</a>
    <?php if (!$product || (int) $product['seller_id'] === $userId): ?>
      <section class="bg-white rounded-2xl border border-gray-200 p-10 text-center"><p class="text-sm text-gray-500">This seller conversation is unavailable.</p></section>
    <?php else: ?>
      <section class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="bg-slate-900 text-white p-5"><p class="text-[10px] uppercase tracking-wider text-slate-400">Chat seller</p><h1 class="text-lg font-bold mt-1"><?php echo chatEscape($product['seller_name']); ?></h1><p class="text-xs text-slate-300 mt-1"><?php echo chatEscape($product['seller_university']); ?> · <?php echo chatEscape($product['title']); ?></p></div>
        <div id="messageStream" class="h-[420px] overflow-y-auto p-4 space-y-3 bg-gray-50"><p class="text-center text-xs text-gray-400">Loading conversation...</p></div>
        <form id="chatForm" class="p-4 border-t border-gray-200 flex gap-2"><input id="messageInput" maxlength="500" required class="flex-1 bg-gray-100 border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:bg-white focus:border-custom-primary" placeholder="Type message..." /><button class="bg-custom-primary text-white px-4 rounded-xl font-bold"><i class="fa-solid fa-paper-plane"></i></button></form>
      </section>
    <?php endif; ?>
  </main>

  <?php if ($product && (int) $product['seller_id'] !== $userId): ?>
  <script>
    const productId = <?php echo $productId; ?>;
    const partnerId = <?php echo (int) $product['seller_id']; ?>;
    const currentUserId = <?php echo $userId; ?>;
    const stream = document.getElementById('messageStream');
    const input = document.getElementById('messageInput');

    function escapeHtml(value) {
      return String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
    }

    function renderMessages(messages) {
      stream.innerHTML = messages.length ? messages.map(message => {
        const mine = Number(message.sender_id) === currentUserId;
        return `<div class="flex flex-col ${mine ? 'items-end' : 'items-start'}"><div class="max-w-[80%] p-3 rounded-2xl ${mine ? 'bg-custom-primary text-white rounded-tr-none' : 'bg-white border border-gray-200 rounded-tl-none'}">${escapeHtml(message.message_text)}</div><span class="text-[10px] text-gray-400 mt-1">${message.created_at ? new Date(message.created_at).toLocaleString() : ''}</span></div>`;
      }).join('') : '<p class="text-center text-xs text-gray-400">No messages yet. Start the conversation.</p>';
      stream.scrollTop = stream.scrollHeight;
    }

    async function loadMessages() {
      const readResponse = await fetch(`chat_messages.php?action=mark_read&product_id=${productId}&partner_id=${partnerId}`, { credentials: 'same-origin' });
      await readResponse.json();
      const response = await fetch(`chat_messages.php?product_id=${productId}&partner_id=${partnerId}`, { credentials: 'same-origin' });
      const data = await response.json();
      renderMessages(Array.isArray(data.messages) ? data.messages : []);
    }

    document.getElementById('chatForm').addEventListener('submit', async event => {
      event.preventDefault();
      const message = input.value.trim();
      if (!message) return;
      const response = await fetch('chat_messages.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ product_id: productId, receiver_id: partnerId, message }) });
      const data = await response.json();
      if (!data.success) { alert(data.message || 'Unable to send message.'); return; }
      input.value = '';
      await loadMessages();
    });

    input.addEventListener('keydown', event => {
      if (event.key === 'Enter') { event.preventDefault(); document.getElementById('chatForm').requestSubmit(); }
    });
    loadMessages();
  </script>
  <?php endif; ?>
</body>
</html>
