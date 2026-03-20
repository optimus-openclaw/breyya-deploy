/**
 * Realistic "Seen" behavior for chat.
 * Replaces instant "Seen ✓✓" with:
 * 1. "Delivered ✓" immediately
 * 2. "Seen ✓✓" after a random delay (mimics her opening the app)
 * 3. Then typing indicator kicks in naturally
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  // Track when we last sent a message
  var lastSentTime = 0;
  var seenShown = false;

  // Watch for send button clicks to track timing
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('[class*="sendBtn"]');
    if (btn) {
      lastSentTime = Date.now();
      seenShown = false;
    }
  });

  // Override the "Seen ✓✓" display
  setInterval(function() {
    var seenEls = document.querySelectorAll('div');
    seenEls.forEach(function(el) {
      if (el.textContent.trim() === 'Seen ✓✓' && el.style.fontSize === '11px') {
        var timeSinceSend = Date.now() - lastSentTime;
        
        // If we sent a message recently (within 10 minutes)
        if (lastSentTime > 0 && timeSinceSend < 600000) {
          // Random "seen" delay: 2-8 minutes after sending
          var seenDelay = (Math.random() * 360000) + 120000; // 2-8 min in ms
          
          if (timeSinceSend < seenDelay) {
            // Not enough time passed — show "Delivered" instead
            el.textContent = 'Delivered ✓';
            el.style.color = '#556677';
          } else {
            // Enough time passed — show "Seen"
            el.style.color = '#00b4ff';
            seenShown = true;
          }
        }
        // If no recent send or more than 10 min ago, leave "Seen" as normal
      }
    });
  }, 500);
})();
