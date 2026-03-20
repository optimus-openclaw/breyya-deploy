/**
 * Realistic Seen/Delivered behavior.
 * Shows "Delivered ✓" after last fan message until Breyya replies.
 * Shows "Seen ✓✓" only when typing indicator appears.
 * Also hides the lock icons on non-PPV messages.
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  setInterval(function() {
    // Hide lock icons on non-PPV text messages (the small lock next to messages)
    var lockIcons = document.querySelectorAll('[class*="message"] svg[viewBox="0 0 24 24"]');
    lockIcons.forEach(function(svg) {
      var path = svg.querySelector('path');
      if (path && path.getAttribute('d') && path.getAttribute('d').includes('18 8h-1V6c0-2.76')) {
        svg.parentElement.style.display = 'none';
      }
    });

    // Replace "Seen ✓✓" with "Delivered ✓" unless typing
    var divs = document.querySelectorAll('div');
    var hasTyping = !!document.querySelector('[class*="typingDots"]');
    
    divs.forEach(function(el) {
      var text = el.textContent.trim();
      if (text === 'Seen ✓✓' && el.style.fontSize === '11px') {
        if (hasTyping) {
          el.textContent = 'Seen ✓✓';
          el.style.color = '#00b4ff';
        } else {
          el.textContent = 'Delivered ✓';
          el.style.color = '#556677';
        }
      }
    });
  }, 300);
})();
