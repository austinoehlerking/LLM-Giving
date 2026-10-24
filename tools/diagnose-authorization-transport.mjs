/** Bounded workflow-token diagnostics. Never authorizes a release or touches source/production. */
import {createClient} from './github-delivery-runner.mjs';
const repository='austinoehlerking/LLM-Giving';
if(process.env.GITHUB_REPOSITORY!==repository||process.env.GITHUB_REF!=='refs/heads/codex/diagnose-manual-authorization'||process.env.GITHUB_EVENT_NAME!=='workflow_dispatch'||process.env.GITHUB_ACTOR!=='austinoehlerking')throw Error('Unexpected diagnostic context');
const prefix='/repos/'+repository;
const run=Number(process.env.GITHUB_RUN_ID);
if(!Number.isSafeInteger(run)||run<=0)throw Error('Invalid diagnostic run');
const headers={Accept:'application/vnd.github+json','X-GitHub-Api-Version':'2022-11-28',Authorization:'Bearer '+process.env.GITHUB_TOKEN};
const redact=s=>String(s).replace(/\b(?:gh[pousr]_|github_pat_)[A-Za-z0-9_]+/g,'[redacted]').slice(0,300);
async function traceFetch(url,options){
  const response=await fetch(url,options);
  const record={method:options.method||'GET',path:new URL(url).pathname,status:response.status,acceptedPermissions:response.headers.get('x-accepted-github-permissions'),requestId:response.headers.get('x-github-request-id'),rateRemaining:response.headers.get('x-ratelimit-remaining'),rateResource:response.headers.get('x-ratelimit-resource')};
  if(!response.ok){try{record.message=redact((await response.clone().json()).message||'');}catch{record.message='Non-JSON GitHub error';}}
  console.log(JSON.stringify(record));
  return response;
}
const client=createClient({token:process.env.GITHUB_TOKEN,fetchImpl:traceFetch});
try{
  for(const route of ['/actions/runs/40111044606','/actions/workflows/manual-release.yml','/actions/runs/40111044606/approvals'])await client.request(prefix+route);
  const comment=await client.request(prefix+'/issues/27/comments',{method:'POST',body:{body:'Transport permission diagnostic only; this does not authorize promotion or deployment. Diagnostic run '+run+'.'}});
  if(!Number.isSafeInteger(comment.id)||comment.id<=0)throw Error('Missing probe comment identity');
  const removed=await traceFetch('https://api.github.com'+prefix+'/issues/comments/'+comment.id,{method:'DELETE',headers,redirect:'error',signal:AbortSignal.timeout(15000)});
  if(removed.status!==204)throw Error('Probe cleanup failed; comment '+comment.id+' needs owner cleanup');
  console.log(JSON.stringify({status:'ALL_TRANSPORT_PROBES_PASS',releaseAuthorized:false,sourceMutated:false,productionContact:false}));
}catch(e){console.error('Transport diagnostic: '+redact(e.message));process.exitCode=1;}
