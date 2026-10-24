(() => {
'use strict';
document.querySelectorAll('[data-session-copy]').forEach(button=>{button.hidden=false;});
const setup=document.querySelector('[data-volunteer-setup]');
if(setup){
 const scope=setup.elements.scope_kind,category=setup.elements.category_id,research=setup.elements.research_id;
 const chosen=name=>setup.querySelector('input[name="'+name+'"]:checked');
 const select=(name,value)=>{const item=setup.querySelector('input[name="'+name+'"][value="'+value+'"]');if(item)item.checked=true;};
 const update=()=>{
  const preset=chosen('session_preset')?.value||'custom',cause=chosen('cause_choice');
  if(cause?.value==='all')scope.value='all';
  else if(cause?.value.startsWith('category:')){scope.value='category';category.value=cause.value.slice(9);}
  setup.querySelectorAll('[data-volunteer-scope]').forEach(el=>{el.hidden=el.dataset.volunteerScope!==scope.value;});
  for(const field of ['minutes','assignment_limit'])setup.elements[field].disabled=preset!=='custom';
  const limits={quick:'15 min or 1 task',standard:'60 min or 3 tasks',long:'180 min or 10 tasks',unlimited:'Until Stop'};
  const time=limits[preset]||(setup.elements.minutes.value+' min or '+setup.elements.assignment_limit.value+' tasks');
  let place=cause?.dataset.causeLabel||'Wherever needed';
  if(cause?.value==='custom')place=scope.value==='research'?'One research question':scope.value==='category'?(category.selectedOptions[0]?.textContent||'Choose a category'):'Wherever needed';
  setup.querySelector('[data-volunteer-summary]').textContent=time+' · '+place;
 };
 setup.addEventListener('input',event=>{
  if(['minutes','assignment_limit'].includes(event.target.name))select('session_preset','custom');
  if(['scope_kind','category_id','research_id'].includes(event.target.name)){select('cause_choice','custom');if(event.target===category)scope.value='category';if(event.target===research)scope.value='research';}
  update();
 });
 setup.addEventListener('change',update);update();
}
const consent=document.querySelector('[data-volunteer-consent]');
if(consent){
 const disclosure=consent.elements.human_level;
 const update=()=>{if(['influenced','unsure'].includes(disclosure.value))consent.querySelector('[data-declaration-details]').open=true;};
 disclosure.addEventListener('change',update);update();
}
const exchange=document.querySelector('[data-volunteer-exchange]');
if(exchange && location.hash){
 const secret=location.hash.slice(1);history.replaceState(null,'',location.pathname);
 exchange.elements.session_secret.value=secret;exchange.requestSubmit();
}
})();
