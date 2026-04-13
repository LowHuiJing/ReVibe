<footer style="background:#111;color:#fff;margin-top:60px;padding:48px 24px 24px;font-family:'Segoe UI',sans-serif;">
    <div style="max-width:1200px;margin:0 auto;">

        <!-- Top section -->
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:40px;">

            <!-- Brand -->
            <div>
                <div style="font-size:1.8rem;font-weight:900;letter-spacing:2px;margin-bottom:12px;">REVIBE</div>
                <p style="color:#94a3b8;font-size:.88rem;line-height:1.7;margin:0 0 16px;">
                    Malaysia's preloved fashion marketplace. Buy and sell sustainable fashion with confidence.
                </p>
                <div style="display:flex;gap:12px;">
                    <a href="#" style="width:36px;height:36px;border-radius:50%;background:#1d4ed8;
                        display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:.9rem;">📘</a>
                    <a href="#" style="width:36px;height:36px;border-radius:50%;background:#e11d48;
                        display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:.9rem;">📸</a>
                    <a href="#" style="width:36px;height:36px;border-radius:50%;background:#0ea5e9;
                        display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:.9rem;">🐦</a>
                </div>
            </div>

            <!-- Shop -->
            <div>
                <div style="font-weight:700;font-size:.9rem;letter-spacing:1px;text-transform:uppercase;margin-bottom:16px;color:#fff;">Shop</div>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <a href="/revibe/products.php?category=New" style="color:#94a3b8;text-decoration:none;font-size:.88rem;transition:color .15s;"
                       onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">New Arrivals</a>
                    <a href="/revibe/products.php?category=PreLoved" style="color:#94a3b8;text-decoration:none;font-size:.88rem;"
                       onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Pre-Loved</a>
                    <a href="/revibe/wishlist_page.php" style="color:#94a3b8;text-decoration:none;font-size:.88rem;"
                       onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Wishlist</a>
                    <a href="/revibe/cart.php" style="color:#94a3b8;text-decoration:none;font-size:.88rem;"
                       onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Cart</a>
                </div>
            </div>

            <!-- Support -->
            <div>
                <div style="font-weight:700;font-size:.9rem;letter-spacing:1px;text-transform:uppercase;margin-bottom:16px;color:#fff;">Support</div>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <a href="/revibe/faq.php" style="color:#94a3b8;text-decoration:none;font-size:.88rem;"
                       onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">FAQ</a>
                    <a href="/revibe/messaging.php" style="color:#94a3b8;text-decoration:none;font-size:.88rem;"
                       onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Messages</a>
                    <a href="/revibe/faq.php" style="color:#94a3b8;text-decoration:none;font-size:.88rem;"
                       onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Shipping Policy</a>
                    <a href="/revibe/return_request.php" style="color:#94a3b8;text-decoration:none;font-size:.88rem;"
                       onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">Returns</a>
                </div>
            </div>

            <!-- Contact -->
            <div>
                <div style="font-weight:700;font-size:.9rem;letter-spacing:1px;text-transform:uppercase;margin-bottom:16px;color:#fff;">Contact</div>
                <div style="display:flex;flex-direction:column;gap:10px;color:#94a3b8;font-size:.88rem;">
                    <span>📧 hello@revibe.my</span>
                    <span>📞 +60 12-345 6789</span>
                    <span>📍 Kuala Lumpur, Malaysia</span>
                </div>
            </div>

        </div>

        <!-- Divider -->
        <div style="border-top:1px solid #1e293b;padding-top:20px;
                    display:flex;align-items:center;justify-content:space-between;
                    flex-wrap:wrap;gap:12px;">
            <p style="color:#475569;font-size:.8rem;margin:0;">
                © <?= date('Y') ?> REVIBE. All rights reserved. Made with ♥ in Malaysia.
            </p>
            <div style="display:flex;gap:16px;">
                <a href="#" style="color:#475569;font-size:.8rem;text-decoration:none;"
                   onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#475569'">Privacy Policy</a>
                <a href="#" style="color:#475569;font-size:.8rem;text-decoration:none;"
                   onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#475569'">Terms of Use</a>
            </div>
        </div>

    </div>
</footer>

<script src="/revibe/js/main.js?v=<?= time() ?>"></script>
</body>
</html>