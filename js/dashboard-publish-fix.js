/**
 * Dashboard Publish Fix — wires up the Publish button in the New Post modal.
 * Handles file upload + API call to /api/posts/create.php
 */
(function() {
  function init() {
    // Watch for the modal to appear
    var observer = new MutationObserver(function() {
      var publishBtn = document.querySelector(".btn.btn-primary.btn-block");
      if (publishBtn && !publishBtn._publishWired && publishBtn.textContent.trim() === "Publish") {
        publishBtn._publishWired = true;
        publishBtn.addEventListener("click", handlePublish);
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  async function handlePublish(e) {
    e.preventDefault();
    e.stopPropagation();

    var btn = e.target;
    btn.disabled = true;
    btn.textContent = "Publishing...";

    try {
      // Get the modal content
      var modal = btn.closest("[class*=modal]");
      if (!modal) { alert("Could not find modal"); btn.disabled = false; btn.textContent = "Publish"; return; }

      // Get post type
      var activeTypeBtn = modal.querySelector("[class*=typeBtnActive]");
      var postType = "photo";
      if (activeTypeBtn) {
        var txt = activeTypeBtn.textContent.toLowerCase();
        if (txt.includes("video")) postType = "video";
        else if (txt.includes("text")) postType = "text";
      }

      // Get caption
      var textarea = modal.querySelector("textarea");
      var caption = textarea ? textarea.value.trim() : "";

      // Get free post checkbox
      var checkbox = modal.querySelector("input[type=checkbox]");
      var isFree = checkbox ? checkbox.checked : false;

      // Get file
      var fileInput = modal.querySelector("input[type=file]");
      var file = fileInput && fileInput.files.length > 0 ? fileInput.files[0] : null;

      if (postType !== "text" && !file) {
        alert("Please select a file to upload");
        btn.disabled = false;
        btn.textContent = "Publish";
        return;
      }

      var mediaUrl = "";

      // Upload file if present
      if (file) {
        btn.textContent = "Uploading...";
        var formData = new FormData();
        formData.append("file", file);
        formData.append("type", postType);

        var uploadResp = await fetch("/api/posts/upload.php", {
          method: "POST",
          credentials: "include",
          body: formData
        });
        var uploadResult = await uploadResp.json();
        if (!uploadResult.ok) {
          alert("Upload failed: " + (uploadResult.error || "Unknown error"));
          btn.disabled = false;
          btn.textContent = "Publish";
          return;
        }
        mediaUrl = uploadResult.url;
      }

      // Create post
      btn.textContent = "Creating post...";
      var createResp = await fetch("/api/posts/create.php", {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          type: postType,
          caption: caption,
          media_url: mediaUrl,
          is_free: isFree ? 1 : 0
        })
      });
      var createResult = await createResp.json();

      if (createResult.ok) {
        btn.textContent = "Published! ✅";
        setTimeout(function() { location.reload(); }, 1000);
      } else {
        alert("Failed to create post: " + (createResult.error || "Unknown error"));
        btn.disabled = false;
        btn.textContent = "Publish";
      }
    } catch (err) {
      alert("Error: " + err.message);
      btn.disabled = false;
      btn.textContent = "Publish";
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
