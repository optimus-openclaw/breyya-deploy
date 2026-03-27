/**
 * Voice Notes v2 — Works with React chat component
 * Detects .mp3 URLs rendered as broken <img> tags and replaces with audio players
 */
(function() {
  if (window.location.pathname.indexOf('/chat') !== 0) return;

  var processed = new Set();

  function processVoiceNotes() {
    // Strategy: Find <img> tags with src ending in .mp3 — React renders media_url as img
    var images = document.querySelectorAll('img[src*="/voice-notes/"], img[src$=".mp3"]');
    
    images.forEach(function(img) {
      var src = img.getAttribute('src');
      if (!src || processed.has(src)) return;
      processed.add(src);
      
      // Create audio player to replace the broken img
      var container = document.createElement('div');
      container.style.cssText = 'background:linear-gradient(135deg,#667eea,#764ba2);border-radius:16px;padding:10px 14px;display:flex;align-items:center;gap:8px;max-width:250px;min-width:160px;margin:4px 0;';
      
      var playBtn = document.createElement('button');
      playBtn.textContent = '▶';
      playBtn.style.cssText = 'background:rgba(255,255,255,0.9);border:none;border-radius:50%;width:32px;height:32px;font-size:14px;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;';
      
      // Waveform bars
      var wave = document.createElement('div');
      wave.style.cssText = 'flex:1;display:flex;align-items:center;gap:2px;height:20px;';
      for (var i = 0; i < 10; i++) {
        var bar = document.createElement('div');
        bar.style.cssText = 'background:rgba(255,255,255,0.6);width:3px;height:' + (Math.random()*12+4) + 'px;border-radius:1px;';
        wave.appendChild(bar);
      }
      
      var dur = document.createElement('span');
      dur.textContent = '0:00';
      dur.style.cssText = 'color:rgba(255,255,255,0.9);font-size:11px;min-width:28px;text-align:right;';
      
      var audio = document.createElement('audio');
      audio.src = src;
      audio.preload = 'metadata';
      
      audio.addEventListener('loadedmetadata', function() {
        var m = Math.floor(audio.duration / 60);
        var s = Math.floor(audio.duration % 60);
        dur.textContent = m + ':' + (s < 10 ? '0' : '') + s;
      });
      
      var playing = false;
      playBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (playing) {
          audio.pause();
          playBtn.textContent = '▶';
          playing = false;
        } else {
          // Stop other players
          document.querySelectorAll('audio').forEach(function(a) { if (a !== audio) a.pause(); });
          audio.play().catch(function() {});
          playBtn.textContent = '⏸';
          playing = true;
        }
      });
      
      audio.addEventListener('ended', function() {
        playBtn.textContent = '▶';
        playing = false;
        var m = Math.floor(audio.duration / 60);
        var s = Math.floor(audio.duration % 60);
        dur.textContent = m + ':' + (s < 10 ? '0' : '') + s;
      });
      
      audio.addEventListener('timeupdate', function() {
        if (playing) {
          var m = Math.floor(audio.currentTime / 60);
          var s = Math.floor(audio.currentTime % 60);
          dur.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        }
      });
      
      container.appendChild(playBtn);
      container.appendChild(wave);
      container.appendChild(dur);
      
      // Replace the broken img with our player
      var parent = img.parentElement;
      if (parent) {
        parent.replaceChild(container, img);
      }
    });
  }
  
  // Run on load and poll for new messages
  if (document.readyState === 'complete') { setTimeout(processVoiceNotes, 500); }
  else { window.addEventListener('load', function() { setTimeout(processVoiceNotes, 500); }); }
  
  // MutationObserver for dynamic content
  var obs = new MutationObserver(function() { processVoiceNotes(); });
  function startObs() {
    var chat = document.querySelector('[class*="messages"]');
    if (chat) { obs.observe(chat, {childList:true, subtree:true}); processVoiceNotes(); }
    else { setTimeout(startObs, 500); }
  }
  startObs();
  
  // Fallback poll
  setInterval(processVoiceNotes, 3000);
})();
