/**
 * Chat UX Fixes v1
 * 1. Smart auto-scroll: pause during PPV burst, resume on next fan message
 * 2. Image lightbox: tap any chat image to view fullscreen
 */
(function() {
  // ============ 1. SMART AUTO-SCROLL ============
  var scrollPaused = false;
  var pauseTimer = null;
  var lastMessageCount = 0;

  // Detect PPV unlock burst: when multiple images arrive at once, pause scroll
  function checkForBurst() {
    var container = document.querySelector("[class*=messagesInner]") || document.querySelector("[class*=messages]");
    if (!container) return;

    var msgs = container.querySelectorAll("[class*=message]");
    var currentCount = msgs.length;

    // If 3+ messages appeared since last check, it's a burst
    if (currentCount - lastMessageCount >= 3) {
      scrollPaused = true;
      clearTimeout(pauseTimer);
      // Keep scroll paused until fan interacts
    }
    lastMessageCount = currentCount;
  }

  // Override the React scroll behavior
  function patchAutoScroll() {
    var container = document.querySelector("[class*=messagesInner]") || document.querySelector("[class*=messages]");
    if (!container) return;

    // Watch for scroll attempts and block them during pause
    var origScrollTo = container.scrollTo;
    if (origScrollTo && !container._scrollPatched) {
      container._scrollPatched = true;

      // Intercept scrollIntoView on the scroll sentinel
      var observer = new MutationObserver(function() {
        if (scrollPaused) {
          // Don't auto-scroll — user is browsing burst content
          return;
        }
      });
      observer.observe(container, { childList: true, subtree: true });
    }

    // Patch Element.prototype.scrollIntoView temporarily during burst
    var origScrollIntoView = Element.prototype.scrollIntoView;
    Element.prototype.scrollIntoView = function(opts) {
      // Only block scroll for elements inside the messages container
      if (scrollPaused && container.contains(this)) {
        return; // Suppress auto-scroll during burst
      }
      return origScrollIntoView.call(this, opts);
    };
  }

  // Resume scroll when fan sends a message (input bar submit)
  function watchForFanMessage() {
    var form = document.querySelector("[class*=inputBar]");
    if (form && !form._scrollWatched) {
      form._scrollWatched = true;
      form.addEventListener("submit", function() {
        scrollPaused = false;
      });
    }

    // Also resume on manual scroll to bottom
    var container = document.querySelector("[class*=messages]");
    if (container && !container._scrollEndWatched) {
      container._scrollEndWatched = true;
      container.addEventListener("scroll", function() {
        var atBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 50;
        if (atBottom) {
          scrollPaused = false;
        }
      });
    }
  }

  // Poll for burst detection
  setInterval(checkForBurst, 2000);
  setTimeout(function() {
    patchAutoScroll();
    watchForFanMessage();
  }, 3000);

  // ============ 2. IMAGE LIGHTBOX ============
  var lightboxEl = null;

  function createLightbox() {
    if (lightboxEl) return;

    lightboxEl = document.createElement("div");
    lightboxEl.id = "chat-lightbox";
    lightboxEl.innerHTML =
      '<div id="lightbox-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.92);z-index:99999;display:none;justify-content:center;align-items:center;cursor:pointer;-webkit-tap-highlight-color:transparent">' +
        '<img id="lightbox-img" src="" style="max-width:95vw;max-height:90vh;object-fit:contain;border-radius:4px;user-select:none;-webkit-user-drag:none;pointer-events:none" />' +
        '<div style="position:absolute;top:16px;right:20px;color:white;font-size:28px;font-weight:bold;cursor:pointer;z-index:100000;padding:8px;line-height:1" id="lightbox-close">&times;</div>' +
        '<div style="position:absolute;bottom:16px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,0.5);font-size:12px">Tap anywhere to close</div>' +
      '</div>';

    document.body.appendChild(lightboxEl);

    var overlay = document.getElementById("lightbox-overlay");
    overlay.addEventListener("click", closeLightbox);

    // Close on Escape key
    document.addEventListener("keydown", function(e) {
      if (e.key === "Escape") closeLightbox();
    });
  }

  function openLightbox(src) {
    createLightbox();
    var overlay = document.getElementById("lightbox-overlay");
    var img = document.getElementById("lightbox-img");
    img.src = src;
    overlay.style.display = "flex";
    document.body.style.overflow = "hidden";
  }

  function closeLightbox() {
    var overlay = document.getElementById("lightbox-overlay");
    if (overlay) {
      overlay.style.display = "none";
      document.body.style.overflow = "";
    }
  }

  // Attach click handlers to all chat images
  function attachLightboxHandlers() {
    var container = document.querySelector("[class*=messages]");
    if (!container) return;

    var images = container.querySelectorAll("img");
    images.forEach(function(img) {
      if (img._lightboxAttached) return;
      if (img.className && img.className.indexOf("Avatar") !== -1) return; // Skip avatars
      if (img.width < 60 || img.height < 60) return; // Skip tiny images/icons

      var src = img.getAttribute("src") || "";
      // Only attach to content images (R2 URLs or local uploads), not UI elements
      if (src.indexOf("r2.dev") !== -1 || src.indexOf("/data/") !== -1 || src.indexOf("/uploads/") !== -1 ||
          (img.className && (img.className.indexOf("ppv") !== -1 || img.className.indexOf("Unlocked") !== -1))) {

        img._lightboxAttached = true;
        img.style.cursor = "pointer";
        img.style.pointerEvents = "auto";
        img.addEventListener("click", function(e) {
          e.preventDefault();
          e.stopPropagation();
          // For blurred PPV previews, don't open lightbox
          if (img.className && img.className.indexOf("Blurred") !== -1) return;
          openLightbox(img.src);
        });
      }
    });
  }

  // Re-attach on DOM changes (React re-renders)
  setTimeout(function() {
    attachLightboxHandlers();
    var container = document.querySelector("[class*=messages]");
    if (container) {
      var observer = new MutationObserver(function() {
        setTimeout(attachLightboxHandlers, 200);
      });
      observer.observe(container, { childList: true, subtree: true });
    }
  }, 2000);

  // Also run on load
  window.addEventListener("load", function() {
    setTimeout(attachLightboxHandlers, 1500);
  });
})();
