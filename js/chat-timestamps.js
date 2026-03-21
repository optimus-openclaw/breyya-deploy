/**
 * Chat enhancements:
 * 1. Dynamic Breyya status based on last activity
 * (Timestamp formatting handled by the chat client component directly)
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0 && 
      window.location.pathname.indexOf('/feed') !== 0) return;

  // === Dynamic Status ===
  function updateStatus() {
    fetch('/api/chat/last-active.php')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.last_active) return setStatus('Away — leave a message 💌', '#ff9a50');
        
        var last = new Date(d.last_active.replace(' ', 'T') + 'Z');
        var now = new Date();
        var hoursAgo = (now - last) / (1000 * 60 * 60);
        
        if (hoursAgo < 2) {
          setStatus('Active today 💕', '#00e676');
        } else if (hoursAgo < 12) {
          setStatus('Last seen recently', '#00b4d8');
        } else {
          setStatus('Away — leave a message 💌', '#ff9a50');
        }
      })
      .catch(function() {
        setStatus('Away — leave a message 💌', '#ff9a50');
      });
  }

  function setStatus(text, color) {
    var statusEls = document.querySelectorAll('[class*="headerStatus"]');
    statusEls.forEach(function(el) {
      el.innerHTML = '<span style="color:' + color + '">● ' + text + '</span>';
    });
    
    var feedStatus = document.querySelectorAll('[class*="creatorInfo"] span');
    feedStatus.forEach(function(el) {
      if (el.querySelector('span[style*="border-radius:50%"]') || 
          el.textContent.includes('Offline') ||
          el.textContent.includes('Sleeping') ||
          el.textContent.includes('Active') ||
          el.textContent.includes('Away') ||
          el.textContent.includes('Last seen')) {
        el.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:' + color + ';display:inline-block"></span> ' + text;
      }
    });
  }

  if (document.readyState === 'complete') { setTimeout(updateStatus, 500); }
  else { window.addEventListener('load', function() { setTimeout(updateStatus, 500); }); }
  setInterval(updateStatus, 60000);
})();
