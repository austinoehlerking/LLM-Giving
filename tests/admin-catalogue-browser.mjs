import fs from 'node:fs';
import path from 'node:path';
import {root,env} from '../tools/runtime.mjs';
/** Local fixture controls only; no production data or mutations. */
export async function adminCatalogueQa({page,ctx,base,runPhp,check,visibleActions,report,password,browser}) {
 const out=path.join(root,'storage/qa/admin-catalogue');fs.mkdirSync(out,{recursive:true});
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];let fixture;const errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 const capture=async label=>{const file=path.join(out,label+'.png');await page.screenshot({path:file,fullPage:false});report.screenshots.push(file);return file;};
 const inspect=()=>JSON.parse(runPhp(['tests/admin-catalogue-state.php','inspect']));
 try {
  fixture=JSON.parse(runPhp(['tests/admin-catalogue-state.php','seed']));runPhp(['bin/console.php','admin','catalogue-qa@example.invalid'],password);
  await page.goto(base+'/admin/login');await page.getByLabel('Email',{exact:true}).fill('catalogue-qa@example.invalid');await page.getByLabel('Password',{exact:true}).fill(password);await page.getByRole('button',{name:'Sign in',exact:true}).click();
  const record='/admin/research/'+fixture.ids.usage,initial=inspect(),shots=[];
  for(const size of sizes){await page.setViewportSize(size);const response=await page.goto(base+record);check('Manage record renders '+size.width,response.status()===200);check('Initial manage viewport starts at zero '+size.width,await page.evaluate(()=>scrollY===0));shots.push(await capture('initial-'+size.width+'x'+size.height));await visibleActions(page,'.admin-review-header > a,.admin-decision-actions > .button,#discovery-listing > summary,#request-changes > summary,.admin-review-links > a','Admin listing '+size.width);}
  console.log('INSPECT ADMIN CATALOGUE INITIAL: '+shots.join(' | '));
  if(env.QA_INSPECT_FIRST==='admin-catalogue')await new Promise(resolve=>{process.stdin.resume();process.stdin.once('data',()=>{process.stdin.pause();resolve();});});
  await page.setViewportSize(sizes[0]);await page.goto(base+record);
  const toggle=page.locator('#discovery-listing');await toggle.locator(':scope > summary').click();
  const token=await toggle.locator('[name=csrf]').inputValue();
  const anon=await browser.newContext();try{const p=await anon.newPage();await p.goto(base+'/submit');const csrf=await p.locator('[name=csrf]').first().inputValue();const denied=await anon.request.post(base+record,{form:{csrf,action:'discovery-listing',discovery_listed:'0',reason:'Unauthorized fixture listing change.'},maxRedirects:0});check('Anonymous listing action redirects to admin login',denied.status()===302&&denied.headers().location==='/admin/login');}finally{await anon.close();}
  check('Anonymous request did not change listing',inspect().rows.usage.listed===1);
  check('Missing CSRF rejects listing action',(await ctx.request.post(base+record,{form:{action:'discovery-listing',discovery_listed:'0',reason:'Missing CSRF fixture attempt.'}})).status()===403);
  check('Short reason rejected server-side',(await ctx.request.post(base+record,{form:{csrf:token,action:'discovery-listing',discovery_listed:'0',reason:'short'}})).status()===422);
  check('Invalid listing value rejected server-side',(await ctx.request.post(base+record,{form:{csrf:token,action:'discovery-listing',discovery_listed:'2',reason:'Invalid listing fixture attempt.'}})).status()===422);
  check('Rejected attempts preserved listing',inspect().rows.usage.listed===1);
  await toggle.getByLabel('Reason for the listing change',{exact:false}).fill('Unlist this explicitly synthetic local fixture from normal discovery.');await toggle.getByRole('button',{name:'Unlist from discovery',exact:true}).click();
  let state=inspect();check('Real unlist control updates same record',state.rows.usage.listed===0&&new URL(page.url()).pathname===record);check('Unlist confirmation describes direct access',(await page.locator('body').innerText()).includes('Direct links retain their normal access checks.'));
  check('Unlisting preserves moderation status and context',state.rows.usage.moderation===initial.rows.usage.moderation&&state.rows.usage.status===initial.rows.usage.status&&state.rows.usage.context_version===initial.rows.usage.context_version);
  check('Audited private unlist transition',state.audit.length===1&&state.audit[0].old_state==='Listed'&&state.audit[0].new_state==='Unlisted'&&state.audit[0].visibility==='private');
  await page.goto(base+'/research?q='+encodeURIComponent(fixture.slug.slice(-10)));check('Keyword discovery omits unlisted record',await page.locator('a[href="/research/'+fixture.slug+'"]').count()===0);
  for(const suffix of ['', '/resources','/context.json','/results/'+fixture.result])check('Direct approved proof remains readable '+suffix,(await ctx.request.get(base+'/research/'+fixture.slug+suffix)).status()===200);
  check('Private parent remains hidden',(await ctx.request.get(base+'/research/'+fixture.ids.private)).status()===404);
  await page.goto(base+'/admin?q='+fixture.ids.usage);await capture('admin-overview-initial');await visibleActions(page,'.page-heading .primary-actions > *','Admin overview desktop');
  check('Pending summary counters say pending',await page.getByText('Pending requests',{exact:true}).count()===1&&await page.getByText('Pending contributions',{exact:true}).count()===1&&await page.getByText('Pending reviews',{exact:true}).count()===1&&await page.getByText('Quarantined workflow packets',{exact:true}).count()===1);
  const usage=page.locator('[data-research-usage="'+fixture.ids.usage+'"]');await usage.getByText('Workflow: 4 packets',{exact:true}).click();await usage.getByText('Supplementary: 4 contributions',{exact:true}).click();const text=await usage.innerText();check('Admin usage includes workflow and supplementary totals separately',text.includes('2 accepted')&&text.includes('1 quarantined (awaiting admin)')&&text.includes('1 rejected')&&text.includes('1 removed')&&text.includes('1 Pending Approval')&&text.includes('1 Approved'));
  check('Admission is not described as scientific verification',(await page.locator('body').innerText()).includes('it does not establish scientific correctness'));await usage.scrollIntoViewIfNeeded();await capture('usage-breakdowns');
  for(const size of [sizes[0],sizes[2]]){await page.setViewportSize(size);await page.goto(base+record);await page.evaluate(()=>{const nodes=[...document.querySelectorAll('body,body *')].filter(el=>el instanceof HTMLElement).map(el=>[el,parseFloat(getComputedStyle(el).fontSize)]);for(const [el,font]of nodes)if(Number.isFinite(font))el.style.fontSize=font*2+'px';});await capture('reflow-200-'+size.width);check('Admin 200% text reflows '+size.width,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.locator('#discovery-listing > summary').click();check('Enlarged listing control opens '+size.width,await page.locator('#discovery-listing').getAttribute('open')!==null);}
  await page.setViewportSize(sizes[2]);await page.goto(base+record);await page.locator('#discovery-listing > summary').focus();await page.keyboard.press('Enter');check('Listing disclosure opens by keyboard',await page.locator('#discovery-listing').getAttribute('open')!==null);await page.locator('#discovery-listing textarea').fill('Relist the same synthetic fixture through its normal audited control.');await page.getByRole('button',{name:'List in discovery',exact:true}).click();state=inspect();check('Real relist control restores same record and adds audit history',state.rows.usage.listed===1&&state.audit.length===2&&state.audit[1].new_state==='Listed');
  await page.goto(base+'/research?q='+encodeURIComponent(fixture.slug.slice(-10)));check('Relisting restores normal keyword discovery',await page.locator('a[href="/research/'+fixture.slug+'"]').count()===1);
  check('No browser script exceptions',errors.length===0);report.adminCatalogueScope='Synthetic isolated local usage and listing controls; direct links, authorization, CSRF, audit and 200% text. No production mutation.';
 }catch(error){await capture('failed-'+Date.now());throw error;}finally{if(fixture)runPhp(['tests/admin-catalogue-state.php','cleanup']);}
}
