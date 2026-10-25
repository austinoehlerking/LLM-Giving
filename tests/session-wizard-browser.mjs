import {connectVolunteer,privateControllerInstructions} from './session-wizard-actions.mjs';
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {root} from '../tools/runtime.mjs';

export async function sessionWizardQa({page,ctx,base,runPhp,check,visibleActions,report}) {
 const out=path.join(root,'storage/qa/session-wizard');fs.mkdirSync(out,{recursive:true});
 const sessions=[];const errors=[];const sizes=[{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
 const captureOnly=process.env.QA_WIZARD_CAPTURE_ONLY==='1';
 const counts=()=>JSON.parse(runPhp(['tests/session-wizard-state.php','counts']));
 const masks=p=>[p.locator('textarea:visible'),p.locator('[name=email]:visible'),p.locator('[data-wizard-email-status]:visible')];
 const title=['Save your private link','Copy start message','Paste into Dot and send','Open your dashboard'];
 async function shot(p,name){const file=path.join(out,(captureOnly?'capture-':'exercise-')+name+'.png');await p.screenshot({path:file,fullPage:false,mask:masks(p)});report.screenshots.push(file);}
 async function create(p,size=sizes[0]){
  await p.setViewportSize(size);await p.goto(base+'/volunteer');
  await connectVolunteer(p);
  for(const name of ['authorize','authorized_resources','terms'])await p.locator('[name='+name+']').check();
  if(await p.locator('[name=human_level]').count())await p.locator('[name=human_level]').selectOption('none');
  await p.getByRole('button',{name:/^Authorize (?:volunteer )?session$/}).click();
  await p.getByRole('heading',{name:'Your session is ready',exact:true}).waitFor();
  const link=await p.locator('#volunteer-private-link').inputValue();const id=new URL(link).pathname.split('/')[2];sessions.push(id);
  return {id,link};
 }
 const next=p=>p.locator('[data-wizard-next="2"]');
 const back=p=>p.locator('[data-wizard-pane="2"] [data-wizard-back]');
 const pane=(p,n)=>p.locator('[data-wizard-pane="'+n+'"]');
 async function advanceWithoutSave(p){await next(p).click();await p.getByRole('button',{name:'Continue anyway',exact:true}).click();}
 try{
  runPhp(['tests/session-wizard-state.php','driver','file']);
  for(const size of sizes){
   const c=await create(page,size);page.on('pageerror',error=>errors.push(error.message));
   // Viewport-only capture and geometry precede any interaction with this receipt.
   await shot(page,'initial-'+size.width+'x'+size.height);
   await visibleActions(page,'[data-wizard-copy="link"],[data-wizard-email-toggle],[data-wizard-next="2"]','Wizard initial '+size.width);
   check('Single initial pane and no giant private text at '+size.width,await pane(page,1).isVisible()&&await pane(page,2).isHidden()&&await page.locator('#volunteer-private-link').isHidden()&&await page.locator('#orchestrator-handoff').isHidden());
   check('Next initially enabled '+size.width,await next(page).isEnabled());
   check('All four exact titles preserved '+size.width,JSON.stringify(await page.locator('.wizard-step-label').allTextContents())===JSON.stringify(title));
   if(size.width===1366)await visibleActions(page,'.wizard-overview li','Desktop complete four-step overview');
   check('No initial email disclosure '+size.width,await page.locator('[data-wizard-email]').isHidden());
   if(!captureOnly){
    const before=counts();await next(page).click();await page.locator('[data-wizard-confirm]').waitFor({state:'visible'});await shot(page,'continue-dialog-'+size.width);
    await visibleActions(page,'[data-wizard-confirm] button','Wizard confirmation '+size.width);
    check('Exact calm dialog copy '+size.width,await page.getByRole('heading',{name:'Continue without saving?',exact:true}).isVisible()&&await page.getByText("Are you sure you don't want to save the session link?",{exact:true}).isVisible()&&await page.getByText('Saving it helps you return to this session later.',{exact:true}).isVisible());
    await page.getByRole('button',{name:'Go back and save',exact:true}).click();
    check('Go back restores Next focus '+size.width,await next(page).evaluate(el=>el===document.activeElement));
    await next(page).click();await page.keyboard.press('Escape');check('Escape returns with useful focus '+size.width,await pane(page,1).isVisible()&&await next(page).evaluate(el=>el===document.activeElement));
    await next(page).click();await page.getByRole('button',{name:'Close',exact:true}).click();check('Close returns to step one '+size.width,await next(page).evaluate(el=>el===document.activeElement));
    await advanceWithoutSave(page);check('Continue anyway immediately opens step two '+size.width,await pane(page,2).isVisible());
    check('Navigation/dialog do not claim/renew or send '+size.width,JSON.stringify(counts())===JSON.stringify(before));
    await back(page).click();await next(page).click();check('Skip acknowledgement is transient and avoids repeated nag '+size.width,await pane(page,2).isVisible()&&await page.locator('[data-wizard-confirm]').isHidden());
    const expected=JSON.parse(runPhp(['tests/session-wizard-state.php','inspect'],JSON.stringify({id:c.id,secret:new URL(c.link).hash.slice(1)}),{APP_URL:base})).start_message.replaceAll('\r\n','\n');
    const actual=(await page.locator('#orchestrator-handoff').inputValue()).replaceAll('\r\n','\n');check('Wizard text equals canonical short start message '+size.width,actual===expected);
    check('Wizard retains accepted first-person launch intent and runtime permission boundary '+size.width,actual.startsWith('Please start my LLM.Giving volunteer session')&&actual.includes('does not bypass them'));
    await page.getByRole('button',{name:'Copy start message',exact:true}).click();await page.locator('[data-wizard-copy-status=instructions]').filter({hasText:'Start message copied'}).waitFor();
    check('Clipboard contains exact one-message authorization '+size.width,(await page.evaluate(()=>navigator.clipboard.readText())).replaceAll('\r\n','\n')===expected);
    await pane(page,2).locator('[data-wizard-next]').click();check('Step three sends one message and reads complete private guidance '+size.width,(await pane(page,3).innerText()).includes('new chat in Dot')&&(await pane(page,3).innerText()).includes('browser control enabled')&&(await pane(page,3).innerText()).includes('one copied start message')&&(await pane(page,3).innerText()).includes('Send'));
    await pane(page,3).locator('[data-wizard-next]').click();await shot(page,'dashboard-step-'+size.width);await visibleActions(page,'[data-wizard-dashboard]','Wizard dashboard action '+size.width);
    check('Final instructions distinguish Stop from stopping AI '+size.width,(await pane(page,4).innerText()).includes('Stop session')&&(await pane(page,4).innerText()).includes('cannot terminate'));
    await page.getByRole('link',{name:'Open dashboard',exact:true}).click();check('Open dashboard reaches recorded empty session '+size.width,new URL(page.url()).pathname==='/volunteer/'+c.id&&await page.getByText('No assignments picked up yet.',{exact:true}).isVisible());
    await page.getByRole('button',{name:'Stop session',exact:true}).click();check('Dashboard Stop retains normal revocation '+size.width,(await page.locator('body').innerText()).includes('Session stopped'));
   }
  }
  if(captureOnly){check('Capture-only run performs no receipt copy/email/pickup actions');return;}
  for(const size of sizes){await page.setViewportSize(size);await page.goto(base+'/volunteer/quick-start');await shot(page,'guide-initial-'+size.width+'x'+size.height);await visibleActions(page,'[data-guide-actions] a','Guide initial '+size.width);}
  const copied=await create(page);const beforeCopy=counts();await page.getByRole('button',{name:'Copy session link',exact:true}).click();await page.locator('[data-wizard-copy-status=link]').filter({hasText:'Session link copied'}).waitFor();
  check('Successful session-link clipboard write contains the exact existing private link',await page.evaluate(()=>navigator.clipboard.readText()).then(value=>value.replaceAll('\r\n','\n')===copied.link.replaceAll('\r\n','\n')));check('Copying existing link makes no server mutation',JSON.stringify(counts())===JSON.stringify(beforeCopy));
  await create(page);const baseline=counts();
  await page.evaluate(()=>Object.defineProperty(navigator.clipboard,'writeText',{configurable:true,value:async()=>{throw new DOMException('Synthetic clipboard denial','NotAllowedError');}}));
  await page.getByRole('button',{name:'Copy session link',exact:true}).click();await page.locator('[data-wizard-copy-status=link]').filter({hasText:'denied'}).waitFor();
  check('Failed copy opens complete private-link manual fallback',await page.locator('[data-wizard-link-manual]').getAttribute('open')!==null&&await next(page).isEnabled());
  await shot(page,'clipboard-denial');await next(page).click();check('Copy interaction permits Next despite failure',await pane(page,2).isVisible()&&await page.locator('[data-wizard-confirm]').isHidden());
  await page.getByRole('button',{name:'Copy start message',exact:true}).click();await page.locator('[data-wizard-copy-status=instructions]').filter({hasText:'denied'}).waitFor();
  check('Copy failure exposes short readonly fallback',await page.locator('[data-wizard-instructions-manual]').getAttribute('open')!==null&&(await page.locator('#orchestrator-handoff').inputValue()).startsWith('Please start my LLM.Giving volunteer session'));
  check('Clipboard failures cause no server changes',JSON.stringify(counts())===JSON.stringify(baseline));
  const emailSession=await create(page);await page.getByRole('button',{name:'Email it to me (optional)',exact:true}).click();
  check('Email option reveals labelled field without sending',await page.getByLabel('Your email address',{exact:true}).isVisible()&&await next(page).isEnabled());
  await next(page).click();check('Empty unsent optional email cannot intercept Next',await pane(page,2).isVisible());await back(page).click();
  await page.getByLabel('Your email address',{exact:true}).fill('invalid');await page.getByRole('button',{name:'Send my link',exact:true}).click();check('Invalid email never disables Next',await next(page).isEnabled());await next(page).click();check('Invalid email cannot intercept navigation',await pane(page,2).isVisible());await back(page).click();
  await page.getByLabel('Your email address',{exact:true}).fill('wizard-browser@example.invalid');
  runPhp(['tests/session-wizard-state.php','driver','disabled']);await page.getByRole('button',{name:'Send my link',exact:true}).click();await page.locator('[data-wizard-email-status]').filter({hasText:"couldn't send"}).waitFor();
  check('Failed transport is honest and Next remains free',await next(page).isEnabled()&&!(await page.locator('[data-wizard-email-status]').innerText()).includes('accepted'));await next(page).click();check('Transport failure does not gate progression',await pane(page,2).isVisible());await back(page).click();runPhp(['tests/session-wizard-state.php','driver','file']);
  let release;const gate=new Promise(resolve=>{release=resolve;});let requests=0;
  await ctx.route('**/email-link',async route=>{requests++;await gate;await route.continue();});
  const beforePending=counts();await page.getByRole('button',{name:'Send my link',exact:true}).click();await page.locator('[data-wizard-email-status]').filter({hasText:'Sending'}).waitFor();
  await page.locator('[data-wizard-email]').evaluate(form=>form.requestSubmit());check('Pending send disables only Send, never Next',await next(page).isEnabled()&&await page.getByRole('button',{name:'Send my link',exact:true}).isDisabled());
  await next(page).click();check('Pending send permits immediate step two',await pane(page,2).isVisible());release();await page.locator('[data-wizard-email-status]').filter({hasText:'Email accepted'}).waitFor({state:'attached'});await ctx.unroute('**/email-link');await back(page).click();
  check('Only one pending/double-click send reaches transport',requests===1&&counts().mail===beforePending.mail+1);check('Accepted transport names destination without promising inbox delivery',(await page.locator('[data-wizard-email-status]').innerText()).includes('wizard-browser@example.invalid')&&(await page.locator('[data-wizard-email-status]').innerText()).includes('not guaranteed')&&await next(page).isEnabled());
  await shot(page,'email-accepted');await next(page).click();check('Accepted send does not autoadvance or gate Next',await pane(page,2).isVisible());await back(page).click();
  // The failure and two transport-accepted explicit attempts consume the bounded allowance.
  await page.getByRole('button',{name:'Send my link',exact:true}).click();await page.locator('[data-wizard-email-status]').filter({hasText:'Email accepted'}).waitFor();await page.getByRole('button',{name:'Send my link',exact:true}).click();await page.locator('[data-wizard-email-status]').filter({hasText:'Too many attempts'}).waitFor();
  check('Rate limiting remains honest and does not disable Next',await next(page).isEnabled());await next(page).click();check('Rate-limited email permits navigation',await pane(page,2).isVisible());
  const post={csrf:await page.locator('[name=csrf]').inputValue(),session_secret:new URL(emailSession.link).hash.slice(1),email_attempt:'a'.repeat(32),email:'http-synthetic@example.invalid'};
  const beforeForged=counts();
  check('Email GET cannot send',(await page.request.get(base+'/volunteer/'+emailSession.id+'/email-link')).status()===405);
  check('Email rejects forged current capability',(await page.request.post(base+'/volunteer/'+emailSession.id+'/email-link',{form:{...post,session_secret:'0'.repeat(64)},headers:{Accept:'application/json'}})).status()===403);
  check('Email rejects forged CSRF',(await page.request.post(base+'/volunteer/'+emailSession.id+'/email-link',{form:{...post,csrf:'wrong'},headers:{Accept:'application/json'}})).status()===403);
  check('Email rejects recipient header injection',(await page.request.post(base+'/volunteer/'+emailSession.id+'/email-link',{form:{...post,email:'a@example.invalid\r\nBcc: b@example.invalid'},headers:{Accept:'application/json'}})).status()===422);
  check('Invalid/forged requests send nothing',JSON.stringify(counts())===JSON.stringify(beforeForged));
  const anonymous=await ctx.browser().newContext();try{const p=await anonymous.newPage();await p.goto(base+'/volunteer');const csrf=await p.locator('[name=csrf]').inputValue();check('Another anonymous browser cannot email a known private capability without its session authorization',(await p.request.post(base+'/volunteer/'+emailSession.id+'/email-link',{form:{...post,csrf},headers:{Accept:'application/json'}})).status()===403);}finally{await anonymous.close();}
  // CR49-2: real transport accepts, its response is lost, and accepted-state persistence is absent.
  const recovery=await create(page);await page.getByRole('button',{name:'Email it to me (optional)',exact:true}).click();await page.getByLabel('Your email address',{exact:true}).fill('uncertain-wizard@example.invalid');
  const recoveryAttempt=await page.locator('[name=email_attempt]').inputValue();const recoveryKeys=runPhp(['tests/session-wizard-state.php','email-attempt-snapshot']);const recoveryBefore=counts();
  await ctx.route('**/email-link',async route=>{const response=await route.fetch();assert.equal(response.status(),200);runPhp(['tests/session-wizard-state.php','acceptance-state-lost'],recoveryKeys);await route.abort('connectionreset');});
  await page.getByRole('button',{name:'Send my link',exact:true}).click();await page.locator('[data-wizard-email-status]').filter({hasText:'outcome is unknown'}).waitFor();await ctx.unroute('**/email-link');
  check('Uncertain accepted send retains original identity after lost response',await page.locator('[name=email_attempt]').inputValue()===recoveryAttempt&&counts().mail===recoveryBefore.mail+1);
  const recoveryMail=counts().mail;
  for(let retry=0;retry<2;retry++){await page.getByRole('button',{name:'Send my link',exact:true}).click();await page.locator('[data-wizard-email]').filter({has:page.locator('[data-wizard-email-status]').filter({hasText:'pending or unknown'})}).waitFor();await page.locator('[data-wizard-email] button[type=submit]:not([disabled])').waitFor({state:'visible'});check('Pending client recovery retains identity without another transport '+retry,await page.locator('[name=email_attempt]').inputValue()===recoveryAttempt&&counts().mail===recoveryMail&&await next(page).isEnabled());}
  const pendingPost={csrf:await page.locator('[name=csrf]').inputValue(),session_secret:new URL(recovery.link).hash.slice(1),email_attempt:recoveryAttempt,email:'uncertain-wizard@example.invalid'};
  const pendingResponse=await page.request.post(base+'/volunteer/'+recovery.id+'/email-link',{form:pendingPost,headers:{Accept:'application/json'}});const pendingJson=await pendingResponse.json();
  check('Pending JSON route reports explicit state and SAME recovery identity',pendingResponse.status()===409&&pendingJson.ok===false&&pendingJson.state==='pending'&&pendingJson.next_attempt===recoveryAttempt&&counts().mail===recoveryMail);
  await shot(page,'email-pending-recovery');await next(page).click();check('Unknown/pending recovery never gates Next',await pane(page,2).isVisible());
  // A confirmed failed attempt resolves uncertainty and permits a NEW explicit send, never an automatic retry.
  await create(page);await page.getByRole('button',{name:'Email it to me (optional)',exact:true}).click();await page.getByLabel('Your email address',{exact:true}).fill('resolved-failure@example.invalid');const failedAttempt=await page.locator('[name=email_attempt]').inputValue();const beforeFailureMail=counts().mail;
  runPhp(['tests/session-wizard-state.php','driver','disabled']);await ctx.route('**/email-link',async route=>{const response=await route.fetch();assert.equal(response.status(),503);await route.abort('connectionreset');});await page.getByRole('button',{name:'Send my link',exact:true}).click();await page.locator('[data-wizard-email-status]').filter({hasText:'outcome is unknown'}).waitFor();await ctx.unroute('**/email-link');
  check('Lost failed response retains identity before confirmed recovery',await page.locator('[name=email_attempt]').inputValue()===failedAttempt&&counts().mail===beforeFailureMail);
  await page.getByRole('button',{name:'Send my link',exact:true}).click();await page.locator('[data-wizard-email-status]').filter({hasText:'That send failed'}).waitFor();await page.locator('[data-wizard-email] button[type=submit]:not([disabled])').waitFor();
  check('Confirmed failure permits a fresh explicit attempt without delivery',await page.locator('[name=email_attempt]').inputValue()!==failedAttempt&&counts().mail===beforeFailureMail&&await next(page).isEnabled());runPhp(['tests/session-wizard-state.php','driver','file']);await page.getByRole('button',{name:'Send my link',exact:true}).click();await page.locator('[data-wizard-email-status]').filter({hasText:'Email accepted'}).waitFor();check('Only explicit new send after confirmed failure reaches transport',counts().mail===beforeFailureMail+1);
  // Accessible 200% text reflow: normal scrolling is permitted at enlarged text sizes.
  await create(page,sizes[2]);await page.evaluate(()=>{const fonts=[...document.querySelectorAll('body,body *')].filter(el=>el instanceof HTMLElement).map(el=>[el,parseFloat(getComputedStyle(el).fontSize)]);for(const[el,size]of fonts)if(Number.isFinite(size))el.style.fontSize=size*2+'px';});await shot(page,'200-percent-text');
  check('200% all-font reflow has no horizontal clipping',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await next(page).click();await page.getByRole('button',{name:'Continue anyway',exact:true}).click();check('200% text remains operable',await pane(page,2).isVisible());
  const native=await ctx.browser().newContext({javaScriptEnabled:false,viewport:sizes[2]});try{
   const np=await native.newPage();await create(np,sizes[2]);await shot(np,'no-javascript-initial');
   check('Native fallback retains the same one-message authorization without JS',(await np.locator('#orchestrator-handoff').inputValue()).startsWith('Please start my LLM.Giving volunteer session')&&(await np.locator('#orchestrator-handoff').inputValue()).length<=1200);
   await np.locator('.wizard-email-native-summary').click();await np.getByLabel('Your email address',{exact:true}).fill('native-wizard@example.invalid');const nativeAttempt=await np.locator('[name=email_attempt]').inputValue();const nativeKeys=runPhp(['tests/session-wizard-state.php','email-attempt-snapshot']);await np.getByRole('button',{name:'Send my link',exact:true}).click();
   check('No-JS native form performs real private sink send with honest acceptance',(await np.locator('[data-wizard-email-status]').innerText()).includes('Email accepted'));
   runPhp(['tests/session-wizard-state.php','acceptance-state-lost'],nativeKeys);await np.locator('[name=email_attempt]').evaluate((input,value)=>{input.value=value;},nativeAttempt);const nativeMail=counts().mail;
   for(let retry=0;retry<2;retry++){await np.getByRole('button',{name:'Send my link',exact:true}).click();await np.locator('[data-wizard-email][data-email-state=pending]').waitFor();check('Native pending retry retains identity and sends no additional message '+retry,await np.locator('[name=email_attempt]').inputValue()===nativeAttempt&&counts().mail===nativeMail);}
   await shot(np,'native-email-pending-recovery');
   await np.locator('[data-wizard-instructions-manual] summary').click();check('Native manual disclosure exposes one-message authorization',await np.locator('#orchestrator-handoff').isVisible()&&(await np.locator('#orchestrator-handoff').inputValue()).length<=1200);
   check('Native private route retains complete controller guidance',(await privateControllerInstructions(np,new URL(np.url()).pathname.split('/')[2])).includes('END PRIVATE SESSION SUPPLEMENT'));
   await np.getByRole('link',{name:'Open dashboard',exact:true}).click();await np.getByRole('button',{name:'Stop session',exact:true}).click();check('No-JS native dashboard/Stop remains usable',(await np.locator('body').innerText()).includes('Session stopped'));
  }finally{await native.close();}
  check('Wizard has no browser script exceptions',errors.length===0);assert.equal(errors.length,0,errors.join('\n'));
 }finally{
  runPhp(['tests/session-wizard-state.php','driver','file']);runPhp(['tests/session-wizard-state.php','cleanup'],JSON.stringify(sessions));
 }
}
