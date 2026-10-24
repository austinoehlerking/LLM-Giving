/** One public REST preflight at QA start. No status/workflow/approval polling. */
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {POLICY,assertLocalReady,need,SHA,readState} from './github-delivery-policy.mjs';
import {createClient,loadBase} from './github-delivery-runner.mjs';
export async function loadReadinessProof(client){
  const base=await loadBase(client),b=readState(base.issue).activeBatch,prefix='/repos/'+POLICY.repository;
  const s={...base,pulls:[...base.open],ancestry:{}},proofs=new Set();
  for(const t of b.tasks.filter(t=>t.status==='merged')){
    let pr=s.pulls.find(pr=>pr.number===t.pr);
    if(!pr){pr=await client.request(prefix+'/pulls/'+t.pr);s.pulls.push(pr);}
    if(b.candidate&&SHA.test(pr.merge_commit_sha)){proofs.add(t.head+'...'+pr.merge_commit_sha);proofs.add(pr.merge_commit_sha+'...'+b.candidate.sha);}
  }
  if(b.candidate)proofs.add(b.baseMain+'...'+b.candidate.sha);
  for(const key of proofs){
    const [older,head]=key.split('...');need(SHA.test(older)&&SHA.test(head),'Invalid ancestry identity.');
    const r=await client.request(prefix+'/compare/'+key);
    s.ancestry[key]={base:older,head,ancestor:r.base_commit?.sha===older&&r.merge_base_commit?.sha===older&&['ahead','identical'].includes(r.status)};
  }
  return s;
}
export async function verifyGithubReady(local,{snapshot,client}={}){return assertLocalReady(snapshot??await loadReadinessProof(client??createClient()),local);}
if(process.argv[1]&&path.resolve(process.argv[1])===fileURLToPath(import.meta.url)){
  try{
    need(process.argv.length===3&&process.argv[2]==='--stdin','Use --stdin with the local ledger JSON.');
    process.stdin.setEncoding('utf8');let text='';for await(const part of process.stdin){text+=part;need(text.length<=4*1024*1024,'Oversized local ledger.');}
    console.log(JSON.stringify(await verifyGithubReady(JSON.parse(text))));
  }catch(e){console.error('GitHub batch readiness: '+e.message);process.exitCode=1;}
}
