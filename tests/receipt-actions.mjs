import assert from 'node:assert/strict';
/** Exercise real copying and explicit save confirmation before leaving a private receipt. */
export async function saveManagementLink(page){
 const link=await page.locator('#management-link').inputValue();
 await page.getByRole('button',{name:'Copy management link',exact:true}).click();
 const dialog=page.getByRole('dialog',{name:'Have you saved your management link?',exact:true});
 await dialog.waitFor();
 await page.getByText('Your management link was copied. Paste it somewhere safe before confirming.',{exact:true}).waitFor();
 assert.ok(await page.evaluate(()=>navigator.clipboard.readText())===link,'Complete private management link copied');
 await dialog.getByLabel('I confirm I have copied and saved my management link.',{exact:true}).check();
 await dialog.getByRole('button',{name:'Confirm saved',exact:true}).click();
 await dialog.waitFor({state:'hidden'});
 return link;
}
