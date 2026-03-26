/**
 * Voice Notes Support for Breyya Chat
 * Renders audio messages with playback controls
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  let audioProcessed = new Set();

  function processAudioMessages() {
    // Find all message elements
    const messages = document.querySelectorAll('[class*="message"]');
    
    messages.forEach(message => {
      const messageId = message.dataset.messageId;
      if (!messageId || audioProcessed.has(messageId)) return;

      // Look for data attributes that might contain message data
      const messageData = JSON.parse(message.dataset.message || '{}');
      
      // Check if this is an audio message
      if (messageData.message_type === 'audio' && messageData.media_url) {
        audioProcessed.add(messageId);
        renderAudioMessage(message, messageData);
      }
    });
  }

  function renderAudioMessage(messageElement, messageData) {
    const textContent = messageElement.querySelector('[class*="content"], [class*="text"]');
    if (!textContent) return;

    // Create audio player container
    const audioContainer = document.createElement('div');
    audioContainer.className = 'voice-note-container';
    audioContainer.style.cssText = `
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 18px;
      padding: 12px 16px;
      margin: 4px 0;
      display: flex;
      align-items: center;
      gap: 10px;
      max-width: 280px;
      min-width: 140px;
      position: relative;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    `;

    // Play button
    const playButton = document.createElement('button');
    playButton.className = 'voice-note-play-btn';
    playButton.innerHTML = '▶️';
    playButton.style.cssText = `
      background: rgba(255, 255, 255, 0.9);
      border: none;
      border-radius: 50%;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.2s;
      flex-shrink: 0;
    `;

    // Waveform visualization (fake)
    const waveform = document.createElement('div');
    waveform.className = 'voice-note-waveform';
    waveform.style.cssText = `
      flex: 1;
      height: 20px;
      display: flex;
      align-items: center;
      gap: 2px;
      opacity: 0.8;
    `;

    // Generate fake waveform bars
    for (let i = 0; i < 12; i++) {
      const bar = document.createElement('div');
      bar.style.cssText = `
        background: rgba(255, 255, 255, 0.7);
        width: 3px;
        height: ${Math.random() * 12 + 4}px;
        border-radius: 1px;
        transition: all 0.3s;
      `;
      waveform.appendChild(bar);
    }

    // Duration display
    const duration = document.createElement('span');
    duration.className = 'voice-note-duration';
    duration.textContent = '0:00';
    duration.style.cssText = `
      color: rgba(255, 255, 255, 0.9);
      font-size: 12px;
      font-weight: 500;
      min-width: 30px;
      text-align: right;
    `;

    // Create audio element
    const audio = document.createElement('audio');
    audio.src = messageData.media_url;
    audio.preload = 'metadata';

    // Update duration when metadata loads
    audio.addEventListener('loadedmetadata', () => {
      const mins = Math.floor(audio.duration / 60);
      const secs = Math.floor(audio.duration % 60);
      duration.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
    });

    // Error fallback - show text content if audio fails
    audio.addEventListener('error', () => {
      audioContainer.style.display = 'none';
      const fallbackText = document.createElement('div');
      fallbackText.textContent = messageData.content || 'Voice message unavailable';
      fallbackText.style.cssText = `
        padding: 8px 12px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: #666;
        font-style: italic;
      `;
      textContent.appendChild(fallbackText);
    });

    let isPlaying = false;
    let progressInterval = null;

    // Play/pause functionality
    playButton.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      if (isPlaying) {
        audio.pause();
        playButton.innerHTML = '▶️';
        isPlaying = false;
        clearInterval(progressInterval);
        
        // Reset waveform animation
        waveform.querySelectorAll('div').forEach(bar => {
          bar.style.background = 'rgba(255, 255, 255, 0.7)';
        });
      } else {
        // Pause other playing voice notes
        document.querySelectorAll('.voice-note-play-btn').forEach(btn => {
          if (btn !== playButton && btn.innerHTML === '⏸️') {
            btn.click();
          }
        });

        audio.play().then(() => {
          playButton.innerHTML = '⏸️';
          isPlaying = true;
          
          // Animate waveform bars
          const bars = waveform.querySelectorAll('div');
          progressInterval = setInterval(() => {
            bars.forEach(bar => {
              bar.style.background = `rgba(255, 255, 255, ${Math.random() * 0.5 + 0.5})`;
              bar.style.transform = `scaleY(${Math.random() * 0.6 + 0.4})`;
            });
          }, 200);
          
        }).catch(err => {
          console.error('Audio play failed:', err);
          playButton.innerHTML = '❌';
          setTimeout(() => {
            audioContainer.style.display = 'none';
            const fallbackText = document.createElement('div');
            fallbackText.textContent = messageData.content || 'Voice message unavailable';
            fallbackText.style.cssText = `
              padding: 8px 12px;
              background: rgba(255, 255, 255, 0.1);
              border-radius: 12px;
              color: #666;
              font-style: italic;
            `;
            textContent.appendChild(fallbackText);
          }, 1000);
        });
      }
    });

    // Update progress
    audio.addEventListener('timeupdate', () => {
      if (isPlaying) {
        const mins = Math.floor(audio.currentTime / 60);
        const secs = Math.floor(audio.currentTime % 60);
        duration.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
      }
    });

    // Handle end of audio
    audio.addEventListener('ended', () => {
      playButton.innerHTML = '▶️';
      isPlaying = false;
      clearInterval(progressInterval);
      
      // Reset duration display
      const mins = Math.floor(audio.duration / 60);
      const secs = Math.floor(audio.duration % 60);
      duration.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
      
      // Reset waveform
      waveform.querySelectorAll('div').forEach(bar => {
        bar.style.background = 'rgba(255, 255, 255, 0.7)';
        bar.style.transform = 'scaleY(1)';
      });
    });

    // Assemble the audio player
    audioContainer.appendChild(playButton);
    audioContainer.appendChild(waveform);
    audioContainer.appendChild(duration);

    // Replace text content with audio player
    textContent.innerHTML = '';
    textContent.appendChild(audioContainer);

    // Add a small "🎵" indicator next to avatar
    const avatar = messageElement.querySelector('[class*="avatar"]');
    if (avatar) {
      const voiceIndicator = document.createElement('span');
      voiceIndicator.textContent = '🎵';
      voiceIndicator.style.cssText = `
        position: absolute;
        bottom: -4px;
        right: -4px;
        background: #667eea;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        border: 2px solid #fff;
      `;
      avatar.style.position = 'relative';
      avatar.appendChild(voiceIndicator);
    }
  }

  // Monitor for new messages and process audio
  const observer = new MutationObserver(() => {
    processAudioMessages();
  });

  // Start observing when chat container is available
  function startObserving() {
    const chatContainer = document.querySelector('[class*="messages"], [class*="chat"]');
    if (chatContainer) {
      observer.observe(chatContainer, { 
        childList: true, 
        subtree: true,
        attributes: true,
        attributeFilter: ['data-message', 'data-message-id']
      });
      processAudioMessages(); // Process any existing messages
    }
  }

  // Try to start observing
  if (document.readyState === 'complete') {
    setTimeout(startObserving, 500);
  } else {
    window.addEventListener('load', () => {
      setTimeout(startObserving, 500);
    });
  }

  // Also check periodically for new messages
  setInterval(processAudioMessages, 2000);

  // Cleanup on page unload
  window.addEventListener('beforeunload', () => {
    observer.disconnect();
  });
})();