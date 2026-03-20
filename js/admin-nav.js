/**
 * Admin nav: Backstage + Home buttons on ALL pages for admin/creator only.
 * On chat page: injects into the header bar. On other pages: fixed position.
 */
(function() {
  var path = window.location.pathname;
  var isChat = path.indexOf('/chat') === 0;
  var hasHeader = path.indexOf('/dashboard') === 0 || 
                  path.indexOf('/backstage/dashboard') === 0 ||
                  path.indexOf('/backstage/inventory') === 0;

  var btnStyle = 
    'font-family:"DM Sans","Inter",-apple-system,sans-serif;' +
    'font-size:11px;font-weight:600;padding:5px 12px;' +
    'border-radius:16px;text-decoration:none;white-space:nowrap;';

  function createBtns() {
    var bs = document.createElement('a');
    bs.href = '/backstage/';
    bs.textContent = '🔒 Backstage';
    bs.setAttribute('style', btnStyle + 'color:#e91e8c;border:1px solid rgba(233,30,140,0.3);background:rgba(19,36,58,0.8);');
    
    var hm = document.createElement('a');
    hm.href = '/feed/';
    hm.textContent = '🏠 Home';
    hm.setAttribute('style', btnStyle + 'color:#00b4d8;border:1px solid rgba(0,180,216,0.3);background:rgba(19,36,58,0.8);margin-left:6px;');
    
    return [bs, hm];
  }

  function injectChat() {
    if (document.getElementById('admin-nav-wrap')) return;
    // Find the chat header's navLinks area or the header itself
    var header = document.querySelector('[class*="header"]');
    if (!header) return;
    
    var wrap = document.createElement('div');
    wrap.id = 'admin-nav-wrap';
    wrap.setAttribute('style', 'display:flex;align-items:center;gap:6px;margin-left:auto;margin-right:8px;');
    var btns = createBtns();
    wrap.appendChild(btns[0]);
    wrap.appendChild(btns[1]);
    
    // Insert before the last child (test fan btn area)
    var testFanBtn = header.querySelector('[class*="testFan"]');
    if (testFanBtn) {
      header.insertBefore(wrap, testFanBtn);
    } else {
      header.appendChild(wrap);
    }
  }

  function injectFixed() {
    if (document.getElementById('admin-nav-wrap')) return;
    var wrap = document.createElement('div');
    wrap.id = 'admin-nav-wrap';
    var topPos = hasHeader ? '56px' : '12px';
    wrap.setAttribute('style',
      'position:fixed !important;top:' + topPos + ' !important;left:16px !important;z-index:99999 !important;' +
      'display:flex !important;gap:8px !important;'
    );
    var btns = createBtns();
    // Make fixed buttons slightly bigger
    btns[0].setAttribute('style', btns[0].getAttribute('style').replace('font-size:11px','font-size:13px').replace('padding:5px 12px','padding:8px 16px'));
    btns[1].setAttribute('style', btns[1].getAttribute('style').replace('font-size:11px','font-size:13px').replace('padding:5px 12px','padding:8px 16px'));
    wrap.appendChild(btns[0]);
    wrap.appendChild(btns[1]);
    document.body.appendChild(wrap);
  }

  function showAdminNav() {
    if (isChat) { injectChat(); }
    else { injectFixed(); }
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

  if (document.readyState === 'complete') { setTimeout(checkAdmin, 500); }
  else { window.addEventListener('load', function() { setTimeout(checkAdmin, 500); }); }
  setInterval(checkAdmin, 3000);
})();

// Hide the built-in "Log Out" button on backstage hub (we have our own global one)
(function() {
  if (window.location.pathname.indexOf('/backstage') !== 0) return;
  var style = document.createElement('style');
  style.textContent = 'button[style*="background:none"][style*="border:1px solid #333"] { display:none !important; }';
  document.head.appendChild(style);
  // Also try to find and hide it after React renders
  setInterval(function() {
    var btns = document.querySelectorAll('button');
    btns.forEach(function(b) {
      if (b.textContent.trim() === 'Log Out' && !b.closest('#admin-nav-wrap') && !b.closest('#global-logout-btn')) {
        b.style.display = 'none';
      }
    });
  }, 1000);
})();
