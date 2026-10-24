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
  await p.getByRole('button',{name:'Connect my AI',exact:true}).click();
  check('Session consent begins unchecked',await p.locator('[name=authorize],[name=authorized_resources],[name=terms]').evaluateAll(es=>es.length===3&&es.every(e=>!e.checked)));
  check('No orchestrator copy control precedes actual authorization',await p.locator('[data-copy-orchestrator]').count()===0);
  for(const name of ['authorize','authorized_resources','terms'])await p.locator('[name='+name+']').check();
  await p.locator('[name=human_level]').selectOption('none');
  await p.getByRole('button',{name:/^Authorize (?:volunteer )?session$/}).click();
  await p.locator('#orchestrator-handoff').waitFor({state:'attached'});
  return p.locator('.volunteer-start > .primary-actions a').getAttribute('href');
 }
 const referenceResponse=await page.request.get(base+'/volunteer/orchestrator.txt');
 const publicText=await referenceResponse.text();
 check('Public orchestrator guidance is reference only without a private supplement',referenceResponse.status()===200&&publicText.includes('PUBLIC REFERENCE ONLY')&&!publicText.includes('PRIVATE VOLUNTEER SESSION SUPPLEMENT'));
 const dashboard=await authorize(page);
 for(const size of sizes){
  await page.setViewportSize(size);await page.evaluate(()=>scrollTo(0,0));
  await page.screenshot({path:path.join(out,'authorized-initial-'+size.width+'x'+size.height+'.png'),fullPage:false,mask:[page.locator('#orchestrator-handoff'),page.locator('#volunteer-private-link')]});
  await visibleActions(page,'.volunteer-start > .primary-actions a,[data-copy-orchestrator],[data-orchestrator-text] summary','Orchestrator authorized '+size.width);
 }
 const text=await page.locator('#orchestrator-handoff').inputValue();
 const reference=fs.readFileSync(path.join(root,'templates/volunteer-orchestrator.txt'),'utf8').replaceAll('\r\n','\n');
 check('Actual authorized handoff retains complete reusable instructions',text.startsWith(reference+'\nPRIVATE VOLUNTEER SESSION SUPPLEMENT'));
 check('Private delivery contains exact session limits and original authorization',text.includes('RECORDED SESSION SCOPE')&&text.includes('"assignment_limit": 3')&&text.includes('"assignments_used": 0'));
 await page.getByRole('button',{name:'Copy orchestrator handoff',exact:true}).click();
 await page.locator('[data-orchestrator-status]').filter({hasText:'Copied the complete'}).waitFor();
 check('Clipboard contains complete actual handoff',await page.evaluate(()=>navigator.clipboard.readText()).then(v=>v.replaceAll('\r\n','\n')===text));
 await page.evaluate(()=>Object.defineProperty(navigator.clipboard,'writeText',{configurable:true,value:async()=>{throw new DOMException('Synthetic permission denial','NotAllowedError');}}));
 await page.getByRole('button',{name:'Copy orchestrator handoff',exact:true}).click();
 await page.locator('[data-orchestrator-status]').filter({hasText:'Clipboard'}).waitFor();
 check('Denial clears success and exposes full manual text',await page.locator('[data-orchestrator-text]').getAttribute('open')!==null&&await page.locator('#orchestrator-handoff').inputValue()===text);
 await page.screenshot({path:path.join(out,'clipboard-denied.png'),fullPage:false,mask:[page.locator('#orchestrator-handoff'),page.locator('#volunteer-private-link')]});
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
  await p.screenshot({path:path.join(out,'native-initial-375x667.png'),fullPage:false,mask:[p.locator('#orchestrator-handoff'),p.locator('#volunteer-private-link')]});
  await visibleActions(p,'.volunteer-start > .primary-actions a,[data-orchestrator-text] summary','Native orchestrator 375');
  check('Native copy enhancement stays hidden',await p.locator('[data-copy-orchestrator]').isHidden());
  await p.locator('[data-orchestrator-text] summary').click();
  check('Native disclosure exposes complete private handoff',await p.locator('#orchestrator-handoff').isVisible()&&(await p.locator('#orchestrator-handoff').inputValue()).includes('END PRIVATE SESSION SUPPLEMENT'));
  await p.goto(base+nativeDashboard);await p.getByRole('button',{name:'Stop session',exact:true}).click();
 }finally{await native.close();}
 check('Orchestrator route has no browser script exceptions',errors.length===0);
 assert.equal(errors.length,0,errors.join('\n'));
}
