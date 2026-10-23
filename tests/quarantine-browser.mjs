import {reservationPickup} from './reservation-browser-support.mjs';
import {authorizedHandoff} from './browser-authorization.mjs';
import fs from 'node:fs';
import path from 'node:path';
import {root} from '../tools/runtime.mjs';
export async function quarantineQa({page,ctx,base,runPhp,check,visibleActions,password}){
 const out=path.join(root,'storage/qa/quarantine');fs.mkdirSync(out,{recursive:true});const shots=[];
 const fixture=JSON.parse(runPhp(['tests/workflow-browser-state.php','create'])),url=base+'/research/'+fixture.slug;
 const ac=await ctx.browser().newContext({viewport:{width:1366,height:768}}),ap=await ac.newPage();
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 const data=async()=>await(await page.request.get(url+'/context.json')).json();
 const plan=async()=>(await data()).workflow.tasks.find(t=>t.kind==='planning');
 const shot=async(p,n)=>{await p.screenshot({path:path.join(out,n+'.png')});shots.push(n+'.png');};
 const viewports=async(p,u,name,selector)=>{for(const s of sizes){await p.setViewportSize(s);await p.goto(u);await shot(p,name+'-'+s.width);await visibleActions(p,selector,name+' '+s.width);}await p.setViewportSize(sizes[0]);};
 const packet=t=>{const d=JSON.parse(runPhp(['tests/workflow-browser-state.php','payload',fixture.id,t.id]));d.human_interaction={level:'influenced',details:'Synthetic quarantine QA: human asked for a preferred no-effect conclusion. This fixture is not scientific evidence.'};return d;};
 const submit=async(t,d)=>{
  await page.goto(url+'/tasks/'+t.id);await reservationPickup(page,t.revision);await page.locator('[name=payload]').fill(JSON.stringify(d,null,2));await page.locator('[name=human_level]').selectOption('influenced');await page.locator('[name=human_details]').fill('Synthetic QA: requested a preferred conclusion after the original handoff.');
  await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').filter({hasText:'will be quarantined'}).waitFor();await page.locator('[data-workflow-error]').scrollIntoViewIfNeeded();await shot(page,'preflight-hold-warning');
  await page.locator('[name=terms]').check();await page.getByRole('button',{name:'Submit task result',exact:true}).click();await page.getByRole('heading',{name:'Packet quarantined — awaiting admin',exact:true}).waitFor();return page.url();
 };
 try{
  runPhp(['bin/console.php','admin','browser-qa@example.invalid'],password);
  await ap.goto(base+'/admin/login');await ap.getByLabel('Email').fill('browser-qa@example.invalid');await ap.getByLabel('Password').fill(password);await ap.getByRole('button',{name:'Sign in',exact:true}).click();
  await ap.goto(base+'/admin/research/'+fixture.id);const f=ap.locator('form.moderate').filter({has:ap.locator('[name=type][value=request]')});await f.locator('[name=state]').selectOption('Approved');await f.locator('[name=reason]').fill('Approve clearly labeled synthetic quarantine UI test.');await f.getByRole('button',{name:'Save moderation decision'}).click();
  let t=await plan();
  await viewports(page,url,'ready','.research-heading .primary-actions > *');
  check('Ready handoff copied',(await authorizedHandoff(page)).includes('handoff_code'));
  const rejectedUrl=await submit(t,packet(t));const rejectedId=new URL(rejectedUrl).pathname.split('/').pop();
  check('Quarantined plan creates no child tasks',(await data()).workflow.tasks.length===1&&(await plan()).state==='Awaiting Admin'&&!(await plan()).accepted_id);
  check('Quarantined result excluded from evidence',(await data()).workflow.submissions.length===0);
  await viewports(page,rejectedUrl,'quarantined','.workflow-heading .primary-actions > *');
  await viewports(page,url,'project-held','.research-heading .primary-actions > *');
  check('Research clearly shows hold',(await page.locator('.handoff-state').innerText()).includes('Awaiting admin decision'));
  await page.getByRole('button',{name:'Copy AI handoff',exact:true}).click();await page.locator('[data-handoff-status]').filter({hasText:'Copied!'}).waitFor();const heldText=await page.evaluate(()=>navigator.clipboard.readText());check('Held handoff gives admin next action without schema',heldText.includes('A packet is quarantined')&&!heldText.includes('REQUIRED OUTPUT JSON'));
  await page.goto(url+'/tasks/'+t.id);check('Return form unavailable during hold',await page.locator('[name=payload]').count()===0);
  await ap.goto(base+'/admin/research/'+fixture.id);
  let form=ap.locator('form').filter({has:ap.locator('[name=result_id][value="'+rejectedId+'"]')});
  await form.getByLabel('Review assessment and reason').fill('Reject this synthetic preferred-conclusion packet. Do not apply its plan; return a fresh unsteered submission.');
  await form.scrollIntoViewIfNeeded();await shot(ap,'admin-reject');
  await form.getByRole('button',{name:'Reject packet',exact:true}).click();
  await viewports(page,rejectedUrl,'rejected','.workflow-heading .primary-actions > *');
  check('Rejection status clear',await page.getByRole('heading',{name:'Packet rejected',exact:true}).count()===1);
  check('Rejected plan never advanced',(await data()).workflow.tasks.length===1&&(await plan()).state==='Ready'&&(await data()).research.status==='Needs Research Plan');
  check('Rejected packet excluded from evidence',(await data()).workflow.submissions.length===0);
  await page.locator('[data-influence-status] summary').click();await page.locator('[data-influence-status]').scrollIntoViewIfNeeded();await shot(page,'rejected-reason-disclosures');
  t=await plan();const approvedUrl=await submit(t,packet(t)),approvedId=new URL(approvedUrl).pathname.split('/').pop();
  await ap.goto(base+'/admin');check('Admin queue contains quarantine',await ap.locator('#human-influence').innerText().then(x=>x.includes('Quarantined')));
  form=ap.locator('form').filter({has:ap.locator('[name=result_id][value="'+approvedId+'"]')});
  await form.getByLabel('Review assessment and reason').fill('Approve this bounded synthetic QA plan after inspecting its disclosures. Tests workflow admission only; no scientific conclusion is validated.');
  await form.scrollIntoViewIfNeeded();await shot(ap,'admin-approve');
  await form.getByRole('button',{name:'Approve packet',exact:true}).click();
  await viewports(page,approvedUrl,'approved','.workflow-heading .primary-actions > *');
  check('Approved receipt says accepted',await page.getByRole('heading',{name:'Submission accepted',exact:true}).count()===1);
  const d=await data();check('Approval applies plan once',d.workflow.tasks.length===5&&d.research.status==='Open for Research'&&d.workflow.submissions.length===1&&d.workflow.submissions[0].id===approvedId);
  check('Original influence preserved after approval',JSON.parse(d.workflow.submissions[0].metadata).human_influence.human_interaction.level==='influenced');
  check('UTC audit records both decisions',['Quarantined packet rejected','Quarantined packet approved'].every(a=>d.workflow.ledger.some(e=>e.action===a&&e.actor_type==='administrator'&&e.created_at)));
  await page.locator('[data-influence-status] summary').click();await page.locator('[data-influence-status]').scrollIntoViewIfNeeded();await shot(page,'approved-reason-disclosures');
  await page.goto(url);check('Research now offers next real task',(await page.locator('.handoff-state').innerText()).includes('Ready for research'));await shot(page,'research-advanced');
  for(const u of [rejectedUrl,approvedUrl]){await page.setViewportSize({width:683,height:384});await page.goto(u);await page.evaluate(()=>document.body.style.fontSize='200%');check('Decision receipt 200% text reflows',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await shot(page,u===rejectedUrl?'rejected-200-percent':'approved-200-percent');}
  await page.setViewportSize(sizes[0]);await page.goto(url+'/ledger');await shot(page,'utc-ledger-decisions');
  fs.writeFileSync(path.join(out,'result.json'),JSON.stringify({research:url,rejectedUrl,approvedUrl,shots},null,2));
 }finally{
  fs.writeFileSync(path.join(out,'index.html'),'<!doctype html><meta charset="utf-8"><title>Quarantine approval and rejection QA</title><style>body{background:#14202b;color:#eee;font:16px system-ui;max-width:1100px;margin:30px auto}img{max-width:100%}a{color:#aac7ff}</style><h1>Quarantine approval and rejection</h1><p>Synthetic local workflow test. No scientific findings asserted.</p>'+shots.map(n=>'<h2>'+n+'</h2><a href="'+n+'"><img loading="lazy" src="'+n+'"></a>').join(''));await ac.close();
 }
}
