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

  function escapeHtml(s) {
    return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function buildPost(p) {
    var avatar = p.creator_avatar || '/images/hero.jpg';
    var name = p.creator_name || 'Breyya';
    var isVideo = p.type === 'video';

    var article = document.createElement('article');
    article.className = 'feed-module__Sej6XW__post';
    article.dataset.postId = p.id;
    article.innerHTML =
      '<div class="feed-module__Sej6XW__postHeader">' +
        '<img src="' + avatar + '" alt="" class="feed-module__Sej6XW__postAvatar"/>' +
        '<div>' +
          '<span class="feed-module__Sej6XW__postAuthor">' + escapeHtml(name) + '</span>' +
          '<span class="feed-module__Sej6XW__postTime">' + timeAgo(p.created_at) + '</span>' +
        '</div>' +
      '</div>' +
      (p.caption ? '<p class="feed-module__Sej6XW__postCaption">' + escapeHtml(p.caption) + '</p>' : '') +
      '<div class="feed-module__Sej6XW__postMedia" style="user-select:none;-webkit-user-select:none;position:relative">' +
        (isVideo
          ? '<video src="' + p.media_url + '" controls playsinline preload="metadata" style="width:100%;border-radius:8px;"></video>'
          : '<img src="' + p.media_url + '" alt="" style="pointer-events:none;-webkit-user-drag:none;width:100%" draggable="false"/>'
        ) +
      '</div>' +
      '<div class="feed-module__Sej6XW__postActions">' +
        '<button class="feed-module__Sej6XW__actionBtn">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>' +
          '<span>' + (p.like_count || 0) + '</span>' +
        '</button>' +
        '<a href="/chat" class="feed-module__Sej6XW__actionBtn">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>' +
        '</a>' +
        '<button class="feed-module__Sej6XW__actionBtn">' +
          '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>' +
          '<span>Tip</span>' +
        '</button>' +
      '</div>';

    return article;
  }

  async function loadDynamicFeed() {
    try {
      var res = await fetch('/api/posts/list.php?limit=50');
      if (!res.ok) return;
      var data = await res.json();
      if (!data.ok || !data.posts || data.posts.length === 0) return;

      // Find the feed container
      var feedContainer = document.querySelector('.feed-module__Sej6XW__feed');
      if (!feedContainer) return;

      // Prepend dynamic posts BEFORE static posts
      var fragment = document.createDocumentFragment();
      data.posts.forEach(function(p) {
        // Skip if this post ID already exists
        if (document.querySelector('[data-post-id="' + p.id + '"]')) return;
        fragment.appendChild(buildPost(p));
      });

      feedContainer.insertBefore(fragment, feedContainer.firstChild);
    } catch(e) {
      console.error('[dynamic-feed]', e);
    }
  }

  if (document.readyState === 'complete') setTimeout(loadDynamicFeed, 800);
  else window.addEventListener('load', function() { setTimeout(loadDynamicFeed, 800); });
})();
