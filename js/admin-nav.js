/**
 * Admin nav: Backstage + Home buttons on all pages for admin/creator only.
 */
(function() {
  var path = window.location.pathname;
  if (path.indexOf('/backstage') === 0 || path.indexOf('/admin') === 0) return;

  var isChat = path.indexOf('/chat') === 0;
  var isDashboard = path.indexOf('/dashboard') === 0;

  function showAdminNav() {
    if (document.getElementById('admin-nav-wrap')) return;

    var wrap = document.createElement('div');
    wrap.id = 'admin-nav-wrap';
    var topPos = isChat ? '52px' : isDashboard ? '56px' : '12px';
    wrap.setAttribute('style',
      'position:fixed !important;top:' + topPos + ' !important;left:16px !important;z-index:99999 !important;' +
      'display:flex !important;gap:8px !important;'
    );

    var btnStyle = 
      'background:rgba(19,36,58,0.95) !important;' +
      'font-family:"DM Sans","Inter",-apple-system,sans-serif !important;' +
      'font-size:13px !important;font-weight:600 !important;padding:8px 16px !important;' +
      'border-radius:20px !important;text-decoration:none !important;' +
      'backdrop-filter:blur(8px) !important;-webkit-backdrop-filter:blur(8px) !important;' +
      'display:block !important;visibility:visible !important;opacity:1 !important;';

    // Backstage button
    var bs = document.createElement('a');
    bs.href = '/backstage/';
    bs.textContent = '🔒 Backstage';
    bs.setAttribute('style', btnStyle + 'color:#e91e8c !important;border:1px solid rgba(233,30,140,0.3) !important;');
    bs.onmouseover = function() { bs.style.borderColor='#e91e8c'; };
    bs.onmouseout = function() { bs.style.borderColor='rgba(233,30,140,0.3)'; };

    // Home button
    var hm = document.createElement('a');
    hm.href = '/feed/';
    hm.textContent = '🏠 Home';
    hm.setAttribute('style', btnStyle + 'color:#00b4d8 !important;border:1px solid rgba(0,180,216,0.3) !important;');
    hm.onmouseover = function() { hm.style.borderColor='#00b4d8'; };
    hm.onmouseout = function() { hm.style.borderColor='rgba(0,180,216,0.3)'; };

    wrap.appendChild(bs);
    wrap.appendChild(hm);
    document.body.appendChild(wrap);
  }

  function checkAdmin() {
    try {
      var user = JSON.parse(localStorage.getItem('breyya_user') || '{}');
      if (user.role === 'creator' || user.role === 'admin') { showAdminNav(); return; }
    } catch(e) {}
    try {
      var user2 = JSON.parse(localStorage.getItem('user') || '{}');
      if (user2.role === 'creator' || user2.role === 'admin') { showAdminNav(); return; }
    } catch(e) {}
    fetch('/api/auth/me.php', { credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d && d.ok && d.user && (d.user.role === 'creator' || d.user.role === 'admin')) showAdminNav();
      }).catch(function() {});
  }

  if (document.readyState === 'complete') { setTimeout(checkAdmin, 300); }
  else { window.addEventListener('load', function() { setTimeout(checkAdmin, 300); }); }
  setInterval(checkAdmin, 3000);
})();
