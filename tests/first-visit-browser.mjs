import fs from 'node:fs';
import path from 'node:path';
import { root } from '../tools/runtime.mjs';
import { setupSchema } from '../public/assets/request-setup.js';

export async function firstVisitQa({ page, ctx, base, runPhp, check, visibleActions, report }) {
  const sizes = [{width:1366,height:768},{width:390,height:844},{width:375,height:667}];
  const output = path.join(root, 'storage/qa/first-visit');
  fs.mkdirSync(output, {recursive:true});
  await ctx.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
  const fixture = JSON.parse(runPhp(['tests/first-visit-state.php','create']));
  const state = (command, id) => JSON.parse(runPhp(['tests/first-visit-state.php',command,id]));
  let nativeContext;

  async function capture(p, name) {
    const file = path.join(output, name + '.png');
    await p.screenshot({path:file, fullPage:false});
    report.screenshots.push(file);
  }
  async function initial(url, name, selector) {
    for (const size of sizes) {
      await page.setViewportSize(size);
      await page.goto(base + url);
      await page.evaluate(async()=>{await document.fonts.ready;await document.querySelector('.giving-art img')?.decode();await Promise.all(document.querySelector('main').getAnimations({subtree:true}).filter(anim=>anim.effect.getTiming().iterations!==Infinity).map(anim=>anim.finished.catch(()=>{})));});
      await capture(page, name+'-'+size.width+'x'+size.height);
      await visibleActions(page, selector, name+' '+size.width);
    }
    if (process.env.QA_FIRST_VISIT_INSPECT === '1') {
      console.log('Inspect initial '+name+': '+report.screenshots.slice(-3).join(' | '));
      await new Promise(resolve => {process.stdin.resume();process.stdin.once('data',()=>{process.stdin.pause();resolve();});});
    }
  }
  async function enlarged(url, name) {
    await page.setViewportSize(sizes[2]);
    await page.goto(base+url);
    await page.evaluate(() => {
      const fonts = [...document.querySelectorAll('body,body *')].map(el => [el,parseFloat(getComputedStyle(el).fontSize)]);
      for (const [el,size] of fonts) if (Number.isFinite(size)) el.style.fontSize = size*2+'px';
    });
    await capture(page,name+'-200');
    check(name+' reflows at 200% text',await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth+1));
  }
  async function authorize(url, preset, tasks, minutes) {
    await page.goto(base+url);
    const chosen=page.locator('.setup-time-card').filter({has:page.locator('[name=session_preset]:checked')});
    check('Visible commitment for '+preset,await chosen.isVisible()&&(await chosen.innerText()).includes(preset));
    check('Capability-based setup omits model and reasoning selectors',await page.locator('select[name=model],select[name=reasoning]').count()===0);
    await page.locator('[data-setup-submit]').click();
    check(preset+' review leaves authorization and consent unchecked',await page.locator('[data-volunteer-consent]').isVisible()&&!await page.locator('[name=authorize]').isChecked()&&!await page.locator('[name=terms]').isChecked());
    for (const field of ['authorize','authorized_resources','terms']) await page.locator('[name='+field+']').check();
    await page.getByRole('button',{name:'Authorize session',exact:true}).press('Enter');
    await page.locator('#volunteer-private-link').waitFor({state:'attached'});
    const id = new URL(await page.locator('#volunteer-private-link').inputValue()).pathname.split('/')[2];
    const stored = state('session',id);
    check(preset+' persists expected minutes and task allowance',stored.minutes===minutes&&stored.tasks===tasks);
  }
  async function submit(p, title, question, fromAI) {
    await p.goto(base+'/submit');
    if (fromAI) {
      await p.getByRole('button',{name:'Help me draft with AI',exact:true}).click();
      const draft = {...setupSchema(), title, question, category:fixture.categoryName};
      await p.locator('#paste-setup-prompt').fill(JSON.stringify(draft));
      await p.locator('#fill-request-fields').click();
      check('AI draft fills exact public fields',await p.locator('[name=question]').inputValue()===question&&await p.locator('[name=title]').inputValue()===title);
    } else {
      await p.getByLabel('Research question',{exact:false}).fill(question);
      await p.getByLabel('Research title',{exact:false}).fill(title);
      await p.locator('[name=category_id]').selectOption(String(fixture.category));
    }
    await p.locator('[data-request-next-step]>summary').press('Enter');
    const terms = p.locator('[name=terms]');
    const displayed = await terms.locator('..').innerText();
    check('Consent is unchecked and conditional',!await terms.isChecked()&&displayed.includes('If I used AI to prepare it'));
    // A normal route request with its native CSRF token verifies the server independently of form validation.
    const csrf = await p.locator('[name=csrf]').inputValue();
    const invalid = await p.request.post(base+'/submit',{form:{csrf,title,question,category_id:fixture.category}});
    check('Server rejects missing '+(fromAI?'AI-assisted':'manual')+' publication consent',invalid.status()===422);
    if (fromAI) {
      await p.getByRole('button',{name:'Review your request',exact:true}).click();
      check('Human review shows imported question',await p.locator('#request-preview').innerText().then(text=>text.includes(question)));
      await p.getByRole('button',{name:'Back to editing',exact:true}).click();
    }
    await terms.check();
    await p.locator('.request-submit-actions').getByRole('button',{name:'Submit for approval',exact:true}).press('Enter');
    await p.locator('#management-link').waitFor();
    const id = new URL(await p.locator('#management-link').inputValue()).pathname.split('/')[2];
    const recorded = state('acceptance',id);
    check('Request stays pending and records displayed acceptance',recorded.moderation==='Pending Approval'&&recorded.visibility==='private'&&recorded.version===recorded.acceptance.terms_version&&displayed.includes(recorded.acceptance.confirmation));
  }

  try {
    const first = '.library-first-result .research-card h3 a';
    await initial('/research','catalogue','#research-query,.search-submit,'+first);
    check('No solved action when only excluded studies are solved',await page.getByRole('link',{name:'Explore solved research',exact:true}).count()===0);
    check('Flagged synthetic study excluded from discovery totals',await page.locator('.library-resultbar [role=status]').innerText()==='2 matching research requests'&&await page.locator('[data-research-id="'+fixture.hidden+'"]').count()===0);
    check('Unlisted synthetic study direct link works',(await page.request.get(base+'/research/'+fixture.hidden)).status()===200);
    check('First long title is shown before closed Filters',await page.locator(first).innerText()===fixture.title&&await page.locator('.library-advanced').getAttribute('open')===null&&await page.locator(first).evaluate(el=>Boolean(el.compareDocumentPosition(document.querySelector('.library-advanced'))&Node.DOCUMENT_POSITION_FOLLOWING)));
    await page.locator(first).click();
    check('First matching question opens its real study',new URL(page.url()).pathname===fixture.study);
    await initial('/','homepage','.giving-copy .primary-actions a');
    await page.getByRole('link',{name:'Find research',exact:true}).click();
    check('Homepage Find research opens the catalogue at the top',new URL(page.url()).pathname==='/research'&&await page.evaluate(()=>scrollY===0));
    await visibleActions(page,'#research-query,.search-submit,'+first,'Homepage actual catalogue navigation');
    await page.locator('#research-query').fill('Nonexistent first visit query');
    await page.getByRole('button',{name:'Search research',exact:true}).press('Enter');
    check('Search shows a real empty state',await page.getByText('No matching research requests.',{exact:true}).isVisible());
    await page.locator('.library-resultbar').getByRole('link',{name:'Reset filters'}).click();
    check('Reset restores results',await page.locator(first).innerText()===fixture.title);
    await page.getByText('Filters',{exact:true}).click();
    await page.getByLabel('Sort by',{exact:true}).selectOption('title');
    await page.waitForURL(/sort=title/);
    check('Sort persists and reorders results',await page.locator('[name=sort]').inputValue()==='title'&&await page.locator(first).innerText()===fixture.otherTitle);
    runPhp(['tests/first-visit-state.php','solve',fixture.withFindings]);
    await page.goto(base+'/research');
    await page.getByRole('link',{name:'Explore solved research',exact:true}).click();
    check('Solved link leads to a discoverable solved study',await page.locator('.research-card').count()===1&&await page.locator('.research-card').getAttribute('data-research-id')===fixture.withFindings);
    await enlarged('/research','catalogue');

    await initial(fixture.study,'study-plan','.research-heading .primary-actions a');
    check('Study without findings promotes its actual planning task',await page.locator('.research-heading .primary-actions a').first().innerText()==='Help plan this study');
    await page.getByRole('link',{name:'Help plan this study',exact:true}).click();
    check('Planning CTA opens the actual task',new URL(page.url()).pathname.includes('/tasks/'));
    runPhp(['tests/first-visit-state.php','pause',fixture.id]);
    await page.goto(base+fixture.study);
    await page.getByRole('link',{name:'Read the brief',exact:true}).click();
    check('No-task study leads to its open brief',new URL(page.url()).hash==='#question'&&await page.locator('#question').getAttribute('open')!==null);
    await initial('/research/'+fixture.withFindings,'study-findings','.research-heading .primary-actions a');
    await page.locator('.research-heading .primary-actions a').first().click();
    check('Findings CTA opens a reader with public items',new URL(page.url()).pathname.endsWith('/findings')&&await page.locator('.reader-finding').count()>0);
    await enlarged('/research/'+fixture.withFindings,'study');

    await initial('/volunteer','volunteer','[data-volunteer-advanced]>summary,[data-setup-submit]');
    check('Handoff is explained before authorization',await page.locator('.volunteer-subtitle').innerText()==='You’ll need an AI tool with browser control. We’ll give you one message to paste into it.');
    await enlarged('/volunteer','volunteer');
    runPhp(['tests/first-visit-state.php','resume',fixture.id]);
    await page.setViewportSize(sizes[0]);
    await authorize('/volunteer','Quick try',1,15);
    await authorize('/volunteer?session_preset=standard','Standard',3,60);

    await initial('/submit','submit','#copy-setup-prompt,[name=question],[name=title],[name=category_id],[data-request-next-step]>summary');
    await enlarged('/submit','submit');
    await page.setViewportSize(sizes[0]);
    nativeContext = await ctx.browser().newContext({javaScriptEnabled:false,viewport:sizes[0]});
    await submit(await nativeContext.newPage(),'Manual visitor fixture','How could a community group compare participation in small library activities using public records?',false);
    await submit(page,'AI-assisted visitor fixture','How might a community group compare the practical cost of two public library activity formats?',true);
  } finally {
    await nativeContext?.close();
  }
}
