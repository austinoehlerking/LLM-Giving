import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {randomBytes} from 'node:crypto';
import {root,env,playwright,runPhp,browserExecutable} from './runtime.mjs';
const base='http://127.0.0.1:8086', out=path.join(root,'storage/qa/real-submission'), priv=path.join(root,'storage/private');
fs.mkdirSync(out,{recursive:true});fs.mkdirSync(priv,{recursive:true});
const brief=JSON.parse(fs.readFileSync(path.join(root,'storage/research/plastic-carbon-pump.json'),'utf8'));
const state=()=>JSON.parse(runPhp(['tools/local-research-state.php','inspect']));
const mode=process.argv[2]||'capture';
const report={created:new Date().toISOString(),screenshots:[],checks:[],viewports:[]};
const pw=await playwright();
const browser=await pw.chromium.launch({headless:true,executablePath:browserExecutable(),env,args:['--no-sandbox'],downloadsPath:path.join(out,'downloads')});
const context=await browser.newContext({viewport:{width:1366,height:768}});
await context.route('**/*',route=>new URL(route.request().url()).hostname==='127.0.0.1'?route.continue():route.abort());
const page=await context.newPage();
async function shot(name,fullPage=false,mask=[]){await page.screenshot({path:path.join(out,name+'.png'),fullPage,mask});report.screenshots.push(name+'.png');}
async function actions(selector,label){
 const boxes=await page.locator(selector).evaluateAll(els=>els.map(el=>{const r=el.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return {text:el.textContent.trim(),inside:r.x>=0&&r.y>=0&&r.right<=innerWidth&&r.bottom<=innerHeight,uncovered:!!hit&&(el===hit||el.contains(hit)),height:r.height};}));
 report.viewports.push({label,size:page.viewportSize(),boxes});assert(boxes.length&&boxes.every(b=>b.inside&&b.uncovered&&b.height>=44),label);
 assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No horizontal overflow');
}
try {
 if(mode==='capture'){
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){
   await page.setViewportSize(size);await page.goto(base+'/submit');await shot('01-start-'+size.width+'x'+size.height);await actions('.form-actions button','Submission '+size.width);
  }
 }else if(mode==='submit'){
  assert(!state().research.some(r=>r.title===brief.title),'This research request already exists; refusing duplicate submission.');
  let credential;
  const credentialPath=path.join(priv,'admin-access.json');
  if(fs.existsSync(credentialPath))credential=JSON.parse(fs.readFileSync(credentialPath,'utf8'));
  else{
   credential={email:'admin@llm.giving.test',password:randomBytes(24).toString('base64url')};
   assert(!state().admins.some(a=>a.email===credential.email),'Existing admin account requires existing credentials.');
   runPhp(['bin/console.php','admin',credential.email],credential.password);
   fs.writeFileSync(credentialPath,JSON.stringify(credential,null,2));
  }
  // The user explicitly requested removal of demo requests. Preserve a private DB backup first.
  const removed=JSON.parse(runPhp(['tools/local-research-state.php','remove-demos']));
  report.removed=removed.removed;
  await page.goto(base+'/');await shot('02-home-demos-removed');
  await page.getByRole('link',{name:/Submit a research request/}).first().click();
  await page.locator('[name=title]').fill(brief.title);await page.locator('[name=question]').fill(brief.question);
  await page.locator('[name=category_id]').selectOption({label:'Environment & Climate'});
  await shot('03-essentials',true);
  await page.getByText('Background, objectives & references',{exact:true}).click();
  for(const key of ['context','success','previous','constraints_text','questions','references_text','tags'])await page.locator('[name='+key+']').fill(brief[key]);
  await shot('04-complete-research-brief',true);
  for(const key of ['context','success','previous','constraints_text','questions','references_text','tags']){
   await page.locator('[name='+key+']').scrollIntoViewIfNeeded();await shot('05-field-'+key);
  }
  await page.getByText('Supporting files',{exact:true}).click();
  await page.getByText('Optional attribution & private email',{exact:true}).click();
  await page.locator('details').nth(1).scrollIntoViewIfNeeded();await shot('06-optional-files-and-attribution');
  await page.locator('[name=terms]').check();
  await page.getByRole('button',{name:'Submit for approval',exact:true}).last().scrollIntoViewIfNeeded();await shot('07-ready-to-submit');
  await page.getByRole('button',{name:'Submit for approval',exact:true}).last().click();
  await page.locator('#management-link').waitFor();
  const link=await page.locator('#management-link').inputValue();
  const record=state().research.find(r=>r.title===brief.title);assert(record);
  fs.writeFileSync(path.join(priv,'request-management.txt'),link+'\n');
  fs.writeFileSync(path.join(priv,'admin-access.txt'),'LOCAL LLM.Giving administrator\nLogin: '+base+'/admin/login\nEmail: '+credential.email+'\nPassword: '+credential.password+'\nReview: '+base+'/admin/research/'+record.id+'\n\nKeep this file private. Local development only.\n');
  report.record=record;
  await shot('08-submission-receipt',true,[page.locator('#management-link')]);
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){
   await page.setViewportSize(size);await page.evaluate(()=>scrollTo(0,0));await shot('09-receipt-'+size.width+'x'+size.height,false,[page.locator('#management-link')]);await actions('.receipt .primary-actions > *','Receipt '+size.width);
  }
  await page.setViewportSize({width:1366,height:768});
  await page.goto(base+'/admin/login');await shot('10-admin-login');
  await page.locator('[name=email]').fill(credential.email);await page.locator('[name=password]').fill(credential.password);
  await page.getByRole('button',{name:'Sign in',exact:true}).click();await shot('11-admin-pending-queue',true);
  await page.goto(base+'/admin/research/'+record.id);await shot('12-admin-review-full',true);
  const moderation=page.locator('form.moderate').filter({has:page.locator('input[name="type"][value="request"]')});
  await moderation.scrollIntoViewIfNeeded();await shot('13-pending-approval-control');
  assert.equal(await moderation.locator('[name=state]').inputValue(),'Pending Approval');
  assert.equal(state().research.length,1);assert.equal(state().research[0].moderation,'Pending Approval');
  const anon=await browser.newContext();assert.equal((await anon.request.get(base+'/research/'+record.slug)).status(),404);await anon.close();
  report.checks.push('Exactly one real request remains','Request is Pending Approval','Anonymous public request remains inaccessible','No approval submitted');
  fs.writeFileSync(path.join(out,'request.json'),JSON.stringify(record,null,2));
 }else if(mode==='verify'){
  const credential=JSON.parse(fs.readFileSync(path.join(priv,'admin-access.json'),'utf8'));
  const record=state().research.find(r=>r.title===brief.title);assert(record);
  report.record=record;
  await page.goto(base+'/admin/login');await shot('10-admin-login');
  await page.locator('[name=email]').fill(credential.email);await page.locator('[name=password]').fill(credential.password);
  await page.getByRole('button',{name:'Sign in',exact:true}).click();await shot('11-admin-pending-queue',true);
  await page.goto(base+'/admin/research/'+record.id);await shot('12-admin-review-full',true);
  const moderation=page.locator('form.moderate').filter({has:page.locator('input[name="type"][value="request"]')});
  await moderation.scrollIntoViewIfNeeded();await shot('13-pending-approval-control');
  assert.equal(await moderation.locator('[name=state]').inputValue(),'Pending Approval');
  assert.equal(state().research.length,1);assert.equal(state().research[0].moderation,'Pending Approval');
  const anon=await browser.newContext();assert.equal((await anon.request.get(base+'/research/'+record.slug)).status(),404);await anon.close();
  report.checks.push('Exactly one real request remains','Request is Pending Approval','Anonymous public request remains inaccessible','No approval submitted');
  fs.writeFileSync(path.join(out,'request.json'),JSON.stringify(record,null,2));
 }else throw new Error('Use capture, submit or verify.');
}finally{
 fs.writeFileSync(path.join(out,mode+'-report.json'),JSON.stringify(report,null,2));
 const shots=fs.readdirSync(out).filter(f=>f.endsWith('.png')).sort();
 fs.writeFileSync(path.join(out,'index.html'),'<!doctype html><meta charset="utf-8"><title>LLM.Giving submission walkthrough</title><style>body{font:16px system-ui;background:#14202a;color:#eef5f7;max-width:1100px;margin:32px auto;padding:20px}img{max-width:100%;border:1px solid #52606a}figure{margin:40px 0}a{color:#c4ef9a}</style><h1>Real research submission walkthrough</h1><p>Local submission. Management secret masked. Optional files and attribution left empty. No administrator approval was submitted.</p>'+shots.map(f=>'<figure><figcaption>'+f+'</figcaption><a href="'+f+'"><img loading="lazy" src="'+f+'"></a></figure>').join(''));
 await browser.close();
}
console.log(JSON.stringify({mode,screenshots:report.screenshots.length,checks:report.checks,record:report.record}));
