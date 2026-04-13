<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    require_once 'includes/db.php';
    require_once 'includes/session.php';
    require_once 'includes/auth.php';
    if (session_status() === PHP_SESSION_NONE) session_start();

    checkSessionTimeout();
    if (!isset($_SESSION['user_id'])) {
        header('Location: /revibe/signin.php');
        exit;
    }

    $seller_id = (int)$_SESSION['user_id'];
    $me = (int)$_SESSION['user_id'];
    $page_title = 'Notifications — ReVibe';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Messages — ReVibe</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="/revibe/css/messaging.css">
    <style>
        .pm-header{background:#1a1a1a;color:#fff;padding:.9rem 1.2rem;border-radius:18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:18px auto 14px;max-width:1200px;}
        .pm-header-left{display:flex;align-items:center;gap:10px;}
        .pm-icon{color:#c8f04a;}
        .pm-header h1{font-family:'Montserrat',sans-serif;color:#1a1a1a;font-size:1.05rem;font-weight:900;margin:0;color:#c8f04a;letter-spacing:.5px;}
        .pm-subtitle{font-family:'Montserrat',sans-serif;font-size:.78rem;color:#94a3b8;margin-top:2px;}
        .pm-back-btn{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1.5px solid #444;color:#ccc;text-decoration:none;}
        .pm-back-btn:hover{border-color:#c8f04a;color:#c8f04a;}
        .wrap{max-width:1200px;margin:2rem auto;padding:0 1.5rem;}
        .btn-teal{font-family:'Montserrat',sans-serif;display:inline-flex;align-items:center;gap:8px;padding:.55rem 1rem;border-radius:10px;background:#fff;color:#1a1a1a;border:1.5px solid #ddd;text-decoration:none;font-weight:900;font-size:.82rem;cursor:pointer;white-space:nowrap;}
        .btn-teal:hover{border-color:#c8f04a;}
        .btn-outline-teal{display:inline-flex;align-items:center;justify-content:center;padding:.55rem 1rem;border-radius:10px;background:#fff;color:#1a1a1a;border:1.5px solid #ddd;font-weight:900;cursor:pointer;}
        .btn-outline-teal:hover{border-color:#c8f04a;}
        .pm-wrap{max-width:1200px;margin:auto auto;padding:auto auto;}
        @media(max-width:700px){.pm-header{padding:.9rem 1rem;margin:12px auto 12px;}.pm-wrap{padding:0 1rem;}}
    </style>
</head>
<body>

<div class="wrap">
<header class="pm-header">
    <div class="pm-header-left">
        <a href="/revibe/product_management.php" class="pm-back-btn" aria-label="Back">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <span class="material-symbols-outlined pm-icon">storefront</span>
        <div>
            <h1>Seller Messages</h1>
            <div class="pm-subtitle">Chat with buyers</div>
        </div>
    </div>
    <a href="/revibe/dashboard.php" class="btn-teal">
        <span class="material-symbols-outlined">dashboard</span>
        Back to Dashboard
    </a>
</header>
<div class="wrap">

<div class="pm-wrap">
  <div class="msg-container">

    <aside class="msg-sidebar">
        <div class="msg-sidebar-header">
            <h2>💬 Messages</h2>
            <div class="msg-search">
                <span class="msg-search-icon">🔍</span>
                <input type="text" id="convSearch" placeholder="Search conversations…">
            </div>
        </div>
        <div class="conv-list" id="convList">
            <div style="padding:24px;text-align:center;color:#94a3b8;font-size:.85rem;">Loading…</div>
        </div>
    </aside>

    <main class="msg-chat">
        <div class="chat-empty" id="chatEmpty">
            <div class="chat-empty-icon">💬</div>
            <h3>Select a conversation</h3>
            <p style="font-size:.85rem;">Choose a chat from the left to start messaging.</p>
        </div>

        <div class="chat-header" id="chatHeader" style="display:none">
            <div class="conv-avatar" id="chatOtherAvatar">?</div>
            <div class="chat-header-info">
                <div class="chat-header-name" id="chatOtherName">–</div>
                <div class="chat-header-sub"  id="chatOtherSub">–</div>
            </div>
            <div class="chat-header-actions">
                <button id="btnBlock"   class="btn-block">🚫 Block</button>
                <button id="btnUnblock" style="display:none">✅ Unblock</button>
            </div>
        </div>

        <div class="chat-messages" id="chatMessages" style="display:none"></div>

        <div class="reply-bar" id="replyBar" style="display:none">
            <div class="reply-bar-text">
                <strong>Replying to:</strong> <span id="replyPreview"></span>
            </div>
            <span class="reply-bar-close" id="cancelReply">✕</span>
        </div>

        <div class="emoji-picker-popup" id="emojiPicker" style="display:none"></div>

        <div class="chat-input-area" id="chatInputArea" style="display:none">
            <div class="blocked-banner" id="blockedBanner" style="display:none">
                🚫 You cannot send messages to this user.
            </div>
            <div class="chat-input-row" id="inputRow">
                <button class="input-btn" id="emojiBtn">😊</button>
                <textarea id="msgInput" rows="1" placeholder="Type a message…" maxlength="2000"></textarea>
                <label class="input-btn" style="cursor:pointer" title="Send image">
                    📷 <input type="file" id="imgUpload" accept="image/*" style="display:none">
                </label>
                <button class="send-btn" id="sendBtn">➤</button>
            </div>
        </div>
    </main>

    <aside class="msg-product-panel">
        <div class="product-panel-header">📦 Item Details</div>
        <div class="product-panel-body" id="productPanelBody">
            <p style="color:#94a3b8;font-size:.85rem;text-align:center;padding-top:40px;">
                Select a conversation to view item details.
            </p>
        </div>
    </aside>

  </div>
</div>

<script>
const ME  = <?= $me ?>;
const API = '/revibe/api/messages_api.php';

let currentConvId   = null;
let currentOtherId  = null;
let currentConvData = null;
let replyToId       = null;
let pollTimer       = null;
let lastMsgId       = 0;
let convCache       = [];

document.addEventListener('DOMContentLoaded', async () => {
    buildEmojiPicker();
    await loadConversations();

    document.getElementById('sendBtn').addEventListener('click', sendMessage);
    document.getElementById('msgInput').addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    document.getElementById('msgInput').addEventListener('input', autoResize);
    document.getElementById('emojiBtn').addEventListener('click', toggleEmoji);
    document.getElementById('cancelReply').addEventListener('click', cancelReply);
    document.getElementById('btnBlock').addEventListener('click', blockUser);
    document.getElementById('btnUnblock').addEventListener('click', unblockUser);
    document.getElementById('imgUpload').addEventListener('change', uploadImage);
    document.getElementById('convSearch').addEventListener('input', filterConvs);

    document.addEventListener('click', e => {
        if (!e.target.closest('#emojiPicker') && !e.target.closest('#emojiBtn'))
            document.getElementById('emojiPicker').style.display = 'none';
    });
});

async function getAPI(params) {
    const url = API + '?' + new URLSearchParams(params);
    const res = await fetch(url);
    return res.json();
}
async function postAPI(action, data={}) {
    const body = new URLSearchParams({action, ...data});
    const res  = await fetch(API, {method:'POST', body});
    return res.json();
}

async function loadConversations() {
    const data = await getAPI({action:'get_conversations'});
    convCache  = data;
    renderConvList(data);
}

function renderConvList(list) {
    const el = document.getElementById('convList');
    if (!list.length) {
        el.innerHTML = '<div style="padding:24px;text-align:center;color:#94a3b8;font-size:.85rem;">No conversations yet.</div>';
        return;
    }
    el.innerHTML = list.map(c => {
        const initial = (c.other_name || '?')[0].toUpperCase();
        const time    = c.last_message_at ? timeAgo(c.last_message_at) : '';
        const unread  = c.my_unread > 0 ? `<span class="unread-badge">${c.my_unread}</span>` : '';
        const preview = escHtml(c.last_message || 'No messages yet');
        return `<div class="conv-item${currentConvId==c.id?' active':''}"
                     onclick="openConversation(${c.id})">
            <div class="conv-avatar">${initial}${unread}</div>
            <div class="conv-info">
                <div class="conv-name">${escHtml(c.other_name)}</div>
                <div class="conv-preview">${preview}</div>
            </div>
            <div class="conv-time">${time}</div>
        </div>`;
    }).join('');
}

function filterConvs() {
    const q = document.getElementById('convSearch').value.toLowerCase();
    const filtered = q ? convCache.filter(c => (c.other_name||'').toLowerCase().includes(q)) : convCache;
    renderConvList(filtered);
}

async function openConversation(convId) {
    currentConvId = convId;
    cancelReply();

    currentConvData = convCache.find(c => c.id == convId);
    if (!currentConvData) { await loadConversations(); currentConvData = convCache.find(c => c.id == convId); }
    currentOtherId = currentConvData ? currentConvData.other_id : null;

    document.getElementById('chatEmpty').style.display      = 'none';
    document.getElementById('chatHeader').style.display     = 'flex';
    document.getElementById('chatMessages').style.display   = 'flex';
    document.getElementById('chatInputArea').style.display  = 'block';

    const name = currentConvData?.other_name || 'User';
    document.getElementById('chatOtherAvatar').textContent = name[0].toUpperCase();
    document.getElementById('chatOtherName').textContent   = name;
    document.getElementById('chatOtherSub').textContent    = currentConvData?.product_title || 'Direct message';

    const isBlocked = currentConvData?.is_blocked;
    document.getElementById('btnBlock').style.display      = isBlocked ? 'none'  : 'inline-flex';
    document.getElementById('btnUnblock').style.display    = isBlocked ? 'inline-flex' : 'none';
    document.getElementById('blockedBanner').style.display = isBlocked ? 'block' : 'none';
    document.getElementById('inputRow').style.display      = isBlocked ? 'none'  : 'flex';

    renderProductPanel(currentConvData);

    lastMsgId = 0;
    await loadMessages();

    clearInterval(pollTimer);
    pollTimer = setInterval(loadMessages, 3000);

    // Refresh badge
    fetch('/revibe/api/messages_api.php?action=get_unread_count')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('msg-badge') || document.getElementById('msgBadge');
            if (badge) {
                if (data.unread > 0) {
                    badge.textContent = data.unread;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        });
}

async function loadMessages() {
    if (!currentConvId) return;
    const data = await getAPI({action:'get_messages', conversation_id: currentConvId});
    if (data.error) return;

    const container  = document.getElementById('chatMessages');
    const wasAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 60;
    const newMsgs    = data.filter(m => m.id > lastMsgId);

    newMsgs.forEach(m => {
        lastMsgId = Math.max(lastMsgId, m.id);
        container.insertAdjacentHTML('beforeend', renderBubble(m));
    });

    if (wasAtBottom || newMsgs.length > 0) container.scrollTop = container.scrollHeight;
    if (newMsgs.length > 0) loadConversations();
}

function renderBubble(m) {
    const mine    = parseInt(m.sender_id) === parseInt(ME);
    const deleted = m.is_deleted == 1;
    const replyHtml = (!deleted && m.reply_to_id && m.reply_body)
        ? `<div class="msg-reply-quote"><strong>${escHtml(m.reply_sender||'User')}:</strong> ${escHtml((m.reply_body||'').substring(0,80))}</div>`
        : '';
    const imgHtml = (!deleted && m.image_url)
        ? `<img src="${escHtml(m.image_url)}" alt="image" onclick="window.open(this.src)">`
        : '';
    const bodyHtml    = deleted ? `<em>This message was deleted.</em>` : escHtml(m.body || '');
    const actionsHtml = !deleted
        ? `<div class="msg-actions"><button onclick="setReply(${m.id},\`${escHtml(m.body||'📷').replace(/`/g,"'")}\`)">↩ Reply</button>${mine ? `<button onclick="deleteMsg(${m.id})">🗑 Delete</button>` : ''}</div>`
        : '';
    return `<div class="msg-bubble-row${mine?' mine':''}" id="msg-${m.id}">
        <div class="msg-avatar-sm">${(m.sender_name||'?')[0].toUpperCase()}</div>
        <div style="display:flex;flex-direction:column;${mine?'align-items:flex-end':''}">
            <div class="msg-bubble${deleted?' deleted':''}">
                ${actionsHtml}${replyHtml}${bodyHtml}${imgHtml}
            </div>
            <div class="msg-time">${formatTime(m.created_at)}</div>
        </div>
    </div>`;
}

async function sendMessage() {
    const input = document.getElementById('msgInput');
    const body  = input.value.trim();
    if (!body && !window._pendingImageUrl) return;
    if (!currentConvId) return;

    const payload = {
        conversation_id: currentConvId,
        body:            body,
        reply_to_id:     replyToId || '',
        image_url:       window._pendingImageUrl || ''
    };
    input.value = '';
    input.style.height = '';
    window._pendingImageUrl = null;
    cancelReply();

    const res = await postAPI('send_message', payload);
    if (res.error) {
        if (res.error === 'profanity') {
            showToast('⚠️ Your message contains inappropriate language.');
        } else {
            showToast('❌ ' + res.error);
        }
        input.value = payload.body;
        return;
    }
    await loadMessages();
}

async function deleteMsg(msgId) {
    if (!confirm('Delete this message?')) return;
    await postAPI('delete_message', {message_id: msgId});
    const el = document.getElementById('msg-' + msgId);
    if (el) el.querySelector('.msg-bubble').innerHTML = '<em>This message was deleted.</em>';
}

function setReply(msgId, preview) {
    replyToId = msgId;
    document.getElementById('replyBar').style.display = 'flex';
    document.getElementById('replyPreview').textContent = preview.substring(0,80);
    document.getElementById('msgInput').focus();
}
function cancelReply() {
    replyToId = null;
    document.getElementById('replyBar').style.display = 'none';
}

async function blockUser() {
    if (!currentOtherId || !confirm('Block this user?')) return;
    await postAPI('block_user', {user_id: currentOtherId});
    showToast('User blocked.');
    await loadConversations();
    openConversation(currentConvId);
}
async function unblockUser() {
    if (!currentOtherId || !confirm('Unblock this user?')) return;
    await postAPI('unblock_user', {user_id: currentOtherId});
    showToast('User unblocked.');
    await loadConversations();
    openConversation(currentConvId);
}

async function uploadImage() {
    const file = document.getElementById('imgUpload').files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image', file);
    const res  = await fetch(API, {method:'POST', body: formData});
    const data = await res.json();
    if (data.url) {
        window._pendingImageUrl = data.url;
        showToast('📷 Image ready. Press send.');
    } else {
        showToast(data.error || 'Upload failed');
    }
}

const EMOJIS = ['😀','😂','🥹','😍','😎','🤔','😅','🙏','👍','❤️','🔥','✅','🎉','💯','😭','😤','🤩','🥰','😱','🫶','👏','💪','🌸','⭐','🛍️','💬','📦','💸','🎁','🔔'];
function buildEmojiPicker() {
    const p = document.getElementById('emojiPicker');
    p.innerHTML = EMOJIS.map(e => `<button onclick="insertEmoji('${e}')">${e}</button>`).join('');
}
function toggleEmoji() {
    const p = document.getElementById('emojiPicker');
    p.style.display = p.style.display === 'grid' ? 'none' : 'grid';
}
function insertEmoji(e) {
    const ta = document.getElementById('msgInput');
    const s  = ta.selectionStart;
    ta.value = ta.value.slice(0,s) + e + ta.value.slice(ta.selectionEnd);
    ta.selectionStart = ta.selectionEnd = s + e.length;
    ta.focus();
    document.getElementById('emojiPicker').style.display = 'none';
}

function renderProductPanel(conv) {
    const body = document.getElementById('productPanelBody');
    if (!conv || !conv.product_id) {
        body.innerHTML = '<p style="color:#94a3b8;font-size:.85rem;text-align:center;padding-top:40px;">No item linked.</p>';
        return;
    }
    const price = 'RM ' + parseFloat(conv.product_price||0).toFixed(2);
    const img   = conv.product_image
        ? `<img src="/revibe/${escHtml(conv.product_image)}" class="product-img" alt="product">`
        : `<div class="product-img" style="display:flex;align-items:center;justify-content:center;font-size:3rem;">📦</div>`;
    body.innerHTML = `
        ${img}
        <div class="product-title">${escHtml(conv.product_title||'Item')}</div>
        <div class="product-price">${price}</div>
        <a href="/revibe/product_detail.php?id=${conv.product_id}" class="product-btn">View Listing</a>
    `;
}

function autoResize() {
    const ta = document.getElementById('msgInput');
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
}
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
function formatTime(dateStr) {
    const d = new Date(dateStr.replace(' ','T'));
    return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
}
function showToast(msg) {
    const t = document.createElement('div');
    t.textContent = msg;
    Object.assign(t.style, {
        position:'fixed', bottom:'24px', left:'50%', transform:'translateX(-50%)',
        background:'#1e293b', color:'#fff', padding:'10px 20px',
        borderRadius:'999px', fontSize:'.88rem', zIndex:'9999',
        boxShadow:'0 4px 16px rgba(0,0,0,.2)'
    });
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}
</script>

</body>
</html>
