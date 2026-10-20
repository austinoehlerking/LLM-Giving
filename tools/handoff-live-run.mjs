import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {randomBytes} from 'node:crypto';
import {root,env,playwright,spawnPhp,ready,browserExecutable} from './runtime.mjs';
const dir=path.join(root,'storage/research/handoff-live'),out=path.join(root,'storage/qa/handoff-live'),priv=path.join(root,'storage/private/handoff-live');
for(const p of [dir,out,priv])fs.mkdirSync(p,{recursive:true});
const mode=process.argv[2]||'copy',base='http://127.0.0.1:8089',canonical='http://127.0.0.1:8086';
const research=JSON.parse(fs.readFileSync(path.join(root,'storage/research/cool-roofs/record.json')));
const url=base+'/research/'+research.slug,reportFile=path.join(out,'report.json');
const report=fs.existsSync(reportFile)?JSON.parse(fs.readFileSync(reportFile)): {checks:[],screenshots:[],viewports:[]};
const check=(label,value)=>{assert.ok(value,label);report.checks.push({label,utc:new Date().toISOString()});};
const server=spawnPhp(['-S','127.0.0.1:8089','-t','public','public/router.php'],{APP_URL:canonical,RATE_LIMIT_NAMESPACE:'handoff-live-'+randomBytes(8).toString('hex')});
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
 }else if(mode==='form'){
  const {task}=JSON.parse(fs.readFileSync(path.join(dir,'task.json')));
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){
   await page.setViewportSize(size);await page.goto(url);await page.getByRole('link',{name:'Return AI result',exact:true}).click();
   await page.waitForFunction(()=>{const e=document.querySelector('#task-submit');return e&&e.getBoundingClientRect().top>=0&&e.getBoundingClientRect().top<100;},{},{timeout:5000}).catch(async()=>{report.returnPosition=await page.locator('#task-submit').evaluate(e=>({top:e.getBoundingClientRect().top,hash:location.hash,scrollY}));});
   await shot(page,'05-return-settled-'+size.width);check('Return link scrolls to actual form '+size.width,new URL(page.url()).hash==='#task-submit');
  }
  await page.setViewportSize({width:683,height:384});await page.goto(url);await page.evaluate(()=>document.body.style.fontSize='200%');await shot(page,'03-enlarged-text');check('Enlarged text reflows',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 }else if(mode==='submit'){
  const {task}=JSON.parse(fs.readFileSync(path.join(dir,'task.json'))),schema=JSON.parse(fs.readFileSync(path.join(dir,'copied-schema.json'))),packet=JSON.parse(fs.readFileSync(path.join(dir,'ai-result.json')));
  for(const k of ['submission_key','handoff_code','task_revision','context_id'])check('AI preserved '+k,packet[k]===schema[k]);
  const before=await data(),count=before.workflow.submissions.length;assert.ok(before.workflow.tasks.find(t=>t.id===task.id).state==='Ready');
  await page.goto(url);await page.getByRole('link',{name:'Return AI result',exact:true}).click();
  const bad={...packet,handoff_code:(packet.handoff_code[0]==='0'?'1':'0')+packet.handoff_code.slice(1)};
  await page.locator('[name=payload]').fill(JSON.stringify(bad,null,2));await page.getByRole('button',{name:'Check packet',exact:true}).click();await shot(page,'06-bad-packet-preview');
  if(await page.locator('[name=human_level]').count())await page.locator('[name=human_level]').selectOption('none');await page.locator('[name=terms]').check();await page.getByRole('button',{name:'Submit task result',exact:true}).click();
  check('Bad signed packet rejected',await page.getByRole('alert').innerText().then(t=>t.includes('Handoff code does not match')));
  await page.getByRole('alert').scrollIntoViewIfNeeded();await shot(page,'07-bad-code-rejected');
  let d=await data();check('Bad code caused no public result or task advancement',d.workflow.submissions.length===count&&d.workflow.tasks.find(t=>t.id===task.id).state==='Ready');
  const missing={...packet,acceptance_checks:[]};await page.locator('[name=payload]').fill(JSON.stringify(missing,null,2));if(await page.locator('[name=human_level]').count())await page.locator('[name=human_level]').selectOption('none');await page.locator('[name=terms]').check();await page.getByRole('button',{name:'Submit task result',exact:true}).click();
  check('Valid signature cannot bypass acceptance checks',await page.getByRole('alert').count()===1);await page.getByRole('alert').scrollIntoViewIfNeeded();await shot(page,'08-missing-evidence-rejected');report.acceptanceError=await page.getByRole('alert').innerText();
  d=await data();check('Missing evidence caused no public result or task advancement',d.workflow.submissions.length===count&&d.workflow.tasks.find(t=>t.id===task.id).state==='Ready');
  await page.locator('[name=payload]').fill(JSON.stringify(packet,null,2));await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').scrollIntoViewIfNeeded();await shot(page,'09-valid-packet-preview');
  if(await page.locator('[name=human_level]').count())await page.locator('[name=human_level]').selectOption('none');await page.locator('[name=terms]').check();await page.getByRole('button',{name:'Submit task result',exact:true}).click();if(await page.getByRole('alert').count())throw Error(await page.getByRole('alert').innerText());
  await page.getByRole('heading',{name:'Submission accepted',exact:true}).waitFor();await shot(page,'10-result-recorded');await shot(page,'11-result-full',true);
  report.resultUrl=page.url().replace(base,canonical);d=await data();check('Exactly one real result recorded',d.workflow.submissions.length===count+1);check('Result present in UTC ledger',d.workflow.ledger.some(e=>e.entity_id===new URL(page.url()).pathname.split('/').pop()));
  report.finalStatus=d.research.status;fs.writeFileSync(path.join(dir,'context-after.json'),JSON.stringify(d,null,2));
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}])await first(page,size,'12-after-'+size.width);
  await page.setViewportSize({width:1366,height:768});await page.goto(url+'/ledger');await shot(page,'13-ledger',true);
 }
 if(mode==='inspect'){
  const packet=JSON.parse(fs.readFileSync(path.join(dir,'ai-result.json'))),d=await data(),resultId=new URL(report.resultUrl).pathname.split('/').pop();
  const results=d.workflow.submissions.filter(s=>s.id===resultId);check('Exactly one matching recorded result',results.length===1);
  check('Recorded scientific content matches unchanged AI packet',['body','methodology','sources','limitations','reason','next_action'].every(k=>results[0][k]===packet[k]));
  check('Result present in UTC ledger',d.workflow.ledger.some(e=>e.entity_id===resultId&&e.action==='Task result submitted'));
  report.resultUrl=report.resultUrl.split('#')[0];report.finalStatus=d.research.status;
  fs.writeFileSync(path.join(dir,'context-after.json'),JSON.stringify(d,null,2));
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}])await first(page,size,'12-after-'+size.width);
  await page.setViewportSize({width:1366,height:768});await page.goto(url+'/results/'+resultId);await shot(page,'10-result-recorded');await shot(page,'11-result-full',true);await page.goto(url+'/ledger');await shot(page,'13-ledger',true);
 }
 await context.storageState({path:path.join(priv,'browser.json')});
 console.log(JSON.stringify({mode,checks:report.checks.length,resultUrl:report.resultUrl,status:report.finalStatus}));
}finally{
 fs.writeFileSync(reportFile,JSON.stringify(report,null,2));
 fs.writeFileSync(path.join(out,'index.html'),'<!doctype html><meta charset="utf-8"><title>Real AI handoff test</title><style>body{background:#14202b;color:#eee;font:16px system-ui;max-width:1100px;margin:30px auto}img{max-width:100%;border:1px solid #405060}a{color:#aac7ff}</style><h1>Real AI handoff test</h1>'+report.screenshots.map(n=>'<h2>'+n+'</h2><a href="'+n+'"><img loading="lazy" src="'+n+'"></a>').join(''));
 await context?.close();await browser?.close();server.kill();
}
