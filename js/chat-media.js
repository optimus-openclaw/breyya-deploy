/**
 * Chat media upload — adds image/video upload button to chat input bar.
 * Uploads to /api/messages/upload-media.php then sends via /api/messages/send.php
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

    // Check if already injected
    if (document.getElementById('chat-media-btn')) return;
    injected = true;

    // Create file input (hidden)
    var fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*,video/mp4';
    fileInput.style.display = 'none';
    fileInput.id = 'chat-media-input';

    // Create upload button
    var btn = document.createElement('button');
    btn.id = 'chat-media-btn';
    btn.innerHTML = '📎';
    btn.title = 'Send image or video';
    btn.setAttribute('style',
      'background:none;border:none;font-size:22px;cursor:pointer;padding:8px;' +
      'display:flex;align-items:center;justify-content:center;opacity:0.6;transition:opacity 0.2s;'
    );
    btn.onmouseover = function() { btn.style.opacity = '1'; };
    btn.onmouseout = function() { btn.style.opacity = '0.6'; };
    btn.onclick = function() { fileInput.click(); };

    // Handle file selection
    fileInput.onchange = async function() {
      var file = fileInput.files[0];
      if (!file) return;

      // Show uploading state
      btn.innerHTML = '⏳';
      btn.style.opacity = '1';

      try {
        // Step 1: Upload the file
        var formData = new FormData();
        formData.append('media', file);

        var token = '';
        try { token = localStorage.getItem('breyya_token') || localStorage.getItem('token') || ''; } catch(e) {}

        var uploadRes = await fetch('/api/messages/upload-media.php', {
          method: 'POST',
          credentials: 'include',
          headers: token ? { 'Authorization': 'Bearer ' + token } : {},
          body: formData
        });
        var uploadData = await uploadRes.json();

        if (!uploadData.ok) {
          alert('Upload failed: ' + (uploadData.error || 'Unknown error'));
          btn.innerHTML = '📎';
          btn.style.opacity = '0.6';
          return;
        }

        // Step 2: Send message with media URL
        var testFanMode = document.querySelector('[class*="testFanBanner"]') !== null;
        var sendBody = {
          content: '',
          receiver_id: 1,
          media_url: uploadData.media_url
        };
        if (testFanMode) sendBody.test_as_fan = true;

        var sendRes = await fetch('/api/messages/send.php', {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            ...(token ? { 'Authorization': 'Bearer ' + token } : {})
          },
          body: JSON.stringify(sendBody)
        });
        var sendData = await sendRes.json();

        if (sendData.ok) {
          btn.innerHTML = '✅';
          setTimeout(function() { btn.innerHTML = '📎'; btn.style.opacity = '0.6'; }, 1500);
        } else {
          alert('Send failed: ' + (sendData.error || 'Unknown error'));
          btn.innerHTML = '📎';
        }
      } catch(e) {
        alert('Error: ' + e.message);
        btn.innerHTML = '📎';
      }
      
      btn.style.opacity = '0.6';
      fileInput.value = '';
    };

    // Insert before send button
    inputBar.insertBefore(btn, sendBtn);
    inputBar.appendChild(fileInput);
  }

  // Keep trying to inject (React may re-render)
  setInterval(injectUploadBtn, 1000);
  if (document.readyState === 'complete') setTimeout(injectUploadBtn, 500);
  else window.addEventListener('load', function() { setTimeout(injectUploadBtn, 500); });
})();
