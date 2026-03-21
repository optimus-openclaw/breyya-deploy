(function() {
  if (window.location.pathname.indexOf('/feed') !== 0) return;
  
  // Wait for page to be visible (auth gate)
  function waitAndInject() {
    if (document.documentElement.style.visibility === 'hidden') {
      setTimeout(waitAndInject, 500);
      return;
    }
    
    // Page is visible — wait for React to render
    setTimeout(function() {
      // Find the sticky creator header
      var header = document.querySelector('[style*="sticky"]');
      if (!header) {
        // Try finding by content
        var allDivs = document.getElementsByTagName('div');
        for (var i = 0; i < allDivs.length; i++) {
          if (allDivs[i].textContent.trim() === 'Breyya' && allDivs[i].querySelector('img')) {
            header = allDivs[i];
            break;
          }
        }
      }
      
      if (!header || document.getElementById('feed-stats-row')) return;
      
      var div = document.createElement('div');
      div.id = 'feed-stats-row';
      div.style.cssText = 'text-align:center;padding:4px 0;font-size:11px;color:#556677;background:#111d32;';
      div.innerHTML = '❤️ 259 · <span id="feed-seen-text">Seen recently</span>';
      
      // Insert after the header
      header.after(div);
      
      // Fetch real seen time
      fetch('/api/chat/last-active.php')
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (!d.ok || !d.last_active) return;
          var last = new Date(d.last_active.replace(' ', 'T') + 'Z');
          var mins = Math.floor((new Date() - last) / 60000);
          var text = mins < 1 ? 'just now' : mins < 60 ? mins + 'm ago' : mins < 1440 ? Math.floor(mins/60) + 'h ago' : Math.floor(mins/1440) + 'd ago';
          var el = document.getElementById('feed-seen-text');
          if (el) el.textContent = 'Seen ' + text;
        }).catch(function(){});
    }, 2000);
  }
  
  waitAndInject();
})();
