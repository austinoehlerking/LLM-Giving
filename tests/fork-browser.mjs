import fs from 'node:fs';
import path from 'node:path';
import {root} from '../tools/runtime.mjs';
import {saveManagementLink} from './receipt-actions.mjs';
export async function forkQa({page,ctx,base,runPhp,check,visibleActions,report}){
 const output=path.join(root,'storage/qa/forks');fs.mkdirSync(output,{recursive:true});
 const parent=JSON.parse(runPhp(['tests/fork-qa-state.php','setup'])),shots=[],viewports=[],errors=[];
 page.on('pageerror',error=>errors.push(error.message));
 const source=base+'/research/'+parent.slug;
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 const shot=async(target,name)=>{const file=path.join(output,name+'.png');await target.screenshot({path:file,fullPage:false});shots.push(file);};
 try{
  for(const size of sizes){
   await page.setViewportSize(size);await page.goto(source);await shot(page,'source-'+size.width+'x'+size.height);
   await visibleActions(page,'.research-heading .primary-actions > *','Fork source '+size.width);viewports.push({size,source:true});
   await page.getByRole('link',{name:'Fork this question',exact:true}).press('Enter');
   await shot(page,'form-'+size.width+'x'+size.height);
   await visibleActions(page,'#copy-setup-prompt,.fork-form input[name=title],.fork-form textarea[name=question]','Fork creation '+size.width);viewports.push({size,creation:true});
   check('New question starts blank '+size.width,await page.locator('[name=title]').inputValue()===''&&await page.locator('[name=question]').inputValue()==='');
   check('No private source contact copied '+size.width,!await page.content().then(text=>text.includes('private-fork@example.invalid')));
  }
  await page.setViewportSize(sizes[0]);await page.goto(source+'/fork');
  const disclosure=page.locator('[data-fork-background] > details > summary');
  await disclosure.press('Enter');check('Inherited disclosure works with keyboard',await disclosure.locator('..').getAttribute('open')!==null);
  check('Inherited findings retain contributor attribution',await page.locator('[data-fork-background]').innerText().then(text=>text.includes('Synthetic research contributor')&&text.includes('Method')&&text.includes('Limitations')&&text.includes('Inherited background')));
  await page.locator('.fork-records h3').first().scrollIntoViewIfNeeded();await shot(page,'background-open');
  await page.getByRole('button',{name:'Copy AI prompt to assist setup'}).press('Enter');
  const prompt=await page.locator('#request-setup-prompt').inputValue();
  check('Setup handoff includes pinned background as read-only',prompt.includes('PINNED INHERITED BACKGROUND')&&prompt.includes(parent.id)&&prompt.includes('Do not copy inherited records'));
  await page.locator('[name=title]').fill('[QA] Independent related ocean question');
  await page.locator('[name=question]').fill('Which independent synthetic follow-up could test a different marine observation method?');
  await page.locator('[name=terms]').press('Space');
  const formBody=await page.locator('#request-form').evaluate(form=>new URLSearchParams([...new FormData(form)].filter(([,value])=>typeof value==='string')).toString());
  await page.getByRole('button',{name:'Review your request',exact:true}).press('Enter');
  check('Preview distinguishes inherited work from own question',await page.locator('#request-preview [data-fork-background]').count()===1&&await page.locator('#request-preview-content').innerText().then(text=>text.includes('Independent related ocean question')&&text.includes('Inherited background')));
  await page.locator('#request-preview').getByRole('button',{name:'Submit for approval',exact:true}).press('Enter');
  await page.locator('#management-link').waitFor();
  const management=await page.locator('#management-link').inputValue(),child=new URL(management).pathname.split('/').pop();
  await saveManagementLink(page);
  check('Fork receipt has own management identity',child!==parent.id);
  const repeat=await page.request.post(source+'/fork',{data:formBody,headers:{'Content-Type':'application/x-www-form-urlencoded'}});
  check('Actual HTTP retry returns same management receipt',repeat.status()===200&&(await repeat.text()).includes(management.replaceAll('&','&amp;')));
  const changed=new URLSearchParams(formBody);changed.set('title','[QA] Changed duplicate fork question');
  check('Changed HTTP retry rejected',(await page.request.post(source+'/fork',{data:changed.toString(),headers:{'Content-Type':'application/x-www-form-urlencoded'}})).status()===409);
  check('Child hidden before independent approval',(await page.request.get(base+'/research/'+child)).status()===404);
  runPhp(['tests/fork-qa-state.php','approve',child]);
  await page.goto(management);await page.locator('h2').filter({hasText:'Independent related ocean question'}).waitFor();
  check('Own management view discloses inherited background',await page.locator('[data-fork-background]').count()===1);
  await page.goto(source);
  check('Public parent lists related fork',await page.locator('.fork-related').getByRole('link',{name:'[QA] Independent related ocean question'}).count()===1);
  await page.locator('.fork-related a').press('Enter');
  const childUrl=page.url();
  check('Child shows attributed Forked from',await page.locator('.fork-origin a').count()===1);
  const childContext=await (await page.request.get(childUrl+'/context.json')).json();
  check('Context records exact source version',childContext.fork.parent.id===parent.id&&childContext.fork.snapshot_id.length===32&&!childContext.workflow.submissions.length);
  const bg=await page.request.get(childUrl+'/fork-background.json');
  check('Background download includes provenance',bg.status()===200&&(await bg.json()).background_sha256===childContext.fork.background_sha256);
  const packet=await page.request.get(childUrl+'/package.json'),packageId=packet.headers()['x-context-id'];
  const issued=await page.request.get(childUrl+'/handoff.md'),archiveId=issued.headers()['x-handoff-id'];
  check('Handoff carries inherited background',issued.status()===200&&(await issued.text()).includes('llm-giving-fork-background-v1'));
  const resultId=runPhp(['tests/fork-qa-state.php','plan',child]).trim();
  check('Child has independently submitted own result',(await page.request.get(childUrl+'/results/'+resultId)).status()===200);
  await page.setViewportSize({width:683,height:384});await page.goto(source+'/fork');await page.evaluate(()=>{document.body.style.fontSize='200%';});
  await shot(page,'enlarged-text');check('Enlarged fork form reflows without clipping',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  await page.locator('[data-fork-background] > details > summary').press('Enter');await page.locator('.fork-records h3').first().scrollIntoViewIfNeeded();await shot(page,'enlarged-background');
  check('Enlarged inherited text reflows',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  const native=await ctx.browser().newContext({javaScriptEnabled:false,viewport:sizes[2]});const nojs=await native.newPage();
  try{
   await nojs.goto(source);await nojs.getByRole('link',{name:'Fork this question',exact:true}).press('Enter');await shot(nojs,'no-js-initial');
   await visibleActions(nojs,'.fork-form input[name=title],.fork-form textarea[name=question]','No-JS fork creation');
   await nojs.locator('[data-fork-background] > details > summary').press('Enter');check('No-JS inherited disclosure works',await nojs.locator('[data-fork-background]').innerText().then(text=>text.includes('Synthetic research contributor')));
   await nojs.locator('[name=title]').fill('[QA] Native HTML related question');await nojs.locator('[name=question]').fill('Can an independent related request be created with native HTML forms and no JavaScript?');
   await nojs.locator('[name=terms]').press('Space');await nojs.locator('#request-form > .actions').getByRole('button',{name:'Submit for approval'}).press('Enter');
   check('No-JS fork receives independent receipt',await nojs.locator('#management-link').count()===1);
  }finally{await native.close();}
  const resources=JSON.parse(runPhp(['tests/fork-qa-state.php','resources']));
  try{
   const resourceSource=base+'/research/'+resources.slug;await page.goto(resourceSource+'/fork');
   await page.locator('[name=title]').fill('[QA] Fork with attributed retained materials');
   await page.locator('[name=question]').fill('Which independent follow-up can reuse the original attributed mass conversion as background?');
   await page.locator('[name=terms]').press('Space');await page.locator('#request-form > .actions').getByRole('button',{name:'Submit for approval'}).press('Enter');
   const materialLink=await page.locator('#management-link').inputValue(),materialChild=new URL(materialLink).pathname.split('/').pop();await saveManagementLink(page);
   runPhp(['tests/fork-qa-state.php','approve',materialChild]);await page.goto(base+'/research/'+materialChild);
   await page.locator('[data-fork-background] > details > summary').press('Enter');
   await page.locator('.fork-resources > summary').press('Enter');
   const tableLink=page.locator('.fork-resources a').filter({hasText:'Original complete table (CSV)'}).first(),notes=page.locator('.fork-resources a').filter({hasText:'continuation.txt'}).first();
   const tableUrl=await tableLink.getAttribute('href'),notesUrl=await notes.getAttribute('href');
   await tableLink.scrollIntoViewIfNeeded();await shot(page,'inherited-resources');
   check('Inherited resource attribution visible in real UI',await page.locator('.fork-resources').innerText().then(text=>text.includes('LLM.Giving synthetic QA fixture')&&text.includes('synthetic-v1')&&text.includes('mass = 2 kg')));
   const csv=await page.request.get(base+tableUrl),note=await page.request.get(base+notesUrl);
   check('Inherited links retain original complete table and material',csv.status()===200&&(await csv.text()).includes('mass,2')&&note.status()===200&&(await note.text()).includes('mass=2 kg'));
   const downloadEvent=page.waitForEvent('download');await tableLink.click();const download=await downloadEvent;check('Actual inherited table action downloads original file',download.suggestedFilename()==='original-mass.csv');
   await download.saveAs(path.join(output,'original-mass.csv'));
   runPhp(['tests/fork-qa-state.php','withdraw',resources.id]);
   check('Source withdrawal blocks inherited material downloads',(await page.request.get(base+tableUrl)).status()===404&&(await page.request.get(base+notesUrl)).status()===404);
   check('Source withdrawal blocks forked resource catalogue',(await page.request.get(base+'/research/'+materialChild+'/resources')).status()===404);
  }finally{runPhp(['tests/fork-qa-state.php','cleanup',resources.id]);}
  runPhp(['tests/fork-qa-state.php','withdraw',parent.id]);
  for(const suffix of ['', '/context.json','/context.md','/prompt.md','/fork-background.json','/packages/'+packageId,'/results/'+resultId,...(archiveId?['/issued/'+archiveId]:[])]){
   const response=await page.request.get(childUrl+suffix);check('Source withdrawal blocks child HTTP '+suffix,response.status()===404&&!(await response.text()).includes('Synthetic inherited finding'));
  }
  runPhp(['tests/fork-qa-state.php','restore',parent.id]);runPhp(['tests/fork-qa-state.php','revoke',parent.id]);
  check('Source snapshot revocation blocks old child package',(await page.request.get(childUrl+'/packages/'+packageId)).status()===404);
  check('No browser script exceptions',errors.length===0);
  report.screenshots.push(...shots);
 }finally{runPhp(['tests/fork-qa-state.php','cleanup',parent.id]);}
}
