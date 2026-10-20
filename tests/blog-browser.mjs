import fs from 'node:fs';
import path from 'node:path';
import {root} from '../tools/runtime.mjs';
const normalize=text=>text.replace(/\s+/gu,' ').trim();
const text=tokens=>tokens.map(t=>t.type==='text'?t.text:text(t.children)).join('');
const blockText=b=>b.type==='table'?[b.header,...b.rows].flat().map(text).join(' '):b.type==='list'?b.items.map(text).join(' '):text(b.content);
export async function blogQa({page,ctx,base,check,shot,visibleActions,report}) {
 const output=path.join(root,'storage','qa','blog');fs.mkdirSync(output,{recursive:true});
 const posts=JSON.parse(fs.readFileSync(path.join(root,'content','blog','articles.json'),'utf8')).posts;
 const consoleErrors=[];page.on('console',message=>{if(message.type()==='error')consoleErrors.push(message.text());});
 report.blogArticles=[];
 const visit=async route=>{const res=await page.goto(base+route);check('Blog HTTP success '+route,res.status()===200);check('Blog starts at scroll zero '+route,await page.evaluate(()=>scrollY===0));};
 const navigation=page.getByRole('navigation',{name:'Main navigation'});
 await visit('/');await navigation.getByRole('link',{name:'Blog',exact:true}).click();
 check('Main navigation opens blog',new URL(page.url()).pathname==='/blog');
 for(const size of [{width:1366,height:768},{width:390,height:844},{width:375,height:667},{width:768,height:1024}]){
  await page.setViewportSize(size);await visit('/blog');
  await shot(page,'blog/index-'+size.width+'x'+size.height);
  await visibleActions(page,'.blog-heading .primary-actions a, .blog-card:first-child .blog-read, .site-header nav a[href="/blog"]','Blog index '+size.width);
  check('All four article cards '+size.width,await page.locator('.blog-grid>.blog-card').count()===4);
  await page.locator('.blog-card').first().locator('.blog-read').click();
  check('Article card opens full guide '+size.width,new URL(page.url()).pathname==='/blog/'+posts[0].slug);
  for(const post of posts){
   await visit('/blog/'+post.slug);
   await shot(page,'blog/'+post.slug+'-'+size.width+'x'+size.height);
   await visibleActions(page,'.blog-heading .primary-actions a, .blog-back, .site-header nav a[href="/blog"]',post.slug+' '+size.width);
   check('Visible full heading '+post.slug+' '+size.width,await page.locator('h1').innerText()===post.title);
   check('Complete body '+post.slug+' '+size.width,normalize(await page.locator('.blog-body').innerText())===normalize(post.blocks.map(blockText).join(' ')));
   check('Article title metadata '+post.slug,await page.title()===post.seoTitle+' · LLM.Giving');
   check('Article description '+post.slug,await page.locator('meta[name="description"]').getAttribute('content')===post.description);
   check('Article canonical '+post.slug,await page.locator('link[rel="canonical"]').getAttribute('href')===base+'/blog/'+post.slug);
   const schema=JSON.parse(await page.locator('script[type="application/ld+json"]').textContent());
   check('Article schema '+post.slug,schema['@type']==='BlogPosting'&&schema.headline===post.title&&!schema.datePublished&&!schema.author);
   check('Social preview text '+post.slug,await page.locator('meta[property="og:description"]').getAttribute('content')===post.description&&await page.locator('meta[property="og:type"]').getAttribute('content')==='article');
   check('Section links '+post.slug,await page.locator('.blog-contents nav a').count()===post.headings.length);
   await page.locator('.blog-heading a[href="#article-body"]').click();
   await page.waitForFunction(()=>document.querySelector('#article-body').getBoundingClientRect().top<80);
   check('Read action reaches article body '+post.slug+' '+size.width,new URL(page.url()).hash==='#article-body');
   if(size.width===1366){
    const rendered={slug:post.slug,title:await page.title(),h1:await page.locator('h1').innerText(),description:post.description,excerpt:await page.locator('.blog-heading .lead').innerText(),readingLabel:await page.locator('.blog-reading').innerText(),actions:await page.locator('.blog-heading .primary-actions').innerText(),contents:await page.locator('.blog-contents').textContent(),body:await page.locator('.blog-body').innerText(),related:await page.locator('.blog-related').innerText(),canonical:await page.locator('link[rel="canonical"]').getAttribute('href'),schema,social:{title:await page.locator('meta[property="og:title"]').getAttribute('content'),description:await page.locator('meta[property="og:description"]').getAttribute('content'),twitterTitle:await page.locator('meta[name="twitter:title"]').getAttribute('content'),twitterDescription:await page.locator('meta[name="twitter:description"]').getAttribute('content')},links:await page.locator('#main a').evaluateAll(els=>els.map(el=>({text:el.textContent.trim(),href:el.getAttribute('href')})))};
    fs.writeFileSync(path.join(output,post.slug+'-render.json'),JSON.stringify(rendered,null,2));
    fs.writeFileSync(path.join(output,post.slug+'.html'),await page.content());
    const full=path.join(output,post.slug+'-full.png');await page.screenshot({path:full,fullPage:true});report.screenshots.push(full);
    report.blogArticles.push({slug:post.slug,render:path.join(output,post.slug+'-render.json'),fullScreenshot:full,allBodyTextMatches:true});
    for(const href of new Set(rendered.links.filter(l=>l.href.startsWith('/')).map(l=>l.href)))check('Internal article link '+href,(await ctx.request.get(base+href)).status()===200);
   }
  }
  await visit('/blog');await page.locator('.blog-heading a[href="/research"]').click();check('Research CTA opens research library '+size.width,new URL(page.url()).pathname==='/research'&&await page.getByRole('heading',{name:'Explore deep research',exact:true}).isVisible());
 }
 await page.setViewportSize({width:1366,height:768});await visit('/blog/'+posts[0].slug);await page.locator('.blog-contents summary').click();await page.locator('.blog-contents nav a').first().click();
 check('Contents link reaches section',new URL(page.url()).hash==='#'+posts[0].headings[0].id);
 await visit('/blog/'+posts[0].slug);await page.locator('.blog-related a.blog-read').first().click();check('Related guide opens correctly',new URL(page.url()).pathname==='/blog/'+posts[0].relatedSlugs[0]);
 await visit('/blog');await page.keyboard.press('Tab');check('Blog keyboard skip link',await page.evaluate(()=>document.activeElement.classList.contains('skip')));await page.keyboard.press('Enter');check('Blog keyboard reaches main',new URL(page.url()).hash==='#main');
 for(const route of ['/blog',...posts.map(p=>'/blog/'+p.slug)]){
  await page.setViewportSize({width:375,height:667});await visit(route);
  await page.evaluate(()=>{const sizes=[...document.querySelectorAll('body *')].map(el=>[el,parseFloat(getComputedStyle(el).fontSize)]);for(const [el,size] of sizes)el.style.fontSize=size*2+'px';});
  await shot(page,'blog/'+(route==='/blog'?'index':route.split('/').at(-1))+'-text-200');
  check('200 percent text reflows '+route,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 }
 const plain=await ctx.browser().newContext({javaScriptEnabled:false,viewport:{width:375,height:667}});
 try {
  const p=await plain.newPage();await p.goto(base+'/blog');check('Blog index works without JavaScript',await p.locator('.blog-card').count()===4);
  for(const post of posts){await p.goto(base+'/blog/'+post.slug);check('Full guide without JavaScript '+post.slug,normalize(await p.locator('.blog-body').innerText())===normalize(post.blocks.map(blockText).join(' ')));await p.locator('.blog-heading a[href="#article-body"]').click();check('Read link without JavaScript '+post.slug,new URL(p.url()).hash==='#article-body');}
 }finally{await plain.close();}
 for(const route of ['/blog/not-a-guide','/blog/tokenmaxxing-meaning','/content/blog-drafts/articles.json','/content/blog/articles.json','/storage/editorial/2026-10-19/blog-seed-research.json'])check('Unknown/private editorial path hidden '+route,(await ctx.request.get(base+route)).status()===404);
 const sitemap=await ctx.request.get(base+'/sitemap.xml');const sitemapText=await sitemap.text();check('Blog sitemap available',sitemap.status()===200&&posts.every(p=>sitemapText.includes('/blog/'+p.slug+'<')));
 const index=await ctx.request.get(base+'/blog');check('No draft notes published',!/(keywordValidation|review_record_valid|secondaryKeywords)/.test(await index.text()));
 check('No blog console errors',consoleErrors.length===0);
 console.log('Blog browser checks passed; rendered text, navigation, metadata and required viewports verified.');
}