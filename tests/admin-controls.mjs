/** Reveal existing native disclosures through their real controls before using a nested form. */
export async function revealAdminControls(target) {
 const ancestors=target.locator('xpath=ancestor::details');
 for(let i=0;i<await ancestors.count();i++){const d=ancestors.nth(i);if(await d.getAttribute('open')===null)await d.locator(':scope > summary').click();}
}
