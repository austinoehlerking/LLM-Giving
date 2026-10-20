export async function libraryQa({page,ctx,base,runPhp,check,shot}){
 const fixture=JSON.parse(runPhp(['tests/library-fixtures.php']));const url=base+'/research?tag='+fixture.tag;
 const ids=()=>page.locator('.research-card').evaluateAll(es=>es.map(e=>e.dataset.researchId));
 const select=async(name,value)=>{await Promise.all([page.waitForNavigation(),page.locator('[name="'+name+'"]').selectOption(value)]);};
 await page.setViewportSize({width:1366,height:768});await page.goto(url);
 check('Library default newest and pagination',(await ids()).length===12&&(await ids())[0]===fixture.ids[13]);
 await Promise.all([page.waitForNavigation(),page.getByRole('link',{name:'2',exact:true}).click()]);
 check('Pagination preserves tag and displays remaining requests',new URL(page.url()).searchParams.get('tag')===fixture.tag&&(await ids()).length===2);
 await select('sort','most');check('Sort resets page and orders by approved contributions',(await ids())[0]===fixture.ids[0]&&!new URL(page.url()).searchParams.has('page'));
 await select('sort','fewest');check('Fewest contributions sort',(await ids())[0]===fixture.ids[13]);
 await select('sort','title');check('Title sorting',(await ids())[0]===fixture.ids[0]);
 await select('status','needs-review');check('Review status filter composes with tag',(await ids()).length===1&&(await ids())[0]===fixture.ids[0]);
 await Promise.all([page.waitForNavigation(),page.locator('[name=no_contributions]').check()]);
 check('Combined zero-contribution filter empty state',await page.getByRole('heading',{name:'No matching research requests.'}).isVisible());
 await Promise.all([page.waitForNavigation(),page.locator('.library-resultbar').getByRole('link',{name:'Reset filters'}).click()]);
 check('Reset clears filters',!new URL(page.url()).search);
 await page.goto(url);await select('external_tools','free');check('Free tools filter excludes licensed requests',(await ids()).length===12&&!(await ids()).includes(fixture.ids[13]));
 await Promise.all([page.waitForNavigation(),page.getByRole('link',{name:'2',exact:true}).click()]);check('Tool access persists through pagination',new URL(page.url()).searchParams.get('external_tools')==='free'&&(await ids()).length===1);
 await select('external_tools','licensed');check('Licensed filter resets pagination and returns the paid fixture',(await ids()).length===1&&(await ids())[0]===fixture.ids[13]&&!new URL(page.url()).searchParams.has('page'));
 for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667}]){await page.setViewportSize(size);await page.goto(url+'&external_tools=free');await shot(page,'external-filter-'+size.width);check('Tool filter visible and unobscured before interaction '+size.width,await page.locator('[name=external_tools]').evaluate(el=>{const r=el.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight&&r.right<=innerWidth&&r.height>=44&&document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)===el;}));await select('external_tools','licensed');check('Tool filter works at '+size.width,(await ids()).length===1&&(await ids())[0]===fixture.ids[13]);}
 await page.setViewportSize({width:683,height:384});await page.goto(url+'&external_tools=free');await page.evaluate(()=>document.body.style.fontSize='200%');await shot(page,'external-filter-enlarged');check('Tool filter enlarged-text reflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 await page.setViewportSize({width:1366,height:768});await page.goto(url);await page.getByLabel('Search research requests',{exact:true}).fill('01');
 await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Search research',exact:true}).click()]);
 check('Search submits real filtered result',(await ids()).length===1&&(await ids())[0]===fixture.ids[1]);
 await page.goto(url);await select('sort','fewest');
 const links=page.locator('.library-categories nav a');let target;
 for(const a of await links.all()){if(new URL(await a.getAttribute('href'),base).searchParams.get('category')===fixture.category){target=a;break;}}
 await Promise.all([page.waitForNavigation(),target.click()]);
 check('Category links preserve tag and sorting',new URL(page.url()).searchParams.get('tag')===fixture.tag&&new URL(page.url()).searchParams.get('sort')==='fewest');
 await page.setViewportSize({width:375,height:667});await page.goto(url);await select('category',fixture.category);
 check('Mobile category selection uses real data',new URL(page.url()).searchParams.get('category')===fixture.category&&!((await ids()).includes(fixture.ids[13])));
 await shot(page,'library-mobile-filtered');
 const plain=await ctx.browser().newContext({javaScriptEnabled:false,viewport:{width:1366,height:768}});const p=await plain.newPage();
 await p.goto(url);await p.locator('[name=external_tools]').selectOption('free');await p.locator('[name=sort]').selectOption('most');await p.locator('[name=status]').selectOption('needs-review');await p.getByRole('button',{name:'Search research',exact:true}).click();
 check('No-JavaScript filtering and sorting work',await p.locator('.research-card').count()===1&&await p.locator('.research-card').getAttribute('data-research-id')===fixture.ids[0]);await plain.close();
 await page.setViewportSize({width:1366,height:768});await page.goto(base+'/');
 check('Homepage explains unused subscription capacity and Folding@home',(await page.locator('.library-hero').innerText()).includes('unused AI subscription')&&(await page.locator('.mission').innerText()).includes('Folding@home'));
 check('Brand capitalization and dark stylesheet',await page.locator('.site-header .brand').innerText()==='LLM.Giving'&&await page.evaluate(()=>getComputedStyle(document.body).backgroundColor)==='rgb(20, 30, 40)');
 await page.getByRole('link',{name:'Find research to help with ↘',exact:true}).click();
 check('Explore action moves to research library',new URL(page.url()).hash==='#research');
}
