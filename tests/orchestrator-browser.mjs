import {connectVolunteer,readyInstructions,privateControllerInstructions} from './session-wizard-actions.mjs';
import {startupQa} from './volunteer-startup-browser.mjs';
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {root} from '../tools/runtime.mjs';
/** Deterministic UI/privacy regression. Genuine AI-authored execution evidence is recorded separately. */
export async function orchestratorQa({page,ctx,base,check,visibleActions}) {
 const out=path.join(root,'storage/qa/orchestrator-browser');fs.mkdirSync(out,{recursive:true});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 async function authorize(p){
  await p.goto(base+'/volunteer');
  check('Public setup has no private orchestrator authorization',await p.locator('#orchestrator-handoff').count()===0);
  await connectVolunteer(p);
  check('Session consent begins unchecked',await p.locator('[name=authorize],[name=authorized_resources],[name=terms]').evaluateAll(es=>es.length===3&&es.every(e=>!e.checked)));
  check('No orchestrator copy control precedes actual authorization',await p.locator('[data-wizard-copy=instructions]').count()===0);
  for(const name of ['authorize','authorized_resources','terms'])await p.locator('[name='+name+']').check();

  await p.getByRole('button',{name:/^Authorize (?:volunteer )?session$/}).click();
  await p.locator('#orchestrator-handoff').waitFor({state:'attached'});
  return p.locator('[data-wizard-dashboard]').getAttribute('href');
 }
 const referenceResponse=await page.request.get(base+'/volunteer/orchestrator.txt');
 const publicText=await referenceResponse.text();
 check('Public orchestrator guidance is reference only without a private supplement',referenceResponse.status()===200&&publicText.includes('PUBLIC REFERENCE ONLY')&&!publicText.includes('PRIVATE VOLUNTEER SESSION SUPPLEMENT'));
 await startupQa({page,ctx,base,check,visibleActions});
 const dashboard=await authorize(page);
 for(const size of sizes){
  await page.setViewportSize(size);await page.evaluate(()=>scrollTo(0,0));
  await page.screenshot({path:path.join(out,'authorized-initial-'+size.width+'x'+size.height+'.png'),fullPage:false,mask:[page.locator('#orchestrator-handoff:visible'),page.locator('#volunteer-private-link:visible')]});
  await visibleActions(page,'[data-wizard-copy=link],[data-wizard-email-toggle],[data-wizard-next="2"]','Orchestrator authorized '+size.width);
 }
 await readyInstructions(page);
 const text=await page.locator('#orchestrator-handoff').inputValue();const complete=await privateControllerInstructions(page,dashboard.split('/')[2]);
 const reference=fs.readFileSync(path.join(root,'templates/volunteer-orchestrator.txt'),'utf8').replaceAll('\r\n','\n');
 check('One-message authorization loads complete authenticated instructions',text.startsWith('Please start my LLM.Giving volunteer session')&&text.length<=1200&&complete.includes(reference+'\nPRIVATE VOLUNTEER SESSION SUPPLEMENT'));
 check('Private delivery contains exact session limits and original authorization',complete.includes('RECORDED SESSION SCOPE')&&complete.includes('"assignment_limit": 3')&&complete.includes('"assignments_used": 0'));
 await page.getByRole('button',{name:'Copy start message',exact:true}).click();
 await page.locator('[data-wizard-copy-status=instructions]').filter({hasText:'Start message copied'}).waitFor();
 check('Clipboard contains exact short start message',await page.evaluate(()=>navigator.clipboard.readText()).then(v=>v.replaceAll('\r\n','\n')===text));
 await page.evaluate(()=>Object.defineProperty(navigator.clipboard,'writeText',{configurable:true,value:async()=>{throw new DOMException('Synthetic permission denial','NotAllowedError');}}));
 await page.getByRole('button',{name:'Copy start message',exact:true}).click();
 await page.locator('[data-wizard-copy-status=instructions]').filter({hasText:'Clipboard'}).waitFor();
 check('Denial clears success and exposes full manual text',await page.locator('[data-wizard-instructions-manual]').getAttribute('open')!==null&&await page.locator('#orchestrator-handoff').inputValue()===text);
 await page.screenshot({path:path.join(out,'clipboard-denied.png'),fullPage:false,mask:[page.locator('#orchestrator-handoff:visible'),page.locator('#volunteer-private-link:visible')]});
 // A separate anonymous controller receives only the actually issued private capability.
 const link=await page.locator('#volunteer-private-link').inputValue();
 const other=await ctx.browser().newContext({viewport:sizes[0]});const controller=await other.newPage();
 try{
  await controller.goto(link);await controller.waitForURL(base+dashboard);
  check('Session fragment exchange works with the normal global scripts',!new URL(controller.url()).hash&&await controller.getByRole('button',{name:'Pick up next task',exact:true}).isVisible());
  check('Copying and transfer reserve no assignment',await controller.getByText('No assignments picked up yet.',{exact:true}).isVisible());
  await controller.getByRole('button',{name:'Stop session',exact:true}).click();
  check('Transferred session honors explicit Stop',await controller.getByText('Session stopped — existing receipts remain available',{exact:true}).isVisible());
 }finally{await other.close();}
 const native=await ctx.browser().newContext({javaScriptEnabled:false,viewport:sizes[2]});const p=await native.newPage();
 try{
  const nativeDashboard=await authorize(p);
  await p.screenshot({path:path.join(out,'native-initial-375x667.png'),fullPage:false,mask:[p.locator('#orchestrator-handoff:visible'),p.locator('#volunteer-private-link:visible')]});
  await visibleActions(p,'.wizard-save-methods a,#wizard-step-1 .wizard-actions a','Native orchestrator 375');
  check('Native copy enhancement stays hidden',await p.locator('[data-wizard-copy=instructions]').isHidden());
  await p.locator('[data-wizard-instructions-manual] summary').click();
  check('Native disclosure exposes short start message and complete private guidance',await p.locator('#orchestrator-handoff').isVisible()&&(await p.locator('#orchestrator-handoff').inputValue()).length<=1200&&(await privateControllerInstructions(p,nativeDashboard.split('/')[2])).includes('END PRIVATE SESSION SUPPLEMENT'));
  await p.goto(base+nativeDashboard);await p.getByRole('button',{name:'Stop session',exact:true}).click();
 }finally{await native.close();}
 check('Orchestrator route has no browser script exceptions',errors.length===0);
 assert.equal(errors.length,0,errors.join('\n'));
}
