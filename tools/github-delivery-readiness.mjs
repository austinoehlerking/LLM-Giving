/** Public REST proof from the trusted-main server gate. No local token or GraphQL required. */
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {POLICY,assertLocalReady,need,SHA} from './github-delivery-policy.mjs';
import {createClient} from './github-delivery-runner.mjs';
export async function loadReadinessProof(client){
  const base='/repos/'+POLICY.repository;
  const repository=await client.request(base),issue=await client.request(base+'/issues/'+POLICY.controlIssue),refs={};
  for(const name of ['main','develop']){
    const b=await client.request(base+'/branches/'+name);need(SHA.test(b.commit?.sha),'Missing live branch identity.');
    const c=await client.request(base+'/git/commits/'+b.commit.sha);need(c.sha===b.commit.sha&&SHA.test(c.tree?.sha),'Missing live tree.');refs[name]={sha:c.sha,tree:c.tree.sha};
  }
  const statuses=(await client.pages(base+'/commits/'+refs.develop.sha+'/statuses')).map(s=>({...s,sha:refs.develop.sha}));
  const latest=statuses.filter(s=>s.context===POLICY.contexts.batch).sort((a,b)=>Date.parse(a.created_at)-Date.parse(b.created_at)||a.id-b.id).at(-1);
  const match=latest?.target_url?.match(/^https:\/\/github\.com\/austinoehlerking\/LLM-Giving\/actions\/runs\/([1-9][0-9]*)$/);need(match,'Missing trusted batch-ready publisher link.');
  const run=await client.request(base+'/actions/runs/'+match[1]),workflow=await client.request(base+'/actions/workflows/delivery-gates.yml');
  return {repository,issue,refs,statuses,publisher:{run,workflow}};
}
export async function verifyGithubReady(local,{snapshot,client}={}){return assertLocalReady(snapshot??await loadReadinessProof(client??createClient()),local);}
if(process.argv[1]&&path.resolve(process.argv[1])===fileURLToPath(import.meta.url)){
  try{
    need(process.argv.length===3&&process.argv[2]==='--stdin','Use --stdin with the local ledger JSON.');
    process.stdin.setEncoding('utf8');let text='';for await(const part of process.stdin){text+=part;need(text.length<=4*1024*1024,'Oversized local ledger.');}
    console.log(JSON.stringify(await verifyGithubReady(JSON.parse(text))));
  }catch(e){console.error('GitHub batch readiness: '+e.message);process.exitCode=1;}
}
