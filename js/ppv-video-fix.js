/**
 * PPV Video Fix v1 — converts <img> tags pointing to video files 
 * into <video> elements within PPV cards. Runs after React renders.
 */
(function() {
  function fixPPVVideos() {
    // Find all PPV image elements
    var imgs = document.querySelectorAll("[class*=ppvImage]");
    imgs.forEach(function(img) {
      var src = img.getAttribute("src") || "";
      if (src.match(/\.(mp4|mov|webm|avi)(\?|$)/i)) {
        // Replace img with video
        var video = document.createElement("video");
        video.src = src;
        video.controls = true;
        video.playsInline = true;
        video.preload = "metadata";
        video.className = img.className;
        video.style.maxWidth = "100%";
        video.style.borderRadius = "10px";
        video.oncontextmenu = function(e) { e.preventDefault(); };
        img.parentNode.replaceChild(video, img);
      }
    });
    
    // Also fix any ppv-unlocked images in the ppv-chat.js system
    var ppvImgs = document.querySelectorAll(".ppv-unlocked-image");
    ppvImgs.forEach(function(img) {
      var src = img.getAttribute("src") || "";
      if (src.match(/\.(mp4|mov|webm|avi)(\?|$)/i)) {
        var video = document.createElement("video");
        video.src = src;
        video.controls = true;
        video.playsInline = true;
        video.className = "ppv-unlocked-video";
        video.style.maxWidth = "300px";
        video.style.borderRadius = "10px";
        img.parentNode.replaceChild(video, img);
      }
    });
  }

  // Run on load and on every DOM mutation (React re-renders)
  if (document.readyState === "complete") {
    setTimeout(fixPPVVideos, 500);
  } else {
    window.addEventListener("load", function() { setTimeout(fixPPVVideos, 500); });
  }

  var observer = new MutationObserver(function() {
    setTimeout(fixPPVVideos, 100);
  });

  setTimeout(function() {
    var container = document.querySelector("[class*=messages]");
    if (container) {
      observer.observe(container, { childList: true, subtree: true });
    }
  }, 2000);
})();
