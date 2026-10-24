/** Offline metadata/security regressions. No live API, application, database or browser. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {POLICY as P,hash,marker,readMarker,readState,batchDigest,developGate,batchReady,assertQa,releaseGate,assertLocalReady,releaseRunName} from '../tools/github-delivery-policy.mjs';
import {createClient,refresh,authorize,loadSnapshot} from '../tools/github-delivery-runner.mjs';
import {verifyGithubReady} from '../tools/github-delivery-readiness.mjs';
const A='a'.repeat(40),H='b'.repeat(40),M='c'.repeat(40),C='d'.repeat(40),T='e'.repeat(40),X='f'.repeat(40);
const actor={login:P.trustedActor},repo={id:42,full_name:P.repository,default_branch:'main'};
const date=n=>'2026-10-23T12:'+String(n).padStart(2,'0')+':00Z';
const comment=(id,body,n=1,user=actor)=>({id,body,user,created_at:date(n),updated_at:date(n)});
const stateBody=state=>'Delivery control\n<!-- llm-delivery-state:v1 -->\n\x60\x60\x60json\n'+JSON.stringify(state,null,2)+'\n\x60\x60\x60\n';
function fixture(){
  const state={schemaVersion:1,activeBatch:{id:'batch-a',membershipClosed:true,phase:'qa_ready',baseMain:A,candidate:{sha:C,tree:T},blockers:[],tasks:[{id:'dev-1',pr:1,head:H,status:'merged'}]},queuedNextBatch:[]};
  const head=(sha,ref)=>({sha,ref,repo});
  const first={number:1,state:'closed',draft:false,merged:true,merge_commit_sha:M,head:head(H,'codex/component'),base:{ref:'develop',repo},body:marker('task',{batchId:'batch-a',taskId:'dev-1'})};
  const release={number:2,state:'open',draft:false,merged:false,head:head(C,'develop'),base:{ref:'main',repo},body:'Release approved candidate'};
  const review=comment(10,marker('review',{schemaVersion:1,sha:H,verdict:'pass',taskId:'dev-1',reviewerThread:P.reviewerThread}));
  const s={repository:repo,issue:{number:20,user:actor,state:'open',body:stateBody(state),updated_at:date(1)},refs:{main:{sha:A,tree:X},develop:{sha:C,tree:T}},pulls:[first,release],prData:{1:{reviews:[],comments:[review],threads:[]},2:{reviews:[],comments:[],threads:[]}},issueComments:[],evidence:{},manualRuns:{},statuses:[{...comment(900,'',2),context:P.contexts.batch,state:'success',creator:{login:'github-actions[bot]'},sha:C,description:'batch:'+batchDigest(state),target_url:'https://github.com/'+P.repository+'/actions/runs/88'}],publisher:{workflow:{id:99,path:P.deliveryWorkflow},run:{id:88,workflow_id:99,path:P.deliveryWorkflow,repository:repo,event:'workflow_run',head_branch:'main',head_sha:A,status:'completed',conclusion:'success',run_started_at:date(0),updated_at:date(3)}},ancestry:{}};
  for(const [base,head]of [[A,C],[H,M],[M,C]])s.ancestry[base+'...'+head]={base,head,ancestor:true};
  return {s,state,first,release,save(){s.issue.body=stateBody(state);}};
}
function dev(){
  const f=fixture();f.state.activeBatch.phase='developing';f.state.activeBatch.membershipClosed=false;f.state.activeBatch.candidate=null;f.state.activeBatch.tasks[0].status='reviewed';f.first.state='open';f.first.merged=false;f.save();return f;
}
function approved(){
  const f=fixture();f.state.activeBatch.phase='qa_passed';f.save();const d=batchDigest(f.state);
  const url='https://github.com/'+P.repository+'/issues/20#issuecomment-50';
  const body='QA report: focused delivery-policy checks passed. No merge or deployment performed.';
  f.s.evidence[url]={...comment(50,body),html_url:url};
  f.s.issueComments=[comment(60,marker('qa',{schemaVersion:1,batchId:'batch-a',sha:C,tree:T,batchDigest:d,verdict:'pass',reviewerThread:P.qaThread,evidence:[{url,sha256:hash(body)}]}),3)];
  const auth={schemaVersion:1,batchId:'batch-a',pr:2,sha:C,tree:T,batchDigest:d,runId:7,runAttempt:1};
  f.s.prData[2].comments=[comment(70,marker('release',auth),4,{login:'github-actions[bot]'})];
  f.s.manualRuns[7]={workflow:{id:77,path:P.manualWorkflow},run:{id:7,run_attempt:1,workflow_id:77,path:P.manualWorkflow,repository:repo,event:'workflow_dispatch',head_branch:'main',head_sha:A,actor,triggering_actor:actor,status:'completed',conclusion:'success',display_title:releaseRunName(2,C,d)},approvals:[{state:'approved',user:actor,environments:[{name:P.environment}]}]};
  return f;
}
let count=0;const names=[];
async function test(name,fn){await fn();count++;names.push(name);}
const reject=(f,call,pattern=/./)=>assert.throws(()=>call(f),pattern);
await test('reviewed registered develop PR passes',()=>{const f=dev();assert.equal(developGate(f.s,f.first).status,'PASS');});
await test('direct-main feature PR fails',()=>{const f=dev();f.first.base.ref='main';reject(f,x=>releaseGate(x.s,x.first),/develop release/);});
for(const key of ['reviewerThread','sha','taskId'])await test('missing/stale/forged '+key+' review fails',()=>{const f=dev();const m={schemaVersion:1,sha:H,verdict:'pass',taskId:'dev-1',reviewerThread:P.reviewerThread};m[key]=key==='sha'?X:'forged';f.s.prData[1].comments[0].body=marker('review',m);reject(f,x=>developGate(x.s,x.first),/review PASS/);});
await test('untrusted actor cannot provide review marker',()=>{const f=dev();f.s.prData[1].comments[0].user={login:'stranger'};reject(f,x=>developGate(x.s,x.first));});
await test('missing review fails',()=>{const f=dev();f.s.prData[1].comments=[];reject(f,x=>developGate(x.s,x.first));});
await test('latest matching review verdict wins',()=>{const f=dev();f.s.prData[1].comments.push(comment(11,marker('review',{schemaVersion:1,sha:H,verdict:'changes_requested',taskId:'dev-1',reviewerThread:P.reviewerThread}),2));reject(f,x=>developGate(x.s,x.first));f.s.prData[1].comments.push({...f.s.prData[1].comments[0],id:12,updated_at:date(3)});assert.equal(developGate(f.s,f.first).status,'PASS');});
await test('edited comment order uses updated timestamp',()=>{const f=dev();f.s.prData[1].comments.push(comment(11,marker('review',{schemaVersion:1,sha:H,verdict:'changes_requested',taskId:'dev-1',reviewerThread:P.reviewerThread}),2));f.s.prData[1].comments[0].updated_at=date(3);assert.equal(developGate(f.s,f.first).status,'PASS');});
await test('outstanding native changes requested blocks marker pass',()=>{const f=dev();f.s.prData[1].reviews=[{...comment(30,'Please fix'),state:'CHANGES_REQUESTED',commit_id:H}];reject(f,x=>developGate(x.s,x.first),/CHANGES_REQUESTED/);f.s.prData[1].reviews.push({...comment(31,'Fixed',2),state:'COMMENTED',commit_id:H});reject(f,x=>developGate(x.s,x.first));f.s.prData[1].reviews.push({...comment(32,'Approved',3),state:'APPROVED',commit_id:H});assert.equal(developGate(f.s,f.first).status,'PASS');});
await test('all unresolved threads block conservatively',()=>{const f=dev();f.s.prData[1].threads=[{isResolved:false}];reject(f,x=>developGate(x.s,x.first),/thread/);});
await test('review marker must match actual review commit',()=>{const f=dev();f.s.prData[1].reviews=[{...f.s.prData[1].comments[0],state:'APPROVED',commit_id:X}];f.s.prData[1].comments=[];reject(f,x=>developGate(x.s,x.first),/reviewed commit/);});
for(const status of ['in_progress','in_review','deferred'])await test('task '+status+' cannot merge to develop',()=>{const f=dev();Object.assign(f.state.activeBatch.tasks[0],{status,reason:'Later',humanDecision:'Human explicitly deferred'});f.save();reject(f,x=>developGate(x.s,x.first));});
await test('unregistered head and stale binding fail',()=>{const f=dev();f.state.activeBatch.tasks=[];f.save();reject(f,x=>developGate(x.s,x.first));});
for(const phase of ['qa_running','qa_passed'])await test('freeze during '+phase,()=>{const f=dev();f.state.activeBatch.phase=phase;f.save();reject(f,x=>developGate(x.s,x.first),/frozen/);});
await test('forked PR cannot merge',()=>{const f=dev();f.first.head={...f.first.head,repo:{id:44,full_name:'other/fork'}};reject(f,x=>developGate(x.s,x.first),/same repository/);});
await test('closed integrated batch readiness passes',()=>assert.equal(batchReady(fixture().s).status,'PASS'));
for(const mutate of [f=>f.state.activeBatch.membershipClosed=false,f=>f.state.activeBatch.tasks=[],f=>f.state.activeBatch.blockers=['finding'],f=>f.state.activeBatch.tasks[0].status='reviewed',f=>f.state.activeBatch.candidate.sha=X,f=>f.state.activeBatch.candidate.tree=X,f=>f.s.refs.main.sha=X,f=>f.first.head.sha=X,f=>f.first.base.ref='main',f=>f.first.merged=false,f=>delete f.s.ancestry[H+'...'+M]])await test('readiness rejects incomplete/mismatched integration '+(count+1),()=>{const f=fixture();mutate(f);f.save();reject(f,x=>batchReady(x.s));});
await test('deferred task requires explicit human decision',()=>{const f=fixture();f.state.activeBatch.tasks.push({id:'later',pr:null,head:null,status:'deferred',reason:'Later'});f.save();reject(f,x=>batchReady(x.s),/humanDecision/);});
await test('explicit human deferral is terminal',()=>{const f=fixture();f.state.activeBatch.tasks.push({id:'later',pr:null,head:null,status:'deferred',reason:'Later batch',humanDecision:'Human explicitly deferred this work'});f.save();assert.equal(batchReady(f.s).status,'PASS');});
await test('open unregistered PR blocks until exact next-batch queue',()=>{const f=fixture();f.s.pulls.push({...f.first,number:3,state:'open',merged:false});reject(f,x=>batchReady(x.s),/queued/);f.state.queuedNextBatch=[{pr:3,head:H,reason:'After this fixed batch'}];f.save();assert.equal(batchReady(f.s).status,'PASS');f.state.queuedNextBatch[0].head=X;f.save();reject(f,x=>batchReady(x.s));});
await test('digest excludes reporting phase and order but binds head/closed/tasks',()=>{const f=fixture(),before=batchDigest(f.state);f.state.activeBatch.phase='qa_passed';assert.equal(batchDigest(f.state),before);f.state.activeBatch.tasks[0].head=X;assert.notEqual(batchDigest(f.state),before);});
await test('QA and successful manually approved release passes',()=>{const f=approved();assert.equal(releaseGate(f.s,f.release).status,'PASS');});
await test('fake QA role is not accepted',()=>{const f=approved();f.s.issueComments[0].body=f.s.issueComments[0].body.replace(P.qaThread,P.reviewerThread);reject(f,x=>releaseGate(x.s,x.release),/QA/);});
await test('QA digest mismatch fails',()=>{const f=approved();f.s.issueComments[0].body=f.s.issueComments[0].body.replace(batchDigest(f.state),'0'.repeat(64));reject(f,x=>releaseGate(x.s,x.release),/digest/);});
await test('tampered QA evidence fails',()=>{const f=approved();Object.values(f.s.evidence)[0].body+=' altered';reject(f,x=>releaseGate(x.s,x.release),/hash mismatch/);});
await test('evidence private paths are not public proof',()=>{const f=approved();const e=Object.values(f.s.evidence)[0];e.body='Private evidence D:\\private\\file';const q=readMarker(f.s.issueComments[0].body,'qa');q.evidence[0].sha256=hash(e.body);f.s.issueComments[0].body=marker('qa',q);reject(f,x=>releaseGate(x.s,x.release),/unsanitized/);});
await test('missing manual run cannot be replaced by marker text',()=>{const f=approved();delete f.s.manualRuns[7];reject(f,x=>releaseGate(x.s,x.release),/workflow run identity/);});
for(const mutate of [r=>r.run.conclusion='failure',r=>r.run.status='in_progress',r=>r.run.event='push',r=>r.run.head_branch='develop',r=>r.run.actor={login:'stranger'},r=>r.run.triggering_actor={login:'stranger'},r=>r.workflow.path='other.yml',r=>r.run.workflow_id=999,r=>r.run.run_attempt=2,r=>r.run.display_title='unbound',r=>r.approvals=[],r=>r.approvals[0].user={login:'stranger'},r=>r.approvals[0].environments=[{name:'other'}]])await test('release rejects unapproved/run identity mismatch '+(count+1),()=>{const f=approved();mutate(f.s.manualRuns[7]);reject(f,x=>releaseGate(x.s,x.release));});
await test('human typed authorization cannot impersonate workflow bot',()=>{const f=approved();f.s.prData[2].comments[0].user=actor;reject(f,x=>releaseGate(x.s,x.release));});
await test('duplicate/malformed state and marker fail closed',()=>{const f=fixture();f.s.issue.body+=f.s.issue.body;reject(f,x=>readState(x.s.issue));assert.throws(()=>readMarker('<!-- llm-delivery-review {"x":1}','review'));assert.throws(()=>readMarker(marker('task',{})+marker('task',{}),'task'));});
await test('JSON code-injection inputs remain rejected data',()=>{const f=dev();f.s.prData[1].comments[0].body='<!-- llm-delivery-review {"schemaVersion":1,"sha":"$(touch SHOULD_NOT_EXIST)","verdict":"pass","taskId":"dev-1","reviewerThread":"'+P.reviewerThread+'"} -->';reject(f,x=>developGate(x.s,x.first));assert.equal(fs.existsSync('SHOULD_NOT_EXIST'),false);assert.throws(()=>readMarker('<!-- llm-delivery-task {"x": process.exit(0)} -->','task'));});
await test('live local readiness binds local membership/candidate and GitHub status',async()=>{const f=fixture();const local={batchId:'batch-a',baseMain:A,candidate:{sha:C,tree:T},tasks:[{id:'dev-1',status:'merged',commit:H}]};assert.equal((await verifyGithubReady(local,{snapshot:f.s})).status,'PASS');local.candidate.sha=X;assert.throws(()=>assertLocalReady(f.s,local),/Local and GitHub/);});
await test('fake/stale successful status cannot authorize local QA',()=>{const f=fixture();f.s.statuses[0].creator={login:'stranger'};assert.throws(()=>assertLocalReady(f.s,{batchId:'batch-a',baseMain:A,candidate:{sha:C,tree:T},tasks:[{id:'dev-1',status:'merged',commit:H}]}),/status/);});
await test('API adapter confines token to fixed GitHub routes',async()=>{let calls=0;const api=createClient({token:'synthetic-only',fetchImpl:async(url,o)=>{calls++;assert.equal(new URL(url).origin,'https://api.github.com');assert.equal(o.redirect,'error');return new Response('[]',{status:200});}});await api.pages('/repos/'+P.repository+'/pulls');assert.equal(calls,1);for(const route of ['https://evil.invalid','/repos/'+P.repository+'/../../other','/repos/'+P.repository+'/%2e%2e/evil'])await assert.rejects(()=>api.request(route),/Untrusted API/);assert.equal(calls,1);});
await test('API missing/error data fails closed',async()=>{for(const response of [new Response('{}',{status:403}),new Response('{"errors":[{"message":"no"}]}',{status:200})]){const api=createClient({fetchImpl:async()=>response});await assert.rejects(()=>api.request('/graphql'));}});
await test('runner rejects untrusted execution context before requests',async()=>{let calls=0;const client={request:()=>{calls++;}};await assert.rejects(()=>refresh(client,{GITHUB_REPOSITORY:P.repository,GITHUB_WORKFLOW_REF:P.repository+'/'+P.deliveryWorkflow+'@refs/pull/1/merge',GITHUB_RUN_ID:'1'}),/trusted main/);await assert.rejects(()=>authorize(client,{GITHUB_REPOSITORY:P.repository,GITHUB_WORKFLOW_REF:P.repository+'/'+P.manualWorkflow+'@refs/heads/main',GITHUB_RUN_ID:'1',GITHUB_REF:'refs/heads/develop',GITHUB_EVENT_NAME:'workflow_dispatch',GITHUB_ACTOR:P.trustedActor}));assert.equal(calls,0);});
await test('missing control issue revokes earlier statuses before failing',async()=>{
 const f=fixture(),writes=[],prefix='/repos/'+P.repository;
 const client={pages:async()=>[f.release],request:async(route,options={})=>{
  if(options.method==='POST'){writes.push(options.body);return {};}
  if(route===prefix)return repo;
  if(route===prefix+'/branches/main')return {commit:{sha:A}};
  if(route===prefix+'/branches/develop')return {commit:{sha:C}};
  if(route===prefix+'/git/commits/'+A)return {sha:A,tree:{sha:X}};
  if(route===prefix+'/git/commits/'+C)return {sha:C,tree:{sha:T}};
  throw new Error('Synthetic missing control issue');
 }};
 await assert.rejects(()=>refresh(client,{GITHUB_REPOSITORY:P.repository,GITHUB_WORKFLOW_REF:P.repository+'/'+P.deliveryWorkflow+'@refs/heads/main',GITHUB_RUN_ID:'10'}),/missing control issue/);
 assert.deepEqual(writes.map(x=>x.state),['pending','pending','failure','failure']);
});
await test('latest QA declaration with stale candidate cannot inherit earlier pass',()=>{const f=approved(),old=readMarker(f.s.issueComments[0].body,'qa');f.s.issueComments.push(comment(61,marker('qa',{...old,sha:X}),5));reject(f,x=>releaseGate(x.s,x.release),/mismatch/);});
await test('API pagination exhaustion is a blocker',async()=>{const api=createClient({fetchImpl:async()=>new Response(JSON.stringify(Array.from({length:100},()=>({id:1}))),{status:200})});await assert.rejects(()=>api.pages('/repos/'+P.repository+'/pulls'),/pagination/);});
await test('public REST local readiness needs no token and never calls GraphQL',async()=>{
 const f=fixture(),prefix='/repos/'+P.repository,seen=[];
 const local={batchId:'batch-a',baseMain:A,candidate:{sha:C,tree:T},tasks:[{id:'dev-1',status:'merged',commit:H}]};
 const client=createClient({token:null,fetchImpl:async(url,options)=>{
  const u=new URL(url);seen.push(u.pathname);assert.equal(options.headers.Authorization,undefined);assert.notEqual(u.pathname,'/graphql');let data;
  if(u.pathname===prefix)data=repo;
  else if(u.pathname===prefix+'/issues/20')data=f.s.issue;
  else if(u.pathname===prefix+'/branches/main')data={commit:{sha:A}};
  else if(u.pathname===prefix+'/branches/develop')data={commit:{sha:C}};
  else if(u.pathname===prefix+'/git/commits/'+A)data={sha:A,tree:{sha:X}};
  else if(u.pathname===prefix+'/git/commits/'+C)data={sha:C,tree:{sha:T}};
  else if(u.pathname===prefix+'/commits/'+C+'/statuses')data=f.s.statuses;
  else if(u.pathname===prefix+'/actions/runs/88')data=f.s.publisher.run;
  else if(u.pathname===prefix+'/actions/workflows/delivery-gates.yml')data=f.s.publisher.workflow;
  else throw new Error('Unexpected/non-REST route '+u.pathname);
  return new Response(JSON.stringify(data),{status:200});
 }});
 assert.equal((await verifyGithubReady(local,{client})).status,'PASS');assert.ok(seen.length>0);
});
for(const mutate of [f=>f.s.issue.updated_at=date(4),f=>f.s.statuses[0].description='batch:'+'0'.repeat(64),f=>f.s.statuses[0].target_url='https://evil.invalid/actions/runs/88',f=>f.s.publisher.run.head_branch='develop',f=>f.s.publisher.run.conclusion='failure',f=>f.s.publisher.run.status='in_progress',f=>f.s.publisher.workflow.path='untrusted.yml'])await test('public publisher proof mismatch fails closed '+(count+1),()=>{const f=fixture();mutate(f);const local={batchId:'batch-a',baseMain:A,candidate:{sha:C,tree:T},tasks:[{id:'dev-1',status:'merged',commit:H}]};assert.throws(()=>assertLocalReady(f.s,local));});
await test('trusted publisher emits complete compact batch digest on candidate',async()=>{
 const f=fixture(),prefix='/repos/'+P.repository,writes=[];
 const client={
  connection:async()=>[],
  pages:async route=>{
   if(route===prefix+'/pulls?state=open')return [f.release];
   if(route===prefix+'/issues/1/comments')return f.s.prData[1].comments;
   if(route===prefix+'/issues/2/comments'||route===prefix+'/issues/20/comments'||route.includes('/reviews')||route.includes('/statuses'))return [];
   throw new Error('Unexpected list '+route);
  },
  request:async(route,options={})=>{
   if(options.method==='POST'){writes.push({route,...options.body});return {};}
   if(route===prefix)return repo;if(route===prefix+'/issues/20')return f.s.issue;
   if(route===prefix+'/branches/main')return {commit:{sha:A}};if(route===prefix+'/branches/develop')return {commit:{sha:C}};
   if(route===prefix+'/git/commits/'+A)return {sha:A,tree:{sha:X}};if(route===prefix+'/git/commits/'+C)return {sha:C,tree:{sha:T}};
   if(route===prefix+'/pulls/1')return f.first;if(route===prefix+'/pulls/2')return f.release;
   if(route.startsWith(prefix+'/compare/')){const key=route.slice((prefix+'/compare/').length),proof=f.s.ancestry[key];assert.ok(proof);return {status:'ahead',base_commit:{sha:proof.base},merge_base_commit:{sha:proof.base}};}
   throw new Error('Unexpected request '+route);
  }
 };
 await refresh(client,{GITHUB_REPOSITORY:P.repository,GITHUB_WORKFLOW_REF:P.repository+'/'+P.deliveryWorkflow+'@refs/heads/main',GITHUB_RUN_ID:'10'});
 const published=writes.filter(s=>s.context===P.contexts.batch).at(-1);
 assert.equal(published.state,'success');assert.equal(published.description,'batch:'+batchDigest(f.state));assert.equal(published.description.length,70);assert.equal(published.route,prefix+'/statuses/'+C);
});
// CR-GH-1: exercise the actual dependency loader, not only preassembled policy snapshots.
function snapshotClient(f,{missingRuns=[]}={}){
 const prefix='/repos/'+P.repository,requests=[],writes=[];
 const client={connection:async(id,kind)=>kind==='reviewThreads'?f.s.prData[id].threads:[],pages:async route=>{
  requests.push(route);
  if(route===prefix+'/pulls?state=open')return f.s.pulls.filter(p=>p.state==='open');
  if(route===prefix+'/issues/20/comments')return f.s.issueComments;
  const match=route.match(/\/(?:issues|pulls)\/(\d+)\/(comments|reviews)$/);
  if(match)return f.s.prData[Number(match[1])][match[2]];
  if(route===prefix+'/commits/'+C+'/statuses')return f.s.statuses;
  throw new Error('Unexpected snapshot list '+route);
 },request:async(route,options={})=>{
  requests.push(route);
  if(options.method==='POST'){writes.push({route,body:options.body});return {};}
  if(route===prefix)return repo;if(route===prefix+'/issues/20')return f.s.issue;
  if(route===prefix+'/branches/main')return {commit:{sha:A}};if(route===prefix+'/branches/develop')return {commit:{sha:C}};
  if(route===prefix+'/git/commits/'+A)return {sha:A,tree:{sha:X}};if(route===prefix+'/git/commits/'+C)return {sha:C,tree:{sha:T}};
  const pr=route.match(/\/pulls\/(\d+)$/);if(pr)return f.s.pulls.find(p=>p.number===Number(pr[1]));
  if(route.startsWith(prefix+'/compare/')){const proof=f.s.ancestry[route.slice((prefix+'/compare/').length)];assert.ok(proof);return {status:'ahead',base_commit:{sha:proof.base},merge_base_commit:{sha:proof.base}};}
  const evidence=route.match(/\/issues\/comments\/(\d+)$/);
  if(evidence){const found=Object.values(f.s.evidence).find(c=>c.html_url.endsWith('#issuecomment-'+evidence[1]));if(found)return found;throw new Error('GitHub metadata HTTP 404.');}
  if(route===prefix+'/actions/workflows/manual-release.yml')return {id:77,path:P.manualWorkflow};
  const run=route.match(/\/actions\/runs\/(\d+)(\/approvals)?$/);
  if(run){const id=Number(run[1]),data=f.s.manualRuns[id];if(!data||missingRuns.includes(id))throw new Error('GitHub metadata HTTP 404.');return run[2]?data.approvals:data.run;}
  throw new Error('Unexpected snapshot request '+route);
 }};
 return {client,requests,writes};
}
function replaceQaEvidence(f,id,minute){
 const url='https://github.com/'+P.repository+'/issues/20#issuecomment-'+id,body='Replacement sanitized QA evidence '+id;
 f.s.evidence[url]={...comment(id,body),html_url:url};
 const q={...readMarker(f.s.issueComments[0].body,'qa'),evidence:[{url,sha256:hash(body)}]};
 const c=comment(60+minute,marker('qa',q),minute);f.s.issueComments.push(c);return c;
}
await test('CR-GH-1 previous batch deleted evidence does not block new development',async()=>{
 const f=dev();f.s.issueComments=[comment(60,marker('qa',{schemaVersion:1,batchId:'old-batch',reviewerThread:P.qaThread,evidence:[{url:'https://github.com/'+P.repository+'/issues/20#issuecomment-123',sha256:'0'.repeat(64)}]}),1)];
 const x=snapshotClient(f),s=await loadSnapshot(x.client);assert.equal(developGate(s,f.first).status,'PASS');assert.ok(!x.requests.some(r=>r.endsWith('/issues/comments/123')));
});
await test('CR-GH-1 superseded active-batch evidence404 is not loaded',async()=>{
 const f=approved(),old={...readMarker(f.s.issueComments[0].body,'qa'),evidence:[{url:'https://github.com/'+P.repository+'/issues/20#issuecomment-123',sha256:'0'.repeat(64)}]};f.s.issueComments.unshift(comment(59,marker('qa',old),1));
 const x=snapshotClient(f),s=await loadSnapshot(x.client);assert.equal(releaseGate(s,f.release).status,'PASS');assert.ok(!x.requests.some(r=>r.endsWith('/issues/comments/123')));
});
await test('CR-GH-1 replacement attestation recovers without editing old comments',async()=>{
 const f=approved();delete f.s.evidence[Object.keys(f.s.evidence)[0]];await assert.rejects(()=>loadSnapshot(snapshotClient(f).client),/404/);
 replaceQaEvidence(f,51,5);const x=snapshotClient(f),s=await loadSnapshot(x.client);assert.equal(releaseGate(s,f.release).status,'PASS');assert.ok(!x.requests.some(r=>r.endsWith('/issues/comments/50')));
});
await test('CR-GH-1 current missing evidence still fails closed',async()=>{
 const f=approved();f.s.evidence={};const x=snapshotClient(f);await assert.rejects(()=>loadSnapshot(x.client),/404/);assert.ok(x.requests.some(r=>r.endsWith('/issues/comments/50')));
});
await test('CR-GH-1 current tampered evidence still fails policy',async()=>{
 const f=approved();Object.values(f.s.evidence)[0].body+=' altered';const s=await loadSnapshot(snapshotClient(f).client);assert.throws(()=>releaseGate(s,f.release),/hash mismatch/);
});
await test('CR-GH-1 edited QA timestamp matches policy selection',async()=>{
 const f=approved(),replacement=replaceQaEvidence(f,51,1);replacement.updated_at=date(5);delete f.s.evidence[Object.keys(f.s.evidence)[0]];
 const x=snapshotClient(f),s=await loadSnapshot(x.client);assert.equal(releaseGate(s,f.release).status,'PASS');assert.ok(!x.requests.some(r=>r.endsWith('/issues/comments/50')));
});
await test('CR-GH-1 tied QA timestamps use policy numeric comment order',async()=>{
 const f=approved();replaceQaEvidence(f,51,3);delete f.s.evidence[Object.keys(f.s.evidence)[0]];
 const x=snapshotClient(f),s=await loadSnapshot(x.client);assert.equal(releaseGate(s,f.release).status,'PASS');assert.ok(!x.requests.some(r=>r.endsWith('/issues/comments/50')));
});
await test('CR-GH-1 never falls back to older pass if current evidence404',async()=>{
 const f=approved(),q={...readMarker(f.s.issueComments[0].body,'qa'),evidence:[{url:'https://github.com/'+P.repository+'/issues/20#issuecomment-123',sha256:'0'.repeat(64)}]};f.s.issueComments.push(comment(61,marker('qa',q),5));
 const x=snapshotClient(f);await assert.rejects(()=>loadSnapshot(x.client),/404/);assert.ok(!x.requests.some(r=>r.endsWith('/issues/comments/50')));
});
await test('CR-GH-1 superseded manual run404 does not block release',async()=>{
 const f=approved(),a={...readMarker(f.s.prData[2].comments[0].body,'release'),runId:999};f.s.prData[2].comments.unshift(comment(69,marker('release',a),1,{login:'github-actions[bot]'}));
 const x=snapshotClient(f),s=await loadSnapshot(x.client);assert.equal(releaseGate(s,f.release).status,'PASS');assert.ok(!x.requests.some(r=>r.includes('/actions/runs/999')));
});
await test('CR-GH-1 obsolete manual batch run is not fetched or accepted',async()=>{
 const f=approved(),a={...readMarker(f.s.prData[2].comments[0].body,'release'),runId:999,batchId:'old-batch'};f.s.prData[2].comments=[comment(70,marker('release',a),4,{login:'github-actions[bot]'})];
 const x=snapshotClient(f),s=await loadSnapshot(x.client);assert.equal(batchReady(s).status,'PASS');assert.throws(()=>releaseGate(s,f.release),/Stale/);assert.ok(!x.requests.some(r=>r.includes('/actions/runs/999')));
});
await test('CR-GH-1 closed PR historical authorization is not loaded',async()=>{
 const f=approved(),a={...readMarker(f.s.prData[2].comments[0].body,'release'),pr:1,runId:999};f.s.prData[1].comments.push(comment(71,marker('release',a),5,{login:'github-actions[bot]'}));
 const x=snapshotClient(f),s=await loadSnapshot(x.client);assert.equal(releaseGate(s,f.release).status,'PASS');assert.ok(!x.requests.some(r=>r.includes('/actions/runs/999')));
});
await test('CR-GH-1 current manual run404 still fails closed',async()=>{
 const f=approved(),x=snapshotClient(f,{missingRuns:[7]});await assert.rejects(()=>loadSnapshot(x.client),/404/);assert.ok(x.requests.some(r=>r.endsWith('/actions/runs/7')));
});
await test('CR-GH-1 replacement manual declaration recovers deleted prior run',async()=>{
 const f=approved(),old=readMarker(f.s.prData[2].comments[0].body,'release');f.s.manualRuns[8]=structuredClone(f.s.manualRuns[7]);f.s.manualRuns[8].run.id=8;f.s.prData[2].comments.push(comment(71,marker('release',{...old,runId:8}),5,{login:'github-actions[bot]'}));
 const x=snapshotClient(f,{missingRuns:[7]}),s=await loadSnapshot(x.client);assert.equal(releaseGate(s,f.release).status,'PASS');assert.ok(!x.requests.some(r=>r.includes('/actions/runs/7')));
});
await test('CR-GH-1 new owner-approved workflow can replace missing prior run',async()=>{
 const f=approved();f.s.manualRuns[8]=structuredClone(f.s.manualRuns[7]);Object.assign(f.s.manualRuns[8].run,{id:8,status:'in_progress',conclusion:null});const x=snapshotClient(f,{missingRuns:[7]});
 const result=await authorize(x.client,{GITHUB_REPOSITORY:P.repository,GITHUB_WORKFLOW_REF:P.repository+'/'+P.manualWorkflow+'@refs/heads/main',GITHUB_RUN_ID:'8',GITHUB_RUN_ATTEMPT:'1',GITHUB_REF:'refs/heads/main',GITHUB_SHA:A,GITHUB_EVENT_NAME:'workflow_dispatch',GITHUB_ACTOR:P.trustedActor,RELEASE_PR:'2',RELEASE_SHA:C,RELEASE_DIGEST:batchDigest(f.state)});
 assert.equal(result.status,'RECORDED');assert.ok(!x.requests.some(r=>r.includes('/actions/runs/7')));assert.equal(readMarker(x.writes.at(-1).body.body,'release').runId,8);
});
await test('CR-GH-1 skipping prior-run dependencies never grants release permission',async()=>{
 const f=approved(),x=snapshotClient(f),s=await loadSnapshot(x.client,{includeManualRuns:false});assert.deepEqual(s.manualRuns,{});assert.throws(()=>releaseGate(s,f.release),/workflow run identity/);
});
await test('compare transport accepts exact full-SHA same-repository route',async()=>{
 const route='/repos/'+P.repository+'/compare/'+A+'...'+C,seen=[];
 const api=createClient({token:null,fetchImpl:async(url)=>{seen.push(url);return new Response('{"status":"ahead"}',{status:200});}});
 assert.equal((await api.request(route)).status,'ahead');assert.deepEqual(seen,['https://api.github.com'+route]);
});
const compare='/repos/'+P.repository+'/compare/'+A+'...'+C;
const badCompareRoutes=[
 compare+'?per_page=100',compare+'?redirect=https://evil.invalid',compare+'&page=1',compare+'#fragment',compare+'/../pulls',compare+'/./',compare+'/',compare+'\n',compare+'\r',compare+'\t',compare+'\0',
 '/repos/'+P.repository+'/compare?base='+A+'&head='+C,
 '/repos/'+P.repository+'/compare/main...develop',
 '/repos/'+P.repository+'/compare/'+A.slice(0,7)+'...'+C,
 '/repos/'+P.repository+'/compare/'+A+'..'+C,
 '/repos/'+P.repository+'/compare/'+A+'....'+C,
 '/repos/'+P.repository+'/compare/'+A+'%2e%2e%2e'+C,
 '/repos/'+P.repository+'/compare/%2e%2e/pulls',
 '/repos/'+P.repository+'/compare/'+A.toUpperCase()+'...'+C,
 '/repos/'+P.repository+'/../foreign/compare/'+A+'...'+C,
 '/repos/'+P.repository+'/compare/'+A+'...'+C+'%3fextra=1',
 '/repos/stranger/other/compare/'+A+'...'+C,
 '/repos/'+P.repository+'-foreign/compare/'+A+'...'+C,
 'https://api.github.com'+compare,
 '//evil.invalid'+compare,
 '/repos/'+P.repository+'/compare/'+A+'...'+C+'\\..\\pulls'
];
for(const [index,route]of badCompareRoutes.entries())await test('compare transport rejects suspicious route '+index,async()=>{
 let calls=0;const api=createClient({token:null,fetchImpl:async()=>{calls++;return new Response('{}',{status:200});}});
 await assert.rejects(()=>api.request(route),/Untrusted API route/);assert.equal(calls,0);
});
await test('full refresh ancestry proof traverses actual transport adapter',async()=>{
 const f=fixture(),prefix='/repos/'+P.repository,seen=[],writes=[],compares=[];
 const api=createClient({token:null,fetchImpl:async(url,options)=>{
  const u=new URL(url),route=u.pathname;seen.push({route,method:options.method});let data;
  assert.equal(u.origin,'https://api.github.com');assert.equal(options.redirect,'error');
  if(route==='/graphql'){
   const query=JSON.parse(options.body).query,kind=query.includes('reviewThreads(')?'reviewThreads':'reviews';
   data={data:{repository:{pullRequest:{[kind]:{nodes:[],pageInfo:{hasNextPage:false,endCursor:null}}}}}};
  }else if(options.method==='POST'&&route.startsWith(prefix+'/statuses/')){writes.push({sha:route.slice((prefix+'/statuses/').length),...JSON.parse(options.body)});data={id:writes.length};}
  else if(route===prefix)data=repo;
  else if(route===prefix+'/issues/20')data=f.s.issue;
  else if(route===prefix+'/branches/main')data={commit:{sha:A}};
  else if(route===prefix+'/branches/develop')data={commit:{sha:C}};
  else if(route===prefix+'/git/commits/'+A)data={sha:A,tree:{sha:X}};
  else if(route===prefix+'/git/commits/'+C)data={sha:C,tree:{sha:T}};
  else if(route===prefix+'/pulls'){assert.equal(u.searchParams.get('state'),'open');assert.equal(u.searchParams.get('page'),'1');data=[f.release];}
  else if(route===prefix+'/pulls/1')data=f.first;
  else if(route===prefix+'/pulls/2')data=f.release;
  else if(route===prefix+'/issues/1/comments')data=f.s.prData[1].comments;
  else if(route===prefix+'/issues/2/comments'||route===prefix+'/issues/20/comments'||route.endsWith('/reviews')||route===prefix+'/commits/'+C+'/statuses')data=[];
  else if(route.startsWith(prefix+'/compare/')){
   assert.equal(u.search,'');const key=route.slice((prefix+'/compare/').length),proof=f.s.ancestry[key];assert.ok(proof);compares.push(key);data={status:'ahead',base_commit:{sha:proof.base},merge_base_commit:{sha:proof.base}};
  }else throw new Error('Unexpected real-adapter mock request '+route);
  return new Response(JSON.stringify(data),{status:200});
 }});
 await refresh(api,{GITHUB_REPOSITORY:P.repository,GITHUB_WORKFLOW_REF:P.repository+'/'+P.deliveryWorkflow+'@refs/heads/main',GITHUB_RUN_ID:'10'});
 assert.deepEqual(compares.sort(),[A+'...'+C,H+'...'+M,M+'...'+C].sort());
 const published=writes.filter(s=>s.context===P.contexts.batch).at(-1);assert.equal(published.state,'success');assert.equal(published.sha,C);assert.equal(published.description,'batch:'+batchDigest(f.state));
 assert.ok(seen.some(r=>r.route==='/graphql'));assert.ok(seen.some(r=>r.route.includes('/compare/')));
});
await test('workflow static security contract',()=>{
 const dir=new URL('../.github/workflows/',import.meta.url);
 const signal=fs.readFileSync(new URL('delivery-signal.yml',dir),'utf8');
 const gates=fs.readFileSync(new URL('delivery-gates.yml',dir),'utf8');
 const manual=fs.readFileSync(new URL('manual-release.yml',dir),'utf8');
 assert.match(signal,/permissions: \{\}/);assert.doesNotMatch(signal,/checkout|secrets\.|github\.token|\$\{\{/);
 for(const source of [gates,manual]){assert.doesNotMatch(source,/pull_request_target|actions\/download-artifact|git merge|ssh |deploy:/);assert.match(source,/actions\/checkout@11bd71901bbe5b1630ceea73d27597364c9af683/);assert.match(source,/ref: main/);assert.match(source,/persist-credentials: false/);}
 assert.match(gates,/workflows: \['Delivery signal', 'Manual release authorization'\]/);assert.doesNotMatch(gates,/workflow_run:[\s\S]*workflows:.*Delivery metadata gates/);assert.match(gates,/types: \[completed\]/);assert.match(manual,/environment: manual-release/);assert.match(manual,/if: github.ref == 'refs\/heads\/main'/);
 assert.match(gates,/statuses: write/);assert.match(manual,/issues: write/);assert.doesNotMatch(manual,/push:|schedule:|pull_request:/);
});
const dir=path.resolve(import.meta.dirname,'../storage/qa/github-delivery');
fs.mkdirSync(dir,{recursive:true});
const report=path.join(dir,'policy-'+Date.now()+'.json');
fs.writeFileSync(report,JSON.stringify({status:'PASS',tests:count,passed:names,limitations:'Offline injected GitHub metadata and API transport; no live settings, workflow execution, application QA, merge or deployment.'},null,2)+'\n');
console.log(count+' offline GitHub delivery policy checks passed. Report: '+report);
