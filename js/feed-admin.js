/**
 * Feed admin controls — adds delete button to each post for admin/creator only.
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
    // Check via API
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

    var posts = document.querySelectorAll('[class*="__post"]');
    posts.forEach(function(post, index) {
      if (post.querySelector('.admin-delete-btn')) return;

      // Try to find the post ID from data attributes or index
      var btn = document.createElement('button');
      btn.className = 'admin-delete-btn';
      btn.innerHTML = '🗑️ Delete';
      btn.setAttribute('style',
        'position:absolute;top:8px;right:8px;z-index:100;' +
        'background:rgba(255,59,48,0.9);color:#fff;border:none;' +
        'font-size:11px;font-weight:600;padding:4px 10px;border-radius:12px;' +
        'cursor:pointer;font-family:inherit;backdrop-filter:blur(4px);' +
        'transition:background 0.2s;'
      );
      btn.onmouseover = function() { btn.style.background = 'rgba(255,59,48,1)'; };
      btn.onmouseout = function() { btn.style.background = 'rgba(255,59,48,0.9)'; };

      btn.onclick = async function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!confirm('Delete this post? This cannot be undone.')) return;

        // For dynamic posts, get the post ID from data attribute
        var postId = post.dataset.postId;
        
        if (postId) {
          // Dynamic post — delete via API
          try {
            var token = getToken();
            var res = await fetch('/api/posts/delete.php', {
              method: 'POST',
              credentials: 'include',
              headers: {
                'Content-Type': 'application/json',
                ...(token ? { 'Authorization': 'Bearer ' + token } : {})
              },
              body: JSON.stringify({ post_id: parseInt(postId) })
            });
            var data = await res.json();
            if (data.ok) {
              post.style.transition = 'opacity 0.3s';
              post.style.opacity = '0';
              setTimeout(function() { post.remove(); }, 300);
            } else {
              alert('Delete failed: ' + (data.error || 'Unknown error'));
            }
          } catch(err) {
            alert('Error: ' + err.message);
          }
        } else {
          // Static post — just hide it (can't delete from static HTML)
          post.style.transition = 'opacity 0.3s';
          post.style.opacity = '0';
          setTimeout(function() { post.remove(); }, 300);
        }
      };

      // Make the post container relative for absolute positioning
      post.style.position = 'relative';
      post.appendChild(btn);
    });
  }

  // Keep injecting (React may re-render, or dynamic posts load later)
  if (document.readyState === 'complete') setTimeout(injectDeleteButtons, 1000);
  else window.addEventListener('load', function() { setTimeout(injectDeleteButtons, 1000); });
  setInterval(injectDeleteButtons, 3000);
})();
