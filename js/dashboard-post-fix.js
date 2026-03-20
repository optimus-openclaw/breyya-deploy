/**
 * Fix dashboard New Post — intercept publish on mobile + desktop.
 */
(function() {
  if (window.location.pathname.indexOf('/dashboard') !== 0) return;

  function getToken() {
    try { return localStorage.getItem('breyya_token') || localStorage.getItem('token') || ''; }
    catch(e) { return ''; }
  }

  var selectedFile = null;
  var isUploading = false;

  // Capture file selection globally
  document.addEventListener('change', function(e) {
    if (e.target && e.target.type === 'file' && e.target.files && e.target.files.length > 0) {
      selectedFile = e.target.files[0];
    }
  }, true);

  async function doPublish(btn) {
    if (isUploading) return;
    isUploading = true;

    var textareas = document.querySelectorAll('textarea');
    var caption = '';
    textareas.forEach(function(ta) { if (ta.value.trim()) caption = ta.value.trim(); });

    if (!selectedFile) {
      var inputs = document.querySelectorAll('input[type="file"]');
      inputs.forEach(function(inp) {
        if (inp.files && inp.files.length > 0) selectedFile = inp.files[0];
      });
    }

    if (!caption && !selectedFile) {
      alert('Add a caption or choose an image first');
      isUploading = false;
      return;
    }

    if (btn) { btn.textContent = 'Publishing...'; btn.disabled = true; }

    try {
      var formData = new FormData();
      if (selectedFile) formData.append('media', selectedFile);
      formData.append('caption', caption || '');
      formData.append('like_count', '0');
      formData.append('is_free', '1');
      if (!selectedFile) formData.append('type', 'text');

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
        if (btn) { btn.textContent = '✅ Posted!'; btn.style.background = '#00c853'; }
        selectedFile = null;
        setTimeout(function() { window.location.reload(); }, 1500);
      } else {
        alert('Failed: ' + (data.error || 'Unknown error'));
        if (btn) { btn.textContent = 'Publish'; btn.disabled = false; }
      }
    } catch(err) {
      alert('Error: ' + err.message);
      if (btn) { btn.textContent = 'Publish'; btn.disabled = false; }
    }
    isUploading = false;
  }

  function isPublishBtn(el) {
    if (!el) return false;
    var text = el.textContent.trim().toLowerCase();
    return text === 'publish' || text === 'publishing...' || text === '✅ posted!';
  }

  // Click handler (capture)
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('button');
    if (btn && isPublishBtn(btn)) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      doPublish(btn);
    }
  }, true);

  // Touch handler for mobile (capture) — touchend fires more reliably on iOS
  document.addEventListener('touchend', function(e) {
    var btn = e.target.closest('button');
    if (btn && isPublishBtn(btn)) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      doPublish(btn);
    }
  }, true);

  // Also add pointer event for modern browsers
  document.addEventListener('pointerup', function(e) {
    var btn = e.target.closest('button');
    if (btn && isPublishBtn(btn)) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      doPublish(btn);
    }
  }, true);
})();
