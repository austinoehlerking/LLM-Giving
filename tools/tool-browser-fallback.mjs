// Read-only real-page snapshots for text enlargement and script-free rendering.
// Used only after the mandatory Edge QA runner fails to launch in the Windows sandbox.
import fs from 'node:fs';import path from 'node:path';import http from 'node:http';import {spawnSync} from 'node:child_process';import {root,runPhp,portOpen} from './runtime.mjs';
if(runPhp(['-r',"require 'src/bootstrap.php';echo env('APP_ENV');"]).trim()==='production')throw Error('Local QA only.');
const record=JSON.parse(fs.readFileSync(path.join(root,'storage/qa/tool-builds/iab-fixture.json'),'utf8'));const files={};
for(const [label,url] of Object.entries(record.pages)){
 if(!['request','task','result'].includes(label)||!(url==='/submit'||/^\/research\/[a-z0-9-]+\/(tasks|results)\/[a-f0-9]{32}$/.test(url)))throw Error('Invalid local QA page.');
 const response=await fetch('http://127.0.0.1:8086'+url);if(!response.ok)throw Error('Actual QA page unavailable: '+url);const html=await response.text();
 if(!html.includes('<main'))throw Error('Missing rendered application.');
 files['storage/qa/tool-builds/fallback-'+label+'-enlarged.html']=html.replace('</head>','<style>body{font-size:200%}</style></head>');
 files['storage/qa/tool-builds/fallback-'+label+'-nojs.html']=html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
}
for(const present of [false,true]){const group=Object.fromEntries(Object.entries(files).filter(([file])=>fs.existsSync(path.join(root,file))===present));if(!Object.keys(group).length)continue;const result=spawnSync(process.execPath,['tools/write-files.mjs',...(present?['--replace']:[])],{input:JSON.stringify(group),encoding:'utf8'});if(result.status!==0)throw Error(result.stdout+result.stderr);}
if(await portOpen(8098))throw Error('QA port 8098 is already in use.');
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://127.0.0.1:8098');let file;
 if(/^\/(request|task|result)-(enlarged|nojs)$/.test(url.pathname))file=path.join(root,'storage/qa/tool-builds/fallback-'+url.pathname.slice(1)+'.html');
 else if(/^\/assets\/[a-z0-9_.-]+$/.test(url.pathname))file=path.join(root,'public',url.pathname.slice(1));
 else if(url.pathname==='/favicon.svg')file=path.join(root,'public/favicon.svg');
 if(!file||!fs.existsSync(file)||req.method!=='GET'){res.writeHead(404);res.end();return;}
 res.setHeader('Cache-Control','no-store');res.setHeader('Content-Type',({'.html':'text/html; charset=utf-8','.css':'text/css','.js':'text/javascript','.svg':'image/svg+xml'})[path.extname(file)]||'application/octet-stream');fs.createReadStream(file).pipe(res);
});server.listen(8098,'127.0.0.1',()=>console.log('Read-only QA snapshots at http://127.0.0.1:8098/{request|task|result}-{enlarged|nojs}. Enlarged text is a documented CSS simulation; script-free snapshots contain no JavaScript.'));
process.once('SIGINT',()=>server.close());process.once('SIGTERM',()=>server.close());