(function(){
  try{
    // only run on backstage hub
    if (!location.pathname.startsWith('/backstage')) return;
    const btn = document.createElement('a');
    btn.href = '/backstage/upload/';
    btn.textContent = 'Upload Post';
    btn.style.position='fixed';
    btn.style.right='18px';
    btn.style.bottom='18px';
    btn.style.zIndex=9999;
    btn.style.background='#00b4ff';
    btn.style.color='#042033';
    btn.style.padding='10px 14px';
    btn.style.borderRadius='8px';
    btn.style.boxShadow='0 6px 14px rgba(0,0,0,0.4)';
    btn.style.fontFamily='Inter, system-ui, sans-serif';
    document.addEventListener('DOMContentLoaded', ()=>document.body.appendChild(btn));
  }catch(e){console.error(e)}
})();