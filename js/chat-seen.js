/**
 * Chat UI fixes:
 * 1. "Delivered ✓" always shows under the LATEST fan message
 * 2. Changes to "Seen ✓✓" when typing
 * 3. Hides lock icons on non-PPV messages
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  // CSS: hide lock icons globally
  var s = document.createElement('style');
  s.textContent = '[class*="bubble"] ~ div:not([id]):not([class*="msgTime"]) { display:none !important; }';
  document.head.appendChild(s);

  setInterval(function() {
    // Remove any old delivery status
    var old = document.getElementById('delivery-status');
    
    // Find all messages
    var allMsgs = document.querySelectorAll('[class*="message"]');
    if (allMsgs.length === 0) return;

    // Find the LAST fan message (sent = right side)  
    var lastSentMsg = null;
    var lastSentIndex = -1;
    for (var i = allMsgs.length - 1; i >= 0; i--) {
      if (allMsgs[i].className.includes('sent')) {
        lastSentMsg = allMsgs[i];
        lastSentIndex = i;
        break;
      }
    }
    
    if (!lastSentMsg) { if (old) old.remove(); return; }

    // Check if there's a Breyya reply AFTER the last fan message
    var hasReplyAfter = false;
    for (var j = lastSentIndex + 1; j < allMsgs.length; j++) {
      if (allMsgs[j].className.includes('received')) { hasReplyAfter = true; break; }
    }

    // If Breyya already replied after the last fan msg, remove status
    if (hasReplyAfter) { if (old) old.remove(); return; }

    // Show Delivered or Seen
    var hasTyping = !!document.querySelector('[class*="typingDots"], [class*="typingBubble"]');
    var text = hasTyping ? 'Seen ✓✓' : 'Delivered ✓';
    var color = hasTyping ? '#00b4ff' : '#556677';

    if (old) {
      old.textContent = text;
      old.style.color = color;
      // Make sure it's positioned after the correct message
      if (old.previousElementSibling !== lastSentMsg) {
        old.remove();
        old = null;
      }
    }

    if (!old) {
      var el = document.createElement('div');
      el.id = 'delivery-status';
      el.textContent = text;
      el.style.cssText = 'text-align:right;padding:2px 16px 6px;font-size:11px;color:' + color + ';';
      // Insert right after the last sent message
      if (lastSentMsg.nextSibling) {
        lastSentMsg.parentElement.insertBefore(el, lastSentMsg.nextSibling);
      } else {
        lastSentMsg.parentElement.appendChild(el);
      }
    }

    // Also hide React's built-in "Seen ✓✓" to avoid duplicates
    document.querySelectorAll('div').forEach(function(d) {
      if (d.id !== 'delivery-status' && d.textContent.trim() === 'Seen ✓✓' && d.style.fontSize === '11px') {
        d.style.display = 'none';
      }
    });
  }, 400);
})();
