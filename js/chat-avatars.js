/**
 * Fix chat avatars:
 * - Breyya: always show hero2.jpg
 * - Fan: show first initial of display name in a colored circle
 * - Typing indicator: show Breyya's avatar
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  function fixAvatars() {
    var avatars = document.querySelectorAll('[class*="msgAvatar"]');
    avatars.forEach(function(img) {
      // If it's Breyya's avatar (has src with hero)
      if (img.src && img.src.includes('hero')) {
        img.src = '/images/hero2.jpg';
        img.style.objectFit = 'cover';
        img.style.objectPosition = 'center 15%';
      }
      
      // If it's a fan avatar (empty src or gray background)
      if ((!img.src || img.src === '' || img.src.endsWith('/')) && img.style.background) {
        // Get fan display name from localStorage or default
        var initial = 'B'; // default
        try {
          var user = JSON.parse(localStorage.getItem('breyya_user') || localStorage.getItem('user') || '{}');
          var name = user.display_name || user.email || 'Fan';
          initial = name.charAt(0).toUpperCase();
        } catch(e) {}
        
        // Replace img with a div showing the initial
        var div = document.createElement('div');
        div.className = img.className;
        div.setAttribute('style', 
          'background:linear-gradient(135deg,#00b4d8,#0090b0);' +
          'width:28px;height:28px;border-radius:50%;display:flex;' +
          'align-items:center;justify-content:center;flex-shrink:0;' +
          'color:#fff;font-size:13px;font-weight:700;font-family:inherit;'
        );
        div.textContent = initial;
        if (img.parentNode) img.parentNode.replaceChild(div, img);
      }
    });
    
    // Fix typing indicator avatar
    var typingBubble = document.querySelector('[class*="typingBubble"]');
    if (typingBubble) {
      var parent = typingBubble.closest('[class*="message"]');
      if (parent) {
        var avatar = parent.querySelector('[class*="msgAvatar"]');
        if (avatar && avatar.tagName === 'IMG') {
          avatar.src = '/images/hero2.jpg';
          avatar.style.objectFit = 'cover';
          avatar.style.objectPosition = 'center 15%';
        }
      }
    }
  }

  setInterval(fixAvatars, 500);
  if (document.readyState === 'complete') setTimeout(fixAvatars, 300);
  else window.addEventListener('load', function() { setTimeout(fixAvatars, 300); });
})();
