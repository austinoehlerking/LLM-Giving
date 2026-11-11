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

/** Progress through the enhanced AI step or the native combined form before server review. */
export async function openVolunteerTime(page) {const advance=page.locator('[data-ai-continue]');if(await advance.isVisible())await advance.click();}
export async function connectVolunteer(page) {
 await openVolunteerTime(page);
 const next=page.locator('[data-setup-submit]');
 if(await next.count())await next.click();else await page.getByRole('button',{name:'Connect my AI',exact:true}).click();
}
export async function chooseVolunteerWorker(page,preset,effort='max') {
 const models={astra_max:'gpt-6-astra',sol_max:'gpt-6.1-sol',opus_max:'claude-opus-5-5',fable_max:'claude-fable-5-1'};
 const model=page.locator('[data-volunteer-setup] [name=model]');
 if(await model.count()){if(!await model.isVisible()&&await page.locator('[data-ai-change]').isVisible())await page.locator('[data-ai-change]').click();await model.selectOption(models[preset]||preset);await page.locator('[data-volunteer-setup] [name=reasoning]').selectOption(effort);}
 else {await page.locator('[name=worker_choice]').selectOption(preset);const reasoning=page.locator('[name=worker_reasoning]');if(await reasoning.isEnabled())await reasoning.selectOption(effort);}
}
