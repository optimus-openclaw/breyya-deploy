/**
 * Fix dashboard New Post form — wire publish button to /api/posts/create.php
 * Handles both mobile and desktop.
 */
(function() {
  if (window.location.pathname.indexOf('/dashboard') !== 0) return;

  function getToken() {
    try { return localStorage.getItem('breyya_token') || localStorage.getItem('token') || ''; }
    catch(e) { return ''; }
  }

  // Watch for the publish button and new post modal
  setInterval(function() {
    // Find all buttons that say "Publish" or "Post"
    var buttons = document.querySelectorAll('button');
    buttons.forEach(function(btn) {
      var text = btn.textContent.trim().toLowerCase();
      if ((text === 'publish' || text === 'post' || text.includes('publish')) && !btn.dataset.wired) {
        btn.dataset.wired = '1';
        
        // Make it clickable on mobile
        btn.style.cursor = 'pointer';
        btn.style.pointerEvents = 'auto';
        btn.style.touchAction = 'manipulation';
        btn.style.webkitTapHighlightColor = 'transparent';
        
        btn.addEventListener('click', async function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          // Find the caption textarea
          var textarea = document.querySelector('textarea');
          var caption = textarea ? textarea.value.trim() : '';
          
          // Find the file input
          var fileInput = document.querySelector('input[type="file"]');
          var file = fileInput && fileInput.files ? fileInput.files[0] : null;
          
          if (!caption && !file) {
            alert('Add a caption or image first');
            return;
          }
          
          btn.textContent = 'Publishing...';
          btn.disabled = true;
          
          try {
            var formData = new FormData();
            if (file) formData.append('media', file);
            formData.append('caption', caption || 'New post ✨');
            formData.append('like_count', '0');
            formData.append('is_free', '1');
            
            var token = getToken();
            var res = await fetch('/api/posts/create.php', {
              method: 'POST',
              credentials: 'include',
              headers: token ? { 'Authorization': 'Bearer ' + token } : {},
              body: formData
            });
            var data = await res.json();
            
            if (data.ok || data.post) {
              alert('Posted! ✅');
              if (textarea) textarea.value = '';
              if (fileInput) fileInput.value = '';
              // Try to close the modal
              var closeBtn = document.querySelector('[class*="close"], [class*="Close"], button[aria-label="Close"]');
              if (closeBtn) closeBtn.click();
            } else {
              alert('Failed: ' + (data.error || 'Unknown error'));
            }
          } catch(err) {
            alert('Error: ' + err.message);
          }
          
          btn.textContent = 'Publish';
          btn.disabled = false;
        }, { passive: false });
      }
      
      // Also fix "Free post" / "PPV post" toggle buttons
      if ((text === 'free post' || text === 'ppv post') && !btn.dataset.touchFixed) {
        btn.dataset.touchFixed = '1';
        btn.style.cursor = 'pointer';
        btn.style.pointerEvents = 'auto';
        btn.style.touchAction = 'manipulation';
        btn.style.webkitTapHighlightColor = 'transparent';
      }
    });
    
    // Fix file inputs for mobile
    var fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(function(fi) {
      if (!fi.dataset.touchFixed) {
        fi.dataset.touchFixed = '1';
        fi.style.touchAction = 'manipulation';
      }
    });
    
    // Fix any clickable areas that aren't responding
    var clickables = document.querySelectorAll('[onclick], [role="button"], label');
    clickables.forEach(function(el) {
      if (!el.dataset.touchFixed) {
        el.dataset.touchFixed = '1';
        el.style.cursor = 'pointer';
        el.style.touchAction = 'manipulation';
      }
    });
  }, 1000);
})();
