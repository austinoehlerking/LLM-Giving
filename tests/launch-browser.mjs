import {revealAdminControls} from './admin-controls.mjs';
import {authorizedHandoff,openResearchHelp} from './browser-authorization.mjs';
import fs from 'node:fs';import path from 'node:path';import {root} from '../tools/runtime.mjs';
export async function launchQa({page,ctx,base,runPhp,check,visibleActions,password}){
 const out=path.join(root,'storage/qa/launch-safeguards');fs.mkdirSync(out,{recursive:true});const shots=[];
 const shot=async(name)=>{await page.screenshot({path:path.join(out,name+'.png')});shots.push(name+'.png');};
 const completed=JSON.parse(runPhp(['tests/completed-workflow-state.php']));const real=base+'/research/'+completed.slug;
 for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){
  await page.setViewportSize(size);await page.goto(real);await shot('first-viewport-'+size.width);await visibleActions(page,'.research-heading .primary-actions > *','Findings '+size.width);
  const summary=await page.locator('.findings-summary').innerText();check('Scientific summary '+size.width,summary.toLowerCase().includes('synthetic')&&!summary.includes('REVISION'));
  await page.locator('.findings-summary').scrollIntoViewIfNeeded();await shot('summary-'+size.width);
 }
 await page.setViewportSize({width:683,height:384});await page.goto(real);await page.evaluate(()=>document.body.style.fontSize='200%');check('Findings enlarged text reflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await shot('enlarged-text');
 await page.setViewportSize({width:1366,height:768});
 runPhp(['bin/console.php','admin','launch-qa@example.invalid'],password);
 await page.goto(base+'/admin/login');await page.getByLabel('Email').fill('launch-qa@example.invalid');await page.getByLabel('Password').fill(password);await page.getByRole('button',{name:'Sign in',exact:true}).click();
 await page.goto(base+'/admin/system');await page.getByRole('button',{name:'Clean up expired unused handoffs'}).click();check('Admin retention action completed',page.url().includes('/admin/system'));await page.getByRole('heading',{name:'AI handoff archive retention'}).scrollIntoViewIfNeeded();await shot('archive-maintenance');
 const f=JSON.parse(runPhp(['tests/workflow-browser-state.php','create']));
 await page.goto(base+'/admin/research/'+f.id);const mod=page.locator('form.moderate').filter({has:page.locator('[name=type][value=request]')});await revealAdminControls(mod);await mod.locator('[name=state]').selectOption('Approved');await mod.locator('[name=reason]').fill('Synthetic archive limit QA fixture.');await mod.getByRole('button',{name:'Save moderation decision'}).click();
 const url=base+'/research/'+f.slug;await page.goto(url);
 const copied=await authorizedHandoff(page);const archiveHref=await page.locator('[data-issued-archive]').getAttribute('href'),id=archiveHref.split('/').pop();const issued=await page.request.get(base+archiveHref),original=await issued.body();check('Copy succeeds below quota',issued.status()===200);check('Private handoff preserves public archive bytes',copied.split('\n\nPRIVATE VOLUNTEER AUTHORIZATION SUPPLEMENT')[0]===original.toString('utf8'));await page.goto(url);await shot('copy-success');
 for(let i=1;i<60;i++){const r=await page.request.get(url+'/handoff.md');check('Copy quota allows issuance '+(i+1),r.status()===200);}
 await openResearchHelp(page);await page.locator('[data-authorize-url]').click();await page.locator('[name=authorize]').check();await page.locator('[name=terms]').check();await page.locator('[name=human_level]').selectOption('none');const blocked=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('/authorize/'));await page.getByRole('button',{name:'Copy authorized handoff'}).click();const denied=await blocked;check('61st copy blocked with retry guidance',denied.status()===429&&denied.headers()['retry-after']==='3600');await page.getByRole('alert').filter({hasText:'Copy limit reached'}).waitFor();await shot('copy-limit');
 const archive=await page.request.get(url+'/issued/'+id);check('Archive still readable after copy limit',archive.status()===200&&(await archive.body()).equals(original));
 fs.writeFileSync(path.join(out,'index.html'),'<meta charset="utf-8"><title>Launch safeguards QA</title><style>body{background:#14202a;color:#fff;font:16px system-ui}img{max-width:100%;border:1px solid #789}section{margin:2rem}</style><h1>Summary and archive safeguards</h1>'+shots.map(s=>'<section><h2>'+s+'</h2><img src="'+s+'"></section>').join(''));
}
