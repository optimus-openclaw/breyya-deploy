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

  function postHTML(p) {
    var avatar = p.creator_avatar || '/images/hero2.jpg';
    var name = p.creator_name || 'Breyya';
    var media = '';
    if (p.media_url) {
      if (p.type === 'video') {
        media = '<video src="'+p.media_url+'" controls playsinline preload="metadata" style="width:100%;display:block;pointer-events:auto"></video>';
      } else {
        media = '<img src="'+p.media_url+'" alt="" style="pointer-events:none;-webkit-user-drag:none;width:100%;display:block" draggable="false"/>';
      }
    }
    return '<article class="feed-module__Sej6XW__post" data-post-id="'+p.id+'">' +
      '<div class="feed-module__Sej6XW__postHeader">' +
        '<img src="'+avatar+'" alt="" class="feed-module__Sej6XW__postAvatar"/>' +
        '<div><span class="feed-module__Sej6XW__postAuthor">'+esc(name)+'</span>' +
        '<span class="feed-module__Sej6XW__postTime">'+timeAgo(p.created_at)+'</span></div>' +
      '</div>' +
      (p.caption ? '<p class="feed-module__Sej6XW__postCaption">'+esc(p.caption)+'</p>' : '') +
      '<div class="feed-module__Sej6XW__postMedia" style="user-select:none;-webkit-user-select:none;position:relative">' +
        media +
      '</div>' +
      '<div class="feed-module__Sej6XW__postActions">' +
        '<button class="feed-module__Sej6XW__actionBtn ">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>' +
          '<span>'+(p.like_count||0)+'</span>' +
        '</button>' +
        '<a href="/chat" class="feed-module__Sej6XW__actionBtn">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>' +
        '</a>' +
        '<button class="feed-module__Sej6XW__actionBtn">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>' +
          '<span>Tip</span>' +
        '</button>' +
      '</div>' +
    '</article>';
  }

  function loadFeed() {
    fetch('/api/posts/list.php?limit=50')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.ok || !data.posts || data.posts.length === 0) return;

        var feedDiv = document.querySelector('.feed-module__Sej6XW__feed');
        if (!feedDiv) return;

        // Build HTML for all dynamic posts
        var html = '';
        data.posts.forEach(function(p) { html += postHTML(p); });

        // INSERT dynamic posts at the TOP of the feed, before all static articles
        var firstStatic = feedDiv.querySelector('article');
        if (firstStatic) {
          var wrapper = document.createElement('div');
          wrapper.id = 'dynamic-posts';
          wrapper.innerHTML = html;
          feedDiv.insertBefore(wrapper, firstStatic);
        } else {
          feedDiv.innerHTML = html;
        }
      })
      .catch(function(e) { console.error('[dynamic-feed]', e); });
  }

  // Wait for React to fully render, then inject
  function init() {
    // Remove any previous dynamic posts (on re-run)
    var old = document.getElementById('dynamic-posts');
    if (old) old.remove();
    loadFeed();
  }

  if (document.readyState === 'complete') setTimeout(init, 1200);
  else window.addEventListener('load', function() { setTimeout(init, 1200); });

  // Re-inject every 30 seconds (handles React re-renders)
  setInterval(init, 30000);
})();
