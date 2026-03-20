/**
 * Fix dashboard New Post — intercept publish, upload file + caption to API.
 */
(function() {
  if (window.location.pathname.indexOf('/dashboard') !== 0) return;

  function getToken() {
    try { return localStorage.getItem('breyya_token') || localStorage.getItem('token') || ''; }
    catch(e) { return ''; }
  }

  // Store reference to file when user selects one (React may clear the input)
  var selectedFile = null;

  // Intercept ALL file input changes to capture the file
  document.addEventListener('change', function(e) {
    if (e.target && e.target.type === 'file' && e.target.files && e.target.files.length > 0) {
      selectedFile = e.target.files[0];
      console.log('[dashboard-fix] File captured:', selectedFile.name, selectedFile.size);
    }
  }, true);

  // Also watch for drag-and-drop
  document.addEventListener('drop', function(e) {
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      selectedFile = e.dataTransfer.files[0];
      console.log('[dashboard-fix] File dropped:', selectedFile.name);
    }
  }, true);

  // Intercept Publish click
  document.addEventListener('click', async function(e) {
    var btn = e.target.closest('button');
    if (!btn) return;
    var text = btn.textContent.trim();

    if (text !== 'Publish' && text !== 'Publishing...' && text !== '✅ Posted!') return;

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    // Find caption
    var textareas = document.querySelectorAll('textarea');
    var caption = '';
    textareas.forEach(function(ta) {
      if (ta.value.trim()) caption = ta.value.trim();
    });

    // Try to find file from all inputs as fallback
    if (!selectedFile) {
      var inputs = document.querySelectorAll('input[type="file"]');
      inputs.forEach(function(inp) {
        if (inp.files && inp.files.length > 0) selectedFile = inp.files[0];
      });
    }

    if (!caption && !selectedFile) {
      alert('Add a caption or choose an image first');
      return;
    }

    // If no file but has caption, post as text
    var postType = selectedFile ? (selectedFile.type.startsWith('video') ? 'video' : 'photo') : 'text';

    btn.textContent = 'Publishing...';
    btn.disabled = true;

    try {
      var formData = new FormData();
      if (selectedFile) formData.append('media', selectedFile);
      formData.append('caption', caption || '');
      formData.append('like_count', '0');
      formData.append('is_free', document.querySelector('input[type="checkbox"]:checked') ? '1' : '0');
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
        btn.textContent = '✅ Posted!';
        btn.style.background = '#00c853';
        selectedFile = null;
        setTimeout(function() { window.location.reload(); }, 1500);
      } else {
        alert('Failed: ' + (data.error || 'Unknown error'));
        btn.textContent = 'Publish';
        btn.disabled = false;
      }
    } catch(err) {
      alert('Error: ' + err.message);
      btn.textContent = 'Publish';
      btn.disabled = false;
    }
  }, true);
})();
