const allowedDomains = ['student.newera.edu.my', 'student.tarc.edu.my', 'student.utar.edu.my'];

const sessionUser = window.__APP_USER__ || null;

const state = {
    currentUser: {
        id: sessionUser?.id ?? 'STU-2026-001',
        name: sessionUser?.full_name ?? 'Alex Tan',
        studentId: sessionUser?.student_id ?? '24B0037',
        university: sessionUser?.university ?? 'New Era UC',
        email: sessionUser?.email ?? 'alex.tan@student.newera.edu.my',
        walletBalance: Number(sessionUser?.wallet_balance ?? 150),
        unreadMessages: Number(sessionUser?.unread_messages ?? 0),
        watchlistCount: Number(sessionUser?.watchlist_count ?? 0),
        trustScore: Number(sessionUser?.trust_score ?? 5.0),
        completedTrades: Number(sessionUser?.completed_trades ?? 0)
    },
    currentTheme: 'newera',
    currentCategory: 'All',
    currentCampusFilter: 'home',
    currentConditionFilter: 'All',
    minPrice: null,
    maxPrice: null,
    sortBy: 'newest',
    searchQuery: '',
    currentPage: 1,
    pageSize: 12,
    favorites: new Set(),
    favoriteOnly: false,
    activeBuyProduct: null
};

let appProducts = [];

let myOrders = [];

function saveMarketplaceState() {
    const user = getUserFromStorage() || state.currentUser;
    sessionStorage.setItem('marketplaceState', JSON.stringify({
        userId: Number(user?.id || 0),
        currentCategory: state.currentCategory,
        currentCampusFilter: state.currentCampusFilter,
        currentConditionFilter: state.currentConditionFilter,
        minPrice: state.minPrice,
        maxPrice: state.maxPrice,
        sortBy: state.sortBy,
        searchQuery: state.searchQuery,
        currentPage: state.currentPage,
        scrollY: window.scrollY
    }));
}

function restoreMarketplaceState() {
    const rawState = sessionStorage.getItem('marketplaceState');
    if (!rawState) return;

    try {
        const savedState = JSON.parse(rawState);
        const user = getUserFromStorage() || state.currentUser;
        if (!savedState.userId || Number(savedState.userId) !== Number(user?.id || 0)) {
            sessionStorage.removeItem('marketplaceState');
            return;
        }
        state.currentCategory = savedState.currentCategory || 'All';
        state.currentCampusFilter = savedState.currentCampusFilter || 'home';
        state.currentConditionFilter = savedState.currentConditionFilter || 'All';
        state.minPrice = savedState.minPrice ?? null;
        state.maxPrice = savedState.maxPrice ?? null;
        state.sortBy = savedState.sortBy || 'newest';
        state.searchQuery = savedState.searchQuery || '';
        state.currentPage = Number(savedState.currentPage) || 1;

        const campusFilter = document.getElementById('headerCampusFilter');
        const conditionFilter = document.getElementById('conditionFilter');
        const minPriceInput = document.getElementById('minPriceInput');
        const maxPriceInput = document.getElementById('maxPriceInput');
        const sortSelect = document.getElementById('sortSelect');
        const searchInput = document.getElementById('searchInput');
        if (campusFilter) campusFilter.value = state.currentCampusFilter;
        if (conditionFilter) conditionFilter.value = state.currentConditionFilter;
        if (minPriceInput) minPriceInput.value = state.minPrice ?? '';
        if (maxPriceInput) maxPriceInput.value = state.maxPrice ?? '';
        if (sortSelect) sortSelect.value = state.sortBy;
        if (searchInput) searchInput.value = state.searchQuery;

        document.querySelectorAll('.category-btn').forEach(button => {
            const active = button.dataset.category === state.currentCategory;
            button.classList.toggle('border-custom-primary', active);
            button.classList.toggle('bg-custom-light', active);
            button.classList.toggle('text-custom-primary', active);
            button.classList.toggle('border-gray-200', !active);
            button.classList.toggle('bg-white', !active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (savedState.scrollY) window.requestAnimationFrame(() => window.scrollTo(0, savedState.scrollY));
    } catch {
        sessionStorage.removeItem('marketplaceState');
    }
}

function normalizeCampusName(name) {
    const value = String(name || '').trim();
    if (!value) return 'Campus';
    if (/new era/i.test(value)) return 'New Era UC';
    if (/university\s+tunku\s+abdul\s+rahman/i.test(value)) return 'UTAR';
    if (/tunku abdul rahman|tar umt|tarc/i.test(value)) return 'TAR UMT';
    return value;
}

function normalizeCategoryName(name) {
    const value = String(name || '').trim();
    if (!value) return 'General';
    const lower = value.toLowerCase();
    if (lower.includes('book')) return 'Books & Study Materials';
    if (lower.includes('electronic')) return 'Electronics';
    if (lower.includes('lifestyle')) return 'Lifestyle Goods';
    if (lower.includes('fashion')) return 'Fashion & Accessories';
    if (lower.includes('sport')) return 'Sports & Gears';
    return value;
}

function normalizeMoney(value) {
    return Number((Number(value) || 0).toFixed(2));
}

function hasSufficientBalance(balance, amount) {
    return normalizeMoney(balance) >= normalizeMoney(amount);
}

function getUserFromStorage() {
    if (window.__APP_USER__) {
        return {
            id: window.__APP_USER__.id,
            name: window.__APP_USER__.full_name,
            studentId: window.__APP_USER__.student_id,
            university: normalizeCampusName(window.__APP_USER__.university),
            email: window.__APP_USER__.email,
            walletBalance: Number(window.__APP_USER__.wallet_balance ?? 150),
            unreadMessages: Number(window.__APP_USER__.unread_messages ?? 0),
            watchlistCount: Number(window.__APP_USER__.watchlist_count ?? 0),
            trustScore: Number(window.__APP_USER__.trust_score ?? 5.0),
            completedTrades: Number(window.__APP_USER__.completed_trades ?? 0)
        };
    }

    const raw = localStorage.getItem('campusUser');
    if (!raw) return null;
    try {
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') return parsed;
    } catch {
        return null;
    }

    return null;
}

function persistUser(user) {
    localStorage.setItem('campusUser', JSON.stringify(user));
}

function setPageTheme(themeKey) {
    const body = document.body;
    body.classList.remove('theme-newera', 'theme-tarc', 'theme-utar');
    if (themeKey === 'newera') body.classList.add('theme-newera');
    if (themeKey === 'tarc') body.classList.add('theme-tarc');
    if (themeKey === 'utar') body.classList.add('theme-utar');
    state.currentTheme = themeKey;
    const themeSelector = document.getElementById('themeSelector');
    if (themeSelector) themeSelector.value = themeKey;
}

function getThemeKeyFromUniversity(university) {
    const normalized = normalizeCampusName(university || '');
    if (/new era/i.test(normalized)) return 'newera';
    if (/utar/i.test(normalized)) return 'utar';
    if (/tar umt|tarc/i.test(normalized)) return 'tarc';
    return 'newera';
}

function updateLoggedInHeader() {
    const user = getUserFromStorage() || state.currentUser;
    const nameEl = document.getElementById('heroStudentName');
    const heroBadge = document.getElementById('heroUniBadge');
    const navName = document.getElementById('profileNameLabel');
    const wallet = document.getElementById('navWalletBalance');
    const modalBalance = document.getElementById('modalWalletBalance');
    const profileThumb = document.getElementById('profileThumb');
    const unreadBadge = document.getElementById('unreadBadge');
    const favoriteCount = document.getElementById('favoriteCount');
    const trustLabel = document.getElementById('profileTrustLabel');

    if (nameEl) nameEl.textContent = user.name;
    if (heroBadge) heroBadge.textContent = normalizeCampusName(user.university || state.currentUser.university || 'Campus');
    if (navName) navName.textContent = user.name;
    if (wallet) wallet.textContent = `RM ${user.walletBalance.toFixed(2)}`;
    if (modalBalance) modalBalance.textContent = `RM ${user.walletBalance.toFixed(2)}`;
    if (profileThumb) profileThumb.src = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80';

    if (unreadBadge) {
        const unread = Number(user.unreadMessages ?? 0);
        unreadBadge.textContent = unread;
        unreadBadge.classList.toggle('hidden', unread <= 0);
    }

    if (favoriteCount) {
        const count = Number(user.watchlistCount ?? 0);
        favoriteCount.textContent = count;
        favoriteCount.classList.toggle('hidden', count <= 0);
    }

    if (trustLabel) {
        const score = Number(user.trustScore ?? 5.0);
        const trades = Number(user.completedTrades ?? 0);
        trustLabel.innerHTML = `<i class="fa-solid fa-star text-amber-400"></i> ${score.toFixed(1)} (${trades} trades)`;
    }
}

function updateUnreadMessageCount(count) {
    const unread = Math.max(0, Number(count) || 0);
    state.currentUser.unreadMessages = unread;
    if (window.__APP_USER__) window.__APP_USER__.unread_messages = unread;

    const storedUser = localStorage.getItem('campusUser');
    if (storedUser) {
        try {
            const parsedUser = JSON.parse(storedUser);
            parsedUser.unreadMessages = unread;
            parsedUser.unread_messages = unread;
            persistUser(parsedUser);
        } catch {
        }
    }

    const unreadBadge = document.getElementById('unreadBadge');
    if (unreadBadge) {
        unreadBadge.textContent = unread;
        unreadBadge.classList.toggle('hidden', unread <= 0);
    }
}

function initAuth() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    if (loginForm) {
        loginForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const email = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('loginPassword').value;
            const alertBox = document.getElementById('loginAlert');

            const domain = email.split('@')[1] || '';
            const validDomain = allowedDomains.includes(domain);

            if (!email || !password || !validDomain) {
                alertBox.textContent = 'Invalid email address or password.';
                alertBox.classList.add('show', 'alert-error');
                return;
            }

            const user = {
                ...state.currentUser,
                email,
                name: email.split('@')[0].replace(/[._-]/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
                university: domain.includes('newera') ? 'New Era UC' : domain.includes('tarc') ? 'TAR UMT' : 'UTAR',
                walletBalance: 150
            };
            persistUser(user);
            window.location.href = 'home.html';
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const fullName = document.getElementById('regFullName').value.trim();
            const studentId = document.getElementById('regStudentId').value.trim();
            const email = document.getElementById('regEmail').value.trim();
            const phone = document.getElementById('regPhone').value.trim();
            const password = document.getElementById('regPassword').value;
            const confirm = document.getElementById('regConfirmPassword').value;
            const alertBox = document.getElementById('registerAlert');

            const domain = email.split('@')[1] || '';
            if (!allowedDomains.includes(domain)) {
                alertBox.textContent = 'Registration is currently restricted to official student emails of partnered universities.';
                alertBox.classList.add('show', 'alert-error');
                return;
            }
            if (!/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,32}$/.test(password)) {
                alertBox.textContent = 'Password must include uppercase, lowercase, number, special character, and be 8-32 characters.';
                alertBox.classList.add('show', 'alert-error');
                return;
            }
            if (password !== confirm) {
                alertBox.textContent = 'Passwords do not match.';
                alertBox.classList.add('show', 'alert-error');
                return;
            }
            if (!/^\d{10,11}$/.test(phone)) {
                alertBox.textContent = 'Phone number must contain 10 to 11 digits only.';
                alertBox.classList.add('show', 'alert-error');
                return;
            }
            if (studentId.length < 3 || !email || !fullName) {
                alertBox.textContent = 'Please complete all required fields.';
                alertBox.classList.add('show', 'alert-error');
                return;
            }

            alertBox.textContent = 'Registration successful. Redirecting to login...';
            alertBox.classList.remove('alert-error');
            alertBox.classList.add('show', 'alert-success');
            setTimeout(() => window.location.href = 'index.html', 1000);
        });
    }
}

async function loadProducts() {
    const grid = document.getElementById('productGrid');
    if (!grid) return;

    grid.innerHTML = `
      <div class="col-span-full py-12 text-center text-gray-500 bg-white rounded-2xl border border-dashed border-gray-300">
        <i class="fa-solid fa-spinner fa-spin text-3xl mb-3 text-custom-primary"></i>
        <p class="text-sm font-semibold">Loading live listings from database...</p>
      </div>
    `;

    try {
        const response = await fetch('get_products.php', { credentials: 'same-origin' });
        const data = await response.json();
        if (!Array.isArray(data)) {
            appProducts = [];
            throw new Error(data?.error || 'Unable to load product listings.');
        }

        const user = getUserFromStorage() || state.currentUser;
        const currentUserId = Number(user?.id ?? state.currentUser.id ?? 0);

        appProducts = data.map((item) => ({
            id: Number(item.product_id),
            title: item.title,
            price: Number(item.price || 0),
            category: normalizeCategoryName(item.category_name || 'General'),
            condition: item.item_condition || 'Like New',
            campus: normalizeCampusName(item.university_name || 'Campus'),
            seller: item.seller_name || 'Student Seller',
            sellerId: Number(item.seller_id || 0),
            rating: 4.9,
            trades: 0,
            img: item.primary_image || 'assets/images/default.jpg',
            created_at: item.created_at || new Date().toISOString(),
            isFavorite: false
        }));

        if (currentUserId > 0) {
            try {
                const favoriteResponse = await fetch('watchlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'list' })
                });
                const favoriteData = await favoriteResponse.json();
                const favoriteIds = Array.isArray(favoriteData?.items) ? favoriteData.items.map(id => Number(id)) : [];
                appProducts = appProducts.map(item => ({ ...item, isFavorite: favoriteIds.includes(item.id) }));
                state.favorites = new Set(favoriteIds);
            } catch (favoriteError) {
                console.warn('Unable to load watchlist from database:', favoriteError);
                state.favorites = new Set();
            }
        }
    } catch (error) {
        console.error('Failed to load live products:', error);
        appProducts = [];
        if (grid) {
            grid.innerHTML = `
              <div class="col-span-full py-12 text-center text-red-500 bg-white rounded-2xl border border-dashed border-red-200">
                <i class="fa-solid fa-circle-exclamation text-4xl mb-3"></i>
                <p class="text-sm font-semibold">Unable to load listings from database.</p>
              </div>
            `;
        }
        return;
    }

    renderHomePage();
}

function renderHomePage() {
    const grid = document.getElementById('productGrid');
    const campusIndicator = document.getElementById('activeCampusIndicator');
    if (!grid) return;

    const currentUserId = Number(getUserFromStorage()?.id ?? window.__APP_USER__?.id ?? state.currentUser.id ?? 0);

    const currentUserCampus = normalizeCampusName(getUserFromStorage()?.university || state.currentUser.university || 'Campus');
    const isWatchlistView = state.favoriteOnly;

    let filtered = appProducts.filter(item => {
        if (Number(item.sellerId) === currentUserId) return false;

        if (!isWatchlistView) {
            if (state.currentCampusFilter === 'home') {
                if (normalizeCampusName(item.campus) !== currentUserCampus) return false;
            } else if (state.currentCampusFilter !== 'all') {
                if (normalizeCampusName(item.campus) !== normalizeCampusName(state.currentCampusFilter)) return false;
            }
        }

        if (state.currentCategory !== 'All' && normalizeCategoryName(item.category) !== normalizeCategoryName(state.currentCategory)) return false;
        if (state.currentConditionFilter !== 'All' && item.condition !== state.currentConditionFilter) return false;
        if (state.minPrice !== null && item.price < state.minPrice) return false;
        if (state.maxPrice !== null && item.price > state.maxPrice) return false;
        if (state.favoriteOnly && !state.favorites.has(item.id)) return false;
        if (state.searchQuery) {
            const q = state.searchQuery.toLowerCase();
            if (!item.title.toLowerCase().includes(q) && !item.category.toLowerCase().includes(q)) return false;
        }
        return true;
    });

    if (state.sortBy === 'newest') filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    if (state.sortBy === 'price-low') filtered.sort((a, b) => a.price - b.price);
    if (state.sortBy === 'price-high') filtered.sort((a, b) => b.price - a.price);

    if (isWatchlistView) {
        campusIndicator.textContent = 'Showing All Watchlist Items';
    } else if (state.currentCampusFilter === 'home') {
        campusIndicator.textContent = `Showing ${(getUserFromStorage()?.university || state.currentUser.university)} Home Feed`;
    } else if (state.currentCampusFilter === 'all') {
        campusIndicator.textContent = 'Showing All Partner Universities Feed';
    } else {
        campusIndicator.textContent = `Showing ${state.currentCampusFilter} Feed`;
    }

    const totalItems = filtered.length;
    const totalPages = Math.ceil(totalItems / state.pageSize) || 1;
    state.currentPage = Math.min(state.currentPage, totalPages);
    const startIndex = (state.currentPage - 1) * state.pageSize;
    const paginatedItems = filtered.slice(startIndex, startIndex + state.pageSize);

    document.getElementById('currentPageNum').textContent = state.currentPage;
    document.getElementById('totalPagesNum').textContent = totalPages;
    document.getElementById('totalItemsCount').textContent = totalItems;
    document.getElementById('resultCountLabel').textContent = `Showing ${paginatedItems.length} active listings`;
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');
    const isFirstPage = state.currentPage === 1;
    const isLastPage = state.currentPage === totalPages;
    prevPageBtn.disabled = isFirstPage;
    nextPageBtn.disabled = isLastPage;
    prevPageBtn.classList.toggle('text-gray-400', isFirstPage);
    prevPageBtn.classList.toggle('bg-gray-50', isFirstPage);
    prevPageBtn.classList.toggle('cursor-not-allowed', isFirstPage);
    prevPageBtn.classList.toggle('text-gray-700', !isFirstPage);
    prevPageBtn.classList.toggle('hover:bg-gray-50', !isFirstPage);
    nextPageBtn.classList.toggle('text-gray-400', isLastPage);
    nextPageBtn.classList.toggle('bg-gray-50', isLastPage);
    nextPageBtn.classList.toggle('cursor-not-allowed', isLastPage);
    nextPageBtn.classList.toggle('text-gray-700', !isLastPage);
    nextPageBtn.classList.toggle('hover:bg-gray-50', !isLastPage);
    document.querySelectorAll('.page-num-btn').forEach((button, index) => {
        const isActive = index + 1 === state.currentPage;
        button.classList.toggle('bg-custom-primary', isActive);
        button.classList.toggle('text-white', isActive);
        button.classList.toggle('font-bold', isActive);
        button.classList.toggle('text-gray-700', !isActive);
        button.classList.toggle('font-medium', !isActive);
        button.classList.toggle('hover:bg-gray-100', !isActive);
    });

    if (paginatedItems.length === 0) {
        grid.innerHTML = `
      <div class="col-span-full py-12 text-center text-gray-500 bg-white rounded-2xl border border-dashed border-gray-300">
        <i class="fa-solid fa-box-open text-4xl mb-3 text-gray-300"></i>
        <p class="text-sm font-semibold">No active product listings found matching your criteria.</p>
        <button onclick="resetFilters()" class="mt-3 text-xs bg-custom-primary text-white px-3 py-1.5 rounded-lg">Reset Filters</button>
      </div>
    `;
        return;
    }

    grid.innerHTML = paginatedItems.map(item => {
        const isCrossCampus = item.campus !== (getUserFromStorage()?.university || state.currentUser.university);
        const isFav = state.favorites.has(item.id);
        return `
    <div onclick="saveMarketplaceState(); window.location.href='product.php?id=${item.id}'" class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md transition flex flex-col group cursor-pointer">
        <div class="relative aspect-square bg-gray-100 overflow-hidden">
          <img src="${item.img}" alt="${item.title}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
          <span class="absolute top-2.5 left-2.5 bg-slate-900/80 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">${item.condition}</span>
          <button onclick="event.stopPropagation(); toggleFavorite(${item.id})" class="absolute top-2.5 right-2.5 w-7 h-7 rounded-full bg-white/80 flex items-center justify-center text-gray-600 hover:text-red-500 transition shadow-sm">
            <i class="${isFav ? 'fa-solid fa-heart text-red-500' : 'fa-regular fa-heart'} text-xs"></i>
          </button>
          <div class="absolute bottom-2.5 left-2.5">
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md ${isCrossCampus ? 'bg-purple-100 text-purple-800 border border-purple-200' : 'bg-blue-100 text-blue-800 border border-blue-200'} shadow-sm">
              <i class="fa-solid fa-building-columns mr-1"></i>${item.campus}
            </span>
          </div>
        </div>
        <div class="p-3.5 flex-1 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between text-[10px] text-gray-500 mb-1">
              <span>${item.category}</span>
            </div>
            <h3 class="text-xs font-bold text-gray-900 line-clamp-2 leading-snug mb-2 group-hover:text-custom-primary transition">${item.title}</h3>
          </div>
          <div>
            <div class="text-base font-extrabold text-custom-primary mb-3">RM ${Number(item.price).toFixed(2)}</div>
            <div class="grid grid-cols-2 gap-1.5">
              <button onclick="event.stopPropagation(); openChatModal(${item.id})" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-[11px] font-bold py-1.5 rounded-lg text-center transition flex items-center justify-center gap-1"><i class="fa-regular fa-comment"></i> Chat</button>
              <button onclick="event.stopPropagation(); openBuyModal(${item.id})" class="bg-custom-primary hover:bg-opacity-90 text-white text-[11px] font-bold py-1.5 rounded-lg text-center transition">Buy Now</button>
            </div>
          </div>
        </div>
      </div>
    `;
    }).join('');
}

function selectCategory(catName) {
    state.currentCategory = catName;
    state.currentPage = 1;
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('border-custom-primary', 'bg-custom-light', 'text-custom-primary');
        btn.classList.add('border-gray-200', 'bg-white');
    });
    const current = event.currentTarget;
    if (current) {
        current.classList.add('border-custom-primary', 'bg-custom-light', 'text-custom-primary');
    }
    renderHomePage();
}

function applyFilters() {
    state.currentCampusFilter = document.getElementById('headerCampusFilter').value;
    state.currentConditionFilter = document.getElementById('conditionFilter').value;
    const minP = parseFloat(document.getElementById('minPriceInput').value);
    const maxP = parseFloat(document.getElementById('maxPriceInput').value);
    state.minPrice = isNaN(minP) ? null : minP;
    state.maxPrice = isNaN(maxP) ? null : maxP;
    state.sortBy = document.getElementById('sortSelect').value;
    state.currentPage = 1;
    renderHomePage();
}

function resetFilters() {
    state.currentCategory = 'All';
    state.currentConditionFilter = 'All';
    state.minPrice = null;
    state.maxPrice = null;
    state.searchQuery = '';
    state.sortBy = 'newest';
    document.getElementById('conditionFilter').value = 'All';
    document.getElementById('minPriceInput').value = '';
    document.getElementById('maxPriceInput').value = '';
    document.getElementById('searchInput').value = '';
    document.getElementById('sortSelect').value = 'newest';
    document.getElementById('searchAlert').classList.add('hidden');

    document.querySelectorAll('.category-btn').forEach(item => {
        item.classList.remove(
            'border-custom-primary',
            'bg-custom-light',
            'text-custom-primary',
            'ring-2',
            'ring-custom-primary/30',
            'shadow-md',
            'scale-[1.02]',
            '-translate-y-0.5'
        );
        item.classList.add('border-gray-200', 'bg-white', 'text-gray-700');
        item.setAttribute('aria-pressed', 'false');
    });

    const allBtn = document.querySelector('.category-btn[data-category="All"]');
    if (allBtn) {
        allBtn.classList.remove('border-gray-200', 'bg-white', 'text-gray-700');
        allBtn.classList.add(
            'border-custom-primary',
            'bg-custom-light',
            'text-custom-primary',
            'ring-2',
            'ring-custom-primary/30',
            'shadow-md',
            'scale-[1.02]',
            '-translate-y-0.5'
        );
        allBtn.setAttribute('aria-pressed', 'true');
    }

    renderHomePage();
}

function executeSearch() {
    const query = document.getElementById('searchInput').value.trim();
    const alertBox = document.getElementById('searchAlert');
    if (query.length > 0 && query.length < 2) {
        alertBox.classList.remove('hidden');
        document.getElementById('searchAlertText').textContent = 'Search query must contain at least 2 characters.';
        return;
    }
    alertBox.classList.add('hidden');
    state.searchQuery = query;
    state.currentPage = 1;
    renderHomePage();
}

async function toggleFavorite(itemId) {
    const isCurrentlyFavorite = state.favorites.has(itemId);
    const nextAction = isCurrentlyFavorite ? 'remove' : 'add';

    try {
        const response = await fetch('watchlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ product_id: Number(itemId), action: nextAction })
        });
        const data = await response.json();

        if (!data.success) {
            alert(data.message || 'Unable to update watchlist.');
            return;
        }

        if (isCurrentlyFavorite) state.favorites.delete(itemId);
        else state.favorites.add(itemId);

        const countBadge = document.getElementById('favoriteCount');
        if (countBadge) {
            const count = state.favorites.size;
            countBadge.textContent = count;
            if (count > 0) countBadge.classList.remove('hidden');
            else countBadge.classList.add('hidden');
        }

        appProducts = appProducts.map(item => ({
            ...item,
            isFavorite: state.favorites.has(Number(item.id))
        }));

        renderHomePage();
    } catch (error) {
        console.error('Failed to toggle favorite:', error);
        alert('Unable to update watchlist right now.');
    }
}

function filterFavorites() {
    state.favoriteOnly = !state.favoriteOnly;
    state.currentPage = 1;
    const watchlistButton = document.querySelector('button[title="Watchlist"]');
    if (watchlistButton) {
        watchlistButton.classList.toggle('text-red-500', state.favoriteOnly);
    }
    renderHomePage();
}

window.filterFavorites = filterFavorites;
window.toggleFavorite = toggleFavorite;
window.executeSearch = executeSearch;
window.openWalletModal = openWalletModal;
window.closeWalletModal = closeWalletModal;
window.executeTopUp = executeTopUp;
window.openBuyModal = openBuyModal;
window.closeBuyModal = closeBuyModal;
window.confirmEscrowPurchase = confirmEscrowPurchase;
window.openMyListingsModal = openMyListingsModal;
window.closeMyListingsModal = closeMyListingsModal;
window.loadMyListings = loadMyListings;
window.openChatModal = openChatModal;
window.closeChatModal = closeChatModal;
window.confirmItemReceived = confirmItemReceived;
window.changePage = changePage;
window.goToPage = goToPage;
window.resetFilters = resetFilters;
window.applyFilters = applyFilters;

async function loadWalletLedger() {
    const ledger = document.getElementById('ledgerLogList');
    const emptyState = document.getElementById('ledgerEmptyState');
    if (!ledger) return;

    try {
        const response = await fetch('wallet_history.php', { credentials: 'same-origin' });
        const data = await response.json();
        const transactions = Array.isArray(data?.transactions) ? data.transactions : [];

        if (!transactions.length) {
            ledger.innerHTML = '<div class="text-[11px] text-gray-400 text-center py-3">No wallet transactions yet.</div>';
            return;
        }

        ledger.innerHTML = transactions.map(txn => {
            const amount = Number(txn.amount || 0);
            const isCredit = amount >= 0;
            const label = txn.transaction_type === 'TOPUP' ? 'Top Up' : txn.transaction_type === 'ESCROW_HOLD' ? 'Escrow Hold' : txn.transaction_type === 'ESCROW_RELEASE' ? 'Escrow Release' : txn.transaction_type;
            const ref = txn.transaction_ref || 'N/A';
            const date = txn.created_at ? new Date(txn.created_at).toLocaleString('en-GB', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }) : 'N/A';

            return `
                <div class="flex justify-between items-center p-2 ${isCredit ? 'bg-emerald-50 border border-emerald-100' : 'bg-gray-50 border border-gray-100'} rounded">
                    <div>
                        <div class="font-semibold ${isCredit ? 'text-emerald-900' : 'text-gray-800'}">${label}</div>
                        <div class="text-[10px] ${isCredit ? 'text-emerald-600' : 'text-gray-400'}">${ref} • ${date}</div>
                    </div>
                    <span class="font-bold ${isCredit ? 'text-emerald-600' : 'text-red-600'}">${isCredit ? '+' : '-'}RM ${Math.abs(amount).toFixed(2)}</span>
                </div>
            `;
        }).join('');

        if (emptyState) emptyState.remove();
    } catch (error) {
        console.error('Failed to load wallet ledger:', error);
        ledger.innerHTML = '<div class="text-[11px] text-gray-400 text-center py-3">No wallet transactions yet.</div>';
    }
}

async function openWalletModal() {
    const user = getUserFromStorage() || state.currentUser;
    const balanceEl = document.getElementById('modalWalletBalance');
    if (balanceEl) balanceEl.textContent = `RM ${Number(user.walletBalance ?? 0).toFixed(2)}`;
    document.getElementById('walletModal').classList.remove('hidden');
    await loadWalletLedger();
}

function closeWalletModal() {
    document.getElementById('walletModal').classList.add('hidden');
}

async function executeTopUp() {
    const amount = parseFloat(document.getElementById('topUpAmount').value);
    const alertBox = document.getElementById('topUpErrorAlert');
    if (isNaN(amount) || amount < 10 || amount > 500) {
        alertBox.classList.remove('hidden');
        return;
    }
    alertBox.classList.add('hidden');

    try {
        const response = await fetch('top_up.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ amount })
        });
        const data = await response.json();

        if (!data.success) {
            alertBox.textContent = data.message || 'Unable to top up wallet.';
            alertBox.classList.remove('hidden');
            return;
        }

        const user = getUserFromStorage() || state.currentUser;
        user.walletBalance = Number(data.new_balance ?? user.walletBalance + amount);
        persistUser(user);
        document.getElementById('navWalletBalance').textContent = `RM ${user.walletBalance.toFixed(2)}`;
        document.getElementById('modalWalletBalance').textContent = `RM ${user.walletBalance.toFixed(2)}`;
        document.getElementById('topUpAmount').value = '';

        await loadWalletLedger();
        alert(`Success! RM ${amount.toFixed(2)} added to your wallet.`);
    } catch (error) {
        console.error('Top-up failed:', error);
        alertBox.textContent = 'Unable to process top-up right now.';
        alertBox.classList.remove('hidden');
    }
}

function openBuyModal(productId) {
    const product = appProducts.find(p => Number(p.id) === Number(productId));
    if (!product) return;
    state.activeBuyProduct = product;
    const user = getUserFromStorage() || state.currentUser;
    document.getElementById('buyProductTitle').textContent = product.title;
    document.getElementById('buyProductSeller').textContent = 'Seller: ' + product.seller + ' (' + product.campus + ')';
    document.getElementById('buyProductPrice').textContent = 'RM ' + Number(product.price).toFixed(2);
    document.getElementById('buyProductImg').src = product.img;
    document.getElementById('buyUserBalance').textContent = 'RM ' + user.walletBalance.toFixed(2);
    document.getElementById('buyTotalDeduction').textContent = 'RM ' + Number(product.price).toFixed(2);

    const isCrossCampus = product.campus !== (user.university || state.currentUser.university);
    document.getElementById('crossCampusNotice').classList.toggle('hidden', !isCrossCampus);
    document.getElementById('buyErrorAlert').classList.add('hidden');
    document.getElementById('buyModal').classList.remove('hidden');
}

function closeBuyModal() {
    document.getElementById('buyModal').classList.add('hidden');
}

async function confirmEscrowPurchase() {
    const product = state.activeBuyProduct;
    const user = getUserFromStorage() || state.currentUser;
    if (!product) return;
    if (!hasSufficientBalance(user.walletBalance, product.price)) {
        document.getElementById('buyErrorAlert').classList.remove('hidden');
        return;
    }

    try {
        const response = await fetch('purchase_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                product_id: Number(product.id),
                seller_id: Number(product.sellerId || 0),
                amount: Number(product.price)
            })
        });
        const data = await response.json();
        if (!data.success) {
            document.getElementById('buyErrorAlert').textContent = data.message || 'Unable to place order.';
            document.getElementById('buyErrorAlert').classList.remove('hidden');
            return;
        }

        user.walletBalance = Number(data.new_balance ?? user.walletBalance - product.price);
        if (window.__APP_USER__) window.__APP_USER__.wallet_balance = user.walletBalance;
        persistUser(user);
        document.getElementById('navWalletBalance').textContent = `RM ${user.walletBalance.toFixed(2)}`;
        closeBuyModal();
        await loadOrdersList();
        alert('Order ' + (data.order_ref || 'Placed') + ' successfully created! Payment locked in escrow.');
    } catch (error) {
        console.error('Failed to place order:', error);
        document.getElementById('buyErrorAlert').textContent = 'Unable to place order right now.';
        document.getElementById('buyErrorAlert').classList.remove('hidden');
    }
}

async function loadMyListings() {
    const container = document.getElementById('myListingsList');
    if (!container) return;

    try {
        const response = await fetch('my_listings_api.php', { credentials: 'same-origin' });
        const data = await response.json();
        const listings = Array.isArray(data) ? data : [];

        if (!listings.length) {
            container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">You have not posted any listings yet.</p>';
            return;
        }

        container.innerHTML = listings.map(item => {
            const status = (item.status || 'Available').toString();
            const statusClasses = {
                Available: 'bg-emerald-100 text-emerald-700',
                Sold: 'bg-red-100 text-red-700',
                Reserved: 'bg-amber-100 text-amber-700',
                'Awaiting Handover': 'bg-blue-100 text-blue-700',
                'Completed': 'bg-slate-200 text-slate-700'
            };
            const badgeClass = statusClasses[status] || 'bg-gray-100 text-gray-700';

            return `
            <div class="bg-gray-50 border border-gray-200 p-3 rounded-xl text-xs space-y-2">
                <div class="flex gap-3">
                    <img src="${item.primary_image || 'assets/images/default.jpg'}" alt="${item.title}" class="w-16 h-16 object-cover rounded-lg border border-gray-200" />
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-gray-900 truncate">${item.title}</div>
                        <div class="text-[11px] text-gray-500">${item.category_name || 'General'} • ${item.item_condition || 'Like New'}</div>
                        <div class="text-sm font-extrabold text-custom-primary mt-1">RM ${Number(item.price || 0).toFixed(2)}</div>
                    </div>
                </div>
                <div class="flex justify-between items-center text-[10px] text-gray-500">
                    <span>${item.university_name || 'Campus'}</span>
                    <span class="px-2 py-0.5 rounded-full font-bold ${badgeClass}">${status}</span>
                </div>
            </div>
        `;
        }).join('');
    } catch (error) {
        console.error('Failed to load my listings:', error);
        container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">Unable to load your listings right now.</p>';
    }
}

function openMyListingsModal() {
    const modal = document.getElementById('myListingsModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    loadMyListings();
}

function closeMyListingsModal() {
    const modal = document.getElementById('myListingsModal');
    if (modal) modal.classList.add('hidden');
}

async function loadOrdersList() {
    const container = document.getElementById('ordersList');
    if (!container) return;

    try {
        const response = await fetch('orders_api.php', { credentials: 'same-origin' });
        const data = await response.json();
        myOrders = Array.isArray(data?.orders) ? data.orders.map(order => ({
            id: order.order_ref || `ORD-${order.order_id}`,
            orderId: Number(order.order_id || 0),
            itemTitle: order.item_title || 'Product',
            price: Number(order.price || 0),
            seller: order.seller_name || 'Seller',
            campus: order.campus || 'Campus',
            status: order.status || 'Awaiting Handover',
            productId: Number(order.product_id || 0),
            image: order.product_image || 'assets/images/default.jpg',
            isCrossCampus: normalizeCampusName(order.campus) !== normalizeCampusName((getUserFromStorage() || state.currentUser).university)
        })) : [];

        if (!myOrders.length) {
            container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No active orders found.</p>';
            return;
        }

        container.innerHTML = myOrders.map(ord => `
        <div class="bg-gray-50 border border-gray-200 p-3 rounded-xl text-xs space-y-2">
          <div class="flex justify-between items-center">
            <span class="font-bold text-gray-900">${ord.id}</span>
            <span class="px-2 py-0.5 text-[10px] rounded-full font-bold ${ord.status === 'Completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">${ord.status}</span>
          </div>
          <div class="text-gray-700 font-semibold">${ord.itemTitle}</div>
          <div class="flex justify-between text-gray-500 text-[11px]">
            <span>Seller: ${ord.seller} (${ord.campus})</span>
            <span class="font-bold text-custom-primary">RM ${ord.price.toFixed(2)}</span>
          </div>
          ${ord.isCrossCampus ? '<div class="text-[10px] text-purple-700 font-medium">Notice: Cross-campus trade detected.</div>' : ''}
          ${ord.status === 'Awaiting Handover' ? '<div class="pt-2 flex gap-2"><button onclick="confirmItemReceived(\'' + ord.id + '\')" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-1.5 rounded-lg text-[11px] transition">Confirm Item Received (Release Escrow)</button></div>' : ''}
        </div>
      `).join('');
    } catch (error) {
        console.error('Failed to load orders:', error);
        container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No active orders found.</p>';
    }
}

async function confirmItemReceived(orderId) {
    const ord = myOrders.find(o => o.id === orderId);
    if (!ord) return;

    try {
        const response = await fetch('complete_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ order_id: Number(ord.orderId || 0) })
        });
        const data = await response.json();
        if (!data.success) {
            alert(data.message || 'Unable to complete order.');
            return;
        }

        ord.status = 'Completed';
        await loadOrdersList();
        alert('Transaction completed! Funds released from escrow to seller ' + ord.seller + '. Please rate your seller.');
    } catch (error) {
        console.error('Failed to complete order:', error);
        alert('Unable to complete order right now.');
    }
}

let activeChatProduct = null;

async function loadChatMessages(productId, sellerName, sellerCampus, productTitle, productPrice, productImage, partnerId) {
    const stream = document.getElementById('chatMessagesStream');
    if (!stream) return;

    try {
        const readResponse = await fetch(`chat_messages.php?action=mark_read&product_id=${encodeURIComponent(productId)}&partner_id=${encodeURIComponent(partnerId)}`, {
            credentials: 'same-origin'
        });
        const readData = await readResponse.json();
        if (readData.success) updateUnreadMessageCount(readData.unread_count);

        const currentUserId = Number(getUserFromStorage()?.id ?? state.currentUser.id ?? 0);
        const url = productId ? `chat_messages.php?product_id=${encodeURIComponent(productId)}&partner_id=${encodeURIComponent(partnerId)}` : 'chat_messages.php';
        const response = await fetch(url, { credentials: 'same-origin' });
        const data = await response.json();
        const messages = Array.isArray(data?.messages) ? data.messages : [];

        if (!messages.length) {
            stream.innerHTML = '<div class="text-center text-[10px] text-gray-400 my-1">--- Encrypted Student Chat Started ---</div>';
            return;
        }

        stream.innerHTML = messages.map(msg => {
            const isMine = Number(msg.sender_id) === Number(currentUserId);
            const text = (msg.message_text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const time = msg.created_at ? new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
            const partnerLabel = productId ? '' : `<span class="text-[9px] text-gray-500 mb-1 ${isMine ? 'pr-1' : 'pl-1'}">${isMine ? 'You' : (msg.sender_name || msg.receiver_name || 'Seller')}</span>`;

            return `
                <div class="flex flex-col ${isMine ? 'items-end' : 'items-start'}">
                    ${partnerLabel}
                    <div class="${isMine ? 'bg-custom-primary text-white rounded-2xl rounded-tr-none' : 'bg-white border border-gray-200 rounded-2xl rounded-tl-none text-gray-800'} p-2.5 max-w-[80%] shadow-sm">${text}</div>
                    <span class="text-[9px] text-gray-400 mt-1 ${isMine ? 'pr-1' : 'pl-1'}">${time}</span>
                </div>
            `;
        }).join('');
        stream.scrollTop = stream.scrollHeight;
    } catch (error) {
        console.error('Failed to load chat messages:', error);
        stream.innerHTML = '<div class="text-center text-[10px] text-gray-400 my-1">--- Encrypted Student Chat Started ---</div>';
    }
}

function showChatConversationList() {
    const stream = document.getElementById('chatMessagesStream');
    const backBtn = document.getElementById('chatBackBtn');
    const composer = document.getElementById('chatComposer');
    if (!stream) return;

    if (backBtn) backBtn.classList.add('hidden');
    if (composer) composer.classList.add('hidden');
    document.getElementById('chatProductTitle').textContent = 'My Chat';
    document.getElementById('chatProductPrice').textContent = 'Select a chat';
    document.getElementById('chatProductImg').src = 'assets/images/default.jpg';
    document.getElementById('chatSellerAvatar').src = 'assets/image/chat_icon.jpg';
    document.getElementById('chatSellerName').textContent = 'Conversation list';
    document.getElementById('chatUniBadge').textContent = 'All chats';
    document.getElementById('chatInput').value = '';
    document.getElementById('chatCharCounter').textContent = '0/500';
    renderConversationList();
}

async function renderConversationList() {
    const stream = document.getElementById('chatMessagesStream');
    if (!stream) return;

    try {
        stream.innerHTML = '<div class="text-center text-[10px] text-gray-400 my-1">Loading conversations...</div>';
        const response = await fetch('chat_messages.php?action=conversations', { credentials: 'same-origin' });
        const data = await response.json();
        const conversations = Array.isArray(data?.conversations) ? data.conversations : [];
        if (typeof data?.unread_count !== 'undefined') updateUnreadMessageCount(data.unread_count);

        if (!conversations.length) {
            stream.innerHTML = '<div class="text-center text-[10px] text-gray-400 my-1">No conversations yet.</div>';
            return;
        }

        const productStatusClass = status => {
            if (status === 'Available') return 'bg-emerald-100 text-emerald-700';
            if (status === 'Sold') return 'bg-red-100 text-red-700';
            return 'bg-gray-100 text-gray-600';
        };

        stream.innerHTML = conversations.map(item => `
            <button class="chat-conversation-row w-full text-left bg-white border border-gray-200 rounded-xl p-3 hover:border-custom-primary hover:bg-blue-50 transition" data-product-id="${item.product_id}" data-seller-id="${item.seller_id || ''}" data-partner-id="${item.partner_id || ''}" data-partner-name="${item.partner_name || ''}" data-partner-campus="${item.campus || ''}">
                <div class="flex items-center gap-3">
                    <img src="${item.product_image || 'assets/images/default.jpg'}" alt="${item.product_title || 'Product'}" class="w-12 h-12 rounded-lg object-cover border border-gray-200" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <div class="font-bold text-gray-900 truncate">${item.partner_name || 'Conversation partner'}</div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[9px] text-gray-400">${item.campus || 'Campus'}</span>
                                ${Number(item.unread_count || 0) > 0 ? `<span class="bg-red-500 text-white text-[9px] font-bold min-w-4 h-4 px-1 rounded-full inline-flex items-center justify-center">${item.unread_count}</span>` : ''}
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5 min-w-0">
                            <div class="text-[10px] text-gray-600 truncate">${item.product_title || 'Product'}</div>
                            <span class="shrink-0 px-1.5 py-0.5 rounded-full text-[9px] font-bold ${productStatusClass(item.product_status)}">${item.product_status || 'Unknown'}</span>
                        </div>
                        <div class="text-[9px] text-gray-400 mt-1 truncate">${item.last_message || 'Start conversation'}</div>
                    </div>
                </div>
            </button>
        `).join('');

        stream.querySelectorAll('.chat-conversation-row').forEach(button => {
            button.addEventListener('click', async () => {
                const productId = Number(button.dataset.productId || 0);
                const sellerId = Number(button.dataset.sellerId || 0);
                const partnerId = Number(button.dataset.partnerId || 0);
                const partnerName = button.dataset.partnerName || 'Conversation partner';
                const partnerCampus = button.dataset.partnerCampus || 'Campus';
                const conversationImage = button.querySelector('img')?.src || 'assets/images/default.jpg';
                const product = appProducts.find(p => Number(p.id) === productId) || {
                    id: productId,
                    title: button.querySelector('div:nth-child(2) .text-gray-600')?.textContent || 'Product',
                    price: 0,
                    seller: partnerName,
                    sellerId,
                    campus: partnerCampus,
                    img: conversationImage
                };

                product.img = conversationImage;
                product.sellerId = partnerId || sellerId;
                product.seller = partnerName;
                product.campus = partnerCampus;
                activeChatProduct = product;
                const backBtn = document.getElementById('chatBackBtn');
                const composer = document.getElementById('chatComposer');
                if (backBtn) backBtn.classList.remove('hidden');
                if (composer) composer.classList.remove('hidden');
                document.getElementById('chatProductTitle').textContent = product.title;
                document.getElementById('chatProductPrice').textContent = product.price ? 'RM ' + Number(product.price).toFixed(2) : 'Product chat';
                document.getElementById('chatProductImg').src = product.img;
                document.getElementById('chatSellerAvatar').src = product.img;
                document.getElementById('chatSellerName').textContent = product.seller;
                document.getElementById('chatUniBadge').textContent = product.campus;
                document.getElementById('chatModal').classList.remove('hidden');
                await loadChatMessages(productId, product.seller, product.campus, product.title, product.price, product.img, product.sellerId);
            });
        });
    } catch (error) {
        console.error('Failed to load conversation list:', error);
        stream.innerHTML = '<div class="text-center text-[10px] text-gray-400 my-1">Unable to load conversations.</div>';
    }
}

async function openChatModal(productId = null) {
    const composer = document.getElementById('chatComposer');
    if (productId) {
        const product = appProducts.find(p => Number(p.id) === Number(productId)) || appProducts[0];
        if (!product) return;
        activeChatProduct = product;
        if (composer) composer.classList.remove('hidden');
        document.getElementById('chatProductTitle').textContent = product.title;
        document.getElementById('chatProductPrice').textContent = 'RM ' + Number(product.price).toFixed(2);
        document.getElementById('chatProductImg').src = product.img;
        document.getElementById('chatSellerAvatar').src = product.img;
        document.getElementById('chatSellerName').textContent = product.seller;
        document.getElementById('chatUniBadge').textContent = product.campus;
        document.getElementById('chatModal').classList.remove('hidden');
        document.getElementById('unreadBadge').classList.add('hidden');
        document.getElementById('chatInput').value = '';
        document.getElementById('chatCharCounter').textContent = '0/500';
        await loadChatMessages(product.id, product.seller, product.campus, product.title, product.price, product.img, product.sellerId);
        return;
    }

    activeChatProduct = null;
    const backBtn = document.getElementById('chatBackBtn');
    if (backBtn) backBtn.classList.add('hidden');
    if (composer) composer.classList.add('hidden');
    document.getElementById('chatProductTitle').textContent = 'My Chat';
    document.getElementById('chatProductPrice').textContent = 'Select a chat';
    document.getElementById('chatProductImg').src = 'assets/images/default.jpg';
    document.getElementById('chatSellerAvatar').src = 'assets/image/chat_icon.jpg';
    document.getElementById('chatSellerName').textContent = 'Conversation list';
    document.getElementById('chatUniBadge').textContent = 'All chats';
    document.getElementById('chatModal').classList.remove('hidden');
    document.getElementById('unreadBadge').classList.add('hidden');
    document.getElementById('chatInput').value = '';
    document.getElementById('chatCharCounter').textContent = '0/500';
    await renderConversationList();
}

function closeChatModal() {
    const backBtn = document.getElementById('chatBackBtn');
    const composer = document.getElementById('chatComposer');
    if (backBtn) backBtn.classList.add('hidden');
    if (composer) composer.classList.add('hidden');
    document.getElementById('chatModal').classList.add('hidden');
}

function validateChatLength(input) {
    const len = input.value.length;
    const counter = document.getElementById('chatCharCounter');
    const warning = document.getElementById('chatLimitWarning');
    const sendBtn = document.getElementById('sendChatBtn');
    counter.textContent = len + '/500';
    if (len > 500) {
        warning.classList.remove('hidden');
        sendBtn.disabled = true;
        sendBtn.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        warning.classList.add('hidden');
        sendBtn.disabled = false;
        sendBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

async function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const stream = document.getElementById('chatMessagesStream');
    const msg = input.value.trim();
    if (!msg || msg.length > 500 || !activeChatProduct) return;

    try {
        const currentUser = getUserFromStorage() || state.currentUser;
        const response = await fetch('chat_messages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                product_id: Number(activeChatProduct.id),
                receiver_id: Number(activeChatProduct.sellerId || 1),
                message: msg
            })
        });
        const data = await response.json();
        if (!data.success) {
            alert(data.message || 'Unable to send message.');
            return;
        }

        const safeMsg = msg.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        stream.insertAdjacentHTML('beforeend', `
            <div class="flex flex-col items-end">
                <div class="bg-custom-primary text-white rounded-2xl rounded-tr-none p-2.5 max-w-[80%] shadow-sm">${safeMsg}</div>
                <span class="text-[9px] text-gray-400 mt-1 pr-1">${timeStr}</span>
            </div>
        `);
        input.value = '';
        document.getElementById('chatCharCounter').textContent = '0/500';
        stream.scrollTop = stream.scrollHeight;
    } catch (error) {
        console.error('Failed to send chat message:', error);
        alert('Unable to send message right now.');
    }
}

function changePage(delta) {
    state.currentPage += delta;
    renderHomePage();
    window.scrollTo({ top: 400, behavior: 'smooth' });
}

function goToPage(pageNum) {
    state.currentPage = pageNum;
    renderHomePage();
    window.scrollTo({ top: 400, behavior: 'smooth' });
}

function initHomeUI() {
    const user = getUserFromStorage() || state.currentUser;
    const themeSelector = document.getElementById('themeSelector');
    if (themeSelector) themeSelector.addEventListener('change', (e) => setPageTheme(e.target.value));

    const heroName = document.getElementById('heroStudentName');
    if (heroName) heroName.textContent = user.name;

    const wallet = document.getElementById('navWalletBalance');
    if (wallet) wallet.textContent = `RM ${user.walletBalance.toFixed(2)}`;

    restoreMarketplaceState();

    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const value = btn.dataset.category || 'All';
            state.currentCategory = value;
            state.currentPage = 1;
            document.querySelectorAll('.category-btn').forEach(item => {
                item.classList.remove(
                    'border-custom-primary',
                    'bg-custom-light',
                    'text-custom-primary',
                    'ring-2',
                    'ring-custom-primary/30',
                    'shadow-md',
                    'scale-[1.02]',
                    '-translate-y-0.5'
                );
                item.classList.add('border-gray-200', 'bg-white', 'text-gray-700');
                item.setAttribute('aria-pressed', 'false');
            });

            btn.classList.remove('border-gray-200', 'bg-white', 'text-gray-700');
            btn.classList.add(
                'border-custom-primary',
                'bg-custom-light',
                'text-custom-primary',
                'ring-2',
                'ring-custom-primary/30',
                'shadow-md',
                'scale-[1.02]',
                '-translate-y-0.5'
            );
            btn.setAttribute('aria-pressed', 'true');
            renderHomePage();
        });
    });

    document.getElementById('searchInput')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') executeSearch();
    });

    document.getElementById('chatInput')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChatMessage();
        }
    });

    renderHomePage();
    loadMyListings();
    loadOrdersList();
    loadProducts();
}

function initPostListingForm() {
    const form = document.getElementById('postListingForm');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const title = document.getElementById('postTitle')?.value.trim() || '';
        const categoryId = Number(document.getElementById('postCategory')?.value || 1);
        const condition = document.getElementById('postCondition')?.value || 'Like New';
        const price = Number(document.getElementById('postPrice')?.value || 0);
        const description = document.getElementById('postDescription')?.value.trim() || '';
        const imageFiles = Array.from(document.getElementById('postImage')?.files || []);
        const alertBox = document.getElementById('postErrorAlert');

        if (!title || title.length < 5 || title.length > 100 || !description || description.length < 20 || price <= 0 || price > 5000) {
            alertBox?.classList.remove('hidden');
            alertBox.textContent = 'Please provide a valid title, description, and price before publishing.';
            return;
        }

        if (imageFiles.length < 1 || imageFiles.length > 3) {
            alertBox?.classList.remove('hidden');
            alertBox.textContent = 'Please upload at least 1 photo and no more than 3 photos.';
            return;
        }

        const invalidFile = imageFiles.find(file => !file.type.startsWith('image/'));
        if (invalidFile) {
            alertBox?.classList.remove('hidden');
            alertBox.textContent = 'Only JPG, PNG, and WEBP images are allowed.';
            return;
        }

        const oversizedFile = imageFiles.find(file => file.size > 2 * 1024 * 1024);
        if (oversizedFile) {
            alertBox?.classList.remove('hidden');
            alertBox.textContent = 'Each image must be smaller than 2MB.';
            return;
        }

        alertBox?.classList.add('hidden');

        try {
            const formData = new FormData();
            formData.append('title', title);
            formData.append('category_id', String(categoryId));
            formData.append('condition_status', condition);
            formData.append('price', String(price));
            formData.append('description', description);
            imageFiles.forEach((file, index) => {
                formData.append(`images[${index}]`, file);
            });

            const response = await fetch('post_product.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            const data = await response.json();
            if (!data.success) {
                alertBox?.classList.remove('hidden');
                alertBox.textContent = data.message || 'Unable to publish listing.';
                return;
            }

            form.reset();
            document.getElementById('postModal')?.classList.add('hidden');
            await loadProducts();
            alert('Listing published successfully!');
        } catch (error) {
            console.error('Failed to post listing:', error);
            alertBox?.classList.remove('hidden');
            alertBox.textContent = 'Unable to publish listing right now.';
        }
    });
}

function renderAdminPage() {
    const summary = document.getElementById('adminSummary');
    if (!summary) return;
    const adminData = window.__ADMIN_DATA__ || { summary: {}, moderation: [], integrity: {} };
    const summaryData = adminData.summary || {};
    const cards = [
        { label: 'Total Users', value: Number(summaryData.total_users || 0).toLocaleString(), icon: 'fa-users', tone: 'blue' },
        { label: 'Active Listings', value: Number(summaryData.active_listings || 0).toLocaleString(), icon: 'fa-box-open', tone: 'green' },
        { label: 'Completed Trades', value: Number(summaryData.completed_trades || 0).toLocaleString(), icon: 'fa-check-circle', tone: 'amber' },
        { label: 'Volume RM', value: `RM ${Number(summaryData.trade_volume || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`, icon: 'fa-wallet', tone: 'purple' },
    ];

    summary.innerHTML = cards.map(card => `
    <div class="stat-card">
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-bold uppercase text-slate-500">${card.label}</span>
        <div class="w-9 h-9 rounded-xl flex items-center justify-center ${card.tone === 'blue' ? 'bg-blue-100 text-blue-600' : card.tone === 'green' ? 'bg-emerald-100 text-emerald-600' : card.tone === 'amber' ? 'bg-amber-100 text-amber-600' : 'bg-violet-100 text-violet-600'}">
          <i class="fa-solid ${card.icon}"></i>
        </div>
      </div>
      <div class="text-2xl font-extrabold text-slate-900">${card.value}</div>
    </div>
  `).join('');

    const moderationRows = document.getElementById('moderationRows');
    if (moderationRows) {
        const moderation = Array.isArray(adminData.moderation) ? adminData.moderation : [];
        moderationRows.innerHTML = moderation.length ? moderation.map(row => {
            const suspended = row.status === 'suspended';
            const action = suspended ? 'Suspend' : 'Warning';
            const warningCount = Number(row.warning_count || 0);
            const status = suspended ? 'Suspended' : `${warningCount} warning${warningCount === 1 ? '' : 's'}`;
            return `
      <tr class="border-t border-gray-200 text-xs text-slate-700">
                <td class="px-3 py-3 font-semibold">${row.full_name}</td>
                <td class="px-3 py-3">${row.student_id}</td>
        <td class="px-3 py-3">${row.email}</td>
        <td class="px-3 py-3">${row.university}</td>
                <td class="px-3 py-3"><span class="px-2 py-1 rounded-full ${suspended ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}">${action}</span></td>
                <td class="px-3 py-3"><span class="px-2 py-1 rounded-full ${suspended ? 'bg-red-100 text-red-700' : 'bg-slate-200 text-slate-700'}">${status}</span></td>
      </tr>
        `;
        }).join('') : `
            <tr class="border-t border-gray-200 text-xs text-slate-500">
                <td class="px-3 py-6 text-center" colspan="6">No students currently require moderation.</td>
            </tr>
        `;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const page = document.body.dataset.page || 'login';
    if (page === 'home') {
        const user = getUserFromStorage() || state.currentUser;
        setPageTheme(getThemeKeyFromUniversity(user.university || state.currentUser.university));
        initHomeUI();
        updateLoggedInHeader();
        initPostListingForm();
    }
    if (page === 'admin') {
        renderAdminPage();
    }
    initAuth();
});
