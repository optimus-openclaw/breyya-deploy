/**
 * Auth gate — redirects to /login/ if user is not logged in.
 * Hides all page content until auth is confirmed.
 * Add to any page that requires login.
 */
(function() {
  // Hide everything immediately
  document.documentElement.style.visibility = 'hidden';

  var hasToken = false;
  
  // Check localStorage for token (fan login or backstage login)
  try { if (localStorage.getItem('token')) hasToken = true; } catch(e) {}
  try { if (localStorage.getItem('breyya_token')) hasToken = true; } catch(e) {}

  if (hasToken) {
    // Has token — show page
    document.documentElement.style.visibility = 'visible';
    return;
  }

  // No localStorage token — check cookie-based auth via API
  fetch('/api/auth/me.php', { credentials: 'include' })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d && d.ok && d.user) {
        // Logged in via cookie
        document.documentElement.style.visibility = 'visible';
      } else {
        // Not logged in — redirect
        window.location.href = '/login/';
      }
    })
    .catch(function() {
      // API error — redirect to be safe
      window.location.href = '/login/';
    });
})();
