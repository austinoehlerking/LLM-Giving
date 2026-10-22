import fs from 'node:fs';
import path from 'node:path';
import {createHash} from 'node:crypto';
import {root} from '../tools/runtime.mjs';
export async function resourcesQa({page,ctx,base,runPhp,check,visibleActions,shot}){
 const output=path.join(root,'storage/qa/resources');fs.mkdirSync(output,{recursive:true});
 const f=JSON.parse(runPhp(['tests/resources-state.php','create']));
 const url=base+'/research/'+f.slug;const resources=url+'/resources';const result=url+'/results/'+f.result_id;const progress=url+'/results/'+f.progress_id;
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 const mutate=(kind,id,value)=>runPhp(['tests/resources-state.php','mutate',f.id,kind,id,value]);
 const catalog=async()=>JSON.parse(runPhp(['tests/resources-state.php','catalogue',f.id]));
 const has=(data,id)=>data.sources.some(group=>group.contributions.some(entry=>entry.result_id===id));
 try{
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){
   await page.setViewportSize(size);await page.goto(url);await shot(page,'resources/study-initial-'+size.width+'x'+size.height);await visibleActions(page,'.research-heading .primary-actions > *','Study resources '+size.width);
   check('Resources section discoverable from study '+size.width,await page.locator('.research-heading .primary-actions a[href$="/resources"]').isVisible());
   await page.locator('.research-heading .primary-actions a[href$="/resources"]').click();
   await shot(page,'resources/initial-'+size.width+'x'+size.height);await visibleActions(page,'.page-heading .primary-actions > *','Resources '+size.width);
   check('Resources page identity '+size.width,(await page.title()).startsWith('Resources & sources')&&await page.getByRole('heading',{name:'Resources & sources',exact:true}).isVisible());
   check('Resource page has no horizontal clipping '+size.width,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   await page.getByRole('link',{name:'Inspect sources',exact:true}).click();check('Inspect sources opens source list '+size.width,new URL(page.url()).hash==='#resource-list'&&await page.locator('[data-resource]').first().isVisible());
  }
  await page.setViewportSize({width:1366,height:768});await page.goto(resources);
  const original=page.locator('[data-resource]').filter({has:page.getByRole('heading',{name:'Synthetic original mass table — local QA only',exact:true})});
  check('Duplicate identities retain each source contribution',await original.count()===1&&await original.locator('[data-source-record]').count()===3);
  const text=await original.innerText();
  check('Scholarly attribution, original source location and numerical input are visible',['LLM.Giving synthetic QA fixture','2026-10-21','Local regression fixtures','Original synthetic mass table, row mass','mass = 2 kg','Derived calculation'].every(value=>text.includes(value)));
  for(const label of ['Background reference','Abstract-only access','Unavailable source','Rejected evidence'])check('Resource access/use classification: '+label,await page.getByText(label,{exact:true}).count()>0);
  await original.scrollIntoViewIfNeeded();await shot(page,'resources/source-to-result-trace');
  const csvResponse=await page.request.get(result+'/resources/datasets/original-mass.csv');const csv=await csvResponse.text();check('Original table download retains data and units',csvResponse.status()===200&&csv.includes('Original mass (kg)')&&csv.includes('mass,2'));
  const bundleResponse=await page.request.get(result+'/calculations/mass-conversion/bundle.json');const bundle=await bundleResponse.json();
  check('Calculation output traces to original 2 kg and yields 2000 g',bundleResponse.status()===200&&bundle.calculation.inputs[0].value===2&&bundle.calculation.inputs[0].source_locator==='Original synthetic mass table, row mass'&&bundle.calculation.outputs[0].value===2000&&bundle.calculation.outputs[0].unit==='g');
  check('Retained exact calculation bytes match SHA-256',bundle.calculation.files.every(file=>createHash('sha256').update(file.content).digest('hex')===file.sha256));
  const notes=await page.request.get(progress+'/resources/files/notes');check('Useful partial work downloads for continuation',notes.status()===200&&(await notes.text()).includes('Useful synthetic intermediate notes'));
  const sourceRecord=original.locator('[data-source-record]').filter({has:page.locator('a[href$="/results/'+f.result_id+'#source-s1"]')});const sourceLink=sourceRecord.getByRole('link',{name:'Read claim c1',exact:true});await sourceLink.click();await page.locator('[data-evidence-panel]').evaluate(el=>el.open=true);await page.locator('#claim-c1').scrollIntoViewIfNeeded();
  check('Source-to-claim link reaches exact contributing result',new URL(page.url()).pathname.endsWith('/results/'+f.result_id)&&await page.locator('#claim-c1').innerText().then(text=>text.includes('2 kg is converted to 2000 g')));await shot(page,'resources/linked-claim');
  await page.locator('#calculations').scrollIntoViewIfNeeded();await page.getByText('Inputs, outputs and unit checks',{exact:true}).click();await shot(page,'resources/derived-calculation');
  await page.goto(url+'/tasks/'+f.ready_task_id);await shot(page,'resources/task-validation-initial');await visibleActions(page,'.workflow-heading .primary-actions > *','Material validation task');const packet=JSON.parse(runPhp(['tests/resources-state.php','packet',f.id,f.ready_task_id]));const bad=structuredClone(packet);bad.evidence.supporting_materials.items[0].status='missing';
  await page.locator('[name=payload]').fill(JSON.stringify(bad));await page.locator('[name=human_level]').selectOption('none');await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.locator('[data-workflow-error]').filter({hasText:'Required supporting material is missing'}).waitFor();
  check('Browser rejects unsupported completion with missing material',await page.locator('[data-workflow-error]').innerText().then(text=>text.includes('Required supporting material is missing')));await page.locator('[data-workflow-error]').scrollIntoViewIfNeeded();await shot(page,'resources/missing-material-blocked');
  await page.locator('[name=payload]').fill(JSON.stringify(packet));await page.getByRole('button',{name:'Check packet',exact:true}).click();await page.getByText(/Packet checks passed/).waitFor();check('Complete narrative packet passes without invented code',await page.getByText(/Packet checks passed/).isVisible());
  for(const [kind,id,badValue,restore] of [['visibility',f.result_id,'removed','public'],['admission',f.result_id,'quarantined','accepted'],['admission',f.result_id,'rejected','accepted'],['task',f.task_id,'removed','public'],['snapshot',f.snapshot_id,'0','1']]){
   mutate(kind,id,badValue);const data=await catalog();check(kind+' '+badValue+' excluded from resource aggregation',!has(data,f.result_id));
   const resourceHtml=await(await page.request.get(resources)).text();check(kind+' '+badValue+' is absent from rendered links',!resourceHtml.includes(f.result_id));
   for(const suffix of ['/resources/datasets/original-mass.csv','/calculations/mass-conversion/bundle.json'])check(kind+' '+badValue+' denies download '+suffix,(await page.request.get(result+suffix)).status()===404);
   mutate(kind,id,restore);
  }
  await page.setViewportSize({width:683,height:384});await page.goto(resources);await page.evaluate(()=>{for(const el of document.querySelectorAll('body,body *')){const size=parseFloat(getComputedStyle(el).fontSize);el.dataset.qaFont=String(size);}for(const el of document.querySelectorAll('[data-qa-font]'))el.style.fontSize=Number(el.dataset.qaFont)*2+'px';});await shot(page,'resources/enlarged-text');
  check('200% enlarged text reflows without clipping',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.getByRole('link',{name:'Inspect sources',exact:true}).focus();await page.keyboard.press('Enter');check('Resource navigation works with enlarged text and keyboard',new URL(page.url()).hash==='#resource-list');
  const nojs=await ctx.browser().newContext({javaScriptEnabled:false,viewport:{width:375,height:667}});const p=await nojs.newPage();await p.goto(resources);await shot(p,'resources/no-js-initial');await visibleActions(p,'.page-heading .primary-actions > *','Resources without JavaScript');await p.getByRole('link',{name:'Inspect sources',exact:true}).click();check('Resources and trace work without JavaScript',await p.locator('[data-source-record]').count()>0);await nojs.close();
  mutate('parent',f.id,'Withdrawn');for(const route of [resources,result+'/resources/datasets/original-mass.csv',progress+'/resources/files/notes',result+'/calculations/mass-conversion/bundle.json'])check('Withdrawal denies '+new URL(route).pathname,(await page.request.get(route)).status()===404);
  check('Resources flow has no browser script exceptions',errors.length===0);
  fs.writeFileSync(path.join(output,'trace-example.json'),JSON.stringify({synthetic:true,source:'tests/resources-fixture.php: original mass table, row mass',input:{value:2,unit:'kg',file:'inputs.json',locator:'mass_kg'},formula:'2 * 1000',output:{value:2000,unit:'g',file:'outputs.json',locator:'mass_g'},claim:'c1',fixture:f,checksums:bundle.calculation.files.map(({name,sha256})=>({name,sha256}))},null,2));
 }finally{runPhp(['tests/resources-state.php','cleanup',f.id]);}
}
