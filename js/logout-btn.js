/**
 * Global logout button — appears on ALL pages when user is logged in
 * OR on any member-area page (feed, chat, backstage, dashboard).
 * Fixed position, top-right corner.
 */
(function() {
  // Don't show on logout page itself
  if (window.location.pathname.indexOf('/logout') === 0) return;

  // Member-area pages always show logout (you wouldn't be here if not logged in)
  var memberPages = ['/feed', '/chat', '/backstage', '/dashboard'];
  var onMemberPage = memberPages.some(function(p) {
    return window.location.pathname.indexOf(p) === 0;
  });

  // Check localStorage token
  var hasLocalToken = false;
  try { hasLocalToken = !!localStorage.getItem('token'); } catch(e) {}

  function showButton() {
    // Prevent duplicates
    if (document.getElementById('global-logout-btn')) return;
    var btn = document.createElement('a');
    btn.id = 'global-logout-btn';
    btn.href = '/logout/';
    btn.textContent = 'Log out';
    btn.style.cssText = 'position:fixed;top:12px;right:16px;z-index:9999;' +
      'background:rgba(19,36,58,0.95);color:#7a93a8;font-family:"DM Sans","Inter",-apple-system,sans-serif;' +
      'font-size:13px;font-weight:600;padding:8px 16px;border-radius:20px;' +
      'text-decoration:none;border:1px solid rgba(122,147,168,0.3);' +
      'backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);' +
      'transition:color 0.2s,border-color 0.2s;';
    btn.onmouseover = function() { btn.style.color='#00b4d8'; btn.style.borderColor='#00b4d8'; };
    btn.onmouseout = function() { btn.style.color='#7a93a8'; btn.style.borderColor='rgba(122,147,168,0.3)'; };
    document.body.appendChild(btn);
  }

  // Show immediately on member pages or if localStorage has token
  if (onMemberPage || hasLocalToken) { showButton(); return; }

  // For other pages, check cookie-based auth via API
  fetch('/api/auth/me.php', { credentials: 'include' })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d && d.ok && d.user) showButton(); })
    .catch(function() {});
})();
