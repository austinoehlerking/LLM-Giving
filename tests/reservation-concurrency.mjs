import fs from 'node:fs';import path from 'node:path';import assert from 'node:assert/strict';import {spawn} from 'node:child_process';import {randomBytes} from 'node:crypto';import {root,env,phpArgs,runPhp} from '../tools/runtime.mjs';
const output=path.join(root,'storage/qa/reservations/concurrency');fs.mkdirSync(output,{recursive:true});
const fixtures=JSON.parse(runPhp(['tests/reservation-concurrency.php','create']));const [a,b]=fixtures,actor=()=>randomBytes(32).toString('hex');let checks=0;
const at=Math.floor(Date.now()/1000),history=[];
async function race(specs){
 const id=randomBytes(8).toString('hex'),gate=path.join(output,id+'-go'),ready=specs.map((_,i)=>path.join(output,id+'-'+i+'-ready')),children=[];
 const promises=specs.map((s,i)=>new Promise((resolve,reject)=>{
  const child=spawn(process.env.PHP_BINARY||'php',[...phpArgs(),'tests/reservation-concurrency.php','worker',s.rid,s.tid,s.actor,String(s.at??at),s.op,gate,ready[i]],{cwd:root,env,windowsHide:true,stdio:['ignore','pipe','pipe']});children.push(child);let stdout='',stderr='';child.stdout.on('data',b=>stdout+=b);child.stderr.on('data',b=>stderr+=b);child.on('error',reject);child.on('close',code=>{if(code)reject(Error(stderr+stdout));else try{resolve(JSON.parse(stdout));}catch{reject(Error(stderr+stdout));}});
 }));
 try{const deadline=Date.now()+12000;while(!ready.every(f=>fs.existsSync(f))){if(Date.now()>deadline)throw Error('Workers did not reach the barrier.');await new Promise(r=>setTimeout(r,20));}fs.writeFileSync(gate,'go');const results=await Promise.all(promises);history.push({specs,results});return results;}
 finally{for(const c of children)if(c.exitCode===null)c.kill();}
}
const state=f=>JSON.parse(runPhp(['tests/reservation-concurrency.php','state',f.rid]));
const singleWinner=results=>{assert.deepEqual(results.map(r=>r.status).sort(),[200,409]);checks++;};
try{
 const x=actor(),y=actor();
 let results=await race([{...a,actor:x,op:'claim'},{...a,actor:y,op:'claim'}]);singleWinner(results);
 assert.equal(state(a).leases.filter(r=>!r.ended_at).length,1);checks++;
 const winner=results[0].status===200?x:y;
 await race([{...a,actor:winner,op:'release'}]);
 const sequential=actor();results=await race([{...a,actor:sequential,op:'claim'},{...b,actor:sequential,op:'claim'}]);singleWinner(results);
 const held=results[0].status===200?a:b;await race([{...held,actor:sequential,op:'release'}]);
 const old=actor();await race([{...a,actor:old,op:'claim'}]);
 results=await race([{...a,actor:old,at:at+3600,op:'submit'},{...a,actor:actor(),at:at+3600,op:'claim'}]);assert.equal(results[0].status,409);assert.equal(results[1].status,200);assert.equal(state(a).results.length,0);checks+=3;
 const holder=results[1].result.actor_hash;
 results=await race([{...a,actor:holder,at:at+3601,op:'renew'},{...a,actor:holder,at:at+3601,op:'submit'}]);
 assert.equal(results[1].status,200);assert.ok([200,409].includes(results[0].status));assert.equal(state(a).results.length,1);assert.equal(state(a).leases.filter(r=>!r.ended_at).length,0);checks+=4;
 console.log(checks+' concurrent reservation assertions passed.');fs.writeFileSync(path.join(output,'report.json'),JSON.stringify({checks,history},null,2));
}finally{runPhp(['tests/reservation-concurrency.php','cleanup',...fixtures.map(f=>f.rid)]);}
