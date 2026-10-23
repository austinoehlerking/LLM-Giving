/** Offline gate regressions. All artifacts stay in ignored storage; no real remote or QA subprocess. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {createHash} from 'node:crypto';
import {createContext,runGate} from '../tools/delivery-gate.mjs';

const artifacts=path.resolve(import.meta.dirname,'../storage/qa');
fs.mkdirSync(artifacts,{recursive:true});
const root=fs.mkdtempSync(path.join(artifacts,'delivery-gate-'));
fs.mkdirSync(path.join(root,'.git')); fs.mkdirSync(path.join(root,'storage/orchestration'),{recursive:true});
const ledger=path.join(root,'storage/orchestration/batch-state.json');
const A='a'.repeat(40),B='b'.repeat(40),C='c'.repeat(40),T='d'.repeat(40),OTHER='e'.repeat(40);
const evidence='storage/qa/report.json';fs.mkdirSync(path.dirname(path.join(root,evidence)),{recursive:true});fs.writeFileSync(path.join(root,evidence),'original QA evidence\n');
const hash=createHash('sha256').update(fs.readFileSync(path.join(root,evidence))).digest('hex');
fs.writeFileSync(path.join(root,'storage/qa/component.json'),'component evidence');
const outside=path.resolve(root,'../'+path.basename(root)+'-outside.json');fs.writeFileSync(outside,'outside evidence');
let remote=C,main=A,head=C,tree=T,dirty='',notAncestor='',failGit='',calls=[],hook;
function git(args) {
  calls.push(args); if(hook)hook(args);
  if(args[0]===failGit)return {status:128,stdout:'',stderr:'fake failure'};
  if(args[0]==='rev-parse') {
    if(args[1]==='--git-common-dir')return {status:0,stdout:path.join(root,'.git')+'\n'};
    const arg=args[2];return {status:0,stdout:(arg==='HEAD'?head:arg.endsWith('^{tree}')?tree:arg.slice(0,40))+'\n'};
  }
  if(args[0]==='status')return {status:0,stdout:dirty};
  if(args[0]==='merge-base')return {status:args[2]===notAncestor?1:0,stdout:''};
  if(args[0]==='ls-remote')return {status:0,stdout:remote+'\trefs/heads/develop\n'+main+'\trefs/heads/main\n'};
  throw new Error('Unexpected Git command '+JSON.stringify(args));
}
const ctx=createContext({cwd:root,git});
const fresh=()=>({schemaVersion:1,batchId:'batch-local',membershipClosed:true,phase:'qa_ready',baseMain:A,candidate:{sha:C,tree:T},blockers:[],tasks:[{id:'dev1',owner:'Dev 1',status:'merged',commit:B,review:{status:'pass',sha:B},checks:{status:'pass',sha:B,evidence:['storage/qa/component.json']}}]});
function save(state){fs.writeFileSync(ledger,JSON.stringify(state,null,2)+'\n');}
function reset(){remote=C;main=A;head=C;tree=T;dirty='';notAncestor='';failGit='';hook=undefined;calls=[];save(fresh());}
let count=0;const passed=[];
function test(name,fn){reset();fn();passed.push(name);count++;}
function gate(command='check-qa',options={}){return runGate(command,{context:ctx,...options});}
function rejects(change,pattern=/./,command='check-qa'){const state=fresh();change(state);save(state);const before=fs.readFileSync(ledger,'utf8');assert.throws(()=>gate(command),pattern);assert.equal(fs.readFileSync(ledger,'utf8'),before);}
function passState(){const state=fresh();state.phase='qa_passed';state.qa={status:'pass',owner:'QA',candidateSha:C,candidateTree:T,evidence:[{path:evidence,sha256:hash}]};return state;}

test('canonical path resolves through Git common dir from linked worktree',()=>{const linked=path.join(root,'storage/worktrees/linked');fs.mkdirSync(linked,{recursive:true});const linkedContext=createContext({cwd:linked,git});const before=fs.readFileSync(ledger,'utf8');const result=runGate('check-qa',{context:linkedContext});assert.equal(result.statePath,ledger);assert.equal(result.testState,false);assert.equal(fs.readFileSync(ledger,'utf8'),before);});
test('relative common-dir path also resolves to shared root',()=>{const linked=path.join(root,'storage/worktrees/linked');const relative=(args)=>args[0]==='rev-parse'&&args[1]==='--git-common-dir'?{status:0,stdout:path.relative(linked,path.join(root,'.git'))+'\n'}:git(args);assert.equal(createContext({cwd:linked,git:relative}).sharedRoot,root);});
test('open membership',()=>rejects(s=>s.membershipClosed=false,/membership is open/));
test('empty membership',()=>rejects(s=>s.tasks=[],/nonempty/));
test('active task',()=>rejects(s=>s.tasks[0].status='working',/not terminal/));
test('review mismatch',()=>rejects(s=>s.tasks[0].review.sha=OTHER,/review does not match/));
test('checks mismatch',()=>rejects(s=>s.tasks[0].checks.sha=OTHER,/checks\/evidence/));
test('missing component evidence list',()=>rejects(s=>s.tasks[0].checks.evidence=[],/checks\/evidence/));
test('deferred task needs reason and human decision',()=>{rejects(s=>s.tasks=[{id:'later',owner:'Dev 2',status:'deferred',humanDecision:'human approved',reason:''}],/humanDecision and reason/);rejects(s=>s.tasks=[{id:'later',owner:'Dev 2',status:'deferred',humanDecision:'',reason:'Separate scope'}],/humanDecision and reason/);});
test('explicitly deferred task is terminal',()=>{const s=fresh();s.tasks.push({id:'later',owner:'Dev 2',status:'deferred',humanDecision:'Human deferred this task',reason:'Next batch'});save(s);assert.equal(gate().status,'PASS');});
test('blockers',()=>rejects(s=>s.blockers=['unresolved finding'],/blockers/));
test('duplicate task identities',()=>rejects(s=>s.tasks.push({...s.tasks[0]}),/Duplicate task/));
test('candidate drift on live develop',()=>{remote=OTHER;assert.throws(()=>gate(),/Candidate drift/);});
test('current HEAD must be the candidate',()=>{head=OTHER;assert.throws(()=>gate(),/HEAD/);});
test('candidate tree mismatch',()=>{tree=OTHER;assert.throws(()=>gate(),/tree does not match/);});
test('dirty tracked source',()=>{dirty=' M src/domain.php\0';assert.throws(()=>gate(),/source is dirty/);});
test('dirty untracked source',()=>{dirty='?? views/new.php\0';assert.throws(()=>gate(),/source is dirty/);});
test('component must be in candidate ancestry',()=>{notAncestor=B;assert.throws(()=>gate(),/Task dev1 commit is not an ancestor/);});
test('batch base must be in candidate ancestry',()=>{notAncestor=A;assert.throws(()=>gate(),/baseMain is not an ancestor/);});
test('Git failure fails closed',()=>{failGit='ls-remote';assert.throws(()=>gate(),/Git ls-remote failed/);});
test('wrong schema version and abbreviated candidate fail',()=>{rejects(s=>s.schemaVersion=2,/schemaVersion/);rejects(s=>s.candidate.sha='abc123',/full SHA/);});
test('start requires owner without changing state',()=>{const before=fs.readFileSync(ledger,'utf8');assert.throws(()=>gate('start-qa'),/owner/);assert.equal(fs.readFileSync(ledger,'utf8'),before);});
test('start claims once and records exact owner/source',()=>{const result=gate('start-qa',{owner:'QA task'});const state=JSON.parse(fs.readFileSync(ledger));assert.equal(result.phase,'qa_running');assert.equal(state.qa.owner,'QA task');assert.equal(state.qa.candidateSha,C);assert.equal(state.qa.candidateTree,T);assert.ok(!fs.existsSync(ledger+'.lock'));const before=fs.readFileSync(ledger,'utf8');assert.throws(()=>gate('start-qa',{owner:'duplicate'}),/Required phase/);assert.equal(fs.readFileSync(ledger,'utf8'),before);});
test('preexisting lock is never removed',()=>{fs.writeFileSync(ledger+'.lock','existing owner');try{assert.throws(()=>gate('start-qa',{owner:'QA'}),/locked/);assert.equal(fs.readFileSync(ledger+'.lock','utf8'),'existing owner');}finally{fs.unlinkSync(ledger+'.lock');}});
test('state is rechecked after exclusive lock',()=>{let reads=0;hook=args=>{if(args[0]==='ls-remote'&&++reads===2){const s=fresh();s.membershipClosed=false;save(s);}};assert.throws(()=>gate('start-qa',{owner:'QA'}),/Ledger changed/);assert.equal(JSON.parse(fs.readFileSync(ledger)).membershipClosed,false);assert.ok(!fs.existsSync(ledger+'.lock'));});
test('remote drift under lock prevents claim',()=>{let reads=0;hook=args=>{if(args[0]==='ls-remote'&&++reads===2)remote=OTHER;};assert.throws(()=>gate('start-qa',{owner:'QA'}),/Candidate drift/);assert.equal(JSON.parse(fs.readFileSync(ledger)).phase,'qa_ready');assert.ok(!fs.existsSync(ledger+'.lock'));});
test('test override needs explicit opt-in and cannot target canonical state',()=>{assert.throws(()=>gate('check-qa',{statePath:'isolated.json'}),/isolated tests/);assert.throws(()=>gate('check-qa',{statePath:ledger,allowTestState:true}),/canonical ledger/);const file=path.join(root,'storage/qa/test-state.json');fs.writeFileSync(file,JSON.stringify(fresh()));const result=gate('check-qa',{statePath:file,allowTestState:true});assert.equal(result.statePath,file);assert.equal(result.testState,true);});
test('release verifies evidence without writing',()=>{save(passState());const before=fs.readFileSync(ledger,'utf8');assert.equal(gate('check-release').status,'PASS');assert.equal(fs.readFileSync(ledger,'utf8'),before);});
test('release requires actual pass and matching QA identity',()=>{const state=passState();state.qa.status='failed';save(state);assert.throws(()=>gate('check-release'),/QA pass/);state.qa.status='pass';state.qa.candidateTree=OTHER;save(state);assert.throws(()=>gate('check-release'),/QA identity/);});
test('release rejects evidence mismatch',()=>{const state=passState();state.qa.evidence[0].sha256='0'.repeat(64);save(state);assert.throws(()=>gate('check-release'),/hash mismatch/);});
test('release rejects missing evidence and absolute/traversing paths',()=>{const state=passState();state.qa.evidence=[];save(state);assert.throws(()=>gate('check-release'),/evidence is missing/);for(const candidate of ['storage/qa/missing.json',path.join(root,evidence),'../outside.json']){state.qa.evidence=[{path:candidate,sha256:hash}];save(state);assert.throws(()=>gate('check-release'));}});
test('release rejects main divergence',()=>{save(passState());main=OTHER;notAncestor=OTHER;assert.throws(()=>gate('check-release'),/Live main is not an ancestor/);});
test('release still checks live develop drift',()=>{save(passState());remote=OTHER;assert.throws(()=>gate('check-release'),/Candidate drift/);});


test('missing component evidence file fails closed',()=>rejects(s=>s.tasks[0].checks.evidence=['storage/qa/missing-component.json'],/ENOENT/));
test('existing evidence outside shared root is rejected',()=>{const state=passState();state.qa.evidence=[{path:path.relative(root,outside),sha256:hash}];save(state);assert.throws(()=>gate('check-release'),/escapes the shared root/);});
test('state change before lock acquisition is rejected',()=>{let reads=0;hook=args=>{if(args[0]==='ls-remote'&&++reads===1){const state=fresh();state.tasks[0].status='working';save(state);}};assert.throws(()=>gate('start-qa',{owner:'QA'}),/before lock acquisition/);assert.equal(JSON.parse(fs.readFileSync(ledger)).tasks[0].status,'working');assert.ok(!fs.existsSync(ledger+'.lock'));});
test('competing exclusive claim retains its lock',()=>{let reads=0;hook=args=>{if(args[0]==='ls-remote'&&++reads===1)fs.writeFileSync(ledger+'.lock','competing claim');};try{assert.throws(()=>gate('start-qa',{owner:'QA'}),/another start/);assert.equal(fs.readFileSync(ledger+'.lock','utf8'),'competing claim');}finally{fs.unlinkSync(ledger+'.lock');}});
test('externally replaced lock is never deleted',()=>{let reads=0;hook=args=>{if(args[0]==='ls-remote'&&++reads===2)fs.writeFileSync(ledger+'.lock',JSON.stringify({token:'another owner'}));};try{assert.throws(()=>gate('start-qa',{owner:'QA'}),/Lock ownership changed/);assert.equal(JSON.parse(fs.readFileSync(ledger+'.lock')).token,'another owner');assert.equal(JSON.parse(fs.readFileSync(ledger)).phase,'qa_ready');}finally{fs.unlinkSync(ledger+'.lock');}});
test('failed QA phase cannot start without explicit reconciliation',()=>rejects(s=>s.phase='qa_failed',/Required phase/));
test('Git spawn errors fail closed during context discovery',()=>assert.throws(()=>createContext({cwd:root,git:()=>({status:null,error:new Error('test timeout'),stdout:''})}),/Git rev-parse failed/));


test('running claim cannot be bypassed by changing phase back to ready',()=>rejects(s=>s.qa={status:'running',owner:'first QA',candidateSha:C,candidateTree:T},/running claim/));
test('prior stopped QA evidence is retained on explicit restart',()=>{const state=fresh();state.qa={status:'failed',owner:'prior QA',candidateSha:C,candidateTree:T,evidence:[{path:evidence,sha256:hash}]};save(state);gate('start-qa',{owner:'new QA'});const current=JSON.parse(fs.readFileSync(ledger));assert.deepEqual(current.qaHistory,[state.qa]);assert.equal(current.qa.owner,'new QA');});

fs.writeFileSync(path.join(root,'test-report.json'),JSON.stringify({status:'PASS',tests:count,passed,limitation:'Offline injected Git context; no live remote, QA matrix or deployment exercised.'},null,2)+'\n');
console.log(count+' offline delivery-gate checks passed. Artifacts: '+root);
