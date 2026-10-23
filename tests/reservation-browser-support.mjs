import assert from 'node:assert/strict';
/** Exercise the shared pickup endpoint while public pickup controls are a separate task. */
export async function reservationRelease(page,revision){
 const url=new URL(new URL(page.url()).pathname+'/reservation',page.url()).href;
 const csrf=await page.locator('[name=csrf]').first().inputValue();
 const response=await page.request.post(url,{form:{csrf,operation:'release',task_revision:String(revision)}});
 assert.equal(response.status(),200,await response.text());
 assert.equal((await response.json()).status,'released');
}
export async function reservationPickup(page,revision){
 const path=new URL(page.url()).pathname;
 assert.match(path,/\/tasks\/[a-f0-9]{32}$/);
 const csrf=await page.locator('[name=csrf]').first().inputValue();
 const response=await page.request.post(new URL(path+'/reservation',page.url()).href,{form:{csrf,operation:'claim',task_revision:String(revision)}});
 assert.equal(response.status(),200,await response.text());
 assert.equal((await response.json()).status,'reserved');
}
