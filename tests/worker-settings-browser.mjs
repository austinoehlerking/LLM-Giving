import { connectVolunteer } from './session-wizard-actions.mjs';
export async function workerSettingsQa({page,base,runPhp,check,report}) {
 const projects=[];
 try {
  const work=JSON.parse(runPhp(['tests/volunteer-real-start-state.php','create','work']));
  projects.push(work.id);
  for(const profile of ['general','restricted_web_v1']) {
   await page.goto(base+'/volunteer?model=retired-model&reasoning=provider-adaptive');
   await page.locator('[data-volunteer-advanced]>summary').click();
   await page.locator('#volunteer-capabilities').selectOption(profile);
   await connectVolunteer(page);
   check(profile+' reaches actual authorization without worker settings',await page.locator('[data-volunteer-consent] [name=session_profile]').inputValue()===profile&&await page.locator('[name=model],[name=reasoning]').count()===0);
   check(profile+' leaves authorization and terms unchecked',await page.locator('[data-volunteer-consent]>.check input').evaluateAll(list=>list.length===3&&list.every(input=>!input.checked)));
  }
  report.scope='Capability-only startup ignores obsolete model fields from legacy setup links without authorizing anything.';
 } finally{runPhp(['tests/volunteer-real-start-state.php','cleanup'],JSON.stringify({projects,sessions:[]}));}
}
