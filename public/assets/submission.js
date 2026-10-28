'use strict';
const requestForm=document.querySelector('[data-request-form]');
if(requestForm){
 const validationMessages=new Map(),touchedFields=new Set();
 let validationAttempted=false;
 const validationFields='input:not([type=hidden]):not([type=file]),textarea:not([data-setup-text]),select';
 requestForm.dataset.validationReady='';
 const textLimits={title:[8,180],question:[20,Number(requestForm.dataset.maxText)],required_software:[1,3000]};
 for(const field of requestForm.querySelectorAll('[data-tool-requirements] textarea'))textLimits[field.name]=[20,3000];
 const requiredMessages={category_id:'Choose a category.',required_reasoning:'Choose a reasoning level.',terms:'Confirm the terms before submitting.',required_software:'Name the required software.'};
 function normalizeTags(value){
  if(typeof value!=='string')throw Error('Tags must be text.');
  // Match text_field + set_tags: PHP trim, raw byte cap, case-sensitive uniqueness.
  const trim=value=>value.replace(/^[\0\t\n\r\v ]+|[\0\t\n\r\v ]+$/g,'');
  const clean=trim(value),encoder=new TextEncoder();
  if(encoder.encode(clean).length>800)throw Error('Tags must total at most 800 UTF-8 bytes.');
  if(clean.includes('\0'))throw Error('Remove unsupported characters from tags.');
  // PHP array_filter also drops the exact string "0"; do not fold case or Unicode.
  const tags=[...new Set(clean.split(',').map(trim).filter(tag=>tag!==''&&tag!=='0'))];
  if(tags.length>12)throw Error('Use at most 12 unique tags.');
  if(tags.some(tag=>encoder.encode(tag).length>60))throw Error('Each tag must be at most 60 UTF-8 bytes.');
  return tags.join(', ');
 }
 const encoder=new TextEncoder();
 requestForm.elements.namedItem('tags').setAttribute('aria-describedby','request-tags-help');
 function validateRequest(){
  const access=requestForm.elements.namedItem('external_tools').value,software=requestForm.elements.namedItem('required_software');
  software.disabled=access==='none';
  software.closest('[data-required-software]').hidden=software.disabled;
  software.required=['free','licensed','mixed'].includes(access);
  for(const field of requestForm.querySelectorAll(validationFields)){
   // Match PHP trim and UTF-8 byte limits; browser string length differs for Unicode.
   if(field.hasAttribute('data-ai-choice'))continue;
   field.setCustomValidity('');
   let message='';
   if(field.name==='tags'&&!field.disabled){
    try{normalizeTags(field.value);}catch(error){message=error.message;}
    field.setCustomValidity(message);
   }else if(field.required&&!field.disabled){
    const value=field.type==='checkbox'?(field.checked?'1':''):field.value.replace(/^[\0\t\n\r\v ]+|[\0\t\n\r\v ]+$/g,'');
    const limits=textLimits[field.name],bytes=encoder.encode(value.replace(/\r?\n/g,'\r\n')).length;
    if(!value)message=requiredMessages[field.name]||'Required.';
    else if(limits&&bytes<limits[0])message='Add more detail.';
    else if(limits&&bytes>limits[1])message='Shorten this text.';
    else if(value.includes('\0'))message='Remove unsupported characters.';
    field.setCustomValidity(message);
   }
   const invalid=field.willValidate&&!field.validity.valid&&(validationAttempted||touchedFields.has(field));
   const label=field.labels?.[0];
   label?.classList.toggle('field-invalid',invalid);
   if(label&&field.type!=='checkbox'){
    let marker=label.querySelector('[data-required-marker]');
    if(field.required&&!label.textContent.includes('*')){marker=document.createElement('span');marker.dataset.requiredMarker='';marker.setAttribute('aria-hidden','true');marker.textContent=' *';label.append(marker);}
    if(marker)marker.hidden=!field.required||field.disabled;
   }
   if(invalid)field.setAttribute('aria-invalid','true');else field.removeAttribute('aria-invalid');
   let error=validationMessages.get(field);
   if(invalid&&!error){
    error=document.createElement('p');error.className='validation-message';error.id='request-error-'+field.name;
    const describedBy=field.getAttribute('aria-describedby');field.setAttribute('aria-describedby',[describedBy,error.id].filter(Boolean).join(' '));
    (field.closest('label')||field).after(error);validationMessages.set(field,error);
   }
   if(error){error.textContent=invalid?(message||field.validationMessage):'';error.hidden=!invalid;}
  }
 }
 requestForm.addEventListener('input',validateRequest);
 requestForm.addEventListener('change',event=>{
  if(event.isTrusted)touchedFields.add(event.target);
  // An imported draft needs fresh consent; a scripted reset is not a user attempt.
  else if(event.target.name==='terms')touchedFields.delete(event.target);
  validateRequest();
 });
 requestForm.addEventListener('focusout',event=>{
  if(event.target.matches(validationFields)){
   if(event.target.name==='tags'){try{event.target.value=normalizeTags(event.target.value);}catch{/* Retain invalid text for correction. */}}
   touchedFields.add(event.target);validateRequest();
  }
 });
 requestForm.addEventListener('reset',()=>{touchedFields.clear();validationAttempted=false;setTimeout(()=>{updateProjectType();validateRequest();},0);});
 window.addEventListener('pageshow',validateRequest);
 requestForm.addEventListener('submit',event=>{validationAttempted=true;validateRequest();if(!requestForm.reportValidity())event.preventDefault();});

 const projectType=requestForm.elements.namedItem('project_type');
 const toolRequirements=requestForm.querySelector('[data-tool-requirements]');
 function updateProjectType(){const tool=projectType.value==='research_tool';toolRequirements.hidden=!tool;toolRequirements.open=tool;toolRequirements.querySelectorAll('textarea').forEach(field=>{field.required=tool;field.disabled=!tool;});resizeAll();}
 projectType.addEventListener('change',updateProjectType);
 const areas=[...requestForm.querySelectorAll('textarea')].filter(area=>!area.hasAttribute('data-setup-text'));
 function resize(area){
  if(!area.getClientRects().length)return;
  area.style.height='auto';
  area.style.height=(area.scrollHeight+2)+'px';
 }
 function resizeAll(){areas.forEach(resize);}
 areas.forEach(area=>area.addEventListener('input',()=>resize(area)));
 requestForm.querySelectorAll('details').forEach(details=>details.addEventListener('toggle',resizeAll));
 let frame;window.addEventListener('resize',()=>{cancelAnimationFrame(frame);frame=requestAnimationFrame(resizeAll);});
 resizeAll();updateProjectType();validateRequest();
 const preview=document.getElementById('request-preview');
 const content=document.getElementById('request-preview-content');
 const labels={title:'Research title',question:'Research objective',category_id:'Category',calculation_help:'Calculation help needed',external_tools:'External tool access',required_software:'Required software and versions',setup_needs:'Downloads and setup needed',project_type:'Project type',tool_intended_users:'Intended users and scientific use',tool_requirements:'Bounded requirements and exclusions',tool_inputs_outputs:'Inputs, outputs and units',tool_validation_plan:'Reference cases and test expectations',tool_maintenance:'Maintenance plan or experimental status',context:'Background and context',success:'Desired outcome',previous:'Previous research',constraints_text:'Limitations or constraints',questions:'Specific questions',references_text:'References and source URLs',tags:'Tags',display_name:'Public attribution',linkedin:'LinkedIn URL',github:'GitHub URL',website:'Website URL',email:'Private email (not published)'};
 const editButton=document.getElementById('edit-request');
 const reviewButton=document.getElementById('review-request');
 requestForm.dataset.requestEnhanced='';
 const basics=requestForm.querySelector('[data-request-basics]'),nextStep=requestForm.querySelector('[data-request-next-step]'),editBasics=requestForm.querySelector('[data-request-edit-basics]');
 function showBasics(){basics.hidden=false;nextStep.open=false;editBasics.hidden=true;preview.hidden=true;requestForm.hidden=false;basics.querySelector('[name=title]').focus({preventScroll:true});scrollTo(0,0);}
 nextStep.addEventListener('toggle',()=>{if(nextStep.open){basics.hidden=true;editBasics.hidden=false;scrollTo(0,0);}else{basics.hidden=false;editBasics.hidden=true;}});
 editBasics.addEventListener('click',showBasics);
 reviewButton.hidden=false;
 reviewButton.addEventListener('click',()=>{
  validateRequest();if(!requestForm.elements.namedItem('tags').reportValidity())return;
  content.replaceChildren();
  const settings=document.createElement('section'),settingsTitle=document.createElement('h3'),settingsList=document.createElement('dl');settingsTitle.textContent='Research settings';settings.className='request-settings-summary';settings.append(settingsTitle,settingsList);
  const settingNames=['category_id','project_type','calculation_help','external_tools','required_software','setup_needs'];
  for(const [name,label] of Object.entries(labels)){
   const field=requestForm.elements.namedItem(name);
   if(field.disabled)continue;
   const value=field.tagName==='SELECT'?(field.value?field.selectedOptions[0].textContent:''):field.value.trim()||(name==='setup_needs'?'None specified':'');
   if(!value && !['title','question','category_id'].includes(name))continue;
   if(settingNames.includes(name)){const term=document.createElement('dt'),description=document.createElement('dd');term.textContent=label;description.textContent=value;settingsList.append(term,description);continue;}
   const section=document.createElement('section'),heading=document.createElement('h3'),body=document.createElement('p');
   heading.textContent=label;body.textContent=value||'Not provided — complete before submitting.';
   section.append(heading,body);content.append(section);
  }
  content.insertBefore(settings,content.children[2]||null);
  const inherited=requestForm.querySelector('[data-fork-background]');if(inherited)content.append(inherited.cloneNode(true));
  const ai=document.createElement('section'),aiHeading=document.createElement('h3'),aiBody=document.createElement('p');aiHeading.textContent='AI requirements';aiBody.textContent=requestForm.querySelector('[data-ai-summary]').textContent;ai.append(aiHeading,aiBody);content.append(ai);
  const files=requestForm.elements.namedItem('attachments[]').files;
  if(files.length){const heading=document.createElement('h3'),list=document.createElement('ul');heading.textContent='Files (require separate approval)';for(const file of files){const item=document.createElement('li');item.textContent=file.name;list.append(item);}content.append(heading,list);}
  requestForm.hidden=true;preview.hidden=false;preview.focus({preventScroll:true});scrollTo(0,0);
 });
 editButton.addEventListener('click',()=>{preview.hidden=true;requestForm.hidden=false;basics.hidden=false;editBasics.hidden=false;basics.querySelector('[name=title]').focus({preventScroll:true});scrollTo(0,0);});
 // A native validation error can be inside a collapsed optional section.
 requestForm.addEventListener('invalid',event=>{validationAttempted=true;requestForm.hidden=false;preview.hidden=true;validateRequest();if([...basics.querySelectorAll('input,textarea,select')].some(field=>field.willValidate&&!field.validity.valid)){basics.hidden=false;nextStep.open=false;}else{nextStep.open=true;const details=event.target.closest('details');if(details)details.open=true;}},true);
 requestForm.addEventListener('input',()=>{preview.hidden=true;});
 requestForm.addEventListener('change',()=>{preview.hidden=true;});
}
