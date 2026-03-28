/**
 * Kill auto-scroll v1 - disables React's auto-scroll completely.
 * Chat only scrolls on: page load + after fan sends a message.
 */
(function() {
  var userSentMessage = false;

  function init() {
    var container = document.querySelector("[class*=messages]");
    if (!container) { setTimeout(init, 500); return; }

    // Override scrollIntoView
    var orig = Element.prototype.scrollIntoView;
    Element.prototype.scrollIntoView = function(opts) {
      if (userSentMessage && container.contains(this)) {
        userSentMessage = false;
        return orig.call(this, opts);
      }
      if (container.contains(this)) return;
      return orig.call(this, opts);
    };

    // Block scrollTop setter from React polling
    var desc = Object.getOwnPropertyDescriptor(Element.prototype, "scrollTop");
    if (desc && desc.set) {
      var origSet = desc.set;
      Object.defineProperty(container, "scrollTop", {
        get: function() { return desc.get.call(this); },
        set: function(val) {
          if (userSentMessage) {
            userSentMessage = false;
            origSet.call(this, val);
          }
        },
        configurable: true
      });
    }
  }

  function watchSubmit() {
    var form = document.querySelector("[class*=inputBar]");
    if (!form) { setTimeout(watchSubmit, 500); return; }
    form.addEventListener("submit", function() {
      userSentMessage = true;
      setTimeout(function() { userSentMessage = false; }, 3000);
    });
  }

  // Scroll to bottom once on initial page load
  setTimeout(function() {
    var c = document.querySelector("[class*=messages]");
    if (c) c.scrollTop = c.scrollHeight;
  }, 2500);

  setTimeout(init, 1000);
  setTimeout(watchSubmit, 1000);
})();
