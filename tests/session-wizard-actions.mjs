/** Exercise the real receipt wizard when another workflow needs its dashboard. */
export async function readyInstructions(page) {
 if (await page.locator('[data-volunteer-wizard]').count()) {
  await page.locator('[data-wizard-next="2"]').click();
  if (await page.locator('[data-wizard-confirm]').isVisible()) await page.getByRole('button',{name:'Continue anyway',exact:true}).click();
 }
}
export async function readyDashboard(page) {
 if (await page.locator('[data-volunteer-wizard]').count()) {
  if (await page.locator('[data-volunteer-wizard]').evaluate(el=>el.classList.contains('is-enhanced'))) {
   await readyInstructions(page);
   await page.locator('[data-wizard-pane="2"] [data-wizard-next]').click();
   await page.locator('[data-wizard-pane="3"] [data-wizard-next]').click();
  }
  await page.getByRole('link',{name:'Open dashboard',exact:true}).click();
 } else await page.getByRole('link',{name:'Open private dashboard',exact:true}).click();
}

/** Read the complete guidance from the normal authenticated page, without a second paste. */
export async function privateControllerInstructions(page,id) {
 const response=await page.request.get(new URL('/volunteer/'+id+'/instructions',page.url()).href);
 if(response.status()!==200)throw Error('Private instruction response '+response.status());
 const html=await response.text();
 return page.evaluate(html=>new DOMParser().parseFromString(html,'text/html').querySelector('#session-ai-instructions').value,html);
}

/** A human must explicitly choose a requested worker before reviewing authorization. */
export async function connectVolunteer(page) {
 const picker=page.locator('[name=worker_choice]');
 if(await picker.count() && !(await picker.inputValue()))await picker.selectOption('astra_max');
 await page.getByRole('button',{name:'Connect my AI',exact:true}).click();
}
