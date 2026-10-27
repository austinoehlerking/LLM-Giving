/** One owned Node job: isolated MariaDB, actual app query, focused tests/HTTP, shutdown. */
import fs from 'node:fs';import path from 'node:path';import {spawn,spawnSync} from 'node:child_process';import {randomBytes,createHash} from 'node:crypto';import assert from 'node:assert/strict';
import {root,env,runPhp,spawnPhp,portOpen,ready} from '../tools/runtime.mjs';
const rotationOnly=process.argv.includes('--rotation-only');
const stamp=new Date().toISOString().replace(/[:.]/g,'-');const output=path.join(root,'storage/qa','research-transport-'+stamp);fs.mkdirSync(output,{recursive:true});
const maria=process.env.MARIADB_BIN||path.join(process.env.USERPROFILE,'scoop/apps/mariadb/current/bin');const port=18944;const httpPort=18941;const password=randomBytes(24).toString('hex');const database='rtx_'+randomBytes(8).toString('hex');
const datadir=path.join(output,'db');const defaults=path.join(output,'private-client.cnf');const extra={APP_ENV:'test',APP_URL:'http://127.0.0.1:'+httpPort,DB_HOST:'127.0.0.1',DB_PORT:String(port),DB_NAME:database,DB_USER:'root',DB_PASSWORD:password,GOOGLE_ANALYTICS_ENABLED:'0',RATE_LIMIT_NAMESPACE:'transport-'+stamp};
const report={status:'RUNNING',at:new Date().toISOString(),source:'focused transport branch',checks:[],screenshots:[],browser:'Not run; results owner holds first browser slot. HTTP/component evidence only.'};let server,dbChild,fixture;
const sha=value=>createHash('sha256').update(value).digest('hex');const check=(label,condition)=>{assert.ok(condition,label);report.checks.push(label);};
function binary(name,args,input){const result=spawnSync(path.join(maria,name+'.exe'),args,{cwd:root,env:{...env,...extra},input,encoding:'utf8',windowsHide:true});if(result.error||result.status!==0)throw new Error(name+': '+(result.error?.message||result.stderr||result.stdout));return result.stdout;}
function php(args,name){try{const result=runPhp(args,undefined,extra);fs.writeFileSync(path.join(output,name+'.log'),result);return result;}catch(error){fs.writeFileSync(path.join(output,name+'-FAILED.log'),String(error.stack));throw error;}}
try{
  if(await portOpen(port)||await portOpen(httpPort))throw new Error('Owned transport port is occupied; no existing process will be stopped.');
  binary('mariadb-install-db',['--datadir='+datadir,'--password='+password,'--port='+port]);
  const log=fs.openSync(path.join(output,'mariadb.log'),'a');dbChild=spawn(path.join(maria,'mariadbd.exe'),['--no-defaults','--datadir='+datadir,'--port='+port,'--bind-address=127.0.0.1','--max-allowed-packet=32M','--console'],{cwd:root,env:{...env,...extra},windowsHide:true,stdio:['ignore',log,log]});fs.closeSync(log);
  for(let i=0;i<100&&!await portOpen(port);i++){if(dbChild.exitCode!==null)throw new Error('Owned MariaDB exited; inspect retained log.');await new Promise(resolve=>setTimeout(resolve,150));}
  if(!await portOpen(port))throw new Error('Owned MariaDB failed readiness.');
  fs.writeFileSync(defaults,'[client]\nuser=root\npassword='+password+'\nhost=127.0.0.1\nport='+port+'\n',{mode:0o600});binary('mariadb',['--defaults-extra-file='+defaults],`CREATE DATABASE ${database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`);
  php(['bin/console.php','migrate'],'migrate');php(['-r',"require 'src/bootstrap.php'; if(!one('SELECT id FROM categories LIMIT 1'))throw new RuntimeException('Actual app query failed'); echo 'Actual PDO application query passed.';"],'app-query');
  if(rotationOnly){const focused=JSON.parse(php(['tests/research-transport-rotation.php'],'credential-rotation'));report.coverage='CR53-1 credential rotation only';report.checks=focused.checks;report.status='PASS';}
  else{
  fs.writeFileSync(path.join(output,'unit.json'),php(['tests/research-transport-unit.php'],'unit'));php(['tests/research-transport.php'],'server');php(['tests/handoffs.php'],'handoffs');php(['tests/volunteer-orchestrator.php'],'orchestrator');
  fixture=JSON.parse(php(['tests/research-transport-fixture.php'],'private-http-fixture'));
  server=spawnPhp(['-S','127.0.0.1:'+httpPort,'-t','public','public/router.php'],extra);await ready(extra.APP_URL,server);
  let cookie='';let csrf='';
  async function fetchLocal(url,fields){const response=await fetch(extra.APP_URL+url,{method:fields?'POST':'GET',headers:{...(cookie?{Cookie:cookie}:{}),...(fields?{Accept:'application/json'}:{})},...(fields?{body:new URLSearchParams({csrf,...fields})}:{}),redirect:'manual'});for(const set of response.headers.getSetCookie()){cookie=set.split(';')[0];}return response;}
  let response=await fetchLocal(fixture.base+'/return/'+fixture.grant_id);let html=await response.text();csrf=html.match(/name="csrf" value="([a-f0-9]+)"/)?.[1];check('Private exchange exposes CSRF-protected native form',response.status===200&&!!csrf);
  response=await fetchLocal(fixture.base+'/return/'+fixture.grant_id,{return_secret:fixture.secret});check('Original private capability exchanges for scoped browser session',response.status===303);
  const base=fixture.base+'/return/'+fixture.grant_id+'/transport';response=await fetchLocal(base);html=await response.text();check('Private transfer UI is reachable through actual application router',response.status===200&&html.includes('Private packet recovery'));csrf=html.match(/data-transport-csrf value="([a-f0-9]+)"/)?.[1];
  const bytes=Buffer.from(JSON.stringify(fixture.packet));const parts=[];for(let offset=0;offset<bytes.length;offset+=16384)parts.push(bytes.subarray(offset,Math.min(bytes.length,offset+16384)));
  const manifest={version:'llm-transport-v1',encoding:'UTF-8',length_unit:'bytes',binding:fixture.binding,part_count:parts.length,byte_length:bytes.length,sha256:sha(bytes),parts:parts.map((p,index)=>({index,byte_length:p.length,sha256:sha(p)})),end_marker:'END LLM TRANSPORT '+sha(bytes)};
  response=await fetchLocal(base,{operation:'begin',direction:'output',manifest:JSON.stringify(manifest)});let stream=await response.json();check('HTTP creates private exact-bound output stream',response.status===200&&stream.id);
  response=await fetchLocal(base+'/'+stream.id,{operation:'finalize'});check('HTTP rejects partial finalization',response.status===409);
  for(let index=0;index<parts.length;index++){response=await fetchLocal(base+'/'+stream.id,{operation:'put',index:String(index),body_base64:parts[index].toString('base64')});const ack=await response.json();assert.equal(response.status,200,JSON.stringify(ack));assert.ok(ack.acknowledged.includes(index));}
  response=await fetchLocal(base+'/'+stream.id,{operation:'finalize'});check('HTTP finalizes complete >200k output without Submit',(await response.json()).state==='finalized');
  response=await fetchLocal(base+'/'+stream.id+'/body');check('HTTP reassembled bytes equal exact original packet',response.status===200&&Buffer.from(await response.arrayBuffer()).equals(bytes));
  if(fixture.binding.schema_version!=='legacy'&&fixture.binding.schema_version!=='research-packet-v1'){
    response=await fetchLocal(fixture.base+'/return/'+fixture.grant_id+'/submit',{operation:'check',transport_id:stream.id});const checked=await response.json();check('Existing authorized Check accepts exact complete >200k v2 finalized draft',response.status===200&&checked.ok===true);
  }else report.integrationGaps=['Large-packet Check intentionally not executed against legacy200000-byte contract; awaits reviewed v2 schema integration.'];
  response=await fetchLocal(fixture.base+'/return/'+fixture.grant_id+'/submit');check('Check leaves original grant unconsumed and return page accessible',response.status===200);
  response=await fetchLocal(fixture.base+'/issued/'+fixture.binding.archive_id+'/transport/manifest');const publicManifest=await response.json();check('Public immutable archive manifest contains no private return credential',response.status===200&&!JSON.stringify(publicManifest).includes(fixture.secret)&&!JSON.stringify(publicManifest).includes(fixture.grant_id));
  response=await fetchLocal(base,{operation:'begin',direction:'input'});const input=await response.json();assert.equal(response.status,200,JSON.stringify(input));
  for(const part of input.manifest.parts){response=await fetchLocal(base+'/'+input.id+'/parts/'+part.index);const data=Buffer.from(await response.arrayBuffer());assert.equal(sha(data),part.sha256);await fetchLocal(base+'/'+input.id,{operation:'ack',index:String(part.index),sha256:part.sha256});}
  for(const resource of input.manifest.required_resources){response=await fetchLocal(resource.manifest_url);const dependency=await response.json();assert.equal(response.status,200,JSON.stringify(dependency));const buffers=[];for(const part of dependency.parts){response=await fetchLocal(part.url);buffers.push(Buffer.from(await response.arrayBuffer()));}assert.equal(sha(Buffer.concat(buffers)),resource.digest);await fetchLocal(base+'/'+input.id,{operation:'ack-resource',sha256:resource.digest});}
  response=await fetchLocal(base+'/'+input.id,{operation:'finalize'});check('HTTP input requires and verifies every exact dependency resource',(await response.json()).state==='finalized');
  const savedCookie=cookie;cookie='';response=await fetchLocal(base+'/'+stream.id);check('Different browser without private capability cannot read drafts',response.status===403);cookie=savedCookie;
  report.status='PASS';
  }
}catch(error){report.status='FAIL';report.failure=error.stack;fs.writeFileSync(path.join(output,'failure.log'),error.stack);throw error;}
finally{
  if(server){server.kill();await new Promise(resolve=>{if(server.exitCode!==null)return resolve();server.once('exit',resolve);setTimeout(resolve,3000);});}
  if(fixture){try{php(['tests/research-transport-fixture.php','cleanup',fixture.id],'cleanup');}catch(error){report.cleanupFailure=error.message;}}
  if(dbChild){try{binary('mariadb-admin',['--defaults-extra-file='+defaults,'shutdown']);}catch(error){report.shutdownFailure=error.message;dbChild.kill();}await new Promise(resolve=>{if(dbChild.exitCode!==null)return resolve();dbChild.once('exit',resolve);setTimeout(resolve,3000);});}
  if(fs.existsSync(defaults))fs.unlinkSync(defaults);
  report.databaseReleased=!await portOpen(port);report.serverReleased=!await portOpen(httpPort);report.finishedAt=new Date().toISOString();fs.writeFileSync(path.join(output,'report.json'),JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify({status:report.status,checks:report.checks.length,report:path.join(output,'report.json'),databaseReleased:report.databaseReleased,serverReleased:report.serverReleased}));
}