(() => {
'use strict';
document.querySelectorAll('[data-session-copy]').forEach(button=>{button.hidden=false;});
const setup=document.querySelector('[data-volunteer-setup]');
if(setup){
 const model=setup.elements.model,effort=setup.elements.reasoning,scope=setup.elements.scope_kind,advanced=setup.querySelector('[data-volunteer-advanced]');
 const registry=JSON.parse(document.getElementById('volunteer-ai-options').content.textContent),models=new Map(registry.map(item=>[item.id,item]));
 const ai=setup.querySelector('[data-ai-open]'),summary=setup.querySelector('[data-ai-summary]'),time=setup.querySelector('[data-time-body]'),heading=setup.querySelector('[data-time-heading]');
 const selectedPreset=()=>setup.querySelector('[name=session_preset]:checked');
 const effortLabel=value=>value==='xhigh'?'Extra High':value.charAt(0).toUpperCase()+value.slice(1);
 let timeWasShown=setup.dataset.step==='time';
 const timeSummary=()=>{const radio=selectedPreset();if(radio?.value==='custom')return 'Custom · '+setup.elements.minutes.value+' min or '+setup.elements.assignment_limit.value+' '+(Number(setup.elements.assignment_limit.value)===1?'task':'tasks');const card=radio?.closest('.setup-time-card');return card?card.querySelector('.setup-time-name>span').textContent+' · '+card.querySelector('.setup-time-caption').textContent:'Choose your time';};
 const setStep=(step,focus=false)=>{
  setup.dataset.step=step;ai.hidden=step!=='ai';summary.hidden=step==='ai';time.hidden=step!=='time';
  if(step==='time')timeWasShown=true;
  heading.textContent=step==='time'?'2. How much time do you have?':('2. '+(timeWasShown?timeSummary():'Choose your time'));
  setup.querySelector('[data-ai-summary-text]').textContent='1. '+(models.get(model.value)?.label||model.value)+' · '+effortLabel(effort.value)+' reasoning';
  if(focus)(step==='ai'?setup.querySelector('#setup-ai-heading'):heading).focus({preventScroll:true});
 };
 const validateAi=()=>{
  model.setCustomValidity(models.has(model.value)?'':'Choose a supported AI model.');
  effort.setCustomValidity(models.get(model.value)?.efforts.includes(effort.value)?'':'Choose a reasoning level supported by this AI model.');
  const invalid=[model,effort].find(field=>!field.checkValidity());
  if(invalid){setStep('ai');invalid.reportValidity();return false;}return true;
 };
 const revealTime=()=>{if(validateAi())setStep('time',true);};
 const updateScope=()=>{
  for(const el of setup.querySelectorAll('[data-volunteer-scope]'))el.hidden=el.dataset.volunteerScope!==scope.value;
  setup.elements.category_id.required=scope.value==='category';setup.elements.research_id.required=scope.value==='research';
 };
 const updateTime=()=>{setup.querySelector('[data-custom-active]').hidden=selectedPreset()?.value!=='custom';};
 const presetLimits={quick:[15,1],standard:[60,3],long:[180,10]};
 setup.noValidate=true;setup.classList.add('is-enhanced');setup.querySelector('[data-ai-continue]').hidden=false;
 setup.querySelector('[data-ai-continue]').addEventListener('click',revealTime);
 setup.querySelector('[data-ai-change]').addEventListener('click',()=>setStep('ai',true));
 model.addEventListener('change',()=>{
  const current=effort.value,definition=models.get(model.value);effort.replaceChildren();
  for(const value of definition?.efforts||[]){const option=document.createElement('option');option.value=value;option.textContent=effortLabel(value);effort.append(option);}
  effort.value=definition?.efforts.includes(current)?current:(definition?.efforts.includes('max')?'max':definition?.efforts[0]||'');
  if(setup.elements.tool_source.value==='default')setup.elements.tool.value=definition?.tool||'';
  model.setCustomValidity('');effort.setCustomValidity('');
 });
 effort.addEventListener('change',()=>effort.setCustomValidity(''));
 setup.elements.tool.addEventListener('input',()=>{setup.elements.tool_source.value='explicit';});
 setup.addEventListener('input',event=>{
  if(['minutes','assignment_limit'].includes(event.target.name)){setup.querySelector('[name=session_preset][value=custom]').checked=true;updateTime();}
 });
 setup.addEventListener('change',event=>{
  if(event.target.name==='session_preset'){const limits=presetLimits[event.target.value];if(limits){setup.elements.minutes.value=limits[0];setup.elements.assignment_limit.value=limits[1];}updateTime();}
  if(event.target===scope)updateScope();
 });
 setup.addEventListener('submit',event=>{
  if(setup.dataset.step==='ai'){event.preventDefault();revealTime();return;}
  if(!validateAi()){event.preventDefault();return;}
  const fields=[setup.elements.tool,setup.elements.session_profile,setup.elements.concurrent_limit];
  if(scope.value==='category')fields.push(setup.elements.category_id);if(scope.value==='research')fields.push(setup.elements.research_id);
  if(selectedPreset()?.value==='custom')fields.push(setup.elements.minutes,setup.elements.assignment_limit);
  const invalid=fields.find(field=>!field.checkValidity());
  if(invalid){event.preventDefault();advanced.open=true;invalid.reportValidity();}
 });
 updateScope();updateTime();setStep(setup.dataset.step);
 const error=document.querySelector('[data-setup-error]');if(error){if(setup.dataset.step==='time')advanced.open=true;error.focus({preventScroll:true});}
}
for(const button of document.querySelectorAll('[data-copy-continuation]')){button.hidden=false;button.addEventListener('click',async()=>{const text=document.getElementById('continuation-start-message'),status=document.querySelector('[data-continuation-copy-status]');try{await navigator.clipboard.writeText(text.value);status.textContent='Continuation message copied. Paste this one message into your AI and send.';}catch{document.querySelector('[data-continuation-manual]').open=true;text.focus();text.select();status.textContent='Clipboard access failed. Copy the complete short message manually.';}});}
const exchange=document.querySelector('[data-volunteer-exchange]');
if(exchange && location.hash){
 const secret=location.hash.slice(1);history.replaceState(null,'',location.pathname);
 exchange.elements.session_secret.value=secret;exchange.requestSubmit();
}
})();
