/** Focused owner: isolated database, actual PDO query, server/API tests and optional repo browser QA. */
import fs from 'node:fs';import path from 'node:path';import {spawn,spawnSync} from 'node:child_process';import {randomBytes} from 'node:crypto';import assert from 'node:assert/strict';
import {root,env,runPhp,spawnPhp,portOpen,ready} from './runtime.mjs';
const stamp=new Date().toISOString().replace(/[:.]/g,'-'),output=path.join(root,'storage/qa','agent-access-'+stamp);fs.mkdirSync(output,{recursive:true});
const maria=process.env.MARIADB_BIN||path.join(process.env.USERPROFILE,'scoop/apps/mariadb/current/bin'),port=20841,httpPort=20842;
const password=randomBytes(24).toString('hex'),database='agent_'+randomBytes(8).toString('hex'),datadir=path.join(output,'db'),defaults=path.join(output,'private-client.cnf');
const extra={APP_ENV:'test',APP_URL:'http://127.0.0.1:'+httpPort,DB_HOST:'127.0.0.1',DB_PORT:String(port),DB_NAME:database,DB_USER:'root',DB_PASSWORD:password,GOOGLE_ANALYTICS_ENABLED:'0',RATE_LIMIT_NAMESPACE:'agent-'+stamp,QA_PORT:String(httpPort)};
const report={status:'RUNNING',checks:[],source:'Focused synthetic agent access; no real scientific validation.'};
let databaseProcess,server;
const binary=(name,args,input)=>{const r=spawnSync(path.join(maria,name+'.exe'),args,{input,encoding:'utf8',env:{...env,...extra},windowsHide:true});if(r.error||r.status!==0)throw Error(name+' failed: '+(r.error?.message||r.stderr));return r.stdout;};
const php=(args,name)=>{const value=runPhp(args,undefined,extra);if(name)fs.writeFileSync(path.join(output,name+'.log'),value);return value;};
function check(label,condition){assert.ok(condition,label);report.checks.push(label);}
try{
 if(await portOpen(port)||await portOpen(httpPort))throw Error('Owned agent test port is occupied; do not stop another task.');
 binary('mariadb-install-db',['--datadir='+datadir,'--password='+password,'--port='+port]);
 const log=fs.openSync(path.join(output,'database.log'),'a');databaseProcess=spawn(path.join(maria,'mariadbd.exe'),['--no-defaults','--datadir='+datadir,'--port='+port,'--bind-address=127.0.0.1','--max-allowed-packet=32M','--console'],{cwd:root,env:{...env,...extra},windowsHide:true,stdio:['ignore',log,log]});fs.closeSync(log);
 for(let i=0;i<100&&!await portOpen(port);i++){if(databaseProcess.exitCode!==null)throw Error('Owned database exited.');await new Promise(r=>setTimeout(r,150));}
 if(!await portOpen(port))throw Error('Owned database readiness failed.');
 fs.writeFileSync(defaults,'[client]\nuser=root\npassword='+password+'\nhost=127.0.0.1\nport='+port+'\n',{mode:0o600});
 binary('mariadb',['--defaults-extra-file='+defaults],`CREATE DATABASE ${database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`);
 php(['bin/console.php','migrate'],'migrations');php(['-r',"require 'src/bootstrap.php'; if(!one('SELECT id FROM categories LIMIT 1'))throw new RuntimeException('Actual PDO query failed'); echo 'PDO ready';"],'app-query');
 if(!process.argv.includes('--browser-only')){
 const focused=JSON.parse(php(['tests/agent-access.php'],'server'));report.serverChecks=focused.checks;
 for(const file of ['volunteer-sessions','session-review-regressions','session-contribution-identity'])php(['tests/'+file+'.php'],file);
 const f=JSON.parse(php(['tests/agent-access-fixture.php']));
 server=spawnPhp(['-S','127.0.0.1:'+httpPort,'-t','public','public/router.php'],extra);await ready(extra.APP_URL,server);
 const base='/api/agent/v1/sessions/'+f.session_id;
 async function api(tail='',data,headers={}){return fetch(extra.APP_URL+base+tail,{method:data===undefined?'GET':'POST',headers:{Authorization:'Bearer '+f.token,...(data===undefined?{}:{'Content-Type':'application/json'}),...headers},...(data===undefined?{}:{body:JSON.stringify(data)}),redirect:'manual'});}
 let r=await fetch(extra.APP_URL+base);check('HTTP requires bearer without browser session',r.status===401&&!r.headers.has('set-cookie'));
 r=await api('',undefined,{Cookie:'llmgiving=untrusted'});check('HTTP rejects ambient browser cookie',r.status===403);
 r=await api('',undefined,{Origin:extra.APP_URL});check('HTTP rejects browser-origin bearer use',r.status===403);
 r=await fetch(extra.APP_URL+'/agents/guide');check('Public protocol guide is available from packaged runtime source',r.status===200&&(await r.text()).includes('POST /claim'));
 r=await api('/instructions');const guidance=await r.json();check('Private start discovers complete API protocol',r.status===200&&guidance.data.instructions.includes('POST /assignments/{id}/submit'));
 r=await api();let home=await r.json();check('HTTP returns live session without issuing a browser cookie',r.status===200&&home.ok&&!r.headers.has('set-cookie'));
 const key=home.data.claim_key;r=await api('/claim',{claim_key:key});let claimed=await r.json();assert.equal(r.status,200,JSON.stringify(claimed));const aid=claimed.data.id;
 r=await api('/claim',{claim_key:key});check('HTTP claim retry recovers original assignment',(await r.json()).data.id===aid);
 r=await api('/assignments/'+aid+'/handoff');const handoff=await r.json();check('HTTP handoff exposes exact body and dependency directory',r.status===200&&handoff.data.body&&handoff.data.context_directory);
 r=await api('/assignments/'+aid+'/notes',{version:0,notes:{next_step:'Synthetic next step'}});check('HTTP stores private resumable research',r.status===200&&(await r.json()).data.version===1);
 r=await api('/assignments/'+aid+'/notes',{version:0,notes:{next_step:'Conflicting old draft'}});check('HTTP rejects stale note overwrite',r.status===409);
 r=await api('/assignments/'+aid+'/check',{packet:{}});check('HTTP returns actionable shared validation errors',r.status===422&&(await r.json()).ok===false);
 const packet=JSON.parse(php(['tests/agent-access-fixture.php','packet',f.session_id,aid]));
 r=await api('/assignments/'+aid+'/check',{packet});let checked=await r.json();assert.equal(r.status,200,JSON.stringify(checked));check('HTTP shared Check records nothing',checked.data.checked&&!checked.data.recorded);
 r=await api('/assignments/'+aid+'/submit',{packet});let recorded=await r.json();assert.equal(r.status,200,JSON.stringify(recorded));check('HTTP Submit returns recorded receipt',recorded.data.receipt.recorded&&recorded.data.receipt.admission==='accepted');
 r=await api('/assignments/'+aid+'/submit',{packet});check('HTTP identical Submit preserves recorded ID',(await r.json()).data.receipt.result_id===recorded.data.receipt.result_id);
 r=await api('/claim',{claim_key:randomBytes(16).toString('hex')});const second=await r.json();assert.equal(r.status,200,JSON.stringify(second));const secondId=second.data.id;
 await api('/assignments/'+secondId+'/notes',{version:0,notes:{next_step:'Resume after human authorization'}});
 r=await api('/stop',{});check('HTTP Stop ends existing permission',r.status===200&&(await r.json()).data.state==='stopped');
 r=await api('/assignments/'+secondId+'/notes');check('HTTP Stop retains recovery reads',r.status===200&&(await r.json()).data.notes.next_step==='Resume after human authorization');
 r=await api('/assignments/'+secondId+'/renew',{});check('HTTP Stop rejects renewal',r.status===409);
 r=await api('/claim',{claim_key:randomBytes(16).toString('hex')});check('HTTP Stop rejects new pickup',r.status===409);
 php(['tests/agent-access-fixture.php','revoke',f.session_id]);r=await api();check('HTTP revocation rejects the former token',r.status===401);
 server.kill();await new Promise(resolve=>{server.once('exit',resolve);setTimeout(resolve,3000);});server=null;
 }
 if(process.argv.includes('--browser')||process.argv.includes('--browser-only')){
   const qa=spawnSync(process.execPath,['tools/qa.mjs','agent-access'],{cwd:root,env:{...env,...extra},encoding:'utf8',windowsHide:true,maxBuffer:8*1024*1024});
   fs.writeFileSync(path.join(output,'browser-run.log'),qa.stdout+qa.stderr);if(qa.error||qa.status!==0)throw Error('Repo agent-access browser QA failed: '+qa.stdout+qa.stderr);
 }
 report.status='PASS';
}catch(e){report.status='FAIL';report.failure=e.stack;throw e;}
finally{
 if(server)server.kill();
 if(databaseProcess){try{binary('mariadb-admin',['--defaults-extra-file='+defaults,'shutdown']);}catch{databaseProcess.kill();}await new Promise(resolve=>{if(databaseProcess.exitCode!==null)return resolve();databaseProcess.once('exit',resolve);setTimeout(resolve,3000);});}
 if(fs.existsSync(defaults))fs.unlinkSync(defaults);
 report.databaseReleased=!await portOpen(port);report.serverReleased=!await portOpen(httpPort);fs.writeFileSync(path.join(output,'report.json'),JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify({status:report.status,serverChecks:report.serverChecks?.length,httpChecks:report.checks.length,report:path.join(output,'report.json'),databaseReleased:report.databaseReleased,serverReleased:report.serverReleased}));
}
