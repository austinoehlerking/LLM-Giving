import fs from 'node:fs';
import path from 'node:path';
import { root, env } from '../tools/runtime.mjs';
import { connectVolunteer } from './session-wizard-actions.mjs';

export async function volunteerInlineQa({page,ctx,base,runPhp,check,visibleActions,report}) {
 const out=path.join(root,'storage/qa/volunteer-inline');
 fs.mkdirSync(out,{recursive:true});
 const projects=[],sessions=[],contexts=[],errors=[];
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 const mainControls='.setup-time-card,[data-volunteer-advanced]>summary,[data-setup-submit]';
 const modelFields='[name=model],[name=reasoning],[name=worker_choice],[name=tool],[name=workflow_mode]';
 const count=()=>JSON.parse(runPhp(['tests/volunteer-real-start-state.php','counts']));
 const preset=(p,value)=>p.locator('[name=session_preset][value='+value+']');
 page.on('pageerror',error=>errors.push(error.message));
 async function shot(p,name) {
  const file=path.join(out,name+'.png');
  await p.screenshot({path:file,fullPage:false,mask:[p.locator('textarea:visible')]});
  report.screenshots.push(file);
 }
 async function initial(p,label) {
  for(const size of sizes) {
   await p.setViewportSize(size);
   await p.goto(base+'/volunteer');
   await shot(p,label+'-'+size.width+'x'+size.height);
   await visibleActions(p,mainControls,label+' '+size.width);
   check('Quick try and one Continue before interaction '+label+' '+size.width,await preset(p,'quick').isChecked()&&await p.locator('[data-setup-submit]').count()===1&&await p.evaluate(()=>scrollY===0));
   check('Research only is ready without showing capability or model settings '+label+' '+size.width,
    !await p.locator('#volunteer-capabilities').isVisible()&&await p.locator('#volunteer-capabilities').inputValue()==='restricted_web_v1'&&await p.locator(modelFields).count()===0
      &&await p.locator('#volunteer-research-advice').count()===0);
  }
 }
 async function reflow(p,label) {
  await p.setViewportSize(sizes[2]);
  await p.evaluate(()=>{
   const fonts=[...document.querySelectorAll('body,body *')].filter(el=>el instanceof HTMLElement).map(el=>[el,parseFloat(getComputedStyle(el).fontSize)]);
   for(const[el,size]of fonts)if(Number.isFinite(size))el.style.fontSize=size*2+'px';
  });
  await shot(p,label+'-200-percent');
  check(label+' 200% text has no horizontal clipping',await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 }
 try {
  const project=JSON.parse(runPhp(['tests/volunteer-real-start-state.php','create']));
  projects.push(project.id);
  await initial(page,'setup');
  const nativeContext=await ctx.browser().newContext({javaScriptEnabled:false,viewport:sizes[0],reducedMotion:'reduce'});
  contexts.push(nativeContext);
  const native=await nativeContext.newPage();
  await initial(native,'native');
  if(env.QA_INSPECT_FIRST==='volunteer-inline') {
   console.log('INSPECT VOLUNTEER INITIAL '+report.screenshots.join(' | '));
   await new Promise(resolve=>{process.stdin.resume();process.stdin.once('data',()=>{process.stdin.pause();resolve();});});
  }
  await reflow(page,'setup');
  await reflow(native,'native');
  await page.setViewportSize(sizes[0]);
  for(const [key,limit] of Object.entries({quick:'15 min or 1 task',standard:'60 min or 3 tasks',long:'180 min or 10 tasks',unlimited:'no session time or task limit'})) {
   await page.goto(base+'/volunteer');
   await preset(page,key).check();
   const before=count();
   await connectVolunteer(page);
   check(key+' reaches server review with correct limits',await page.locator('.volunteer-authorization>.fine').first().innerText().then(text=>text.includes(limit)&&text.includes('Research only')));
   check(key+' keeps consent blank and creates no session or reservation',await page.locator('[data-volunteer-consent] input[type=checkbox]').evaluateAll(list=>list.every(input=>!input.checked))&&JSON.stringify(count())===JSON.stringify(before));
  }
  await page.goto(base+'/volunteer');
  await page.locator('[data-volunteer-advanced]>summary').click();
  await page.locator('[name=minutes]').fill('125');
  await page.locator('[name=assignment_limit]').fill('7');
  await page.locator('[name=concurrent_limit]').selectOption('2');
  await page.locator('#volunteer-capabilities').selectOption('general');
  check('Editing numeric values selects custom limits',await preset(page,'custom').isChecked());
  await page.locator('[name=minutes]').fill('1');
  const invalid=count();
  await connectVolunteer(page);
  check('Invalid custom limit retains the form and makes no session',await page.locator('[data-volunteer-advanced]').evaluate(el=>el.open)&&await page.locator('[name=minutes]').evaluate(el=>el===document.activeElement)&&JSON.stringify(count())===JSON.stringify(invalid));
  await page.locator('[name=minutes]').fill('125');
  await connectVolunteer(page);
  check('Capability and custom limits reach authorization',await page.locator('.volunteer-authorization>.fine').first().innerText().then(text=>text.includes('125 min or 7 tasks')&&text.includes('Research + tools')&&text.includes('Up to 2')));
  check('Authorization does not include named worker requirements',await page.locator(modelFields).count()===0);
  await page.locator('#startup-settings>summary').click();
  await page.locator('#startup-capabilities').selectOption('restricted_web_v1');
  await page.getByRole('button',{name:'Recheck availability',exact:true}).click();
  check('Changing capability rechecks availability without authorizing',await page.locator('[data-volunteer-consent] [name=session_profile]').inputValue()==='restricted_web_v1'&&!await page.locator('[name=authorize]').isChecked());
  await native.setViewportSize(sizes[0]);
  await native.goto(base+'/volunteer?model=retired-model&reasoning=provider-adaptive');
  check('Native setup keeps the research-only default behind Advanced options',!await native.locator('#volunteer-capabilities').isVisible()&&await native.locator('#volunteer-capabilities').inputValue()==='restricted_web_v1');
  await connectVolunteer(native);
  check('Native legacy URL reaches authorization without model or reasoning fields',await native.locator('[data-volunteer-consent]').count()===1&&await native.locator(modelFields).count()===0);
  for(const name of ['authorize','authorized_resources','terms']) await native.locator('[name='+name+']').check();
  await native.getByRole('button',{name:'Authorize session',exact:true}).press('Enter');
  const link=await native.locator('#volunteer-private-link').inputValue();
  const id=new URL(link).pathname.split('/')[2];
  sessions.push(id);
  const stored=JSON.parse(runPhp(['tests/volunteer-real-start-state.php','inspect',id]));
  check('Native consent creates a Quick try capability session with no inferred worker or pickup',stored.profile==='restricted_web_v1'&&Object.values(stored.settings).every(value=>value==='')&&stored.limit===1&&stored.assignments===0);
  check('No browser script exceptions',errors.length===0);
  report.scope='Native and enhanced capability-only volunteer entry, first viewports and 200% text, isolated synthetic authorization. No model execution or outbound mail.';
 } finally {
  for(const context of contexts)await context.close();
  runPhp(['tests/volunteer-real-start-state.php','cleanup'],JSON.stringify({projects,sessions}));
 }
}
