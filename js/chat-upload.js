/**
 * Chat image upload fix
 * Intercepts the file input, uploads to server, then sends message with media_url
 * Images only, no video
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  // Wait for the chat to load
  setTimeout(function() {
    var fileInput = document.getElementById('chatUpload');
    if (!fileInput) return;

    // Restrict to images only (no video)
    fileInput.setAttribute('accept', 'image/jpeg,image/png,image/webp,image/gif');

    // Clone and replace to remove existing handlers
    var newInput = fileInput.cloneNode(true);
    fileInput.parentNode.replaceChild(newInput, fileInput);

    newInput.addEventListener('change', async function(e) {
      var file = e.target.files[0];
      if (!file) return;

      // Block video
      if (file.type.startsWith('video/')) {
        alert('Only images are allowed in chat');
        newInput.value = '';
        return;
      }

      // Block non-images
      if (!file.type.startsWith('image/')) {
        alert('Only images are allowed in chat');
        newInput.value = '';
        return;
      }

      // Max 20MB
      if (file.size > 20 * 1024 * 1024) {
        alert('Image too large (max 20MB)');
        newInput.value = '';
        return;
      }

      try {
        // Upload to server
        var formData = new FormData();
        formData.append('media', file);

        var uploadResp = await fetch('/api/messages/upload-media.php', {
          method: 'POST',
          credentials: 'include',
          body: formData
        });

        var uploadData = await uploadResp.json();
        if (!uploadData.ok) {
          console.error('Upload failed:', uploadData.error);
          return;
        }

        // Send message with media_url
        var sendResp = await fetch('/api/messages/send.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            content: '',
            receiver_id: 1,
            media_url: uploadData.media_url
          })
        });

        var sendData = await sendResp.json();
        if (sendData.ok) {
          // Trigger a refresh of messages
          newInput.value = '';
        }
      } catch (err) {
        console.error('Upload error:', err);
      }

      newInput.value = '';
    });
  }, 2000);
})();
