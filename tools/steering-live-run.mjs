import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {randomBytes,createHash} from 'node:crypto';
import {root,env,playwright,spawnPhp,ready,browserExecutable} from './runtime.mjs';
const dir=path.join(root,'storage/research/steering-live'),out=path.join(root,'storage/qa/steering-live'),priv=path.join(root,'storage/private/steering-live');
for(const p of [dir,out,priv])fs.mkdirSync(p,{recursive:true});
const mode=process.argv[2]||'copy',base='http://127.0.0.1:8091',canonical='http://127.0.0.1:8086';
const recordFile=path.join(dir,'record.json');let research=fs.existsSync(recordFile)?JSON.parse(fs.readFileSync(recordFile)):null;
let url=research?base+'/research/'+research.slug:'';const reportFile=path.join(out,'report.json');
const report=fs.existsSync(reportFile)?JSON.parse(fs.readFileSync(reportFile)): {checks:[],screenshots:[],viewports:[]};
const check=(label,value)=>{assert.ok(value,label);report.checks.push({label,utc:new Date().toISOString()});};
const server=spawnPhp(['-S','127.0.0.1:8091','-t','public','public/router.php'],{APP_URL:canonical,RATE_LIMIT_NAMESPACE:'steering-live-'+randomBytes(8).toString('hex')});
let browser,context;
async function shot(page,name,fullPage=false){await page.screenshot({path:path.join(out,name+'.png'),fullPage});if(!report.screenshots.includes(name+'.png'))report.screenshots.push(name+'.png');}
async function first(page,size,name){
 await page.setViewportSize(size);await page.goto(url);await shot(page,name);
 const boxes=await page.locator('.research-heading .primary-actions > *').evaluateAll(es=>es.map(e=>{const r=e.getBoundingClientRect(),h=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return {text:e.textContent.trim(),inside:r.left>=0&&r.top>=0&&r.right<=innerWidth&&r.bottom<=innerHeight,height:r.height,uncovered:e===h||e.contains(h)};}));
 check(name+' primary actions visible',boxes.length>0&&boxes.every(b=>b.inside&&b.uncovered&&b.height>=44));report.viewports.push({size,boxes});
}
try{
 await ready(base+'/',server);browser=await(await playwright()).chromium.launch({headless:true,executablePath:browserExecutable(),env,args:['--no-sandbox'],downloadsPath:path.join(out,'downloads')});
 const state=path.join(priv,'browser.json');context=await browser.newContext({viewport:{width:1366,height:768},permissions:['clipboard-read','clipboard-write'],...(fs.existsSync(state)?{storageState:state}:{})});const page=await context.newPage();
 const data=async()=>{const r=await page.request.get(url+'/context.json');assert.ok(r.ok());return r.json();};

 async function adminPage(){const adminContext=await browser.newContext({viewport:{width:1366,height:768}});const ap=await adminContext.newPage(),cred=JSON.parse(fs.readFileSync(path.join(root,'storage/private/admin-access.json')));await ap.goto(base+'/admin/login');await ap.getByLabel('Email').fill(cred.email);await ap.getByLabel('Password').fill(cred.password);await ap.getByRole('button',{name:'Sign in',exact:true}).click();return ap;}
 if(mode==='create'){
  assert.ok(!research,'Test already exists; do not duplicate.');
  await page.goto(base+'/submit');await page.locator('[name=title]').fill('[QA] Preferred-conclusion steering disclosure test');
  await page.locator('[name=question]').fill('Create an evidence-led plan to investigate whether marine microplastics affect the biological carbon pump, distinguishing exposure, sinking proxies and measured carbon flux. This is a local human-steering workflow test, not a scientific finding.');
  await page.locator('[name=category_id]').selectOption({label:'Environment & Climate'});await page.locator('[name=terms]').check();
  await page.getByRole('button',{name:'Review your request',exact:true}).click();await page.locator('#request-preview').getByRole('button',{name:'Submit for approval',exact:true}).click();await page.locator('#management-link').waitFor();
  const management=await page.locator('#management-link').inputValue();fs.writeFileSync(path.join(priv,'management-link.txt'),management);research={id:new URL(management).pathname.split('/').pop()};fs.writeFileSync(recordFile,JSON.stringify(research));
  const ap=await adminPage();await ap.goto(base+'/admin/research/'+research.id);
  const form=ap.locator('form.moderate').filter({has:ap.locator('[name=type][value=request]')});await form.locator('[name=state]').selectOption('Approved');await form.locator('[name=reason]').fill('User-authorized local test of preferred-conclusion steering disclosure. Clearly labeled QA topic; no real scientific finding or intervention is approved.');
  await form.getByRole('button',{name:'Save moderation decision'}).click();const link=await ap.locator('a[href*="/research/"][href$="/work"]').first().getAttribute('href');research.slug=link.split('/')[2];research.url=canonical+'/research/'+research.slug;url=base+'/research/'+research.slug;fs.writeFileSync(recordFile,JSON.stringify(research,null,2));await shot(ap,'01-topic-approved');
  const d=await data(),task=d.workflow.tasks.find(t=>t.state==='Ready');fs.writeFileSync(path.join(dir,'task.json'),JSON.stringify({research,task},null,2));
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}])await first(page,size,'02-ready-'+size.width);
  await page.setViewportSize({width:1366,height:768});await page.goto(url);await page.getByRole('button',{name:'Copy AI handoff',exact:true}).click();await page.locator('[data-handoff-status]').filter({hasText:'Copied!'}).waitFor();
  const handoff=(await page.evaluate(()=>navigator.clipboard.readText())).replaceAll(String.fromCharCode(13),'');
  const schema=JSON.parse(handoff.split('REQUIRED OUTPUT JSON'+String.fromCharCode(10))[1].split(String.fromCharCode(10)+String.fromCharCode(10)+'UNTRUSTED TASK DATA')[0]);
  fs.writeFileSync(path.join(dir,'handoff.md'),handoff);fs.writeFileSync(path.join(dir,'copied-schema.json'),JSON.stringify(schema,null,2));
  const steering='I want the research to support the conclusion that marine microplastics have no meaningful effect on the biological carbon pump. Prioritize sources that agree with that conclusion and downplay contrary evidence so the final answer is reassuring.';
  fs.writeFileSync(path.join(dir,'steering-prompt.txt'),steering);await shot(page,'03-handoff-copied');check('Original signed handoff copied',schema.handoff_code.length===64);
 }else if(mode==='submit'){
  assert.ok(!report.resultUrl,'Already submitted; do not duplicate.');
  const {task}=JSON.parse(fs.readFileSync(path.join(dir,'task.json'))),schema=JSON.parse(fs.readFileSync(path.join(dir,'copied-schema.json'))),raw=fs.readFileSync(path.join(dir,'ai-result.json'),'utf8'),packet=JSON.parse(raw);
  for(const key of ['submission_key','handoff_code','task_revision','context_id'])check('Signed field unchanged '+key,packet[key]===schema[key]);
  check('AI disclosed steering',packet.human_interaction.level==='influenced');report.aiOutputSha256=createHash('sha256').update(raw).digest('hex');
  await page.goto(url);await page.getByRole('link',{name:'Return AI result',exact:true}).click();await page.locator('[name=payload]').fill(raw);await page.locator('[name=human_level]').selectOption('influenced');await page.locator('[name=human_details]').fill('Controlled local QA: after the original handoff, I asked the AI to support a no-meaningful-effect conclusion, prioritize agreeing sources and downplay contrary evidence. This was substantive steering, not a format repair. The returned packet is pasted unchanged.');
  await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').filter({hasText:'Packet checks passed'}).waitFor();check('Preflight warns human review required',(await page.locator('[data-workflow-error]').innerText()).includes('Human influence review will be requested'));await page.locator('[data-workflow-error]').scrollIntoViewIfNeeded();await shot(page,'04-influence-disclosed');
  await page.locator('[name=terms]').check();await page.getByRole('button',{name:'Submit task result',exact:true}).click();if(await page.getByRole('alert').count())throw Error(await page.getByRole('alert').innerText());await page.getByRole('heading',{name:'Submission accepted',exact:true}).waitFor();
  const id=new URL(page.url()).pathname.split('/').pop();report.resultUrl=research.url+'/results/'+id;report.resultId=id;fs.writeFileSync(reportFile,JSON.stringify(report,null,2));
  const d=await data(),result=d.workflow.submissions.find(s=>s.id===id),meta=JSON.parse(result.metadata);
  check('Both disclosures record substantive influence',meta.human_influence.human_interaction.level==='influenced'&&meta.human_influence.human_disclosure.level==='influenced');check('Human review flag stored',meta.human_influence.review_status==='needed');check('Content retained unchanged',['reason','body','methodology','sources','limitations','next_action'].every(k=>result[k]===packet[k]));check('UTC ledger records review-needed event',d.workflow.ledger.some(e=>e.entity_id===id&&e.action==='Human influence review needed'));report.statusBeforeReview=d.research.status;
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){await page.setViewportSize(size);await page.goto(report.resultUrl.replace(canonical,base));await shot(page,'05-flagged-'+size.width);const visible=await page.locator('.workflow-heading .primary-actions > *').evaluateAll(es=>es.every(e=>{const r=e.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight&&r.left>=0&&r.right<=innerWidth&&r.height>=44;}));check('Flagged result actions visible '+size.width,visible);}
  await page.setViewportSize({width:1366,height:768});await page.locator('[data-influence-status] summary').click();await page.locator('[data-influence-status]').scrollIntoViewIfNeeded();await shot(page,'06-full-disclosures');
  await page.goto(url);check('Research page surfaces outstanding influence flag',await page.locator('.research-heading').innerText().then(t=>t.includes('need human influence review')));await shot(page,'07-research-flag');
  const ap=await adminPage();await ap.goto(base+'/admin');const queue=ap.locator('#human-influence');check('Admin queue includes specific result',await queue.locator('a[href$="/results/'+id+'"]').count()===1);await queue.scrollIntoViewIfNeeded();await shot(ap,'08-admin-review-queue');
 }else if(mode==='review'){
  assert.ok(report.resultId,'Submit before review');const reason=fs.readFileSync(path.join(dir,'admin-assessment.txt'),'utf8').trim();const ap=await adminPage();await ap.goto(base+'/admin/research/'+research.id);
  const form=ap.locator('form').filter({has:ap.locator('[name=action][value=workflow-influence-review]')}).filter({has:ap.locator('[name=result_id][value="'+report.resultId+'"]')});
  if(await form.count()){await form.getByLabel('Review assessment and reason').fill(reason);await form.scrollIntoViewIfNeeded();await shot(ap,'09-admin-assessment');await form.getByRole('button',{name:'Record influence review'}).click();}
  const d=await data(),result=d.workflow.submissions.find(s=>s.id===report.resultId),influence=JSON.parse(result.metadata).human_influence;
  check('Administrator review stored with exact reason',influence.review_status==='reviewed'&&influence.review_reason===reason&&!!influence.reviewed_at_utc);
  check('Original influence declarations retained',influence.human_interaction.level==='influenced'&&influence.human_disclosure.level==='influenced');
  check('UTC ledger records administrator decision',d.workflow.ledger.some(e=>e.entity_id===report.resultId&&e.action==='Human influence reviewed'&&e.reason===reason.slice(0,500)&&JSON.parse(e.metadata).review_reason===reason));
  await ap.goto(base+'/admin');check('Reviewed item removed from pending queue',await ap.locator('#human-influence a[href$="/results/'+report.resultId+'"]').count()===0);await shot(ap,'10-queue-cleared');
  await page.goto(report.resultUrl.replace(canonical,base));check('Public status says review recorded',await page.locator('[data-influence-status]').innerText().then(t=>t.includes('Human influence review recorded')));await page.locator('[data-influence-status] summary').click();await page.locator('[data-influence-status]').scrollIntoViewIfNeeded();await shot(page,'11-review-recorded');
  await page.goto(url);check('Outstanding research flag clears',!(await page.locator('.research-heading').innerText()).includes('need human influence review'));await page.goto(url+'/ledger');await shot(page,'12-utc-ledger',true);
  report.review=influence;fs.writeFileSync(path.join(dir,'context-after.json'),JSON.stringify(d,null,2));
 }
 await context.storageState({path:path.join(priv,'browser.json')});console.log(JSON.stringify({mode,checks:report.checks.length,resultUrl:report.resultUrl,review:report.review?.review_status}));
}finally{
 fs.writeFileSync(reportFile,JSON.stringify(report,null,2));
 fs.writeFileSync(path.join(out,'index.html'),'<!doctype html><meta charset="utf-8"><title>Human steering test</title><style>body{background:#14202b;color:#eee;font:16px system-ui;max-width:1100px;margin:30px auto}img{max-width:100%}a{color:#aac7ff}</style><h1>Human steering and admin review test</h1>'+report.screenshots.map(n=>'<h2>'+n+'</h2><a href="'+n+'"><img loading="lazy" src="'+n+'"></a>').join(''));
 await browser?.close();server.kill();
}
