import fs from 'node:fs';import path from 'node:path';import {spawn} from 'node:child_process';import {randomBytes} from 'node:crypto';import {root,env,phpArgs,runPhp} from '../tools/runtime.mjs';
const fixtureScript='tests/reservation-grant-race.php',output=path.join(root,'storage/qa/reservations/grant-race-'+randomBytes(6).toString('hex'));fs.mkdirSync(output,{recursive:true});
const checks=[],cases=[];const check=(name,ok)=>checks.push({name,ok});const at=Math.floor(Date.now()/1000);
function fixture(name){const file=path.join(output,name+'-private-fixture.json');fs.writeFileSync(file,runPhp([fixtureScript,'create']),{mode:0o600});return file;}
function cleanup(file){try{runPhp([fixtureScript,'cleanup',file]);}finally{fs.unlinkSync(file);}}
const ownerFile=fixture('original-owner');
try{
 const owner=JSON.parse(runPhp([fixtureScript,'holder-controls',ownerFile]));
 check('Default private return still requires exchanged session',owner.strict_return_rejected);
 check('Original holder did not exchange private fragment',!owner.scoped_session_present);
 for(const operation of ['renew','release','claim'])check('Original holder can '+operation+' without fragment exchange',owner.statuses[operation]===200);
 check('Original holder identity is preserved',owner.owner_preserved);
 const rejected=JSON.parse(runPhp([fixtureScript,'unauthorized',ownerFile]));
 check('Neither holder nor delegated session cannot mutate',rejected.status===403);
 check('Unauthorized request leaves leases unchanged',rejected.leases_unchanged);
}finally{cleanup(ownerFile);}
for(const transport of ['delegated','holder'])for(const operation of ['claim','renew','release']){
 const name=transport+'-'+operation,file=fixture(name),ready=path.join(output,name+'-ready.json'),gate=path.join(output,name+'-go');let child;
 try{
  const pending=new Promise((resolve,reject)=>{child=spawn(process.env.PHP_BINARY||'php',[...phpArgs(),fixtureScript,'request',file,operation,gate,ready,String(at+120),transport],{cwd:root,env,windowsHide:true,stdio:['ignore','pipe','pipe']});let stdout='',stderr='';child.stdout.on('data',b=>stdout+=b);child.stderr.on('data',b=>stderr+=b);child.on('error',reject);child.on('close',code=>{if(code)reject(Error(stderr+stdout));else try{resolve(JSON.parse(stdout));}catch{reject(Error(stderr+stdout));}});});
  const deadline=Date.now()+12000;while(!fs.existsSync(ready)){if(Date.now()>deadline)throw Error('Request did not reach grant-load barrier.');await new Promise(r=>setTimeout(r,20));}
  check(name+': request loaded unused grant before recording',JSON.parse(fs.readFileSync(ready)).loaded_unused);
  runPhp([fixtureScript,'record',file,String(at+10)]);
  if(operation!=='claim')runPhp([fixtureScript,'owner-reacquire',file,String(at+20)]);
  const before=JSON.parse(runPhp([fixtureScript,'state',file]));
  check(name+': progress consumed grant and ended original lease',before.consumed&&before.old_lease_ended);
  fs.writeFileSync(gate,'resume after progress transaction committed');
  const response=await pending,after=JSON.parse(runPhp([fixtureScript,'state',file]));
  check(name+': consumed grant rejected',response.status===409);
  check(name+': every lease unchanged',before.lease_hash===after.lease_hash);
  check(name+': consumed grant unchanged',before.grant_hash===after.grant_hash);
  check(name+': no duplicate result',after.result_count===1);
  cases.push({transport,operation,events:['grant loaded','progress committed',...(operation==='claim'?[]:['holder legitimately reacquired later lease']),'stale request released from barrier'],before,response,after});
 }finally{if(child&&child.exitCode===null)child.kill();cleanup(file);}
}
const failures=checks.filter(x=>!x.ok);fs.writeFileSync(path.join(output,'report.json'),JSON.stringify({checks,cases},null,2));console.log(JSON.stringify({assertions:checks.length,failed:failures.map(x=>x.name),output}));if(failures.length)process.exitCode=1;else console.log(checks.length+' scoped-grant mutation assertions passed.');
