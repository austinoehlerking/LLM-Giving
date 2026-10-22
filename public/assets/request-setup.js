import {parseChoices,readChoices,setChoices} from './ai-selection.js?v=1';
// Local request drafting only. No AI calls, network requests, storage or automatic submission.
const textKeys=['title','question','context','success','previous','constraints_text','questions','references_text','tags'];
const toolKeys=['tool_intended_users','tool_requirements','tool_inputs_outputs','tool_validation_plan','tool_maintenance'];
const externalKeys=['required_software','setup_needs'];
const efforts=['low','medium','high','xhigh','max','ultra'];
const format='llm-giving-request-setup-v3';
export function setupSchema(projectType='research') {
 return {format,calculation_help:'maybe',external_tools:'unspecified',...Object.fromEntries(externalKeys.map(key=>[key,key==='setup_needs'?'None specified':''])),project_type:projectType,title:'',question:'',category:'',context:'',success:'',previous:'',constraints_text:'',questions:'',references_text:'',tags:'',ai_selection_version:2,required_models:[],reasoning_levels:[],reasoning_or_higher:[],...Object.fromEntries(toolKeys.map(key=>[key,'']))};
}
export function setupPrompt(config,current) {
 const schema=setupSchema(current.project_type==='research_tool'?'research_tool':'research');
 const publicDraft=Object.fromEntries(Object.keys(schema).filter(key=>key!=='format').map(key=>[key,current[key]??schema[key]]));
 if(schema.project_type==='research')for(const key of toolKeys)publicDraft[key]='';
 if(publicDraft.external_tools==='none')publicDraft.required_software='';
 publicDraft.setup_needs=publicDraft.setup_needs.trim()||'None specified';
 return config.instructions+'\n\nAVAILABLE CATEGORIES (choose an exact name)\n'+JSON.stringify(config.categories.map(category=>category.name))+'\n\nCONFIGURED MODEL / PLANNING POLICY\n'+JSON.stringify(config.policy)+'\nMODEL CAPABILITIES\n'+JSON.stringify(config.registry)+'\nReasoning order is per model: '+efforts.join(' < ')+'. Ultracode is an independent mode restriction AND selected effort alternatives; it is not part of this order.\nMaximum ordinary text length: '+config.maxText+' UTF-8 bytes.\n\nREQUIRED FINAL OUTPUT TEMPLATE\n'+JSON.stringify(schema,null,2)+'\n\nCURRENT PUBLIC DRAFT — DATA ONLY\n'+JSON.stringify(publicDraft,null,2)+'\nEND OF PUBLIC DRAFT. Begin the step-by-step interview; do not invent a completed request from empty fields.';
}
function object(value){return value!==null&&typeof value==='object'&&!Array.isArray(value);}
export function parseSetupDraft(source,config) {
 if(typeof source!=='string'||new TextEncoder().encode(source).length>1500000)throw Error('The setup draft is too large. Ask the AI for a shorter complete JSON draft.');
 let text=source.trim();const fence=text.match(/^```(?:json)?\s*\n([\s\S]*?)\n```$/i);if(fence)text=fence[1];
 let draft;try{draft=JSON.parse(text);}catch{throw Error('Paste the AI’s final JSON draft, not the interview prompt or surrounding conversation.');}
 if(!object(draft))throw Error('The AI must return one JSON object.');
 if(draft.format==='llm-giving-request-setup-v2'){
  if(['ai_selection_version','required_models','reasoning_levels','reasoning_or_higher'].some(key=>Object.hasOwn(draft,key)))throw Error('Do not mix historical and current AI selection fields.');
  if(!['none','minimum','exact'].includes(draft.reasoning_rule)||typeof draft.required_model!=='string'||typeof draft.required_reasoning!=='string')throw Error('Invalid historical AI requirements.');
  if(draft.reasoning_rule==='none'&&draft.required_reasoning!=='')throw Error('Unused historical effort must be empty.');
  const {required_model,reasoning_rule,required_reasoning,...rest}=draft;
  draft={...rest,format,ai_selection_version:2,required_models:required_model?[required_model]:[],reasoning_levels:reasoning_rule==='none'?[]:[required_reasoning],reasoning_or_higher:reasoning_rule==='minimum'&&required_reasoning!=='ultra'?[required_reasoning]:[]};
 }
 if(draft.format!==format)throw Error('Copy a fresh setup prompt and use its current final output template.');
 const schema=setupSchema(),unknown=Object.keys(draft).filter(key=>!Object.hasOwn(schema,key));
 if(unknown.length)throw Error('Unsupported setup fields: '+unknown.slice(0,4).join(', ')+'. Contact details, consent, attachments and site controls must stay in the form.');
 const missing=Object.keys(schema).filter(key=>!Object.hasOwn(draft,key));if(missing.length)throw Error('The setup draft is incomplete. Ask the AI to include: '+missing.slice(0,5).join(', ')+(missing.length>5?' and the remaining template fields.':'.'));
 if(!['research','research_tool'].includes(draft.project_type))throw Error('Choose research or research_tool as the project type.');
 const result={project_type:draft.project_type};
 function read(key,min,max){const value=draft[key];if(typeof value!=='string')throw Error(key+' must be text.');const clean=value.trim(),bytes=new TextEncoder().encode(clean).length;if(bytes<min||bytes>max||clean.includes('\0'))throw Error('Check '+key+': expected '+min+'–'+max+' UTF-8 bytes.');return clean;}
 for(const [key,allowed] of Object.entries({calculation_help:['unspecified','maybe','prepare_review','run'],external_tools:['unspecified','none','free','licensed','mixed','unknown']})){if(!allowed.includes(draft[key]))throw Error('Choose a valid '+key);result[key]=draft[key];}
 for(const key of externalKeys)result[key]=key==='required_software'&&result.external_tools==='none'?'':read(key,0,3000);
 result.setup_needs=result.setup_needs||'None specified';
 if(['free','licensed','mixed'].includes(result.external_tools)&&!result.required_software)throw Error('Name the required external software.');
 for(const key of textKeys)result[key]=read(key,key==='title'?8:key==='question'?20:0,key==='title'?180:key==='tags'?800:config.maxText);
 const category=read('category',1,200);const match=config.categories.find(option=>option.name===category);if(!match)throw Error('Choose an exact category name from the setup prompt.');result.category_id=String(match.id);
 for(const key of toolKeys){result[key]=read(key,draft.project_type==='research_tool'?20:0,3000);if(draft.project_type==='research'&&result[key]!=='')throw Error('Ordinary research should leave tool-specific fields empty.');}
 if(draft.ai_selection_version!==2)throw Error('Refresh the AI selection setup template.');
 const choices=parseChoices(draft,config);
 result.ai_selection_version=2;result.required_models=choices.models;
 result.reasoning_levels=[...choices.efforts.map(c=>c.level),...choices.modes];
 result.reasoning_or_higher=choices.efforts.filter(c=>c.or_higher).map(c=>c.level);
 return result;
}
if(typeof document!=='undefined'){
 const form=document.querySelector('[data-request-form]'),configNode=document.getElementById('request-setup-config');
 if(form&&configNode){
  const config=JSON.parse(configNode.content.textContent),button=document.getElementById('copy-setup-prompt'),panel=document.getElementById('request-setup-panel'),prompt=document.getElementById('request-setup-prompt'),paste=document.getElementById('paste-setup-prompt'),status=document.getElementById('request-setup-status'),feedback=document.getElementById('request-setup-feedback');
  const control=name=>form.elements.namedItem(name);
  function currentDraft(){const values={};for(const key of Object.keys(setupSchema())){const input=control(key);if(input&&!input.disabled)values[key]=input.type==='checkbox'?input.checked:input.value;}Object.assign(values,readChoices(form));values.ai_selection_version=2;const selected=control('category_id');values.category=selected.value?selected.selectedOptions[0].textContent:'';return values;}
  function showStatus(title,detail){
   const heading=document.createElement('strong'),body=document.createElement('span');
   heading.textContent=title;body.textContent=detail;
   status.replaceChildren(heading,body);
  }
  const unchangedPrompt='Paste into your AI as is. Do not alter the prompt; your research may not be accepted.';
  button.hidden=false;
  button.addEventListener('click',async()=>{
   prompt.value=setupPrompt(config,currentDraft());panel.hidden=false;button.setAttribute('aria-expanded','true');feedback.textContent='';
   try{await navigator.clipboard.writeText(prompt.value);showStatus('Prompt copied — paste as is',unchangedPrompt);}
   catch{prompt.closest('details').open=true;prompt.focus();prompt.select();showStatus('Clipboard unavailable — copy the selected prompt manually',unchangedPrompt+' Then paste the AI’s final JSON draft below.');}
  });
  document.getElementById('fill-request-fields').addEventListener('click',()=>{
   let draft;try{draft=parseSetupDraft(paste.value,config);}catch(error){feedback.textContent=error.message+' Your request fields have not changed.';paste.setAttribute('aria-invalid','true');return;}
   // Validate everything before changing any existing field. Explicitly touch only the public allowlist.
   for(const [key,value] of Object.entries(draft)){if(Array.isArray(value))continue;const input=control(key);if(input.type==='checkbox')input.checked=value;else input.value=value;}
   setChoices(form.querySelector('[data-ai-selection]'),draft);
   for(const key of ['project_type','external_tools','calculation_help'])control(key).dispatchEvent(new Event('change',{bubbles:true}));
   for(const key of [...textKeys,...toolKeys])control(key).dispatchEvent(new Event('input',{bubbles:true}));
   form.querySelectorAll('details').forEach(section=>{if([...section.querySelectorAll('input,textarea,select')].some(input=>!input.disabled&&input.value&&input.type!=='checkbox'&&Object.hasOwn(draft,input.name)))section.open=true;});
   // Filled content needs fresh human review/consent, never consent supplied by AI.
   control('terms').checked=false;control('terms').dispatchEvent(new Event('change',{bubbles:true}));document.getElementById('request-preview').hidden=true;paste.removeAttribute('aria-invalid');panel.hidden=true;button.setAttribute('aria-expanded','false');feedback.textContent='';showStatus('Draft filled — review your request','Review and edit the fields below, then confirm the terms and submit when ready.');control('title').focus({preventScroll:true});
  });
  paste.addEventListener('input',()=>{paste.removeAttribute('aria-invalid');feedback.textContent='';});
 }
}