import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {createHash,randomBytes} from 'node:crypto';
import {root,env,playwright,spawnPhp,ready,browserExecutable} from './runtime.mjs';
const dir=path.join(root,'storage/research/climate-awd'),out=path.join(root,'storage/qa/climate-awd'),priv=path.join(root,'storage/private/climate-awd');
for(const p of [dir,out,priv])fs.mkdirSync(p,{recursive:true});
const base='http://127.0.0.1:8088',canonical='http://127.0.0.1:8086';
const action=process.argv[2]||'inspect',key=process.argv[3],file=process.argv[4];
const server=spawnPhp(['-S','127.0.0.1:8088','-t','public','public/router.php'],{APP_URL:canonical,RATE_LIMIT_NAMESPACE:'climate-real-'+randomBytes(8).toString('hex')});
let browser;
const reportFile=path.join(out,'report.json'),report=fs.existsSync(reportFile)?JSON.parse(fs.readFileSync(reportFile)): {checks:[],screenshots:[],viewports:[]};
function check(label,ok){assert.ok(ok,label);report.checks.push({label,utc:new Date().toISOString()});}
async function shot(page,name,fullPage=false,mask=[]){const p=path.join(out,name+'.png');await page.screenshot({path:p,fullPage,mask});if(!report.screenshots.includes(name+'.png'))report.screenshots.push(name+'.png');}
async function actions(page,selector,label){
const boxes=await page.locator(selector).evaluateAll(es=>es.map(e=>{const r=e.getBoundingClientRect(),h=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return {text:e.textContent.trim(),x:r.x,y:r.y,width:r.width,height:r.height,inside:r.left>=0&&r.top>=0&&r.right<=innerWidth&&r.bottom<=innerHeight,uncovered:!!h&&(h===e||e.contains(h))};}));
check(label,boxes.length>0&&boxes.every(b=>b.inside&&b.uncovered&&b.height>=44));report.viewports.push({label,size:page.viewportSize(),boxes});
}
let context,adminContext;
try{
await ready(base+'/',server);browser=await(await playwright()).chromium.launch({headless:true,executablePath:browserExecutable(),env,args:['--no-sandbox'],downloadsPath:path.join(out,'downloads')});
const role=action==='review'?'reviewer':'author',state=path.join(priv,role+'.json');
context=await browser.newContext({viewport:{width:1366,height:768},permissions:['clipboard-read','clipboard-write'],...(fs.existsSync(state)?{storageState:state}:{})});
const page=await context.newPage();
let research=fs.existsSync(path.join(dir,'record.json'))?JSON.parse(fs.readFileSync(path.join(dir,'record.json'))):null;
if(action==='create'){
assert.ok(!research,'A retained climate topic already exists; do not duplicate');
const b=JSON.parse(fs.readFileSync(path.join(dir,'brief.json')));
for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){
await page.setViewportSize(size);await page.goto(base+'/submit');await shot(page,'01-submit-'+size.width);await actions(page,'.form-actions button','Submission first viewport '+size.width);
}
await page.setViewportSize({width:1366,height:768});await page.goto(base+'/submit');
await page.locator('[name=title]').fill(b.title);await page.locator('[name=question]').fill(b.question);await page.locator('[name=category_id]').selectOption({label:'Environment & Climate'});
await page.getByText('Background, objectives & references',{exact:true}).click();
for(const k of ['context','success','previous','constraints_text','questions','references_text','tags'])await page.locator('[name='+k+']').fill(b[k]);
await page.locator('[name=terms]').check();await page.getByRole('button',{name:'Review your request',exact:true}).click();await shot(page,'02-brief-preview',true);
await page.locator('#request-preview').getByRole('button',{name:'Submit for approval',exact:true}).click();await page.locator('#management-link').waitFor();
const management=await page.locator('#management-link').inputValue();fs.writeFileSync(path.join(priv,'management-link.txt'),management);
research={id:new URL(management).pathname.split('/').pop(),title:b.title};
fs.writeFileSync(path.join(dir,'record.json'),JSON.stringify(research,null,2));await shot(page,'03-submitted',false,[page.locator('#management-link')]);check('Submitted through public form',true);
adminContext=await browser.newContext();const ap=await adminContext.newPage();const cred=JSON.parse(fs.readFileSync(path.join(root,'storage/private/admin-access.json')));
await ap.goto(base+'/admin/login');await ap.getByLabel('Email').fill(cred.email);await ap.getByLabel('Password').fill(cred.password);await ap.getByRole('button',{name:'Sign in',exact:true}).click();
await ap.goto(base+'/admin/research/'+research.id);
const form=ap.locator('form.moderate').filter({has:ap.locator('[name=type][value=request]')});
await form.locator('[name=state]').selectOption('Approved');await form.locator('[name=reason]').fill('User authorized this local scientific end-to-end test. Approve this bounded literature topic once; no real field intervention is authorized.');
await form.getByRole('button',{name:'Save moderation decision'}).click();
const links=await ap.locator('a[href*="/research/"][href$="/work"]').evaluateAll(es=>es.map(e=>e.getAttribute('href')));
assert.ok(links.length,'Approved workflow link');research.slug=links[0].split('/')[2];research.url=canonical+'/research/'+research.slug;
fs.writeFileSync(path.join(dir,'record.json'),JSON.stringify(research,null,2));await shot(ap,'04-topic-approved');check('Topic approved through admin form once',true);
}
assert.ok(research?.slug,'Need created approved topic');
const url=base+'/research/'+research.slug;
async function data(){const res=await page.request.get(url+'/context.json');assert.ok(res.ok());return res.json();}
if(action==='submit'||action==='review'){
const d=await data();const t=d.workflow.tasks.find(t=>t.task_key===key&&t.round_no===d.workflow.project.round_no);assert.ok(t,'Requested task exists');
await page.goto(url+'/tasks/'+t.id);const schema=JSON.parse(await page.locator('#workflow-schema').textContent());
const authored=JSON.parse(fs.readFileSync(path.resolve(file),'utf8'));
const payload={...schema,...authored,submission_key:schema.submission_key,task_revision:schema.task_revision,context_id:schema.context_id};
fs.writeFileSync(path.join(dir,'submitted-'+key+'-'+Date.now()+'.json'),JSON.stringify(payload,null,2));
await page.locator('[name=payload]').fill(JSON.stringify(payload,null,2));await page.locator('[name=terms]').check();
await page.locator('[name=human_level]').selectOption('none');await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').filter({hasText:'Packet checks passed'}).waitFor();await shot(page,'preview-'+key,true);
await page.getByRole('button',{name:'Submit task result',exact:true}).click();
const alert=page.getByRole('alert');if(await alert.count())throw new Error(await alert.innerText());
await page.getByRole('heading',{name:'Submission accepted',exact:true}).waitFor();await shot(page,'result-'+key,true);check('Accepted '+key+' '+payload.outcome,true);
}
const d=await data();fs.writeFileSync(path.join(dir,'context-latest.json'),JSON.stringify(d,null,2));
if(action==='inspect'){
 for(const [i,s] of d.workflow.submissions.entries()){
  await page.setViewportSize({width:1366,height:768});await page.goto(url+'/results/'+s.id);
  await shot(page,'history-'+String(i+1).padStart(2,'0')+'-'+s.outcome,true);
  const old=await page.request.get(url+'/packages/'+s.snapshot_id);const bytes=await old.text();
  check('Historical input package exists and hash matches '+s.id,old.ok()&&createHash('sha256').update(bytes).digest('hex')===old.headers()['x-context-sha256']);
 }
 check('UTC audit ledger records every published result',d.workflow.submissions.every(s=>d.workflow.ledger.some(e=>e.entity_id===s.id&&e.action==='Task result submitted')));
}
for(const t of d.workflow.tasks.filter(t=>t.round_no===d.workflow.project.round_no&&t.state==='Ready')){
const res=await page.request.get(url+'/tasks/'+t.id+'/prompt.md');assert.ok(res.ok());fs.writeFileSync(path.join(dir,t.task_key+'-prompt.md'),await res.text());
}
const packageResponse=await page.request.get(url+'/package.json');const packageText=await packageResponse.text();check('Full context SHA-256 matches served bytes',createHash('sha256').update(packageText).digest('hex')===packageResponse.headers()['x-context-sha256']);
fs.writeFileSync(path.join(dir,'full-context.json'),packageText);
for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){
await page.setViewportSize(size);await page.goto(url+'/work');await shot(page,'work-'+size.width);await actions(page,'.workflow-heading .primary-actions > *','Workflow first viewport '+size.width);
await page.goto(url+'/ledger');await shot(page,'ledger-'+size.width);await actions(page,'.workflow-heading .primary-actions > *','Ledger first viewport '+size.width);
}
await page.setViewportSize({width:1366,height:768});await page.goto(url+'/ledger');await shot(page,'ledger-full',true);
await page.setViewportSize({width:683,height:384});await page.goto(url+'/work');await page.evaluate(()=>document.body.style.fontSize='200%');await shot(page,'enlarged-text');check('Enlarged text no horizontal clipping',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
await context.storageState({path:state});
console.log(JSON.stringify({research,status:d.research.status,tasks:d.workflow.tasks.map(t=>({key:t.task_key,state:t.state})),results:d.workflow.submissions.length,checks:report.checks.length}));
}catch(e){report.failure={message:e.message,utc:new Date().toISOString()};throw e;}
finally{
fs.writeFileSync(reportFile,JSON.stringify(report,null,2));
fs.writeFileSync(path.join(out,'index.html'),'<!doctype html><meta charset="utf-8"><title>LLM.Giving climate research test</title><style>body{font:16px system-ui;background:#141e28;color:#eaf0f5;max-width:1100px;margin:24px auto;padding:20px}a{color:#c1ee9c}img{max-width:100%;border:1px solid #42574c}figure{margin:32px 0}</style><h1>Real climate research walkthrough</h1><p>Literature assessment, not a field experiment. Separate planning and review provenance is preserved in the results. Private management credentials are masked.</p>'+report.screenshots.map(s=>'<figure><figcaption>'+s+'</figcaption><a href="'+s+'"><img loading="lazy" src="'+s+'"></a></figure>').join(''));
await adminContext?.close();await context?.close();await browser?.close();server.kill();
}
