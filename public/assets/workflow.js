'use strict';
document.querySelectorAll('[data-workflow-form]').forEach(form=>{
 const field=form.querySelector('[name=payload]'),message=form.querySelector('[data-workflow-error]'),panel=form.querySelector('[data-repair-panel]'),repair=form.querySelector('[data-repair-text]'),copy=form.querySelector('[data-copy-repair]');
 let version=0;
 form.addEventListener('input',()=>{version++;message.textContent='Packet or disclosure changed. Check again before submitting.';panel.hidden=true;});
 form.addEventListener('submit',async event=>{
  if(event.submitter?.value!=='check')return;
  event.preventDefault();const button=event.submitter,requestVersion=version;button.disabled=true;message.textContent='Checking task, signed identifiers, outcome, evidence and disclosures…';panel.hidden=true;
  try{
   const body=new FormData(form);body.set('operation','check');
   const response=await fetch(form.action||location.href,{method:'POST',body,credentials:'same-origin',headers:{Accept:'application/json'}});
   if(!response.headers.get('content-type')?.includes('application/json'))throw Error('The check could not finish. Reload this page and try again; keep your original AI output.');
   const data=await response.json();if(version!==requestVersion){message.textContent='The packet or disclosure changed during the check. Check it again.';return;}
   message.textContent=(data.ok?'':'Not accepted — nothing advanced. ')+data.message+(data.influence_review?' This packet will be quarantined until an admin approves it. Research will not advance.':'');
   message.classList.toggle('warning',!data.ok);
   const reacquire=form.querySelector('[data-session-reacquire]');
   if(reacquire)reacquire.hidden=!data.session_reacquire;
   if(!data.ok){repair.value=data.repair_prompt||'';panel.hidden=!repair.value;}
  }catch(error){message.textContent=error.message;}finally{button.disabled=false;}
 });
 copy.addEventListener('click',async()=>{
  const status=form.querySelector('[data-repair-status]');
  try{await navigator.clipboard.writeText(repair.value);status.textContent='Copied. Send this to the same AI conversation, then paste its entire new packet unchanged.';}
  catch{panel.querySelector('details').open=true;repair.focus();repair.select();status.textContent='Clipboard unavailable. Copy the selected repair prompt manually.';}
 });
});
