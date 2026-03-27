/**
 * Typing indicator v1 — shows bouncing dots in chat when Breyya is typing.
 * Polls last-active.php, injects/removes dot bubble in message area.
 */
(function() {
  if (window.location.pathname.indexOf("/chat") !== 0) return;

  var fanId = 0;
  try {
    var token = localStorage.getItem("jwt_token") || localStorage.getItem("token");
    if (token) {
      var payload = JSON.parse(atob(token.split(".")[1]));
      fanId = payload.user_id || payload.sub || 0;
    }
  } catch(e) {}

  var wasTyping = false;
  var dotEl = null;

  function createDotBubble() {
    if (dotEl) return;
    dotEl = document.createElement("div");
    dotEl.id = "breyya-typing-dots";
    dotEl.setAttribute("style",
      "display:flex;align-items:center;gap:4px;padding:10px 16px;" +
      "margin:8px 60px 8px 12px;width:fit-content;" +
      "background:#1a2a3a;border-radius:18px 18px 18px 4px;" +
      "animation:typingFadeIn 0.3s ease;"
    );
    dotEl.innerHTML =
      '<span class="tdot" style="width:8px;height:8px;background:#667;border-radius:50%;animation:tdotBounce 1.4s infinite ease-in-out"></span>' +
      '<span class="tdot" style="width:8px;height:8px;background:#667;border-radius:50%;animation:tdotBounce 1.4s infinite ease-in-out 0.2s"></span>' +
      '<span class="tdot" style="width:8px;height:8px;background:#667;border-radius:50%;animation:tdotBounce 1.4s infinite ease-in-out 0.4s"></span>';

    if (!document.getElementById("typing-dot-styles")) {
      var style = document.createElement("style");
      style.id = "typing-dot-styles";
      style.textContent =
        "@keyframes tdotBounce { 0%,60%,100% { transform:translateY(0); } 30% { transform:translateY(-6px); } }" +
        "@keyframes typingFadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }";
      document.head.appendChild(style);
    }

    var msgContainer = document.querySelector("[class*=messages]");
    if (msgContainer) {
      msgContainer.appendChild(dotEl);
      msgContainer.scrollTop = msgContainer.scrollHeight;
    }
  }

  function removeDotBubble() {
    if (dotEl) {
      dotEl.remove();
      dotEl = null;
    }
  }

  function poll() {
    var url = "/api/chat/last-active.php";
    if (fanId) url += "?fan_id=" + fanId;

    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.is_typing || d.has_pending) {
          if (!wasTyping) {
            createDotBubble();
            wasTyping = true;
          }
        } else {
          if (wasTyping) {
            removeDotBubble();
            wasTyping = false;
          }
        }
      })
      .catch(function() {});
  }

  if (document.readyState === "complete") setTimeout(function() { poll(); setInterval(poll, 3000); }, 1000);
  else window.addEventListener("load", function() { setTimeout(function() { poll(); setInterval(poll, 3000); }, 1000); });

  var observer = new MutationObserver(function() {
    if (dotEl) {
      var msgContainer = document.querySelector("[class*=messages]");
      if (msgContainer && msgContainer.lastElementChild !== dotEl) {
        removeDotBubble();
        wasTyping = false;
      }
    }
  });

  setTimeout(function() {
    var msgContainer = document.querySelector("[class*=messages]");
    if (msgContainer) {
      observer.observe(msgContainer, { childList: true });
    }
  }, 2000);
})();
