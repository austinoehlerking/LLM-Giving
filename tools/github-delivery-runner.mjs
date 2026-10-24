/** Trusted-main metadata runner. Never checks out/evaluates PR code or runs application QA. */
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {POLICY,need,SHA,HASH,positive,hash,readState,batchDigest,readMarker,marker,developGate,batchReady,releaseGate,releasePrerequisites,assertManualRun,releaseRunName} from './github-delivery-policy.mjs';
export function createClient({token=process.env.GITHUB_TOKEN||process.env.GH_TOKEN,fetchImpl=fetch}={}){
  let calls=0;
  async function request(route,{method='GET',body}={}){
    need(++calls<=300,'GitHub metadata request budget exceeded.');
    const repositoryRoute='/repos/'+POLICY.repository,comparePrefix=repositoryRoute+'/compare/';
    const isCompare=typeof route==='string'&&route.startsWith(comparePrefix)&&/^[a-f0-9]{40}\.\.\.[a-f0-9]{40}$/.test(route.slice(comparePrefix.length));
    need(typeof route==='string'&&(route===repositoryRoute||route.startsWith(repositoryRoute+'/')||route==='/graphql')&&!/[\\%#\r\n]/.test(route)&&
      (!route.includes('..')||isCompare)&&(!route.startsWith(repositoryRoute+'/compare')||isCompare),'Untrusted API route.');
    const r=await fetchImpl('https://api.github.com'+route,{method,redirect:'error',signal:AbortSignal.timeout(15000),
      headers:{Accept:'application/vnd.github+json','X-GitHub-Api-Version':'2022-11-28',...(token?{Authorization:'Bearer '+token}:{}),...(body!==undefined?{'Content-Type':'application/json'}:{})},
      ...(body!==undefined?{body:JSON.stringify(body)}:{})});
    need(r.ok,'GitHub metadata HTTP '+r.status+'.');
    const text=await r.text();need(text.length<=5*1024*1024,'Oversized GitHub response.');
    const data=JSON.parse(text);need(!data.errors,'GitHub GraphQL metadata is incomplete.');return data;
  }
  async function pages(route){
    const out=[];for(let page=1;page<=10;page++){const rows=await request(route+(route.includes('?')?'&':'?')+'per_page=100&page='+page);need(Array.isArray(rows),'GitHub list response is missing.');out.push(...rows);if(rows.length<100)return out;}
    throw new Error('GitHub pagination exceeded the bounded limit.');
  }
  async function connection(pr,kind){
    need(positive(pr)&&['reviews','reviewThreads'].includes(kind),'Invalid review connection.');
    const fields=kind==='reviews'?'id updatedAt lastEditedAt':'id isResolved';
    const query='query($owner:String!,$repo:String!,$pr:Int!,$cursor:String){repository(owner:$owner,name:$repo){pullRequest(number:$pr){'+kind+'(first:100,after:$cursor){nodes{'+fields+'} pageInfo{hasNextPage endCursor}}}}}';
    const [owner,repo]=POLICY.repository.split('/');let cursor=null;const out=[];
    for(let page=0;page<10;page++){
      const data=await request('/graphql',{method:'POST',body:{query,variables:{owner,repo,pr,cursor}}});
      const c=data.data?.repository?.pullRequest?.[kind];
      need(c&&Array.isArray(c.nodes)&&typeof c.pageInfo?.hasNextPage==='boolean','Incomplete GraphQL review connection.');
      out.push(...c.nodes);if(!c.pageInfo.hasNextPage)return out;
      need(typeof c.pageInfo.endCursor==='string'&&c.pageInfo.endCursor!==cursor,'Invalid GraphQL pagination.');cursor=c.pageInfo.endCursor;
    }
    throw new Error('Review pagination exceeded the bounded limit.');
  }
  return {request,pages,connection};
}
const prefix='/repos/'+POLICY.repository;
// Same policy order: edited/submitted/created time, then numeric comment ID.
// Select first; never fetch superseded dependencies or fall back from a broken latest record.
function latestDependencyDeclaration(entries,kind,actor,applies){
  const declarations=entries.filter(e=>e.user?.login===actor)
    .map(e=>({entry:e,value:readMarker(e.body,kind)})).filter(x=>x.value&&applies(x.value))
    .map(x=>{const time=Date.parse(x.entry.updated_at??x.entry.submitted_at??x.entry.created_at);need(Number.isFinite(time),'Missing review/comment timestamp.');return {...x,time};});
  declarations.sort((a,b)=>a.time-b.time||Number(a.entry.id)-Number(b.entry.id));
  return declarations.at(-1)?.value;
}
export async function loadBase(client,{includeIssue=true}={}){
  const repository=await client.request(prefix);
  const issue=includeIssue?await client.request(prefix+'/issues/'+POLICY.controlIssue):undefined;
  const refs={};
  for(const name of ['main','develop']){
    const branch=await client.request(prefix+'/branches/'+name);
    need(SHA.test(branch.commit?.sha),'Missing live branch SHA.');
    const commit=await client.request(prefix+'/git/commits/'+branch.commit.sha);
    need(commit.sha===branch.commit.sha&&SHA.test(commit.tree?.sha),'Missing live commit/tree.');
    refs[name]={sha:commit.sha,tree:commit.tree.sha};
  }
  const open=await client.pages(prefix+'/pulls?state=open');
  need(open.every(p=>positive(p.number)),'Malformed open PR list.');
  return {repository,issue,refs,open};
}
export async function loadSnapshot(client,{base,includeManualRuns=true}={}){
  need(typeof includeManualRuns==='boolean','Invalid manual dependency mode.');
  base??=await loadBase(client);
  const state=readState(base.issue),b=state.activeBatch;
  const s={...base,pulls:[],prData:{},ancestry:{},issueComments:await client.pages(prefix+'/issues/'+POLICY.controlIssue+'/comments'),evidence:{},manualRuns:{},statuses:[]};
  const ids=new Set([...base.open.map(p=>p.number),...b.tasks.filter(t=>t.pr!==null).map(t=>t.pr)]);
  for(const id of ids){
    const pr=await client.request(prefix+'/pulls/'+id);need(pr.number===id,'PR identity mismatch.');s.pulls.push(pr);
    const reviews=await client.pages(prefix+'/pulls/'+id+'/reviews');
    const edits=await client.connection(id,'reviews');const updated=new Map(edits.map(e=>[e.id,e.lastEditedAt||e.updatedAt]));
    for(const r of reviews){need(updated.has(r.node_id),'Review edit metadata missing.');r.updated_at=updated.get(r.node_id);}
    s.prData[id]={reviews,comments:await client.pages(prefix+'/issues/'+id+'/comments'),threads:await client.connection(id,'reviewThreads')};
  }
  const proofs=new Set();
  if(b.candidate){
    proofs.add(b.baseMain+'...'+b.candidate.sha);
    for(const t of b.tasks.filter(t=>t.status==='merged')){
      const pr=s.pulls.find(p=>p.number===t.pr);
      if(SHA.test(pr?.merge_commit_sha)){proofs.add(t.head+'...'+pr.merge_commit_sha);proofs.add(pr.merge_commit_sha+'...'+b.candidate.sha);}
    }
  }
  for(const key of proofs){
    const [baseSha,head]=key.split('...');need(SHA.test(baseSha)&&SHA.test(head),'Invalid compare identity.');
    const result=await client.request(prefix+'/compare/'+key);
    s.ancestry[key]={base:baseSha,head,ancestor:result.base_commit?.sha===baseSha&&result.merge_base_commit?.sha===baseSha&&['ahead','identical'].includes(result.status)};
  }
  const q=latestDependencyDeclaration(s.issueComments,'qa',POLICY.trustedActor,
    q=>q.reviewerThread===POLICY.qaThread&&q.batchId===b.id);
  if(q){
    need(Array.isArray(q.evidence)&&q.evidence.length<=20,'Malformed QA evidence.');
    for(const e of q.evidence){
      const match=typeof e.url==='string'&&e.url.match(/^https:\/\/github\.com\/austinoehlerking\/LLM-Giving\/(?:issues|pull)\/[1-9][0-9]*#issuecomment-([1-9][0-9]*)$/);
      need(match&&positive(Number(match[1])),'Invalid evidence URL.');
      if(!s.evidence[e.url])s.evidence[e.url]=await client.request(prefix+'/issues/comments/'+match[1]);
    }
  }
  const runIds=new Set();
  if(includeManualRuns)for(const pr of s.pulls.filter(pr=>pr.state==='open'&&pr.base?.ref==='main'&&pr.head?.ref==='develop')){
    const a=latestDependencyDeclaration(s.prData[pr.number].comments,'release','github-actions[bot]',a=>a.pr===pr.number);
    // releaseGate rejects a stale latest declaration before consuming any run proof.
    if(!a||a.schemaVersion!==1||a.batchId!==b.id||a.sha!==b.candidate?.sha||a.tree!==b.candidate?.tree||a.batchDigest!==batchDigest(state))continue;
    need(positive(a.runId),'Malformed workflow run ID.');runIds.add(a.runId);
  }
  need(runIds.size<=10,'Too many release authorization runs.');
  for(const id of runIds)s.manualRuns[id]=await loadRun(client,id);
  s.statuses=(await client.pages(prefix+'/commits/'+s.refs.develop.sha+'/statuses')).map(x=>({...x,sha:s.refs.develop.sha}));
  return s;
}
export async function loadRun(client,id){
  need(positive(id),'Invalid run ID.');
  return {run:await client.request(prefix+'/actions/runs/'+id),workflow:await client.request(prefix+'/actions/workflows/manual-release.yml'),approvals:await client.request(prefix+'/actions/runs/'+id+'/approvals')};
}
function identity(base){
  return hash(JSON.stringify({issue:base.issue.body,issueUpdated:base.issue.updated_at,refs:base.refs,open:base.open.map(p=>({n:p.number,sha:p.head?.sha,body:p.body,updated:p.updated_at})).sort((a,b)=>a.n-b.n)}));
}
function trustedEnvironment(env,manual=false){
  need(env.GITHUB_REPOSITORY===POLICY.repository,'Unexpected workflow repository.');
  const file=manual?POLICY.manualWorkflow:POLICY.deliveryWorkflow;
  need(env.GITHUB_WORKFLOW_REF===POLICY.repository+'/'+file+'@refs/heads/main','Workflow must be defined on trusted main.');
  need(positive(Number(env.GITHUB_RUN_ID)),'Missing workflow run ID.');
  if(manual)need(env.GITHUB_REF==='refs/heads/main'&&env.GITHUB_EVENT_NAME==='workflow_dispatch'&&env.GITHUB_ACTOR===POLICY.trustedActor,'Manual authorization requires owner dispatch on main.');
}
function resultOf(fn){try{const value=fn();return {state:'success',description:JSON.stringify(value),value};}catch(e){return {state:'failure',description:e.message};}}
export async function refresh(client,env=process.env){
  trustedEnvironment(env);
  // Pending statuses revoke prior decisions before fetching policy/review metadata.
  const base=await loadBase(client,{includeIssue:false});
  const targets=[{sha:base.refs.develop.sha,context:POLICY.contexts.batch},...base.open.map(pr=>({sha:pr.head.sha,context:pr.base.ref==='main'?POLICY.contexts.release:POLICY.contexts.develop,number:pr.number}))];
  const status=async(t,state,description)=>{
    need(SHA.test(t.sha),'Invalid status target.');
    await client.request(prefix+'/statuses/'+t.sha,{method:'POST',body:{state,context:t.context,description:description.replace(/[^\x20-\x7e]/g,'?').slice(0,140),target_url:'https://github.com/'+POLICY.repository+'/actions/runs/'+env.GITHUB_RUN_ID}});
  };
  for(const t of targets)await status(t,'pending','Refreshing trusted-main delivery metadata.');
  try{
    // Take the freshness baseline after our own pending statuses have been written.
    const open=await client.pages(prefix+'/pulls?state=open');
    need(JSON.stringify(base.open.map(p=>[p.number,p.head.sha]).sort())===JSON.stringify(open.map(p=>[p.number,p.head.sha]).sort()),'Open PR heads changed before evaluation.');
    base.open=open;
    base.issue=await client.request(prefix+'/issues/'+POLICY.controlIssue);
    const s=await loadSnapshot(client,{base});
    const fresh=await loadBase(client);need(identity(base)===identity(fresh),'Metadata/branch heads changed during gate evaluation; recheck.');
    for(const t of targets){
      const pr=s.pulls.find(pr=>pr.number===t.number);
      const result=resultOf(()=>t.number?(pr.base.ref==='main'?releaseGate(s,pr):developGate(s,pr)):batchReady(s));
      if(!t.number&&result.state==='success')result.description='batch:'+result.value.batchDigest;
      await status(t,result.state,result.description);
    }
  }catch(e){for(const t of targets)await status(t,'failure','Delivery metadata unavailable: '+e.message);throw e;}
}
export async function authorize(client,env=process.env){
  trustedEnvironment(env,true);
  const prNumber=Number(env.RELEASE_PR);
  need(positive(prNumber)&&SHA.test(env.RELEASE_SHA)&&HASH.test(env.RELEASE_DIGEST),'Invalid manual authorization inputs.');
  // A new approved dispatch replaces prior authorization; prior runs are not its prerequisites.
  const s=await loadSnapshot(client,{includeManualRuns:false}),pr=s.pulls.find(p=>p.number===prNumber);
  need(pr,'Release PR is not open.');
  const ready=releasePrerequisites(s,pr);
  need(ready.sha===env.RELEASE_SHA&&ready.batchDigest===env.RELEASE_DIGEST,'Manual inputs differ from QA candidate/digest.');
  const auth={schemaVersion:1,batchId:ready.batchId,pr:prNumber,sha:ready.sha,tree:ready.tree,batchDigest:ready.batchDigest,runId:Number(env.GITHUB_RUN_ID),runAttempt:Number(env.GITHUB_RUN_ATTEMPT)};
  need(positive(auth.runAttempt),'Missing run attempt.');
  const data=await loadRun(client,auth.runId);
  assertManualRun(data,auth,ready,pr,POLICY,true);
  need(env.GITHUB_SHA===data.run.head_sha,'Dispatched main SHA differs from workflow run.');
  need(identity(s)===identity(await loadBase(client)),'Batch/branch metadata changed before authorization.');
  await client.request(prefix+'/issues/'+prNumber+'/comments',{method:'POST',body:{body:'Manual release authorization recorded after protected environment approval. No merge or deployment performed.\n\n'+marker('release',auth)}});
  // GITHUB_TOKEN comments do not trigger another workflow. workflow_run(completed) refreshes after success.
  return {status:'RECORDED',pr:prNumber,sha:ready.sha,batchDigest:ready.batchDigest};
}
async function cli(){
  need(process.argv.length<=3,'Unexpected runner arguments.');
  const mode=process.argv[2]||'refresh';need(['refresh','authorize'].includes(mode),'Unknown runner mode.');
  const client=createClient();
  const out=mode==='authorize'?await authorize(client):await refresh(client);
  console.log(JSON.stringify(out||{status:'EVALUATED'}));
}
if(process.argv[1]&&path.resolve(process.argv[1])===fileURLToPath(import.meta.url))cli().catch(e=>{console.error('GitHub delivery gate: '+e.message);process.exitCode=1;});
