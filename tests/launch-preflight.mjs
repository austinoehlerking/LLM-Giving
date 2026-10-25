import assert from 'node:assert/strict';
import {checkConfiguredLaunch} from '../volunteer/codex-cloud/launch-preflight.mjs';
const expected={task_id:'d7d28fc1661bfe18c74631fee26ed024',task_revision:1,context_id:'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',model:'gpt-6.1-sol',thinking:'high',worker_thread_id:'01a17077-0346-71ab-adb1-3c9802a77008'};
// Credential-free projection of the observed native fields. Context ID is synthetic.
const record={origin:'native_controller_record',assignment:{task_id:expected.task_id,task_revision:1,context_id:expected.context_id},create:{request:{model:'gpt-6.1-sol',thinking:'high'},response:{threadId:expected.worker_thread_id,turnId:'01a17077-0d11-73d3-a855-abdbd0991a82',admissionOutcome:'started',isError:false}}};
let count=0;const results=[];
function test(name,fn){fn();count++;results.push(name);}
function denied(name,mutate){test(name,()=>{const candidate=structuredClone(record);mutate(candidate);const r=checkConfiguredLaunch(candidate,expected);assert.equal(r.qualifiesConfiguredLaunch,false);assert.equal(r.canSendSameSettingsFollowup,false);assert.equal(r.providerVerified,false);assert.ok(r.problems.length);});}
test('Actual explicit6.1Sol/high native request+started admission qualifies configured launch without effective readback or fallback key',()=>{const r=checkConfiguredLaunch(record,expected);assert.equal(r.qualifiesConfiguredLaunch,true);assert.equal(r.providerEffectiveReadback,'unavailable');assert.equal(r.providerVerified,false);assert.equal(r.basis,'self-reported controller-recorded configured launch');assert.equal('fallback'in record.create.response,false);});
for(const field of ['model','thinking'])denied('Missing exact launch '+field,r=>{delete r.create.request[field];});
for(const [field,value]of [['model','gpt-6-sol'],['thinking','max']])denied('Mismatched exact launch '+field,r=>{r.create.request[field]=value;});
denied('Bare requested settings without response are rejected',r=>{delete r.create.response;});
denied('Failed native admission is rejected',r=>{r.create.response.isError=true;});
denied('Rejected native admission is rejected',r=>{r.create.response.admissionOutcome='rejected';});
for(const field of ['threadId','turnId'])denied('Missing returned '+field,r=>{delete r.create.response[field];});
for(const field of ['task_id','context_id'])denied('Missing assignment binding '+field,r=>{delete r.assignment[field];});
denied('Stale website revision is rejected',r=>{r.assignment.task_revision=2;});
denied('Unrelated worker original record is rejected',r=>{r.create.response.threadId='different-worker';});
denied('Bare human model assertion is rejected',r=>{r.origin='human_guess';});
denied('Packet-supplied assertion is rejected',r=>{r.origin='packet';});
denied('Known native fallback is rejected',r=>{r.create.response.fallback=true;});
denied('Separately observed fallback is rejected',r=>{r.observed_fallback=true;});
for(const [field,value]of [['model','gpt-6-sol'],['thinking','max']])denied('Conflicting returned '+field+' is rejected when exposed',r=>{r.create.response[field]=value;});
for(const claim of ['provider_verified','provider_authenticated','provider_effective'])denied('Reject unsupported provenance claim '+claim,r=>{r.provenance_claim=claim;});
const followup={request:{threadId:expected.worker_thread_id,model:expected.model,thinking:expected.thinking},response:{threadId:expected.worker_thread_id,turnId:'01a17078-0346-71ab-adb1-3c9802a77008',admissionOutcome:'started',isError:false}};
test('Same-settings repair keeps original configured-launch basis and retains returned turn',()=>{assert.equal(checkConfiguredLaunch({...record,continuations:[followup]},expected).qualifiesConfiguredLaunch,true);});
test('Outgoing explicit same-settings follow-up can be sent before its future admission exists',()=>{const r=checkConfiguredLaunch({...record,continuations:[{request:followup.request}]},expected);assert.equal(r.canSendSameSettingsFollowup,true);assert.equal(r.pendingFollowup,true);assert.equal(r.providerVerified,false);});
for(const [name,mutate]of [['missing model',r=>{delete r.request.model;}],['missing thinking',r=>{delete r.request.thinking;}],['changed thinking',r=>{r.request.thinking='max';}],['different worker',r=>{r.request.threadId='other';}],['failed repair',r=>{r.response.isError=true;}],['stale returned turn',r=>{r.response.turnId=record.create.response.turnId;}],['mismatched returned worker',r=>{r.response.threadId='other';}]])test('Reject continuation '+name,()=>{const turn=structuredClone(followup);mutate(turn);assert.equal(checkConfiguredLaunch({...record,continuations:[turn]},expected).qualifiesConfiguredLaunch,false);});
const steering={request:followup.request,response:{...followup.response,admissionOutcome:'steered'}};
test('Native steered input is admitted to the known active research turn without inventing a fresh turn',()=>{const r=checkConfiguredLaunch({...record,continuations:[followup,steering,steering]},expected);assert.equal(r.qualifiesConfiguredLaunch,true);assert.equal(r.providerVerified,false);});
test('Native steering may address the original still-active admitted turn',()=>{assert.equal(checkConfiguredLaunch({...record,continuations:[{...steering,response:{...steering.response,turnId:record.create.response.turnId}}]},expected).qualifiesConfiguredLaunch,true);});
for(const [name,mutate]of [['unknown active turn',t=>{t.response.turnId='never-admitted';}],['older turn after new start',t=>{t.response.turnId=record.create.response.turnId;}],['different worker',t=>{t.response.threadId='other';}],['failed input',t=>{t.response.isError=true;}],['unknown thinking',t=>{delete t.request.thinking;}]])test('Reject steering '+name,()=>{const t=structuredClone(steering);mutate(t);assert.equal(checkConfiguredLaunch({...record,continuations:[followup,t]},expected).qualifiesConfiguredLaunch,false);});
test('Duplicate continuation turn receipts are stale, not a new admitted turn',()=>{assert.equal(checkConfiguredLaunch({...record,continuations:[followup,followup]},expected).qualifiesConfiguredLaunch,false);});
test('Diagnostic never forwards private native configuration/transport fields or claims authentication',()=>{const r=checkConfiguredLaunch({...record,create:{request:{...record.create.request,environmentConfigId:'private-config'},response:{...record.create.response,attachmentMessageId:'private-transport'}}},expected);assert.equal(r.qualifiesConfiguredLaunch,true);const text=JSON.stringify(r);assert.ok(!text.includes('private-config')&&!text.includes('private-transport'));assert.equal(r.providerVerified,false);});
console.log(JSON.stringify({status:'PASS',checks:count,results,limits:'Structural consistency of caller-supplied native projections only; cannot authenticate record origin or provider-effective execution. No website authorization/policy replacement, model calls or real research.'},null,2));
