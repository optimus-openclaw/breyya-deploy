/**
 * Chat enhancements:
 * 1. Full date timestamps (Mar 20, 2026, 3:11 AM)
 * 2. Dynamic Breyya status based on last activity
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
    // Chat page header status
    var statusEls = document.querySelectorAll('[class*="headerStatus"]');
    statusEls.forEach(function(el) {
      el.innerHTML = '<span style="color:' + color + '">● ' + text + '</span>';
    });
    
    // Feed page creator status (below avatar)
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

  // Update on load and every 60 seconds
  if (document.readyState === 'complete') { setTimeout(updateStatus, 500); }
  else { window.addEventListener('load', function() { setTimeout(updateStatus, 500); }); }
  setInterval(updateStatus, 60000);

  // === Full Date Timestamps (chat page only) ===
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  setInterval(function() {
    var timeEls = document.querySelectorAll('[class*="msgTime"]');
    timeEls.forEach(function(el) {
      if (el.dataset.fulldate) return;
      var text = el.textContent.trim();
      if (text.match(/\d{4}/)) { el.dataset.fulldate = '1'; return; }
      
      var now = new Date();
      var date = null;
      
      if (text.match(/^yesterday/i)) {
        var timePart = text.replace(/^yesterday\s*/i, '');
        var d = new Date(now);
        d.setDate(d.getDate() - 1);
        var tp = parseTime(timePart);
        if (tp) { d.setHours(tp.h, tp.m, 0); date = d; }
      } else if (text.match(/^\d{1,2}:\d{2}/)) {
        date = parseTimeToday(text);
      } else if (text.match(/^(mon|tue|wed|thu|fri|sat|sun)/i)) {
        var parts = text.split(/\s+/);
        date = parseDayOfWeek(parts[0], parts.slice(1).join(' '));
      }
      
      if (date && !isNaN(date.getTime())) {
        el.textContent = date.toLocaleDateString([], {
          month: 'short', day: 'numeric', year: 'numeric'
        }) + ', ' + date.toLocaleTimeString([], {
          hour: 'numeric', minute: '2-digit', hour12: true
        });
        el.dataset.fulldate = '1';
      }
    });
  }, 500);

  function parseTime(str) {
    var m = str.match(/(\d{1,2}):(\d{2})\s*(AM|PM)?/i);
    if (!m) return null;
    var h = parseInt(m[1]), min = parseInt(m[2]), ampm = (m[3] || '').toUpperCase();
    if (ampm === 'PM' && h < 12) h += 12;
    if (ampm === 'AM' && h === 12) h = 0;
    return { h: h, m: min };
  }
  function parseTimeToday(str) {
    var tp = parseTime(str);
    if (!tp) return null;
    var d = new Date(); d.setHours(tp.h, tp.m, 0, 0); return d;
  }
  function parseDayOfWeek(dayName, timeStr) {
    var days = { sun:0, mon:1, tue:2, wed:3, thu:4, fri:5, sat:6 };
    var target = days[dayName.toLowerCase().substring(0,3)];
    if (target === undefined) return null;
    var tp = parseTime(timeStr);
    if (!tp) return null;
    var d = new Date();
    var diff = d.getDay() - target;
    if (diff <= 0) diff += 7;
    d.setDate(d.getDate() - diff);
    d.setHours(tp.h, tp.m, 0, 0);
    return d;
  }
})();
