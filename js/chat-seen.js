/**
 * Realistic Seen behavior — based on server-side is_read flag.
 * "Delivered ✓" until the AI processor marks the message as read.
 * "Seen ✓✓" only after Breyya's AI has started processing.
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  setInterval(function() {
    // Find all "Seen ✓✓" indicators
    var divs = document.querySelectorAll('div');
    divs.forEach(function(el) {
      var text = el.textContent.trim();
      if (text === 'Seen ✓✓' && el.style.fontSize === '11px') {
        // Replace with "Delivered ✓" — server will control when "Seen" shows
        // The React app shows "Seen" when has_pending=true && is_typing=false
        // We override: show "Delivered" until is_typing becomes true
        el.textContent = 'Delivered ✓';
        el.style.color = '#556677';
      }
    });
  }, 300);

  // Watch for typing indicator — when typing shows, upgrade previous "Delivered" to "Seen"
  setInterval(function() {
    var typingDots = document.querySelector('[class*="typingDots"]');
    if (typingDots) {
      // Breyya is typing — she's seen the message
      var divs = document.querySelectorAll('div');
      divs.forEach(function(el) {
        if (el.textContent.trim() === 'Delivered ✓' && el.style.fontSize === '11px') {
          el.textContent = 'Seen ✓✓';
          el.style.color = '#00b4ff';
        }
      });
    }
  }, 500);
})();
