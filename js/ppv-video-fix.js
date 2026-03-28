/**
 * PPV Video Fix v2 — converts <img> tags pointing to video files
 * into <video> elements within PPV cards. Fixes pointer-events and CSS inheritance.
 */
(function() {
  function fixPPVVideos() {
    // Find all PPV image elements (React renders videos as <img>)
    var imgs = document.querySelectorAll("[class*=ppvImage]");
    imgs.forEach(function(img) {
      if (img.tagName === "VIDEO") return; // Already fixed
      var src = img.getAttribute("src") || "";
      if (src.match(/\.(mp4|mov|webm|avi)(\?|$)/i)) {
        var video = document.createElement("video");
        video.src = src;
        video.controls = true;
        video.playsInline = true;
        video.preload = "metadata";
        // Keep the class for sizing but override blocking CSS
        video.className = img.className;
        video.style.pointerEvents = "auto";
        video.style.userSelect = "auto";
        video.style.webkitUserDrag = "auto";
        video.style.maxWidth = "100%";
        video.style.borderRadius = "10px";
        video.style.filter = "none";
        video.style.transform = "none";
        img.parentNode.replaceChild(video, img);
      }
    });

    // Fix any generic chat media images that are actually videos
    var allImgs = document.querySelectorAll("[class*=messages] img");
    allImgs.forEach(function(img) {
      if (img._videoFixed) return;
      var src = img.getAttribute("src") || "";
      if (src.match(/\.(mp4|mov|webm|avi)(\?|$)/i)) {
        img._videoFixed = true;
        var video = document.createElement("video");
        video.src = src;
        video.controls = true;
        video.playsInline = true;
        video.preload = "metadata";
        video.style.maxWidth = "100%";
        video.style.maxHeight = "300px";
        video.style.borderRadius = "8px";
        video.style.display = "block";
        video.style.pointerEvents = "auto";
        img.parentNode.replaceChild(video, img);
      }
    });
  }

  // Run on load and watch for React re-renders
  function start() {
    fixPPVVideos();
    var container = document.querySelector("[class*=messages]");
    if (container) {
      new MutationObserver(function() {
        setTimeout(fixPPVVideos, 150);
      }).observe(container, { childList: true, subtree: true });
    }
  }

  if (document.readyState === "complete") setTimeout(start, 500);
  else window.addEventListener("load", function() { setTimeout(start, 500); });
})();
