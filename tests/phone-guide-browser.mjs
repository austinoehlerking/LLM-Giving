import fs from 'node:fs';
import path from 'node:path';
import {root,env} from '../tools/runtime.mjs';
/** Local UI evidence only. Never claims actual phone launch or Cloud execution. */
export async function phoneGuideQa({page,ctx,base,check,visibleActions,report}) {
 const out=path.join(root,'storage/qa/phone-guide');fs.mkdirSync(out,{recursive:true});
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}],errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 page.on('console',message=>{if(message.type()==='error')errors.push(message.text());});
 const screenshot=async(p,name,privatePage=false)=>{const file=path.join(out,name+'.png');await p.screenshot({path:file,fullPage:false,...(privatePage?{mask:[p.locator('textarea')]}:{})});report.screenshots.push(file);return file;};
 const inspect=async(label,files)=>{console.log('INSPECT PHONE GUIDE '+label+': '+files.join(' | '));if(['1','phone-guide'].includes(env.QA_INSPECT_FIRST))await new Promise(resolve=>{process.stdin.resume();process.stdin.once('data',()=>{process.stdin.pause();resolve();});});};
 const initial=async(p,url,label,selector,privatePage=false)=>{const files=[];for(const size of sizes){await p.setViewportSize(size);if(url){const response=await p.goto(base+url);check(label+' HTTP success '+size.width,response.status()===200);}await p.evaluate(()=>scrollTo(0,0));files.push(await screenshot(p,label+'-'+size.width+'x'+size.height,privatePage));check(label+' starts at scroll zero '+size.width,await p.evaluate(()=>scrollY===0));await visibleActions(p,selector,label+' '+size.width);}await inspect(label,files);await p.setViewportSize(sizes[0]);};
 const reflow=async(p,label)=>{await p.setViewportSize(sizes[2]);await p.evaluate(()=>{const fonts=[...document.querySelectorAll('body,body *')].filter(el=>el instanceof HTMLElement).map(el=>[el,parseFloat(getComputedStyle(el).fontSize)]);for(const [el,size]of fonts)if(Number.isFinite(size))el.style.fontSize=(size*2)+'px';scrollTo(0,0);});await screenshot(p,label+'-200-percent');check(label+' all-font 200 percent reflows',await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));};
 let native;
 try{
  await initial(page,'/volunteer/quick-start','guide','[data-guide-actions] > a,[data-phone-entry]');
  check('Guide has real content and correct title',(await page.title()).includes('Volunteer quick start')&&await page.locator('article[data-unavailable]').count()===0&&await page.locator('#quick-start-title').isVisible());
  check('Phone prerequisites clearly mark Cloud unsupported and unverified',(await page.locator('[data-cloud-status]').innerText()).includes('unsupported')&&(await page.locator('[data-cloud-status]').innerText()).includes('unverified'));
  check('Guide contains no private session or return material',!await page.locator('input,textarea,form').count()&&!new RegExp('/return/[a-f0-9]{32}|#session:','i').test(await page.locator('article').innerText()));
  await reflow(page,'guide');await page.goto(base+'/volunteer/quick-start');
  await page.locator('[data-phone-entry]').click();check('Phone entry opens the actual integrated section',new URL(page.url()).hash==='#phone-cloud'&&await page.locator('#phone-cloud').isVisible());
  await page.locator('[data-phone-guide-actions] a[href^="/volunteer?"]').click();check('Restricted settings action opens exact preselection',new URL(page.url()).search==='?session_profile=restricted_web_v1');
  await initial(page,'/volunteer?session_profile=restricted_web_v1','restricted-setup','.volunteer-profile-choice select,.time-card,.connect-ai');
  check('Restricted profile preselection is real',await page.locator('[name=session_profile]').inputValue()==='restricted_web_v1');
  check('Restricted setup exposes the actual Cloud capability warning',await page.locator('[data-volunteer-profile-note]').isVisible());
  await page.locator('[name=session_profile]').selectOption('general');check('General choice hides restricted-only help',await page.locator('[data-volunteer-profile-note]').isHidden());
  await page.locator('[name=session_profile]').selectOption('restricted_web_v1');check('Restricted choice restores the warning',await page.locator('[data-volunteer-profile-note]').isVisible());
  await page.getByRole('button',{name:'Connect my AI',exact:true}).click();await page.getByRole('heading',{name:'Authorize this session',exact:true}).waitFor();
  await initial(page,null,'restricted-authorization','.volunteer-start .primary-actions > *');
  check('Server review retains one validated restricted profile',await page.locator('input[name=session_profile]').count()===1&&await page.locator('input[name=session_profile]').inputValue()==='restricted_web_v1');
  check('Authorization remains unchecked and separate',await page.locator('[name=authorize],[name=authorized_resources],[name=terms]').evaluateAll(els=>els.length===3&&els.every(el=>!el.checked))&&await page.locator('[name=human_level]').inputValue()==='');
  check('Review displays the server-derived restricted label',(await page.locator('.volunteer-profile-details > summary').innerText()).includes('Restricted web research'));
  check('Restricted review does not solicit irrelevant software consent',await page.locator('[name=tool_terms]').count()===0);
  await reflow(page,'authorization');
  await page.evaluate(()=>{for(const el of document.querySelectorAll('body,body *'))if(el instanceof HTMLElement)el.style.removeProperty('font-size');});await page.setViewportSize(sizes[0]);
  for(const name of ['authorize','authorized_resources','terms'])await page.locator('[name='+name+']').check();await page.locator('[name=human_level]').selectOption('none');await page.getByRole('button',{name:'Authorize session',exact:true}).click();await page.getByRole('heading',{name:'Your volunteer session is ready',exact:true}).waitFor();
  await initial(page,null,'synthetic-session-created','.volunteer-start .primary-actions > *',true);
  check('Actual local authorization provides a private dashboard',await page.locator('#volunteer-private-link').inputValue().then(value=>new URL(value).pathname.startsWith('/volunteer/')));
  await page.getByRole('link',{name:'Open private dashboard',exact:true}).click();
  await initial(page,null,'restricted-dashboard','[data-session-actions] button');
  check('Private dashboard displays the stored restricted profile',(await page.locator('.volunteer-profile-details > summary').innerText()).includes('Restricted web research'));
  await page.locator('.volunteer-profile-details > summary').click();check('Dashboard explains real restricted capability boundaries',(await page.locator('.volunteer-profile-details').innerText()).includes('calculators')&&(await page.locator('.volunteer-profile-details').innerText()).includes('not a technical sandbox'));
  await page.getByRole('button',{name:'Stop session',exact:true}).click();check('Actual local Stop revokes this synthetic session',(await page.locator('.session-state').innerText()).includes('Session stopped'));
  await page.goto(base+'/volunteer/quick-start');await page.locator('[data-guide-actions] a[href="/volunteer"]').click();check('Guide primary action opens actual general setup',new URL(page.url()).pathname==='/volunteer');
  await initial(page,'/volunteer','general-setup','.volunteer-profile-choice select,.time-card,.connect-ai');
  check('Existing default stays general',await page.locator('[name=session_profile]').inputValue()==='general');
  await page.getByRole('button',{name:'Connect my AI',exact:true}).click();check('Existing general path retains server review',await page.locator('input[name=session_profile]').inputValue()==='general'&&await page.locator('[name=tool_terms]').count()===1);
  await page.goto(base+'/volunteer?session_profile=restricted_web_v1');await reflow(page,'restricted-setup');
  native=await ctx.browser().newContext({javaScriptEnabled:false,viewport:sizes[0]});const np=await native.newPage();await np.goto(base+'/volunteer?session_profile=restricted_web_v1');
  check('Native setup preserves server preselection',await np.locator('[name=session_profile]').inputValue()==='restricted_web_v1');await np.getByRole('button',{name:'Connect my AI',exact:true}).click();check('Native configure retains the exact profile',await np.locator('input[name=session_profile]').inputValue()==='restricted_web_v1');
  check('Configuring never issues a private session',await np.locator('#volunteer-private-link').count()===0);
  report.consoleErrors=errors;check('No guide/setup script or console errors',errors.length===0);
  report.scope='Local rendered guide/setup/review/navigation evidence only. Synthetic local authorization/Stop only; no actual handset, Codex Cloud research or accepted-result claim.';
 } finally {await native?.close();}
}
