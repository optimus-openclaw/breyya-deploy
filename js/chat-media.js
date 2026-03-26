/**
 * Chat media upload — adds 📎 button to chat input bar.
 * Only opens file picker when 📎 is explicitly clicked.
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  var injected = false;

  function injectUploadBtn() {
    if (injected) return;
    var inputBar = document.querySelector('[class*="inputBar"]');
    if (!inputBar) return;
    var sendBtn = inputBar.querySelector('[class*="sendBtn"]');
    if (!sendBtn) return;
    if (document.getElementById('chat-media-btn')) return;
    injected = true;

    // Create hidden file input OUTSIDE the input bar to prevent event bubbling
    var fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*,video/mp4';
    fileInput.id = 'chat-media-input';
    fileInput.style.cssText = 'position:absolute;top:-9999px;left:-9999px;opacity:0;pointer-events:none;';
    document.body.appendChild(fileInput);

    // Create 📎 button
    var btn = document.createElement('button');
    btn.id = 'chat-media-btn';
    btn.type = 'button'; // Prevent form submission
    btn.innerHTML = '📎';
    btn.title = 'Send image or video';
    btn.setAttribute('style',
      'background:none !important;border:none !important;font-size:22px;cursor:pointer;padding:8px;' +
      'display:flex;align-items:center;justify-content:center;opacity:0.6;transition:opacity 0.2s;' +
      'flex-shrink:0;'
    );
    btn.onmouseover = function() { btn.style.opacity = '1'; };
    btn.onmouseout = function() { btn.style.opacity = '0.6'; };
    
    // ONLY open file picker on explicit 📎 click
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      fileInput.click();
    });

    fileInput.onchange = async function() {
      var file = fileInput.files[0];
      if (!file) return;
      btn.innerHTML = '⏳';
      try {
        var formData = new FormData();
        formData.append('media', file);
        var token = '';
        try { token = localStorage.getItem('breyya_token') || localStorage.getItem('token') || ''; } catch(e) {}
        var uploadRes = await fetch('/api/messages/upload-media.php', {
          method: 'POST', credentials: 'include',
          headers: token ? { 'Authorization': 'Bearer ' + token } : {},
          body: formData
        });
        var uploadData = await uploadRes.json();
        if (!uploadData.ok) { alert('Upload failed: ' + (uploadData.error || '')); btn.innerHTML = '📎'; return; }
        var testFanMode = document.querySelector('[class*="testFanBanner"]') !== null;
        var sendBody = { content: '', receiver_id: 1, media_url: uploadData.media_url };
        if (testFanMode) sendBody.test_as_fan = true;
        await fetch('/api/messages/send.php', {
          method: 'POST', credentials: 'include',
          headers: { 'Content-Type': 'application/json', ...(token ? { 'Authorization': 'Bearer ' + token } : {}) },
          body: JSON.stringify(sendBody)
        });
        btn.innerHTML = '✅';
        setTimeout(function() { btn.innerHTML = '📎'; btn.style.opacity = '0.6'; }, 1500);
      } catch(e) {
        alert('Error: ' + e.message);
        btn.innerHTML = '📎';
      }
      fileInput.value = '';
    };

    // Insert 📎 BEFORE the text input, not near the send button
    var chatInput = inputBar.querySelector('input, [class*="chatInput"]');
    if (chatInput) {
      inputBar.insertBefore(btn, chatInput);
    } else {
      inputBar.insertBefore(btn, inputBar.firstChild);
    }
  }

  setInterval(injectUploadBtn, 1000);
  if (document.readyState === 'complete') setTimeout(injectUploadBtn, 500);
  else window.addEventListener('load', function() { setTimeout(injectUploadBtn, 500); });
})();
