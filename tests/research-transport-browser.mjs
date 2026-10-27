import fs from 'node:fs';import path from 'node:path';import {root} from '../tools/runtime.mjs';
/** Register through tools/qa.mjs; the shared runner owns the server/browser lifecycle. */
export async function researchTransportQa({page,base,runPhp,check,visibleActions}) {
  const output=path.join(root,'storage/qa/research-transport-browser');fs.mkdirSync(output,{recursive:true});const screenshots=[];let fixture;
  async function shot(name){const file=path.join(output,name+'.png');await page.screenshot({path:file,fullPage:false});screenshots.push(file);}
  try{
    fixture=JSON.parse(runPhp(['tests/research-transport-fixture.php']));
    await page.goto(base+fixture.base+'/return/'+fixture.grant_id+'#'+fixture.secret);
    await page.waitForURL('**/return/'+fixture.grant_id+'/submit');
    const transfer=base+fixture.base+'/return/'+fixture.grant_id+'/transport';
    for(const viewport of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){
      await page.setViewportSize(viewport);await page.goto(transfer);await page.evaluate(()=>scrollTo(0,0));
      await shot('initial-'+viewport.width+'x'+viewport.height);
      await visibleActions(page,'[data-transport-input], [data-transport-output]','Transport '+viewport.width+'x'+viewport.height);
    }
    await page.getByRole('button',{name:'Retrieve complete task context'}).click();
    await page.waitForFunction(()=>document.querySelector('[data-transport-status]').textContent.includes('all required dependency bytes verified'));
    check('Primary input action verifies complete handoff and all required pinned context before research',await page.locator('#transport-received').inputValue()!==''&&await page.locator('[data-transport-resources] details').count()>0);
    await shot('complete-context-short-mobile');
    await page.locator('#transport-packet').fill(JSON.stringify(fixture.packet));await page.getByRole('button',{name:'Save complete packet'}).click();
    await page.waitForFunction(()=>document.querySelector('[data-transport-status]').textContent.includes('Complete output finalized privately'));
    check('Complete long packet finalizes without automatic Check or Submit',await page.locator('[data-transport-submit]').isVisible()&&await page.locator('[data-transport-submit] [name=transport_id]').inputValue()!=='');
    await shot('finalized-output-short-mobile');
    await page.setViewportSize({width:1366,height:768});await page.goto(transfer);await page.evaluate(()=>document.documentElement.style.zoom='2');await shot('text-200-percent');
    check('200% zoom retains readable reflow without horizontal clipping',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
    await page.evaluate(()=>document.documentElement.style.zoom='');
    return {screenshots,checks:'Transport first viewports, complete input primary action, >200k private output finalization without Submit, 200% reflow. Screenshots require explicit reviewer inspection.'};
  }finally{if(fixture)runPhp(['tests/research-transport-fixture.php','cleanup',fixture.id]);}
}