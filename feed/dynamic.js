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
    article.dataset.dynamic = '1';
    article.style.position = 'relative';
    article.innerHTML =
      '<div class="feed-module__Sej6XW__postHeader">' +
        '<img src="'+avatar+'" alt="" class="feed-module__Sej6XW__postAvatar"/>' +
        '<div><span class="feed-module__Sej6XW__postAuthor">'+esc(name)+'</span>' +
        '<span class="feed-module__Sej6XW__postTime">'+timeAgo(p.created_at)+'</span></div>' +
      '</div>' +
      (p.caption ? '<p class="feed-module__Sej6XW__postCaption">'+esc(p.caption)+'</p>' : '') +
      '<div class="feed-module__Sej6XW__postMedia" style="user-select:none;-webkit-user-select:none;position:relative">' +
        (isVideo
          ? '<video src="'+p.media_url+'" controls playsinline preload="metadata" style="width:100%;display:block;border-radius:8px"></video>'
          : '<img src="'+p.media_url+'" alt="" style="pointer-events:none;-webkit-user-drag:none;width:100%;display:block" draggable="false"/>') +
      '</div>' +
      '<div class="feed-module__Sej6XW__postActions">' +
        '<button class="feed-module__Sej6XW__actionBtn heart-btn" data-liked="'+(p.liked?'1':'0')+'">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="'+heartFill+'" stroke="'+heartStroke+'" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>' +
          '<span>'+(p.like_count||0)+'</span></button>' +
        '<a href="/chat" class="feed-module__Sej6XW__actionBtn">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></a>' +
        '<button class="feed-module__Sej6XW__actionBtn tip-btn">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>' +
          '<span>Tip</span></button>' +
      '</div>';

    // Wire heart
    var hb = article.querySelector('.heart-btn');
    hb.style.cursor='pointer'; hb.style.transition='transform 0.2s';
    hb.addEventListener('click', function(e) {
      e.preventDefault(); e.stopPropagation();
      var sv = hb.querySelector('svg'), ce = hb.querySelector('span');
      var lk = hb.dataset.liked === '1', ct = parseInt(ce.textContent)||0;
      if (lk) { hb.dataset.liked='0'; sv.setAttribute('fill','none'); sv.setAttribute('stroke','currentColor'); ce.textContent=Math.max(0,ct-1); }
      else { hb.dataset.liked='1'; sv.setAttribute('fill','#ff4757'); sv.setAttribute('stroke','#ff4757'); ce.textContent=ct+1; hb.style.transform='scale(1.3)'; setTimeout(function(){hb.style.transform='scale(1)';},200); }
      var tk=getToken(); fetch('/api/posts/like.php',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json','Authorization':'Bearer '+tk},body:JSON.stringify({post_id:parseInt(p.id)})}).catch(function(){});
    });

    // Wire tip
    var tb = article.querySelector('.tip-btn');
    tb.style.cursor='pointer';
    tb.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); showTipModal(); });

    return article;
  }

  function showTipModal() {
    var ex = document.getElementById('tip-modal-overlay'); if(ex) ex.remove();
    var ov = document.createElement('div'); ov.id='tip-modal-overlay';
    ov.setAttribute('style','position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);');
    var md = document.createElement('div');
    md.setAttribute('style','background:#111d32;border:1px solid rgba(0,180,255,0.2);border-radius:20px;padding:24px;min-width:280px;text-align:center;');
    md.innerHTML='<h3 style="color:#fff;margin:0 0 16px;font-size:16px;">Send a tip to Breyya 💕</h3><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;"><button class="ta" data-a="5" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$5</button><button class="ta" data-a="10" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$10</button><button class="ta" data-a="25" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$25</button><button class="ta" data-a="50" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$50</button></div><div style="display:flex;gap:8px;margin-bottom:14px;align-items:center;"><span style="color:#7a93a8;font-size:18px;font-weight:700;">$</span><input id="ct" type="number" min="2" placeholder="Custom" style="flex:1;padding:12px;background:#1a2940;border:1px solid rgba(255,255,255,0.1);border-radius:12px;color:#fff;font-size:15px;outline:none;"/><button id="sc" style="padding:12px 20px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;">Send</button></div><p style="color:#556677;font-size:11px;margin:0 0 14px;">Minimum: $2</p><button id="tc" style="color:#667;cursor:pointer;background:none;border:1px solid rgba(255,255,255,0.15);border-radius:10px;padding:8px 24px;font-size:13px;">Cancel</button>';
    ov.appendChild(md); document.body.appendChild(ov);
    md.querySelectorAll('.ta').forEach(function(b){b.addEventListener('click',function(){b.textContent='✅ Tipped!';b.style.background='#00c853';setTimeout(function(){ov.remove();},1000);});});
    var ci=md.querySelector('#ct'),sc=md.querySelector('#sc');
    sc.addEventListener('click',function(){var v=parseInt(ci.value);if(!v||v<2){ci.style.borderColor='#ff4757';return;}sc.textContent='✅ $'+v;sc.style.background='#00c853';setTimeout(function(){ov.remove();},1000);});
    ci.addEventListener('keydown',function(e){if(e.key==='Enter')sc.click();});
    md.querySelector('#tc').addEventListener('click',function(){ov.remove();});
    ov.addEventListener('click',function(e){if(e.target===ov)ov.remove();});
  }

  var postsCache = null;

  function loadFeed() {
    var token = getToken();
    var headers = {};
    if (token) headers['Authorization'] = 'Bearer ' + token;

    fetch('/api/posts/list.php?limit=50', { credentials: 'include', headers: headers })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.ok || !data.posts || data.posts.length === 0) return;
        postsCache = data.posts;
        renderPosts();
      })
      .catch(function(e) { console.error('[feed]', e); });
  }

  function renderPosts() {
    if (!postsCache) return;
    var feedDiv = document.querySelector('.feed-module__Sej6XW__feed');
    if (!feedDiv) return;

    // Remove old dynamic posts
    var old = document.getElementById('dynamic-posts');
    if (old) old.remove();

    var wrapper = document.createElement('div');
    wrapper.id = 'dynamic-posts';
    postsCache.forEach(function(p) { wrapper.appendChild(buildPost(p)); });
    feedDiv.insertBefore(wrapper, feedDiv.firstChild);
  }

  // Use MutationObserver to detect when React finishes rendering the feed
  var observer = new MutationObserver(function(mutations) {
    var feedDiv = document.querySelector('.feed-module__Sej6XW__feed');
    if (feedDiv && postsCache && !document.getElementById('dynamic-posts')) {
      renderPosts();
    }
  });
  observer.observe(document.body, { childList: true, subtree: true });

  // Aggressive initialization for mobile Safari
  function init() { loadFeed(); }
  
  // Try immediately
  init();
  
  // Try again after short delays
  setTimeout(init, 500);
  setTimeout(init, 1000);
  setTimeout(init, 2000);
  setTimeout(init, 3000);
  
  // Also try on DOMContentLoaded and load
  document.addEventListener('DOMContentLoaded', init);
  window.addEventListener('load', function() { setTimeout(init, 500); });

  // Reload when page becomes visible
  document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') setTimeout(loadFeed, 200);
  });

  // Periodic re-render check (catches React re-hydrations)
  setInterval(function() {
    var feedDiv = document.querySelector('.feed-module__Sej6XW__feed');
    if (feedDiv && postsCache && !document.getElementById('dynamic-posts')) {
      renderPosts();
    }
  }, 2000);
})();
