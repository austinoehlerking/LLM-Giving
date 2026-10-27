import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {randomBytes} from 'node:crypto';
import {root,env,phpArgs,runPhp} from '../tools/runtime.mjs';
const output=path.join(root,'storage/qa/session-capacity-race');fs.mkdirSync(output,{recursive:true});
const fixtures=JSON.parse(runPhp(['tests/session-capacity-race.php','create']));
const results=[];let checks=0;
async function race(specs){
 const key=randomBytes(8).toString('hex'),gate=path.join(output,key+'-go'),children=[];
 const ready=specs.map((_,i)=>path.join(output,key+'-'+i+'-ready'));
 const promises=specs.map((spec,i)=>new Promise((resolve,reject)=>{
  const child=spawn(process.env.PHP_BINARY||'php',[...phpArgs(),'tests/session-capacity-race.php','worker'],{cwd:root,env,windowsHide:true,stdio:['pipe','pipe','pipe']});children.push(child);
  let stdout='',stderr='';child.stdout.on('data',b=>stdout+=b);child.stderr.on('data',b=>stderr+=b);child.on('error',reject);child.on('close',code=>{if(code)reject(Error(stderr+stdout));else try{resolve(JSON.parse(stdout));}catch{reject(Error(stderr+stdout));}});
  child.stdin.end(JSON.stringify({...spec,gate,ready:ready[i]}));
 }));
 try {
  const deadline=Date.now()+12000;
  while(!ready.every(file=>fs.existsSync(file))){if(Date.now()>deadline)throw Error('Session workers did not reach their barrier.');await new Promise(resolve=>setTimeout(resolve,20));}
  fs.writeFileSync(gate,'go');return await Promise.all(promises);
 } finally {for(const child of children)if(child.exitCode===null)child.kill();}
}
const state=id=>JSON.parse(runPhp(['tests/session-capacity-race.php','state',id]));
const record=(name,value)=>{assert.ok(value,name);checks++;results.push(name);};
try{const [parallel,legacy,owner1,owner2]=fixtures.sessions;const keys=Array.from({length:4},()=>randomBytes(16).toString('hex'));let r=await race(keys.map(key=>({session:parallel,operation:'claim',key})));results.push({case:'four simultaneous claims',raw:r});record('Exactly three parallel claims win, fourth is refused',JSON.stringify(r.map(x=>x.status).sort())==='[200,200,200,409]');record('Capacity race leaves exactly three assignments/leases',state(parallel).used===3&&state(parallel).active===3&&state(parallel).leases===3);const won=keys[r.findIndex(x=>x.status===200)];const aid=r.find(x=>x.status===200).result;r=await race([0,1].map(()=>({session:parallel,operation:'claim',key:won})));record('Concurrent same-key retries return the same assignment',r.every(x=>x.status===200&&x.result===aid)&&state(parallel).used===3);r=await race([{session:parallel,operation:'release',assignment:aid},{session:parallel,operation:'renew',assignment:aid}]);results.push({case:'release versus renewal',raw:r});record('Releasing one assignment never releases siblings',r[0].status===200&&[200,409].includes(r[1].status)&&state(parallel).active===2&&state(parallel).leases===2);r=await race([0,1].map(()=>({session:parallel,operation:'claim',key:randomBytes(16).toString('hex')})));record('One freed slot admits only one of two competing requests',JSON.stringify(r.map(x=>x.status).sort())==='[200,409]'&&state(parallel).active===3);r=await race([{session:parallel,operation:'stop'},{session:parallel,operation:'claim',key:randomBytes(16).toString('hex')}]);record('Stop serializes with claims and releases all slots',r[0].status===200&&r[1].status===409&&state(parallel).stopped&&state(parallel).active===0&&state(parallel).leases===0);r=await race([0,1].map(()=>({session:legacy,operation:'claim',key:randomBytes(16).toString('hex')})));record('Legacy/default single concurrency remains one',JSON.stringify(r.map(x=>x.status).sort())==='[200,409]'&&state(legacy).active===1);r=await race([owner1,owner2].map(session=>({session,operation:'claim',key:randomBytes(16).toString('hex')})));record('Different owners cannot win the same scoped task',JSON.stringify(r.map(x=>x.status).sort())==='[200,409]');fs.writeFileSync(path.join(output,'report.json'),JSON.stringify({pass:true,checks,results,scope:'Real concurrent isolated PHP/PDO workers; synthetic fixtures only.'},null,2));console.log(checks+' session capacity race checks passed.');}
catch(error){fs.writeFileSync(path.join(output,'failure-'+Date.now()+'.json'),JSON.stringify({message:error.message,results},null,2));throw error;}
finally{runPhp(['tests/session-capacity-race.php','cleanup'],JSON.stringify(fixtures));}
