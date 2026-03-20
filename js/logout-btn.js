/**
 * Global logout button — appears on ALL pages when user is logged in.
 * Fixed position, top-right corner.
 * Checks both localStorage token AND httpOnly cookie (via API ping).
 */
(function() {
  // Don't show on logout page itself
  if (window.location.pathname.indexOf('/logout') === 0) return;

  function showButton() {
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
  }

  // Check 1: localStorage token (fan login via /login page)
  var hasLocalToken = false;
  try { hasLocalToken = !!localStorage.getItem('token'); } catch(e) {}
  if (hasLocalToken) { showButton(); return; }

  // Check 2: httpOnly cookie auth (admin/backstage login)
  // Ping the auth endpoint — if it returns a user, we're logged in via cookie
  fetch('/api/auth/me.php', { credentials: 'include' })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d && d.ok && d.user) showButton();
    })
    .catch(function() {});
})();
