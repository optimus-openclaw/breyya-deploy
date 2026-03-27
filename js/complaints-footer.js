/**
 * Adds Complaints/Report link to all content pages.
 * Also adds Report button to feed posts for CCBill compliance.
 */
(function() {
  var path = window.location.pathname;
  if (path.indexOf('/feed') === 0 || path.indexOf('/gallery') === 0 || path.indexOf('/chat') === 0) {
    setTimeout(function() {
      if (document.getElementById('complaints-link')) return;
      var link = document.createElement('div');
      link.id = 'complaints-link';
      link.innerHTML = '<a href="/complaints" style="color:#3d5368;text-decoration:none;pointer-events:auto;">Report / Complaints</a>';

      var isChat = path.indexOf('/chat') === 0;
      if (isChat) {
        // Chat page: insert below the input bar as a flex-shrink:0 item
        // so it doesn't break the 100vh flex column layout
        link.setAttribute('style',
          'text-align:center;padding:4px 8px;font-size:10px;color:#3d5368;' +
          'background:var(--bg-secondary, #111d32);border-top:1px solid rgba(255,255,255,0.04);' +
          'pointer-events:none;flex-shrink:0;');
        // Find the inputBar form and insert after it inside .page
        var form = document.querySelector('form[class*="inputBar"]');
        if (form && form.parentNode) {
          form.parentNode.insertBefore(link, form.nextSibling);
        } else {
          var container = document.querySelector('[class*="page"]') || document.body;
          container.appendChild(link);
        }
      } else {
        // Feed/Gallery: original behavior
        link.setAttribute('style',
          'text-align:center;padding:8px;font-size:11px;color:#3d5368;' +
          'position:relative;bottom:0;left:0;right:0;z-index:1;margin-top:10px;' +
          'background:transparent;pointer-events:none;');
        var container = document.querySelector('[class*="page"]') || document.body;
        container.appendChild(link);
      }
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
