import {completeContextEnvelope} from './session-reliability-browser.mjs';
import {revealAdminControls} from './admin-controls.mjs';
import {reservationPickup} from './reservation-browser-support.mjs';
import {authorizedHandoff} from './browser-authorization.mjs';
import fs from 'node:fs';
import path from 'node:path';
import {root} from '../tools/runtime.mjs';
export async function workflowQa({page,ctx,base,runPhp,check,visibleActions,password}){
 const out=path.join(root,'storage/qa/workflow');fs.mkdirSync(out,{recursive:true});const shots=[],errors=[];
 const fixture=JSON.parse(runPhp(['tests/workflow-browser-state.php','create']));
 const url=base+'/research/'+fixture.slug;
 const adminCtx=await ctx.browser().newContext({viewport:{width:1366,height:768}}),ap=await adminCtx.newPage();
 const managerCtx=await ctx.browser().newContext({viewport:{width:1366,height:768}}),mp=await managerCtx.newPage();
 const reviewCtx=await ctx.browser().newContext({viewport:{width:1366,height:768}}),rp=await reviewCtx.newPage();
 for(const p of [page,ap,mp,rp])p.on('pageerror',e=>errors.push(e.message));
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 const data=async()=>{const response=await page.request.get(url+'/context.json');check('Public context response',response.ok());return response.json();};
 const tasks=async()=>{const d=await data();return d.workflow.tasks.filter(t=>t.round_no===d.workflow.project.round_no);};
 const task=async kind=>(await tasks()).find(t=>t.kind===kind);
 const payload=(t,outcome='completed')=>JSON.parse(runPhp(['tests/workflow-browser-state.php','payload',fixture.id,t.id,outcome]));
 async function shot(p,name,fullPage=false){await p.screenshot({path:path.join(out,name+'.png'),fullPage});shots.push(name+'.png');}
 async function disclose(p,level='none'){if(await p.locator('[name=human_level]').count()){await p.locator('[name=human_level]').selectOption(level);if(level==='influenced')await p.locator('[name=human_details]').fill('Synthetic QA disclosure: a human suggested additional research context.');}}
 async function attestReview(p,t){
  await p.goto(base+'/volunteer/review/'+fixture.id+'/'+t.id);
  if(await p.getByRole('button',{name:'Save account declaration',exact:true}).count()){
   await p.locator('[name=account_provider]').fill('synthetic-qa');await p.locator('[name=account_reference]').fill('Synthetic workflow reviewer account');await p.locator('[name=account_attestation]').check();await p.getByRole('button',{name:'Save account declaration',exact:true}).click();
  }
  await p.locator('[name=review_account_separation]').check();await p.getByRole('button',{name:'Record my separation attestation',exact:true}).click();
 }
 async function submit(p,t,d){
  if(t.kind==='review')await attestReview(p,t);
  await p.goto(url+'/tasks/'+t.id);await reservationPickup(p,t.revision);await p.locator('[name=payload]').fill(JSON.stringify(d,null,2));await disclose(p);await p.locator('[name=terms]').check();
  await p.getByRole('button',{name:'Submit task result',exact:true}).click();
  await p.getByRole('heading',{name:d.human_interaction?.level==='influenced'?'Packet quarantined — awaiting admin':'Submission accepted',exact:true}).waitFor();check('Conclusion precedes collapsed acceptance evidence',await p.locator('.result-conclusion').evaluate(e=>!!(e.compareDocumentPosition(document.querySelector('.acceptance-checks')||document.querySelector('.research-disclosure'))&Node.DOCUMENT_POSITION_FOLLOWING)));return new URL(p.url()).pathname.split('/').pop();
 }
 const previousLimit=runPhp(['tests/workflow-browser-state.php','limit','30']).trim();
 try{
  runPhp(['bin/console.php','admin','browser-qa@example.invalid'],password);
  await ap.goto(base+'/admin/login');await ap.getByLabel('Email').fill('browser-qa@example.invalid');await ap.getByLabel('Password').fill(password);await ap.getByRole('button',{name:'Sign in',exact:true}).click();
  await ap.goto(base+'/admin/research/'+fixture.id);
  const moderation=ap.locator('form.moderate').filter({has:ap.locator('[name=type][value=request]')});
  await revealAdminControls(moderation);await moderation.locator('[name=state]').selectOption('Approved');await moderation.locator('[name=reason]').fill('Approve the disposable topic once to test autonomous follow-up.');
  await moderation.getByRole('button',{name:'Save moderation decision'}).click();
  check('Approval creates planning stage',(await data()).research.status==='Needs Research Plan');
  let plan=await task('planning');
  for(const size of sizes){
   await page.setViewportSize(size);await page.goto(url);await shot(page,'00-handoff-'+size.width+'x'+size.height);await visibleActions(page,'.research-heading .primary-actions > *','Top handoff '+size.width);check('One primary handoff beside ready status '+size.width,await page.locator('.handoff-start [data-copy-handoff]:not(.secondary)').count()===1&&(await page.locator('.handoff-state').innerText()).includes('Ready for AI planning'));
   const copied=await authorizedHandoff(page);check('Copied issued task code and return route '+size.width,copied.includes('handoff_code')&&copied.includes('/tasks/'+plan.id)&&copied.includes('RETURN PROCEDURE'));
   await page.goto(url+'/work');await shot(page,'01-planning-'+size.width+'x'+size.height);await visibleActions(page,'.workflow-heading .primary-actions > *','Planning '+size.width);
   await page.goto(url+'/tasks/'+plan.id);await shot(page,'02-task-'+size.width+'x'+size.height);await visibleActions(page,'.workflow-heading .primary-actions > *','Task '+size.width);
  }
  await page.setViewportSize({width:683,height:384});await page.goto(url);await page.evaluate(()=>document.body.style.fontSize='200%');await shot(page,'00-handoff-enlarged-text');check('Top handoff enlarged text reflows',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  await page.setViewportSize(sizes[0]);await page.goto(url+'/tasks/'+plan.id);const prompt=await(await page.request.get(url+'/tasks/'+plan.id+'/prompt.md')).text();
  check('Prompt includes immutable context and model policy',completeContextEnvelope(prompt)&&prompt.includes('gpt-6-astra'));check('Handoff includes structured evidence and planning rules',prompt.includes('TRUSTED RESEARCH EVIDENCE STANDARD')&&prompt.includes('evidence_needs')&&prompt.includes('counterevidence'));
  const checkBad=payload(plan);checkBad.handoff_code='0'.repeat(64);
  await page.locator('[name=payload]').fill(JSON.stringify(checkBad));await disclose(page);await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').filter({hasText:'Handoff code does not match'}).waitFor();
  check('Check packet rejects invalid signature before submission',(await task('planning')).state==='Ready');await page.getByRole('button',{name:'Copy AI repair prompt'}).click();const repairPrompt=await page.evaluate(()=>navigator.clipboard.readText());check('Repair prompt preserves identifiers and forbids invented evidence',repairPrompt.includes('Preserve the original handoff_code')&&repairPrompt.includes('invent sources')&&repairPrompt.includes('Handoff code does not match'));await page.locator('[data-workflow-error]').scrollIntoViewIfNeeded();await shot(page,'03a-packet-check-error');await shot(page,'03b-repair-prompt',true);
  const wrong=payload(plan);wrong.handoff_code='0'.repeat(64);await page.locator('[name=payload]').fill(JSON.stringify(wrong));await disclose(page);await page.locator('[name=terms]').check();await page.getByRole('button',{name:'Submit task result',exact:true}).click();check('Wrong handoff code rejected in browser without advancing',(await page.getByRole('alert').innerText()).includes('Handoff code does not match')&&(await task('planning')).state==='Ready');
  const bad=payload(plan);bad.metadata.reasoning='low';
  await page.locator('[name=payload]').fill(JSON.stringify(bad,null,2));await disclose(page);await page.locator('[name=terms]').check();await page.getByRole('button',{name:'Submit task result',exact:true}).click();
  check('Invalid reasoning gives actionable validation',await page.getByRole('alert').textContent().then(t=>t.includes('Max/Ultra')));
  check('Invalid payload retained for correction',(await page.locator('[name=payload]').inputValue()).includes('"low"'));
  await page.getByRole('alert').scrollIntoViewIfNeeded();await shot(page,'03-validation');
  const clarification=payload(plan,'needs_clarification');clarification.reason='The deep-ocean depth threshold must be defined before comparing studies.';clarification.next_action='The submitter should specify a depth threshold and distinguish export from long-term storage.';
  await submit(page,plan,clarification);
  for(const size of sizes){await page.setViewportSize(size);await page.evaluate(()=>scrollTo(0,0));await shot(page,'04-outcome-'+size.width+'x'+size.height);await visibleActions(page,'.workflow-heading .primary-actions > *','Outcome '+size.width);}
  await page.setViewportSize(sizes[0]);await page.goto(url+'/work');await shot(page,'05-needs-clarification');
  check('Clarification pauses project',(await data()).research.status==='Needs Clarification');
  await mp.goto(fixture.link.replace('http://127.0.0.1:8086',base));await mp.waitForURL('**/manage/'+fixture.id);
  await mp.locator('.resolution-panel').waitFor();await mp.locator('.resolution-panel').scrollIntoViewIfNeeded();await shot(mp,'06-submitter-response');
  await mp.getByLabel('Response or corrected scope').fill('Use 1,000 metres as the deep-ocean threshold. Report export from the surface separately.');
  await mp.getByLabel('Short reason for this update').fill('Defined the depth threshold and carbon endpoints.');
  await mp.getByRole('button',{name:'Respond and resume planning'}).click();
  check('Submitter resolves without admin approval',(await data()).research.status==='Needs Research Plan');
  plan=await task('planning');await submit(page,plan,payload(plan,'blocked'));
  await ap.goto(base+'/admin/research/'+fixture.id);await ap.locator('.resolution-panel').scrollIntoViewIfNeeded();await shot(ap,'07-admin-blocker-assistance');
  await ap.getByLabel('Response or corrected scope').fill('Restrict the initial investigation to publicly accessible primary literature and explicitly record unavailable data.');
  await ap.getByLabel('Short reason for this update').fill('Narrowed the scope so missing data does not prevent useful research.');
  await ap.getByRole('button',{name:'Respond and resume planning'}).click();
  check('Admin can resolve blocker',(await data()).research.status==='Needs Research Plan');
  plan=await task('planning');const planned=payload(plan);await page.goto(url+'/tasks/'+plan.id);await reservationPickup(page,plan.revision);await page.locator('[name=payload]').fill(JSON.stringify(planned,null,2));await disclose(page);await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').filter({hasText:'Packet checks passed'}).waitFor();
  check('Preview does not submit',await page.locator('[data-workflow-error]').textContent().then(t=>t.includes('Nothing has been submitted')));
  await page.locator('[data-workflow-error]').scrollIntoViewIfNeeded();await shot(page,'08-plan-preview');
  await submit(page,plan,planned);await page.goto(url+'/work');await shot(page,'09-autonomous-tasks',true);
  check('Plan opens tasks autonomously',(await data()).research.status==='Open for Research');
  const all=await tasks(),a=all.find(t=>t.task_key==='exposure'),b=all.find(t=>t.task_key==='transport');
  check('Dependency initially waiting',b.state==='Waiting');
  const influenced=payload(a,'progress');influenced.human_interaction={level:'influenced',details:'Synthetic QA: extra human guidance supplied after the original handoff.'};
  const influenceId=await submit(page,a,influenced);
  await revealAdminControls(page.locator('[data-influence-status]'));
  check('Accepted result clearly retains influence flag',await page.locator('[data-influence-status]').innerText().then(t=>t.includes('awaiting admin decision')));await shot(page,'09a-influence-flag');for(const size of sizes){await page.setViewportSize(size);await page.evaluate(()=>scrollTo(0,0));await shot(page,'09a-influence-'+size.width);await visibleActions(page,'.workflow-heading .primary-actions > *','Influence result '+size.width);}await page.setViewportSize(sizes[0]);
  await ap.goto(base+'/admin');const influenceEntry=ap.locator('#human-influence article').filter({has:ap.locator('[name=result_id][value="'+influenceId+'"]')});check('Admin has result-specific influence review queue entry',await influenceEntry.isVisible());await influenceEntry.getByText('Human-input disclosures',{exact:true}).click();check('Admin can inspect the influence disclosure',await influenceEntry.innerText().then(t=>t.includes(influenced.human_interaction.details)));
  const influenceForm=ap.locator('form').filter({has:ap.locator('[name=result_id][value="'+influenceId+'"]')});await revealAdminControls(influenceForm);await influenceForm.getByLabel('Review assessment and reason').fill('Synthetic QA review: declared input inspected; no substantive research claim is established.');await shot(ap,'09b-admin-influence-review',true);await influenceForm.getByRole('button',{name:'Approve packet'}).click();
  await page.goto(url+'/results/'+influenceId);await revealAdminControls(page.locator('[data-influence-status]'));check('Influence review is recorded visibly',await page.locator('[data-influence-status]').innerText().then(t=>t.includes('packet approved')));
 check('Progress does not falsely complete task',(await tasks()).find(t=>t.id===a.id).state==='Ready');
  const unsupported=payload(a);unsupported.evidence.claims[0].links=[];await page.goto(url+'/tasks/'+a.id);await reservationPickup(page,a.revision);await page.locator('[name=payload]').fill(JSON.stringify(unsupported));await disclose(page);await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').filter({hasText:'needs inspected supporting evidence'}).waitFor();check('Missing source support cannot advance in browser',(await tasks()).find(t=>t.id===a.id).state==='Ready');await page.locator('[data-workflow-error]').scrollIntoViewIfNeeded();await shot(page,'09c-source-support-rejected');
  await submit(page,a,payload(a,'inconclusive'));check('Dependency unlocks after documented inconclusive result',(await tasks()).find(t=>t.id===b.id).state==='Ready');
  await submit(page,b,payload(b));check('Research unlocks synthesis',(await data()).research.status==='Synthesis');
  let syn=await task('synthesis');const synthesisPacket=payload(syn);synthesisPacket.evidence.sources[0].title='Synthetic fixture <img src=x onerror="window.evidenceInjected=true">';const synthesisId=await submit(page,syn,synthesisPacket);
  for(const size of sizes){
   await page.setViewportSize(size);await page.goto(url+'/results/'+synthesisId);await shot(page,'09d-evidence-result-'+size.width+'x'+size.height);await visibleActions(page,'.workflow-heading .primary-actions > *','Evidence result '+size.width);
   await page.locator('[data-evidence-panel] > summary').click();await page.locator('.evidence-claim').first().scrollIntoViewIfNeeded();await shot(page,'09e-evidence-claim-'+size.width+'x'+size.height);check('Evidence claims and confidence readable '+size.width,await page.locator('[data-evidence-panel]').innerText().then(t=>t.includes('moderate confidence')&&t.includes('Contrary evidence and alternatives')));check('Evidence expansion reflows '+size.width,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   await page.getByRole('link',{name:'Source s1',exact:true}).click();check('Claim passage links to its source '+size.width,new URL(page.url()).hash==='#source-s1'&&await page.locator('#source-s1').isVisible());await shot(page,'09f-evidence-source-'+size.width+'x'+size.height);
  }
  check('Evidence source text is escaped',await page.locator('[data-evidence-panel] img').count()===0&&!(await page.evaluate(()=>window.evidenceInjected)));
  await page.setViewportSize({width:683,height:384});await page.goto(url+'/results/'+synthesisId);await page.evaluate(()=>document.body.style.fontSize='200%');await page.locator('[data-evidence-panel] > summary').click();await page.locator('.evidence-claim').first().scrollIntoViewIfNeeded();await shot(page,'09g-evidence-enlarged-text');check('Evidence enlarged text reflows',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  await page.setViewportSize(sizes[0]);await page.goto(url+'/work');await shot(page,'10-independent-verification');
  let review=await task('review');const targeted=payload(review,'needs_correction');const currentExposure=(await tasks()).find(t=>t.id===a.id);const failedAudit=targeted.evidence.review_checks.find(c=>c.task_id===a.id&&c.result_id===currentExposure.accepted_id);failedAudit.verdict='unsupported';failedAudit.reason='Synthetic review finds the source passage does not support the stated exposure conclusion. Reinspect the source and revise the claim.';targeted.corrections=[];
  await attestReview(rp,review);await rp.goto(url+'/tasks/'+review.id);await rp.locator('[name=payload]').fill(JSON.stringify(targeted));
  for(const size of sizes){await rp.setViewportSize(size);await rp.goto(url+'/tasks/'+review.id);await shot(rp,'10a-review-task-'+size.width+'x'+size.height);await visibleActions(rp,'.workflow-heading .primary-actions > *','Evidence review '+size.width);}await rp.setViewportSize(sizes[0]);await rp.goto(url+'/tasks/'+review.id);await rp.locator('[name=payload]').fill(JSON.stringify(targeted));
  check('Source audit identifies unsupported upstream claim',failedAudit.task_id===a.id&&targeted.corrections.length===0);await shot(rp,'10a-ai-correction-packet',true);
  const correctionId=await submit(rp,review,targeted);await rp.locator('[data-evidence-panel] > summary').click();await rp.locator('.evidence-audit').first().scrollIntoViewIfNeeded();await shot(rp,'10c-source-audit-correction');await revealAdminControls(rp.locator('.acceptance-assessment'));check('Claim audit generated a readable correction',await rp.locator('.acceptance-assessment').innerText().then(t=>t.includes('Research evidence: source support')&&t.includes('Reassess claim c1')));check('Auto-correction retained claim reference',JSON.parse((await data()).workflow.submissions.find(s=>s.id===correctionId).metadata).corrections.some(c=>c.claim_id==='c1'&&c.criterion_id==='evidence-standard'));await shot(rp,'10c-targeted-review-result',true);
  check('Upstream correction reopens research',(await data()).research.status==='Open for Research');
  check('Dependent results invalidated',(await tasks()).filter(t=>['transport','synthesis','review'].includes(t.task_key)).every(t=>t.state==='Waiting'&&!t.accepted_id));
  await page.goto(url+'/work');await shot(page,'10b-targeted-correction',true);
  let revised=(await tasks()).find(t=>t.id===a.id);await submit(page,revised,payload(revised));revised=(await tasks()).find(t=>t.id===b.id);await submit(page,revised,payload(revised));
  syn=await task('synthesis');await submit(page,syn,payload(syn));review=await task('review');await submit(rp,review,payload(review,'verified'));
  await shot(rp,'11e-acceptance-checks',true);
  check('Cycle completes without another approval',(await data()).research.status==='Review Complete');
  for(const size of sizes){await page.setViewportSize(size);await page.goto(url+'/work');await shot(page,'11-review-complete-'+size.width+'x'+size.height);await visibleActions(page,'.workflow-heading .primary-actions > *','Completed findings '+size.width);await page.getByRole('link',{name:'Read findings',exact:true}).click();check('Findings action reaches synthesis',await page.locator('#research-findings').isVisible());}
  await page.setViewportSize(sizes[0]);await page.goto(url);check('Main research page shows synthesis',await page.locator('#research-findings').textContent().then(t=>t.includes('synthetic')));await shot(page,'11b-completed-research',true);
  // Use this run's completed fixture; a fresh clone has no private historical climate project.
  for(const size of sizes){await page.setViewportSize(size);await page.goto(url);await shot(page,'11c-synthetic-findings-'+size.width+'x'+size.height);await visibleActions(page,'.research-heading .primary-actions > *','Completed synthetic findings '+size.width);check('Completed synthetic findings shown',await page.locator('#research-findings').count()===1);}
  await page.setViewportSize(sizes[0]);await page.goto(url);await shot(page,'11d-synthetic-findings-full',true);
  for(const size of sizes){await page.setViewportSize(size);await page.goto(url+'/ledger');await shot(page,'12-ledger-'+size.width+'x'+size.height);await visibleActions(page,'.workflow-heading .primary-actions > *','Ledger '+size.width);}
  check('Ledger has explicit UTC timestamps',await page.locator('time').first().textContent().then(t=>t.endsWith(' UTC')));
  await page.setViewportSize(sizes[0]);await page.goto(url+'/ledger');await shot(page,'13-full-ledger',true);
  const current=await data();check('Context includes entire workflow',current.workflow.submissions.length>=10&&current.workflow.clarifications.length===2);
  check('Public context excludes rejected attempt and private identifiers',!JSON.stringify(current).includes('actor_hash')&&!JSON.stringify(current).includes('Submission not accepted'));
  const first=current.workflow.submissions[0];const snapshot=await page.request.get(url+'/packages/'+first.snapshot_id);check('Historical context still accessible',snapshot.ok());check('Historical package remains earlier state',(await snapshot.json()).research.status==='Needs Research Plan');
  await ap.goto(base+'/admin/research/'+fixture.id+'/ledger');await shot(ap,'14-private-audit',true);
  check('Private rejected attempts visible to admin',await ap.getByRole('heading',{name:'Rejected attempts (private)'}).isVisible());
  await page.setViewportSize({width:683,height:384});await page.goto(url+'/work');await page.evaluate(()=>document.body.style.fontSize='200%');await shot(page,'15-enlarged-text');
  check('Enlarged text reflows',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  check('No browser script exceptions',errors.length===0);
  fs.writeFileSync(path.join(out,'index.html'),'<!doctype html><meta charset="utf-8"><title>LLM.Giving workflow QA</title><style>body{font:16px system-ui;background:#141e28;color:#eaf0f5;max-width:1100px;margin:24px auto;padding:20px}a{color:#c1ee9c}img{max-width:100%;border:1px solid #42574c}figure{margin:32px 0}</style><h1>Autonomous research workflow</h1><p>Disposable synthetic local topic. No real scientific results or AI runs are claimed. Topic approval happens once; subsequent task progression requires no routine admin approval.</p>'+shots.map(s=>'<figure><figcaption>'+s+'</figcaption><a href="'+s+'"><img loading="lazy" src="'+s+'"></a></figure>').join(''));
  console.log('Workflow browser passed; '+shots.length+' screenshots captured.');
 }finally{runPhp(['tests/workflow-browser-state.php','limit',previousLimit]);await adminCtx.close();await managerCtx.close();await reviewCtx.close();}
}
