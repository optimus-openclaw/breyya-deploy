/**
 * Override chat timestamps to show full date/time in user's local timezone.
 * Format: "Mar 20, 2026, 1:07 AM"
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  function formatFullDate(el) {
    // The original text might be like "Fri 03:11 AM" or "Yesterday 03:11 AM"
    // We need the actual datetime from the message data
    // Instead, we'll find all msgTime elements and reformat them
  }

  // Observer to catch dynamically rendered messages
  var observer = new MutationObserver(function() {
    // Find all time elements with the msgTime class
    var timeEls = document.querySelectorAll('[class*="msgTime"]');
    timeEls.forEach(function(el) {
      if (el.dataset.reformatted) return;
      
      // Try to find the parent message and extract the original time
      // The time is stored in the React state, but we can parse from the text
      // Better approach: intercept the fetch response
    });
  });

  // Intercept the API response to get raw timestamps
  var origFetch = window.fetch;
  window.fetch = function() {
    return origFetch.apply(this, arguments).then(function(response) {
      var url = arguments[0] || (response && response.url) || '';
      if (typeof url === 'string' && url.indexOf('/api/chat') !== -1) {
        return response.clone().json().then(function(data) {
          // Store message timestamps for later use
          if (data && data.messages) {
            window.__chatTimestamps = window.__chatTimestamps || {};
            data.messages.forEach(function(msg) {
              if (msg.id && msg.created_at) {
                window.__chatTimestamps[msg.id] = msg.created_at;
              }
            });
          }
          return response;
        }).catch(function() { return response; });
      }
      return response;
    });
  };

  // Reformat timestamps every 500ms
  setInterval(function() {
    var timeEls = document.querySelectorAll('[class*="msgTime"]');
    timeEls.forEach(function(el) {
      if (el.dataset.fulldate) return;
      var text = el.textContent.trim();
      // Skip if already reformatted
      if (text.match(/\d{4}/)) { el.dataset.fulldate = '1'; return; }
      
      // Parse the existing relative time back to a date
      // Try to reconstruct from the text
      var now = new Date();
      var date = null;
      
      if (text.match(/^yesterday/i)) {
        var timePart = text.replace(/^yesterday\s*/i, '');
        var d = new Date(now);
        d.setDate(d.getDate() - 1);
        var tp = parseTime(timePart);
        if (tp) { d.setHours(tp.h, tp.m, 0); date = d; }
      } else if (text.match(/^\d{1,2}:\d{2}/)) {
        // Today - just time
        date = parseTimeToday(text);
      } else if (text.match(/^(mon|tue|wed|thu|fri|sat|sun)/i)) {
        // Day of week + time
        var parts = text.split(/\s+/);
        var dayName = parts[0];
        var timePart2 = parts.slice(1).join(' ');
        date = parseDayOfWeek(dayName, timePart2);
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
    var d = new Date();
    d.setHours(tp.h, tp.m, 0, 0);
    return d;
  }

  function parseDayOfWeek(dayName, timeStr) {
    var days = { sun: 0, mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6 };
    var target = days[dayName.toLowerCase().substring(0, 3)];
    if (target === undefined) return null;
    var tp = parseTime(timeStr);
    if (!tp) return null;
    var d = new Date();
    var current = d.getDay();
    var diff = current - target;
    if (diff <= 0) diff += 7;
    d.setDate(d.getDate() - diff);
    d.setHours(tp.h, tp.m, 0, 0);
    return d;
  }
})();
