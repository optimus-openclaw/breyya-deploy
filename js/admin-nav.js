/**
 * Admin nav: Backstage + Home buttons on ALL pages for admin/creator only.
 * Mobile-aware positioning — never overlaps page content.
 */
(function() {
  var path = window.location.pathname;

  // Detect page type for positioning
  var isChat = path.indexOf('/chat') === 0;
  var isFeed = path.indexOf('/feed') === 0;
  var isCreatorDash = path === '/dashboard' || path === '/dashboard/';
  var isBackstageDash = path.indexOf('/backstage/dashboard') === 0;
  var isBackstageInv = path.indexOf('/backstage/inventory') === 0;
  var isBackstageHub = path === '/backstage' || path === '/backstage/';
  var isBackstageUpload = path.indexOf('/backstage/upload') === 0;
  var isMobile = window.innerWidth <= 768;

  function getTopPosition() {
    if (isChat) return isMobile ? '48px' : '52px';
    if (isFeed) return isMobile ? '100px' : '12px'; // below creator header on mobile
    if (isCreatorDash) return isMobile ? '90px' : '56px'; // below dashboard header
    if (isBackstageDash || isBackstageInv) return isMobile ? '48px' : '56px'; // below back nav
    if (isBackstageHub) return isMobile ? '12px' : '12px';
    if (isBackstageUpload) return '12px';
    return '12px';
  }

  function showAdminNav() {
    if (document.getElementById('admin-nav-wrap')) return;

    var wrap = document.createElement('div');
    wrap.id = 'admin-nav-wrap';
    var topPos = getTopPosition();
    
    // On mobile: make buttons smaller, use full width bar style
    if (isMobile) {
      wrap.setAttribute('style',
        'position:fixed !important;top:' + topPos + ' !important;left:0 !important;right:0 !important;' +
        'z-index:99999 !important;display:flex !important;justify-content:center !important;gap:8px !important;' +
        'padding:6px 12px !important;background:rgba(10,22,40,0.95) !important;' +
        'border-bottom:1px solid rgba(255,255,255,0.06) !important;' +
        'backdrop-filter:blur(12px) !important;-webkit-backdrop-filter:blur(12px) !important;'
      );
    } else {
      wrap.setAttribute('style',
        'position:fixed !important;top:' + topPos + ' !important;left:16px !important;z-index:99999 !important;' +
        'display:flex !important;gap:8px !important;'
      );
    }

    var fontSize = isMobile ? '11px' : '13px';
    var padding = isMobile ? '5px 12px' : '8px 16px';

    var btnBase = 
      'font-family:"DM Sans","Inter",-apple-system,sans-serif;' +
      'font-size:' + fontSize + ';font-weight:600;padding:' + padding + ';' +
      'border-radius:16px;text-decoration:none;display:inline-block;';

    var bs = document.createElement('a');
    bs.href = '/backstage/';
    bs.textContent = '🔒 Backstage';
    bs.setAttribute('style', btnBase + 'color:#e91e8c;border:1px solid rgba(233,30,140,0.3);background:rgba(19,36,58,0.9);');

    var hm = document.createElement('a');
    hm.href = '/feed/';
    hm.textContent = '🏠 Home';
    hm.setAttribute('style', btnBase + 'color:#00b4d8;border:1px solid rgba(0,180,216,0.3);background:rgba(19,36,58,0.9);');

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

  // Listen for resize to handle orientation changes
  window.addEventListener('resize', function() {
    var el = document.getElementById('admin-nav-wrap');
    if (el) { el.remove(); }
    isMobile = window.innerWidth <= 768;
    checkAdmin();
  });

  if (document.readyState === 'complete') { setTimeout(checkAdmin, 500); }
  else { window.addEventListener('load', function() { setTimeout(checkAdmin, 500); }); }
  setInterval(checkAdmin, 3000);

  // Hide built-in backstage "Log Out" button
  if (path.indexOf('/backstage') === 0) {
    setInterval(function() {
      var btns = document.querySelectorAll('button');
      btns.forEach(function(b) {
        if (b.textContent.trim() === 'Log Out' && !b.closest('#admin-nav-wrap')) {
          b.style.display = 'none';
        }
      });
    }, 1000);
  }
})();
