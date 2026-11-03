import fs from 'node:fs';import path from 'node:path';import {root,env} from '../tools/runtime.mjs';
export async function contactQa({browser,base,runPhp,check,visibleActions,report}) {
 const out=path.join(root,'storage/qa/contact');fs.mkdirSync(out,{recursive:true});
 const ctx=await browser.newContext({javaScriptEnabled:false,viewport:{width:1366,height:768}});
 const page=await ctx.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const state=(...args)=>JSON.parse(runPhp(['tests/contact-state.php',...args]));
 const configured=state('configure');check('Effective private file mail configuration',configured.driver==='file'&&configured.operator.email==='operator@example.invalid'&&configured.from==='site@example.invalid');
 async function shot(name){const file=path.join(out,name+'.png');await page.screenshot({path:file,fullPage:false});report.screenshots.push(file);}
 async function safeSubmit(expected='file'){
  const actual=state('inspect');check('Effective synthetic transport before submission '+report.checks.length,actual.driver===expected&&actual.operator.email.endsWith('@example.invalid')&&actual.from==='site@example.invalid');
 }
 try {
  if(env.QA_CONTACT_STAGE!=='interactions') {
  for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667},{width:1487,height:1058}]) {
   await page.setViewportSize(size);const response=await page.goto(base+'/contact');check('Contact page identity '+size.width,response.status()===200&&await page.locator('h1').innerText()==='Contact LLM.Giving');
   await shot('initial-'+size.width+'x'+size.height);
   check('At scroll zero before interaction '+size.width,await page.evaluate(()=>scrollY===0));
   await visibleActions(page,'.contact-form input:not([type=hidden]),.contact-form textarea,.contact-form button','Contact '+size.width);
   check('Complete shared navigation stays available '+size.width,await page.getByRole('navigation',{name:'Main navigation',exact:true}).getByRole('link').count()===3);
   check('Input fonts readable '+size.width,await page.locator('.contact-form input:not([type=hidden]),.contact-form textarea').evaluateAll(els=>els.every(el=>parseFloat(getComputedStyle(el).fontSize)>=16)));
   check('No added warning/helper '+size.width,!(await page.locator('.contact').innerText()).match(/password|private link|private information|response time/i)&&await page.locator('.contact-form textarea + button').count()===1);
  }
  const copy=await page.locator('.contact').innerText();report.contactCopy=copy;
  const allowed=['Contact LLM.Giving','Questions, feedback, or something to report? Send a message.','Your name (optional)','Your email','Message','Send message','Prefer email? operator@example.invalid','Synthetic operator · Test country','General questions, privacy, copyright, and moderation.'];
  check('Allowed contact copy exact',copy.split('\n').map(s=>s.trim()).filter(Boolean).join('\n')===allowed.join('\n'));
  for(const size of [{width:1366,height:768},{width:375,height:667}]) {
   await page.setViewportSize(size);await page.goto(base+'/contact');
   await page.evaluate(()=>{const fonts=[...document.querySelectorAll('body,body *')].filter(el=>el instanceof HTMLElement).map(el=>[el,parseFloat(getComputedStyle(el).fontSize)]);for(const [el,size]of fonts)el.style.fontSize=(size*2)+'px';});
   await shot('text-200-'+size.width);
   check('200% text reflows without horizontal clipping '+size.width,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   check('200% controls retain readable content '+size.width,await page.locator('.contact-form input:not([type=hidden]),.contact-form textarea,.contact-form button').evaluateAll(els=>els.every(el=>el.clientWidth>0&&el.scrollWidth<=el.clientWidth+2)));
  }
  }
  if(env.QA_CONTACT_STAGE==='viewports')return;
  await page.setViewportSize({width:375,height:667});await page.goto(base+'/contact');
  const csrf=await page.locator('[name=csrf]').inputValue(),attempt=await page.locator('[name=contact_attempt]').inputValue();
  const original={csrf,contact_attempt:attempt,name:'Synthetic <visitor>',email:'visitor@example.invalid',message:'Synthetic no-JS browser message — café.'};
  await page.getByLabel('Your name (optional)').fill(original.name);await page.getByLabel('Your email',{exact:true}).fill(original.email);await page.getByLabel('Message',{exact:true}).fill(original.message);
  const before=state('inspect').count;await safeSubmit();await page.getByRole('button',{name:'Send message',exact:true}).click();
  check('Native no-JS POST redirects to clean contact route',new URL(page.url()).pathname==='/contact'&&new URL(page.url()).search==='');
  check('Truthful accepted success',await page.getByRole('status').filter({hasText:'Thanks — your message has been sent.'}).count()===1);
  await shot('native-success');
  const accepted=state('inspect',attempt);check('Actual file acceptance and Reply-To',accepted.count===before+1&&accepted.last.to==='operator@example.invalid'&&accepted.last.headers['Reply-To']===original.email&&accepted.last.body.includes(original.message));
  await page.reload();check('PRG refresh never resends',state('inspect').count===before+1);
  await safeSubmit();const duplicate=await ctx.request.post(base+'/contact',{form:original,maxRedirects:0});check('Repeated native attempt redirects without resend',duplicate.status()===303&&state('inspect').count===before+1);
  state('configure','changed@example.invalid');await page.goto(base+'/contact');check('Operator settings immediately update email link',await page.getByRole('link',{name:'changed@example.invalid',exact:true}).getAttribute('href')==='mailto:changed@example.invalid');
  await page.getByLabel('Your email',{exact:true}).fill('second@example.invalid');await page.getByLabel('Message',{exact:true}).fill('Synthetic message after operator change.');
  const changedAttempt=await page.locator('[name=contact_attempt]').inputValue();await safeSubmit();await page.getByRole('button',{name:'Send message',exact:true}).click();check('Changed link and real recipient stay synchronized',state('inspect',changedAttempt).last.to==='changed@example.invalid');
  await page.goto(base+'/contact');const invalid={csrf:await page.locator('[name=csrf]').inputValue(),contact_attempt:await page.locator('[name=contact_attempt]').inputValue(),name:'<script>synthetic</script>',email:'invalid',message:'Preserve <strong>literal</strong> body.'};
  await page.getByLabel('Your name (optional)').fill(invalid.name);await page.getByLabel('Your email',{exact:true}).fill(invalid.email);await page.getByLabel('Message',{exact:true}).fill(invalid.message);
  await page.locator('.contact-form').evaluate(form=>{form.noValidate=true;});await safeSubmit();
  const [validation]=await Promise.all([page.waitForResponse(response=>response.url()===base+'/contact'&&response.request().method()==='POST'),page.getByRole('button',{name:'Send message',exact:true}).click()]);
  check('Server validation returns associated inline errors and escaped fields',validation.status()===422&&(await page.content()).includes('aria-describedby="contact-email-error"')&&(await page.content()).includes('&lt;strong&gt;literal&lt;/strong&gt;'));
  await shot('validation-error');
  state('configure','changed@example.invalid','disabled');await page.goto(base+'/contact');await page.getByLabel('Your email',{exact:true}).fill('failure@example.invalid');await page.getByLabel('Message',{exact:true}).fill('Synthetic transport failure retains input.');
  const failureBefore=state('inspect').count;await safeSubmit('disabled');await page.getByRole('button',{name:'Send message',exact:true}).click();await shot('transport-error');
  check('Transport failure preserves fields and direct alternative',await page.getByLabel('Message',{exact:true}).inputValue()==='Synthetic transport failure retains input.'&&await page.getByRole('alert').innerText().then(t=>t.includes("We couldn't send your message."))&&await page.getByRole('link',{name:'changed@example.invalid',exact:true}).count()===1&&state('inspect').count===failureBefore);
  check('No false success after failure',!await page.getByText('Thanks — your message has been sent.',{exact:true}).count());
  check('No browser script errors',errors.length===0);
 } finally {state('configure');await ctx.close();}
}
