'use strict';
// Deliberate actions only: no page-load claim, polling or renewal timer.
document.querySelectorAll('[data-copy-pickup]').forEach(button=>{button.hidden=false;});
document.addEventListener('click',async event=>{
 const button=event.target.closest('[data-copy-pickup]');if(!button)return;
 const text=document.getElementById(button.dataset.copyPickup),message=document.querySelector('[data-pickup-status]');
 const global=document.getElementById('copy-status');if(global)global.textContent='';
 try{await navigator.clipboard.writeText(text.value);message.textContent='Copied. Ready to paste. Recopying did not renew the reservation.';if(global)global.textContent='Copied. Ready to paste.';}
 catch{message.textContent='Clipboard access is unavailable. '+(document.querySelector('[data-reservation-status]')?.textContent.trim()||'Check task availability before working.')+' Select and copy the complete handoff below manually. Copying does not acquire or renew a reservation.';if(global)global.textContent='Clipboard unavailable. Copy the complete handoff manually.';text.focus();text.select();}
});
document.addEventListener('submit',async event=>{
 const button=event.submitter;if(!button?.value?.startsWith('reservation-'))return;
 const form=event.target;if(!form.matches('[data-reservation-form],[data-workflow-form]'))return;
 event.preventDefault();button.disabled=true;
 const body=new FormData(form);body.set('operation',button.value);
 const current=document.querySelector('[data-reservation-panel]');
 try{
  const response=await fetch(form.action||location.href,{method:'POST',body,credentials:'same-origin',headers:{Accept:'application/json'}});
  if(!response.headers.get('content-type')?.includes('application/json'))throw Error('Reservation status could not be confirmed. Keep your work and reload availability before retrying.');
  const data=await response.json();
  const template=document.createElement('template');template.innerHTML=data.html;
  if(current&&template.content.firstElementChild)current.replaceWith(template.content.firstElementChild);
 }catch(error){const message=current?.querySelector('[data-reservation-status]');if(message)message.textContent=error.message;}
 finally{button.disabled=false;}
});
