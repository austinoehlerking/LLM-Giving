import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';

const root=path.resolve(import.meta.dirname,'..');
export function inline(text){
 const tokens=[];const pattern=/\*\*([^*]+)\*\*|\[([^\]]+)\]\(([^)]+)\)/g;let start=0;
 for(const match of text.matchAll(pattern)){
  if(match.index>start)tokens.push({type:'text',text:text.slice(start,match.index)});
  if(match[1])tokens.push({type:'strong',children:inline(match[1])});
  else {
   const href=match[3];
   if(href.startsWith('/')){
    if(!/^\/(?:about|research|submit)$/.test(href))throw new Error('Unapproved editorial route: '+href);
   }else{
    const url=new URL(href);
    if(url.protocol!=='https:'||url.username||url.password||/[\s\\]/.test(href))throw new Error('Unsafe editorial URL');
   }
   tokens.push({type:'link',href,children:inline(match[2])});
  }
  start=match.index+match[0].length;
 }
 if(start<text.length)tokens.push({type:'text',text:text.slice(start)});
 return tokens;
}
export function compileMarkdown(markdown,title){
 const lines=markdown.replace(/\r\n/g,'\n').trim().split('\n');
 if(lines.shift()!=='# '+title)throw new Error('Article H1 must match its catalogue title');
 const blocks=[];const headings=[];const used=new Set();let i=0;
 const cells=line=>line.trim().replace(/^\||\|$/g,'').split('|').map(cell=>inline(cell.trim()));
 const special=line=>/^## |^\| |^> |^[-*] |^\d+\. /.test(line);
 while(i<lines.length){
  const line=lines[i].trim();if(!line){i++;continue;}
  if(line.startsWith('## ')){
   const text=line.slice(3);const base=text.normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')||'section';
   let id=base;for(let n=2;used.has(id);n++)id=base+'-'+n;used.add(id);
   headings.push({id,text});blocks.push({type:'heading',id,content:inline(text)});i++;continue;
  }
  if(line.startsWith('| ')){
   const header=cells(line);i++;
   if(!/^\|\s*:?-+/.test(lines[i]||''))throw new Error('Table separator missing');i++;
   const rows=[];while(i<lines.length&&lines[i].trim().startsWith('|')){const row=cells(lines[i++]);if(row.length!==header.length)throw new Error('Inconsistent table width');rows.push(row);}
   blocks.push({type:'table',header,rows});continue;
  }
  if(/^[-*] |^\d+\. /.test(line)){
   const ordered=/^\d+\. /.test(line);const items=[];const pattern=ordered?/^\d+\. /:/^[-*] /;
   while(i<lines.length&&pattern.test(lines[i].trim()))items.push(inline(lines[i++].trim().replace(pattern,'')));
   blocks.push({type:'list',ordered,items});continue;
  }
  if(line.startsWith('> ')){
   const text=[];while(i<lines.length&&lines[i].trim().startsWith('> '))text.push(lines[i++].trim().slice(2));
   blocks.push({type:'quote',content:inline(text.join(' '))});continue;
  }
  if(/^#/.test(line))throw new Error('Unsupported heading level');
  const text=[line];i++;while(i<lines.length&&lines[i].trim()&&!special(lines[i].trim()))text.push(lines[i++].trim());
  blocks.push({type:'paragraph',content:inline(text.join(' '))});
 }
 return {blocks,headings};
}
export function compileCatalogue(catalog,readSource){
 const posts=[];const slugs=new Set();
 for(const article of catalog.articles){
  if(article.status!=='published')continue;
  if(!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(article.slug)||slugs.has(article.slug))throw new Error('Invalid or duplicate blog slug');
  if(article.source!=='content/blog-drafts/'+article.slug+'.md')throw new Error('Editorial source must match the fixed source directory');
  slugs.add(article.slug);
  for(const field of ['title','seoTitle','metaDescription','excerpt'])if(typeof article[field]!=='string'||!article[field].trim())throw new Error('Missing '+field);
  const source=readSource(article.source);const body=compileMarkdown(source,article.title);
  const wordCount=source.trim().split(/\s+/u).length;
  posts.push({slug:article.slug,title:article.title,seoTitle:article.seoTitle,description:article.metaDescription,excerpt:article.excerpt,readingMinutes:Math.max(1,Math.ceil(wordCount/220)),relatedSlugs:article.relatedSlugs??[],...body});
 }
 for(const article of posts)if(article.relatedSlugs.some(slug=>!slugs.has(slug)))throw new Error('Related article must be published');
 return {version:1,posts};
}
if(process.argv[1]&&path.resolve(process.argv[1])===fileURLToPath(import.meta.url)){
 const catalog=JSON.parse(fs.readFileSync(path.join(root,'content/blog-drafts/articles.json'),'utf8'));
 const compiled=JSON.stringify(compileCatalogue(catalog,file=>fs.readFileSync(path.join(root,file),'utf8')),null,2)+'\n';
 const target='content/blog/articles.json';
 if(process.argv.includes('--check')){
  if(!fs.existsSync(path.join(root,target))||fs.readFileSync(path.join(root,target),'utf8')!==compiled)throw new Error('Blog output is stale. Run node tools/build-blog.mjs.');
  console.log('Reviewed blog content is current.');
 }else{
  const args=['tools/write-files.mjs',...(fs.existsSync(path.join(root,target))?['--replace']:[])];
  const result=spawnSync(process.execPath,args,{cwd:root,input:JSON.stringify({[target]:compiled}),encoding:'utf8'});
  process.stdout.write(result.stdout);process.stderr.write(result.stderr);process.exitCode=result.status??1;
 }
}