/**
 * Injects Gallery tab into bottom nav on feed and chat pages.
 * Runs after React hydration to ensure it sticks.
 */
(function() {
  var path = window.location.pathname;
  var isFeed = path.indexOf('/feed') === 0;
  var isChat = path.indexOf('/chat') === 0;
  var isGallery = path.indexOf('/gallery') === 0;
  
  if (!isFeed && !isChat && !isGallery) return;

  function injectGalleryTab() {
    if (document.getElementById('gallery-nav-tab')) return;
    
    // Find the bottom nav
    var navs = document.querySelectorAll('nav[style*="position:fixed"][style*="bottom:0"], nav[style*="bottom: 0"]');
    var bottomNav = null;
    for (var i = 0; i < navs.length; i++) {
      var style = navs[i].getAttribute('style') || '';
      if (style.includes('bottom') && style.includes('fixed')) {
        bottomNav = navs[i];
        break;
      }
    }
    if (!bottomNav) return;

    // Check if Gallery already exists
    var links = bottomNav.querySelectorAll('a');
    for (var j = 0; j < links.length; j++) {
      if (links[j].href && links[j].href.includes('/gallery')) return;
    }

    // Find the chat link to insert before it
    var chatLink = null;
    for (var k = 0; k < links.length; k++) {
      if (links[k].href && links[k].href.includes('/chat')) {
        chatLink = links[k];
        break;
      }
    }
    if (!chatLink) return;

    // Create Gallery tab
    var galleryLink = document.createElement('a');
    galleryLink.id = 'gallery-nav-tab';
    galleryLink.href = '/gallery/';
    var activeColor = isGallery ? '#00b4ff' : '#556677';
    galleryLink.setAttribute('style',
      'display:flex;flex-direction:column;align-items:center;gap:2px;' +
      'color:' + activeColor + ';text-decoration:none;font-size:11px;padding:4px 12px;' +
      (isGallery ? 'font-weight:600;' : '')
    );
    galleryLink.innerHTML = 
      '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
        '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>' +
        '<rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>' +
      '</svg>' +
      '<span' + (isGallery ? ' style="font-weight:600;color:#00b4ff"' : '') + '>Gallery</span>';

    // Insert before chat link
    bottomNav.insertBefore(galleryLink, chatLink);
  }

  // Run after React hydration
  if (document.readyState === 'complete') setTimeout(injectGalleryTab, 500);
  else window.addEventListener('load', function() { setTimeout(injectGalleryTab, 500); });
  
  // Keep checking in case React re-renders
  setInterval(injectGalleryTab, 2000);
})();
