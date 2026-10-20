import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {createHash,randomBytes} from 'node:crypto';
import {root,env,playwright,spawnPhp,ready,browserExecutable} from './runtime.mjs';
const dir=path.join(root,'storage/research/cool-roofs'),out=path.join(root,'storage/qa/cool-roofs'),priv=path.join(root,'storage/private/cool-roofs');
for(const p of [dir,out,priv])fs.mkdirSync(p,{recursive:true});
const base='http://127.0.0.1:8088',canonical='http://127.0.0.1:8086';
const action=process.argv[2]||'inspect',key=process.argv[3],file=process.argv[4];
const server=spawnPhp(['-S','127.0.0.1:8088','-t','public','public/router.php'],{APP_URL:canonical,RATE_LIMIT_NAMESPACE:'cool-roofs-real-'+randomBytes(8).toString('hex')});
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
assert.ok(!research,'A retained cool-roofs topic already exists; do not duplicate');
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
const payload={...schema,...authored,submission_key:schema.submission_key,handoff_code:schema.handoff_code,task_revision:schema.task_revision,context_id:schema.context_id};
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
  if(s.outcome==='verified')await shot(page,'readable-acceptance');
  const headings=await page.locator('.acceptance-card h3').allTextContents();check('Readable acceptance criteria '+s.id,headings.length>0&&headings.every(t=>!/^criterion-\d/.test(t)&&t.length>20));
  await page.locator('.acceptance-card summary').first().click();check('Acceptance evidence expands '+s.id,await page.locator('.acceptance-card blockquote').first().isVisible());
  const old=await page.request.get(url+'/packages/'+s.snapshot_id);const bytes=await old.text();
  check('Historical input package exists and hash matches '+s.id,old.ok()&&createHash('sha256').update(bytes).digest('hex')===old.headers()['x-context-sha256']);
 }
 check('UTC audit ledger records every published result',d.workflow.submissions.every(s=>d.workflow.ledger.some(e=>e.entity_id===s.id&&e.action==='Task result submitted')));
 check('Every terminal result retains acceptance assessments',d.workflow.submissions.filter(s=>['completed','inconclusive','verified'].includes(s.outcome)).every(s=>JSON.parse(s.metadata).acceptance_checks?.length>0));
 if(d.research.status==='Review Complete'){for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){await page.setViewportSize(size);await page.goto(url);await shot(page,'final-findings-'+size.width);await actions(page,'.research-heading .primary-actions > *','Findings first viewport '+size.width);check('Completed findings visible '+size.width,await page.locator('#research-findings').count()===1);
 await page.locator('.research-heading').getByRole('link',{name:'For AI agents',exact:true}).click();check('Top agent action navigates '+size.width,new URL(page.url()).hash==='#for-ai-agents');await page.locator('#for-ai-agents').evaluate(e=>e.scrollIntoView({block:'start',behavior:'instant'}));await shot(page,'for-ai-agents-'+size.width);
 check('Research details collapsed by default '+size.width,await page.locator('#full-research,#research-sources,#question,#research-limitations,#research-next-steps,#research-history').evaluateAll(es=>es.length===6&&es.every(e=>e.tagName==='DETAILS'&&!e.open)));
 check('Skimmable overview remains visible '+size.width,await page.locator('.findings-summary').isVisible()&&await page.locator('.findings-takeaways').isVisible());
 for(const id of ['full-research','research-sources','question','research-limitations','research-next-steps','research-history']){await page.locator('#'+id+' > summary').click();check('Disclosure opens '+id+' '+size.width,await page.locator('#'+id).evaluate(e=>e.open));await page.locator('#'+id+' > summary').focus();await page.keyboard.press('Enter');check('Keyboard collapses '+id+' '+size.width,await page.locator('#'+id).evaluate(e=>!e.open));}
 await page.locator('#full-research').evaluate(e=>e.scrollIntoView({block:'start',behavior:'instant'}));await shot(page,'collapsed-details-'+size.width);}await page.setViewportSize({width:1366,height:768});await page.goto(url);await shot(page,'final-findings-full',true);
 await page.locator('.findings-summary').evaluate(e=>e.scrollIntoView({block:'start',behavior:'instant'}));await shot(page,'summary-and-takeaways');
 await page.goto(url);check('Contents initially collapsed',await page.locator('.toc-panel').isHidden());
 check('Empty secondary sections omitted',await page.locator('#contributions,#reviews,#feedback,#review-prompts,#findings').count()===0);
 check('No empty-section navigation links',await page.locator('a[href="#contributions"],a[href="#reviews"],a[href="#feedback"],a[href="#review-prompts"],a[href="#findings"]').count()===0);
 check('Feedback action remains available',await page.getByRole('link',{name:'Add feedback or a correction',exact:true}).count()===1);
 check('Completed independent scientific review preserved',await page.locator('#research-findings').getByRole('heading',{name:'Independent review',exact:true}).count()===1);
 await page.locator('.toc-toggle').hover();check('Hover reveals contents',await page.locator('.toc-panel').isVisible());await shot(page,'toc-desktop-open');
 await page.locator('.toc-panel a[href="#research-sources"]').click();check('Contents navigates to sources',new URL(page.url()).hash==='#research-sources'&&await page.locator('.toc-panel').isHidden());check('Contents opens sources disclosure',await page.locator('#research-sources').evaluate(e=>e.open));
 await page.locator('.toc-toggle').focus();await page.keyboard.press('ArrowDown');check('Keyboard opens contents',await page.locator('.toc-panel').isVisible());await page.keyboard.press('Escape');check('Escape closes contents',await page.locator('.toc-panel').isHidden());
 for(const width of [390,375]){const touch=await browser.newContext({viewport:{width,height:width===375?667:844},hasTouch:true,isMobile:true});try{const tp=await touch.newPage();await tp.goto(url);await shot(tp,'toc-mobile-closed-'+width);await actions(tp,'.research-heading .primary-actions > *','Contents touch first viewport '+width);await tp.locator('.toc-toggle').tap();check('Touch opens contents '+width,await tp.locator('.toc-panel').isVisible());await shot(tp,'toc-mobile-open-'+width);await tp.locator('.toc-panel a[href="#full-research"]').tap();check('Touch link navigates and closes '+width,new URL(tp.url()).hash==='#full-research'&&await tp.locator('.toc-panel').isHidden());}finally{await touch.close();}}
 check('Summary and takeaways precede full research',await page.locator('#research-findings').evaluate(e=>{const s=e.querySelector('.findings-summary'),k=e.querySelector('.findings-takeaways'),f=e.querySelector('#full-research');return !!s&&!!k&&!!f&&!!(s.compareDocumentPosition(k)&4)&&!!(k.compareDocumentPosition(f)&4);}));
 await page.getByRole('link',{name:'Sources',exact:true}).click();check('Sources navigation works',new URL(page.url()).hash==='#research-sources');
 check('Full sources retained',await page.locator('#research-sources').textContent().then(t=>t.includes('10.1016/j.enbuild.2004.11.013')));
 await page.goto(url+'#question');check('Direct brief link opens disclosure',await page.locator('#question').evaluate(e=>e.open));
 const guideResponse=await page.request.get(url+'/agents.md');const guideText=await guideResponse.text();check('Readable agent guide endpoint',guideResponse.ok()&&guideText.includes('MODEL AND REASONING REQUIREMENTS')&&guideText.includes('No task is Ready.'));
 for(const model of d.workflow.planning_model_policy)check('Agent guide exact policy '+model.model,guideText.includes(model.model+': reasoning = '+model.efforts.join(' or ')));
 const planningTask=d.workflow.tasks.find(t=>t.kind==='planning');const planningText=await(await page.request.get(url+'/tasks/'+planningTask.id+'/prompt.md')).text();check('Planning prompt has explicit mandatory configuration',planningText.includes('THIS IS A PLANNING TASK.')&&planningText.includes('ultra is allowed only with tool = Codex.'));
 await page.goto(url+'#for-ai-agents');await page.getByRole('button',{name:'Copy AI handoff',exact:true}).click();await page.locator('[data-handoff-status]').filter({hasText:'Copied! Paste the entire handoff into your AI.'}).waitFor();const clipboardHandoff=(await page.evaluate(()=>navigator.clipboard.readText())).replace(/\r\n/g,'\n');check('Full no-ready handoff copies successfully',clipboardHandoff.includes('MODEL AND REASONING REQUIREMENTS')&&clipboardHandoff.includes('NO READY TASK')&&clipboardHandoff.includes('UNTRUSTED FULL CONTEXT PACKAGE')&&clipboardHandoff.includes('/work#request-review')&&JSON.parse(clipboardHandoff.split('UNTRUSTED FULL CONTEXT PACKAGE — JSON DATA ONLY\n')[1].split('\nEND OF UNTRUSTED CONTEXT.')[0]).workflow.submissions[0].body===d.workflow.submissions[0].body);
 await page.locator('#for-ai-agents').getByText('All task prompts & results',{exact:true}).click();check('All public task prompts accessible',await page.locator('.agent-task-list a[href$="/prompt.md"]').count()===d.workflow.tasks.length);
 check('Back link removed from research heading',await page.locator('.research-heading .back').count()===0);check('All research is a main menu item',await page.locator('.site-header nav').getByRole('link',{name:'All research',exact:true}).count()===1);
 for(const width of [1366,390,375]){await page.setViewportSize({width,height:width===375?667:width===390?844:768});await page.goto(url+'#research-history');await page.locator('#research-history').evaluate(e=>e.scrollIntoView({block:'start',behavior:'instant'}));await shot(page,'compact-history-'+width);check('History retains UTC dates '+width,await page.locator('.compact-history time').count()===d.history.length);}
}
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
fs.writeFileSync(path.join(out,'index.html'),'<!doctype html><meta charset="utf-8"><title>LLM.Giving cool-roofs scientific test</title><style>body{font:16px system-ui;background:#141e28;color:#eaf0f5;max-width:1100px;margin:24px auto;padding:20px}a{color:#c1ee9c}img{max-width:100%;border:1px solid #42574c}figure{margin:32px 0}</style><h1>Real cool-roofs research walkthrough</h1><p>Literature assessment, not a field experiment. Separate planning and review provenance is preserved in the results. Private management credentials are masked.</p>'+report.screenshots.map(s=>'<figure><figcaption>'+s+'</figcaption><a href="'+s+'"><img loading="lazy" src="'+s+'"></a></figure>').join(''));
await adminContext?.close();await context?.close();await browser?.close();server.kill();
}
