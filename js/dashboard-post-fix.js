/**
 * Fix dashboard New Post form — wire publish button to /api/posts/create.php
 */
(function() {
  if (window.location.pathname.indexOf('/dashboard') !== 0) return;

  function getToken() {
    try { return localStorage.getItem('breyya_token') || localStorage.getItem('token') || ''; }
    catch(e) { return ''; }
  }

  // Use capture phase to intercept before React
  document.addEventListener('click', async function(e) {
    var btn = e.target.closest('button');
    if (!btn) return;
    var text = btn.textContent.trim();
    
    if (text === 'Publish' || text === 'Publishing...') {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      
      // Find the modal/form container
      var modal = btn.closest('div[style*="position"]') || btn.parentElement;
      
      // Find caption textarea
      var textarea = modal ? modal.querySelector('textarea') : document.querySelector('textarea');
      var caption = textarea ? textarea.value.trim() : '';
      
      // Find file input
      var fileInput = modal ? modal.querySelector('input[type="file"]') : document.querySelector('input[type="file"]');
      var file = fileInput && fileInput.files ? fileInput.files[0] : null;
      
      if (!caption && !file) {
        alert('Add a caption or image first');
        return;
      }
      
      var origText = btn.textContent;
      btn.textContent = 'Publishing...';
      btn.disabled = true;
      
      try {
        var formData = new FormData();
        if (file) formData.append('media', file);
        formData.append('caption', caption || 'New post ✨');
        formData.append('like_count', '0');
        formData.append('is_free', '1');
        
        var token = getToken();
        var headers = {};
        if (token) headers['Authorization'] = 'Bearer ' + token;
        
        var res = await fetch('/api/posts/create.php', {
          method: 'POST',
          credentials: 'include',
          headers: headers,
          body: formData
        });
        var data = await res.json();
        
        if (data.ok || data.post) {
          btn.textContent = '✅ Posted!';
          btn.style.background = '#00c853';
          if (textarea) textarea.value = '';
          if (fileInput) fileInput.value = '';
          setTimeout(function() {
            btn.textContent = origText;
            btn.style.background = '';
            btn.disabled = false;
            // Close modal - find X button
            var xBtn = document.querySelector('button svg[viewBox] path[d*="M"]');
            if (xBtn) xBtn.closest('button').click();
            // Refresh page to show new post
            window.location.reload();
          }, 1500);
        } else {
          alert('Failed: ' + (data.error || 'Unknown error'));
          btn.textContent = origText;
          btn.disabled = false;
        }
      } catch(err) {
        alert('Error: ' + err.message);
        btn.textContent = origText;
        btn.disabled = false;
      }
    }
  }, true); // <-- capture phase = fires BEFORE React's handler
})();
