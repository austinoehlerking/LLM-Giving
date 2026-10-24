/** GitHub metadata policy only. Role markers are trusted declarations, NOT identity authentication. */
import {createHash} from 'node:crypto';
import fs from 'node:fs';
export const POLICY=Object.freeze(JSON.parse(fs.readFileSync(new URL('../.github/delivery-policy.json',import.meta.url),'utf8')));
export const SHA=/^[a-f0-9]{40}$/;
export const HASH=/^[a-f0-9]{64}$/;
const phases=['developing','qa_ready','qa_running','qa_passed','qa_failed'];
export function need(ok,message){if(!ok)throw new Error(message);}
const object=v=>v!==null&&typeof v==='object'&&!Array.isArray(v);
const text=v=>typeof v==='string'&&v.trim().length>0&&v.length<=3000;
export const positive=v=>Number.isSafeInteger(v)&&v>0;
export const hash=v=>createHash('sha256').update(v).digest('hex');
function keys(v,allowed){need(object(v)&&Object.keys(v).every(k=>allowed.includes(k)),'Invalid or unknown metadata fields.');}
function list(v){need(Array.isArray(v)&&v.length<=1000,'Missing or oversized metadata list.');return v;}
export function marker(kind,value){return '<!-- llm-delivery-'+kind+' '+JSON.stringify(value)+' -->';}
export function readMarker(body,kind,required=false){
  need(typeof body==='string'&&body.length<=131072,'Missing/oversized metadata body.');
  const prefix='<!-- llm-delivery-'+kind;
  const count=body.split(prefix).length-1;
  need(count<=1,'Duplicate '+kind+' marker.');
  if(!count){need(!required,'Missing '+kind+' marker.');return null;}
  const start=body.indexOf(prefix),end=body.indexOf('-->',start);
  need(end>=0,'Unterminated '+kind+' marker.');
  const raw=body.slice(start+prefix.length,end);
  need(/^\s+\{[\s\S]*\}\s*$/.test(raw),'Malformed '+kind+' marker.');
  const out=JSON.parse(raw.trim());need(object(out),'Marker must contain a JSON object.');return out;
}
export function readState(issue,p=POLICY){
  need(issue?.number===p.controlIssue&&issue.user?.login===p.trustedActor&&issue.state==='open','Control issue identity/state is invalid.');
  const body=issue.body;
  need(typeof body==='string'&&body.length<=131072,'Missing/oversized control issue.');
  const tag='<!-- llm-delivery-state:v1 -->';
  need(body.split(tag).length===2,'Control issue needs exactly one state marker.');
  const tail=body.slice(body.indexOf(tag)+tag.length);
  const blocks=[...tail.matchAll(/^[ \t]*\x60{3}json[ \t]*\r?\n([\s\S]*?)^[ \t]*\x60{3}[ \t]*$/gm)];
  need(blocks.length===1&&tail.split('\x60\x60\x60').length===3,'Control state needs exactly one JSON fenced block.');
  const state=JSON.parse(blocks[0][1]);
  keys(state,['schemaVersion','activeBatch','queuedNextBatch']);
  need(state.schemaVersion===1,'Unsupported state schemaVersion.');
  const b=state.activeBatch;
  keys(b,['id','membershipClosed','phase','baseMain','candidate','tasks','blockers']);
  need(text(b.id)&&typeof b.membershipClosed==='boolean'&&phases.includes(b.phase)&&SHA.test(b.baseMain),'Invalid active batch identity/phase.');
  need(Array.isArray(b.blockers),'Batch blockers must be explicit.');
  need(b.candidate===null||(object(b.candidate)&&SHA.test(b.candidate.sha)&&SHA.test(b.candidate.tree)),'Invalid candidate SHA/tree.');
  if(b.candidate)keys(b.candidate,['sha','tree']);
  const ids=new Set(),prs=new Set();
  for(const t of list(b.tasks)){
    keys(t,['id','pr','head','status','reason','humanDecision']);
    need(text(t.id)&&!ids.has(t.id),'Invalid/duplicate task id.');ids.add(t.id);
    need(['in_progress','in_review','reviewed','merged','deferred'].includes(t.status),'Unknown task status.');
    need(t.pr===null||positive(t.pr),'Task PR must be a number or null.');
    need(t.head===null||SHA.test(t.head),'Task head must be a full SHA or null.');
    if(t.pr!==null){need(!prs.has(t.pr),'Duplicate task PR.');prs.add(t.pr);}
    if(['reviewed','merged'].includes(t.status))need(positive(t.pr)&&SHA.test(t.head),'Reviewed/merged tasks need PR and full head.');
    if(t.status==='deferred')need(text(t.reason)&&text(t.humanDecision),'Deferral requires explicit humanDecision and reason.');
  }
  const queued=state.queuedNextBatch??[];const seen=new Set();
  for(const q of list(queued)){keys(q,['pr','head','reason']);need(positive(q.pr)&&SHA.test(q.head)&&text(q.reason)&&!seen.has(q.pr),'Invalid next-batch queue.');seen.add(q.pr);}
  return state;
}
function stable(value){
  if(Array.isArray(value))return value.map(stable);
  if(object(value))return Object.fromEntries(Object.keys(value).sort().map(k=>[k,stable(value[k])]));
  return value;
}
export function batchDigest(state){
  const b=state.activeBatch;
  const tasks=b.tasks.map(t=>({id:t.id,pr:t.pr,head:t.head,status:t.status,...(t.reason!==undefined?{reason:t.reason}:{}),...(t.humanDecision!==undefined?{humanDecision:t.humanDecision}:{})})).sort((a,b)=>a.id<b.id?-1:a.id>b.id?1:0);
  return hash(JSON.stringify(stable({schemaVersion:1,id:b.id,baseMain:b.baseMain,membershipClosed:b.membershipClosed,candidate:b.candidate,tasks})));
}
function repository(s,p){need(s.repository?.full_name===p.repository&&s.repository.default_branch==='main'&&positive(s.repository.id),'Wrong repository/default branch.');}
function sameRepo(pr,s,p){
  need(pr?.base?.repo?.full_name===p.repository&&pr.head?.repo?.full_name===p.repository&&pr.base.repo.id===s.repository.id&&pr.head.repo.id===s.repository.id,'PR must belong to this same repository.');
  need(SHA.test(pr.head.sha),'PR head is not a full SHA.');
}
function ordered(entries){return [...entries].sort((a,b)=>time(a)-time(b)||Number(a.id)-Number(b.id));}
function time(e){const n=Date.parse(e.updated_at??e.submitted_at??e.created_at);need(Number.isFinite(n),'Missing review/comment timestamp.');return n;}
export function assertReviewed(s,pr,task,p=POLICY){
  const d=s.prData?.[pr.number];
  need(d&&Array.isArray(d.reviews)&&Array.isArray(d.comments)&&Array.isArray(d.threads),'Incomplete review data.');
  need(d.threads.every(t=>t.isResolved===true),'Unresolved review thread blocks delivery.');
  const native=new Map();
  for(const r of ordered(d.reviews)){
    need(text(r.user?.login)&&text(r.state),'Incomplete native review.');
    if(['APPROVED','CHANGES_REQUESTED','DISMISSED'].includes(r.state))native.set(r.user.login,r.state);
  }
  need(![...native.values()].includes('CHANGES_REQUESTED'),'Outstanding native CHANGES_REQUESTED review.');
  const verdicts=[];
  for(const r of [...d.reviews,...d.comments]){
    if(r.user?.login!==p.trustedActor)continue;
    const m=readMarker(r.body,'review');
    if(!m)continue;
    keys(m,['schemaVersion','sha','verdict','taskId','reviewerThread']);
    need(m.schemaVersion===1&&SHA.test(m.sha)&&['pass','changes_requested'].includes(m.verdict),'Invalid review declaration.');
    if(m.reviewerThread!==p.reviewerThread||m.taskId!==task.id||m.sha!==pr.head.sha)continue;
    if(r.commit_id!==undefined)need(r.commit_id===m.sha,'Review marker differs from reviewed commit.');
    if(r.state==='PENDING'||r.state==='DISMISSED')continue;
    verdicts.push({...r,verdict:m.verdict});
  }
  const latest=ordered(verdicts).at(-1);
  need(latest?.verdict==='pass','No current independent review PASS (latest verdict wins).');
}
export function developGate(s,pr,p=POLICY){
  repository(s,p);sameRepo(pr,s,p);
  const state=readState(s.issue,p),b=state.activeBatch;
  need(pr.base.ref==='develop'&&pr.state==='open'&&pr.draft===false&&/^codex\/[A-Za-z0-9._/-]+$/.test(pr.head.ref),'Development PR must be an open codex/ branch targeting develop.');
  need(!['qa_running','qa_passed'].includes(b.phase),'Develop merges are frozen during/after QA.');
  const bind=readMarker(pr.body,'task',true);keys(bind,['batchId','taskId']);
  need(bind.batchId===b.id,'PR belongs to a different batch.');
  const t=b.tasks.find(t=>t.id===bind.taskId&&t.pr===pr.number);
  need(t?.status==='reviewed'&&t.head===pr.head.sha,'PR must be a registered reviewed task at its current head.');
  need(b.blockers.length===0,'Active batch has blockers.');
  assertReviewed(s,pr,t,p);
  return {status:'PASS',batchId:b.id,taskId:t.id,sha:pr.head.sha};
}
function ancestor(s,base,head){
  need(SHA.test(base)&&SHA.test(head),'Invalid ancestry identity.');
  const proof=s.ancestry?.[base+'...'+head];
  need(proof?.base===base&&proof.head===head&&proof.ancestor===true,'Missing or failed GitHub ancestry proof.');
}
export function batchReady(s,p=POLICY){
  repository(s,p);const state=readState(s.issue,p),b=state.activeBatch;
  need(b.membershipClosed&&b.tasks.length>0,'Batch membership must be closed and nonempty.');
  need(['qa_ready','qa_running','qa_passed'].includes(b.phase),'Batch is not ready for QA.');
  need(b.blockers.length===0,'Batch blockers must be empty.');
  need(b.candidate&&s.refs?.develop?.sha===b.candidate.sha&&s.refs.develop.tree===b.candidate.tree,'Candidate differs from live develop SHA/tree.');
  need(s.refs?.main?.sha===b.baseMain&&SHA.test(s.refs.main.tree),'Main baseline has changed or is missing.');
  ancestor(s,b.baseMain,b.candidate.sha);
  for(const t of b.tasks){
    if(t.status==='deferred')continue;
    need(t.status==='merged','Batch contains an active/unmerged task.');
    const pr=s.pulls.find(pr=>pr.number===t.pr);
    sameRepo(pr,s,p);
    need(pr.base.ref==='develop'&&pr.merged===true&&pr.state==='closed'&&pr.head.sha===t.head&&SHA.test(pr.merge_commit_sha),'Recorded merged task differs from actual GitHub PR.');
    ancestor(s,t.head,pr.merge_commit_sha);ancestor(s,pr.merge_commit_sha,b.candidate.sha);
    assertReviewed(s,pr,t,p);
  }
  for(const pr of s.pulls.filter(pr=>pr.state==='open'&&pr.base?.ref==='develop')){
    sameRepo(pr,s,p);
    need((state.queuedNextBatch??[]).some(q=>q.pr===pr.number&&q.head===pr.head.sha),'Open develop PR is not explicitly queued for the next batch.');
    need(!b.tasks.some(t=>t.pr===pr.number&&t.status!=='deferred'),'Active member cannot be queued away.');
  }
  return {status:'PASS',batchId:b.id,sha:b.candidate.sha,tree:b.candidate.tree,batchDigest:batchDigest(state),state};
}
function declarations(entries,kind,role,p){
  return ordered(entries.filter(e=>e.user?.login===p.trustedActor).map(e=>({e,m:readMarker(e.body,kind)})).filter(x=>x.m&&x.m.reviewerThread===role).map(x=>({...x.e,value:x.m})));
}
function evidenceComment(url,p){
  const u=new URL(url);
  need(u.origin==='https://github.com'&&u.search===''&&new RegExp('^/'+p.repository+'/(?:issues|pull)/[1-9][0-9]*$').test(u.pathname)&&/^#issuecomment-[1-9][0-9]*$/.test(u.hash),'Evidence must link to an exact repository issue comment.');
  return Number(u.hash.slice('#issuecomment-'.length));
}
export function assertQa(s,ready,p=POLICY){
  const candidates=declarations(list(s.issueComments),'qa',p.qaThread,p).filter(e=>e.value.batchId===ready.batchId);
  const q=candidates.at(-1)?.value;
  need(q,'Missing trusted QA attestation.');
  keys(q,['schemaVersion','batchId','sha','tree','batchDigest','verdict','reviewerThread','evidence']);
  need(q.schemaVersion===1&&q.verdict==='pass'&&q.sha===ready.sha&&q.tree===ready.tree&&q.batchDigest===ready.batchDigest,'QA candidate/tree/digest/verdict mismatch.');
  need(Array.isArray(q.evidence)&&q.evidence.length>0&&q.evidence.length<=20,'QA needs bounded evidence.');
  const seen=new Set();
  for(const e of q.evidence){
    keys(e,['url','sha256']);const id=evidenceComment(e.url,p);
    need(HASH.test(e.sha256)&&!seen.has(id),'Invalid/duplicate evidence hash.');seen.add(id);
    const c=s.evidence?.[e.url];
    need(c?.html_url===e.url&&c.user?.login===p.trustedActor&&typeof c.body==='string'&&c.body.trim().length>0&&c.body.length<=65536,'Evidence comment is missing or untrusted.');
    need(!/(?:\/manage\/|\/return\/[a-f0-9]+#|[A-Z]:[\\/]|storage[\\/]private[\\/]|Bearer\s+[A-Za-z0-9_-]{20,}|<script)/i.test(c.body),'Evidence contains unsanitized private/executable content.');
    need(hash(c.body)===e.sha256,'QA evidence body hash mismatch.');
  }
  return q;
}
export function releasePrerequisites(s,pr,p=POLICY){
  sameRepo(pr,s,p);
  need(pr.base.ref==='main'&&pr.head.ref==='develop'&&pr.state==='open'&&pr.draft===false,'Main accepts only an open same-repository develop release PR.');
  const ready=batchReady(s,p);
  need(ready.state.activeBatch.phase==='qa_passed'&&pr.head.sha===ready.sha,'Release must use the exact QA-passed candidate.');
  ancestor(s,s.refs.main.sha,ready.sha);assertQa(s,ready,p);return ready;
}
export function releaseRunName(pr,sha,digest){return 'Release authorization PR #'+pr+' '+sha+' '+digest;}
export function assertManualRun(data,auth,ready,pr,p=POLICY,inProgress=false){
  const r=data?.run,w=data?.workflow;
  need(r&&w&&r.id===auth.runId&&r.run_attempt===auth.runAttempt&&r.workflow_id===w.id&&w.path===p.manualWorkflow&&r.path===p.manualWorkflow,'Wrong/missing release workflow run identity.');
  need(r.repository?.full_name===p.repository&&r.event==='workflow_dispatch'&&r.head_branch==='main'&&r.head_sha===ready.state.activeBatch.baseMain,'Release authorization must run the trusted main workflow.');
  need(r.actor?.login===p.trustedActor&&r.triggering_actor?.login===p.trustedActor,'Release dispatch/rerun actor is untrusted.');
  need(r.display_title===releaseRunName(pr.number,ready.sha,ready.batchDigest),'Release workflow inputs do not bind this PR/candidate/digest.');
  need(inProgress?r.status==='in_progress':r.status==='completed'&&r.conclusion==='success','Release run has not completed successfully.');
  const approvals=list(data.approvals).filter(a=>Array.isArray(a.environments)&&a.environments.some(e=>e.name===p.environment));
  need(approvals.some(a=>a.state==='approved'&&a.user?.login===p.trustedActor)&&!approvals.some(a=>a.state==='rejected'),'Missing owner environment approval.');
}
export function releaseGate(s,pr,p=POLICY){
  const ready=releasePrerequisites(s,pr,p);
  const entries=list(s.prData?.[pr.number]?.comments).filter(e=>e.user?.login==='github-actions[bot]').map(e=>({e,m:readMarker(e.body,'release')})).filter(x=>x.m&&x.m.pr===pr.number);
  const a=ordered(entries.map(x=>({...x.e,value:x.m}))).at(-1)?.value;
  need(a,'Missing workflow-produced manual release authorization.');
  keys(a,['schemaVersion','batchId','pr','sha','tree','batchDigest','runId','runAttempt']);
  need(a.schemaVersion===1&&a.batchId===ready.batchId&&a.sha===ready.sha&&a.tree===ready.tree&&a.batchDigest===ready.batchDigest&&positive(a.runId)&&positive(a.runAttempt),'Stale/malformed release authorization.');
  assertManualRun(s.manualRuns?.[a.runId],a,ready,pr,p);
  return {status:'PASS',batchId:ready.batchId,sha:ready.sha,batchDigest:ready.batchDigest};
}
export function assertLocalReady(s,local,p=POLICY){
  repository(s,p);const state=readState(s.issue,p),b=state.activeBatch;
  need(b.phase==='qa_ready'&&b.membershipClosed&&b.tasks.length>0&&b.blockers.length===0,'GitHub batch is not closed and ready.');
  need(b.candidate&&s.refs?.develop?.sha===b.candidate.sha&&s.refs.develop.tree===b.candidate.tree&&s.refs.main?.sha===b.baseMain,'Live GitHub candidate/main has changed.');
  need(b.tasks.every(t=>['merged','deferred'].includes(t.status)),'GitHub membership is not terminal.');
  need(local.batchId===b.id&&local.baseMain===b.baseMain&&local.candidate?.sha===b.candidate.sha&&local.candidate.tree===b.candidate.tree,'Local and GitHub batch candidate differ.');
  need(local.tasks.length===b.tasks.length&&b.tasks.every(t=>{const x=local.tasks.find(x=>x.id===t.id);return x?.status===t.status&&(t.status==='merged'?x.commit===t.head:x.reason===t.reason&&x.humanDecision===t.humanDecision);}), 'Local and GitHub task membership differ.');
  const digest=batchDigest(state),latest=ordered(list(s.statuses).filter(x=>x.context===p.contexts.batch)).at(-1);
  need(latest?.state==='success'&&latest.creator?.login==='github-actions[bot]'&&latest.sha===b.candidate.sha&&latest.description==='batch:'+digest,'Live GitHub batch-ready status/digest is not successful.');
  const target=typeof latest.target_url==='string'&&latest.target_url.match(/^https:\/\/github\.com\/austinoehlerking\/LLM-Giving\/actions\/runs\/([1-9][0-9]*)$/);
  need(target,'Batch-ready publisher URL is invalid.');
  const run=s.publisher?.run,workflow=s.publisher?.workflow;
  need(run&&workflow&&run.id===Number(target[1])&&run.repository?.full_name===p.repository&&run.workflow_id===workflow.id&&workflow.path===p.deliveryWorkflow&&run.path===p.deliveryWorkflow,'Batch-ready publisher run/workflow identity differs.');
  need(run.head_branch==='main'&&run.head_sha===s.refs.main.sha&&run.status==='completed'&&run.conclusion==='success'&&['workflow_run','issue_comment','issues','workflow_dispatch','push'].includes(run.event),'Batch-ready publisher is not a successful trusted-main run.');
  const changed=Date.parse(s.issue.updated_at),published=Date.parse(latest.created_at),started=Date.parse(run.run_started_at),finished=Date.parse(run.updated_at);
  need([changed,published,started,finished].every(Number.isFinite)&&changed<=published&&started<=published&&published<=finished,'Control issue changed after the status, or publisher timestamps are missing; await refresh.');
  return {status:'PASS',batchId:b.id,sha:b.candidate.sha,tree:b.candidate.tree,batchDigest:digest};
}
