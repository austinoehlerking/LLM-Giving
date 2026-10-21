import fs from 'node:fs';
import path from 'node:path';
import {root} from '../tools/runtime.mjs';
export async function receiptQa({page,ctx,base,runPhp,check,visibleActions}){
 const output=path.join(root,'storage/qa/receipt');fs.mkdirSync(output,{recursive:true});
 const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 const errors=[],shots=[];page.on('pageerror',error=>errors.push(error.message));
 const title='[QA] Effects of plastic pollution on the biological carbon pump: transparent methods and reproducible evidence for community researchers';
 async function submit(target,email=''){
  await target.goto(base+'/submit');await target.locator('[name=title]').fill(title);
  await target.locator('[name=question]').fill('Which reproducible methods could volunteers use to compare the supplied synthetic research sources without asserting real scientific findings?');
  await target.locator('[name=category_id]').selectOption({label:'Environment & Climate'});
  if(email){await target.getByText('Optional attribution & private email',{exact:true}).click();await target.locator('[name=email]').fill(email);}
  await target.locator('[name=terms]').press('Space');
  await target.locator('#request-form > .actions').getByRole('button',{name:'Submit for approval',exact:true}).press('Enter');
  await target.locator('#management-link').waitFor();
  return target.locator('#management-link').inputValue();
 }
 async function shot(target,name){
  const file=path.join(output,name+'.png'),field=target.locator('#management-link');
  const original=await field.getAttribute('type');
  // Redact via a native password field; overlay masks can cover the foreground dialog.
  await field.evaluate(el=>{el.type='password';});
  try{await target.screenshot({path:file,fullPage:false});shots.push(file);}
  finally{await field.evaluate((el,type)=>{if(type===null)el.removeAttribute('type');else el.setAttribute('type',type);},original);}
 }
 const link=await submit(page),rid=new URL(link).pathname.split('/').pop();
 const dialog=page.getByRole('dialog',{name:'Have you saved your management link?',exact:true});
 check('Receipt warns of loss of private access',await page.locator('.management-warning').innerText().then(text=>text.includes('If you lose it, you lose access to your request.')));
 check('Receipt includes an alert symbol',await page.locator('.management-warning svg[aria-hidden=true]').count()===1);
 check('Requester link does not claim site administrator approval powers',!await page.locator('.management-warning').innerText().then(text=>/approve research|site admin/i.test(text)));
 check('Receipt remains private and pending',(await page.request.get(base+'/research/'+rid+'/context.json')).status()===404);
 for(const size of sizes){
  await page.setViewportSize(size);await page.evaluate(()=>scrollTo(0,0));await shot(page,'initial-'+size.width+'x'+size.height);
  await visibleActions(page,'.receipt .primary-actions > *','Private receipt '+size.width);
  check('Warning and complete link input visible '+size.width,await page.locator('.management-warning h2,#management-link').evaluateAll(els=>els.every(el=>{const r=el.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight;})));
 }
 await page.setViewportSize(sizes[0]);await page.locator('#management-link').click();
 let nativeType='';
 page.once('dialog',async native=>{nativeType=native.type();await native.dismiss();});
 await page.goto(base+'/about',{timeout:4000}).catch(()=>{});
 check('Browser navigation has a real unsaved-link warning',nativeType==='beforeunload'&&new URL(page.url()).pathname==='/submit');
 await page.getByRole('navigation',{name:'Main navigation',exact:true}).getByRole('link',{name:'Explore research',exact:true}).click();
 await dialog.waitFor();
 check('Navigation before copying opens the confirmation',new URL(page.url()).pathname==='/submit'&&(await page.locator('#management-save-copy-note').innerText()).includes('not used Copy management link'));
 await dialog.getByRole('button',{name:'Confirm saved',exact:true}).click();
 check('Unchecked saved confirmation cannot continue',await dialog.isVisible()&&new URL(page.url()).pathname==='/submit'&&await page.locator('#management-saved-confirmation').evaluate(el=>!el.validity.valid));
 await page.keyboard.press('Tab');check('Keyboard focus stays in the modal',await page.evaluate(()=>!!document.activeElement.closest('#management-save-dialog')));
 await page.keyboard.press('Escape');await dialog.waitFor({state:'hidden'});
 await page.getByRole('link',{name:'Manage my request',exact:true}).click();await dialog.waitFor();
 check('Dismissing the modal does not confirm saving',new URL(page.url()).pathname==='/submit');
 await dialog.getByRole('button',{name:'Return to link',exact:true}).click();
 check('Return action selects the complete private link',await page.locator('#management-link').evaluate(el=>el===document.activeElement&&el.selectionStart===0&&el.selectionEnd===el.value.length));
 await page.evaluate(()=>{Object.defineProperty(navigator.clipboard,'writeText',{value:async()=>{throw new DOMException('Synthetic denied clipboard','NotAllowedError');},configurable:true});});
 await page.getByRole('button',{name:'Copy management link',exact:true}).click();await dialog.waitFor();
 check('Failed copy offers manual saving without claiming success',(await page.locator('#management-save-copy-note').innerText()).includes('Automatic copy was unavailable'));
 await shot(page,'clipboard-denied');
 await dialog.getByRole('button',{name:'Return to link',exact:true}).click();await page.keyboard.press(process.platform==='darwin'?'Meta+c':'Control+c');
 check('Manual copy includes the full private fragment',await page.evaluate(()=>navigator.clipboard.readText())===link);
 await page.getByRole('link',{name:'Manage my request',exact:true}).click();await dialog.waitFor();
 await dialog.getByLabel('I confirm I have copied and saved my management link.',{exact:true}).check();
 await dialog.getByRole('button',{name:'Confirm saved',exact:true}).click();
 await page.getByRole('heading',{name:title,exact:true}).waitFor();
 check('Manual save confirmation follows the intended management link',new URL(page.url()).pathname==='/manage/'+rid);
 await page.getByText('Rotate the private link',{exact:true}).click();
 const rotate=page.locator('form[action$="/rotate"]');await rotate.locator('input[type=checkbox]').check();await rotate.getByRole('button').click();
 await page.getByRole('heading',{name:'Your replacement management link',exact:true}).waitFor();
 const replacement=await page.locator('#management-link').inputValue();
 check('Replacement link receives a fresh save warning',replacement!==link&&await page.locator('.management-warning').isVisible());
 const anonymous=await ctx.browser().newContext();
 try{const old=await anonymous.newPage();await old.goto(link);await old.getByRole('heading',{name:'Unable to continue'}).waitFor();check('Rotated old link remains revoked',await old.getByText('This management link is invalid or has been revoked.',{exact:true}).isVisible());}finally{await anonymous.close();}
 await page.getByRole('button',{name:'Copy management link',exact:true}).click();await dialog.waitFor();
 await page.getByText('Your management link was copied. Paste it somewhere safe before confirming.',{exact:true}).waitFor();
 check('Successful copy includes the complete replacement capability',await page.evaluate(()=>navigator.clipboard.readText())===replacement);
 for(const size of sizes){
  await page.setViewportSize(size);await shot(page,'confirmation-'+size.width+'x'+size.height);
  await visibleActions(page,'#management-save-dialog .dialog-actions > button','Save confirmation '+size.width);
  check('Confirmation checkbox visible '+size.width,await page.locator('#management-saved-confirmation').evaluate(el=>{const r=el.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight;}));
 }
 check('Private capability is absent from Web Storage',await page.evaluate(value=>![...Object.values(localStorage),...Object.values(sessionStorage)].some(text=>text.includes(value)),replacement));
 await dialog.getByLabel('I confirm I have copied and saved my management link.',{exact:true}).check();await dialog.getByRole('button',{name:'Confirm saved',exact:true}).click();await dialog.waitFor({state:'hidden'});
 check('Explicit save confirmation is acknowledged',await page.locator('#management-copy-status').innerText()==='You confirmed that your management link is saved.');
 let unexpected=0;const unexpectedDialog=async native=>{unexpected++;await native.accept();};page.on('dialog',unexpectedDialog);
 await page.getByRole('navigation',{name:'Main navigation',exact:true}).getByRole('link',{name:'Explore research',exact:true}).click();
 check('Confirmed saving allows navigation without another prompt',new URL(page.url()).pathname==='/research'&&unexpected===0);page.off('dialog',unexpectedDialog);
 const nojs=await ctx.browser().newContext({javaScriptEnabled:false,viewport:sizes[2]});
 try{
  const native=await nojs.newPage();await submit(native,'receipt-qa@example.invalid');await shot(native,'no-javascript-375x667');
  check('No-JS receipt retains warning and complete private link',await native.locator('.management-warning').isVisible()&&(await native.locator('#management-link').inputValue()).includes('#'));
  check('No-JS receipt explains manual copying',await native.getByText('Select and copy the complete link manually before leaving this page.',{exact:true}).isVisible());
  check('Email recovery is described without exposing the email',await native.getByRole('link',{name:'request a replacement link',exact:true}).isVisible()&&!(await native.locator('main').innerText()).includes('receipt-qa@example.invalid'));
 }finally{await nojs.close();}
 const enlarged=await ctx.browser().newContext({viewport:{width:683,height:384},deviceScaleFactor:2,permissions:['clipboard-read','clipboard-write']});
 try{
  const zoom=await enlarged.newPage();await submit(zoom);await shot(zoom,'initial-enlarged');
  await zoom.getByRole('button',{name:'Copy management link',exact:true}).click();
  const modal=zoom.getByRole('dialog',{name:'Have you saved your management link?',exact:true});await modal.waitFor();await shot(zoom,'confirmation-enlarged');
  check('Receipt dialog reflows at 200% viewport/device scale',await zoom.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)&&await modal.evaluate(el=>el.scrollWidth<=el.clientWidth+1));
  await modal.getByLabel('I confirm I have copied and saved my management link.',{exact:true}).check();await modal.getByRole('button',{name:'Confirm saved',exact:true}).click();
 }finally{await enlarged.close();}
 check('No receipt script exceptions',errors.length===0);
 fs.writeFileSync(path.join(output,'evidence.json'),JSON.stringify({shots,errors},null,2));
}
