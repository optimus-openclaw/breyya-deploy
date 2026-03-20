/**
 * Global logout button — shows on member pages and when logged in.
 * Resilient to Next.js hydration — retries injection.
 */
(function() {
  if (window.location.pathname.indexOf('/logout') === 0) return;

  var memberPages = ['/feed', '/chat', '/backstage', '/dashboard'];
  var onMemberPage = memberPages.some(function(p) {
    return window.location.pathname.indexOf(p) === 0;
  });
  var hasLocalToken = false;
  try { hasLocalToken = !!localStorage.getItem('token'); } catch(e) {}

  function injectButton() {
    if (document.getElementById('global-logout-btn')) return;
    var btn = document.createElement('a');
    btn.id = 'global-logout-btn';
    btn.href = '/logout/';
    btn.textContent = 'Log out';
    btn.setAttribute('style',
      'position:fixed !important;top:56px !important;right:16px !important;z-index:99999 !important;' +
      'background:rgba(19,36,58,0.95) !important;color:#7a93a8 !important;' +
      'font-family:"DM Sans","Inter",-apple-system,sans-serif !important;' +
      'font-size:13px !important;font-weight:600 !important;padding:8px 16px !important;' +
      'border-radius:20px !important;text-decoration:none !important;' +
      'border:1px solid rgba(122,147,168,0.3) !important;' +
      'backdrop-filter:blur(8px) !important;-webkit-backdrop-filter:blur(8px) !important;' +
      'display:block !important;visibility:visible !important;opacity:1 !important;'
    );
    btn.onmouseover = function() { btn.style.color='#00b4d8'; btn.style.borderColor='#00b4d8'; };
    btn.onmouseout = function() { btn.style.color='#7a93a8'; btn.style.borderColor='rgba(122,147,168,0.3)'; };
    document.body.appendChild(btn);
  }

  function tryShow() {
    if (onMemberPage || hasLocalToken) {
      injectButton();
      // Re-inject every 2 seconds in case React hydration removes it
      setInterval(function() { injectButton(); }, 2000);
      return;
    }
    fetch('/api/auth/me.php', { credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d && d.ok && d.user) {
          injectButton();
          setInterval(function() { injectButton(); }, 2000);
        }
      })
      .catch(function() {});
  }

  // Wait for DOM to be fully ready, then inject after a delay
  if (document.readyState === 'complete') {
    setTimeout(tryShow, 500);
  } else {
    window.addEventListener('load', function() { setTimeout(tryShow, 500); });
  }
})();
