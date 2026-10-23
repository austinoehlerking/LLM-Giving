import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {randomBytes} from 'node:crypto';
import {root,env,phpArgs,runPhp} from '../tools/runtime.mjs';
const output=path.join(root,'storage/qa/volunteers/concurrency');fs.mkdirSync(output,{recursive:true});
const fixtures=JSON.parse(runPhp(['tests/volunteer-concurrency.php','create']));
const results=[];let checks=0;
async function race(specs){
 const key=randomBytes(8).toString('hex'),gate=path.join(output,key+'-go'),children=[];
 const ready=specs.map((_,i)=>path.join(output,key+'-'+i+'-ready'));
 const promises=specs.map((spec,i)=>new Promise((resolve,reject)=>{
  const child=spawn(process.env.PHP_BINARY||'php',[...phpArgs(),'tests/volunteer-concurrency.php','worker'],{cwd:root,env,windowsHide:true,stdio:['pipe','pipe','pipe']});children.push(child);
  let stdout='',stderr='';child.stdout.on('data',b=>stdout+=b);child.stderr.on('data',b=>stderr+=b);child.on('error',reject);child.on('close',code=>{if(code)reject(Error(stderr+stdout));else try{resolve(JSON.parse(stdout));}catch{reject(Error(stderr+stdout));}});
  child.stdin.end(JSON.stringify({...spec,gate,ready:ready[i]}));
 }));
 try {
  const deadline=Date.now()+12000;
  while(!ready.every(file=>fs.existsSync(file))){if(Date.now()>deadline)throw Error('Session workers did not reach their barrier.');await new Promise(resolve=>setTimeout(resolve,20));}
  fs.writeFileSync(gate,'go');return await Promise.all(promises);
 } finally {for(const child of children)if(child.exitCode===null)child.kill();}
}
const state=id=>JSON.parse(runPhp(['tests/volunteer-concurrency.php','state',id]));
const record=(name,value)=>{assert.ok(value,name);checks++;results.push(name);};
try {
 const s=fixtures.sessions;
 let r=await race([{session:s[0],operation:'claim'},{session:s[0],operation:'claim'}]);
 record('Simultaneous same-session pickup returns one shared assignment',r.every(x=>x.status===200)&&r[0].result===r[1].result);
 record('Same-session race consumes exactly one allowance and one reservation',state(s[0]).used===1&&state(s[0]).assignments===1&&state(s[0]).live===1);
 r=await race([{session:s[1],operation:'claim'},{session:s[2],operation:'claim'}]);
 record('Different sessions competing for one task produce one winner',JSON.stringify(r.map(x=>x.status).sort())==='[200,409]');
 record('Losing session consumes no assignment',state(s[1]).used+state(s[2]).used===1);
 r=await race([{session:s[3],operation:'claim'},{session:s[4],operation:'claim'}]);
 record('One actor cannot use two sessions to hold simultaneous tasks',JSON.stringify(r.map(x=>x.status).sort())==='[200,409]');
 record('Cross-session actor race records only the winning assignment',state(s[3]).used+state(s[4]).used===1);
 r=await race([{session:s[5],operation:'claim'},{session:s[5],operation:'stop'}]);
 record('Stop serializes with pickup and always blocks later work',r[1].status===200&&[200,409].includes(r[0].status)&&state(s[5]).stopped&&state(s[5]).live===0);
 runPhp(['tests/volunteer-concurrency.php','claim',s[6]]);
 r=await race([{session:s[6],operation:'record'},{session:s[6],operation:'stop'}]);
 record('Stop versus recording has a real recorded-or-not-recorded outcome',r[1].status===200&&[200,409].includes(r[0].status)&&state(s[6]).results===(r[0].status===200?1:0));
 record('Stop versus recording leaves no active task and retains its consumed allowance',state(s[6]).live===0&&state(s[6]).used===1&&state(s[6]).stopped);
 runPhp(['tests/volunteer-concurrency.php','claim',s[7]]);runPhp(['tests/volunteer-concurrency.php','expire-task',s[7]]);
 r=await race([{session:s[7],operation:'stop'},{session:s[8],operation:'claim'}]);
 record('Expired holder Stop and another session pickup both succeed without lock inversion',r.every(x=>x.status===200));
 record('Stopping an expired holder never releases the new holder reservation',state(s[7]).stopped&&state(s[7]).used===1&&state(s[7]).live===0&&state(s[8]).used===1&&state(s[8]).live===1);
 fs.writeFileSync(path.join(output,'report.json'),JSON.stringify({pass:true,checks,results,scope:'Independent local PHP/PDO workers, synthetic workflow evidence; no scientific or AI-execution claim.'},null,2));
 console.log(checks+' concurrent volunteer session checks passed.');
} finally {runPhp(['tests/volunteer-concurrency.php','cleanup'],JSON.stringify(fixtures));}
