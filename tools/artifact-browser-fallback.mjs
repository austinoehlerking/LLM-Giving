// Read-only rendering fallback after the mandatory Edge route cannot launch.
import fs from 'node:fs';import path from 'node:path';import http from 'node:http';import {spawnSync} from 'node:child_process';import {root,runPhp,portOpen} from './runtime.mjs';
if(runPhp(['-r',"require 'src/bootstrap.php';echo env('APP_ENV');"]).trim()==='production')throw Error('Local QA only.');
const dir=path.join(root,'storage/qa/artifacts'),fixture=JSON.parse(fs.readFileSync(path.join(dir,'iab-fixture.json'),'utf8'));
if(!/^[a-f0-9]{32}$/.test(fixture.id)||!/^[a-f0-9]{32}$/.test(fixture.result_id)||!/^[a-z0-9-]+$/.test(fixture.slug))throw Error('Invalid QA fixture identity.');
const base='http://127.0.0.1:8086/research/'+fixture.slug+'/results/'+fixture.result_id+'/artifacts/';const files={};
for(const [name,id] of [['enlarged','scenario-page'],['no-js','pathway-animation'],['reduced-motion','pathway-animation']]){
 const response=await fetch(base+id);if(!response.ok)throw Error('The real accepted QA artifact is unavailable.');let html=await response.text();
 if(!html.includes('data-artifact-widget'))throw Error('Artifact render missing.');
 if(name==='enlarged')html=html.replace('</head>','<style>body{font-size:200%}</style></head>');
 if(name==='no-js')html=html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
 if(name==='reduced-motion')html=html.replace('</head>','<script src="/qa-media.js"></script></head>');
 files['storage/qa/artifacts/fallback-'+name+'.html']=html;
}
const media="'use strict';const originalMatchMedia=window.matchMedia.bind(window);window.matchMedia=query=>query==='(prefers-reduced-motion: reduce)'?{matches:true,media:query,addEventListener(){},removeEventListener(){}}:originalMatchMedia(query);";
files['storage/qa/artifacts/fallback-media.js']=media;
for(const present of [false,true]){const group=Object.fromEntries(Object.entries(files).filter(([file])=>fs.existsSync(path.join(root,file))===present));if(!Object.keys(group).length)continue;const result=spawnSync(process.execPath,['tools/write-files.mjs',...(present?['--replace']:[])],{cwd:root,input:JSON.stringify(group),encoding:'utf8'});if(result.status!==0)throw Error(result.stdout+result.stderr);}
if(await portOpen(8096))throw Error('QA port 8096 already in use; no existing process was changed.');const assetRoot=path.join(root,'public','assets');
const server=http.createServer((req,res)=>{const url=new URL(req.url,'http://127.0.0.1:8096');let file;
 if(['/enlarged','/no-js','/reduced-motion'].includes(url.pathname))file=path.join(dir,'fallback-'+url.pathname.slice(1)+'.html');
 else if(url.pathname==='/qa-media.js')file=path.join(dir,'fallback-media.js');
 else if(url.pathname==='/favicon.svg')file=path.join(root,'public/favicon.svg');
 else if(url.pathname.startsWith('/assets/')){file=path.resolve(root,'public','.'+decodeURIComponent(url.pathname));if(!file.startsWith(assetRoot+path.sep)){res.writeHead(404);res.end();return;}}
 else if(url.pathname.startsWith('/research/')){res.writeHead(302,{Location:'http://127.0.0.1:8086'+req.url});res.end();return;}
 if(!file||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Cache-Control','no-store');res.setHeader('Content-Type',({html:'text/html; charset=utf-8',js:'text/javascript',css:'text/css',svg:'image/svg+xml',woff2:'font/woff2'})[path.extname(file).slice(1)]||'application/octet-stream');fs.createReadStream(file).pipe(res);
});server.listen(8096,'127.0.0.1',()=>console.log('Read-only QA snapshots: http://127.0.0.1:8096/enlarged, /no-js, /reduced-motion. Media preference is simulated only in the reduced-motion snapshot.'));
process.once('SIGINT',()=>server.close());process.once('SIGTERM',()=>server.close());
