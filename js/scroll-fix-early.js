/**
 * Scroll Fix v3 — Nuclear option.
 * 
 * 1. Reserve min-height on all chat images so layout doesn't shift
 * 2. Track scroll direction — ANY upward scroll = freeze
 * 3. Block BOTH scrollIntoView AND scrollTop setter on messages container
 * 4. Only resume on: fan sends message OR manually scrolls to very bottom
 */
(function() {
  var frozen = false;
  var initialLoad = true;
  var lastScrollTop = 0;

  // === PATCH scrollIntoView immediately (before React) ===
  var origSIV = Element.prototype.scrollIntoView;
  Element.prototype.scrollIntoView = function(opts) {
    if (initialLoad) return origSIV.call(this, opts);
    if (frozen) return;
    return origSIV.call(this, opts);
  };

  // === INJECT CSS: reserve space for images ===
  var style = document.createElement("style");
  style.textContent = [
    // All chat media images get min-height to prevent layout shift
    "[class*=messages] img[src*='r2.dev'] { min-height: 280px; object-fit: contain; }",
    "[class*=messages] img[src*='/data/'] { min-height: 280px; object-fit: contain; }",
    "[class*=messages] img[src*='/uploads/'] { min-height: 280px; object-fit: contain; }",
    // PPV images already have aspect-ratio from ppvImageWrap
    "[class*=ppvImageWrap] img { min-height: auto; }"
  ].join("\n");
  document.head.appendChild(style);

  function setup() {
    var container = document.querySelector("[class*=messages]");
    if (!container) { setTimeout(setup, 300); return; }

    // Allow initial scroll, then lock down
    setTimeout(function() { initialLoad = false; }, 3000);

    // === PATCH scrollTop on this specific container ===
    var desc = Object.getOwnPropertyDescriptor(HTMLElement.prototype, "scrollTop") ||
               Object.getOwnPropertyDescriptor(Element.prototype, "scrollTop");
    if (desc && desc.set) {
      var origSet = desc.set;
      var origGet = desc.get;
      Object.defineProperty(container, "scrollTop", {
        get: function() { return origGet.call(this); },
        set: function(val) {
          if (initialLoad) return origSet.call(this, val);
          if (frozen) return; // Block programmatic scroll
          return origSet.call(this, val);
        },
        configurable: true
      });
    }

    // === Track scroll direction ===
    lastScrollTop = container.scrollTop;
    container.addEventListener("scroll", function() {
      // Use the raw getter to avoid our override
      var st = desc ? desc.get.call(container) : container.scrollTop;
      var distFromBottom = container.scrollHeight - st - container.clientHeight;

      if (st < lastScrollTop - 5) {
        // Scrolled UP — freeze
        frozen = true;
      }
      if (distFromBottom < 30) {
        // Manually reached bottom — unfreeze
        frozen = false;
      }
      lastScrollTop = st;
    }, { passive: true });

    // === Resume on message send ===
    var form = document.querySelector("[class*=inputBar]");
    if (form) {
      form.addEventListener("submit", function() {
        frozen = false;
        // Scroll to bottom after a tick
        setTimeout(function() {
          if (desc && desc.set) {
            desc.set.call(container, container.scrollHeight);
          }
          var sentinel = container.querySelector("[class*=messagesInner] > div:last-child");
          if (sentinel) origSIV.call(sentinel, { behavior: "smooth" });
        }, 100);
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", setup);
  } else {
    setup();
  }
})();
