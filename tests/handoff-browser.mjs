import {reservationPickup} from './reservation-browser-support.mjs';
import {authorizedHandoff} from './browser-authorization.mjs';
import fs from 'node:fs';
import path from 'node:path';
import {createHash} from 'node:crypto';
import {root} from '../tools/runtime.mjs';
export async function handoffQa({page,ctx,base,runPhp,check,visibleActions,password}){
 const out=path.join(root,'storage/qa/v1-safeguards');fs.mkdirSync(out,{recursive:true});const shots=[],cases=[];
 const f=JSON.parse(runPhp(['tests/workflow-browser-state.php','create'])),url=base+'/research/'+f.slug;
 const ac=await ctx.browser().newContext({viewport:{width:1366,height:768}}),ap=await ac.newPage();
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 const shot=async(p,n)=>{await p.screenshot({path:path.join(out,n+'.png')});shots.push(n+'.png');};
 const data=async()=>await(await page.request.get(url+'/context.json')).json();
 const viewports=async(u,n,selector)=>{for(const size of sizes){await page.setViewportSize(size);await page.goto(u);await page.evaluate(()=>scrollTo({top:0,left:0,behavior:'instant'}));await shot(page,n+'-'+size.width);await visibleActions(page,selector,n+' '+size.width);}await page.setViewportSize(sizes[0]);};
 const scenarios=[
 ['initial-edit','influenced','none','Synthetic QA: changed the INITIAL prompt before the first AI message.'],
 ['deleted-instructions','none','influenced','Synthetic QA: deleted instructions from the initial handoff before pasting it.'],
 ['added-sources','influenced','none','Synthetic QA: added selected sources to the initial prompt before the first message.'],
 ['preferred-conclusion','none','influenced','Synthetic QA: requested a preferred conclusion in the first prompt.'],
 ['uncertain','unsure','none','Synthetic QA: unsure whether the first prompt omitted instructions.'],
 ['unchanged','none','none',''],
 ['platform-repair','repair_only','repair_only','Only a platform formatting repair; original handoff unchanged.']
 ];
 try{
  runPhp(['bin/console.php','admin','browser-qa@example.invalid'],password);
  await ap.goto(base+'/admin/login');await ap.getByLabel('Email').fill('browser-qa@example.invalid');await ap.getByLabel('Password').fill(password);await ap.getByRole('button',{name:'Sign in',exact:true}).click();
  await ap.goto(base+'/admin/research/'+f.id);const mod=ap.locator('form.moderate').filter({has:ap.locator('[name=type][value=request]')});await mod.locator('[name=state]').selectOption('Approved');await mod.locator('[name=reason]').fill('Approve synthetic V1 prompt-alteration safeguards test.');await mod.getByRole('button',{name:'Save moderation decision'}).click();
  await viewports(url,'ready','.research-heading .primary-actions > *');
  for(const [name,ai,human,details] of scenarios){
   await page.goto(url);
   const full=await authorizedHandoff(page);const copied=full.split('\n\nPRIVATE VOLUNTEER AUTHORIZATION SUPPLEMENT')[0];
   const archiveHref=await page.locator('[data-issued-archive]').getAttribute('href'),archiveId=archiveHref.split('/').pop();
   const archived=await page.request.get(base+archiveHref),raw=await archived.body(),headers=archived.headers();
   check(name+' public instruction bytes match private copy prefix',copied===raw.toString('utf8'));
   check(name+' archive hash matches exact bytes',headers['x-handoff-sha256']===createHash('sha256').update(raw).digest('hex'));
   const repeated=await page.request.get(base+archiveHref);check(name+' archived GET is byte-for-byte identical',(await repeated.body()).equals(raw));
   check(name+' prompt covers initial edits and deleted instructions',copied.includes('editing the INITIAL prompt')&&copied.includes('deleting instructions or context'));
   const schema=JSON.parse(copied.split('REQUIRED OUTPUT JSON\n')[1].split('\n\nUNTRUSTED TASK DATA')[0]);
   const t=(await data()).workflow.tasks.find(t=>t.kind==='planning');
   const packet=JSON.parse(runPhp(['tests/workflow-browser-state.php','payload',f.id,t.id,'progress']));
   for(const k of ['submission_key','handoff_code','task_revision','context_id'])packet[k]=schema[k];
   packet.human_interaction={level:ai,details:ai==='none'?'':details};
   await page.goto(url+'/tasks/'+t.id);await reservationPickup(page,t.revision);
   if(name==='initial-edit'){
    await shot(page,'task-first-viewport');
    check('Initial-prompt edits are explicit on return form',(await page.locator('fieldset').innerText()).includes('BEFORE the first AI message'));
    await page.locator('fieldset').scrollIntoViewIfNeeded();await shot(page,'expanded-disclosure-options');
    const tampered={...packet,handoff_code:'0'.repeat(64)};
    await page.locator('[name=payload]').fill(JSON.stringify(tampered));await page.locator('[name=human_level]').selectOption(human);
    await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').filter({hasText:'Handoff code does not match'}).waitFor();await page.locator('[data-workflow-error]').scrollIntoViewIfNeeded();await shot(page,'invalid-signature-rejected');
    check('Invalid signature check does not advance',(await data()).workflow.tasks.length===1);
   }
   await page.locator('[name=payload]').fill(JSON.stringify(packet,null,2));await page.locator('[name=human_level]').selectOption(human);await page.locator('[name=human_details]').fill(human==='none'?'':details);
   const checkedResponse=page.waitForResponse(response=>response.request().method()==='POST'&&response.url()===url+'/tasks/'+t.id);
   await page.getByRole('button',{name:'Check packet',exact:true}).click();const validation=await(await checkedResponse).json();check(name+' packet validation: '+validation.message,validation.ok);await page.locator('[data-workflow-error]').filter({hasText:'Packet checks passed'}).waitFor();
   await page.locator('[name=terms]').check();await page.getByRole('button',{name:'Submit task result',exact:true}).click();
   const held=!['unchanged','platform-repair'].includes(name);
   await page.getByRole('heading',{name:held?'Packet quarantined — awaiting admin':'Submission accepted',exact:true}).waitFor();
   const resultUrl=page.url(),id=new URL(resultUrl).pathname.split('/').pop();cases.push({name,resultUrl,archiveId,sha256:headers['x-handoff-sha256']});
   check(name+' verification label is qualified',(await page.locator('[data-verification]').innerText()).includes('Task signature valid.')&&(await page.locator('[data-verification]').innerText()).includes('Conversation integrity is self-reported'));
   await page.locator('[data-verification] summary').click();check(name+' exact issued copy linked',await page.getByRole('link',{name:'Open archived handoff'}).getAttribute('href')==='/research/'+f.slug+'/issued/'+archiveId);await shot(page,name+'-verification');
   if(name==='initial-edit')await viewports(resultUrl,'quarantined','.workflow-heading .primary-actions > *');
   if(held){
    const d=await data();check(name+' cannot advance or enter evidence',d.workflow.tasks.length===1&&d.workflow.tasks[0].state==='Awaiting Admin'&&!d.workflow.submissions.some(s=>s.id===id));
    await ap.goto(base+'/admin/research/'+f.id);const form=ap.locator('form').filter({has:ap.locator('[name=result_id][value="'+id+'"]')});
    await form.getByLabel('Review assessment and reason').fill('Reject synthetic '+name+' packet. Preserve the exact issued prompt and disclose the reported alteration; no scientific evidence is established.');
    if(name==='initial-edit'){await form.scrollIntoViewIfNeeded();await shot(ap,'admin-initial-edit-review');}
    await form.getByRole('button',{name:'Reject packet',exact:true}).click();
    await page.goto(resultUrl);await page.getByRole('heading',{name:'Packet rejected',exact:true}).waitFor();check(name+' rejection preserves archived bytes',(await(await page.request.get(url+'/issued/'+archiveId)).body()).equals(raw));
   }else check(name+' accepted without quarantine',(await data()).workflow.tasks[0].state==='Ready');
  }
  await viewports(cases.at(-1).resultUrl,'repair-accepted','.workflow-heading .primary-actions > *');
  await page.setViewportSize({width:683,height:384});await page.goto(cases[0].resultUrl);await page.locator('[data-verification] summary').click();await page.evaluate(()=>document.body.style.fontSize='200%');check('Expanded checksum reflows at 200% text',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await shot(page,'archive-200-percent');
  fs.writeFileSync(path.join(out,'results.json'),JSON.stringify({research:url,cases},null,2));
 }finally{
  fs.writeFileSync(path.join(out,'index.html'),'<!doctype html><meta charset="utf-8"><title>V1 handoff safeguards</title><style>body{background:#14202b;color:#eee;font:16px system-ui;max-width:1100px;margin:30px auto}img{max-width:100%}a{color:#aac7ff}</style><h1>V1 handoff safeguards</h1><p>Synthetic alteration scenarios; no scientific findings or real AI runs asserted.</p>'+shots.map(n=>'<h2>'+n+'</h2><a href="'+n+'"><img loading="lazy" src="'+n+'"></a>').join(''));await ac.close();
 }
}
