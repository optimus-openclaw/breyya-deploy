/**
 * Zero out placeholder/fake stats on admin dashboard pages.
 * These pages show hardcoded demo data from the React build.
 * Until real data flows in, everything should show 0.
 * Admin-only — does NOT run on fan-facing pages.
 */
(function() {
  var path = window.location.pathname;
  
  // Only run on admin dashboard pages
  if (path.indexOf('/dashboard') !== 0 && path.indexOf('/backstage') !== 0) return;

  function zeroOut() {
    // Find all stat cards with numbers and zero them
    var cards = document.querySelectorAll('[style*="font-size"]');
    cards.forEach(function(el) {
      var text = el.textContent.trim();
      // Match numbers like 342, 1,847, $485, $0.00, 23
      if (/^[\$]?[\d,]+(\.\d{2})?$/.test(text) && !el.dataset.zeroed) {
        var isPrice = text.startsWith('$');
        // Don't zero out inventory counts (those are real)
        if (path.indexOf('/backstage/inventory') === 0) return;
        // Don't zero out real data on inventory page
        var parent = el.closest('table');
        if (parent) return; // Table data might be real (inventory)
        
        el.textContent = isPrice ? '$0.00' : '0';
        el.dataset.zeroed = '1';
      }
    });
    
    // Specifically target known fake stat values on /dashboard/
    if (path.indexOf('/dashboard') === 0) {
      document.querySelectorAll('div, span, p').forEach(function(el) {
        var t = el.textContent.trim();
        // Zero out known fake values
        if (t === '342' || t === '1,847' || t === '23' || t === '$485') {
          el.textContent = t.startsWith('$') ? '$0.00' : '0';
          el.dataset.zeroed = '1';
        }
      });
    }
  }

  // Run after page renders
  setTimeout(zeroOut, 1500);
  setTimeout(zeroOut, 3000);
  setTimeout(zeroOut, 5000);
})();
