import fs from 'node:fs';import path from 'node:path';import {root} from '../tools/runtime.mjs';import {setupSchema} from '../public/assets/request-setup.js';
export async function requestSetupQa({page,ctx,base,runPhp,check,visibleActions}){
 if(process.env.QA_SETUP_TAGS_ONLY==='1')return requestSetupTagQa({page,ctx,base,runPhp,check,visibleActions});
 const output=path.join(root,'storage/qa/setup-assist');fs.mkdirSync(output,{recursive:true});const shots=[],errors=[];page.on('pageerror',error=>errors.push(error.message));
 const shot=async name=>{const file=path.join(output,name+'.png');await page.screenshot({path:file,fullPage:false});shots.push(file);};
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 for(const size of sizes){
  await page.setViewportSize(size);await page.goto(base+'/submit');await page.locator('#copy-setup-prompt').waitFor({state:'visible'});await shot('initial-'+size.width);
  await visibleActions(page,'.form-actions button','Setup assistant initial '+size.width);
  check('Essential request fields fully visible '+size.width,await page.locator('[name=project_type],[name=title],[name=question]').evaluateAll(els=>els.every(el=>{const r=el.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight;})));
  check('Paste field initially hidden '+size.width,!await page.locator('#paste-setup-prompt').isVisible());
  await page.getByRole('button',{name:'Copy AI prompt to assist setup',exact:true}).click();
  check('Paste field appears on copy '+size.width,await page.getByLabel('Paste your AI completed draft (JSON)',{exact:true}).isVisible());
  await page.locator('#request-setup-status strong').filter({hasText:'Prompt copied'}).waitFor();
  check('Copied prompt notice is prominent green '+size.width,await page.locator('#request-setup-status').evaluate(el=>getComputedStyle(el).backgroundColor==='rgb(22, 61, 44)'&&el.querySelector('strong').textContent.includes('paste as is')));
  check('Unchanged prompt and acceptance warning are explicit '+size.width,await page.locator('#request-setup-status').innerText().then(text=>text.includes('as is')&&text.includes('Do not alter the prompt')&&text.includes('may not be accepted')));
  // Windows clipboard CRLF is equivalent to the textarea's normalized LF.
  const copied=(await page.evaluate(()=>navigator.clipboard.readText())).replaceAll('\r\n','\n');
  const complete=(await page.locator('#request-setup-prompt').inputValue()).replaceAll('\r\n','\n');
  check('Exact complete prompt reaches clipboard '+size.width,copied===complete);
  await shot('copy-'+size.width);await visibleActions(page,'#fill-request-fields','Setup fill action '+size.width);
 }
 const manual=await ctx.newPage();
 try{
  await manual.addInitScript(()=>{Object.defineProperty(navigator.clipboard,'writeText',{value:async()=>{throw new DOMException('Synthetic denied clipboard','NotAllowedError');}});});
  await manual.setViewportSize(sizes[0]);await manual.goto(base+'/submit');await manual.getByRole('button',{name:'Copy AI prompt to assist setup',exact:true}).click();
  await manual.locator('#request-setup-status strong').filter({hasText:'Clipboard unavailable'}).waitFor();
  check('Clipboard denial never reports a successful copy',!(await manual.locator('#request-setup-status').innerText()).includes('Prompt copied'));
  check('Manual clipboard fallback retains unchanged-prompt warning',(await manual.locator('#request-setup-status').innerText()).includes('Do not alter the prompt'));
  check('Complete prompt remains available for manual copying',await manual.locator('#request-setup-prompt').isVisible()&&(await manual.locator('#request-setup-prompt').inputValue()).includes('REQUIRED FINAL OUTPUT TEMPLATE'));
  const file=path.join(output,'clipboard-denied.png');await manual.screenshot({path:file,fullPage:false});shots.push(file);
 }finally{await manual.close();}
 await page.setViewportSize(sizes[0]);await page.goto(base+'/submit');await page.locator('[name=title]').fill('Existing public idea for the setup interview');
 await page.locator('[data-request-next-step]>summary').click();const attribution=page.getByText('Optional attribution & private email',{exact:true});await attribution.click();await page.locator('[name=email]').fill('private-setup@example.invalid');await page.locator('[name=display_name]').fill('Private draft attribution');await page.locator('[data-request-edit-basics]').click();await page.getByRole('button',{name:'Copy AI prompt to assist setup',exact:true}).click();const copied=await page.evaluate(()=>navigator.clipboard.readText());check('Copied prompt reflects current public draft',copied.includes('Existing public idea for the setup interview'));check('Copied prompt omits private contact/attribution',!copied.includes('private-setup@example.invalid')&&!copied.includes('Private draft attribution'));
 const config=await page.locator('#request-setup-config').evaluate(node=>JSON.parse(node.content.textContent));const draft={...setupSchema('research_tool'),title:'[QA] Guided setup for a research conversion tool',question:'Build a bounded research data conversion tool with explicit inputs, reference cases and independent reproduction.',category:config.categories[0].name,context:'Synthetic local setup-assistant browser fixture only.',success:'A source/test bundle with reproducible reference, boundary and invalid-input cases.',calculation_help:'run',required_models:['gpt-6-astra','claude-fable-5-1'],reasoning_levels:['high','ultracode'],reasoning_or_higher:['high']};for(const key of Object.keys(draft).filter(key=>key.startsWith('tool_')))draft[key]='Explicit synthetic tool requirement with bounded inputs, outputs, reference tests and experimental maintenance status.';
 const bad={...draft,terms:true};await page.getByLabel('Paste your AI completed draft (JSON)',{exact:true}).fill(JSON.stringify(bad));await page.getByRole('button',{name:'Fill request fields',exact:true}).click();check('Invalid import preserves the entire existing draft',await page.locator('[name=title]').inputValue()==='Existing public idea for the setup interview'&&await page.locator('[name=project_type]').inputValue()==='research');check('Invalid import reports protected field',await page.locator('#request-setup-feedback').innerText().then(text=>text.includes('Unsupported setup fields')));
 await page.locator('[data-request-next-step]>summary').click();await page.locator('[name=terms]').check();await page.locator('[data-request-edit-basics]').click();await page.getByLabel('Paste your AI completed draft (JSON)',{exact:true}).fill(JSON.stringify(draft));await page.getByRole('button',{name:'Fill request fields',exact:true}).click();check('Imported AI selection menus retain alternatives, minimum and independent mode',await page.locator('[name="required_models[]"][value=claude-fable-5-1]').isChecked()&&await page.locator('[name="required_models[]"][value=gpt-6-astra]').isChecked()&&await page.locator('[name="reasoning_levels[]"][value=ultracode]').isChecked()&&await page.locator('[name="reasoning_or_higher[]"][value=high]').isChecked());check('Tool draft populates normal editable fields',await page.locator('[name=title]').inputValue()===draft.title&&await page.locator('[name=tool_validation_plan]').inputValue()===draft.tool_validation_plan);check('Project type and authoritative calculation preference correctly set',await page.locator('[name=project_type]').inputValue()==='research_tool'&&await page.locator('[name=calculation_help]').inputValue()==='run');check('Private values preserved and consent requires fresh confirmation',await page.locator('[name=email]').inputValue()==='private-setup@example.invalid'&&await page.locator('[name=display_name]').inputValue()==='Private draft attribution'&&!await page.locator('[name=terms]').isChecked());check('Imported complete fields and fresh consent stay neutral before validation',await page.locator('[name=title]').getAttribute('aria-invalid')===null&&await page.locator('[name=question]').getAttribute('aria-invalid')===null&&await page.locator('[name=category_id]').getAttribute('aria-invalid')===null&&await page.locator('[name=terms]').getAttribute('aria-invalid')===null);check('Integrated draft status preserves prominent feedback',await page.locator('#request-setup-status strong').innerText()==='Draft filled — review your request'&&await page.locator('#request-setup-status').getAttribute('aria-atomic')==='true');check('Raw helper text is not a submitted form field',await page.locator('#paste-setup-prompt').getAttribute('name')===null&&await page.locator('#request-setup-prompt').getAttribute('name')===null);await shot('filled-tool-draft');
 let submitted=0;const countSubmit=request=>{if(request.method()==='POST'&&new URL(request.url()).pathname==='/submit')submitted++;};page.on('request',countSubmit);
 await page.locator('[data-request-next-step]>summary').click();await page.locator('.request-submit-actions').getByRole('button',{name:'Submit for approval',exact:true}).click();
 check('Attempting to submit an imported draft requires fresh consent',submitted===0&&await page.locator('[name=terms]').getAttribute('aria-invalid')==='true'&&await page.locator('[name=terms]').evaluate(el=>el===document.activeElement));page.off('request',countSubmit);
 await page.getByRole('button',{name:'Review your request',exact:true}).click();check('Existing preview includes imported tool and scientific requirements',await page.locator('#request-preview-content').innerText().then(text=>text.includes(draft.tool_validation_plan)&&text.includes(draft.question)));
 // Synthetic importer fixture only: live model conversations are recorded separately.
 const plastic='Effects of plastic pollution on the biological carbon pump in the deep ocean';
 await page.goto(base+'/submit');await page.locator('#copy-setup-prompt').waitFor({state:'visible'});await page.locator('#copy-setup-prompt').click();
 const researchDraft={...setupSchema(),title:plastic,question:plastic,category:config.categories.find(c=>c.name==='Environment & Climate').name};
 await page.locator('#paste-setup-prompt').fill('```json\n'+JSON.stringify(researchDraft,null,2)+'\n```');await page.locator('#fill-request-fields').click();
 check('Exact plastic-topic fenced draft fills without invented scope',await page.locator('[name=question]').inputValue()===plastic&&await page.locator('[name=title]').inputValue()===plastic&&await page.locator('[name=context]').inputValue()===''&&await page.locator('[name=success]').inputValue()==='');
 check('Research defaults remain optional calculations and no invented setup',await page.locator('[name=project_type]').inputValue()==='research'&&await page.locator('[name=calculation_help]').inputValue()==='maybe'&&await page.locator('[name=setup_needs]').inputValue()==='None specified'&&await page.locator('[name=required_software]').inputValue()==='');
 await page.locator('[data-request-next-step]>summary').click();await page.getByRole('button',{name:'Review your request',exact:true}).click();check('Preview retains exact topic without a mandatory calculation task',(await page.locator('#request-preview-content').innerText()).includes(plastic));
 const nojs=await ctx.browser().newContext({javaScriptEnabled:false,viewport:sizes[2]});try{const native=await nojs.newPage();await native.goto(base+'/submit');const nativeInitial=path.join(output,'manual-initial-375.png');await native.screenshot({path:nativeInitial,fullPage:false});shots.push(nativeInitial);await native.locator('[data-request-next-step]>summary').click();check('Manual AI setup guide available without JavaScript',await native.getByText('AI help to draft your request',{exact:true}).isVisible());check('No-JS submit remains available',await native.locator('.request-submit-actions button:not([hidden])').isVisible());check('No-JS title/question remain in first short-mobile viewport',await native.locator('[name=title],[name=question]').evaluateAll(els=>els.every(el=>{const r=el.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight;})));const guide=await native.getByLabel('Manual AI setup guide',{exact:true}).inputValue();check('No-JS guide preserves depth and Ready flow with an explicit manual-output override',guide.includes('quick setup or an in-depth guide')&&guide.includes('MANUAL-ENTRY MODE overrides only')&&guide.includes('Only after Ready')&&guide.includes('instead of JSON'));}finally{await nojs.close();}
 await page.setViewportSize({width:683,height:384});await page.goto(base+'/submit');await page.evaluate(()=>document.body.style.fontSize='200%');await shot('enlarged-text');check('Setup controls reflow at 200% text',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));const zoom=await ctx.browser().newContext({viewport:{width:683,height:384},deviceScaleFactor:2,permissions:['clipboard-read','clipboard-write']});
 try{
  const enlarged=await zoom.newPage();await enlarged.goto(base+'/submit');
  const initial=path.join(output,'initial-200-percent.png');await enlarged.screenshot({path:initial,fullPage:false});shots.push(initial);
  await enlarged.getByRole('button',{name:'Copy AI prompt to assist setup',exact:true}).click();
  await enlarged.locator('#request-setup-status strong').filter({hasText:'Prompt copied'}).waitFor();
  await enlarged.locator('#request-setup-status').scrollIntoViewIfNeeded();
  const file=path.join(output,'copied-prompt-200-percent.png');await enlarged.screenshot({path:file,fullPage:false});shots.push(file);
  check('Copied-prompt notice reflows at 200% viewport/device scale',await enlarged.locator('#request-setup-status').evaluate(el=>el.scrollWidth<=el.clientWidth+1&&document.documentElement.scrollWidth<=innerWidth+1));
 }finally{await zoom.close();}
 check('Setup assistant has no browser errors',errors.length===0);fs.writeFileSync(path.join(output,'browser-evidence.json'),JSON.stringify({shots,errors},null,2));
}
// Focused contract regression; the standard setup suite remains available above.
async function requestSetupTagQa({page,base,runPhp,check,visibleActions}){
 const output=path.join(root,'storage/qa/setup-assist/tags');fs.mkdirSync(output,{recursive:true});
 const shots=[],errors=[];page.on('pageerror',error=>errors.push(error.message));
 const shot=async name=>{const file=path.join(output,name+'.png');await page.screenshot({path:file,fullPage:false});shots.push(file);};
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 const snapshot=()=>page.locator('[data-request-form]').evaluate(form=>[...form.elements].filter(el=>el.name).map(el=>({name:el.name,type:el.type,value:el.value,checked:el.checked,disabled:el.disabled})));
 let posts=0;page.on('request',request=>{if(request.method()==='POST'&&new URL(request.url()).pathname==='/submit')posts++;});
 const badTags=[['61-ascii','a'.repeat(61),'60 UTF-8 bytes'],['13-unique',Array.from({length:13},(_,i)=>'tag'+i).join(','),'12 unique'],['62-multibyte','é'.repeat(31),'60 UTF-8 bytes']];
 let config;
 for(const size of sizes){
  await page.setViewportSize(size);await page.goto(base+'/submit');await page.locator('#copy-setup-prompt').waitFor({state:'visible'});
  check('Tag flow page identity '+size.width,new URL(page.url()).pathname==='/submit'&&(await page.title()).includes('LLM.Giving')&&await page.locator('[name=question]').count()===1);
  await shot('initial-'+size.width);await visibleActions(page,'.form-actions button,#request-next-step > summary','Tag flow initial '+size.width);
  await page.locator('[name=title]').fill('Existing tag validation draft');await page.locator('[name=question]').fill('Preserve every existing public and private draft field during an invalid tag import.');
  await page.locator('[data-request-next-step]>summary').click();await page.locator('[name=terms]').check();await page.getByText('Optional attribution & private email',{exact:true}).click();await page.locator('[name=email]').fill('tag-fixture@example.invalid');await page.locator('[name=display_name]').fill('Tag fixture attribution');await page.locator('[data-request-edit-basics]').click();await page.locator('#copy-setup-prompt').click();
  config=await page.locator('#request-setup-config').evaluate(node=>JSON.parse(node.content.textContent));
  const draft={...setupSchema(),title:'[QA] Tag boundary research request',question:'Which tag boundaries can be accepted consistently by the setup importer and ordinary server submission?',category:config.categories[0].name};
  check('Copied prompt includes full tag limits '+size.width,(await page.locator('#request-setup-prompt').inputValue()).includes('at most 12 unique entries')&&(await page.locator('#request-setup-prompt').inputValue()).includes('60 UTF-8 bytes'));
  for(const [name,tags,message] of badTags){
   const before=await snapshot();await page.locator('#paste-setup-prompt').fill(JSON.stringify({...draft,tags}));await page.locator('#fill-request-fields').click();
   check('Invalid '+name+' import preserves all fields and consent '+size.width,JSON.stringify(await snapshot())===JSON.stringify(before));
   check('Invalid '+name+' rejected before Draft filled '+size.width,(await page.locator('#request-setup-feedback').innerText()).includes(message)&&!(await page.locator('#request-setup-status').innerText()).includes('Draft filled')&&await page.locator('#paste-setup-prompt').getAttribute('aria-invalid')==='true');
  }
  await page.locator('#request-setup-feedback').scrollIntoViewIfNeeded();await shot('import-error-'+size.width);
  const validTags=['a'.repeat(60),...Array.from({length:11},(_,i)=>'tag'+i)].join(',');
  await page.locator('#paste-setup-prompt').fill(JSON.stringify({...draft,tags:validTags+',tag0, ,0'}));await page.locator('#fill-request-fields').click();
  check('60 bytes and 12 unique import succeeds '+size.width,await page.locator('[name=tags]').inputValue()===validTags.split(',').join(', ')&&(await page.locator('#request-setup-status').innerText()).includes('Draft filled'));
  check('Successful import requires fresh consent and keeps private data '+size.width,!await page.locator('[name=terms]').isChecked()&&await page.locator('[name=email]').inputValue()==='tag-fixture@example.invalid');
  await page.locator('[data-request-next-step]>summary').click();
  const tags=page.locator('[name=tags]');await tags.scrollIntoViewIfNeeded();check('Visible guidance states matching limits '+size.width,(await page.locator('#request-tags-help').innerText()).includes('12 unique tags')&&(await page.locator('#request-tags-help').innerText()).includes('60 UTF-8 bytes'));
  for(const [name,value,message] of badTags){
   await tags.fill(value);await tags.press('Tab');
   check('Manual '+name+' gets field-specific feedback '+size.width,await tags.getAttribute('aria-invalid')==='true'&&(await page.locator('#request-error-tags').innerText()).includes(message));
  }
  await tags.scrollIntoViewIfNeeded();await shot('manual-error-'+size.width);
  await page.getByRole('button',{name:'Review your request',exact:true}).click();
  check('Invalid manual tags cannot enter review '+size.width,!await page.locator('#request-preview').isVisible()&&await tags.evaluate(el=>document.activeElement===el));
  check('Invalid manual tags never POST '+size.width,posts===0);
  await tags.fill(' data, data, ,0, DATA ,é,é ');await tags.press('Tab');
  check('Manual repeated tags normalize with server case rules '+size.width,await tags.inputValue()==='data, DATA, é'&&await tags.getAttribute('aria-invalid')===null);
  await page.getByRole('button',{name:'Review your request',exact:true}).click();check('Corrected manual tags enter real preview '+size.width,await page.locator('#request-preview').isVisible()&&(await page.locator('#request-preview-content').innerText()).includes('data, DATA, é'));
 }
 // Enlarge every rendered font separately; scrolling is expected in this accessible state.
 await page.setViewportSize(sizes[0]);await page.goto(base+'/submit');await page.evaluate(()=>{const fonts=[...document.querySelectorAll('body,body *')].filter(el=>el instanceof HTMLElement).map(el=>[el,parseFloat(getComputedStyle(el).fontSize)]);for(const [el,size]of fonts)if(Number.isFinite(size))el.style.fontSize=(size*2)+'px';});
 await shot('initial-200-percent');await page.locator('[data-request-next-step]>summary').click();await page.getByText('Background, objectives & references',{exact:true}).click();const tags=page.locator('[name=tags]');await tags.fill('é'.repeat(31));await tags.press('Tab');await page.locator('#request-error-tags').evaluate(el=>el.style.fontSize=(parseFloat(getComputedStyle(el).fontSize)*2)+'px');await tags.scrollIntoViewIfNeeded();await shot('manual-error-200-percent');
 check('Tag guidance/error reflow at 200% text',await page.locator('#request-tags-help,#request-error-tags').evaluateAll(els=>els.every(el=>el.scrollWidth<=el.clientWidth+1))&&await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 // Real ordinary submit succeeds at the valid 60-byte / 12-unique boundary.
 await page.setViewportSize(sizes[0]);await page.goto(base+'/submit');await page.locator('#copy-setup-prompt').click();const tagsBoundary=['é'.repeat(30),...Array.from({length:11},(_,i)=>'boundary'+i)].join(',');
 const valid={...setupSchema(),title:'[QA] Tag boundary ordinary submission',question:'Does the ordinary submission accept the imported maximum valid tag size and unique count?',category:config.categories[0].name,tags:tagsBoundary};
 const beforeCount=Number(runPhp(['-r',"require 'tests/bootstrap.php';echo one('SELECT COUNT(*) n FROM research')['n'];"]));
 await page.locator('#paste-setup-prompt').fill(JSON.stringify(valid));await page.locator('#fill-request-fields').click();await page.locator('[data-request-next-step]>summary').click();await page.locator('[name=terms]').check();await page.getByRole('button',{name:'Review your request',exact:true}).click();await page.locator('#request-preview').getByRole('button',{name:'Submit for approval',exact:true}).click();await page.getByRole('heading',{name:'Research request submitted',exact:true}).waitFor();
 check('Valid boundary reaches actual server receipt once',posts===1&&Number(runPhp(['-r',"require 'tests/bootstrap.php';echo one('SELECT COUNT(*) n FROM research')['n'];"]))===beforeCount+1);
 check('Valid boundary stores 12 normalized server tags',Number(runPhp(['-r',"require 'tests/bootstrap.php';echo one(\"SELECT COUNT(*) n FROM research_tags rt JOIN research r ON r.id=rt.research_id WHERE r.title='[QA] Tag boundary ordinary submission'\")['n'];"]))===12);
 check('Focused tag flow has no browser exceptions',errors.length===0);fs.writeFileSync(path.join(output,'browser-evidence.json'),JSON.stringify({shots,errors,scope:'Focused tag importer/manual/server-submit regression; unchanged layout suites reused.'},null,2));
}
