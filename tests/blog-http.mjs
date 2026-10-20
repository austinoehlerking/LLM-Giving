import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {root,runPhp} from '../tools/runtime.mjs';
const base=process.argv[2]||'http://127.0.0.1:8086';
assert.ok(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Only the local QA server is allowed');
const data=JSON.parse(runPhp(['-r',"require 'src/bootstrap.php'; require 'src/view.php'; require 'src/blog.php'; echo json_encode(array_values(array_map(fn($p)=>['slug'=>$p['slug'],'html'=>blog_body($p['blocks'])],blog_posts())));"]));
const checks=[];
const verify=(name,ok)=>{assert.ok(ok,name);checks.push(name);};
for(const post of data){
 const response=await fetch(base+'/blog/'+post.slug);const html=await response.text();
 verify('Full article present in initial server HTML: '+post.slug,response.status===200&&html.includes(post.html));
 verify('No private editorial diagnostics: '+post.slug,!/(keywordValidation|review_record_valid|secondaryKeywords)/.test(html));
 for(const target of new Set([...html.matchAll(/href="(\/[^"#?]*)"/g)].map(m=>m[1]))){const r=await fetch(base+target);verify('Local destination '+target,r.status===200);}
 fs.writeFileSync(path.join(root,'storage','qa','blog',post.slug+'-server.html'),html);
}
for(const target of ['/blog/not-a-guide','/blog/tokenmaxxing-meaning','/content/blog/articles.json','/content/blog-drafts/articles.json','/storage/editorial/2026-10-19/blog-seed-research.json'])verify('Private or unknown path '+target,(await fetch(base+target)).status===404);
const redirect=await fetch(base+'/blog/',{redirect:'manual'});verify('Blog trailing slash canonical redirect',redirect.status===301&&redirect.headers.get('location')==='/blog');
const sitemap=await fetch(base+'/sitemap.xml');const xml=await sitemap.text();verify('Blog routes appear in sitemap',sitemap.status===200&&data.every(p=>xml.includes('/blog/'+p.slug+'</loc>'))&&!xml.includes('/admin')&&!xml.includes('/manage'));
verify('New stylesheet is served',(await fetch(base+'/assets/blog.css')).status===200);
fs.writeFileSync(path.join(root,'storage','qa','blog-http.json'),JSON.stringify({base,checks,sourceRendering:'Complete article HTML is delivered before JavaScript; browser JavaScript-disable mode was not available in the in-app fallback.'},null,2));
console.log(checks.length+' local blog HTTP, server-rendered content, link and isolation checks passed.');