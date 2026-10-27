'use strict';
const status = (message) => { const el=document.querySelector('#copy-status'); el.textContent=message; setTimeout(()=>{el.textContent='';},5000); };
async function copy(text){try{await navigator.clipboard.writeText(text);status('Copied. Ready to paste.');}catch{status('Copy unavailable in this browser. Select and copy the text manually.');}}
document.addEventListener('click',async event=>{
 const el=event.target.closest('[data-copy-url],[data-copy-input],[data-back]');
 if(!el)return;
 if(el.hasAttribute('data-back')){history.back();return;}
 if(el.dataset.copyInput){if(el.hasAttribute('data-copy-citation')){try{await navigator.clipboard.writeText(document.getElementById(el.dataset.copyInput).value);status('Citation copied. Include it when reusing this research.');}catch{const input=document.getElementById(el.dataset.copyInput);input.closest('details').open=true;input.focus();input.select();status('Select and copy the citation below.');}return;}await copy(document.getElementById(el.dataset.copyInput).value);return;}
 if(el.hasAttribute('data-copy-handoff')){
  if(el.dataset.authorizeUrl){event.preventDefault();location.assign(el.dataset.authorizeUrl);return;}
  const label=el.textContent;const message=document.querySelector('[data-handoff-status]');el.disabled=true;el.textContent='Preparing handoff…';document.querySelector('#copy-status').textContent='';
  try{const res=await fetch(el.dataset.copyUrl,{credentials:'same-origin',cache:'no-store'});if(!res.ok){if(res.status===429){message.textContent='Copy limit reached. Use your existing handoff or try again in one hour. Archived copies remain readable.';return;}if(res.status===503){message.textContent='The daily archive storage budget is reached. Use an existing handoff or try again after 00:00 UTC.';return;}if(res.status===413){message.textContent='This context exceeds the 4 MiB copy limit. Ask the administrator to help scope the work; nothing was copied.';return;}throw new Error('load');}const text=await res.text();
   try{await navigator.clipboard.writeText(text);message.textContent='Copied! Paste the entire handoff into your AI.';status('AI handoff copied. Paste it into your AI.');}
   catch{message.textContent='Clipboard access is unavailable. Open handoff text, select all, and copy it manually.';}
  }catch{message.textContent='Could not load the handoff. Try again or open handoff text.';}finally{el.disabled=false;el.textContent=label;}return;
 }
 try{const res=await fetch(el.dataset.copyUrl,{credentials:'same-origin'});if(!res.ok)throw new Error();await copy(await res.text());}catch{status('The prompt could not be loaded. Please reload and try again.');}
});
const exchange=document.getElementById('capability-exchange');
if(exchange && location.hash){
 let token=location.hash.slice(1);const recovery=token.startsWith('recovery:');
 if(recovery){token=token.slice(9);document.getElementById('recovery-token').disabled=false;}
 history.replaceState(null,'',location.pathname);
 document.getElementById('capability-token').value=token;
 document.getElementById('capability-submit').disabled=false;
 exchange.requestSubmit();
}
const form=document.getElementById('submission');
// The AI packet owns research/provenance fields, never the volunteer declaration or consent.
const contributionPacketFields=new Set(['kind','target_id','summary','body','methodology','sources','uncertainty','limitations','previous_work','open_questions','next_actions','suggested_status','safety','recommendation','display_name','linkedin','github','website']);
const contributionMetadataFields=new Set(['provider','model','harness','harness_version','reasoning','workflow_mode','input_tokens','output_tokens','total_tokens','measurement','duration','tools','prompt_version']);
function loadJSON(){
 const area=form.elements.namedItem('json');
 let data;try{data=JSON.parse(area.value);}catch{throw new Error('Invalid JSON. Check commas, quotes, and the complete object.');}
 if(!data || Array.isArray(data) || typeof data!=='object')throw new Error('Expected a JSON object.');
 for(const [key,value] of Object.entries(data)){
  if(key==='human_interaction' && value && typeof value==='object'){form.elements.ai_level.value=value.level||'';form.elements.ai_details.value=value.details||'';}
  else if(key==='evidence'){form.elements.namedItem('evidence').value=JSON.stringify(value,null,2);}
  else if(key==='metadata' && value && typeof value==='object'){
   for(const [k,v] of Object.entries(value)){if(!contributionMetadataFields.has(k))continue;const f=form.elements.namedItem('metadata['+k+']');if(f)f.value=v==null?'':Array.isArray(v)?v.join(', '):String(v);}
  }else if(contributionPacketFields.has(key)){const f=form.elements.namedItem(key);if(f)f.value=value==null?'':String(value);}
 }
 area.value='';document.getElementById('parse-status').textContent='Loaded into the editable fields below. Missing metadata remains blank.';
}
document.getElementById('load-json')?.addEventListener('click',()=>{try{loadJSON();}catch(e){document.getElementById('parse-status').textContent=e.message;}});
document.getElementById('copy-schema')?.addEventListener('click',()=>copy(document.getElementById('submission-schema').textContent));
document.getElementById('preview-submission')?.addEventListener('click',()=>{
 try{
  if(form.elements.namedItem('json').value.trim())loadJSON();
  const data={metadata:{}};for(const [k,v] of new FormData(form)){if(['csrf','terms','attachments[]','json','account_provider','account_reference','account_attestation','identity_id','review_account_separation','review_target_digest'].includes(k))continue;const m=k.match(/^metadata\[(.+)\]$/);if(m)data.metadata[m[1]]=v===''?null:v;else data[k]=k==='evidence'?JSON.parse(v):v;}
  document.getElementById('preview-content').textContent=JSON.stringify(data,null,2);
  document.getElementById('preview').hidden=false;document.getElementById('preview').scrollIntoView({block:'start'});
 }catch(e){document.getElementById('parse-status').textContent=e.message;}
});

// Progressive enhancement: GET remains fully usable without JavaScript.
const discoveryForm=document.querySelector('[data-discovery-form]');
discoveryForm?.querySelectorAll('select,input[type=checkbox]').forEach(control=>control.addEventListener('change',()=>discoveryForm.requestSubmit()));

// Floating contents is an enhancement; the existing in-page navigation remains usable.
if(document.querySelector('.research-heading')){
 const sections=[['#for-ai-agents','for-ai-agents','For AI agents'],['.findings-summary','findings-summary','Summary'],['.findings-takeaways','key-takeaways','Key takeaways'],['#full-research','full-research','Full research'],['#research-sources','research-sources','Sources'],['#research-limitations','research-limitations','Limitations'],['#research-next-steps','research-next-steps','Recommended next steps'],['#question','question','Research brief'],['#findings','findings','Findings ledger'],['#contributions','contributions','Contributions'],['#reviews','reviews','Independent reviews'],['#feedback','feedback','Feedback'],['#research-history','research-history','Research history']];
 const toc=document.createElement('aside');toc.className='floating-toc';
 const toggle=document.createElement('button');toggle.type='button';toggle.className='toc-toggle';toggle.textContent='☰';toggle.setAttribute('aria-label','Table of contents');toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-controls','floating-toc-links');
 const panel=document.createElement('nav');panel.id='floating-toc-links';panel.className='toc-panel';panel.setAttribute('aria-label','Table of contents');panel.hidden=true;
 const title=document.createElement('strong');title.textContent='On this page';panel.append(title);
 for(const [selector,id,label]of sections){if(!label)continue;const target=document.querySelector(selector);if(!target)continue;target.id=id;const link=document.createElement('a');link.href='#'+id;link.textContent=label;panel.append(link);}
 toc.append(toggle,panel);document.body.append(toc);
 const show=open=>{panel.hidden=!open;toggle.setAttribute('aria-expanded',String(open));toc.classList.toggle('is-open',open);};
 toc.addEventListener('mouseenter',()=>{if(matchMedia('(hover:hover)').matches)show(true);});
 toc.addEventListener('mouseleave',()=>{if(!toc.contains(document.activeElement))show(false);});
 toggle.addEventListener('click',()=>show(panel.hidden));
 toc.addEventListener('focusout',e=>{if(!toc.contains(e.relatedTarget))show(false);});
 toc.addEventListener('keydown',e=>{if(e.key==='Escape'){show(false);toggle.focus();e.preventDefault();}else if(e.key==='ArrowDown'&&e.target===toggle){show(true);panel.querySelector('a')?.focus();e.preventDefault();}});
 panel.addEventListener('click',e=>{const link=e.target.closest('a');if(!link)return;const target=document.getElementById(link.hash.slice(1));show(false);if(target){target.setAttribute('tabindex','-1');target.focus({preventScroll:true});}});
 document.addEventListener('pointerdown',e=>{if(!toc.contains(e.target))show(false);});
}

// Open collapsed research details before anchor navigation, including shared deep links.
function revealResearchTarget(hash){
 let id;try{id=decodeURIComponent(hash.slice(1));}catch{return null;}
 const target=document.getElementById(id);if(!target)return null;
 for(let node=target;node;node=node.parentElement)if(node.matches('details.research-disclosure'))node.open=true;
 return target;
}
document.addEventListener('click',event=>{
 const link=event.target.closest('a[href]');if(!link||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
 const destination=new URL(link.href,location.href);
 if(destination.origin===location.origin&&destination.pathname===location.pathname&&destination.search===location.search&&destination.hash)revealResearchTarget(destination.hash);
},true);
window.addEventListener('hashchange',()=>revealResearchTarget(location.hash));
if(location.hash){const target=revealResearchTarget(location.hash);if(target)requestAnimationFrame(()=>target.scrollIntoView({block:'start'}));}

const returnExchange=document.getElementById('return-exchange');
if(returnExchange && location.hash){const secret=location.hash.slice(1);history.replaceState(null,'',location.pathname);returnExchange.elements.return_secret.value=secret;returnExchange.requestSubmit();}
