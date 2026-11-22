(() => {
'use strict';
document.querySelectorAll('[data-session-copy]').forEach(button=>{button.hidden=false;});
const setup=document.querySelector('[data-volunteer-setup]');
if(setup){
 const scope=setup.elements.scope_kind,advanced=setup.querySelector('[data-volunteer-advanced]');
 const selectedPreset=()=>setup.querySelector('[name=session_preset]:checked');
 const updateScope=()=>{
  for(const el of setup.querySelectorAll('[data-volunteer-scope]'))el.hidden=el.dataset.volunteerScope!==scope.value;
  setup.elements.category_id.required=scope.value==='category';setup.elements.research_id.required=scope.value==='research';
 };
 const updateTime=()=>{setup.querySelector('[data-custom-active]').hidden=selectedPreset()?.value!=='custom';};
 const presetLimits={quick:[15,1],standard:[60,3],long:[180,10]};
 setup.noValidate=true;setup.classList.add('is-enhanced');
 setup.addEventListener('input',event=>{
  if(['minutes','assignment_limit'].includes(event.target.name)){setup.querySelector('[name=session_preset][value=custom]').checked=true;updateTime();}
 });
 setup.addEventListener('change',event=>{
  if(event.target.name==='session_preset'){const limits=presetLimits[event.target.value];if(limits){setup.elements.minutes.value=limits[0];setup.elements.assignment_limit.value=limits[1];}updateTime();}
  if(event.target===scope)updateScope();
 });
 setup.addEventListener('submit',event=>{
  const fields=[setup.elements.session_profile,scope,setup.elements.concurrent_limit];
  if(scope.value==='category')fields.push(setup.elements.category_id);if(scope.value==='research')fields.push(setup.elements.research_id);
  if(selectedPreset()?.value==='custom')fields.push(setup.elements.minutes,setup.elements.assignment_limit);
  const invalid=fields.find(field=>!field.checkValidity());
  if(invalid){event.preventDefault();if(advanced.contains(invalid))advanced.open=true;invalid.reportValidity();}
 });
 updateScope();updateTime();
 document.querySelector('[data-setup-error]')?.focus({preventScroll:true});
}
for(const button of document.querySelectorAll('[data-copy-continuation]')){button.hidden=false;button.addEventListener('click',async()=>{const text=document.getElementById('continuation-start-message'),status=document.querySelector('[data-continuation-copy-status]');try{await navigator.clipboard.writeText(text.value);status.textContent='Continuation message copied. Paste this one message into your AI and send.';}catch{document.querySelector('[data-continuation-manual]').open=true;text.focus();text.select();status.textContent='Clipboard access failed. Copy the complete short message manually.';}});}
const exchange=document.querySelector('[data-volunteer-exchange]');
if(exchange && location.hash){
 const secret=location.hash.slice(1);history.replaceState(null,'',location.pathname);
 exchange.elements.session_secret.value=secret;exchange.requestSubmit();
}
})();
