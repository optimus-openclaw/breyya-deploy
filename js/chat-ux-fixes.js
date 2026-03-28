/**
 * Chat UX Fixes v4
 * 1. Image lightbox: tap ANY chat image to view fullscreen
 * 2. Video lightbox: expand button for fullscreen video
 * 3. Gentle auto-scroll suppression after burst loads
 */
(function() {
  // ============ AUTO-SCROLL SUPPRESSION ============
  var burstDetected = false;
  var lastMsgCount = 0;
  var scrollResumeTimer = null;

  // Patch scrollIntoView (safe — doesn't touch scrollTop)
  var origScrollIntoView = Element.prototype.scrollIntoView;
  Element.prototype.scrollIntoView = function(opts) {
    // If burst detected, suppress auto-scroll inside messages container
    if (burstDetected) {
      var container = document.querySelector("[class*=messages]");
      if (container && container.contains(this)) return;
    }
    return origScrollIntoView.call(this, opts);
  };

  // Detect bursts: 3+ new messages in one poll cycle
  setInterval(function() {
    var container = document.querySelector("[class*=messagesInner]") || document.querySelector("[class*=messages]");
    if (!container) return;
    var msgs = container.querySelectorAll("[class*=message]");
    var count = msgs.length;
    if (count - lastMsgCount >= 3) {
      burstDetected = true;
      clearTimeout(scrollResumeTimer);
      // Auto-resume after 30 seconds of no new bursts
      scrollResumeTimer = setTimeout(function() { burstDetected = false; }, 30000);
    }
    lastMsgCount = count;
  }, 2000);

  // Resume scroll when fan sends a message
  setTimeout(function() {
    var form = document.querySelector("[class*=inputBar]");
    if (form) {
      form.addEventListener("submit", function() {
        burstDetected = false;
        clearTimeout(scrollResumeTimer);
      });
    }
    // Also resume if user scrolls to bottom manually
    var container = document.querySelector("[class*=messages]");
    if (container) {
      container.addEventListener("scroll", function() {
        if (container.scrollHeight - container.scrollTop - container.clientHeight < 60) {
          burstDetected = false;
        }
      });
    }
  }, 2000);

  // ============ IMAGE LIGHTBOX ============
  var lightboxOverlay = null;

  function createLightboxOverlay() {
    if (lightboxOverlay) return lightboxOverlay;
    lightboxOverlay = document.createElement("div");
    lightboxOverlay.id = "img-lightbox";
    lightboxOverlay.style.cssText = "position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.92);z-index:99999;display:none;justify-content:center;align-items:center;cursor:pointer";

    var img = document.createElement("img");
    img.id = "lightbox-main-img";
    img.style.cssText = "max-width:75vw;max-height:80vh;object-fit:contain;border-radius:12px;user-select:none;-webkit-user-drag:none;box-shadow:0 8px 40px rgba(0,0,0,0.6)";

    var closeBtn = document.createElement("div");
    closeBtn.innerHTML = "&times;";
    closeBtn.style.cssText = "position:absolute;top:16px;right:20px;color:white;font-size:32px;font-weight:bold;cursor:pointer;z-index:100000;padding:8px;line-height:1";

    var hint = document.createElement("div");
    hint.textContent = "Tap anywhere to close";
    hint.style.cssText = "position:absolute;bottom:16px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,0.4);font-size:12px";

    lightboxOverlay.appendChild(img);
    lightboxOverlay.appendChild(closeBtn);
    lightboxOverlay.appendChild(hint);
    document.body.appendChild(lightboxOverlay);

    function closeLB() {
      lightboxOverlay.style.display = "none";
      document.body.style.overflow = "";
    }
    lightboxOverlay.addEventListener("click", function(e) {
      if (e.target === lightboxOverlay || e.target === closeBtn || e.target === hint) closeLB();
    });
    document.addEventListener("keydown", function(e) {
      if (e.key === "Escape" && lightboxOverlay.style.display === "flex") closeLB();
    });

    return lightboxOverlay;
  }

  function openImageLightbox(src) {
    var lb = createLightboxOverlay();
    var img = document.getElementById("lightbox-main-img");
    img.src = src;
    lb.style.display = "flex";
    document.body.style.overflow = "hidden";
  }

  // Attach click handlers to ALL images in chat
  function attachImageLightbox() {
    var container = document.querySelector("[class*=messages]");
    if (!container) return;

    // Target ALL images inside the messages area
    var imgs = container.querySelectorAll("img");
    imgs.forEach(function(img) {
      if (img._lbAttached) return;

      // Skip avatars (small, class contains Avatar)
      if (img.className && img.className.indexOf("Avatar") !== -1) return;
      if (img.className && img.className.indexOf("avatar") !== -1) return;
      // Skip tiny images (icons, etc)
      if (img.naturalWidth > 0 && img.naturalWidth < 50) return;

      var src = img.getAttribute("src") || "";
      // Skip placeholder/UI images
      if (!src || src.indexOf("data:") === 0) return;
      // Only attach to content images (R2 URLs, uploads, or PPV images)
      if (src.indexOf("r2.dev") === -1 && src.indexOf("/data/") === -1 && src.indexOf("/uploads/") === -1 && src.indexOf("hero") === -1) {
        // Check if it's inside a PPV card
        if (!img.closest("[class*=ppv]") && !img.closest("[class*=bubble]")) return;
      }
      // Skip blurred locked previews
      if (img.className && img.className.indexOf("Blurred") !== -1) return;

      img._lbAttached = true;
      // Override pointer-events so click works
      img.style.pointerEvents = "auto";
      img.style.cursor = "pointer";

      img.addEventListener("click", function(e) {
        e.preventDefault();
        e.stopPropagation();
        openImageLightbox(img.src);
      });
    });
  }

  // ============ VIDEO LIGHTBOX ============
  function attachVideoLightbox() {
    var container = document.querySelector("[class*=messages]");
    if (!container) return;

    var videos = container.querySelectorAll("video");
    videos.forEach(function(vid) {
      if (vid._vlbAttached) return;
      vid._vlbAttached = true;

      // Wrap in relative container for expand button
      if (!vid.parentNode.querySelector(".vid-expand-btn")) {
        var btn = document.createElement("div");
        btn.className = "vid-expand-btn";
        btn.textContent = "\u26F6";
        btn.style.cssText = "position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.7);color:white;width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;z-index:10;opacity:0.7";
        btn.onmouseenter = function() { this.style.opacity = "1"; };
        btn.onmouseleave = function() { this.style.opacity = "0.7"; };

        btn.addEventListener("click", function(e) {
          e.preventDefault();
          e.stopPropagation();
          var curTime = vid.currentTime;
          vid.pause();
          openVideoLightbox(vid.src, curTime);
        });

        // Ensure parent is positioned
        var parent = vid.parentNode;
        if (getComputedStyle(parent).position === "static") {
          parent.style.position = "relative";
        }
        parent.appendChild(btn);
      }
    });
  }

  function openVideoLightbox(src, startTime) {
    var overlay = document.createElement("div");
    overlay.style.cssText = "position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.95);z-index:99999;display:flex;justify-content:center;align-items:center";

    var video = document.createElement("video");
    video.src = src;
    video.controls = true;
    video.autoplay = true;
    video.playsInline = true;
    video.style.cssText = "max-width:75vw;max-height:80vh;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,0.6)";
    video.currentTime = startTime || 0;

    var closeBtn = document.createElement("div");
    closeBtn.innerHTML = "&times;";
    closeBtn.style.cssText = "position:absolute;top:16px;right:20px;color:white;font-size:32px;font-weight:bold;cursor:pointer;z-index:100000;padding:8px;line-height:1";

    function closeVid() {
      video.pause();
      overlay.remove();
      document.body.style.overflow = "";
    }
    closeBtn.addEventListener("click", closeVid);
    overlay.addEventListener("click", function(e) { if (e.target === overlay) closeVid(); });
    document.addEventListener("keydown", function handler(e) {
      if (e.key === "Escape") { closeVid(); document.removeEventListener("keydown", handler); }
    });

    overlay.appendChild(video);
    overlay.appendChild(closeBtn);
    document.body.appendChild(overlay);
    document.body.style.overflow = "hidden";
  }

  // ============ INIT: Watch for DOM changes ============
  function attachAll() {
    attachImageLightbox();
    attachVideoLightbox();
  }

  setTimeout(function() {
    attachAll();
    var container = document.querySelector("[class*=messages]");
    if (container) {
      new MutationObserver(function() {
        setTimeout(attachAll, 200);
      }).observe(container, { childList: true, subtree: true });
    }
  }, 2000);

  window.addEventListener("load", function() { setTimeout(attachAll, 1500); });
})();
