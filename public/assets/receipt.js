(() => {
 'use strict';
 const receipt=document.querySelector('[data-management-receipt]');
 if(!receipt)return;
 const input=document.getElementById('management-link');
 const dialog=document.getElementById('management-save-dialog');
 const confirmation=document.getElementById('management-saved-confirmation');
 const message=document.getElementById('management-copy-status');
 const note=document.getElementById('management-save-copy-note');
 const buttons=[...document.querySelectorAll('[data-management-copy]')];
 let copied=false,saved=false,copying=false,copyError=false,pendingDestination=null;

 // Receipt-only memory: never store the private link or its secret in Web Storage.
 function warnBeforeLeaving(event){
  if(saved)return;
  event.preventDefault();event.returnValue='';
 }
 window.addEventListener('beforeunload',warnBeforeLeaving);
 function showConfirmation(){
  note.textContent=copyError
   ?'Automatic copy was unavailable. Return to the link, copy it manually, and save it before confirming.'
   :copied?'Your management link was copied. Paste it somewhere safe before confirming.'
   :'You have not used Copy management link yet. Copy it now, or save the link manually.';
  if(!dialog.open){confirmation.checked=false;dialog.showModal();}
 }
 async function copyLink(){
  if(copying)return;
  copying=true;buttons.forEach(button=>{button.disabled=true;});
  try{
   await navigator.clipboard.writeText(input.value);
   copied=true;copyError=false;
   message.textContent='Link copied. Save it somewhere safe.';
  }catch{
   copyError=true;
   message.textContent='Automatic copy was unavailable. Select and copy the complete link manually.';
  }finally{
   copying=false;buttons.forEach(button=>{button.disabled=false;});
   if(!saved)showConfirmation();
  }
 }
 buttons.forEach(button=>{button.hidden=false;button.addEventListener('click',copyLink);});
 document.getElementById('management-return-to-link').addEventListener('click',()=>{
  pendingDestination=null;dialog.close();input.focus();input.select();
 });
 dialog.addEventListener('cancel',()=>{pendingDestination=null;});
 dialog.querySelector('form').addEventListener('submit',event=>{
  event.preventDefault();
  if(!confirmation.checked){confirmation.reportValidity();return;}
  saved=true;window.removeEventListener('beforeunload',warnBeforeLeaving);
  message.textContent='You confirmed that your management link is saved.';
  const destination=pendingDestination;pendingDestination=null;dialog.close();
  if(destination)location.assign(destination);
 });
 document.addEventListener('click',event=>{
  if(saved||event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
  const link=event.target.closest('a[href]');
  if(!link||link.hasAttribute('download')||(link.target&&link.target!=='_self'))return;
  const destination=new URL(link.href,location.href);
  if(!['http:','https:'].includes(destination.protocol))return;
  if(destination.origin===location.origin&&destination.pathname===location.pathname&&destination.search===location.search&&destination.hash)return;
  event.preventDefault();pendingDestination=destination.href;showConfirmation();
 },true);
})();
