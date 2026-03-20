/**
 * Shows "Backstage" button on all pages for admin/creator users only.
 * Fixed position, top-left corner. Moves down on chat page to avoid header overlap.
 */
(function() {
  if (window.location.pathname.indexOf('/backstage') === 0) return;
  if (window.location.pathname.indexOf('/admin') === 0) return;

  var isChat = window.location.pathname.indexOf('/chat') === 0;

  function showBackstageBtn() {
    if (document.getElementById('admin-backstage-btn')) return;
    var btn = document.createElement('a');
    btn.id = 'admin-backstage-btn';
    btn.href = '/backstage/';
    btn.textContent = '🔒 Backstage';
    var topPos = isChat ? '52px' : '12px';
    btn.setAttribute('style',
      'position:fixed !important;top:' + topPos + ' !important;left:16px !important;z-index:99999 !important;' +
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
    try {
      var user = JSON.parse(localStorage.getItem('breyya_user') || '{}');
      if (user.role === 'creator' || user.role === 'admin') { showBackstageBtn(); return; }
    } catch(e) {}
    try {
      var user2 = JSON.parse(localStorage.getItem('user') || '{}');
      if (user2.role === 'creator' || user2.role === 'admin') { showBackstageBtn(); return; }
    } catch(e) {}
    fetch('/api/auth/me.php', { credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d && d.ok && d.user && (d.user.role === 'creator' || d.user.role === 'admin')) showBackstageBtn();
      }).catch(function() {});
  }

  if (document.readyState === 'complete') { setTimeout(checkAdmin, 300); }
  else { window.addEventListener('load', function() { setTimeout(checkAdmin, 300); }); }
  setInterval(checkAdmin, 3000);
})();
