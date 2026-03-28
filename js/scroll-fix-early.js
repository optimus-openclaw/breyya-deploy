/**
 * Scroll Fix — must load EARLY (before React hydrates)
 * Only auto-scrolls if user is already near the bottom.
 * If user has scrolled up to browse content, leave them alone.
 */
(function() {
  var orig = Element.prototype.scrollIntoView;
  Element.prototype.scrollIntoView = function(opts) {
    // Find the messages container
    var container = this.closest("[class*=messages]");
    if (container) {
      // Check if user is near the bottom (within 150px)
      var distFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
      if (distFromBottom > 150) {
        // User has scrolled up — don't yank them back down
        return;
      }
    }
    return orig.call(this, opts);
  };
})();
