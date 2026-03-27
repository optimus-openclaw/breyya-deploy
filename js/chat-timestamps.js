/**
 * Chat status v4:
 * - Polls last-active every 5 seconds
 * - Shows "Online now" when pending/typing detected
 * - Shows typing dots when is_typing = true
 * - Falls back to Away/Active/Last seen based on last message time
 */
(function() {
  if (window.location.pathname.indexOf("/chat") !== 0 && 
      window.location.pathname.indexOf("/feed") !== 0) return;

  var currentFanId = 0;
  try {
    var token = localStorage.getItem("jwt_token") || localStorage.getItem("token");
    if (token) {
      var payload = JSON.parse(atob(token.split(".")[1]));
      currentFanId = payload.user_id || payload.sub || 0;
    }
  } catch(e) {}

  function updateStatus() {
    var url = "/api/chat/last-active.php";
    if (currentFanId) url += "?fan_id=" + currentFanId;
    
    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok) return setStatus("Away — leave a message 💌", "#ff9a50");
        
        // Typing state — highest priority
        if (d.is_typing) {
          setStatus("Online now 💕", "#00e676");
          showTypingDots(true);
          return;
        }
        
        showTypingDots(false);
        
        // Has pending message being processed
        if (d.has_pending) {
          setStatus("Online now 💕", "#00e676");
          return;
        }
        
        // Fall back to time-based status
        if (!d.last_active) return setStatus("Away — leave a message 💌", "#ff9a50");
        
        var last = new Date(d.last_active.replace(" ", "T") + "Z");
        var now = new Date();
        var minsAgo = (now - last) / (1000 * 60);
        
        if (minsAgo < 5) {
          setStatus("Online now 💕", "#00e676");
        } else if (minsAgo < 120) {
          setStatus("Active today 💕", "#00e676");
        } else if (minsAgo < 720) {
          setStatus("Last seen recently", "#00b4d8");
        } else {
          setStatus("Away — leave a message 💌", "#ff9a50");
        }
      })
      .catch(function() {
        setStatus("Away — leave a message 💌", "#ff9a50");
      });
  }

  function setStatus(text, color) {
    var statusEls = document.querySelectorAll("[class*=\"headerStatus\"]");
    statusEls.forEach(function(el) {
      el.innerHTML = "<span style=\"color:" + color + "\">● " + text + "</span>";
    });
    
    var feedStatus = document.querySelectorAll("[class*=\"creatorInfo\"] span");
    feedStatus.forEach(function(el) {
      if (el.querySelector("span[style*=\"border-radius:50%\"]") || 
          el.textContent.includes("Offline") ||
          el.textContent.includes("Sleeping") ||
          el.textContent.includes("Active") ||
          el.textContent.includes("Away") ||
          el.textContent.includes("Online") ||
          el.textContent.includes("Last seen")) {
        el.innerHTML = "<span style=\"width:8px;height:8px;border-radius:50%;background:" + color + ";display:inline-block\"></span> " + text;
      }
    });
  }

  function showTypingDots(show) {
    // The typing dots are already rendered by the chat component
    // This just ensures status matches
  }

  if (document.readyState === "complete") { setTimeout(updateStatus, 500); }
  else { window.addEventListener("load", function() { setTimeout(updateStatus, 500); }); }
  setInterval(updateStatus, 5000); // Poll every 5 seconds
})();
