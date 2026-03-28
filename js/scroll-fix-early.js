/**
 * Scroll Fix v2 — track user scroll intent, not position.
 * 
 * Logic:
 * - User scrolls UP at all → set userScrolledUp = true → block ALL auto-scrolls
 * - User sends a message → clear flag → auto-scroll resumes
 * - Page load → allow initial scroll to bottom
 *
 * This is the only reliable approach because image loads change scrollHeight
 * after React's scroll fires, making position-based checks unreliable.
 */
(function() {
  var userScrolledUp = false;
  var initialLoad = true;
  var lastScrollTop = 0;

  // Patch scrollIntoView before React hydrates
  var orig = Element.prototype.scrollIntoView;
  Element.prototype.scrollIntoView = function(opts) {
    // Allow during initial page load
    if (initialLoad) return orig.call(this, opts);
    // Block if user has scrolled up
    if (userScrolledUp) return;
    return orig.call(this, opts);
  };

  // After page loads, start tracking scroll direction
  function initScrollTracking() {
    var container = document.querySelector("[class*=messages]");
    if (!container) { setTimeout(initScrollTracking, 500); return; }

    // Allow initial scroll to bottom
    setTimeout(function() { initialLoad = false; }, 3000);

    lastScrollTop = container.scrollTop;

    container.addEventListener("scroll", function() {
      var st = container.scrollTop;
      var atBottom = container.scrollHeight - st - container.clientHeight < 80;

      if (st < lastScrollTop && !atBottom) {
        // User scrolled UP
        userScrolledUp = true;
      } else if (atBottom) {
        // User scrolled to bottom manually
        userScrolledUp = false;
      }
      lastScrollTop = st;
    });
  }

  // Resume auto-scroll when fan sends a message
  function watchSend() {
    var form = document.querySelector("[class*=inputBar]");
    if (!form) { setTimeout(watchSend, 500); return; }
    form.addEventListener("submit", function() {
      userScrolledUp = false;
      initialLoad = false;
    });
  }

  // Expose for PPV burst integration
  window._chatScrollPause = function() { userScrolledUp = true; };
  window._chatScrollResume = function() { userScrolledUp = false; };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function() {
      initScrollTracking();
      watchSend();
    });
  } else {
    initScrollTracking();
    watchSend();
  }
})();
