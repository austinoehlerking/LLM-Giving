import {saveManagementLink} from './receipt-actions.mjs';
import fs from 'node:fs';
import path from 'node:path';
import {root} from '../tools/runtime.mjs';
export async function submissionQa({page,ctx,base,runPhp,check,visibleActions,password}){
 const out=path.join(root,'storage/qa/submission-ux');fs.mkdirSync(out,{recursive:true});
 const shots=[],errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
 const brief={title:'Synthetic submission usability fixture',question:'How can a research request retain its complete context and remain usable with keyboard and mobile browser input?',context:Array.from({length:16},(_,i)=>'Synthetic context section '+(i+1)+': retain this complete bounded QA text, uncertainty and source qualifications without implying real research findings.').join('\n\n'),success:'Verify complete preview, publication controls and usable first viewport.',previous:'No prior scientific work is claimed.',constraints_text:'Local synthetic testing only; no external experiments.',questions:'Can keyboard and mobile users inspect and submit the full brief?',references_text:'Provided synthetic QA brief; no external scientific source is asserted.',tags:'synthetic, usability'};
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 async function shot(name,fullPage=false,mask=[]){await page.screenshot({path:path.join(out,name+'.png'),fullPage,mask});shots.push(name+'.png');}
 for(const size of sizes){
  await page.setViewportSize(size);await page.goto(base+'/submit');await page.locator('#copy-setup-prompt').waitFor({state:'visible'});await shot('01-form-'+size.width+'x'+size.height);
  await visibleActions(page,'.form-actions button','Updated submission '+size.width);
  check('Untouched required fields are neutral '+size.width,await page.locator('[name=title],[name=question],[name=category_id],[name=terms]').evaluateAll(els=>els.every(el=>!el.hasAttribute('aria-invalid')&&getComputedStyle(el).borderColor!=='rgb(255, 157, 157)')));
  check('No initial inline errors '+size.width,await page.locator('.validation-message:not([hidden])').count()===0);
  check('Title and objective fully visible '+size.width,await page.locator('[name=title],[name=question]').evaluateAll(els=>els.every(el=>{const r=el.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight;})));
 }
 await page.setViewportSize(sizes[0]);await page.goto(base+'/submit');
 check('Page-level back link removed',await page.locator('.submission-heading a').count()===0);
 check('Top submit button removed',await page.locator('.form-actions button:not([type=button])').count()===0);
 check('Global research navigation preserved',await page.getByRole('navigation',{name:'Main navigation',exact:true}).getByRole('link',{name:'Explore research',exact:true}).isVisible());
 const invalid=async name=>await page.locator('[name='+name+']').getAttribute('aria-invalid')==='true';
 await page.locator('[name=title]').fill('short');
 check('Typing a first partial title does not show an error',!await invalid('title')&&await page.locator('.validation-message:not([hidden])').count()===0);
 await page.locator('[name=title]').press('Tab');
 check('Leaving an incomplete title shows its error',await invalid('title')&&await page.locator('#request-error-title').isVisible());
 check('Other untouched required fields stay neutral',!await invalid('question')&&!await invalid('category_id')&&!await invalid('terms'));
 await shot('12-touched-title-error');
 await page.locator('[name=title]').fill(brief.title);check('Correcting a touched title clears its error immediately',!await invalid('title')&&!await page.locator('#request-error-title').isVisible());
 await page.goto(base+'/submit');
 await page.locator('[data-request-next-step]>summary').click();await page.locator('[name=project_type]').selectOption('research_tool');
 check('New tool requirements stay neutral before use',await page.locator('[data-tool-requirements] textarea').evaluateAll(els=>els.every(el=>el.required&&!el.hasAttribute('aria-invalid')&&getComputedStyle(el).borderColor!=='rgb(255, 157, 157)')));
 await page.locator('[name=tool_maintenance]').fill('Short');check('First partial tool entry stays neutral while typing',!await invalid('tool_maintenance'));await page.locator('[name=tool_maintenance]').press('Tab');check('Leaving a short tool entry shows its error',await invalid('tool_maintenance'));
 await page.locator('[name=project_type]').selectOption('research');check('Disabling a touched invalid tool field clears its error',!await invalid('tool_maintenance'));
 await page.locator('[name=external_tools]').locator('xpath=ancestor::details').locator('summary').click();await page.locator('[name=external_tools]').selectOption('free');
 check('New required software stays neutral before use',!await invalid('required_software'));await page.locator('[name=required_software]').focus();await page.locator('[name=required_software]').press('Tab');check('Leaving empty required software shows its error',await invalid('required_software'));
 await page.locator('[name=required_software]').fill('Synthetic local software');check('Correcting software clears its error immediately',!await invalid('required_software'));
 await page.getByText('AI model & reasoning requirements (optional)',{exact:true}).click();await page.locator('[data-ai-effort-menu]>summary').click();
 await page.locator('[name="reasoning_levels[]"][value=high]').check();check('Impossible exact effort has inline configuration guidance',await page.locator('[data-ai-error]').isVisible());await page.locator('[name="reasoning_or_higher[]"][value=high]').check();check('Compatible minimum clears guidance',!await page.locator('[data-ai-error]').isVisible());
 await page.goto(base+'/submit');
 check('Optional empty fields are not errors',!await invalid('email')&&!await invalid('context'));
 let posts=0;const countPost=request=>{if(request.method()==='POST'&&new URL(request.url()).pathname==='/submit')posts++;};page.on('request',countPost);
 await page.locator('[data-request-next-step]>summary').click();await page.locator('.request-submit-actions').getByRole('button',{name:'Submit for approval',exact:true}).click();
 check('Empty request focuses first error without posting',posts===0&&await page.locator('[name=title]').evaluate(el=>el===document.activeElement));
 await page.waitForFunction(()=>{const r=document.querySelector('[name=title]').getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight;});
 check('First required error is in view after a failed submit',await page.locator('[name=title]').evaluate(el=>{const r=el.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight;}));
 for(const name of ['title','question','category_id','terms']){
  check('Submission attempt reveals required error '+name,await invalid(name));
  check('Required error has linked visible guidance '+name,await page.locator('[name='+name+']').evaluate(el=>el.getAttribute('aria-describedby').split(' ').some(id=>{const text=document.getElementById(id);return text&&!text.hidden&&text.textContent.trim();})));
 }
 check('Validated title has red border',await page.locator('[name=title]').evaluate(el=>getComputedStyle(el).borderColor==='rgb(255, 157, 157)'));await shot('13-submit-required-errors');
 for(const value of ['   ','short','x'.repeat(181),'😀'.repeat(46)]){await page.locator('[name=title]').fill(value);check('Incomplete or oversized title stays red '+value.length,await invalid('title'));}
 await page.locator('[name=title]').fill('éééé');check('Unicode title uses the server byte minimum',!await invalid('title'));
 await page.locator('[name=title]').fill('😀'.repeat(45));check('Unicode title uses the server byte maximum',!await invalid('title'));
 await page.locator('[name=title]').fill(brief.title);check('Valid title clears border and guidance',!await invalid('title')&&!await page.locator('#request-error-title').isVisible()&&await page.locator('[name=title]').evaluate(el=>getComputedStyle(el).borderColor!=='rgb(255, 157, 157)'));
 await page.locator('[name=question]').fill('Not enough detail');check('Short question stays invalid',await invalid('question'));
 await page.locator('[name=question]').fill(brief.question);check('Complete question clears error',!await invalid('question'));
 await page.locator('[name=category_id]').selectOption({label:'Environment & Climate'});check('Choosing category clears error',!await invalid('category_id'));
 await page.locator('[data-request-next-step]>summary').click();await page.locator('[name=terms]').check();check('Confirming terms clears error',!await invalid('terms'));
 await page.locator('[name=project_type]').selectOption('research_tool');
 const toolNames=await page.locator('[data-tool-requirements] textarea').evaluateAll(els=>els.map(el=>el.name));
 for(const name of toolNames){check('Tool requirement turns red '+name,await invalid(name));await page.locator('[name='+name+']').fill('Short');check('Short tool requirement remains invalid '+name,await invalid(name));await page.locator('[name='+name+']').fill('A sufficiently detailed synthetic tool requirement.');check('Completed tool requirement clears '+name,!await invalid(name));}
 await page.locator('[name=tool_maintenance]').fill('');await page.locator('[name=project_type]').selectOption('research');check('Switching back disables and clears tool errors',await page.locator('[data-tool-requirements] textarea').evaluateAll(els=>els.every(el=>el.disabled&&!el.hasAttribute('aria-invalid')&&!el.required)));
 const software=page.locator('[name=required_software]'),external=software.locator('xpath=ancestor::details');await external.locator('summary').click();
 await page.locator('[name=external_tools]').selectOption('free');check('Selected software access requires software name',await invalid('required_software'));
 await external.locator('summary').click();await page.locator('.request-submit-actions').getByRole('button',{name:'Submit for approval',exact:true}).click();
 check('Submit reveals collapsed required field without posting',posts===0&&await external.getAttribute('open')!==null&&await software.evaluate(el=>el===document.activeElement));await shot('11-conditional-validation');
 await software.fill('Synthetic local software');check('Naming required software clears error',!await invalid('required_software'));
 await software.fill('');await page.locator('[name=external_tools]').selectOption('none');check('Unneeded software field clears required state',!await invalid('required_software')&&await software.getAttribute('required')===null);
 await page.getByText('AI model & reasoning requirements (optional)',{exact:true}).click();await page.locator('[data-ai-effort-menu]>summary').click();await page.locator('[name="reasoning_levels[]"][value=high]').check();check('Impossible effort is explained inline',await page.locator('[data-ai-error]').isVisible());await page.locator('[name="reasoning_or_higher[]"][value=high]').check();check('Or higher restores planning configuration',!await page.locator('[data-ai-error]').isVisible());await page.locator('[name="reasoning_levels[]"][value=any]').check();check('Any clears specific reasoning options',!await page.locator('[name="reasoning_levels[]"][value=high]').isChecked());
 page.off('request',countPost);
 await page.goto(base+'/submit');
 await page.locator('[name=title]').fill(brief.title);await page.locator('[name=question]').fill(brief.question);await page.locator('[name=category_id]').selectOption({label:'Environment & Climate'});
 await page.locator('[data-request-next-step]>summary').click();await page.getByText('Background, objectives & references',{exact:true}).click();
 for(const key of ['context','success','previous','constraints_text','questions','references_text','tags'])await page.locator('[name='+key+']').fill(brief[key]);
 // Details toggle and viewport resizing finish in animation frames; assert the same sizing invariant after layout settles.
 try{await page.waitForFunction(()=>[...document.querySelectorAll('textarea')].every(el=>el.scrollHeight<=el.clientHeight+2),null,{timeout:5000});}
 catch(error){console.log('Textarea sizing diagnostics:',JSON.stringify(await page.locator('textarea').evaluateAll(els=>els.map(el=>({name:el.name,id:el.id,visible:!!el.getClientRects().length,clientHeight:el.clientHeight,scrollHeight:el.scrollHeight})))));await shot('02-sizing-failure');throw error;}
 check('Long text expands without internal clipping',await page.locator('textarea').evaluateAll(els=>els.every(el=>el.scrollHeight<=el.clientHeight+2)));
 await page.locator('[name=context]').scrollIntoViewIfNeeded();await shot('02-expanding-fields');
 await page.getByRole('button',{name:'Review your request',exact:true}).click();
 check('Review includes the entire original context',await page.locator('#request-preview-content').textContent().then(t=>t.includes(brief.context)));
 await shot('03-complete-review',true);await shot('04-review-desktop');
 await page.getByRole('button',{name:'Back to editing',exact:true}).click();
 await page.locator('[name=question]').fill(brief.question+' <script>window.untrustedPreview=true</script>');
 await page.getByRole('button',{name:'Review your request',exact:true}).click();
 check('Preview renders source text inertly',await page.locator('#request-preview-content').textContent().then(t=>t.includes('<script>')));
 check('Preview never executes source text',await page.evaluate(()=>!window.untrustedPreview));
 await page.getByRole('button',{name:'Back to editing',exact:true}).click();await page.locator('[name=question]').fill(brief.question);
 await page.setViewportSize(sizes[2]);await page.getByRole('button',{name:'Review your request',exact:true}).click();await visibleActions(page,'#request-preview .actions > *','Mobile review');await shot('05-review-mobile');
 check('Mobile review has no horizontal clipping',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 await page.getByRole('button',{name:'Back to editing',exact:true}).click();
 await page.locator('[name=title]').fill('[QA] '+brief.title);
 await page.locator('[name=terms]').check();
 await page.getByRole('button',{name:'Review your request',exact:true}).click();
 await page.locator('#request-preview').getByRole('button',{name:'Submit for approval',exact:true}).click();
 await page.locator('#management-link').waitFor();
 check('Clear successful submission heading',await page.getByRole('heading',{name:'Research request submitted',exact:true}).isVisible());
 check('Receipt identifies submitted title',await page.locator('.receipt-title').textContent()==='[QA] '+brief.title);
 const link=await page.locator('#management-link').inputValue(),rid=new URL(link).pathname.split('/').pop();
 const before=JSON.parse(runPhp(['tools/local-research-state.php','inspect'])).research.find(r=>r.id===rid);
 for(const size of sizes){
  await page.setViewportSize(size);await page.evaluate(()=>scrollTo(0,0));await shot('06-receipt-'+size.width+'x'+size.height,false,[page.locator('#management-link')]);
  await visibleActions(page,'.receipt .primary-actions > *','Updated receipt '+size.width);
 }
 await saveManagementLink(page);
 check('Receipt copy action works',await page.evaluate(()=>navigator.clipboard.readText())===link);
 runPhp(['bin/console.php','admin','browser-qa@example.invalid'],password);
 await page.setViewportSize(sizes[0]);await page.goto(base+'/admin/login');await page.locator('[name=email]').fill('browser-qa@example.invalid');await page.locator('[name=password]').fill(password);await page.getByRole('button',{name:'Sign in',exact:true}).click();
 // Inspect only the disposable pending fixture created by this suite.
 check('Synthetic pending fixture available',!!before);
 for(const size of sizes){
  await page.setViewportSize(size);await page.goto(base+'/admin/research/'+rid);await shot('07-admin-'+size.width+'x'+size.height);
  await visibleActions(page,'.admin-review-heading .primary-actions > *','Admin review '+size.width);
 }
 await page.setViewportSize(sizes[0]);await page.goto(base+'/admin/research/'+rid);
 await page.getByRole('link',{name:'Moderate request',exact:true}).click();
 await page.waitForFunction(()=>document.querySelector('#request-decision').getBoundingClientRect().top>=0&&document.querySelector('#request-decision').getBoundingClientRect().top<100);
 await shot('08-direct-decision');
 check('Moderate request jumps to decision controls',await page.locator('#request-decision').evaluate(el=>el.getBoundingClientRect().top>=0&&el.getBoundingClientRect().top<100));
 await page.getByRole('link',{name:'complete research brief',exact:true}).click();
 check('Decision links back to complete brief',new URL(page.url()).hash==='#request-moderation');
 for(const route of ['/submit','/admin/research/'+rid]){
  await page.setViewportSize({width:683,height:384});await page.goto(base+route);
  await page.evaluate(()=>document.body.style.fontSize='200%');
  check('Enlarged text reflow '+route,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  await shot(route==='/submit'?'09-submit-enlarged':'10-admin-enlarged');
 }
 const nojs=await ctx.browser().newContext({javaScriptEnabled:false});
 const np=await nojs.newPage();await np.goto(base+'/submit');
 await np.locator('[data-request-next-step]>summary').click();check('No-JS submission remains available',await np.locator('.request-submit-actions').getByRole('button',{name:'Submit for approval',exact:true}).isVisible());
 check('No-JS preview control is hidden',!await np.locator('#review-request').isVisible());
 check('No-JS category is natively required',await np.locator('[name=category_id]').getAttribute('required')!==null);
 check('No-JS untouched required field stays neutral',await np.locator('[name=title]').evaluate(el=>getComputedStyle(el).borderColor!=='rgb(255, 157, 157)'));
 await np.locator('.request-submit-actions').getByRole('button',{name:'Submit for approval',exact:true}).press('Enter');
 check('No-JS failed submission highlights missing title',await np.locator('[name=title]').evaluate(el=>el.matches(':user-invalid')&&getComputedStyle(el).borderColor==='rgb(255, 157, 157)'));
 await np.locator('[name=title]').fill('[QA] Native request validation fixture');await np.locator('[name=question]').fill('A synthetic local question verifying the normal HTML submission path without JavaScript.');await np.locator('[name=category_id]').selectOption({label:'Environment & Climate'});await np.locator('[name=terms]').focus();await np.locator('[name=terms]').press('Space');check('No-JS consent can be confirmed with keyboard',await np.locator('[name=terms]').isChecked());
 await np.locator('.request-submit-actions').getByRole('button',{name:'Submit for approval',exact:true}).press('Enter');await np.locator('#management-link').waitFor();check('No-JS bottom submit records a pending request',await np.getByRole('heading',{name:'Research request submitted',exact:true}).isVisible());await nojs.close();
 const after=JSON.parse(runPhp(['tools/local-research-state.php','inspect'])).research.find(r=>r.id===rid);
 check('Synthetic pending request unchanged by inspection',JSON.stringify(before)===JSON.stringify(after));
 check('No browser errors in updated workflow: '+JSON.stringify(errors.map(text=>text.replace(/#[a-f0-9]{64}/gi,'#[REDACTED]'))),errors.length===0);
 fs.writeFileSync(path.join(out,'index.html'),'<!doctype html><meta charset="utf-8"><title>LLM.Giving — submission UX updates</title><style>body{font:16px system-ui;background:#141e28;color:#eaf0f5;max-width:1100px;margin:24px auto;padding:20px}a{color:#c1ee9c}img{max-width:100%;border:1px solid #42574c}figure{margin:32px 0}</style><h1>Submission UX updates</h1><p>Synthetic research brief in the form and preview. Receipt and admin screenshots use the disposable [QA] record; inspection leaves it unchanged. Pink blocks conceal private management links.</p>'+shots.map(s=>'<figure><figcaption>'+s+'</figcaption><a href="'+s+'"><img loading="lazy" src="'+s+'"></a></figure>').join(''));
 console.log('Submission UX: '+shots.length+' fresh screenshots. Synthetic pending request unchanged.');
}
