import {readyDashboard} from './session-wizard-actions.mjs';
import fs from 'node:fs';
import path from 'node:path';
import {root,env} from '../tools/runtime.mjs';
/** Approved design plus real authorization/return regression, using explicitly synthetic research. */
export async function volunteerDesignQa({page,ctx,base,runPhp,check,visibleActions,report}) {
 const out=path.join(root,'storage/qa/volunteer-design');fs.mkdirSync(out,{recursive:true});
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667},{width:1672,height:941}];
 const fixtures=JSON.parse(runPhp(['tests/volunteer-state.php','create'])),sessions=[],contexts=[],failures=[];
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 const shot=async(p,name,privatePage=false)=>{const file=path.join(out,name+'.png');await p.screenshot({path:file,fullPage:false,...(privatePage?{mask:[p.locator('textarea:visible')]}:{})});report.screenshots.push(file);return file;};
 const preset=async(p,key)=>p.locator('.time-card').filter({has:p.locator('input[value="'+key+'"]')}).click();
 async function authorize(p){for(const name of ['authorize','authorized_resources','terms'])await p.locator('[name='+name+']').check();await p.getByRole('button',{name:'Authorize session',exact:true}).click();await p.getByRole('heading',{name:'Your session is ready',exact:true}).waitFor();const link=await p.locator('#volunteer-private-link').inputValue();const id=new URL(link).pathname.split('/')[2];sessions.push(id);return id;}
 try{
  const initial=[];
  for(const size of sizes){await page.setViewportSize(size);await page.goto(base+'/volunteer');initial.push(await shot(page,'initial-'+size.width+'x'+size.height));try{await visibleActions(page,'.time-cards .time-card,.connect-ai','Approved Volunteer '+size.width);check('Initial page starts at scroll zero '+size.width,await page.evaluate(()=>scrollY===0));}catch(error){failures.push(error.message);}}
  console.log('INSPECT APPROVED DESIGN INITIAL VIEWPORTS: '+initial.join(' | '));
  if(env.QA_INSPECT_FIRST==='design'){await new Promise(resolve=>{process.stdin.resume();process.stdin.once('data',()=>{process.stdin.pause();resolve();});});}
  if(failures.length)throw new Error(failures.join('; '));
  await page.setViewportSize(sizes[0]);await page.goto(base+'/volunteer');
  check('Standard is the initial real selection',await page.locator('[name=session_preset][value=standard]').isChecked());
  check('Minimal header links to the actual quick start',await page.locator('.volunteer-header a[href="/volunteer/quick-start"]').isVisible());
  check('Brand uses static two-thirds progress without account telemetry',await page.locator('.volunteer-header .brand-meter').evaluate(el=>{const fill=el.firstElementChild;return el.getAttribute('aria-hidden')==='true'&&Math.abs(fill.getBoundingClientRect().width/el.getBoundingClientRect().width-2/3)<.02;}));
  check('Cause pills use real taxonomy and no placeholder labels',await page.locator('.cause-pills').innerText().then(text=>!text.includes('Cause area')&&text.includes('Wherever needed')));
  for(const [key,summary]of [['quick','15 min or 1 task'],['standard','60 min or 3 tasks'],['long','180 min or 10 tasks'],['unlimited','Until Stop']]){
   await page.goto(base+'/volunteer');await preset(page,key);check('Live summary follows '+key,(await page.locator('[data-volunteer-summary]').innerText()).includes(summary));
   await page.getByRole('button',{name:'Connect my AI',exact:true}).click();await page.getByRole('heading',{name:'Authorize this session',exact:true}).waitFor();
   check('Real authorization reviews '+key,await page.locator('.volunteer-start > .fine').first().innerText().then(text=>text.includes(key==='unlimited'?'no session time or task limit':summary)));
   check('Consent is unchecked before '+key+' authorization',await page.locator('[name=authorize],[name=terms],[name=authorized_resources]').evaluateAll(elements=>elements.length===3&&elements.every(el=>!el.checked)));
  }
  await page.goto(base+'/volunteer');const cause=page.locator('.cause-pill').nth(1);const causeName=await cause.locator('input').getAttribute('data-cause-label');await cause.click();check('Cause pill updates live summary',(await page.locator('[data-volunteer-summary]').innerText()).includes(causeName));await page.getByRole('button',{name:'Connect my AI',exact:true}).click();check('Cause pill is reflected in real server authorization',(await page.locator('.volunteer-start > .fine').first().innerText()).includes('Category: '+causeName));
  await page.goto(base+'/volunteer');await page.locator('[name=session_preset][value=standard]').focus();await page.keyboard.press('ArrowRight');check('Time presets support native keyboard arrow selection',await page.locator('[name=session_preset][value=long]').isChecked());
  await page.getByRole('button',{name:'Connect my AI',exact:true}).focus();check('Primary action receives keyboard focus',await page.getByRole('button',{name:'Connect my AI',exact:true}).evaluate(el=>el===document.activeElement));await shot(page,'keyboard-focus');
  await page.goto(base+'/volunteer');await page.locator('[data-volunteer-advanced] > summary').click();await page.locator('[name=session_preset][value=custom]').check();
  await page.locator('[name=assignment_limit]').fill('7');await page.locator('[name=minutes]').fill('125');check('Advanced custom limits update live summary',(await page.locator('[data-volunteer-summary]').innerText()).includes('125 min or 7 tasks'));
  await page.locator('[name=cause_choice][value=custom]').check();await page.locator('[name=scope_kind]').selectOption('research');await page.locator('[name=research_id]').fill(fixtures[0].id);
  await page.locator('[name=model]').selectOption('gpt-6-astra');await page.locator('[name=reasoning]').selectOption('max');await page.locator('[name=tool]').fill('Codex');
  await page.getByRole('button',{name:'Connect my AI',exact:true}).click();check('Specific research and custom limits survive server review',(await page.locator('.volunteer-start > .fine').first().innerText()).includes('125 min or 7 tasks'));
  await page.goto(base+'/volunteer');await preset(page,'unlimited');await page.locator('[data-volunteer-advanced] > summary').click();
  await page.locator('[name=cause_choice][value=custom]').check();await page.locator('[name=scope_kind]').selectOption('research');await page.locator('[name=research_id]').fill(fixtures[0].id);await page.locator('[name=model]').selectOption('gpt-6-astra');await page.locator('[name=reasoning]').selectOption('max');await page.locator('[name=tool]').fill('Codex');
  await page.getByRole('button',{name:'Connect my AI',exact:true}).click();
  const consentShots=[],consentFailures=[];
  for(const size of sizes.slice(0,3)){await page.setViewportSize(size);await page.evaluate(()=>scrollTo(0,0));consentShots.push(await shot(page,'unlimited-authorization-'+size.width+'x'+size.height));try{await visibleActions(page,'.volunteer-authorization > .form-panel > .primary-actions > *, .volunteer-authorization > .form-panel > .check','Unlimited authorization '+size.width);}catch(error){consentFailures.push(error.message);}}
  console.log('INSPECT UNLIMITED AUTHORIZATION: '+consentShots.join(' | '));
  if(env.QA_INSPECT_FIRST==='design')await new Promise(resolve=>{process.stdin.resume();process.stdin.once('data',()=>{process.stdin.pause();resolve();});});
  if(consentFailures.length)throw new Error(consentFailures.join('; '));
  await page.setViewportSize(sizes[0]);const id=await authorize(page);
  const orchestrator=await page.locator('#orchestrator-handoff').inputValue();check('Actual unlimited orchestrator handoff has null deadline/quota',orchestrator.includes('"limit_mode": "unlimited"')&&orchestrator.includes('"assignment_limit": null')&&orchestrator.includes('"expires_at_utc": null'));
  await readyDashboard(page);check('Unlimited dashboard has honest limits',(await page.locator('.session-meta').innerText()).includes('Until you press Stop')&&!await page.locator('.session-meta').innerText().then(text=>text.includes('min remaining')));
  await page.getByRole('button',{name:'Pick up next task',exact:true}).click();const handoff=await page.locator('#session-task-handoff').inputValue();check('Unlimited pickup supplies complete current worker handoff',handoff.includes('no session deadline or assignment quota')&&handoff.includes('PRIVATE SESSION WORKER PROCEDURE'));
  const returnLink=handoff.match(/Private return link: (http[^\s]+)/)[1];
  const worker=await ctx.browser().newContext({viewport:sizes[0]});contexts.push(worker);const wp=await worker.newPage();await wp.goto(returnLink);await wp.locator('[name=payload]').waitFor();
  const packet=runPhp(['tests/volunteer-state.php','packet',id]);await wp.locator('[name=payload]').fill(packet);await wp.getByRole('button',{name:'Check packet',exact:true}).click();await wp.locator('[data-workflow-error]').getByText('Packet checks passed.',{exact:false}).waitFor();
  await wp.getByRole('button',{name:'Submit task result',exact:true}).click();check('Separate unlimited worker records actual accepted receipt',(await wp.locator('body').innerText()).includes('Submission accepted'));await shot(wp,'unlimited-recorded-receipt');
  await page.goto(base+'/volunteer/'+id);await page.getByRole('button',{name:'Stop session',exact:true}).click();check('Unlimited Stop revokes future pickup',await page.getByText('Session stopped — existing receipts remain available',{exact:true}).isVisible()&&await page.getByRole('button',{name:'Pick up next task',exact:true}).count()===0);
  const native=await ctx.browser().newContext({javaScriptEnabled:false,viewport:sizes[2]});contexts.push(native);const np=await native.newPage();await np.goto(base+'/volunteer');await preset(np,'unlimited');await np.getByRole('button',{name:'Connect my AI',exact:true}).click();const nativeId=await authorize(np);
  check('No-JS unlimited handoff retains real private authorization',(await np.locator('#orchestrator-handoff').inputValue()).includes('"assignment_limit": null'));await readyDashboard(np);await np.getByRole('button',{name:'Stop session',exact:true}).click();check('No-JS unlimited Stop works',await np.getByText('Session stopped — existing receipts remain available',{exact:true}).isVisible());
  await page.goto(base+'/volunteer');await page.setViewportSize(sizes[2]);
  await page.evaluate(()=>{window.__qaFonts=[...document.querySelectorAll('body,body *')].filter(el=>el instanceof HTMLElement).map(el=>[el,el.style.fontSize,parseFloat(getComputedStyle(el).fontSize)]);for(const [el,,size]of window.__qaFonts)if(Number.isFinite(size))el.style.fontSize=size*2+'px';});
  await shot(page,'reflow-200-percent-375x667');check('Approved page reflows at 200% text without horizontal page overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));

  await page.locator('.time-cards').scrollIntoViewIfNeeded();await shot(page,'reflow-200-percent-time-cards');
  check('Enlarged preset text and Popular badge do not overlap',await page.locator('.time-standard').evaluate(card=>{
   const rects=selector=>[...card.querySelectorAll(selector)].flatMap(el=>{const range=document.createRange();range.selectNodeContents(el);return [...range.getClientRects()];});
   const badge=rects('.popular'),text=rects('.time-name,.time-value,.time-task');
   return badge.every(a=>text.every(b=>Math.min(a.right,b.right)-Math.max(a.left,b.left)<=1||Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top)<=1));
  }));
  await page.getByRole('button',{name:'Connect my AI',exact:true}).scrollIntoViewIfNeeded();await shot(page,'reflow-200-percent-connect');

  check('No application script exceptions',errors.length===0);
 }finally{for(const context of contexts)await context.close();runPhp(['tests/volunteer-state.php','cleanup'],JSON.stringify({fixtures,sessions}));}
}
