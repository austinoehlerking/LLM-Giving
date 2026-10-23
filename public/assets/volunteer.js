(()=>{
'use strict';
const setup=document.querySelector('[data-volunteer-setup]');
if(setup){
 const scope=setup.elements.scope_kind;
 const update=()=>{
  setup.querySelectorAll('[data-volunteer-scope]').forEach(el=>{el.hidden=el.dataset.volunteerScope!==scope.value;});
  const details=setup.querySelector('[data-scope-details]');
  details.hidden=scope.value==='all';details.open=scope.value!=='all';
 };
 scope.addEventListener('change',update);update();
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
