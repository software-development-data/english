'use strict';
(()=>{
 const $=id=>document.getElementById(id);let csrf='',topics=[],words=[],p=null,mode='all',limit=30,busy=false,pending=null,pendingKey='';
 const day=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Ho_Chi_Minh',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
 function msg(s){$('message').textContent=s;}
 async function api(action,body){
  const r=await fetch('/api/auth.php?action='+action,{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',headers:body?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{},body:body?JSON.stringify(body):undefined});
  if(r.status===401){location.replace('/index.html');throw Error('Vui lòng đăng nhập lại.');}
  let d;try{d=await r.json();}catch{throw Error('Máy chủ chưa trả dữ liệu hợp lệ.');}
  if(!r.ok||!d.ok){const error=Error(d.message||'Không kết nối được máy chủ.');error.status=r.status;throw error;}return d;
 }
 function stats(){
  $('word-count').textContent=words.length;$('topic-count').textContent=topics.length;$('xp').textContent=p.xp;
  $('due-count').textContent=words.filter(w=>p.reviews[w.id]&&p.reviews[w.id].due<=day()).length;
  const n=p.daily?.[day()]||0,g=p.dailyGoal||10;$('goal').value=g;$('goal-text').textContent=n+' / '+g+' lượt học'+(n>=g?' · Hoàn thành! ':' · Mỗi thẻ hoặc câu trả lời tính một lượt.');$('goal-progress').max=g;$('goal-progress').value=Math.min(n,g);
 }
 function element(tag,text,cls){const el=document.createElement(tag);if(text!==undefined)el.textContent=text;if(cls)el.className=cls;return el;}
 function button(text,callback,cls){const el=element('button',text,cls);el.type='button';el.disabled=busy;el.addEventListener('click',callback);return el;}
 function speak(text){if(!window.speechSynthesis){msg('Thiết bị chưa hỗ trợ giọng đọc.');return;}window.speechSynthesis.cancel();const u=new SpeechSynthesisUtterance(text);u.lang='en-US';u.rate=.85;u.onerror=()=>msg('Không phát được giọng đọc. Hãy kiểm tra giọng tiếng Anh trên thiết bị.');window.speechSynthesis.speak(u);}
 async function mutate(body){
  if(busy)return;if(pending&&body){msg('Có thao tác chờ lưu. Nhấn Thử kết nối lại để gửi lại thao tác cũ.');return;}busy=true;render();$('goal').disabled=true;msg('Đang lưu…');
  pending=pending||{...body,event:crypto.randomUUID()};
  try{localStorage.setItem(pendingKey,JSON.stringify(pending));}catch{}
  try{const s=await api('session');csrf=s.csrf;const d=await api('game_event',pending);p=d.progress;pending=null;try{localStorage.removeItem(pendingKey);}catch{}stats();msg('Đã lưu.');$('retry').hidden=true;}
  catch(e){if([400,404,409,415,422].includes(e.status)){pending=null;try{localStorage.removeItem(pendingKey);}catch{}msg(e.message);$('retry').hidden=true;}else{msg(e.message+' Thao tác đang chờ lưu; nhấn Thử kết nối lại.');$('retry').hidden=false;}}
  finally{busy=false;$('goal').disabled=false;render();}
 }
 function render(){
  const query=$('search').value.trim().toLocaleLowerCase('vi'),t=$('topic').value,l=$('level').value;
  const visible=words.filter(w=>(t==='all'||w.topic.id===t)&&(l==='all'||(w.topic.level||'A1')===l)&&(!query||[w.en,w.vi,w.example,w.topic.title].join(' ').toLocaleLowerCase('vi').includes(query))&&(mode==='all'||mode==='saved'&&(p.bookmarks||[]).includes(w.id)||mode==='due'&&p.reviews[w.id]&&p.reviews[w.id].due<=day()));
  $('results').textContent=visible.length+' từ phù hợp';$('cards').replaceChildren();
  if(!visible.length)$('cards').append(element('p',mode==='due'?'Chưa có thẻ đến hạn. Bạn có thể học từ mới trong học viện.':'Chưa tìm thấy từ. Thử đổi bộ lọc hoặc lưu thêm từ vào sổ.','empty'));
  for(const w of visible.slice(0,limit)){
   const card=element('article',undefined,'card'),top=element('div',undefined,'card-top');top.append(element('h3',w.en));const saved=(p.bookmarks||[]).includes(w.id);const star=button(saved?'':'',()=>mutate({op:'bookmark',topic:w.topic.id,index:w.index,saved:!saved}),saved?'saved':'');const mark=document.createElement('img');mark.src='/icons/quest/star.svg';mark.alt='';mark.width=26;mark.height=26;mark.style.opacity=saved?'1':'.45';star.append(mark);star.setAttribute('aria-label',(saved?'Bỏ lưu ':'Lưu ')+w.en);star.setAttribute('aria-pressed',String(saved));top.append(star);card.append(top,element('p',w.vi,'meaning'),element('span',(w.topic.level||'A1')+' · '+w.topic.title+(w.topic.source==='gemini'?' · AI':''),'meta'));
   if(w.ipa)card.append(element('small',w.ipa));
   const example=element('div',undefined,'example');example.append(element('p',w.example),element('small',w.translation));card.append(example);
   const actions=element('div',undefined,'actions');actions.append(button(' Nghe',()=>speak(w.en)),button(' Đã nhớ',()=>mutate({op:'learn',topic:w.topic.id,index:w.index})),button(' Ôn hôm nay',()=>mutate({op:'unknown',topic:w.topic.id,index:w.index})));card.append(actions);$('cards').append(card);
  }
  $('more').hidden=visible.length<=limit;
 }
 async function load(){try{
  msg('Đang tải sổ tay…');$('retry').hidden=true;const session=await api('session');if(!session.user){location.replace('/index.html');return;}csrf=session.csrf;
  pendingKey='english-quest-library-pending:'+session.user.id;
  try{pending=JSON.parse(localStorage.getItem(pendingKey)||'null');}catch{pending=null;}
  const [catalog,progress]=await Promise.all([api('game_catalog'),api('game_progress')]);topics=catalog.lessons;p=progress.progress;words=topics.flatMap(t=>t.words.map((w,index)=>({...w,topic:t,index,id:t.id+'-'+index})));
  $('topic').replaceChildren(new Option('Tất cả chủ đề','all'));topics.forEach(t=>$('topic').add(new Option(t.title,t.id)));['goal','search','topic','level'].forEach(id=>$(id).disabled=false);stats();render();msg(pending?'Có thao tác chờ lưu từ lần trước. Nhấn Thử kết nối lại.':'');$('retry').hidden=!pending;
 }catch(e){msg(e.message);$('retry').hidden=false;}}
 ['search','topic','level'].forEach(id=>$(id).addEventListener(id==='search'?'input':'change',()=>{limit=30;render();}));
 document.querySelectorAll('[data-mode]').forEach(b=>b.addEventListener('click',()=>{mode=b.dataset.mode;limit=30;document.querySelectorAll('[data-mode]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));if(p)render();}));
 $('more').addEventListener('click',()=>{limit+=30;render();});$('goal').addEventListener('change',()=>mutate({op:'goal',goal:Number($('goal').value)}));$('retry').addEventListener('click',()=>pending?mutate(null):load());window.addEventListener('pagehide',()=>window.speechSynthesis?.cancel());load();
})();
