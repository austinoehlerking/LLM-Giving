import assert from 'node:assert/strict';
import {env} from '../tools/runtime.mjs';

export async function homepageResearchQa({page,ctx,base,runPhp,check,shot,goto,visibleActions}){
    const fixture=JSON.parse(runPhp(['tests/homepage-research-state.php','create']));
    const [first,second,,unavailable]=fixture.rows;
    const filtered='/?tag='+encodeURIComponent(fixture.tag)+'#research';
    const section=page.locator('#research');
    const rows=section.locator('.home-research-row');
    const row=item=>rows.filter({has:page.getByRole('heading',{name:item.title,exact:true})});
    const start=item=>row(item).getByRole('link',{name:'Start researching: '+item.title,exact:true});
    const brief=item=>row(item).getByRole('link',{name:'Read brief: '+item.title,exact:true});
    const counts=()=>JSON.parse(runPhp(['tests/homepage-research-state.php','counts',first.id]));
    const gotoSection=async()=>{
        if(page.url()===base+filtered){
            const response=await page.reload();
            assert.ok(response?.ok(),'Reload homepage discovery');
        }else await goto(page,filtered);
        await page.waitForFunction(()=>document.querySelector('#research').getBoundingClientRect().top<60);
    };
    await page.emulateMedia({reducedMotion:'reduce'});
    let plain;
    try{
        for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667},{width:1754,height:896}]){
            await page.setViewportSize(size);
            await goto(page,'/');
            await shot(page,'homepage-research-home-initial-'+size.width);
            await visibleActions(page,'.giving-copy .primary-actions a','Homepage '+size.width);
            await gotoSection();
            await shot(page,'homepage-research-section-initial-'+size.width);
            await visibleActions(page,'.home-research-toolbar input[type=search],.home-research-toolbar button[aria-label="Search research"],.home-research-category select,.home-research-filters > summary,.home-research-row:first-child .home-research-actions a','Research section '+size.width);
        }
        if(env.QA_INSPECT_FIRST==='homepage-research'){
            console.log('Initial homepage and section screenshots captured. Continue after inspection.');
            await new Promise(resolve=>{process.stdin.resume();process.stdin.once('data',()=>{process.stdin.pause();resolve();});});
        }
        check('Featured row is the newest visible filtered request',await rows.first().getAttribute('data-research-id')===first.id&&await rows.count()===12);
        check('Ready research shows the real task and underlined brief followed by primary action',await row(first).locator('.home-research-next').innerText()==='Next task: '+first.taskTitle&&await row(first).locator('.home-research-actions').getByRole('link').allTextContents().then(text=>text.join('|')==='Read brief|Start researching'));
        check('Unavailable request has a brief but no next-task cue or research action',await brief(unavailable).count()===1&&!await start(unavailable).count()&&!await row(unavailable).locator('.home-research-next').count());
        check('Unlisted request never appears in discovery',!await row(fixture.rows.at(-1)).count());
        check('Homepage omits the removed discovery notice',!await page.getByText('Only approved research is listed. Publication is not factual verification.',{exact:true}).count());

        const before=counts();
        await brief(first).click();
        check('Read brief opens the correct study and reveals its brief',new URL(page.url()).pathname===first.study&&new URL(page.url()).hash==='#question'&&await page.locator('details#question').getAttribute('open')!==null);
        await gotoSection();
        const target=await start(first).getAttribute('href');
        await start(first).click();
        check('Start researching opens the exact task authorization form directly',target===first.study+'/authorize/'+first.taskId&&new URL(page.url()).pathname===target&&await page.getByRole('heading',{name:'Authorize your AI',exact:true}).isVisible());
        check('Authorization still requires explicit consent and rendered no lease or grant',await page.locator('input[name=authorize][required]').count()===1&&await page.locator('input[name=terms][required]').count()===1&&await page.locator('input[name=authorize]').isChecked()===false);
        assert.deepEqual(counts(),before);
        check('GET destinations created no reservations, grants or volunteer sessions');
        await shot(page,'homepage-research-authorization');

        runPhp(['tests/homepage-research-state.php','reserve',first.id,first.taskId]);
        await gotoSection();
        check('A competing reservation removes the unavailable CTA and cue',await brief(first).count()===1&&!await start(first).count()&&!await row(first).locator('.home-research-next').count());
        runPhp(['tests/homepage-research-state.php','pause',first.id]);
        check('An earlier task URL rechecks stale eligibility',(await page.request.get(base+target)).status()===409);

        await gotoSection();
        await page.getByRole('link',{name:'2',exact:true}).click();
        const pageTwo=new URL(page.url());
        check('Pagination stays on homepage and preserves filters',pageTwo.pathname==='/'&&pageTwo.searchParams.get('tag')===fixture.tag&&pageTwo.searchParams.get('page')==='2'&&pageTwo.hash==='#research'&&await rows.count()===2);
        await gotoSection();
        await page.getByRole('searchbox',{name:'Search research requests'}).fill('methane');
        await page.getByRole('button',{name:'Search research',exact:true}).click();
        check('Homepage search stays in the section and shows the matching question',new URL(page.url()).pathname==='/'&&new URL(page.url()).hash==='#research'&&await rows.count()===1&&await row(second).count()===1);
        await gotoSection();
        await page.getByLabel('Category',{exact:true}).selectOption(second.category);
        await page.waitForURL(url=>url.searchParams.get('category')===second.category);
        check('Category toolbar filters the same discovery results',await row(second).count()===1&&!await row(first).count());
        await section.getByText('Filters',{exact:true}).click();
        await page.getByLabel('Sort by',{exact:true}).selectOption('title');
        await page.waitForURL(url=>url.searchParams.get('sort')==='title');
        check('Advanced sorting preserves category and anchor',new URL(page.url()).searchParams.get('category')===second.category&&new URL(page.url()).hash==='#research');
        await section.getByRole('link',{name:'Reset filters'}).click();
        check('Reset clears homepage filters',new URL(page.url()).search===''&&await section.getByLabel('Category',{exact:true}).inputValue()==='');

        plain=await ctx.browser().newContext({javaScriptEnabled:false,reducedMotion:'reduce',viewport:{width:390,height:844}});
        const native=await plain.newPage();
        await native.goto(base+filtered);
        await native.getByLabel('Category',{exact:true}).selectOption(second.category);
        await native.getByText('Filters',{exact:true}).press('Enter');
        await native.getByLabel('Sort by',{exact:true}).selectOption('title');
        await native.getByRole('button',{name:'Apply filters',exact:true}).press('Enter');
        await native.waitForURL(url=>url.searchParams.get('category')===second.category);
        check('Native keyboard form submits filters without JavaScript',new URL(native.url()).pathname==='/'&&new URL(native.url()).searchParams.get('sort')==='title'&&await native.locator('.home-research-row').count()===7);
        await goto(page,'/research?tag='+encodeURIComponent(fixture.tag));
        check('Standalone catalogue omits the removed discovery notice',!await page.getByText('Only approved research is listed. Publication is not factual verification.',{exact:true}).count());
        check('Standalone catalogue keeps cards, categories, search, and filters',await page.locator('.research-card').count()===12&&await page.locator('.library-categories').count()===1&&await page.locator('form[action="/research"] .library-advanced').count()===1&&!await page.locator('.home-research-row').count());

        for(const size of [{width:1366,height:768},{width:375,height:667}]){
            await page.setViewportSize(size);
            await gotoSection();
            await page.evaluate(()=>{
                const fonts=[...document.querySelectorAll('body,body *')].filter(el=>el instanceof HTMLElement).map(el=>[el,parseFloat(getComputedStyle(el).fontSize)]);
                for(const [el,size] of fonts)if(Number.isFinite(size))el.style.fontSize=(2*size)+'px';
                document.querySelector('#research').scrollIntoView({block:'start'});
            });
            await shot(page,'homepage-research-200-percent-'+size.width);
            check('200% homepage discovery reflows without horizontal clipping '+size.width,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
            check('200% action row keeps both links in the reading area '+size.width,await row(second).locator('.home-research-actions > a').evaluateAll(links=>links.every(link=>{const box=link.getBoundingClientRect();return box.left>=0&&box.right<=innerWidth+1&&box.height>=44;})));
        }
    }finally{
        await plain?.close();
        for(const item of fixture.rows)runPhp(['tests/homepage-research-state.php','purge',item.id]);
    }
}
