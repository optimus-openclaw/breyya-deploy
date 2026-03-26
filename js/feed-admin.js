/**
 * Feed admin controls — single delete button per post, top-right, admin only.
 */
(function() {
  if (window.location.pathname.indexOf('/feed') !== 0) return;

  var isAdmin = false;
  try {
    var user = JSON.parse(localStorage.getItem('breyya_user') || '{}');
    if (user.role === 'creator' || user.role === 'admin') isAdmin = true;
  } catch(e) {}
  try {
    var user2 = JSON.parse(localStorage.getItem('user') || '{}');
    if (user2.role === 'creator' || user2.role === 'admin') isAdmin = true;
  } catch(e) {}

  if (!isAdmin) {
    fetch('/api/auth/me.php', { credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d && d.ok && d.user && (d.user.role === 'creator' || d.user.role === 'admin')) {
          isAdmin = true;
          injectDeleteButtons();
        }
      }).catch(function() {});
  }

  function getToken() {
    try { return localStorage.getItem('breyya_token') || localStorage.getItem('token') || ''; }
    catch(e) { return ''; }
  }

  function injectDeleteButtons() {
    if (!isAdmin) return;

    // Target only article elements (actual posts), not every div with "post" in class
    var posts = document.querySelectorAll('article[class*="post"]');
    if (posts.length === 0) {
      // Fallback for dynamic posts
      posts = document.querySelectorAll('[data-post-id]');
    }

    posts.forEach(function(post) {
      // Skip if already has our button
      if (post.dataset.adminBtn === '1') return;
      post.dataset.adminBtn = '1';

      var btn = document.createElement('button');
      btn.innerHTML = '🗑️';
      btn.title = 'Delete post';
      btn.setAttribute('style',
        'position:absolute;top:12px;right:12px;z-index:100;' +
        'background:rgba(255,59,48,0.85);color:#fff;border:none;' +
        'width:32px;height:32px;border-radius:50%;' +
        'cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;' +
        'backdrop-filter:blur(4px);transition:transform 0.2s,background 0.2s;'
      );
      btn.onmouseover = function() { btn.style.transform='scale(1.15)'; btn.style.background='rgba(255,59,48,1)'; };
      btn.onmouseout = function() { btn.style.transform='scale(1)'; btn.style.background='rgba(255,59,48,0.85)'; };

      btn.onclick = async function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!confirm('Delete this post?')) return;

        var postId = post.dataset.postId;
        if (postId) {
          try {
            var token = getToken();
            var res = await fetch('/api/posts/delete.php', {
              method: 'POST', credentials: 'include',
              headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
              body: JSON.stringify({ post_id: parseInt(postId) })
            });
            var data = await res.json();
            if (!data.ok) { alert('Delete failed: ' + (data.error || '')); return; }
          } catch(err) { alert('Error: ' + err.message); return; }
        }
        post.style.transition = 'opacity 0.3s';
        post.style.opacity = '0';
        setTimeout(function() { post.remove(); }, 300);
      };

      post.style.position = 'relative';
      post.appendChild(btn);
    });
  }

  if (document.readyState === 'complete') setTimeout(injectDeleteButtons, 1500);
  else window.addEventListener('load', function() { setTimeout(injectDeleteButtons, 1500); });
  // Only re-check every 5 seconds (not 3) to avoid duplicates
  setInterval(injectDeleteButtons, 5000);
})();
