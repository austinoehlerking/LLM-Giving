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
