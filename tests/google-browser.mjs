import fs from 'node:fs';
import path from 'node:path';
import {root} from '../tools/runtime.mjs';

export async function googleQa({page,ctx,base,check,shot,visibleActions}) {
  let loads = 0;
  await ctx.route('https://www.googletagmanager.com/**', route => {
    loads++;
    return route.fulfill({contentType:'text/javascript',body:'/* synthetic Google library; no real measurement */'});
  });
  await ctx.route('https://**google-analytics.com/**', route => route.abort());
  await ctx.clearCookies();

  for (const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]) {
    await page.setViewportSize(size);
    const before = loads;
    await page.goto(base+'/');
    await page.locator('.giving-art img').evaluate(image => image.decode());
    await page.waitForFunction(() => !!document.querySelector('script[src^="https://www.googletagmanager.com/"]'));
    await page.waitForLoadState('networkidle');
    check('Analytics starts automatically '+size.width, loads === before + 1);
    check('No analytics opt-in controls '+size.width, await page.locator('[data-analytics-choice],[data-analytics-allow],[data-analytics-deny]').count() === 0);
    check('Illustrative usage text removed '+size.width, !(await page.locator('body').innerText()).includes('Illustrative usage'));
    check('Initial scroll position '+size.width, await page.evaluate(() => scrollY === 0));
    await shot(page,'google-first-viewport-'+size.width);
    await visibleActions(page,'header a,.hero .primary-actions > *','Automatic Analytics home '+size.width);
    await page.locator('.hero').getByRole('link',{name:'Submit Research Request',exact:true}).click();
    check('Primary action opens submission form '+size.width,new URL(page.url()).pathname === '/submit');
  }

  await page.setViewportSize({width:1366,height:768});
  const before = loads;
  const response = await page.goto(base+'/?q=private-query#management-secret');
  await page.waitForLoadState('networkidle');
  const html = await response.text();
  check('Static Search Console tag retained',html.includes('synthetic_verification_token_123456'));
  check('Versioned local loader replaces cached consent script',html.includes('/assets/analytics.js?v=2'));
  check('Google origins limited by public CSP',(await response.headerValue('content-security-policy')).includes('https://www.googletagmanager.com'));
  check('One automatic tag per public page',loads === before + 1);
  const commands = await page.evaluate(() => window.dataLayer.map(command => Array.from(command)));
  const config = commands.find(command => command[0] === 'config');
  check('Synthetic measurement ID configured',config?.[1] === 'G-TEST123456');
  check('Query and fragment excluded',config?.[2].page_location === base+'/');
  check('Referrer excluded',config?.[2].page_referrer === '');
  check('Advertising features disabled',config?.[2].allow_google_signals === false && config?.[2].allow_ad_personalization_signals === false);
  check('No new localStorage choice written',await page.evaluate(() => localStorage.getItem('llmgiving.analytics.v1') === null));

  for (const route of ['/submit','/recover','/admin/login','/manage/'+'a'.repeat(32),'/missing-page']) {
    const before = loads;
    const result = await page.goto(base+route);
    check('No Analytics on private/form/error route '+route,await page.locator('[data-google-analytics],[data-analytics-choice]').count() === 0);
    check('No Google script request '+route,loads === before);
    check('Private CSP excludes Google '+route,!(await result.headerValue('content-security-policy')).includes('googletagmanager'));
  }
  const privacy = await page.goto(base+'/legal/privacy');
  const privacyHtml = await privacy.text();
  check('Privacy notice describes automatic collection',privacyHtml.includes('starts automatically without an on-page opt-in prompt'));
  check('Privacy notice has no optional Analytics heading',!privacyHtml.includes('Optional Google Analytics'));
  await shot(page,'google-privacy-disclosure');

  for (const scenario of [{name:'Global Privacy Control',gpc:true},{name:'Do Not Track',dnt:true},{name:'Previous opt-out',declined:true}]) {
    const protectedContext = await ctx.browser().newContext();
    try {
      await protectedContext.addInitScript(options => {
        if (options.gpc) Object.defineProperty(navigator,'globalPrivacyControl',{value:true});
        if (options.dnt) Object.defineProperty(navigator,'doNotTrack',{value:'1'});
        if (options.declined) { try { localStorage.setItem('llmgiving.analytics.v1','denied'); } catch {} }
      },scenario);
      await protectedContext.addCookies([{name:'_ga',value:'synthetic',url:base},{name:'_ga_TEST123456',value:'synthetic',url:base}]);
      let protectedLoads = 0;
      await protectedContext.route('https://www.googletagmanager.com/**', route => {protectedLoads++;return route.abort();});
      await protectedContext.route('https://**google-analytics.com/**', route => route.abort());
      const protectedPage = await protectedContext.newPage();
      await protectedPage.goto(base+'/');
      await protectedPage.waitForLoadState('networkidle');
      check(scenario.name+' suppresses Analytics',protectedLoads === 0 && await protectedPage.evaluate(() => window['ga-disable-G-TEST123456'] === true));
      check(scenario.name+' removes Analytics cookies',!(await protectedContext.cookies()).some(cookie => cookie.name === '_ga' || cookie.name.startsWith('_ga_')));
      check(scenario.name+' has no opt-in UI',await protectedPage.locator('[data-analytics-choice]').count() === 0);
    } finally { await protectedContext.close(); }
  }

  const restrictedStorage = await ctx.browser().newContext();
  try {
    await restrictedStorage.addInitScript(() => { Storage.prototype.getItem = () => { throw new Error('Synthetic unavailable storage'); }; });
    await restrictedStorage.route('https://www.googletagmanager.com/**',route=>route.fulfill({contentType:'text/javascript',body:'/* synthetic Google library */'}));
    await restrictedStorage.route('https://**google-analytics.com/**',route=>route.abort());
    const storagePage = await restrictedStorage.newPage();
    await storagePage.goto(base+'/');
    check('Unavailable storage does not break startup',await storagePage.evaluate(() => typeof window.gtag === 'function'));
  } finally { await restrictedStorage.close(); }

  await page.setViewportSize({width:683,height:384});
  await page.goto(base+'/');
  await page.evaluate(() => document.body.style.fontSize = '200%');
  await shot(page,'google-enlarged-text');
  check('Public page reflows at enlarged text',await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth+1));
  fs.writeFileSync(path.join(root,'storage/qa/google-checks.txt'),'Automatic Analytics startup checked with synthetic IDs and intercepted requests.\n');
}