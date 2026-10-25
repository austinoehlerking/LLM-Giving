import {readyDashboard} from './session-wizard-actions.mjs';
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {root,env} from '../tools/runtime.mjs';
const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
export async function volunteerQa({page,ctx,base,runPhp,check,visibleActions,report}) {
 const out=path.join(root,'storage/qa/volunteers');fs.mkdirSync(out,{recursive:true});
 const fixtures=JSON.parse(runPhp(['tests/volunteer-state.php','create'])),sessions=[],secrets=[],screenshots=[],contexts=[];
 const browser=ctx.browser();let lastCopied='';
 const state=id=>JSON.parse(runPhp(['tests/volunteer-state.php','state',id]));
 const packet=id=>JSON.parse(runPhp(['tests/volunteer-state.php','packet',id]));
 async function inspect(label) {
  if(env.QA_INSPECT_FIRST!=='1'&&!(env.QA_INSPECT_FIRST||'').split(',').includes(label))return;
  console.log('INSPECT FIRST VIEWPORTS: '+label+' — '+screenshots.slice(-3).join(' | '));
  await new Promise(resolve=>{process.stdin.resume();process.stdin.once('data',()=>{process.stdin.pause();resolve();});});
 }
 async function capture(p,label,selector,reload=null) {
  const failures=[];
  for(const size of sizes) {
   await p.setViewportSize(size);if(reload)await p.goto(reload);
   const file=path.join(out,label+'-'+size.width+'x'+size.height+'.png');
   await p.screenshot({path:file,fullPage:false,mask:[p.locator('#volunteer-private-link:visible'),p.locator('#session-task-handoff:visible')]});screenshots.push(file);report.screenshots.push(file);
   try{await visibleActions(p,selector,label+' '+size.width);
    if(label==='separate-worker')check('Assigned worker packet entry is fully visible '+size.width,await p.locator('textarea[name=payload]').evaluate(el=>{const r=el.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight&&r.height>=44&&document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)===el;}));
   }catch(error){failures.push(error.message);}
  }
  await inspect(label);
  if(failures.length)throw Error(failures.join('; '));
  if(['setup','authorization','dashboard-active','assignment'].includes(label)) {
   await p.setViewportSize(sizes[2]);
   await p.evaluate(()=>{window.__qaFonts=[...document.querySelectorAll('body,body *')].filter(e=>e instanceof HTMLElement).map(e=>[e,e.style.fontSize,parseFloat(getComputedStyle(e).fontSize)]);for(const [e,,size]of window.__qaFonts)if(Number.isFinite(size))e.style.fontSize=(size*2)+'px';});
   const file=path.join(out,label+'-200-percent.png');await p.screenshot({path:file,fullPage:false,mask:[p.locator('#volunteer-private-link:visible'),p.locator('#session-task-handoff:visible')]});screenshots.push(file);report.screenshots.push(file);
   check(label+' reflows with all fonts at 200%',await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   await p.evaluate(()=>{for(const [e,original]of window.__qaFonts)e.style.fontSize=original;delete window.__qaFonts;});
  }
 }
 async function configure(p,fixture,limit=2) {
  const details=p.locator('[data-volunteer-advanced]');if(!await details.evaluate(element=>element.open))await details.locator('summary').click();
  await p.locator('[name=cause_choice][value=custom]').check();await p.locator('[name=scope_kind]').selectOption('research');
  await p.locator('[name=research_id]').fill(fixture.id);
  await p.locator('[name=session_preset][value=custom]').check();await p.locator('[name=assignment_limit]').fill(String(limit));
  await p.locator('[name=model]').selectOption('gpt-6-astra');await p.locator('[name=reasoning]').selectOption('max');await p.locator('[name=tool]').fill('Codex');
  await p.getByRole('button',{name:'Connect my AI',exact:true}).click();
  await p.getByRole('heading',{name:'Authorize this session',exact:true}).waitFor();
 }
 async function authorize(p) {
  await p.locator('[name=authorize]').check();await p.locator('[name=authorized_resources]').check();await p.locator('[name=terms]').check();
  await p.getByRole('button',{name:'Authorize session',exact:true}).click();await p.getByRole('heading',{name:'Your session is ready',exact:true}).waitFor();
  const link=await p.locator('#volunteer-private-link').inputValue(),id=new URL(link).pathname.split('/')[2];sessions.push(id);secrets.push(new URL(link).hash.slice(1));return {id,link};
 }
 async function fresh(p,fixture,limit=3) {await p.goto(base+'/volunteer');await configure(p,fixture,limit);const created=await authorize(p);await readyDashboard(p);return created;}
 async function pick(p) {await p.getByRole('button',{name:'Pick up next task',exact:true}).click();await p.getByRole('heading',{name:'Your reserved task handoff',exact:true}).waitFor();return p.locator('#session-task-handoff').inputValue();}
 async function checkPacket(p,value) {await p.locator('[name=payload]').fill(JSON.stringify(value));await p.getByRole('button',{name:'Check packet',exact:true}).click();await p.locator('[data-workflow-error]').getByText('Packet checks passed.',{exact:false}).waitFor();}
 try {
  await capture(page,'home','.hero .primary-actions > *',base+'/');
  await page.getByRole('link',{name:'Start volunteering',exact:true}).first().click();
  check('Prominent homepage action opens real volunteer setup',new URL(page.url()).pathname==='/volunteer');
  await capture(page,'setup','.time-cards .time-card,.connect-ai',base+'/volunteer');
  check('Visible defaults are three assignments and sixty minutes',await page.locator('[name=assignment_limit]').inputValue()==='3'&&await page.locator('[name=minutes]').inputValue()==='60');
  check('Advanced worker configuration starts collapsed',!await page.locator('[data-volunteer-advanced]').evaluate(element=>element.open));
  check('No account credentials or API keys are requested',await page.locator('input[type=password]').count()===0);
  await configure(page,fixtures[0],2);await capture(page,'authorization','.volunteer-authorization > .form-panel > .primary-actions > *, .volunteer-authorization > .form-panel > .check');
  check('Authorization and legal confirmations start unchecked',!await page.locator('[name=authorize]').isChecked()&&!await page.locator('[name=terms]').isChecked()&&!await page.locator('[name=authorized_resources]').isChecked());
  check('Session does not request an upfront independent declaration',await page.locator('[name=human_level],[name=human_details]').count()===0);
  const first=await authorize(page);await capture(page,'created','[data-wizard-copy=link],[data-wizard-email-toggle],[data-wizard-next="2"]');
  check('Private authorization response contains no analytics loader',await page.locator('[data-google-analytics]').count()===0);
  await readyDashboard(page);await capture(page,'dashboard-empty','[data-session-actions] button');
  check('Reading all dashboard sizes never picks up work',state(first.id).used===0);
  check('Dashboard reports remaining minutes and assignments',await page.locator('.session-meta').innerText().then(text=>text.includes('min remaining')&&text.includes('assignments remaining')));
  const outsider=await browser.newContext({viewport:sizes[0]});contexts.push(outsider);const op=await outsider.newPage();
  check('An unrelated anonymous browser cannot view a private session',(await op.request.get(base+'/volunteer/'+first.id)).status()===403);
  const csrf=await page.locator('[name=csrf]').first().inputValue();
  check('Pickup without CSRF is rejected',(await page.request.post(base+'/volunteer/'+first.id,{form:{operation:'claim'}})).status()===403);
  let handoff=(await pick(page)).replaceAll('\r\n','\n');await capture(page,'handoff','.volunteer-start .primary-actions > *:visible');
  const initial=state(first.id),contextBefore=await (await page.request.get(base+'/research/'+fixtures[0].slug+'/context.json')).text();
  check('Pickup prepares one real reservation and complete handoff',initial.used===1&&!!initial.current&&handoff.includes('Private return link: '));
  const privateReturn=handoff.match(/Private return link: (http[^\s]+)/)[1];secrets.push(new URL(privateReturn).hash.slice(1));
  await page.getByRole('button',{name:'Copy complete handoff',exact:true}).click();
  await page.getByText('Copied. Ready to paste.',{exact:true}).waitFor();
  lastCopied=(await page.evaluate(()=>navigator.clipboard.readText())).replaceAll('\r\n','\n');check('Actual clipboard contains the complete unchanged private handoff',lastCopied===handoff);
  await page.evaluate(()=>{navigator.clipboard.writeText=async()=>{throw new Error('Synthetic clipboard denial');};});
  await page.getByRole('button',{name:'Copy complete handoff',exact:true}).click();
  await page.getByText('Copy unavailable in this browser. Select and copy the text manually.',{exact:true}).waitFor();
  check('Denied clipboard keeps a complete honest manual-copy fallback',(await page.locator('#session-task-handoff').inputValue()).replaceAll('\r\n','\n')===handoff&&state(first.id).lease_expiry===initial.lease_expiry);
  await page.getByRole('link',{name:'Private dashboard',exact:true}).click();await capture(page,'dashboard-active','[data-session-actions] button,[data-session-actions] a');await page.getByRole('button',{name:'Copy task handoff',exact:true}).click();
  check('Recopy reuses exact handoff and allowance',(await page.locator('#session-task-handoff').inputValue()).replaceAll('\r\n','\n')===handoff&&state(first.id).used===1&&state(first.id).lease_expiry===initial.lease_expiry);
  check('Unchanged copying is operational and does not change research context',await (await page.request.get(base+'/research/'+fixtures[0].slug+'/context.json')).text()===contextBefore);
  await page.getByRole('link',{name:'Private dashboard',exact:true}).click();const deadline=state(first.id).deadline;
  await page.getByRole('button',{name:'Renew reservation',exact:true}).click();check('Explicit renewal leaves the session deadline and allowance unchanged',state(first.id).deadline===deadline&&state(first.id).used===1);
  await page.getByRole('link',{name:'Open result form',exact:true}).click();await capture(page,'assignment','#workflow-result .primary-actions button:visible,#workflow-result textarea[name=payload]');
  check('Session result form does not ask for consent again',await page.locator('#workflow-result [name=terms],#workflow-result [name=authorize],#workflow-result [name=human_level]').count()===0);
  const good=packet(first.id),bad={...good,body:'tiny'};await page.locator('[name=payload]').fill(JSON.stringify(bad));
  await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').getByText('Not accepted',{exact:false}).waitFor();
  check('Validation failure preserves entered work and the reservation',await page.locator('[name=payload]').inputValue()===JSON.stringify(bad)&&state(first.id).used===1);
  await checkPacket(page,good);await page.getByRole('button',{name:'Submit task result',exact:true}).click();await page.getByRole('heading',{name:'Submission accepted',exact:true}).waitFor();
  check('First task has a real accepted receipt',state(first.id).recorded===1&&state(first.id).outcomes[0]==='accepted');
  const firstReceipt=page.url(),firstAssignment=initial.current;await page.goto(base+'/volunteer/'+first.id+'/assignments/'+firstAssignment);check('Reopening a completed assignment recovers its receipt',page.url()===firstReceipt);
  await page.goto(base+'/volunteer/'+first.id);handoff=await pick(page);const secondState=state(first.id),secondPacket=packet(first.id),secondReturn=handoff.match(/Private return link: (http[^\s]+)/)[1];secrets.push(new URL(secondReturn).hash.slice(1));
  check('Second pickup uses the same authorization and respects dependencies',secondState.used===2&&secondState.task!==initial.task&&secondState.state==='limit_reached');
  const worker=await browser.newContext({viewport:sizes[0]});contexts.push(worker);const wp=await worker.newPage();await wp.goto(secondReturn);await wp.waitForURL('**/return/*/submit');
  await capture(wp,'separate-worker','.workflow-heading .primary-actions > *,#workflow-result .primary-actions button:visible,#workflow-result textarea[name=payload]');
  check('Private worker exchange removes the secret from browser history',new URL(wp.url()).hash==='');
  check('An assignment worker has no general session-dashboard permission',(await wp.request.get(base+'/volunteer/'+first.id)).status()===403);
  await checkPacket(wp,secondPacket);await wp.getByRole('button',{name:'Submit task result',exact:true}).click();await wp.getByRole('heading',{name:'Submission accepted',exact:true}).waitFor();
  check('Separate-browser worker records with original volunteer lineage',state(first.id).recorded===2&&state(first.id).lineage_preserved);
  const controller=await browser.newContext({viewport:sizes[0]});contexts.push(controller);const cp=await controller.newPage();await cp.goto(first.link);await cp.waitForURL('**/volunteer/'+first.id);
  check('Private session exchange transfers bounded controller permission without new consent',await cp.getByRole('heading',{name:'Volunteer session',exact:true}).isVisible()&&await cp.getByRole('link',{name:'Read recorded receipt',exact:true}).count()===2);
  check('Allowance exhaustion hides additional pickup',await cp.getByRole('button',{name:'Pick up next task',exact:true}).count()===0);
  await cp.getByRole('button',{name:'Stop session',exact:true}).click();check('Explicit Stop becomes terminal while preserving receipt links',state(first.id).state==='stopped'&&await cp.getByRole('link',{name:'Read recorded receipt',exact:true}).count()===2);
  const publicContext=await (await op.request.get(base+'/research/'+fixtures[0].slug+'/context.json')).text();check('Public research contains no session identity or capability',!publicContext.includes(first.id)&&!publicContext.includes(secrets[0]));
  runPhp(['tests/volunteer-state.php','withdraw-study',fixtures[0].id]);await cp.goto(base+'/volunteer/'+first.id);await capture(cp,'history-withdrawn','.site-header .nav-submit');
  check('Withdrawn research preserves honest prior recorded outcomes',await cp.locator('.session-history').getByText('Recorded previously — research unavailable',{exact:true}).count()===2);
  check('Private history hides revoked research and receipt links',await cp.getByRole('link',{name:'Read recorded receipt',exact:true}).count()===0&&!await cp.locator('.session-history').innerText().then(text=>text.includes('Establish marine exposure ranges')));
  const late=await fresh(page,fixtures[1]);await pick(page);await page.getByRole('link',{name:'Open result form',exact:true}).click();const latePacket=packet(late.id),lateText=JSON.stringify(latePacket);await page.locator('[name=payload]').fill(lateText);
  runPhp(['tests/volunteer-state.php','expire-task',late.id]);await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').getByText('Not accepted',{exact:false}).waitFor();
  check('Expired task check preserves the full entered packet',await page.locator('[name=payload]').inputValue()===lateText);
  await page.getByRole('button',{name:'Reacquire this assignment',exact:true}).waitFor();await page.getByRole('button',{name:'Reacquire this assignment',exact:true}).click();
  check('Explicit reacquisition preserves the packet and consumed allowance',await page.locator('[name=payload]').inputValue()===lateText&&state(late.id).used===1&&!!state(late.id).lease_expiry);
  runPhp(['tests/volunteer-state.php','expire-task',late.id]);const lateState=state(late.id);await op.goto(base+'/research/'+fixtures[1].slug+'/tasks/'+lateState.task);
  await capture(op,'competing-task','.workflow-heading .primary-actions > *');
  await op.getByRole('button',{name:'Reserve task',exact:true}).first().click();await op.locator('[data-reservation-status]').getByText('Reserved for you until',{exact:false}).waitFor();
  await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').getByText('Not accepted',{exact:false}).waitFor();
  check('A competing holder blocks reacquisition without changing the signed packet',await page.locator('[name=payload]').inputValue()===lateText&&!await page.getByRole('button',{name:'Reacquire this assignment',exact:true}).isVisible());
  runPhp(['tests/volunteer-state.php','expire-session',late.id]);await page.getByRole('button',{name:'Submit task result',exact:true}).click();
  check('Session expiry denies submission and retains entered work',await page.locator('[name=payload]').inputValue()===lateText&&(await page.locator('[data-workflow-error]').innerText()).includes('expired')&&state(late.id).recorded===0);
  await page.goto(base+'/volunteer/'+late.id);await page.getByRole('button',{name:'Stop session',exact:true}).click();
  const native=await browser.newContext({javaScriptEnabled:false,viewport:sizes[0],reducedMotion:'reduce'});contexts.push(native);const np=await native.newPage();await np.goto(base+'/volunteer');await capture(np,'native-setup','.time-cards .time-card,.connect-ai',base+'/volunteer');
  await configure(np,fixtures[2],1);const nc=await authorize(np);await readyDashboard(np);const nh=await pick(np);
  check('Native no-JavaScript pickup supplies the complete manual-copy handoff',nh.includes('Private return link: ')&&await np.locator('[data-session-copy]').count()===1&&await np.locator('[data-session-copy]').isHidden());
  await np.getByRole('link',{name:'Open result form',exact:true}).click();await checkPacket(np,packet(nc.id));await np.getByRole('button',{name:'Submit task result',exact:true}).click();await np.getByRole('heading',{name:'Submission accepted',exact:true}).waitFor();
  check('Native forms complete an authorized last assignment without JavaScript',state(nc.id).recorded===1&&state(nc.id).used===1);
  await page.goto(base+'/volunteer');await page.setViewportSize(sizes[2]);
  await page.evaluate(()=>{const list=[...document.querySelectorAll('body,body *')].filter(e=>e instanceof HTMLElement).map(e=>[e,parseFloat(getComputedStyle(e).fontSize)]);for(const [e,size]of list)if(Number.isFinite(size))e.style.fontSize=(size*2)+'px';});
  const reflow=path.join(out,'setup-200-percent.png');await page.screenshot({path:reflow,fullPage:false});screenshots.push(reflow);report.screenshots.push(reflow);
  check('Volunteer setup reflows with every font enlarged to 200%',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  fs.writeFileSync(path.join(out,'evidence.json'),JSON.stringify({screenshots,viewports:sizes,scope:'Actual local browser actions using deterministic synthetic research packets. Not real AI execution or scientific validation.',secrets:'Private link and handoff fields masked in screenshot files; raw capabilities are not written to reports.'},null,2));
 } catch(error) {
  let message=String(error.message);for(const secret of secrets)if(secret)message=message.split(secret).join('[REDACTED]');throw new Error(message);
 } finally {
  if(lastCopied){try{const current=(await page.evaluate(()=>navigator.clipboard.readText())).replaceAll('\r\n','\n');if(current===lastCopied)await page.evaluate(()=>navigator.clipboard.writeText(''));}catch{}}
  for(const context of contexts)await context.close();
  runPhp(['tests/volunteer-state.php','cleanup'],JSON.stringify({fixtures,sessions}));
 }
}
