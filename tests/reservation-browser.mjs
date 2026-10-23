import fs from 'node:fs';import path from 'node:path';import {root} from '../tools/runtime.mjs';import {authorizedHandoff} from './browser-authorization.mjs';
export async function reservationQa({page,ctx,base,runPhp,check,visibleActions}){
 const output=path.join(root,'storage/qa/reservations');fs.mkdirSync(output,{recursive:true});
 const fixture=JSON.parse(runPhp(['tests/browser-return-state.php','create'])),url=base+'/research/'+fixture.slug,taskUrl=url+'/tasks/'+fixture.task;
 const agent=await ctx.browser().newContext({viewport:{width:1366,height:768}}),ap=await agent.newPage();
 const outsider=await ctx.browser().newContext(),op=await outsider.newPage();const shots=[];
 const shot=async(p,name)=>{const file=path.join(output,name+'.png');await p.screenshot({path:file,fullPage:false});shots.push(file);};
 const availability=()=>JSON.parse(runPhp(['tests/reservation-browser-state.php','availability',fixture.id,fixture.task]));
 const post=async(p,path,operation,extra={})=>p.request.post(path,{form:{csrf:await p.locator('[name=csrf]').first().inputValue(),operation,task_revision:'1',...extra}});
 try{
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){
   await page.setViewportSize(size);await page.goto(taskUrl);await shot(page,'task-initial-'+size.width+'x'+size.height);
   await visibleActions(page,'.workflow-heading .primary-actions > *','Reservation task '+size.width);
   await page.getByRole('link',{name:'Authorize AI browser submission',exact:true}).press('Enter');
   check('Keyboard primary action reaches real authorization '+size.width,await page.getByRole('heading',{name:'Authorize your AI',exact:true}).isVisible());
  }
  await page.setViewportSize({width:1366,height:768});await page.goto(url);const handoff=await authorizedHandoff(page),link=handoff.match(/Private return link: (http[^\s]+)/)[1],returnUrl=link.split('#')[0],gid=new URL(returnUrl).pathname.split('/').pop();
  const first=availability();check('Existing authorized pickup acquires shared lease',first.status==='reserved');
  const publicCopy=await page.request.get(taskUrl+'/prompt.md');check('Reference prompt remains readable',(await publicCopy.text()).includes('TASK RESERVATION CONTRACT'));
  check('Recopy does not extend lease',availability().expires_at_utc===first.expires_at_utc);
  await op.goto(taskUrl);check('Another anonymous browser cannot claim',(await post(op,taskUrl+'/reservation','claim')).status()===409);
  check('Another anonymous browser cannot release',(await post(op,taskUrl+'/reservation','release')).status()===403);
  check('CSRF required for reservation mutations',(await op.request.post(taskUrl+'/reservation',{form:{operation:'claim',task_revision:'1'}})).status()===403);
  check('Grant id is insufficient to renew',(await post(op,returnUrl+'/reservation','renew')).status()===403);
  await ap.goto(link);await ap.waitForURL('**/return/'+gid+'/submit');
  check('Authorized AI may renew original actor lease',(await post(ap,returnUrl+'/reservation','renew')).status()===200);
  check('Authorized AI may release original actor lease',(await post(ap,returnUrl+'/reservation','release')).status()===200);
  check('Release is publicly available',availability().status==='available');
  const consentBefore=handoff.split('PRIVATE VOLUNTEER')[0];
  check('Outstanding handoff explicitly reacquires through grant',(await post(ap,returnUrl+'/reservation','claim')).status()===200);
  check('Public archive remains exact',(await(await page.request.get(url+'/issued/'+publicCopy.headers()['x-handoff-id'])).text())===await publicCopy.text());
  runPhp(['tests/reservation-browser-state.php','expire',fixture.id,fixture.task]);
  check('Expired owner cannot renew',(await post(ap,returnUrl+'/reservation','renew')).status()===409);
  await ap.goto(returnUrl+'/submit');const packet=JSON.parse(runPhp(['tests/browser-return-state.php','payload',fixture.id,gid]));
  await ap.locator('[name=payload]').fill(JSON.stringify(packet));await ap.getByRole('button',{name:'Check packet',exact:true}).click();
  await ap.locator('[data-workflow-error]').filter({hasText:'reacquire'}).waitFor();
  check('Expired packet has actionable reacquisition error',(await ap.locator('[data-workflow-error]').innerText()).includes('reacquire'));
  check('AI explicitly reacquires after exact eligibility recheck',(await post(ap,returnUrl+'/reservation','claim')).status()===200);
  const expiry=availability().expires_at_utc;
  await ap.locator('[name=payload]').fill(JSON.stringify(packet));await ap.getByRole('button',{name:'Check packet',exact:true}).click();
  await ap.locator('[data-workflow-error]').filter({hasText:'Packet checks passed'}).waitFor();
  check('Packet check retains expiry',availability().expires_at_utc===expiry);
  await shot(ap,'live-checked-packet');
  await ap.getByRole('button',{name:'Submit task result',exact:true}).click();await ap.getByRole('heading',{name:'Submission accepted',exact:true}).waitFor();const receipt=ap.url();
  check('Recorded progress releases task',availability().status==='available');await shot(ap,'recorded-receipt');
  check('A new actor can pick up after progress',(await post(op,taskUrl+'/reservation','claim')).status()===200);
  const nextExpiry=availability().expires_at_utc;
  await ap.goto(returnUrl+'/submit');check('Consumed link recovers exact receipt',ap.url()===receipt);
  check('Receipt recovery leaves new holder unchanged',availability().expires_at_utc===nextExpiry);
  check('Public context excludes private return credentials',!(await(await page.request.get(url+'/context.json')).text()).includes(new URL(link).hash.slice(1)));
  await page.goto(taskUrl);await page.evaluate(()=>{const values=[...document.querySelectorAll('body,body *')].filter(x=>x instanceof HTMLElement).map(x=>[x,parseFloat(getComputedStyle(x).fontSize)]);for(const [el,size]of values)if(Number.isFinite(size))el.style.fontSize=(size*2)+'px';});
  await shot(page,'task-200-percent-text');check('Enlarged task text reflows',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  check('Historical public handoff text retained in memory',consentBefore.length>100);
  fs.writeFileSync(path.join(output,'gallery.json'),JSON.stringify({screenshots:shots},null,2));
 }finally{await agent.close();await outsider.close();}
}
