<?php
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    $me = (int)$_SESSION['user_id'];
    $page_title = 'Notifications — ReMarket';
    require_once 'includes/db.php';
    require_once 'includes/header.php';
?>

<style>
.notif-page {
    max-width: 780px;
    margin: 32px auto 0;   
    padding: 0 16px;
}
.notif-page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px;
}
.notif-page-header h1 {
    font-size: 1.5rem; font-weight: 800;
    color: #1e293b; margin: 0;
}
.notif-mark-all {
    background: linear-gradient(135deg, #1d4ed8, #3b82f6);
    color: #fff; border: none; border-radius: 10px;
    padding: 9px 18px; font-size: .85rem; font-weight: 600;
    cursor: pointer;
}
.notif-filter-tabs {
    display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px;
}
.notif-tab {
    border: 1.5px solid #e2e8f0; background: #fff;
    color: #64748b; border-radius: 999px;
    padding: 6px 16px; font-size: .82rem; font-weight: 600;
    cursor: pointer; transition: all .15s; text-transform: capitalize;
}
.notif-tab.active {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff; border-color: transparent;
}
.notif-tab:hover:not(.active) { border-color: #3b82f6; color: #3b82f6; }
.notif-list {
    background: #fff; border-radius: 14px;
    border: 1px solid #e2e8f0; overflow: hidden;
    box-shadow: 0 2px 12px rgba(59,130,246,.06);
}
.notif-item {
    display: flex; gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
    cursor: pointer; transition: background .12s;
    text-decoration: none; color: inherit; align-items: flex-start;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #eff6ff; }
.notif-item.unread { background: #dbeafe; }
.notif-icon {
    width: 44px; height: 44px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; flex-shrink: 0;
}
.notif-icon.type-message { background: #dbeafe; }
.notif-icon.type-order   { background: #d1fae5; }
.notif-icon.type-payment { background: #dcfce7; }
.notif-icon.type-review  { background: #fef9c3; }
.notif-icon.type-offer   { background: #fce7f3; }
.notif-icon.type-product { background: #ede9fe; }
.notif-icon.type-system  { background: #f1f5f9; }
.notif-content { flex: 1; }
.notif-title { font-weight: 600; font-size: .9rem; color: #1e293b; }
.notif-body  { font-size: .83rem; color: #64748b; margin-top: 2px; line-height: 1.4; }
.notif-time  { font-size: .77rem; color: #94a3b8; margin-top: 4px; }
.notif-unread-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #3b82f6; flex-shrink: 0; align-self: center;
}
.notif-empty {
    padding: 60px 20px; text-align: center; color: #94a3b8;
}
.notif-empty-icon { font-size: 3rem; margin-bottom: 12px; }
.notif-load-more {
    display: block; width: 100%;
    background: none; border: none;
    color: #3b82f6; font-size: .88rem; font-weight: 600;
    padding: 14px; cursor: pointer; text-align: center;
    border-top: 1px solid #e2e8f0;
}
.notif-load-more:hover { background: #eff6ff; }

/* Dark mode */
body.dark .notif-page-header h1 { color: #e2e8f0; }
body.dark .notif-tab { background: #1e293b; border-color: #334155; color: #94a3b8; }
body.dark .notif-tab.active { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; }
body.dark .notif-list { background: #1e293b; border-color: #334155; }
body.dark .notif-item { border-color: #334155; color: #e2e8f0; }
body.dark .notif-item:hover { background: #1e3a8a22; }
body.dark .notif-item.unread { background: #1e3a8a44; }
body.dark .notif-title { color: #e2e8f0; }
body.dark .notif-body  { color: #94a3b8; }
body.dark .notif-load-more { color: #93c5fd; }
body.dark .notif-load-more:hover { background: #1e3a8a22; }

@media (max-width: 600px) {
    .notif-page { margin: 16px auto; }
    .notif-filter-tabs { gap: 4px; }
    .notif-tab { padding: 5px 12px; font-size: .78rem; }
}
</style>

<div class="notif-page">
    <div class="notif-page-header">
        <h1>🔔 Notifications</h1>
        <button class="notif-mark-all" onclick="notifMarkAll()">✓ Mark all as read</button>
    </div>

    <div class="notif-filter-tabs" id="filterTabs">
        <?php
        $tabs = ['all','message','order','payment','review','offer','product','system'];
        foreach ($tabs as $t) {
            echo "<button class='notif-tab" . ($t==='all'?' active':'') . "' onclick=\"notifSwitchTab(this,'$t')\">$t</button>";
        }
        ?>
    </div>

    <div class="notif-list" id="notifList">
        <div class="notif-empty">
            <div class="notif-empty-icon">⏳</div>
            <div>Loading…</div>
        </div>
    </div>

    <button class="notif-load-more" id="loadMoreBtn" style="display:none" onclick="notifLoadMore()">
        Load more
    </button>
</div>

<script>
(function() {
    const NOTIF_API = '/revibe/api/notifications_api.php';
    const ICONS = {message:'💬',order:'📦',payment:'💸',review:'⭐',offer:'🏷️',product:'🛍️',system:'🔔'};
    let nType   = 'all';
    let nOffset = 0;
    const PAGE  = 20;

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function timeAgo(dateStr) {
        const d    = new Date(dateStr.replace(' ','T'));
        const diff = (Date.now() - d.getTime()) / 1000;
        if (isNaN(diff)) return '';
        if (diff < 60)    return 'just now';
        if (diff < 3600)  return Math.floor(diff/60) + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
        return Math.floor(diff/86400) + 'd ago';
    }

    function renderItem(n) {
        const icon = ICONS[n.type] || '🔔';
        return `<a class="notif-item${n.is_read==0?' unread':''}"
                   href="${n.link || '#'}"
                   onclick="notifRead(event,${n.id},this)">
            <div class="notif-icon type-${n.type}">${icon}</div>
            <div class="notif-content">
                <div class="notif-title">${escHtml(n.title)}</div>
                <div class="notif-body">${escHtml(n.body||'')}</div>
                <div class="notif-time">${timeAgo(n.created_at)}</div>
            </div>
            ${n.is_read==0?'<div class="notif-unread-dot"></div>':''}
        </a>`;
    }

    async function loadNotifs(reset) {
        if (reset) {
            nOffset = 0;
            document.getElementById('notifList').innerHTML =
                '<div class="notif-empty"><div class="notif-empty-icon">⏳</div><div>Loading…</div></div>';
        }

        const params = new URLSearchParams({
            action: 'get_notifications',
            type:   nType,
            limit:  PAGE,
            offset: nOffset
        });

        const data = await fetch(NOTIF_API + '?' + params)
            .then(r => r.json())
            .catch(() => ({notifications:[], total:0}));

        const list = document.getElementById('notifList');
        if (reset) list.innerHTML = '';

        if (!data.notifications || !data.notifications.length) {
            if (reset) {
                list.innerHTML = `<div class="notif-empty">
                    <div class="notif-empty-icon">🔔</div>
                    <div>No notifications yet.</div>
                </div>`;
            }
            document.getElementById('loadMoreBtn').style.display = 'none';
            return;
        }

        data.notifications.forEach(n => {
            list.insertAdjacentHTML('beforeend', renderItem(n));
        });

        nOffset += data.notifications.length;
        document.getElementById('loadMoreBtn').style.display =
            nOffset < parseInt(data.total) ? 'block' : 'none';
    }

    // Expose functions globally
    window.notifSwitchTab = function(el, type) {
        document.querySelectorAll('.notif-tab').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
        nType = type;
        loadNotifs(true);
    };

    window.notifLoadMore = function() { loadNotifs(false); };

    window.notifRead = async function(e, id, el) {
        await fetch(NOTIF_API, {method:'POST', body: new URLSearchParams({action:'mark_read', id})});
        el.classList.remove('unread');
        el.querySelector('.notif-unread-dot')?.remove();
    };

    window.notifMarkAll = async function() {
        await fetch(NOTIF_API, {method:'POST', body: new URLSearchParams({action:'mark_all_read'})});
        loadNotifs(true);
        if (typeof updateNotifBadge === 'function') updateNotifBadge();
    };

    // Load on ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => loadNotifs(true));
    } else {
        loadNotifs(true);
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>