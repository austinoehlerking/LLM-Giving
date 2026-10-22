// Shared, deterministic UI/import matching. No network, storage or provider calls.
export const effortOrder=['low','medium','high','xhigh','max','ultra'];
const list=(value,allowed,label)=>{
 if(!Array.isArray(value)||value.length>20||value.some(v=>typeof v!=='string'||!allowed.includes(v)))throw Error('Choose supported '+label+' options.');
 const unique=[...new Set(value)];
 if(unique.includes('any')&&unique.length>1)throw Error('Any cannot be combined with specific '+label+' options.');
 return allowed.filter(v=>v!=='any'&&unique.includes(v));
};
export function matchesChoice(choice,registry,model,effort,mode='',tool=''){
 if(choice.models.length&&!choice.models.includes(model))return false;
 const support=registry[model];
 if(support&&effortOrder.includes(effort)&&!support.efforts.includes(effort))return false;
 if(mode&&(mode!=='ultracode'||!support?.modes.includes(mode)||!support.efforts.includes(effort)||tool!=='Claude Code'))return false;
 if(choice.modes.length&&!choice.modes.includes(mode))return false;
 if(!choice.efforts.length)return true;
 if(!support)return false;
 return choice.efforts.some(c=>support.efforts.includes(c.level)&&support.efforts.includes(effort)&&(c.or_higher?support.efforts.indexOf(effort)>=support.efforts.indexOf(c.level):effort===c.level));
}
export function parseChoices(values,config){
 const models=list(values.required_models??[],['any',...Object.keys(config.registry)],'model');
 const levels=list(values.reasoning_levels??[],['any',...effortOrder,'ultracode'],'reasoning');
 const higher=list(values.reasoning_or_higher??[],effortOrder.slice(0,-1),'or higher');
 if(higher.some(v=>!levels.includes(v)))throw Error('Select the effort before enabling its or higher option.');
 const choice={models,efforts:effortOrder.filter(level=>levels.includes(level)).map(level=>({level,or_higher:higher.includes(level)})),modes:levels.includes('ultracode')?['ultracode']:[]};
 const possible=config.policy.some(p=>p.efforts.some(effort=>['','ultracode'].some(mode=>matchesChoice(choice,config.registry,p.model,effort,mode,mode?'Claude Code':effort==='ultra'?p.ultra_tool:''))));
 if(!possible)throw Error('These choices leave no qualifying planning configuration. Planning requires an approved model at Max or Ultra. Select a compatible effort, enable or higher, add another model, or choose Any. Ultracode still requires an eligible actual effort and Claude Code.');
 return choice;
}
export function describeChoices(choice,registry){
 const resolved=registry&&(choice.models.length||choice.efforts.length||choice.modes.length)?Object.entries(registry).filter(([model])=>!choice.models.length||choice.models.includes(model)).map(([model,support])=>{
  const mode=choice.modes[0]||'';
  const levels=support.efforts.filter(effort=>matchesChoice(choice,registry,model,effort,mode,mode?'Claude Code':''));
  return model+': '+(levels.join(', ')||'no matching effort');
 }).join('; '):'';

 return 'Models: '+(choice.models.join(' OR ')||'Any — no additional model restriction')+'. Effort alternatives: '+(choice.efforts.map(c=>c.level+(c.or_higher?' or higher within the actual model':' exactly')).join(' OR ')||'Any')+'. Workflow mode: '+(choice.modes.length?'Ultracode REQUIRED (Claude Code), AND the effort choices above':'Any')+'. Platform planning policy still applies.'+(resolved?' Resolved requester settings: '+resolved+'.':'');
}
export function readChoices(root){
 const checked=name=>[...root.querySelectorAll('[name="'+name+'[]"]:checked')].map(el=>el.value).filter(v=>v!=='any');
 return {required_models:checked('required_models'),reasoning_levels:checked('reasoning_levels'),reasoning_or_higher:checked('reasoning_or_higher')};
}
export function setChoices(root,values){
 for(const name of ['required_models','reasoning_levels','reasoning_or_higher']){
  const selected=values[name]??[];
  for(const el of root.querySelectorAll('[name="'+name+'[]"]'))el.checked=el.value==='any'?!selected.length:selected.includes(el.value);
 }
 root.dispatchEvent(new CustomEvent('ai-selection-update',{bubbles:true}));
}
if(typeof document!=='undefined')for(const root of document.querySelectorAll('[data-ai-selection]')){
 if(root.dataset.aiSelectionReady)continue;
 root.dataset.aiSelectionReady='true';
 const config=JSON.parse(root.querySelector('[data-ai-config]').content.textContent);
 const form=root.closest('form'),anchor=root.querySelector('input[type=checkbox]');
 const sync=()=>{
  const values=readChoices(root);
  for(const el of root.querySelectorAll('[name="reasoning_or_higher[]"]')){el.disabled=!values.reasoning_levels.includes(el.value);if(el.disabled)el.checked=false;}
  const current=readChoices(root);
  root.querySelector('[data-ai-model-summary]').textContent=current.required_models.join(' OR ')||'Any';
  root.querySelector('[data-ai-effort-summary]').textContent=(current.reasoning_levels.filter(v=>v!=='ultracode').map(v=>v+(current.reasoning_or_higher.includes(v)?' or higher':' exactly')).join(' OR ')||'Any effort')+(current.reasoning_levels.includes('ultracode')?' AND Ultracode (mode)':'');
  let error='';try{const unchangedLegacy=config.legacy_values&&Object.keys(current).every(key=>JSON.stringify([...current[key]].sort())===JSON.stringify([...config.legacy_values[key]].sort()));if(unchangedLegacy){root.querySelector('[data-ai-summary]').textContent='Historical requirements retained unchanged. '+config.legacy_description;}else{const choices=parseChoices(current,config);root.querySelector('[data-ai-summary]').textContent=describeChoices(choices,config.registry);}}catch(e){error=e.message;root.querySelector('[data-ai-summary]').textContent='Resolve the configuration below before submitting.';}
  anchor.setCustomValidity(error);root.querySelector('[data-ai-error]').textContent=error;root.querySelector('[data-ai-error]').hidden=!error;
  if(error)root.setAttribute('aria-invalid','true');else root.removeAttribute('aria-invalid');
 };
 root.addEventListener('change',event=>{
  const el=event.target;
  if(!el.matches('[data-ai-choice]'))return;
  if(el.name!=='reasoning_or_higher[]'){
   const group=[...root.querySelectorAll('[name="'+el.name+'"]')],any=group.find(input=>input.value==='any');
   if(el.checked&&el===any){group.forEach(input=>{if(input!==any)input.checked=false;});if(el.name==='reasoning_levels[]')root.querySelectorAll('[name="reasoning_or_higher[]"]').forEach(input=>input.checked=false);}
   else if(el.checked)any.checked=false;
   if(!group.some(input=>input.checked))any.checked=true;
  }
  sync();
 });
 root.addEventListener('keydown',event=>{if(event.key==='Escape'){const menu=event.target.closest('.ai-choice-menu');if(menu){menu.open=false;menu.querySelector('summary').focus();}}});
 root.addEventListener('ai-selection-update',sync);
 form.addEventListener('reset',()=>setTimeout(sync,0));
 form.addEventListener('submit',event=>{sync();if(!form.reportValidity())event.preventDefault();});
 anchor.addEventListener('invalid',()=>{for(const details of [root.closest('details'),root.querySelector('[data-ai-model-menu]')])if(details)details.open=true;});
 sync();
}
