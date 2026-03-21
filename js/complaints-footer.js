/**
 * Adds Complaints/Report footer to all content pages.
 * Footer sits at very bottom, nav bar moves up above it.
 * Also adds Report button to feed posts for CCBill compliance.
 */
(function() {
  var path = window.location.pathname;
  if (path.indexOf('/feed') === 0 || path.indexOf('/gallery') === 0 || path.indexOf('/chat') === 0) {
    setTimeout(function() {
      if (document.getElementById('complaints-link')) return;

      // Create complaints footer - fixed to very bottom
      var footer = document.createElement('div');
      footer.id = 'complaints-link';
      footer.setAttribute('style',
        'text-align:center;padding:6px 0;font-size:11px;' +
        'position:fixed;bottom:0;left:0;right:0;z-index:101;' +
        'background:#0a1628;border-top:1px solid rgba(255,255,255,0.04);');
      footer.innerHTML = '<a href="/complaints" style="color:#3d5368;text-decoration:none;">Report / Complaints</a>';
      document.body.appendChild(footer);

      // Push the bottom nav bar up above the complaints footer
      var navBar = document.querySelector('nav[style*="position:fixed"][style*="bottom:0"]');
      if (navBar) {
        navBar.style.bottom = footer.offsetHeight + 'px';
      }

      // Add padding to page body so content isn't hidden behind both bars
      var existingPadding = parseInt(document.body.style.paddingBottom) || 0;
      document.body.style.paddingBottom = (existingPadding + footer.offsetHeight) + 'px';
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
            window.location.href = '/complaints';
          }
        });
        actions.appendChild(btn);
      });
    }, 3000);
  }
})();
