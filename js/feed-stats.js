/**
 * Feed Stats: Like count (259 floor) + Seen timestamp
 * Only runs on /feed/ page
 */
(function() {
  if (window.location.pathname.indexOf('/feed') !== 0) return;

  var LIKE_FLOOR = 259;
  var injected = false;

  function inject() {
    if (injected) return;
    
    // Find the creator info section (has avatar + name)
    var creatorInfo = document.querySelector('[class*="creatorInfo"]');
    if (!creatorInfo) return;
    
    // Find the name element
    var nameEl = document.querySelector('[class*="creatorName"]');
    if (!nameEl) return;

    injected = true;

    // Count real likes from visible posts
    var realLikes = 0;
    document.querySelectorAll('[class*="actionBtn"] span').forEach(function(s) {
      var n = parseInt(s.textContent);
      if (!isNaN(n)) realLikes += n;
    });

    // Create stats row
    var statsDiv = document.createElement('div');
    statsDiv.id = 'feed-stats-row';
    statsDiv.setAttribute('style',
      'display:flex;align-items:center;gap:12px;margin-top:4px;font-size:12px;color:#556677;');

    // Like count
    var likeSpan = document.createElement('span');
    likeSpan.textContent = '❤️ ' + (LIKE_FLOOR + realLikes).toLocaleString();
    statsDiv.appendChild(likeSpan);

    // Seen timestamp - fetch from API
    var seenSpan = document.createElement('span');
    seenSpan.textContent = '';
    statsDiv.appendChild(seenSpan);

    fetch('/api/chat/last-active.php')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.ok && d.last_active) {
          var last = new Date(d.last_active.replace(' ', 'T') + 'Z');
          var now = new Date();
          var mins = Math.floor((now - last) / 60000);
          var text;
          if (mins < 1) text = 'Seen just now';
          else if (mins < 60) text = 'Seen ' + mins + 'm ago';
          else if (mins < 1440) text = 'Seen ' + Math.floor(mins/60) + 'h ago';
          else text = 'Seen ' + Math.floor(mins/1440) + 'd ago';
          seenSpan.textContent = '· ' + text;
        }
      })
      .catch(function() {});

    // Insert after the name/status area inside creatorInfo
    var innerDiv = creatorInfo.querySelector('div');
    if (innerDiv) {
      innerDiv.appendChild(statsDiv);
    } else {
      creatorInfo.appendChild(statsDiv);
    }
  }

  // Try repeatedly until React renders
  var attempts = 0;
  var timer = setInterval(function() {
    inject();
    attempts++;
    if (injected || attempts > 20) clearInterval(timer);
  }, 500);
})();
