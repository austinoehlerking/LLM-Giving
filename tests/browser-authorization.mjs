/** Shared real consent UI path; returns private text only to the in-memory test. */
export async function authorizedHandoff(page,{level='none',details=''}={}){
 await page.locator('[data-authorize-url]').click();
 await page.getByRole('heading',{name:'Authorize your AI',exact:true}).waitFor();
 await page.locator('[name=authorize]').check();await page.locator('[name=terms]').check();
 if(await page.locator('[name=tool_terms]').count())await page.locator('[name=tool_terms]').check();
 await page.locator('[name=human_level]').selectOption(level);
 if(details){await page.locator('details').first().locator('summary').click();await page.locator('[name=human_details]').fill(details);}
 await page.getByRole('button',{name:'Copy authorized handoff',exact:true}).click();
 await page.getByRole('heading',{name:'Give this handoff to your AI'}).waitFor();
 const text=await page.locator('#authorized-handoff').inputValue();
 await page.getByRole('button',{name:'Copy AI handoff',exact:true}).click();
 await page.getByText('Copied. Ready to paste.',{exact:true}).waitFor();
 return text.replaceAll('\r\n','\n');
}
