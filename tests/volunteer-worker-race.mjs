/** Deterministic real native HTTP worker versus lifecycle regression for CR-VS-1. */
import fs from 'node:fs';import path from 'node:path';import net from 'node:net';import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';import {createHash,randomBytes} from 'node:crypto';
import {root,env,phpArgs,runPhp,spawnPhp,ready} from '../tools/runtime.mjs';
const out=path.join(root,'storage/qa/volunteers/worker-race');fs.mkdirSync(out,{recursive:true});
const socket=net.createServer();await new Promise(resolve=>socket.listen(0,'127.0.0.1',resolve));const port=socket.address().port;await new Promise(resolve=>socket.close(resolve));
const base='http://127.0.0.1:'+port,server=spawnPhp(['-S','127.0.0.1:'+port,'-t','public','public/router.php'],{APP_URL:base,RATE_LIMIT_NAMESPACE:'worker-lifecycle-'+randomBytes(8).toString('hex')});
const cases=[],checks=[];let fixture,activeLifecycle;
const check=(label,ok)=>{assert.ok(ok,label);checks.push(label);};
const escape=s=>s.replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll("'",'&#039;').replaceAll('<','&lt;').replaceAll('>','&gt;');
class Worker {
 jar=new Map();
 async request(route,fields){
  const response=await fetch(base+route,{method:fields?'POST':'GET',body:fields?new URLSearchParams(fields):undefined,redirect:'manual',signal:AbortSignal.timeout(18000),headers:{Cookie:[...this.jar].map(([key,value])=>key+'='+value).join('; '),...(fields?{'Content-Type':'application/x-www-form-urlencoded'}:{})}});
  for(const raw of response.headers.getSetCookie()){const [key,value]=raw.split(';')[0].split('=');this.jar.set(key,value);}return response;
 }
}
function lifecycle(rid){
 const child=spawn(process.env.PHP_BINARY||'php',[...phpArgs(),'tests/volunteer-worker-race.php','lifecycle',rid],{cwd:root,env,windowsHide:true,stdio:['ignore','pipe','pipe']});activeLifecycle=child;
 let buffer='',stderr='',lines=[],unlockReady,rejectReady;
 const locked=new Promise((resolve,reject)=>{unlockReady=resolve;rejectReady=reject;});
 child.stdout.on('data',data=>{buffer+=data;let e;while((e=buffer.indexOf('\n'))!==-1){const raw=buffer.slice(0,e);buffer=buffer.slice(e+1);if(!raw.trim())continue;try{const value=JSON.parse(raw);lines.push(value);if(value.locked)unlockReady();}catch(error){rejectReady(error);}}});
 child.stderr.on('data',data=>stderr+=data);
 const done=new Promise((resolve,reject)=>{child.once('error',error=>{rejectReady(error);reject(error);});child.once('close',code=>{if(code){const error=Error('Lifecycle fixture failed: '+stderr);rejectReady(error);reject(error);}else resolve(lines.find(line=>line.worker_wait_observed)??null);});});
 // Register rejection handling immediately while the HTTP request is being prepared.
 done.catch(()=>{});return {locked,done};
}
function sourceIdentity(){
 try {
  const checkout=fs.realpathSync(execFileSync('git',['rev-parse','--show-toplevel'],{encoding:'utf8'}).trim()),workspace=fs.realpathSync(root);
  const same=process.platform==='win32'?checkout.toLowerCase()===workspace.toLowerCase():checkout===workspace;
  if(same)return {head:execFileSync('git',['rev-parse','HEAD'],{encoding:'utf8'}).trim(),workingTreeDirty:Boolean(execFileSync('git',['status','--porcelain'],{encoding:'utf8'}).trim()),sourceIdentity:'Git worktree'};
 }catch{}
 return {head:null,workingTreeDirty:null,sourceIdentity:'Unversioned snapshot; identify tested bytes by applicationSha256 and independently verified source files'};
}
const save=pass=>fs.writeFileSync(path.join(out,'report.json'),JSON.stringify({pass,...sourceIdentity(),applicationSha256:createHash('sha256').update(fs.readFileSync('src/browser-return.php')).digest('hex'),checks,cases,scope:'Native HTTP through private worker exchange; actual held parent lock observed in MariaDB process list before staged lifecycle revision/invalidation. Synthetic fixtures only. No credentials or packet body are persisted.'},null,2));
try {
 await ready(base+'/',server);
 for(const operation of ['submit','check']) {
  fixture=JSON.parse(runPhp(['tests/volunteer-worker-race.php','create'],undefined,{APP_URL:base}));
  const worker=new Worker(),url=new URL(fixture.link),exchange=await worker.request(url.pathname);check(operation+': private exchange page loads',exchange.status===200);
  let html=await exchange.text(),csrf=html.match(/name="csrf" value="([^"]+)"/)[1];
  const opened=await worker.request(url.pathname,{csrf,return_secret:url.hash.slice(1)});check(operation+': separate worker exchanges stored permission',opened.status===303);
  const route=opened.headers.get('location'),form=await worker.request(route);check(operation+': native assigned form loads before lifecycle',form.status===200);
  html=await form.text();csrf=html.match(/name="csrf" value="([^"]+)"/)[1];
  const before=JSON.parse(runPhp(['tests/volunteer-worker-race.php','state',fixture.rid]));
  const barrier=lifecycle(fixture.rid);await barrier.locked;
  let earlyStatus=null;const payload=JSON.stringify(fixture.packet),responsePromise=worker.request(route,{csrf,operation,payload}).then(response=>{earlyStatus=response.status;return response;});
  let response,transition;try{[response,transition]=await Promise.all([responsePromise,barrier.done]);}catch(error){throw Error(error.message+'; native status before barrier='+earlyStatus);}activeLifecycle=null;html=await response.text();
  const after=JSON.parse(runPhp(['tests/volunteer-worker-race.php','state',fixture.rid]));
  const observed={operation,status:response.status,workerWaitObserved:transition?.worker_wait_observed===true,packetRetained:html.includes('>'+escape(payload)+'</textarea>'),results:after.results,leaseUnchanged:JSON.stringify(after.leases)===JSON.stringify(transition?.state_after_lifecycle.leases),returnUnconsumed:after.returns.every(g=>g.result_id===null&&g.payload_hash===null),assignmentUnchanged:JSON.stringify(after.assignments)===JSON.stringify(before.assignments),payloadSha256:createHash('sha256').update(payload).digest('hex')};cases.push(observed);save(false);
  check(operation+': worker was observed waiting after its grant read',observed.workerWaitObserved);
  check(operation+': concurrent lifecycle is a controlled HTTP409',observed.status===409);
  check(operation+': native response preserves the complete entered packet',observed.packetRetained);
  check(operation+': no result or consumed grant',after.results===0&&observed.returnUnconsumed);
  check(operation+': worker does not mutate invalidated lease or assignment',observed.leaseUnchanged&&observed.assignmentUnchanged);
  check(operation+': conflict is a form response without SQL/exception disclosure',html.includes('Not accepted')&&!/PDOException|SQLSTATE|Record has changed since last read/.test(html));
  check(operation+': lifecycle revision was committed and is not replaced',after.tasks.every(t=>Number(t.revision)===2));
  runPhp(['tests/volunteer-worker-race.php','cleanup',fixture.rid]);fixture=null;
 }
 save(true);console.log(checks.length+' separate-worker lifecycle/native HTTP checks passed.');
} finally {
 if(activeLifecycle?.exitCode===null)activeLifecycle.kill();
 server.kill();if(server.exitCode===null)await new Promise(resolve=>server.once('exit',resolve));
 if(fixture)runPhp(['tests/volunteer-worker-race.php','cleanup',fixture.rid]);
}
