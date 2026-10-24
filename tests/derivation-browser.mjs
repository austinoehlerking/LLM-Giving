import fs from 'node:fs';import path from 'node:path';import assert from 'node:assert/strict';import {root} from '../tools/runtime.mjs';
/** Synthetic local software contract coverage; not real volunteer/scientific acceptance. */
export async function derivationQa({page,base,runPhp,check,visibleActions}){
 const fixture=JSON.parse(runPhp(['tests/derivation-state.php','setup']));const out=path.join(root,'storage/qa/calculations');
 const shot=async name=>page.screenshot({path:path.join(out,'derived-'+name+'.png'),fullPage:false});
 try{
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){
   await page.setViewportSize(size);
   for(const route of ['result','review','resources']){
    const response=await page.goto(base+fixture[route]);assert.equal(response.status(),200);
    // Required screenshots and action rectangles precede scrolling or an auto-scroll click.
    await shot(route+'-'+size.width+'x'+size.height);
    await visibleActions(page,route==='resources'?'.page-heading .primary-actions > *':'.workflow-heading .primary-actions > *','Model-derived '+route+' '+size.width+'x'+size.height);
   }
  }
  await page.setViewportSize({width:1366,height:768});await page.goto(base+fixture.result);
  await page.locator('.workflow-heading').getByRole('link',{name:'Model-derived arithmetic',exact:true}).click();
  check('Primary derived-record action opens the retained reasoning',await page.locator('[data-derivation=share]').isVisible()&&new URL(page.url()).hash==='#calculations');
  const record=page.locator('[data-derivation=share]');const text=await record.innerText();
  check('Derived record explicitly labels non-execution and retains input/unit/steps/limits',['not executed or computationally reproduced','Cited inputs','Worked steps','25 percent','Assumptions:','Limitations:'].every(s=>text.includes(s)));
  check('Derived record invents no execution manifest',await record.locator('pre').count()===0&&!text.includes('Software versions:')&&!text.includes('SHA-256'));
  const pending=page.waitForEvent('download');await record.getByRole('link',{name:'Download model-derived record',exact:true}).click();const download=await pending;
  const file=path.join(out,'derived-downloaded-record.json');await download.saveAs(file);const bundle=JSON.parse(fs.readFileSync(file,'utf8'));
  check('Actual browser download retains model-derived version, inputs and honest notice',bundle.format==='llm-giving-derivation-v1'&&bundle.derivation.version===1&&bundle.derivation.type==='model_derived'&&bundle.derivation.inputs[0].value===2&&bundle.derivation.outputs[0].value===25&&bundle.notice.includes('Not executed or computationally reproduced'));
  check('Derived download contains no invented code or command',!Object.hasOwn(bundle.derivation,'files')&&!Object.hasOwn(bundle.derivation,'command'));
  await shot('record-opened');
  await page.goto(base+fixture.resources);check('Resources attribute the derivation back to retained input evidence',(await page.locator('#resource-list').innerText()).includes('Model-derived arithmetic · not executed or computationally reproduced')&&await page.locator('a[href="'+fixture.bundle+'"]').count()>0);
  await page.goto(base+fixture.review);check('Independent review says assessed, never reproduced',await page.locator('[data-derivation-review=share]').count()===2&&(await page.locator('[data-derivation-review=share]').first().innerText()).includes('Derivation assessment · assessed'));
  for(const route of ['result','review','resources']){
   await page.setViewportSize({width:683,height:384});await page.goto(base+fixture[route]);await page.evaluate(()=>document.body.style.fontSize='200%');await shot(route+'-text200');
   check('Model-derived '+route+' 200% text reflows without clipping',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  }
 }finally{runPhp(['tests/derivation-state.php','cleanup',fixture.id]);}
}
