
const API='https://api.mcsrvstat.us/3/mc.preservedrealm.online';
async function updateServerStatus(){
 const statusEl=document.querySelectorAll('[data-status]');
 const onlineEl=document.querySelectorAll('[data-online]');
 const maxEl=document.querySelectorAll('[data-max]');
 try{
  const response=await fetch(API,{cache:'no-store'});
  if(!response.ok) throw new Error('status unavailable');
  const data=await response.json();
  statusEl.forEach(el=>{el.textContent=data.online?'Сервер онлайн':'Сервер офлайн';const dot=el.querySelector('.dot');if(dot)dot.classList.toggle('off',!data.online);});
  onlineEl.forEach(el=>el.textContent=data.online&&data.players?data.players.online:'—');
  maxEl.forEach(el=>el.textContent=data.online&&data.players?data.players.max:'—');
 }catch(e){statusEl.forEach(el=>{el.textContent='Статус временно недоступен';const dot=el.querySelector('.dot');if(dot)dot.classList.add('off')});onlineEl.forEach(el=>el.textContent='—');maxEl.forEach(el=>el.textContent='—')}
}
document.querySelectorAll('[data-copy-ip]').forEach(btn=>btn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText('mc.preservedrealm.online');btn.textContent='Скопировано ✓';setTimeout(()=>btn.textContent='Копировать IP',1800)}catch(e){window.prompt('Скопируй IP сервера:','mc.preservedrealm.online')}}));
const toggle=document.querySelector('.mobile-toggle');if(toggle)toggle.addEventListener('click',()=>document.querySelector('.links')?.classList.toggle('open'));
updateServerStatus();setInterval(updateServerStatus,60000);
