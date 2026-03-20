/**
 * Delivered/Seen indicator for chat.
 * Always shows "Delivered ✓" after fan's last message.
 * Shows "Seen ✓✓" when typing indicator appears.
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  // Hide lock icons on non-PPV messages
  var lockStyle = document.createElement('style');
  lockStyle.textContent = '[class*="received"] [class*="bubble"] + div { display: none !important; }';
  document.head.appendChild(lockStyle);

  setInterval(function() {
    // Find the last message in the chat
    var messages = document.querySelectorAll('[class*="message"]');
    if (messages.length === 0) return;
    var lastMsg = messages[messages.length - 1];
    
    // Check if it's a sent message (fan's message, right side)
    var isSent = lastMsg.className.includes('sent');
    if (!isSent) return; // Breyya replied last, no need for status

    // Check if we already injected a status
    var existingStatus = document.getElementById('delivery-status');
    var hasTyping = !!document.querySelector('[class*="typingDots"], [class*="typingBubble"]');
    
    // Also check for React's built-in "Seen ✓✓"
    var reactSeen = null;
    document.querySelectorAll('div').forEach(function(el) {
      if (el.textContent.trim() === 'Seen ✓✓' && el.style.fontSize === '11px') {
        reactSeen = el;
      }
    });

    if (reactSeen) {
      // Replace React's "Seen" with our status
      if (hasTyping) {
        reactSeen.textContent = 'Seen ✓✓';
        reactSeen.style.color = '#00b4ff';
      } else {
        reactSeen.textContent = 'Delivered ✓';
        reactSeen.style.color = '#556677';
      }
      // Remove our injected one if React's is showing
      if (existingStatus) existingStatus.remove();
      return;
    }

    // No React status — inject our own
    if (existingStatus) {
      // Update existing
      if (hasTyping) {
        existingStatus.textContent = 'Seen ✓✓';
        existingStatus.style.color = '#00b4ff';
      } else {
        existingStatus.textContent = 'Delivered ✓';
        existingStatus.style.color = '#556677';
      }
    } else {
      // Create new status element after the last sent message
      var status = document.createElement('div');
      status.id = 'delivery-status';
      status.textContent = hasTyping ? 'Seen ✓✓' : 'Delivered ✓';
      status.style.cssText = 'text-align:right;padding:2px 12px;font-size:11px;color:' + 
        (hasTyping ? '#00b4ff' : '#556677') + ';';
      
      // Insert after the last message
      var messagesContainer = lastMsg.parentElement;
      if (messagesContainer) {
        // Insert after lastMsg
        if (lastMsg.nextSibling) {
          messagesContainer.insertBefore(status, lastMsg.nextSibling);
        } else {
          messagesContainer.appendChild(status);
        }
      }
    }
  }, 500);
})();
