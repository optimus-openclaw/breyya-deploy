/**
 * Scroll Fix v4 — Simple. CSS + kill React's scroll.
 * Only auto-scroll when fan sends a message. Nothing else.
 */
(function() {
  // 1. CSS: disable browser scroll anchoring
  var style = document.createElement("style");
  style.textContent = [
    "[class*=messages] { overflow-anchor: none !important; }",
    "[class*=messagesInner] { overflow-anchor: none !important; }",
    "[class*=messages] img[src*='r2.dev'] { min-height: 250px; object-fit: contain; }",
    "[class*=messages] img[src*='/data/'] { min-height: 250px; object-fit: contain; }"
  ].join("\n");
  document.head.appendChild(style);

  // 2. Kill ALL scrollIntoView inside chat — permanently
  var orig = Element.prototype.scrollIntoView;
  Element.prototype.scrollIntoView = function(opts) {
    // Only allow if our flag is set
    if (window._allowChatScroll) {
      window._allowChatScroll = false;
      return orig.call(this, opts);
    }
    // Allow outside chat container
    var container = document.querySelector("[class*=messages]");
    if (container && container.contains(this)) return; // BLOCKED
    return orig.call(this, opts);
  };

  // 3. On page load — scroll to bottom once
  window.addEventListener("load", function() {
    setTimeout(function() {
      window._allowChatScroll = true;
      var container = document.querySelector("[class*=messages]");
      if (container) container.scrollTop = container.scrollHeight;
    }, 2000);
  });

  // 4. On fan send — scroll to bottom
  setTimeout(function() {
    var form = document.querySelector("[class*=inputBar]");
    if (form) {
      form.addEventListener("submit", function() {
        setTimeout(function() {
          var container = document.querySelector("[class*=messages]");
          if (container) container.scrollTop = container.scrollHeight;
        }, 200);
      });
    }
  }, 2000);
})();
