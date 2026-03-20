/**
 * Fix ALL chat avatars:
 * - Breyya = hero2.jpg (blue bikini) — left side
 * - Fan = first initial in cyan circle — right side
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  // Get fan initial
  var fanInitial = '?';
  try {
    var u = JSON.parse(localStorage.getItem('breyya_user') || localStorage.getItem('user') || '{}');
    var name = u.display_name || u.email || '';
    if (name) fanInitial = name.charAt(0).toUpperCase();
  } catch(e) {}

  function fixAvatars() {
    // Find all message groups
    var messages = document.querySelectorAll('[class*="message"]');
    messages.forEach(function(msg) {
      var isSent = msg.className.includes('sent');
      var isReceived = msg.className.includes('received');
      var imgs = msg.querySelectorAll('img[class*="msgAvatar"]');
      
      imgs.forEach(function(img) {
        if (img.dataset.fixed) return;

        if (isReceived) {
          // Breyya's avatar (left side)
          img.src = '/images/hero2.jpg';
          img.style.objectFit = 'cover';
          img.style.objectPosition = 'center 15%';
          img.dataset.fixed = '1';
        } else if (isSent || (!img.src || img.src === '' || img.src === window.location.origin + '/')) {
          // Fan avatar — replace with initial div
          var div = document.createElement('div');
          div.className = img.className;
          div.dataset.fixed = '1';
          div.style.cssText = 
            'background:linear-gradient(135deg,#00b4d8,#0090b0);' +
            'width:28px;height:28px;min-width:28px;border-radius:50%;display:flex;' +
            'align-items:center;justify-content:center;flex-shrink:0;' +
            'color:#fff;font-size:13px;font-weight:700;';
          div.textContent = fanInitial;
          img.parentNode.replaceChild(div, img);
        }
      });
    });

    // Fix header avatar
    var headerAvatar = document.querySelector('[class*="headerAvatar"]');
    if (headerAvatar && headerAvatar.tagName === 'IMG') {
      headerAvatar.src = '/images/hero2.jpg';
    }
  }

  setInterval(fixAvatars, 400);
  if (document.readyState === 'complete') setTimeout(fixAvatars, 500);
  else window.addEventListener('load', function() { setTimeout(fixAvatars, 500); });
})();
