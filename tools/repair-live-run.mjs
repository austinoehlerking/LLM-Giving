import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {randomBytes,createHash} from 'node:crypto';
import {root,env,playwright,spawnPhp,ready,browserExecutable} from './runtime.mjs';
const dir=path.join(root,'storage/research/repair-live'),out=path.join(root,'storage/qa/repair-live'),priv=path.join(root,'storage/private/repair-live');
for(const p of [dir,out,priv])fs.mkdirSync(p,{recursive:true});
const mode=process.argv[2]||'copy',base='http://127.0.0.1:8090',canonical='http://127.0.0.1:8086';
const research=JSON.parse(fs.readFileSync(path.join(root,'storage/research/cool-roofs/record.json')));
const url=base+'/research/'+research.slug,reportFile=path.join(out,'report.json');
const report=fs.existsSync(reportFile)?JSON.parse(fs.readFileSync(reportFile)): {checks:[],screenshots:[],viewports:[]};
const check=(label,value)=>{assert.ok(value,label);report.checks.push({label,utc:new Date().toISOString()});};
const server=spawnPhp(['-S','127.0.0.1:8090','-t','public','public/router.php'],{APP_URL:canonical,RATE_LIMIT_NAMESPACE:'repair-live-'+randomBytes(8).toString('hex')});
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
 if(mode==='copy'){
  assert.ok(!fs.existsSync(path.join(dir,'handoff.md')),'A handoff already exists: do not create another review.');
  const before=await data();check('Existing research cycle completed',before.research.status==='Review Complete');
  await page.goto(url);await shot(page,'01-completed-before');
  await page.getByRole('link',{name:'Request another review',exact:true}).click();
  await page.getByLabel('Reason for another review').fill('Recheck whether the cited primary studies support reduced outdoor pedestrian heat from reflective roofs. Distinguish roof from pavement interventions, building-energy from pedestrian comfort outcomes, and simulations from field evidence. Audit the numerical PET/UTCI statements and whether they justify a measured pilot rather than a universal health benefit.');
  await page.getByLabel('Display name',{exact:true}).fill('Local research handoff test');
  if(await page.locator('[name=human_level]').count())await page.locator('[name=human_level]').selectOption('none');await page.locator('[name=terms]').check();await shot(page,'02-review-request',true);await page.getByRole('button',{name:'Open another review task',exact:true}).click();
  const d=await data(),task=d.workflow.tasks.find(t=>t.state==='Ready'&&t.kind==='review');assert.ok(task);
  fs.writeFileSync(path.join(dir,'task.json'),JSON.stringify({research,task},null,2));
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}])await first(page,size,'03-ready-'+size.width);
  await page.setViewportSize({width:1366,height:768});await page.goto(url);await page.getByRole('button',{name:'Copy AI handoff',exact:true}).click();await page.locator('[data-handoff-status]').filter({hasText:'Copied!'}).waitFor();
  const handoff=(await page.evaluate(()=>navigator.clipboard.readText())).replace(/\r\n/g,'\n');
  check('Clipboard contains selected task and full context',handoff.includes('Task ID: '+task.id)&&handoff.includes('UNTRUSTED FULL CONTEXT PACKAGE'));
  const schema=JSON.parse(handoff.split('REQUIRED OUTPUT JSON\n')[1].split('\n\nUNTRUSTED TASK DATA')[0]);
  check('Clipboard contains signed code and return destination',schema.handoff_code.length===64&&handoff.includes(canonical+'/research/'+research.slug+'/tasks/'+task.id));
  fs.writeFileSync(path.join(dir,'handoff.md'),handoff);fs.writeFileSync(path.join(dir,'copied-schema.json'),JSON.stringify(schema,null,2));await shot(page,'04-copied-handoff');
  await page.getByRole('link',{name:'Return AI result',exact:true}).click();check('Return action reaches correct submission form',new URL(page.url()).pathname.endsWith('/tasks/'+task.id)&&await page.locator('[name=payload]').isVisible());await shot(page,'05-return-form');
 }else if(mode==='bad'){
  assert.ok(!fs.existsSync(path.join(dir,'repair-prompt.md')),'Bad-packet test already recorded; do not repeat.');
  const {task}=JSON.parse(fs.readFileSync(path.join(dir,'task.json'))),schema=JSON.parse(fs.readFileSync(path.join(dir,'copied-schema.json')));
  const bad={...schema,outcome:'completed'};
  fs.writeFileSync(path.join(dir,'bad-packet.json'),JSON.stringify(bad,null,2));
  const before=await data();report.beforeResultCount=before.workflow.submissions.length;
  await page.goto(url);await page.getByRole('link',{name:'Return AI result',exact:true}).click();
  await page.locator('[name=payload]').fill(JSON.stringify(bad,null,2));await page.locator('[name=human_level]').selectOption('none');await page.locator('[name=terms]').check();
  await page.getByRole('button',{name:'Submit task result',exact:true}).click();
  const error=await page.getByRole('alert').innerText();check('Bad review outcome rejected',error.includes('outcomes')&&error.includes('verified'));
  await page.getByRole('alert').scrollIntoViewIfNeeded();await shot(page,'06-bad-packet-rejected');
  const after=await data();check('Rejected packet neither recorded nor advanced task',after.workflow.submissions.length===before.workflow.submissions.length&&after.workflow.tasks.find(t=>t.id===task.id).state==='Ready');
  await page.getByRole('button',{name:'Copy AI repair prompt',exact:true}).click();
  await page.locator('[data-repair-status]').filter({hasText:'Copied.'}).waitFor();
  const prompt=(await page.evaluate(()=>navigator.clipboard.readText())).replaceAll(String.fromCharCode(13),'');
  check('Actual clipboard repair contains task and error',prompt.includes(task.id)&&prompt.includes('verified')&&prompt.includes('Preserve the original handoff_code'));
  fs.writeFileSync(path.join(dir,'repair-prompt.md'),prompt);report.rejection=error;
  await page.locator('[data-repair-panel]').scrollIntoViewIfNeeded();await shot(page,'07-repair-prompt-copied');await page.locator('[data-repair-panel] summary').click();await shot(page,'08-repair-prompt-text');
 }else if(mode==='return'){
  assert.ok(!report.resultUrl,'Result already recorded; inspect instead of resubmitting.');
  const {task}=JSON.parse(fs.readFileSync(path.join(dir,'task.json'))),schema=JSON.parse(fs.readFileSync(path.join(dir,'copied-schema.json')));
  const raw=fs.readFileSync(path.join(dir,'corrected-result.json'),'utf8'),packet=JSON.parse(raw);
  for(const key of ['submission_key','handoff_code','task_revision','context_id'])check('Corrected AI preserves '+key,packet[key]===schema[key]);
  check('AI declared repair-only',packet.human_interaction.level==='repair_only');report.aiOutputSha256=createHash('sha256').update(raw).digest('hex');
  const before=await data();check('Task still ready before return',before.workflow.tasks.find(t=>t.id===task.id).state==='Ready');
  await page.goto(url);await page.getByRole('link',{name:'Return AI result',exact:true}).click();
  await page.locator('[name=payload]').fill(raw);
  check('Pasted AI file unchanged', (await page.locator('[name=payload]').inputValue()).replaceAll(String.fromCharCode(13),'')===raw.replaceAll(String.fromCharCode(13),''));
  await page.locator('[name=human_level]').selectOption('repair_only');
  await page.locator('[name=human_details]').fill('Only the site-generated packet repair prompt was returned to the AI. The initial invalid scaffold was deliberate local QA failure injection; no scientific steering was added. The corrected AI output is submitted unchanged.');
  await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').filter({hasText:'Packet checks passed'}).waitFor();
  await page.locator('[data-workflow-error]').scrollIntoViewIfNeeded();await shot(page,'09-corrected-packet-passes');
  let d=await data();check('Check did not submit result',d.workflow.submissions.length===before.workflow.submissions.length);
  await page.locator('[name=terms]').check();const submissionRequest=page.waitForRequest(r=>r.method()==='POST'&&new URL(r.url()).pathname.endsWith('/tasks/'+task.id));await page.getByRole('button',{name:'Submit task result',exact:true}).click();const sent=new URLSearchParams((await submissionRequest).postData()).get('payload');check('Actual submitted packet equals original AI output',sent.replaceAll(String.fromCharCode(13),'')===raw.replaceAll(String.fromCharCode(13),''));
  if(await page.getByRole('alert').count())throw Error(await page.getByRole('alert').innerText());
  await page.getByRole('heading',{name:'Submission accepted',exact:true}).waitFor();
  const id=new URL(page.url()).pathname.split('/').pop();report.resultUrl=canonical+'/research/'+research.slug+'/results/'+id;
  fs.writeFileSync(reportFile,JSON.stringify(report,null,2));
  d=await data();const result=d.workflow.submissions.find(s=>s.id===id),meta=JSON.parse(result.metadata);
  check('Exactly one result accepted',d.workflow.submissions.length===before.workflow.submissions.length+1);
  check('Accepted scientific content unchanged',['body','reason','methodology','sources','limitations','next_action'].every(k=>result[k]===packet[k]));
  check('AI repair-only disclosure persisted',meta.human_influence.human_interaction.level==='repair_only');
  check('Contributor repair-only disclosure persisted',meta.human_influence.human_disclosure.level==='repair_only');
  check('Repair-only did not request substantive influence review',meta.human_influence.review_status==='not_requested');
  check('Acceptance recorded in UTC ledger',d.workflow.ledger.some(e=>e.entity_id===id&&e.action==='Task result submitted'));
  check('Source AI output file remains unchanged',fs.readFileSync(path.join(dir,'corrected-result.json'),'utf8')===raw);
  fs.writeFileSync(path.join(dir,'context-after.json'),JSON.stringify(d,null,2));report.finalStatus=d.research.status;report.disclosure=meta.human_influence;
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){
   await page.setViewportSize(size);await page.goto(report.resultUrl.replace(canonical,base));await shot(page,'10-accepted-'+size.width);
   const boxes=await page.locator('.workflow-heading .primary-actions > *').evaluateAll(es=>es.map(e=>{const r=e.getBoundingClientRect(),h=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return r.top>=0&&r.left>=0&&r.bottom<=innerHeight&&r.right<=innerWidth&&r.height>=44&&(e===h||e.contains(h));}));
   check('Accepted result primary actions in first viewport '+size.width,boxes.every(Boolean));
  }
  await page.setViewportSize({width:1366,height:768});await page.goto(report.resultUrl.replace(canonical,base));await page.locator('[data-influence-status] summary').click();await page.locator('[data-influence-status]').scrollIntoViewIfNeeded();await shot(page,'11-repair-only-disclosure');
  await page.goto(url+'/ledger');await shot(page,'12-ledger',true);
 }
 await context.storageState({path:path.join(priv,'browser.json')});
 console.log(JSON.stringify({mode,checks:report.checks.length,resultUrl:report.resultUrl,status:report.finalStatus}));
}finally{
 fs.writeFileSync(reportFile,JSON.stringify(report,null,2));
 fs.writeFileSync(path.join(out,'index.html'),'<!doctype html><meta charset="utf-8"><title>Real AI packet repair test</title><style>body{background:#14202b;color:#eee;font:16px system-ui;max-width:1100px;margin:30px auto}img{max-width:100%;border:1px solid #405060}a{color:#aac7ff}</style><h1>Real AI packet repair test</h1>'+report.screenshots.map(n=>'<h2>'+n+'</h2><a href="'+n+'"><img loading="lazy" src="'+n+'"></a>').join(''));
 await context?.close();await browser?.close();server.kill();
}
