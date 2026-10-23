/** Local batch preflight/claim only. Never runs QA, publishes code or authorizes deployment. */
import fs from 'node:fs';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {createHash, randomUUID} from 'node:crypto';
import {fileURLToPath} from 'node:url';

const SHA=/^[a-f0-9]{40}$/;
const HASH=/^[a-f0-9]{64}$/;
const PHASES=['developing','qa_ready','qa_running','qa_passed','qa_failed'];
function need(value,message) { if(!value) throw new Error(message); }
function object(value) { return value!==null && typeof value==='object' && !Array.isArray(value); }
function text(value) { return typeof value==='string' && value.trim().length>0; }

// Injection is for offline tests; production calls always use bounded, shell-free Git.
export function createContext({cwd=process.cwd(),git}={}) {
  const invoke=git || ((args)=>spawnSync('git',args,{cwd,encoding:'utf8',shell:false,windowsHide:true,
    timeout:15000,maxBuffer:1024*1024,env:{...process.env,GIT_TERMINAL_PROMPT:'0',GCM_INTERACTIVE:'Never',GIT_OPTIONAL_LOCKS:'0'}}));
  function run(args,allowNotAncestor=false) {
    const r=invoke(args);
    need(r && !r.error && !r.signal && (r.status===0 || (allowNotAncestor && r.status===1)), 'Git '+args[0]+' failed; stop and inspect Git separately.');
    need(typeof r.stdout==='string','Git returned no usable output.');
    return {status:r.status,output:r.stdout.trim()};
  }
  const common=run(['rev-parse','--git-common-dir']).output;
  need(text(common),'Git common directory is unavailable.');
  return {cwd,sharedRoot:path.dirname(path.resolve(cwd,common)),run};
}
function stateFile(ctx,override,allowTestState) {
  const canonical=path.join(ctx.sharedRoot,'storage','orchestration','batch-state.json');
  if(override===undefined) return canonical;
  need(allowTestState===true,'--state is restricted to isolated tests (DELIVERY_GATE_TEST=1).');
  need(text(override),'Test state path is empty.');
  const target=path.resolve(ctx.cwd,override);
  const identity=value=>process.platform==='win32'?value.toLowerCase():value;
  need(identity(target)!==identity(canonical),'Test state override must not select the canonical ledger.');
  return target;
}
function readState(file) {
  need(fs.lstatSync(file).isFile(),'Ledger must be a regular file.');
  need(fs.statSync(file).size<=4*1024*1024,'Ledger exceeds the 4 MiB limit.');
  const raw=fs.readFileSync(file,'utf8');
  const state=JSON.parse(raw);
  need(object(state),'Ledger must be a JSON object.');
  return {raw,state};
}
function noBlockers(value,label) {
  if(value!==undefined) need(Array.isArray(value) && value.length===0,label+' has blockers or an invalid blockers list.');
}
function ancestor(ctx,older,newer,label) {
  need(ctx.run(['rev-parse','--verify',older+'^{commit}']).output===older,label+' must resolve exactly to a commit.');
  need(ctx.run(['merge-base','--is-ancestor',older,newer],true).status===0,label+' is not an ancestor of the candidate.');
}
function validate(state,ctx,phase) {
  need(state.schemaVersion===1,'Ledger schemaVersion must be 1.');
  need(text(state.batchId),'Missing batchId.');
  need(typeof state.membershipClosed==='boolean' && state.membershipClosed,'Batch membership is open or missing.');
  need(PHASES.includes(state.phase) && state.phase===phase,'Required phase is '+phase+'; current phase is '+String(state.phase)+'.');
  need(SHA.test(state.baseMain),'baseMain must be a full commit SHA.');
  need(object(state.candidate) && SHA.test(state.candidate.sha) && SHA.test(state.candidate.tree),'Candidate requires full SHA and tree.');
  need(Array.isArray(state.tasks) && state.tasks.length>0,'Closed batch must have nonempty task membership.');
  noBlockers(state.blockers,'Batch');
  need(state.qa===undefined || object(state.qa),'Invalid QA record.');
  if(phase==='qa_ready') need(state.qa?.status!=='running','QA already has a running claim; reconcile it explicitly.');
  const ids=new Set();
  for(const task of state.tasks) {
    need(object(task) && text(task.id) && text(task.owner),'Each task needs an id and owner.');
    need(!ids.has(task.id),'Duplicate task id: '+task.id); ids.add(task.id);
    noBlockers(task.blockers,'Task '+task.id);
    if(task.status==='deferred') {
      need(text(task.humanDecision) && text(task.reason),'Deferred task '+task.id+' requires a humanDecision and reason.');
      continue;
    }
    need(task.status==='merged','Task '+task.id+' is not terminal.');
    need(SHA.test(task.commit),'Merged task '+task.id+' needs a full commit SHA.');
    need(object(task.review) && task.review.status==='pass' && task.review.sha===task.commit,'Task '+task.id+' review does not match its commit.');
    need(object(task.checks) && task.checks.status==='pass' && task.checks.sha===task.commit &&
      Array.isArray(task.checks.evidence) && task.checks.evidence.length>0 && task.checks.evidence.every(text),'Task '+task.id+' checks/evidence do not match its commit.');
    for(const evidence of task.checks.evidence) evidencePath(ctx.sharedRoot,evidence);
    ancestor(ctx,task.commit,state.candidate.sha,'Task '+task.id+' commit');
  }
  need(ctx.run(['rev-parse','--verify',state.candidate.sha+'^{commit}']).output===state.candidate.sha,'Candidate commit does not resolve exactly.');
  need(ctx.run(['rev-parse','--verify',state.candidate.sha+'^{tree}']).output===state.candidate.tree,'Candidate tree does not match.');
  need(ctx.run(['rev-parse','--verify','HEAD']).output===state.candidate.sha,'Current HEAD is not the candidate.');
  need(ctx.run(['status','--porcelain=v1','-z','--untracked-files=all','--ignore-submodules=none']).output==='','Tracked or untracked source is dirty.');
  ancestor(ctx,state.baseMain,state.candidate.sha,'Batch baseMain');
  const lines=ctx.run(['ls-remote','--exit-code','--heads','origin','refs/heads/develop','refs/heads/main']).output.split(/\r?\n/);
  const refs=new Map();
  for(const line of lines) { const match=line.match(/^([a-f0-9]{40})\s+(refs\/heads\/(?:develop|main))$/); need(match && !refs.has(match[2]),'Remote refs are missing, duplicated or malformed.'); refs.set(match[2],match[1]); }
  need(refs.get('refs/heads/develop')===state.candidate.sha,'Candidate drift: live origin develop differs.');
  need(SHA.test(refs.get('refs/heads/main')),'Live origin main is missing.');
  if(phase==='qa_passed') ancestor(ctx,refs.get('refs/heads/main'),state.candidate.sha,'Live main');
}
function evidencePath(root,relative) {
  need(text(relative) && !path.isAbsolute(relative) && !path.win32.isAbsolute(relative),'Evidence path must be relative to the shared root.');
  const realRoot=fs.realpathSync(root);
  const file=fs.realpathSync(path.resolve(root,relative));
  const rest=path.relative(realRoot,file);
  need(rest!=='' && rest!=='..' && !rest.startsWith('..'+path.sep) && !path.isAbsolute(rest),'Evidence escapes the shared root.');
  need(fs.statSync(file).isFile(),'Evidence must be a file.');
  return file;
}
function fileHash(file) {
  const hash=createHash('sha256'); const fd=fs.openSync(file,'r'); const buffer=Buffer.alloc(64*1024);
  try { let count; while((count=fs.readSync(fd,buffer,0,buffer.length,null))>0) hash.update(buffer.subarray(0,count)); }
  finally { fs.closeSync(fd); }
  return hash.digest('hex');
}
function releaseEvidence(state,ctx) {
  const qa=state.qa;
  need(object(qa) && qa.status==='pass' && text(qa.owner),'QA pass and owner are missing.');
  need(qa.candidateSha===state.candidate.sha && qa.candidateTree===state.candidate.tree,'QA identity does not match the candidate.');
  need(Array.isArray(qa.evidence) && qa.evidence.length>0,'QA evidence is missing.');
  const paths=new Set();
  for(const evidence of qa.evidence) {
    need(object(evidence) && HASH.test(evidence.sha256),'QA evidence requires a SHA-256.');
    const file=evidencePath(ctx.sharedRoot,evidence.path);
    need(!paths.has(file),'Duplicate QA evidence path.'); paths.add(file);
    need(fileHash(file)===evidence.sha256,'QA evidence hash mismatch: '+evidence.path);
  }
}
function atomicWrite(file,state,expectedRaw) {
  need(fs.readFileSync(file,'utf8')===expectedRaw,'Ledger changed during validation; stop and reconcile.');
  const temporary=file+'.'+randomUUID()+'.tmp'; let fd;
  try {
    fd=fs.openSync(temporary,'wx',0o600); fs.writeFileSync(fd,JSON.stringify(state,null,2)+'\n'); fs.fsyncSync(fd); fs.closeSync(fd); fd=undefined;
    need(fs.readFileSync(file,'utf8')===expectedRaw,'Ledger changed before claim; stop and reconcile.');
    fs.renameSync(temporary,file);
  } finally { if(fd!==undefined) fs.closeSync(fd); if(fs.existsSync(temporary)) fs.unlinkSync(temporary); }
}
export function runGate(command,{context,statePath,allowTestState=false,owner}={}) {
  need(['check-qa','start-qa','check-release'].includes(command),'Use check-qa, start-qa or check-release.');
  if(command==='start-qa') need(text(owner),'start-qa requires --owner.');
  const ctx=context || createContext(); const file=stateFile(ctx,statePath,allowTestState); const lock=file+'.lock';
  need(!fs.existsSync(lock),'Ledger is locked; do not remove a stale lock automatically.');
  const first=readState(file); const phase=command==='check-release'?'qa_passed':'qa_ready';
  validate(first.state,ctx,phase);
  if(command==='check-release') releaseEvidence(first.state,ctx);
  if(command==='start-qa') {
    const token=randomUUID(); let fd;
    try { fd=fs.openSync(lock,'wx',0o600); }
    catch(error) { if(error.code==='EEXIST') throw new Error('QA claim is locked; another start may be active.'); throw error; }
    try {
      fs.writeFileSync(fd,JSON.stringify({token,owner,pid:process.pid,candidateSha:first.state.candidate.sha})+'\n');
      const current=readState(file);
      need(current.raw===first.raw,'Ledger changed before lock acquisition; stop and reconcile.');
      validate(current.state,ctx,'qa_ready');
      const state=current.state;
      if(state.qa!==undefined) { need(object(state.qa) && (state.qaHistory===undefined || Array.isArray(state.qaHistory)),'Invalid prior QA history.'); state.qaHistory=[...(state.qaHistory||[]),state.qa]; }
      state.phase='qa_running';
      state.qa={status:'running',owner,candidateSha:state.candidate.sha,candidateTree:state.candidate.tree,startedAt:new Date().toISOString(),evidence:[]};
      need(JSON.parse(fs.readFileSync(lock,'utf8')).token===token,'Lock ownership changed; leave it for operator reconciliation.');
      atomicWrite(file,state,current.raw);
    } finally {
      fs.closeSync(fd);
      need(JSON.parse(fs.readFileSync(lock,'utf8')).token===token,'Lock ownership changed; leave it for operator reconciliation.');
      fs.unlinkSync(lock);
    }
  }
  return {status:'PASS',command,batchId:first.state.batchId,candidateSha:first.state.candidate.sha,candidateTree:first.state.candidate.tree,
    statePath:file,testState:statePath!==undefined,phase:command==='start-qa'?'qa_running':phase,
    limitation:'Local preflight/claim only; this does not run QA or grant merge, production access or deployment authority.'};
}
function cli(args) {
  const command=args.shift(); const options={};
  while(args.length) { const flag=args.shift(); need(['--state','--owner'].includes(flag) && args.length>0,'Unknown or incomplete option.'); const key=flag==='--state'?'statePath':'owner'; need(options[key]===undefined,'Duplicate option: '+flag); options[key]=args.shift(); }
  need(options.owner===undefined || command==='start-qa','--owner is only valid with start-qa.');
  return runGate(command,{...options,allowTestState:process.env.DELIVERY_GATE_TEST==='1'});
}
if(process.argv[1] && path.resolve(process.argv[1])===fileURLToPath(import.meta.url)) {
  try { console.log(JSON.stringify(cli(process.argv.slice(2)),null,2)); }
  catch(error) { console.error('Delivery gate: '+error.message); process.exitCode=1; }
}
