/**
 * Shows "Backstage" button on all pages for admin/creator users only.
 * Fixed position, top-left corner.
 */
(function() {
  if (window.location.pathname.indexOf('/backstage') === 0) return;
  if (window.location.pathname.indexOf('/admin') === 0) return;

  function showBackstageBtn() {
    if (document.getElementById('admin-backstage-btn')) return;
    var btn = document.createElement('a');
    btn.id = 'admin-backstage-btn';
    btn.href = '/backstage/';
    btn.textContent = '🔒 Backstage';
    btn.setAttribute('style',
      'position:fixed !important;top:12px !important;left:16px !important;z-index:99999 !important;' +
      'background:rgba(19,36,58,0.95) !important;color:#e91e8c !important;' +
      'font-family:"DM Sans","Inter",-apple-system,sans-serif !important;' +
      'font-size:13px !important;font-weight:600 !important;padding:8px 16px !important;' +
      'border-radius:20px !important;text-decoration:none !important;' +
      'border:1px solid rgba(233,30,140,0.3) !important;' +
      'backdrop-filter:blur(8px) !important;-webkit-backdrop-filter:blur(8px) !important;' +
      'display:block !important;visibility:visible !important;opacity:1 !important;'
    );
    btn.onmouseover = function() { btn.style.borderColor='#e91e8c'; };
    btn.onmouseout = function() { btn.style.borderColor='rgba(233,30,140,0.3)'; };
    document.body.appendChild(btn);
  }

  function checkAdmin() {
    // Check localStorage for breyya_user (backstage login)
    try {
      var user = JSON.parse(localStorage.getItem('breyya_user') || '{}');
      if (user.role === 'creator' || user.role === 'admin') {
        showBackstageBtn();
        return;
      }
    } catch(e) {}

    // Check localStorage for user (login page login)
    try {
      var user2 = JSON.parse(localStorage.getItem('user') || '{}');
      if (user2.role === 'creator' || user2.role === 'admin') {
        showBackstageBtn();
        return;
      }
    } catch(e) {}

    // Check cookie-based auth
    fetch('/api/auth/me.php', { credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d && d.ok && d.user && (d.user.role === 'creator' || d.user.role === 'admin')) {
          showBackstageBtn();
        }
      })
      .catch(function() {});
  }

  if (document.readyState === 'complete') {
    setTimeout(checkAdmin, 300);
  } else {
    window.addEventListener('load', function() { setTimeout(checkAdmin, 300); });
  }

  // Re-check periodically in case of React hydration
  setInterval(function() {
    checkAdmin();
  }, 3000);
})();
