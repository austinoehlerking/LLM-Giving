import {readyDashboard} from './session-wizard-actions.mjs';
import fs from 'node:fs';
import path from 'node:path';
import {root,env} from '../tools/runtime.mjs';
/** Real local controls; synthetic research packets, never real AI/scientific validation. */
export async function volunteerCompactQa({page,ctx,base,runPhp,check,visibleActions,report}) {
 const out=path.join(root,'storage/qa/volunteer-compact');fs.mkdirSync(out,{recursive:true});
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}],contexts=[],sessions=[],errors=[];
 const fixtures=JSON.parse(runPhp(['tests/volunteer-state.php','create']));
 const watch=p=>{p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(m.type()==='error')errors.push(m.text());});};watch(page);
 const shot=async(p,name,privatePage=false)=>{const file=path.join(out,name+'.png');await p.screenshot({path:file,fullPage:false,...(privatePage?{mask:[p.locator('textarea:visible')]}:{})});report.screenshots.push(file);return file;};
 const pause=async(label,files)=>{console.log('INSPECT COMPACT '+label+': '+files.join(' | '));if(env.QA_INSPECT_FIRST==='volunteer-compact')await new Promise(resolve=>{process.stdin.resume();process.stdin.once('data',()=>{process.stdin.pause();resolve();});});};
 async function initial(p,label,selector,url=null){const shots=[],failures=[];for(const size of sizes){await p.setViewportSize(size);if(url)await p.goto(url);await p.evaluate(()=>scrollTo(0,0));shots.push(await shot(p,label+'-'+size.width+'x'+size.height));try{check(label+' starts at zero '+size.width,await p.evaluate(()=>scrollY===0));await visibleActions(p,selector,label+' '+size.width);if(size.width===1366)check(label+' default desktop has no vertical scrolling',await p.evaluate(()=>document.documentElement.scrollHeight<=innerHeight+1));}catch(e){failures.push(e.message);}}await pause(label,shots);if(failures.length)throw Error(failures.join('; '));await p.setViewportSize(sizes[0]);}
 async function reflow(p,label){for(const size of [sizes[0],sizes[2]]){await p.setViewportSize(size);await p.evaluate(()=>{window.__compactFonts=[...document.querySelectorAll('body,body *')].filter(el=>el instanceof HTMLElement).map(el=>[el,el.style.fontSize,parseFloat(getComputedStyle(el).fontSize)]);for(const[el,,n]of window.__compactFonts)if(Number.isFinite(n))el.style.fontSize=n*2+'px';scrollTo(0,0);});await shot(p,label+'-200-'+size.width);check(label+' 200% text reflows '+size.width,await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await p.evaluate(()=>{for(const[el,before]of window.__compactFonts)el.style.fontSize=before;delete window.__compactFonts;});}await p.setViewportSize(sizes[0]);}
 const state=id=>JSON.parse(runPhp(['tests/volunteer-state.php','state',id]));
 async function configure(p,fixture){await p.locator('[data-volunteer-advanced] > summary').click();await p.locator('[name=cause_choice][value=custom]').check();await p.locator('[name=scope_kind]').selectOption('research');await p.locator('[name=research_id]').fill(fixture.id);await p.locator('[name=model]').selectOption('gpt-6-astra');await p.locator('[name=reasoning]').selectOption('max');await p.locator('[name=tool]').fill('Codex');await p.locator('[data-volunteer-advanced] > summary').click();await p.getByRole('button',{name:'Connect my AI',exact:true}).click();await p.getByRole('heading',{name:'Authorize this session',exact:true}).waitFor();}
 try{
  let scenario=0;
  for(const js of [true,false])for(const profile of ['general','restricted_web_v1']){
   const label=(js?'js':'native')+'-'+profile;
   let p=page;if(!js){const c=await ctx.browser().newContext({javaScriptEnabled:false,viewport:sizes[0],reducedMotion:'reduce'});contexts.push(c);p=await c.newPage();watch(p);}
   const url=base+'/volunteer'+(profile==='general'?'':'?session_profile=restricted_web_v1');
   await initial(p,label+'-setup','.volunteer-profile-choice label,.time-card,.connect-ai,[data-volunteer-advanced] > summary',url);
   check(label+' true default/query selection',await p.locator('[name=session_profile]:checked').inputValue()===profile);
   check(label+' setup omits debug/status chatter',!(await p.locator('main').innerText()).includes('Dot + worker flow verified; phone start untested')&&await p.locator('[data-volunteer-profile-note]').count()===0);
   check(label+' native compact choice is accessible',await p.getByLabel('Web only',{exact:true}).count()===1&&await p.getByLabel('Web + tools',{exact:true}).count()===1);
   check(label+' advanced settings start closed',await p.locator('[data-volunteer-advanced]').getAttribute('open')===null);
   if(js)await reflow(p,label+'-setup');
   await p.getByLabel(profile==='general'?'Web only':'Web + tools',{exact:true}).check();await p.getByLabel(profile==='general'?'Web + tools':'Web only',{exact:true}).check();
   const fixture=fixtures[scenario%fixtures.length];await configure(p,fixture);
   await initial(p,label+'-authorization','[data-volunteer-consent] > .check,.volunteer-authorization .primary-actions > *');
   check(label+' validated scope/profile/model survive configuration',await p.locator('[data-volunteer-consent] input[name=session_profile]').inputValue()===profile&&await p.locator('[data-volunteer-consent] input[name=research_id]').inputValue()===fixture.id&&await p.locator('[data-volunteer-consent] input[name=model]').inputValue()==='gpt-6-astra'&&await p.locator('[data-volunteer-consent] input[name=reasoning]').inputValue()==='max');
   check(label+' removed declarations/profile/footer absent',await p.locator('[name=human_level],[name=human_details],.volunteer-profile-details,[data-declaration-details]').count()===0&&!(await p.locator('main').innerText()).includes('A public prompt does not create permission. This session authorizes no purchases'));
   check(label+' real permission/resource/publication confirmations remain unchecked',await p.locator('[name=authorize],[name=authorized_resources],[name=terms]').evaluateAll(es=>es.length===3&&es.every(e=>e.required&&!e.checked)));
   check(label+' optional software consent is profile appropriate',await p.locator('[name=tool_terms]').count()===(profile==='general'?1:0));
   if(js)await reflow(p,label+'-authorization');
   await p.getByRole('button',{name:'Authorize session',exact:true}).click();check(label+' unchecked required permission cannot start session',await p.locator('#orchestrator-handoff').count()===0&&await p.locator('[name=authorize]').evaluate(el=>!el.checkValidity()));
   for(const name of ['authorize','authorized_resources','terms'])await p.locator('[name='+name+']').check();await p.getByRole('button',{name:'Authorize session',exact:true}).click();await p.getByRole('heading',{name:'Your session is ready',exact:true}).waitFor();
   const privateLink=await p.locator('#volunteer-private-link').inputValue(),id=new URL(privateLink).pathname.split('/')[2];sessions.push(id);const handoff=await p.locator('#orchestrator-handoff').inputValue();
   check(label+' actual stored session honestly records not-collected provenance',state(id).collection==='session_not_collected_v1'&&state(id).declaration.level==='not_collected'&&state(id).profile===profile);
   check(label+' private orchestrator handoff requires all initial/later AI disclosure',handoff.includes('ALL initial and later scientific steering')&&handoff.includes('"level": "not_collected"')&&!handoff.includes('The captured volunteer declaration covers input through authorization'));
   await readyDashboard(p);
   if(scenario===0){
    await p.getByRole('button',{name:'Pick up next task',exact:true}).click();const workerHandoff=await p.locator('#session-task-handoff').inputValue();const returnLink=workerHandoff.match(/Private return link: (http[^\s]+)/)[1];
    await p.getByRole('link',{name:'Open result form',exact:true}).click();const packet=JSON.parse(runPhp(['tests/volunteer-state.php','packet',id]));delete packet.human_disclosure;
    await p.locator('[name=payload]').fill(JSON.stringify(packet));await p.getByRole('button',{name:'Check packet',exact:true}).click();await p.locator('[data-workflow-error]').getByText('Packet checks passed.',{exact:false}).waitFor();check('Controller JSON Check accepts true not-collected state without a hold',state(id).recorded===0);
    const wc=await ctx.browser().newContext({viewport:sizes[0]});contexts.push(wc);const wp=await wc.newPage();watch(wp);await wp.goto(returnLink);await wp.locator('[name=payload]').fill(JSON.stringify(packet));await wp.getByRole('button',{name:'Check packet',exact:true}).click();await wp.locator('[data-workflow-error]').getByText('Packet checks passed.',{exact:false}).waitFor();check('Separate-worker JSON Check uses trusted session provenance',state(id).recorded===0);
    await wp.getByRole('button',{name:'Submit task result',exact:true}).click();await wp.getByRole('heading',{name:'Submission accepted',exact:true}).waitFor();check('Recorded result says independent declaration was not collected',(await wp.locator('[data-influence-status]').innerText()).includes('independent contributor declaration was not collected')&&state(id).recorded===1);await shot(wp,'accepted-uncollected-receipt');
    await p.goto(base+'/volunteer/'+id);
   }
   await p.getByRole('button',{name:'Stop session',exact:true}).click();check(label+' real Stop revokes session',state(id).state==='stopped'&&await p.getByRole('button',{name:'Pick up next task',exact:true}).count()===0);scenario++;
  }
  check('No application script/console errors',errors.length===0);report.compactScope='Four local configure -> authorize -> private session -> Stop flows: both profiles with JS and without JS. One real synthetic accepted result exercises controller and separate worker JSON Check. Server tests separately cover influenced/unsure holds, forgery and historical declarations. No provider/phone execution or scientific validation.';
 }catch(error){await shot(page,'failed-'+Date.now(),true);throw error;}finally{for(const context of contexts)await context.close();runPhp(['tests/volunteer-state.php','cleanup'],JSON.stringify({fixtures,sessions}));}
}
