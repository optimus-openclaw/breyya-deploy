(function(){
  async function timeAgo(iso){
    const d = new Date(iso.replace(' ', 'T'));
    const diff = Math.floor((Date.now()-d.getTime())/1000);
    if (diff < 60) return diff + 's';
    if (diff < 3600) return Math.floor(diff/60) + 'm';
    if (diff < 86400) return Math.floor(diff/3600) + 'h';
    return Math.floor(diff/86400) + 'd';
  }

  function buildPostHtml(p){
    // Mirror existing feed post structure — classes must match
    const div = document.createElement('div');
    div.className = 'feed-module__Sej6XW__post';
    div.innerHTML = `
      <div class="post-header">
        <img class="avatar" src="${p.creator.avatar_url || '/images/default-avatar.png'}" alt="avatar">
        <div class="meta">
          <div class="author">${p.creator.display_name || 'Creator'}</div>
          <div class="time">${timeAgo(p.created_at)} ago</div>
        </div>
      </div>
      <div class="post-body">
        <div class="caption">${escapeHtml(p.caption)}</div>
        <div class="media">${p.type === 'video' ? `<video controls src="${p.media_url}" preload="metadata" class="post-media"></video>` : `<img src="${p.media_url}" class="post-media">`}</div>
      </div>
      <div class="post-actions">
        <button class="like-btn">❤ <span class="like-count">${p.like_count}</span></button>
        <a class="chat-link" href="/backstage/messages/?to=${encodeURIComponent(p.creator.display_name)}">Chat</a>
        <button class="tip-btn">Tip</button>
      </div>
    `;
    return div;
  }

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

  async function load(){
    try{
      const res = await fetch('/api/posts/list.php?limit=50');
      if (!res.ok) return; // leave static content
      const data = await res.json();
      if (!data.posts || data.posts.length === 0) return; // no posts yet
      const container = document.querySelector('.feed-module__Sej6XW');
      if (!container) return;
      container.innerHTML = '';
      for(const p of data.posts){
        const el = buildPostHtml(p);
        container.appendChild(el);
      }
    }catch(e){console.error(e)}
  }

  document.addEventListener('DOMContentLoaded', load);
})();