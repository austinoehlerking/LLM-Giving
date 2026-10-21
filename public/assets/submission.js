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
 const encoder=new TextEncoder();
 function validateRequest(){
  requestForm.elements.namedItem('required_software').required=['free','licensed','mixed'].includes(requestForm.elements.namedItem('external_tools').value);
  for(const field of requestForm.querySelectorAll(validationFields)){
   // Match PHP trim and UTF-8 byte limits; browser string length differs for Unicode.
   field.setCustomValidity('');
   let message='';
   if(field.required&&!field.disabled){
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
  if(event.target.matches(validationFields)){touchedFields.add(event.target);validateRequest();}
 });
 requestForm.addEventListener('reset',()=>{touchedFields.clear();validationAttempted=false;setTimeout(()=>{updateProjectType();validateRequest();},0);});
 window.addEventListener('pageshow',validateRequest);
 requestForm.addEventListener('submit',event=>{validationAttempted=true;validateRequest();if(!requestForm.reportValidity())event.preventDefault();});

 const help=requestForm.elements.namedItem('calculation_help'),execution=requestForm.elements.namedItem('computational_models');
 help.addEventListener('change',()=>{if(help.value==='run')execution.checked=true;else if(help.value==='prepare_review')execution.checked=false;});
 execution.addEventListener('change',()=>{if(execution.checked)help.value='run';else if(help.value==='run')help.value='unspecified';});
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
 const labels={calculation_help:'Calculation help needed',external_tools:'External tool access',required_software:'Required software and versions',software_licensing:'License eligibility and access',computing_resources:'Computing resources and budget',setup_needs:'Downloads and setup needed',project_type:'Project type',tool_intended_users:'Intended users and scientific use',tool_requirements:'Bounded requirements and exclusions',tool_inputs_outputs:'Inputs, outputs and units',tool_validation_plan:'Reference cases and test expectations',tool_maintenance:'Maintenance plan or experimental status',title:'Research title',question:'Research objective',category_id:'Category',required_model:'Required AI model',reasoning_rule:'Reasoning requirement',required_reasoning:'Reasoning level',computational_models:'Computational models',context:'Background and context',success:'Desired outcome',previous:'Previous research',constraints_text:'Limitations or constraints',questions:'Specific questions',references_text:'References and source URLs',tags:'Tags',display_name:'Public attribution',linkedin:'LinkedIn URL',github:'GitHub URL',website:'Website URL',email:'Private email (not published)'};
 const editButton=document.getElementById('edit-request');
 const reviewButton=document.getElementById('review-request');
 reviewButton.hidden=false;
 reviewButton.addEventListener('click',()=>{
  content.replaceChildren();
  for(const [name,label] of Object.entries(labels)){
   const field=requestForm.elements.namedItem(name);
   if(field.disabled)continue;
   const value=field.type==='checkbox'?(field.checked?'Requested — include running computational models':'Not requested'):field.tagName==='SELECT'?(field.value?field.selectedOptions[0].textContent:''):field.value.trim();
   if(!value && !['title','question','category_id'].includes(name))continue;
   const section=document.createElement('section'),heading=document.createElement('h3'),body=document.createElement('p');
   heading.textContent=label;body.textContent=value||'Not provided — complete before submitting.';
   section.append(heading,body);content.append(section);
  }
  const files=requestForm.elements.namedItem('attachments[]').files;
  if(files.length){const heading=document.createElement('h3'),list=document.createElement('ul');heading.textContent='Files (require separate approval)';for(const file of files){const item=document.createElement('li');item.textContent=file.name;list.append(item);}content.append(heading,list);}
  preview.hidden=false;preview.focus();preview.scrollIntoView({block:'start',behavior:'instant'});
 });
 editButton.addEventListener('click',()=>{preview.hidden=true;reviewButton.focus();});
 // A native validation error can be inside a collapsed optional section.
 requestForm.addEventListener('invalid',event=>{validationAttempted=true;validateRequest();const details=event.target.closest('details');if(details)details.open=true;},true);
 requestForm.addEventListener('input',()=>{preview.hidden=true;});
 requestForm.addEventListener('change',()=>{preview.hidden=true;});
}
