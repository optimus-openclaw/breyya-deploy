/**
 * Feed Stats: Like count (259 floor) + Seen timestamp
 */
(function() {
  if (window.location.pathname.indexOf('/feed') !== 0) return;

  var LIKE_FLOOR = 259;

  function tryInject() {
    if (document.getElementById('feed-stats-row')) return true;
    
    // Find ANY element with creatorInfo or creatorName in class
    var allEls = document.querySelectorAll('*');
    var target = null;
    for (var i = 0; i < allEls.length; i++) {
      var cls = allEls[i].className || '';
      if (typeof cls === 'string' && cls.indexOf('creatorName') > -1) {
        target = allEls[i].parentElement;
        break;
      }
    }
    
    if (!target) return false;

    // Count likes from post action buttons
    var realLikes = 0;
    var spans = document.querySelectorAll('button span, [class*="actionBtn"] span');
    spans.forEach(function(s) {
      var n = parseInt(s.textContent);
      if (!isNaN(n) && n > 0 && n < 1000) realLikes += n;
    });

    var div = document.createElement('div');
    div.id = 'feed-stats-row';
    div.style.cssText = 'display:flex;align-items:center;gap:8px;font-size:11px;color:#556677;margin-top:2px;';
    div.innerHTML = '<span>❤️ ' + (LIKE_FLOOR + realLikes).toLocaleString() + '</span><span id="feed-seen-text"></span>';
    target.appendChild(div);

    // Fetch seen time
    fetch('/api/chat/last-active.php')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.last_active) return;
        var last = new Date(d.last_active.replace(' ', 'T') + 'Z');
        var mins = Math.floor((new Date() - last) / 60000);
        var text = mins < 1 ? 'just now' : mins < 60 ? mins + 'm ago' : mins < 1440 ? Math.floor(mins/60) + 'h ago' : Math.floor(mins/1440) + 'd ago';
        var el = document.getElementById('feed-seen-text');
        if (el) el.textContent = '· Seen ' + text;
      }).catch(function(){});

    return true;
  }

  // Try every 500ms for 15 seconds
  var t = setInterval(function() { if (tryInject()) clearInterval(t); }, 500);
  setTimeout(function() { clearInterval(t); }, 15000);
})();
