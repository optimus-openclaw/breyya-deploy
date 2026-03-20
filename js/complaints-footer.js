/**
 * Adds Complaints/Report link to all content pages.
 * Also adds Report button to feed posts for CCBill compliance.
 */
(function() {
  // Add complaints link to bottom nav area on feed/gallery/chat
  var path = window.location.pathname;
  if (path.indexOf('/feed') === 0 || path.indexOf('/gallery') === 0 || path.indexOf('/chat') === 0) {
    setTimeout(function() {
      if (document.getElementById('complaints-link')) return;
      var link = document.createElement('div');
      link.id = 'complaints-link';
      link.setAttribute('style',
        'text-align:center;padding:8px;font-size:11px;color:#3d5368;' +
        'position:fixed;bottom:52px;left:0;right:0;z-index:99;' +
        'background:linear-gradient(transparent, #0d1b2a 30%);pointer-events:none;');
      link.innerHTML = '<a href="/contact#complaints" style="color:#3d5368;text-decoration:none;pointer-events:auto;">Report / Complaints</a>';
      document.body.appendChild(link);
    }, 1000);
  }

  // Add Report button to each feed post (for CCBill compliance)
  if (path.indexOf('/feed') === 0) {
    setInterval(function() {
      var posts = document.querySelectorAll('article[class*="post"], [data-post-id]');
      posts.forEach(function(post) {
        if (post.dataset.reportBtn) return;
        post.dataset.reportBtn = '1';
        
        var actions = post.querySelector('[class*="postActions"]');
        if (!actions) return;
        
        var btn = document.createElement('button');
        btn.className = actions.querySelector('button') ? actions.querySelector('button').className : '';
        btn.setAttribute('style', 'cursor:pointer;opacity:0.5;font-size:11px;margin-left:auto;');
        btn.innerHTML = '⚑';
        btn.title = 'Report content';
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          if (confirm('Report this content? This will notify our complaints team.')) {
            window.location.href = '/contact#complaints';
          }
        });
        actions.appendChild(btn);
      });
    }, 3000);
  }
})();
