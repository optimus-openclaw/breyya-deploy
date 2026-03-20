(function(){
  function timeAgo(iso) {
    var d = new Date(iso.replace(' ', 'T') + (iso.includes('Z') ? '' : 'Z'));
    var diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff/60) + ' minutes ago';
    if (diff < 7200) return '1 hour ago';
    if (diff < 86400) return Math.floor(diff/3600) + ' hours ago';
    if (diff < 172800) return '1 day ago';
    if (diff < 604800) return Math.floor(diff/86400) + ' days ago';
    return Math.floor(diff/604800) + ' weeks ago';
  }

  function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function getToken() {
    try { return localStorage.getItem('breyya_token') || localStorage.getItem('token') || ''; }
    catch(e) { return ''; }
  }

  function buildPost(p) {
    var avatar = p.creator_avatar || '/images/hero2.jpg';
    var name = p.creator_name || 'Breyya';
    var isVideo = p.type === 'video';
    var heartFill = p.liked ? '#ff4757' : 'none';
    var heartStroke = p.liked ? '#ff4757' : 'currentColor';

    var article = document.createElement('article');
    article.className = 'feed-module__Sej6XW__post';
    article.dataset.postId = p.id;
    article.style.position = 'relative';

    article.innerHTML =
      '<div class="feed-module__Sej6XW__postHeader">' +
        '<img src="' + avatar + '" alt="" class="feed-module__Sej6XW__postAvatar"/>' +
        '<div><span class="feed-module__Sej6XW__postAuthor">' + esc(name) + '</span>' +
        '<span class="feed-module__Sej6XW__postTime">' + timeAgo(p.created_at) + '</span></div>' +
      '</div>' +
      (p.caption ? '<p class="feed-module__Sej6XW__postCaption">' + esc(p.caption) + '</p>' : '') +
      '<div class="feed-module__Sej6XW__postMedia" style="user-select:none;-webkit-user-select:none;position:relative">' +
        (isVideo
          ? '<video src="' + p.media_url + '" controls playsinline preload="metadata" style="width:100%;display:block;border-radius:8px"></video>'
          : '<img src="' + p.media_url + '" alt="" style="pointer-events:none;-webkit-user-drag:none;width:100%;display:block" draggable="false"/>'
        ) +
      '</div>' +
      '<div class="feed-module__Sej6XW__postActions">' +
        '<button class="feed-module__Sej6XW__actionBtn heart-btn" data-liked="' + (p.liked ? '1' : '0') + '" data-post-id="' + p.id + '">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="' + heartFill + '" stroke="' + heartStroke + '" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>' +
          '<span>' + (p.like_count || 0) + '</span>' +
        '</button>' +
        '<a href="/chat" class="feed-module__Sej6XW__actionBtn">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>' +
        '</a>' +
        '<button class="feed-module__Sej6XW__actionBtn tip-btn">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>' +
          '<span>Tip</span>' +
        '</button>' +
      '</div>';

    // Wire heart button directly
    var heartBtn = article.querySelector('.heart-btn');
    heartBtn.style.cursor = 'pointer';
    heartBtn.style.transition = 'transform 0.2s';
    heartBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var svg = heartBtn.querySelector('svg');
      var countEl = heartBtn.querySelector('span');
      var liked = heartBtn.dataset.liked === '1';
      var count = parseInt(countEl.textContent) || 0;

      if (liked) {
        heartBtn.dataset.liked = '0';
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        countEl.textContent = Math.max(0, count - 1);
      } else {
        heartBtn.dataset.liked = '1';
        svg.setAttribute('fill', '#ff4757');
        svg.setAttribute('stroke', '#ff4757');
        countEl.textContent = count + 1;
        heartBtn.style.transform = 'scale(1.3)';
        setTimeout(function() { heartBtn.style.transform = 'scale(1)'; }, 200);
      }

      var token = getToken();
      fetch('/api/posts/like.php', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
        body: JSON.stringify({ post_id: parseInt(p.id) })
      }).catch(function() {});
    });

    // Wire tip button directly
    var tipBtn = article.querySelector('.tip-btn');
    tipBtn.style.cursor = 'pointer';
    tipBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      showTipModal();
    });

    return article;
  }

  function showTipModal() {
    var existing = document.getElementById('tip-modal-overlay');
    if (existing) existing.remove();

    var overlay = document.createElement('div');
    overlay.id = 'tip-modal-overlay';
    overlay.setAttribute('style',
      'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;' +
      'display:flex;align-items:center;justify-content:center;' +
      'backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);');

    var modal = document.createElement('div');
    modal.setAttribute('style',
      'background:#111d32;border:1px solid rgba(0,180,255,0.2);border-radius:20px;' +
      'padding:24px;min-width:280px;text-align:center;font-family:inherit;');
    modal.innerHTML =
      '<h3 style="color:#fff;margin:0 0 16px;font-size:16px;">Send a tip to Breyya 💕</h3>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">' +
        '<button class="tip-amt" data-amt="5" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$5</button>' +
        '<button class="tip-amt" data-amt="10" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$10</button>' +
        '<button class="tip-amt" data-amt="25" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$25</button>' +
        '<button class="tip-amt" data-amt="50" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$50</button>' +
      '</div>' +
      '<div style="display:flex;gap:8px;margin-bottom:14px;align-items:center;">' +
        '<span style="color:#7a93a8;font-size:18px;font-weight:700;">$</span>' +
        '<input id="custom-tip" type="number" min="2" step="1" placeholder="Custom amount" style="flex:1;padding:12px;background:#1a2940;border:1px solid rgba(255,255,255,0.1);border-radius:12px;color:#fff;font-size:15px;outline:none;"/>' +
        '<button id="send-custom-tip" style="padding:12px 20px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;">Send</button>' +
      '</div>' +
      '<p style="color:#556677;font-size:11px;margin:0 0 14px;">Minimum tip: $2</p>' +
      '<button id="tip-cancel" style="color:#667;cursor:pointer;background:none;border:1px solid rgba(255,255,255,0.15);border-radius:10px;padding:8px 24px;font-size:13px;">Cancel</button>';

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    modal.querySelectorAll('.tip-amt').forEach(function(b) {
      b.addEventListener('click', function() {
        b.textContent = '✅ Tipped!'; b.style.background = '#00c853';
        setTimeout(function() { overlay.remove(); }, 1000);
      });
    });
    var ci = modal.querySelector('#custom-tip');
    var sc = modal.querySelector('#send-custom-tip');
    sc.addEventListener('click', function() {
      var v = parseInt(ci.value);
      if (!v || v < 2) { ci.style.borderColor = '#ff4757'; ci.placeholder = 'Min $2'; return; }
      sc.textContent = '✅ $' + v; sc.style.background = '#00c853';
      setTimeout(function() { overlay.remove(); }, 1000);
    });
    ci.addEventListener('keydown', function(e) { if (e.key === 'Enter') sc.click(); });
    modal.querySelector('#tip-cancel').addEventListener('click', function() { overlay.remove(); });
    overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.remove(); });
  }

  function loadFeed() {
    var token = getToken();
    var headers = {};
    if (token) headers['Authorization'] = 'Bearer ' + token;

    fetch('/api/posts/list.php?limit=50', { credentials: 'include', headers: headers })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.ok || !data.posts || data.posts.length === 0) return;

        var feedDiv = document.querySelector('.feed-module__Sej6XW__feed');
        if (!feedDiv) return;

        // Remove previous dynamic posts
        var old = document.getElementById('dynamic-posts');
        if (old) old.remove();

        var wrapper = document.createElement('div');
        wrapper.id = 'dynamic-posts';
        data.posts.forEach(function(p) { wrapper.appendChild(buildPost(p)); });

        feedDiv.insertBefore(wrapper, feedDiv.firstChild);
      })
      .catch(function(e) { console.error('[feed]', e); });
  }

  // Load feed with retries (React may not have rendered the container yet)
  var retries = 0;
  function tryLoad() {
    var feedDiv = document.querySelector('.feed-module__Sej6XW__feed');
    if (feedDiv) {
      loadFeed();
    } else if (retries < 20) {
      retries++;
      setTimeout(tryLoad, 500);
    }
  }
  if (document.readyState === 'complete') setTimeout(tryLoad, 500);
  else window.addEventListener('load', function() { setTimeout(tryLoad, 500); });
  // Also reload if page becomes visible again (user navigated back)
  document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') setTimeout(loadFeed, 300);
  });
})();
