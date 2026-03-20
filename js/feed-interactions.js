/**
 * Feed interactions — heart (like) and tip buttons for all posts.
 */
(function() {
  if (window.location.pathname.indexOf('/feed') !== 0) return;

  function getToken() {
    try { return localStorage.getItem('breyya_token') || localStorage.getItem('token') || ''; }
    catch(e) { return ''; }
  }

  function handleInteractions() {
    var posts = document.querySelectorAll('article[class*="post"], [data-post-id]');
    
    posts.forEach(function(post) {
      if (post.dataset.interactionsWired) return;
      post.dataset.interactionsWired = '1';
      
      var buttons = post.querySelectorAll('button[class*="actionBtn"]');
      
      buttons.forEach(function(btn) {
        var svg = btn.querySelector('svg');
        var span = btn.querySelector('span');
        if (!svg) return;
        
        var paths = svg.querySelectorAll('path');
        var lines = svg.querySelectorAll('line');
        var isHeart = false;
        var isTip = false;
        
        // Detect heart button (has heart path)
        paths.forEach(function(p) {
          if (p.getAttribute('d') && p.getAttribute('d').includes('20.84')) isHeart = true;
        });
        
        // Detect tip button (has $ line + path or "Tip" text)
        if (lines.length > 0 || (span && span.textContent.trim() === 'Tip')) isTip = true;
        
        if (isHeart) {
          btn.style.cursor = 'pointer';
          btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Toggle heart
            var liked = btn.dataset.liked === '1';
            var countEl = btn.querySelector('span');
            var count = countEl ? parseInt(countEl.textContent) || 0 : 0;
            
            if (liked) {
              // Unlike
              btn.dataset.liked = '0';
              svg.setAttribute('fill', 'none');
              svg.style.color = '';
              if (countEl) countEl.textContent = Math.max(0, count - 1);
            } else {
              // Like — animate
              btn.dataset.liked = '1';
              svg.setAttribute('fill', '#ff4757');
              svg.style.color = '#ff4757';
              if (countEl) countEl.textContent = count + 1;
              
              // Quick pulse animation
              btn.style.transform = 'scale(1.3)';
              setTimeout(function() { btn.style.transform = 'scale(1)'; }, 200);
            }
            btn.style.transition = 'transform 0.2s';

            // Save to API if post has an ID
            var postId = post.dataset.postId;
            if (postId) {
              var token = getToken();
              fetch('/api/posts/like.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                  'Content-Type': 'application/json',
                  'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({ post_id: parseInt(postId) })
              }).catch(function() {});
            }
          });
        }
        
        if (isTip) {
          btn.style.cursor = 'pointer';
          btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Show tip modal
            showTipModal(post);
          });
        }
      });
    });
  }

  function showTipModal(post) {
    // Remove existing modal
    var existing = document.getElementById('tip-modal-overlay');
    if (existing) existing.remove();

    var overlay = document.createElement('div');
    overlay.id = 'tip-modal-overlay';
    overlay.setAttribute('style',
      'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;' +
      'display:flex;align-items:center;justify-content:center;' +
      'backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);'
    );

    var modal = document.createElement('div');
    modal.setAttribute('style',
      'background:#111d32;border:1px solid rgba(0,180,255,0.2);border-radius:20px;' +
      'padding:24px;min-width:280px;text-align:center;font-family:inherit;'
    );

    modal.innerHTML = 
      '<h3 style="color:#fff;margin:0 0 16px;font-size:16px;">Send a tip to Breyya 💕</h3>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">' +
        '<button class="tip-amount" data-amount="5" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$5</button>' +
        '<button class="tip-amount" data-amount="10" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$10</button>' +
        '<button class="tip-amount" data-amount="25" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$25</button>' +
        '<button class="tip-amount" data-amount="50" style="padding:12px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">$50</button>' +
      '</div>' +
      '<div style="display:flex;gap:8px;margin-bottom:14px;align-items:center;">' +
        '<span style="color:#7a93a8;font-size:18px;font-weight:700;">$</span>' +
        '<input id="custom-tip" type="number" min="5" step="1" placeholder="Custom amount" style="flex:1;padding:12px;background:#1a2940;border:1px solid rgba(255,255,255,0.1);border-radius:12px;color:#fff;font-size:15px;outline:none;-moz-appearance:textfield;"/>' +
        '<button id="send-custom-tip" style="padding:12px 20px;background:linear-gradient(135deg,#00b4d8,#0090b0);border:none;border-radius:12px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;white-space:nowrap;">Send</button>' +
      '</div>' +
      '<p style="color:#556677;font-size:11px;margin:0 0 14px;">Minimum tip: $5</p>' +
      '<button id="tip-cancel" style="color:#667;cursor:pointer;background:none;border:1px solid rgba(255,255,255,0.15);border-radius:10px;padding:8px 24px;font-size:13px;">Cancel</button>';

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    // Handle tip amount clicks
    modal.querySelectorAll('.tip-amount').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var amount = btn.dataset.amount;
        // For now, show confirmation (real payment comes with CCBill)
        btn.textContent = '✅ Tipped!';
        btn.style.background = '#00c853';
        setTimeout(function() {
          overlay.remove();
        }, 1000);
      });
    });

    // Custom tip
    var customInput = modal.querySelector('#custom-tip');
    var sendCustom = modal.querySelector('#send-custom-tip');
    if (sendCustom) {
      sendCustom.addEventListener('click', function() {
        var val = parseInt(customInput.value);
        if (!val || val < 5) { 
          customInput.style.borderColor = '#ff4757';
          customInput.placeholder = 'Min $5';
          return;
        }
        sendCustom.textContent = '✅ Tipped $' + val + '!';
        sendCustom.style.background = '#00c853';
        setTimeout(function() { overlay.remove(); }, 1000);
      });
      // Enter key
      customInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') sendCustom.click();
      });
    }

    // Cancel
    modal.querySelector('#tip-cancel').addEventListener('click', function() {
      overlay.remove();
    });

    // Click outside to close
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) overlay.remove();
    });
  }

  if (document.readyState === 'complete') setTimeout(handleInteractions, 1500);
  else window.addEventListener('load', function() { setTimeout(handleInteractions, 1500); });
  setInterval(handleInteractions, 3000);
})();
