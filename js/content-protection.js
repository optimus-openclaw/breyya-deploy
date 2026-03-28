/**
 * Content protection — prevents downloading/saving images and videos.
 * Blocks right-click context menu on media elements.
 * Blocks drag-and-drop saving.
 */
(function() {
  // Block right-click on images and videos
  document.addEventListener('contextmenu', function(e) {
    var target = e.target;
    if (target.tagName === 'IMG' || target.tagName === 'VIDEO' || 
        target.closest('[class*="postMedia"]') || target.closest('[class*="ppv"]')) {
      e.preventDefault();
      return false;
    }
  });

  // Block drag on images
  document.addEventListener('dragstart', function(e) {
    if (e.target.tagName === 'IMG' || e.target.tagName === 'VIDEO') {
      e.preventDefault();
      return false;
    }
  });

  // Block long-press save on mobile
  document.addEventListener('touchstart', function(e) {
    if (e.target.tagName === 'IMG') {
      e.target.style.webkitTouchCallout = 'none';
    }
  });

  // Add CSS protection
  var style = document.createElement('style');
  style.textContent = 'img { -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; pointer-events: none; } video { -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; } [class*="postMedia"] { -webkit-user-select: none; user-select: none; }';
  document.head.appendChild(style);
})();

// Block Ctrl+S / Cmd+S (Save Page)
document.addEventListener('keydown', function(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault();
    return false;
  }
});
