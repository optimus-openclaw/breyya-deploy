/**
 * Dashboard stats — populates Overview, Earnings, and Subscribers tabs
 */
(function() {
  if (window.location.pathname.indexOf('/dashboard') !== 0) return;

  function getToken() {
    try { return localStorage.getItem('breyya_token') || localStorage.getItem('token') || ''; }
    catch(e) { return ''; }
  }
  function authHeaders() {
    var t = getToken();
    var h = { 'Content-Type': 'application/json' };
    if (t) h['Authorization'] = 'Bearer ' + t;
    return h;
  }
  function fmt$(cents) { return '$' + (cents / 100).toFixed(2); }

  // Watch for tab switches and inject content
  var lastTab = '';
  setInterval(function() {
    // Detect active tab from sidebar
    var activeLink = document.querySelector('a[style*="border-left"][style*="00b4ff"], a[style*="color: rgb(0, 180, 255)"], [class*="active"]');
    var sideLinks = document.querySelectorAll('nav a, aside a');
    var currentTab = '';
    
    sideLinks.forEach(function(a) {
      var style = window.getComputedStyle(a);
      if (style.borderLeftColor === 'rgb(0, 180, 255)' || style.color === 'rgb(0, 180, 255)' || a.style.borderLeft) {
        currentTab = a.textContent.trim().toLowerCase();
      }
    });

    // Also check for heading text
    var headings = document.querySelectorAll('h1, h2');
    headings.forEach(function(h) {
      var t = h.textContent.trim().toLowerCase();
      if (t.includes('coming soon') || t.includes('overview') || t.includes('earnings') || t.includes('subscribers')) {
        // Find parent container
        var container = h.closest('div[style*="padding"]') || h.parentElement;
        if (!container) return;

        if (t.includes('coming soon')) {
          // Check what section this is in
          var prev = container.previousElementSibling || container.parentElement;
          if (prev) {
            var prevText = prev.textContent.toLowerCase();
            if (prevText.includes('earning')) injectEarnings(container);
            else if (prevText.includes('subscriber')) injectSubscribers(container);
          }
        }
      }
    });

    // Look for "Coming soon" text and replace it
    var allDivs = document.querySelectorAll('div');
    allDivs.forEach(function(div) {
      if (div.dataset.statsInjected) return;
      var text = div.textContent.trim();
      if (text === 'Coming soon' || text === '🚀 Coming soon') {
        div.dataset.statsInjected = '1';
        // Determine which section based on nearby elements
        var section = findSection(div);
        if (section === 'earnings') loadEarnings(div);
        else if (section === 'subscribers') loadSubscribers(div);
        else if (section === 'overview') loadOverview(div);
      }
    });

    // Also populate Overview stats if we see the stats grid
    var statsCards = document.querySelectorAll('[style*="grid"]');
    statsCards.forEach(function(grid) {
      if (grid.dataset.statsLoaded) return;
      var children = grid.querySelectorAll('div');
      var hasSubCount = false;
      children.forEach(function(c) {
        if (c.textContent.includes('Subscribers') || c.textContent.includes('subscribers')) hasSubCount = true;
      });
      if (hasSubCount && !grid.dataset.statsLoaded) {
        grid.dataset.statsLoaded = '1';
        loadOverviewIntoGrid(grid);
      }
    });
  }, 1000);

  function findSection(el) {
    var parent = el;
    for (var i = 0; i < 5; i++) {
      parent = parent.parentElement;
      if (!parent) break;
      var text = parent.textContent.toLowerCase();
      if (text.includes('earning') || text.includes('revenue')) return 'earnings';
      if (text.includes('subscriber') || text.includes('fans')) return 'subscribers';
      if (text.includes('overview') || text.includes('dashboard')) return 'overview';
    }
    return '';
  }

  function loadOverviewIntoGrid(grid) {
    fetch('/api/stats/overview.php', { credentials: 'include', headers: authHeaders() })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok) return;
        // Find stat value elements and update
        var vals = grid.querySelectorAll('[style*="font-size: 28"], [style*="font-size:28"], [style*="fontSize"]');
        // Update stats in order they appear
        var statOrder = [d.subscribers, d.posts, d.messages, d.earnings_cents / 100];
        vals.forEach(function(v, i) {
          if (statOrder[i] !== undefined) {
            v.textContent = i === 3 ? '$' + statOrder[i].toFixed(2) : statOrder[i];
          }
        });
      }).catch(function() {});
  }

  function loadEarnings(container) {
    container.innerHTML = '<p style="color:#667;padding:20px;">Loading earnings...</p>';
    fetch('/api/stats/earnings.php', { credentials: 'include', headers: authHeaders() })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok) { container.innerHTML = '<p style="color:#667">No data yet</p>'; return; }
        container.innerHTML =
          '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;margin:16px 0;">' +
            '<div style="background:#1a2940;border-radius:12px;padding:16px;text-align:center;"><p style="color:#8899aa;font-size:12px;margin:0 0 4px;">Subscriptions</p><p style="color:#00e676;font-size:24px;font-weight:700;margin:0;">' + fmt$(d.subscriptions) + '</p></div>' +
            '<div style="background:#1a2940;border-radius:12px;padding:16px;text-align:center;"><p style="color:#8899aa;font-size:12px;margin:0 0 4px;">Tips</p><p style="color:#00b4ff;font-size:24px;font-weight:700;margin:0;">' + fmt$(d.tips) + '</p></div>' +
            '<div style="background:#1a2940;border-radius:12px;padding:16px;text-align:center;"><p style="color:#8899aa;font-size:12px;margin:0 0 4px;">PPV</p><p style="color:#e91e8c;font-size:24px;font-weight:700;margin:0;">' + fmt$(d.ppv) + '</p></div>' +
            '<div style="background:#1a2940;border-radius:12px;padding:16px;text-align:center;"><p style="color:#8899aa;font-size:12px;margin:0 0 4px;">Total Revenue</p><p style="color:#fff;font-size:24px;font-weight:700;margin:0;">' + fmt$(d.total) + '</p></div>' +
          '</div>' +
          (d.recent.length > 0 ?
            '<h3 style="color:#fff;font-size:14px;margin:16px 0 8px;">Recent Transactions</h3>' +
            '<div style="max-height:300px;overflow-y:auto;">' +
            d.recent.map(function(t) {
              return '<div style="display:flex;justify-content:space-between;padding:8px 12px;border-bottom:1px solid #1e3350;font-size:13px;">' +
                '<span style="color:#8899aa;">' + (t.display_name || t.email || 'Fan') + '</span>' +
                '<span style="color:#8899aa;">' + t.type + '</span>' +
                '<span style="color:#00e676;font-weight:600;">' + fmt$(t.amount_cents) + '</span>' +
              '</div>';
            }).join('') +
            '</div>'
          : '<p style="color:#556677;font-size:13px;margin:16px 0;">No transactions yet — revenue will show here once CCBill is live</p>');
      }).catch(function() { container.innerHTML = '<p style="color:#667">Failed to load</p>'; });
  }

  function loadSubscribers(container) {
    container.innerHTML = '<p style="color:#667;padding:20px;">Loading subscribers...</p>';
    fetch('/api/stats/subscribers.php', { credentials: 'include', headers: authHeaders() })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.fans.length) { container.innerHTML = '<p style="color:#556677;font-size:13px;padding:16px;">No fans registered yet</p>'; return; }
        container.innerHTML =
          '<div style="max-height:400px;overflow-y:auto;">' +
          '<div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:8px;padding:8px 12px;font-size:11px;color:#556677;border-bottom:1px solid #1e3350;">' +
            '<span>Fan</span><span>Status</span><span>Messages</span><span>Likes</span><span>Spent</span>' +
          '</div>' +
          d.fans.map(function(f) {
            var status = f.sub_status === 'active' ? '<span style="color:#00e676">Active</span>' : '<span style="color:#556677">Free</span>';
            return '<div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:8px;padding:10px 12px;font-size:13px;border-bottom:1px solid #1e3350;align-items:center;">' +
              '<span style="color:#fff;">' + (f.display_name || f.email) + '</span>' +
              status +
              '<span style="color:#8899aa;">' + f.msg_count + '</span>' +
              '<span style="color:#8899aa;">' + f.like_count + '</span>' +
              '<span style="color:#00e676;font-weight:600;">' + fmt$(f.total_spent) + '</span>' +
            '</div>';
          }).join('') +
          '</div>';
      }).catch(function() { container.innerHTML = '<p style="color:#667">Failed to load</p>'; });
  }

  function loadOverview(container) {
    container.innerHTML = '<p style="color:#667;padding:20px;">Loading...</p>';
    fetch('/api/stats/overview.php', { credentials: 'include', headers: authHeaders() })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok) return;
        container.innerHTML =
          '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;">' +
            '<div style="background:#1a2940;border-radius:12px;padding:16px;text-align:center;"><p style="color:#8899aa;font-size:11px;margin:0 0 4px;">Subscribers</p><p style="color:#00b4ff;font-size:28px;font-weight:700;margin:0;">'+d.subscribers+'</p></div>' +
            '<div style="background:#1a2940;border-radius:12px;padding:16px;text-align:center;"><p style="color:#8899aa;font-size:11px;margin:0 0 4px;">Fans</p><p style="color:#fff;font-size:28px;font-weight:700;margin:0;">'+d.registered_fans+'</p></div>' +
            '<div style="background:#1a2940;border-radius:12px;padding:16px;text-align:center;"><p style="color:#8899aa;font-size:11px;margin:0 0 4px;">Posts</p><p style="color:#fff;font-size:28px;font-weight:700;margin:0;">'+d.posts+'</p></div>' +
            '<div style="background:#1a2940;border-radius:12px;padding:16px;text-align:center;"><p style="color:#8899aa;font-size:11px;margin:0 0 4px;">Messages (24h)</p><p style="color:#00e676;font-size:28px;font-weight:700;margin:0;">'+d.messages_24h+'</p></div>' +
            '<div style="background:#1a2940;border-radius:12px;padding:16px;text-align:center;"><p style="color:#8899aa;font-size:11px;margin:0 0 4px;">Total Likes</p><p style="color:#ff4757;font-size:28px;font-weight:700;margin:0;">'+d.likes+'</p></div>' +
            '<div style="background:#1a2940;border-radius:12px;padding:16px;text-align:center;"><p style="color:#8899aa;font-size:11px;margin:0 0 4px;">Revenue</p><p style="color:#00e676;font-size:28px;font-weight:700;margin:0;">'+fmt$(d.earnings_cents)+'</p></div>' +
          '</div>';
      }).catch(function() { container.innerHTML = '<p style="color:#667">Failed to load</p>'; });
  }
})();
