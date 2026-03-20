/**
 * Global logout button — appears on ALL pages when user is logged in.
 * Fixed position, top-right corner.
 * Include via: <script src="/js/logout-btn.js" defer></script>
 */
(function() {
  // Only show if user has a token (is logged in)
  var token = null;
  try { token = localStorage.getItem('token'); } catch(e) {}
  if (!token) return;

  // Don't show on logout page itself
  if (window.location.pathname.indexOf('/logout') === 0) return;

  var btn = document.createElement('a');
  btn.href = '/logout/';
  btn.textContent = 'Log out';
  btn.style.cssText = 'position:fixed;top:12px;right:16px;z-index:9999;' +
    'background:rgba(19,36,58,0.9);color:#7a93a8;font-family:"DM Sans",-apple-system,sans-serif;' +
    'font-size:13px;font-weight:600;padding:8px 16px;border-radius:20px;' +
    'text-decoration:none;border:1px solid rgba(122,147,168,0.2);' +
    'backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);' +
    'transition:color 0.2s,border-color 0.2s;';
  btn.onmouseover = function() { btn.style.color='#00b4d8'; btn.style.borderColor='#00b4d8'; };
  btn.onmouseout = function() { btn.style.color='#7a93a8'; btn.style.borderColor='rgba(122,147,168,0.2)'; };
  
  document.body.appendChild(btn);
})();
