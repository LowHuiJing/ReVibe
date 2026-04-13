document.addEventListener('DOMContentLoaded', function() {

    // Apply saved theme on page load
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark');
    }

    // Dot click listeners
    document.querySelectorAll('.hero__dot').forEach(function(dot) {
        dot.addEventListener('click', function() {
            goToSlide(parseInt(dot.dataset.index));
        });
    });

    // Hero slideshow auto-rotate
    if (document.querySelectorAll('.hero__slide').length > 0) {
        setInterval(function() {
            goToSlide((currentSlide + 1) % document.querySelectorAll('.hero__slide').length);
        }, 4000);
    }

    // Intercept all Add to Cart forms
    document.querySelectorAll('form').forEach(function(form) {
        if (!form.querySelector('[name="action"][value="add"]')) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);

            fetch('/revibe/cart.php', {
                method: 'POST',
                body: formData
            }).then(function() {
                showToast('Added to cart');
                updateCartBadge();
            });
        });
    });

    // Auto check wishlist on product detail page
    const wishlistBtn = document.getElementById('wishlist-btn');
    if (wishlistBtn) {
        const productId = wishlistBtn.getAttribute('data-product-id');
        if (productId) checkWishlist(productId);
    }

});

// Toggle dark mode
function toggleDark() {
    document.body.classList.toggle('dark');
    const isDark = document.body.classList.contains('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
}

// Size selection for products page
function selectSize(productId, size, el) {
    el.closest('form').querySelectorAll('.size-badge').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('size-input-' + productId).value = size;
}

// Size selection for product detail page
function selectSize_detail(el) {
    document.querySelectorAll('.detail-size-badge').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selected-size').value = el.dataset.size;
}

// Slideshow navigation
let currentSlide = 0;

function goToSlide(n) {
    const slides = document.querySelectorAll('.hero__slide');
    const dots = document.querySelectorAll('.hero__dot');
    if (slides.length === 0) return;

    slides[currentSlide].classList.remove('active');
    dots[currentSlide].classList.remove('active');
    currentSlide = n;
    slides[currentSlide].classList.add('active');
    dots[currentSlide].classList.add('active');
}

// Show toast notification
function showToast(message) {
    const existing = document.getElementById('toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.id = 'toast';
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(function() {
        toast.remove();
    }, 3000);
}

// Update cart badge count
function updateCartBadge() {
    fetch('/revibe/cart_count.php')
        .then(r => r.text())
        .then(function(count) {
            let badge = document.querySelector('.cart-badge');
            const cartIcon = document.querySelector('.navbar__icons a[href*="cart"]');

            if (parseInt(count) > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'cart-badge';
                    cartIcon.appendChild(badge);
                }
                badge.textContent = count;
            }
        });
}

// Check wishlist status
function checkWishlist(productId) {
    fetch('/revibe/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=check&product_id=${productId}`
    })
    .then(r => r.json())
    .then(data => {
        const icon = document.getElementById('wishlist-icon');
        if (icon) {
            icon.style.fill = data.wishlisted ? '#e11d48' : 'none';
            icon.style.stroke = data.wishlisted ? '#e11d48' : '#333';
        }
    });
}

// Toggle wishlist
function toggleWishlist(productId, btn) {
    fetch('/revibe/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle&product_id=${productId}`
    })
    .then(r => r.json())
    .then(data => {
        // For product detail page
        const icon = document.getElementById('wishlist-icon');
        if (icon) {
            icon.style.fill = data.wishlisted ? '#e11d48' : 'none';
            icon.style.stroke = data.wishlisted ? '#e11d48' : '#333';
        }

        // For product cards
        if (btn) {
            const svg = btn.querySelector('svg');
            if (svg) {
                if (data.wishlisted) {
                    svg.classList.add('wishlisted');
                } else {
                    svg.classList.remove('wishlisted');
                }
            }

            // If on wishlist page and item was removed, fade out and remove the card
            const isWishlistPage = window.location.pathname.includes('wishlist_page');
            if (isWishlistPage && !data.wishlisted) {
                const card = btn.closest('.product-card');
                if (card) {
                    card.style.transition = 'opacity 0.3s ease';
                    card.style.opacity = '0';
                    setTimeout(() => {
                        card.remove();
                        const grid = document.getElementById('wishlist-grid');
                        if (grid && grid.querySelectorAll('.product-card').length === 0) {
                            grid.remove();
                            document.querySelector('.products-page').insertAdjacentHTML('beforeend',
                                `<div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
                                             text-align:center; color:#888;">
                                    <p style="font-size:18px; font-weight:600; margin-bottom:12px;">Your wishlist is empty.</p>
                                    <a href="products.php" style="color:#111; font-weight:700;">Browse products →</a>
                                </div>`
                            );
                        }
                    }, 300);
                }
            }
        }
    });
}

// ── Notifications dropdown ────────────────────────────────
function toggleDropdown() {
    const dropdown = document.getElementById('notif-dropdown');
    if (!dropdown) return;
    const isOpen = dropdown.style.display === 'block';
    dropdown.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) loadNotifications();
}

document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notif-wrapper');
    const dropdown = document.getElementById('notif-dropdown');
    if (wrapper && dropdown && !wrapper.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});

function loadNotifications() {
    fetch('/revibe/api/notifications_api.php?action=get_notifications&limit=6')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('notif-list');
            if (!list) return;
            if (!data.notifications || !data.notifications.length) {
                list.innerHTML = '<div style="padding:24px;text-align:center;color:#94a3b8;font-size:.85rem;">No notifications yet.</div>';
                return;
            }
            const icons = {message:'💬',order:'📦',payment:'💸',review:'⭐',offer:'🏷️',product:'🛍️',system:'🔔'};
            const colors = {message:'#dbeafe',order:'#d1fae5',payment:'#dcfce7',review:'#fef9c3',offer:'#fce7f3',product:'#ede9fe',system:'#f1f5f9'};
            list.innerHTML = data.notifications.map(n => `
                <a href="${n.link || '#'}" onclick="readNotif(event,${n.id},this)"
                   style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;
                          border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit;
                          background:${n.is_read==0?'#eff6ff':'transparent'};
                          transition:background .15s;">
                    <div style="width:40px;height:40px;border-radius:50%;flex-shrink:0;
                                background:${colors[n.type]||'#f1f5f9'};
                                display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
                        ${icons[n.type]||'🔔'}
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:.85rem;font-weight:600;color:#1e293b;
                                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            ${n.title}
                        </div>
                        <div style="font-size:.78rem;color:#64748b;margin-top:2px;
                                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            ${n.body||''}
                        </div>
                        <div style="font-size:.72rem;color:#94a3b8;margin-top:3px;">${timeAgoNotif(n.created_at)}</div>
                    </div>
                    ${n.is_read==0?'<div style="width:8px;height:8px;border-radius:50%;background:#3b82f6;flex-shrink:0;margin-top:4px;"></div>':''}
                </a>
            `).join('');
            updateNotifBadge();
        });
}

function timeAgoNotif(dateStr) {
    const d    = new Date(dateStr.replace(' ','T'));
    const diff = (Date.now() - d.getTime()) / 1000;
    if (isNaN(diff)) return '';
    if (diff < 60)    return 'just now';
    if (diff < 3600)  return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
}

function readNotif(e, id, el) {
    fetch('/revibe/api/notifications_api.php', {
        method: 'POST',
        body: new URLSearchParams({action: 'mark_read', id: id})
    });
    el.style.background = 'white';
    const dot = el.querySelector('div[style*="border-radius:50%;background:#3b82f6"]');
    if (dot) dot.remove();
    updateNotifBadge();
}

function markAllRead() {
    fetch('/revibe/api/notifications_api.php', {
        method: 'POST',
        body: new URLSearchParams({action: 'mark_all_read'})
    }).then(() => {
        loadNotifications();
        updateNotifBadge();
    });
}

function updateNotifBadge() {
    fetch('/revibe/api/notifications_api.php?action=get_badge_count')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('notif-badge');
            if (badge) badge.textContent = data.count || 0;
        });
}

// Poll badge every 15 seconds
setInterval(updateNotifBadge, 15000);
updateNotifBadge();