import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {root} from '../tools/runtime.mjs';
import {saveManagementLink} from './receipt-actions.mjs';

export async function requirementsQa({page,ctx,base,runPhp,check,visibleActions,password,report}){
 const out=path.join(root,'storage/qa/ai-requirements');
 fs.mkdirSync(out,{recursive:true});
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 const shots=[],errors=[];
 page.on('pageerror',error=>errors.push(error.message));
 const shot=async(name,current=page)=>{
  const file=path.join(out,name+'.png');
  await current.screenshot({path:file,fullPage:false});
  shots.push(file);
  report?.screenshots.push(file);
 };
 const review=async name=>{
  if(process.env.QA_INSPECT_FIRST!=='requirements')return;
  console.log('Inspect initial '+name+': '+shots.slice(-sizes.length).join(' | '));
  await new Promise(resolve=>{process.stdin.resume();process.stdin.once('data',()=>{process.stdin.pause();resolve();});});
 };
 const retired='[data-ai-selection],[name="required_models[]"],[name="reasoning_levels[]"],[name=required_model],[name=required_reasoning]';
 let rid,admin,anon;
 const state=command=>JSON.parse(runPhp(['tests/requirements-state.php',command,rid]));
 try{
  for(const size of sizes){
   await page.setViewportSize(size);
   await page.goto(base+'/submit');
   await shot('start-'+size.width);
   await visibleActions(page,'.form-actions button,#request-next-step > summary','Request start '+size.width);
   check('New request needs no model or effort selection '+size.width,await page.locator(retired).count()===0);
  }
  await review('request start');
  const title='[QA] Requester capability and historical settings';
  await page.setViewportSize(sizes[0]);
  await page.locator('[name=title]').fill(title);
  await page.locator('[name=question]').fill('How well do two documented approaches compare for reproducible synthetic research conversions?');
  await page.locator('[name=category_id]').selectOption({label:'Environment & Climate'});
  await page.locator('#request-next-step > summary').click();
  await page.locator('[name=external_tools]').locator('xpath=ancestor::details[1]').locator(':scope > summary').click();
  await page.locator('[name=external_tools]').selectOption('free');
  await page.locator('[name=required_software]').fill('Fixture conversion tool');
  await page.locator('[name=calculation_help]').selectOption('run');
  await page.getByRole('button',{name:'Review your request',exact:true}).click();
  const preview=await page.locator('#request-preview-content').innerText();
  check('Review retains capabilities and concrete dependencies',preview.includes('Fixture conversion tool')&&preview.includes('Run calculations')&&!preview.includes('AI requirements'));
  await page.getByRole('button',{name:'Back to editing',exact:true}).click();
  await page.locator('[name=terms]').check();
  await page.locator('.request-submit-actions').getByRole('button',{name:'Submit for approval',exact:true}).click();
  await page.getByRole('heading',{name:'Research request submitted',exact:true}).waitFor();
  const manage=await saveManagementLink(page),managePath=new URL(manage).pathname;
  rid=managePath.split('/').pop();
  const fresh=state('inspect');
  check('Actual request has no model constraints and retains calculation capability',fresh.requirements==='{}'&&fresh.external.required_software==='Fixture conversion tool'&&fresh.external.calculation_help==='run');
  state('history');

  runPhp(['bin/console.php','admin','requester-capability-qa@example.invalid'],password);
  admin=await ctx.browser().newContext();
  const ap=await admin.newPage();
  await ap.goto(base+'/admin/login');
  await ap.getByLabel('Email').fill('requester-capability-qa@example.invalid');
  await ap.getByLabel('Password').fill(password);
  await ap.getByRole('button',{name:'Sign in',exact:true}).click();
  for(const size of sizes){
   await ap.setViewportSize(size);
   await ap.goto(base+'/admin/research/'+rid);
   await shot('approval-'+size.width,ap);
   await visibleActions(ap,'[data-quick-approve] > button,#request-changes > summary','Admin review '+size.width);
  }
  await review('admin review');
  await ap.locator('#full-request-brief > summary').click();
  await ap.locator('#full-request-brief').getByText('External tools & calculation help',{exact:true}).click();
  const brief=await ap.locator('#full-request-brief').innerText();
  check('Admin brief displays software capabilities without old model gate',brief.includes('Fixture conversion tool')&&!brief.includes('legacy-model-identifier')&&!brief.includes('AI requirements'));
  await ap.getByRole('button',{name:'Approve',exact:true}).click();
  check('Real approval activates the existing workflow',state('inspect').moderation==='Approved');

  await page.goto(manage);
  await page.getByRole('heading',{name:'Change status',exact:true}).waitFor();
  for(const size of sizes){
   await page.setViewportSize(size);
   await page.goto(base+managePath);
   await shot('manage-'+size.width);
   await visibleActions(page,'#request-new-status,.management-status button','Manage request '+size.width);
   check('Manage view keeps controls without a model restriction editor '+size.width,await page.locator(retired).count()===0&&await page.getByRole('heading',{name:'Change status',exact:true}).isVisible());
  }
  await review('request management');
  const before=state('capture');
  check('Historical setting remains in the issued snapshot',before.snapshots.some(snapshot=>snapshot.legacy)&&before.snapshots.some(snapshot=>!snapshot.legacy));
  const csrf=await page.locator('.management-status [name=csrf]').inputValue();
  const action={action:'ai-requirements','required_models[]':'expired-model','reasoning_levels[]':'not-an-effort'};
  const redirected=await page.request.post(base+managePath,{form:{...action,csrf},maxRedirects:0});
  check('Authorized legacy action redirects to management',redirected.status()===303&&redirected.headers().location===managePath);
  assert.deepEqual(state('inspect'),before);
  check('Authorized no-op preserves historical snapshots and audit count',true);
  const denied=await page.request.post(base+managePath,{form:{...action,csrf:'wrong-token'},maxRedirects:0});
  check('Legacy action still rejects invalid CSRF',denied.status()===403);
  anon=await ctx.browser().newContext();
  const visitor=await anon.newPage();
  await visitor.goto(base+managePath);
  const anonToken=await visitor.locator('#capability-exchange [name=csrf]').inputValue();
  const unauthorized=await anon.request.post(base+managePath,{form:{...action,csrf:anonToken},maxRedirects:0});
  check('Correct CSRF alone cannot authorize the legacy action',unauthorized.status()===403);
  assert.deepEqual(state('inspect'),before);
  check('Rejected attempts cannot mutate the request, snapshots or domain audit',true);

  await page.locator('#request-new-status').selectOption('Paused');
  await page.locator('.management-status [name=reason]').fill('Pause the isolated QA fixture after confirming management access.');
  await page.getByRole('button',{name:'Save request status',exact:true}).click();
  check('Visible management action saves a real request status',state('inspect').status==='Paused');
  await page.setViewportSize(sizes[2]);
  await page.evaluate(()=>{
   const sizes=[...document.querySelectorAll('body,body *')].filter(el=>el instanceof HTMLElement).map(el=>[el,parseFloat(getComputedStyle(el).fontSize)]);
   for(const [el,size] of sizes)if(Number.isFinite(size))el.style.fontSize=size*2+'px';
  });
  await shot('manage-reflow-200');
  check('Manage request reflows at 200% text',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  await page.getByText('Read request details',{exact:true}).click();
  check('Enlarged management controls remain usable',await page.getByRole('heading',{name:title,exact:true}).isVisible());
  check('No requester page JavaScript errors',errors.length===0);
 }finally{
  await anon?.close();
  await admin?.close();
  if(rid)state('cleanup');
  fs.writeFileSync(path.join(out,'evidence.json'),JSON.stringify({shots,errors},null,2));
 }
}
